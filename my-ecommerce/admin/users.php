<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize search query (default to empty or from GET)
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the SQL query
$sql = "SELECT id, username, phone_number AS phone, email, role, created_at FROM users WHERE 1=1";
$params = [];

if ($search_query) {
    $sql .= " AND (id LIKE :search OR username LIKE :search OR phone_number LIKE :search OR email LIKE :search OR role LIKE :search)";
    $params[':search'] = "%$search_query%";
}

$sql .= " ORDER BY id ASC";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Meatcircle Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .users-container {
            width: 100%; /* Remove side gaps by using full width */
            margin: 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .users-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .users-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .users-title i:hover {
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
            padding: 12px 40px 12px 20px; /* Space for search icon on the right */
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
            right: 15px; /* Positioned inside, to the right of the input */
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
        .btn-refresh {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
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
        .btn-refresh:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .users-table {
            width: 100%;
            border-collapse: collapse; /* Changed to collapse for a sleeker look */
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .users-table th, .users-table td {
            padding: 8px; /* Reduced padding for sleek design */
            text-align: left;
            font-size: 0.85rem; /* Reduced font size for consistency */
            border-bottom: 1px solid #eee; /* Subtle border for sleekness */
        }
        .users-table th {
            background: linear-gradient(90deg, #4f46e5, #7c3aed); /* Same gradient as list-products.php */
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .users-table tr:nth-child(even) {
            background: #f8fafc; /* Same even row color */
        }
        .users-table tr:hover {
            background: #eef2ff; /* Same hover color */
            transition: background 0.2s ease;
        }
        .users-table td {
            color: #475569; /* Same text color */
        }
        .no-users {
            text-align: center;
            padding: 20px; /* Reduced padding for sleekness */
            color: #78909c;
            font-size: 1rem; /* Reduced font size */
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 768px) {
            .users-container {
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
            .users-table th, .users-table td {
                padding: 6px;
                font-size: 0.75rem;
            }
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #6366f1;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
<div class="users-container">
    <h1 class="users-title"><i class="fas fa-users"></i> Manage Users</h1>

    <!-- Search and Refresh Container -->
    <div class="search-refresh-container">
        <div class="search-bar">
            <form id="search-form">
                <input type="text" name="search" value="" placeholder="Search by User ID, Username, Phone, Email, Role...">
                <i class="fas fa-search" id="search-icon"></i>
            </form>
        </div>
        <button class="btn-refresh" id="refresh-btn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <!-- Loading Indicator -->
    <div class="loading" id="loading">Loading...</div>

    <!-- Users Table -->
    <div id="users-table-container">
        <?php
        try {
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($users) > 0) {
                echo "<table class='users-table'>";
                echo "<thead>";
                echo "<tr>";
                echo "<th>User ID</th>";
                echo "<th>Username</th>";
                echo "<th>Phone Number</th>";
                echo "<th>Email</th>";
                echo "<th>Role</th>";
                echo "<th>Created At</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";

                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['phone'] ?: 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role'] ?: 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                    echo "</tr>";
                }

                echo "</tbody>";
                echo "</table>";
            } else {
                echo "<div class='no-users'>No users found matching your search.</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='no-users' style='color: #ef4444;'>Error fetching users: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchForm = document.getElementById('search-form');
            const searchIcon = document.getElementById('search-icon');
            const refreshBtn = document.getElementById('refresh-btn');
            const loading = document.getElementById('loading');
            const usersTableContainer = document.getElementById('users-table-container');

            const handleSearch = (query) => {
                loading.style.display = 'block';
                usersTableContainer.style.display = 'none';

                $.ajax({
                    url: 'users.php',
                    method: 'GET',
                    data: { search: query },
                    success: function(response) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const newTable = doc.querySelector('.users-table');
                        const noUsers = doc.querySelector('.no-users');

                        if (newTable) {
                            usersTableContainer.innerHTML = newTable.outerHTML;
                        } else if (noUsers) {
                            usersTableContainer.innerHTML = noUsers.outerHTML;
                        } else {
                            usersTableContainer.innerHTML = '<div class="no-users">Error loading results</div>';
                        }

                        loading.style.display = 'none';
                        usersTableContainer.style.display = 'block';
                        searchForm.querySelector('input[name="search"]').value = ''; // Clear search input after search
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        usersTableContainer.innerHTML = '<div class="no-users" style="color: #ef4444;">Error loading search results</div>';
                        loading.style.display = 'none';
                        usersTableContainer.style.display = 'block';
                    }
                });
            };

            const handleRefresh = () => {
                loading.style.display = 'block';
                usersTableContainer.style.display = 'none';

                $.ajax({
                    url: 'users.php',
                    method: 'GET',
                    success: function(response) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const newTable = doc.querySelector('.users-table');
                        const noUsers = doc.querySelector('.no-users');

                        if (newTable) {
                            usersTableContainer.innerHTML = newTable.outerHTML;
                        } else if (noUsers) {
                            usersTableContainer.innerHTML = noUsers.outerHTML;
                        } else {
                            usersTableContainer.innerHTML = '<div class="no-users">Error loading results</div>';
                        }

                        loading.style.display = 'none';
                        usersTableContainer.style.display = 'block';
                        searchForm.querySelector('input[name="search"]').value = ''; // Clear search input after refresh
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        usersTableContainer.innerHTML = '<div class="no-users" style="color: #ef4444;">Error loading results</div>';
                        loading.style.display = 'none';
                        usersTableContainer.style.display = 'block';
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
                    e.preventDefault(); // Prevent any default behavior
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