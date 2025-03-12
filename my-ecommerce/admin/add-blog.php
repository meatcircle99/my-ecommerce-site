<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/my-ecommerce/config/db.php';

// Function to sanitize slugs
function sanitizeSlug($slug) {
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $slug = sanitizeSlug(trim($_POST['slug']));
        $content = $_POST['content'];
        $excerpt = $_POST['excerpt'];
        $author_id = (int)$_POST['author_id'];
        $status = $_POST['status'];
        $meta_title = $_POST['meta_title'];
        $meta_description = $_POST['meta_description'];
        $keywords = $_POST['keywords'];
        $backlinks = $_POST['backlinks'];

        // Handle image upload
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/my-ecommerce/images/';
            $file_name = uniqid() . '_' . basename($_FILES['featured_image']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                $featured_image = $file_name;
            }
        }

        $stmt = $conn->prepare("INSERT INTO blogs (title, slug, content, excerpt, featured_image, author_id, status, meta_title, meta_description, keywords, backlinks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author_id, $status, $meta_title, $meta_description, $keywords, $backlinks]);

        $message = "Blog post added successfully!";
    } catch (Exception $e) {
        $error = "Error adding blog: " . htmlspecialchars($e->getMessage());
    }
}

// Fetch all admins for the dropdown
try {
    $stmt = $conn->query("SELECT id, name AS username FROM admins ORDER BY name ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching admins: " . htmlspecialchars($e->getMessage());
    $admins = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Blog - Meatcircle Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .blog-container {
            width: 100%;
            margin: 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .blog-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .blog-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .blog-title i:hover {
            color: #7c3aed;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 1rem;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            background: #f8fafc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            color: #1e293b;
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .form-group textarea {
            border-radius: 12px;
            resize: vertical;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="file"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #6366f1;
        }
        .btn-submit {
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
        .btn-submit:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .btn-submit:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .success-message, .error-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }
        @media (max-width: 768px) {
            .blog-container {
                margin: 0;
                padding: 15px;
            }
            .form-group input[type="text"],
            .form-group input[type="file"],
            .form-group select,
            .form-group textarea {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="blog-container">
        <h1 class="blog-title"><i class="fas fa-blog"></i> Add New Blog</h1>

        <?php if (isset($message)): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="slug">Slug (SEO URL, e.g., my-blog-post)</label>
                <input type="text" name="slug" id="slug" value="<?php echo isset($_POST['slug']) ? htmlspecialchars($_POST['slug']) : ''; ?>" required pattern="[a-z0-9-]+" title="Slug must contain only lowercase letters, numbers, and hyphens">
            </div>

            <div class="form-group">
                <label for="content">Content</label>
                <textarea name="content" id="content" rows="5" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="excerpt">Excerpt</label>
                <textarea name="excerpt" id="excerpt" rows="3"><?php echo isset($_POST['excerpt']) ? htmlspecialchars($_POST['excerpt']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="featured_image">Upload Featured Image</label>
                <input type="file" name="featured_image" id="featured_image">
            </div>

            <div class="form-group">
                <label for="author_id">Author (select from existing admins)</label>
                <select name="author_id" id="author_id" required>
                    <?php if (empty($admins)): ?>
                        <option value="">No admins available</option>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo htmlspecialchars($admin['id']); ?>" <?php echo isset($_POST['author_id']) && $_POST['author_id'] == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['username']) . " (ID: " . htmlspecialchars($admin['id']) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="draft" <?php echo isset($_POST['status']) && $_POST['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo isset($_POST['status']) && $_POST['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                </select>
            </div>

            <div class="form-group">
                <label for="meta_title">Meta Title</label>
                <input type="text" name="meta_title" id="meta_title" value="<?php echo isset($_POST['meta_title']) ? htmlspecialchars($_POST['meta_title']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="meta_description">Meta Description</label>
                <textarea name="meta_description" id="meta_description" rows="3"><?php echo isset($_POST['meta_description']) ? htmlspecialchars($_POST['meta_description']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="keywords">Keywords</label>
                <textarea name="keywords" id="keywords" rows="2"><?php echo isset($_POST['keywords']) ? htmlspecialchars($_POST['keywords']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="backlinks">Backlinks (format: keyword|URL, comma-separated, e.g., Meatcircle|https://meatcircle.com)</label>
                <textarea name="backlinks" id="backlinks" rows="2"><?php echo isset($_POST['backlinks']) ? htmlspecialchars($_POST['backlinks']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn-submit" <?php echo empty($admins) ? 'disabled' : ''; ?>><i class="fas fa-plus"></i> Add Blog</button>
        </form>
    </div>

    <script>
        // Auto-generate slug based on title
        document.getElementById('title').addEventListener('input', function() {
            const title = this.value;
            const slugInput = document.getElementById('slug');
            let slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            slugInput.value = slug;
        });
    </script>
</body>
</html>