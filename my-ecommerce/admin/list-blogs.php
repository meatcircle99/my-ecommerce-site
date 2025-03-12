<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/my-ecommerce/config/db.php';

// Initialize search query (default to empty or from GET)
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the SQL query
$sql = "SELECT b.*, a.name AS author_name FROM blogs b LEFT JOIN admins a ON b.author_id = a.id WHERE 1=1";
$params = [];

if ($search_query) {
    $sql .= " AND (b.title LIKE :search OR b.slug LIKE :search OR b.status LIKE :search OR a.name LIKE :search)";
    $params[':search'] = "%$search_query%";
}

$sql .= " ORDER BY b.created_at DESC";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blogs - Meatcircle Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .blogs-container {
            width: 100%;
            margin: 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .blogs-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .blogs-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .blogs-title i:hover {
            color: #7c3aed;
        }
        .search-refresh-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .search-bar {
            position: relative;
            flex: 1;
            min-width: 300px;
        }
        .search-bar input[type="text"] {
            width: 100%;
            padding: 12px 40px 12px 20px;
            border: none;
            border-radius: 25px;
            background: #f8fafc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            color: #1e293b;
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .search-bar input[type="text"]:focus {
            outline: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #6366f1;
        }
        .search-bar i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6366f1;
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .search-bar i:hover {
            color: #4f46e5;
        }
        .btn-refresh, .btn-add, .btn-edit, .btn-delete {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-refresh, .btn-add {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
        }
        .btn-edit {
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            color: white;
        }
        .btn-delete {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-refresh:hover, .btn-add:hover, .btn-edit:hover, .btn-delete:hover {
            transform: scale(1.05);
        }
        .btn-refresh:hover, .btn-add:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .btn-edit:hover {
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .btn-delete:hover {
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        .blogs-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .blogs-table th, .blogs-table td {
            padding: 8px;
            text-align: left;
            font-size: 0.85rem;
            border-bottom: 1px solid #eee;
        }
        .blogs-table th {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .blogs-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .blogs-table tr:hover {
            background: #eef2ff;
            transition: background 0.2s ease;
        }
        .blogs-table td {
            color: #475569;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-published {
            background: #d1fae5;
            color: #065f46;
        }
        .status-draft {
            background: #fefce8;
            color: #ca8a04;
        }
        .no-blogs {
            text-align: center;
            padding: 20px;
            color: #78909c;
            font-size: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .error-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
            background: #fee2e2;
            color: #991b1b;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #6366f1;
            font-size: 1.1rem;
        }
        @media (max-width: 768px) {
            .blogs-container {
                margin: 0;
                padding: 15px;
            }
            .search-refresh-container {
                flex-direction: column;
                gap: 10px;
            }
            .search-bar {
                min-width: 100%;
            }
            .search-bar i {
                right: 15px;
                font-size: 1rem;
            }
            .blogs-table th, .blogs-table td {
                padding: 6px;
                font-size: 0.75rem;
            }
            .btn-refresh, .btn-add, .btn-edit, .btn-delete {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="blogs-container">
        <h1 class="blogs-title"><i class="fas fa-blog"></i> Manage Blogs</h1>

        <!-- Search and Refresh Container -->
        <div class="search-refresh-container">
            <div class="search-bar">
                <form id="search-form">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by Title, Slug, Author, Status...">
                    <i class="fas fa-search" id="search-icon"></i>
                </form>
            </div>
            <div class="flex space-x-3">
                <button class="btn-refresh" id="refresh-btn"><i class="fas fa-sync-alt"></i> Refresh</button>
                <a href="dashboard.php?page=blogs&subpage=add-blog" class="btn-add"><i class="fas fa-plus"></i> Add Blog</a>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div class="loading" id="loading">Loading...</div>

        <!-- Blogs Table -->
        <div id="blogs-table-container">
            <?php
            try {
                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($blogs) > 0) {
                    echo "<table class='blogs-table'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>Title</th>";
                    echo "<th>Slug</th>";
                    echo "<th>Author</th>";
                    echo "<th>Status</th>";
                    echo "<th>Created At</th>";
                    echo "<th>Actions</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";

                    foreach ($blogs as $blog) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($blog['title']) . "</td>";
                        echo "<td>" . htmlspecialchars($blog['slug']) . "</td>";
                        echo "<td>" . htmlspecialchars($blog['author_name'] ?? 'Unknown') . "</td>";
                        echo "<td><span class='status-badge status-" . htmlspecialchars($blog['status']) . "'>" . htmlspecialchars($blog['status']) . "</span></td>";
                        echo "<td>" . date('Y-m-d H:i:s', strtotime($blog['created_at'])) . "</td>";
                        echo "<td>";
                        echo "<a href='dashboard.php?page=blogs&subpage=edit-blog&id=" . htmlspecialchars($blog['id']) . "' class='btn-edit'><i class='fas fa-edit'></i> Edit</a> ";
                        echo "<a href='dashboard.php?page=blogs&subpage=delete-blog&id=" . htmlspecialchars($blog['id']) . "' onclick='return confirm(\"Are you sure you want to delete this blog?\");' class='btn-delete'><i class='fas fa-trash'></i> Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }

                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<div class='no-blogs'>No blogs found matching your search.</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error-message'>Error fetching blogs: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchForm = document.getElementById('search-form');
            const searchIcon = document.getElementById('search-icon');
            const refreshBtn = document.getElementById('refresh-btn');
            const loading = document.getElementById('loading');
            const blogsTableContainer = document.getElementById('blogs-table-container');

            const handleSearch = (query) => {
                loading.style.display = 'block';
                blogsTableContainer.style.display = 'none';

                $.ajax({
                    url: 'list-blogs.php',
                    method: 'GET',
                    data: { search: query },
                    success: function(response) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const newTable = doc.querySelector('.blogs-table');
                        const noBlogs = doc.querySelector('.no-blogs');
                        const errorMessage = doc.querySelector('.error-message');

                        if (newTable) {
                            blogsTableContainer.innerHTML = newTable.outerHTML;
                        } else if (noBlogs) {
                            blogsTableContainer.innerHTML = noBlogs.outerHTML;
                        } else if (errorMessage) {
                            blogsTableContainer.innerHTML = errorMessage.outerHTML;
                        } else {
                            blogsTableContainer.innerHTML = '<div class="no-blogs">Error loading results</div>';
                        }

                        loading.style.display = 'none';
                        blogsTableContainer.style.display = 'block';
                        searchForm.querySelector('input[name="search"]').value = ''; // Clear search input after search
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        blogsTableContainer.innerHTML = '<div class="error-message">Error loading search results</div>';
                        loading.style.display = 'none';
                        blogsTableContainer.style.display = 'block';
                    }
                });
            };

            const handleRefresh = () => {
                loading.style.display = 'block';
                blogsTableContainer.style.display = 'none';

                $.ajax({
                    url: 'list-blogs.php',
                    method: 'GET',
                    success: function(response) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const newTable = doc.querySelector('.blogs-table');
                        const noBlogs = doc.querySelector('.no-blogs');
                        const errorMessage = doc.querySelector('.error-message');

                        if (newTable) {
                            blogsTableContainer.innerHTML = newTable.outerHTML;
                        } else if (noBlogs) {
                            blogsTableContainer.innerHTML = noBlogs.outerHTML;
                        } else if (errorMessage) {
                            blogsTableContainer.innerHTML = errorMessage.outerHTML;
                        } else {
                            blogsTableContainer.innerHTML = '<div class="no-blogs">Error loading results</div>';
                        }

                        loading.style.display = 'none';
                        blogsTableContainer.style.display = 'block';
                        searchForm.querySelector('input[name="search"]').value = ''; // Clear search input after refresh
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        blogsTableContainer.innerHTML = '<div class="error-message">Error loading results</div>';
                        loading.style.display = 'none';
                        blogsTableContainer.style.display = 'block';
                    }
                });
            };

            if (searchForm) {
                searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const searchInput = searchForm.querySelector('input[name="search"]');
                    const query = searchInput.value.trim();
                    if (query) {
                        handleSearch(query);
                    }
                });
            }

            if (searchIcon) {
                searchIcon.addEventListener('click', (e) => {
                    e.preventDefault();
                    const searchInput = searchForm.querySelector('input[name="search"]');
                    const query = searchInput.value.trim();
                    if (query) {
                        handleSearch(query);
                    } else {
                        console.log('Please enter a search term');
                    }
                });
            }

            if (refreshBtn) {
                refreshBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    handleRefresh();
                });
            }
        });
    </script>
</body>
</html>