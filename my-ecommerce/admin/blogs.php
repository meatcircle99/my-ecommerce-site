<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Handle blog creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $excerpt = trim($_POST['excerpt']);
    $status = $_POST['status'];
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    $keywords = trim($_POST['keywords']);
    $backlinks = trim($_POST['backlinks']);
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $title));
    $author_id = $_SESSION['admin_id'];

    // Handle featured image upload
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/blogs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $featured_image = $upload_dir . basename($_FILES['featured_image']['name']);
        move_uploaded_file($_FILES['featured_image']['tmp_name'], $featured_image);
    }

    try {
        if ($action === 'create') {
            $sql = "INSERT INTO blogs (title, slug, content, excerpt, featured_image, author_id, status, meta_title, meta_description, keywords, backlinks) 
                    VALUES (:title, :slug, :content, :excerpt, :featured_image, :author_id, :status, :meta_title, :meta_description, :keywords, :backlinks)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':content' => $content,
                ':excerpt' => $excerpt,
                ':featured_image' => $featured_image,
                ':author_id' => $author_id,
                ':status' => $status,
                ':meta_title' => $meta_title,
                ':meta_description' => $meta_description,
                ':keywords' => $keywords,
                ':backlinks' => $backlinks
            ]);
            $message = "Blog created successfully!";
        } elseif ($action === 'edit' && isset($_POST['blog_id'])) {
            $blog_id = (int)$_POST['blog_id'];
            $sql = "UPDATE blogs SET title = :title, slug = :slug, content = :content, excerpt = :excerpt, 
                    featured_image = :featured_image, status = :status, meta_title = :meta_title, 
                    meta_description = :meta_description, keywords = :keywords, backlinks = :backlinks 
                    WHERE id = :blog_id AND author_id = :author_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':slug' => $slug,
                ':content' => $content,
                ':excerpt' => $excerpt,
                ':featured_image' => $featured_image ?? $_POST['existing_image'],
                ':status' => $status,
                ':meta_title' => $meta_title,
                ':meta_description' => $meta_description,
                ':keywords' => $keywords,
                ':backlinks' => $backlinks,
                ':blog_id' => $blog_id,
                ':author_id' => $author_id
            ]);
            $message = "Blog updated successfully!";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle blog deletion
if (isset($_GET['delete']) && isset($_GET['blog_id'])) {
    $blog_id = (int)$_GET['blog_id'];
    $sql = "DELETE FROM blogs WHERE id = :blog_id AND author_id = :author_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':blog_id' => $blog_id, ':author_id' => $_SESSION['admin_id']]);
    $message = "Blog deleted successfully!";
}

// Fetch all blogs for the admin
try {
    $sql = "SELECT * FROM blogs WHERE author_id = :author_id ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':author_id' => $_SESSION['admin_id']]);
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching blogs: " . $e->getMessage();
}

// Fetch single blog for editing
$edit_blog = null;
if (isset($_GET['edit']) && isset($_GET['blog_id'])) {
    $blog_id = (int)$_GET['blog_id'];
    $sql = "SELECT * FROM blogs WHERE id = :blog_id AND author_id = :author_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':blog_id' => $blog_id, ':author_id' => $_SESSION['admin_id']]);
    $edit_blog = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<h2><i class="fas fa-blog" style="color: #4f46e5; margin-right: 10px;"></i> Manage Blogs</h2>
<?php if (isset($message)): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Blog Creation/Editing Form -->
<div class="blog-form">
    <h3><?php echo $edit_blog ? 'Edit Blog' : 'Create New Blog'; ?></h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit_blog ? 'edit' : 'create'; ?>">
        <?php if ($edit_blog): ?>
            <input type="hidden" name="blog_id" value="<?php echo $edit_blog['id']; ?>">
            <input type="hidden" name="existing_image" value="<?php echo $edit_blog['featured_image']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="<?php echo $edit_blog ? htmlspecialchars($edit_blog['title']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="excerpt">Excerpt (Short Description)</label>
            <textarea name="excerpt" id="excerpt" rows="3"><?php echo $edit_blog ? htmlspecialchars($edit_blog['excerpt']) : ''; ?></textarea>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea name="content" id="content" rows="10" class="editor"><?php echo $edit_blog ? $edit_blog['content'] : ''; ?></textarea>
        </div>
        <div class="form-group">
            <label for="featured_image">Featured Image</label>
            <input type="file" name="featured_image" id="featured_image" accept="image/*">
            <?php if ($edit_blog && $edit_blog['featured_image']): ?>
                <img src="<?php echo htmlspecialchars($edit_blog['featured_image']); ?>" alt="Featured Image" style="max-width: 200px; margin-top: 10px;">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="draft" <?php echo $edit_blog && $edit_blog['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo $edit_blog && $edit_blog['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
            </select>
        </div>
        <!-- SEO Fields -->
        <div class="form-group">
            <label for="meta_title">Meta Title (SEO)</label>
            <input type="text" name="meta_title" id="meta_title" value="<?php echo $edit_blog ? htmlspecialchars($edit_blog['meta_title']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="meta_description">Meta Description (SEO)</label>
            <textarea name="meta_description" id="meta_description" rows="3"><?php echo $edit_blog ? htmlspecialchars($edit_blog['meta_description']) : ''; ?></textarea>
        </div>
        <div class="form-group">
            <label for="keywords">Keywords (SEO, comma-separated)</label>
            <input type="text" name="keywords" id="keywords" value="<?php echo $edit_blog ? htmlspecialchars($edit_blog['keywords']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="backlinks">Backlinks (comma-separated URLs)</label>
            <textarea name="backlinks" id="backlinks" rows="3"><?php echo $edit_blog ? htmlspecialchars($edit_blog['backlinks']) : ''; ?></textarea>
        </div>
        <button type="submit" class="submit-btn"><?php echo $edit_blog ? 'Update Blog' : 'Create Blog'; ?></button>
    </form>
</div>

<!-- Blog List -->
<div class="blog-list">
    <h3>All Blogs</h3>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($blogs) > 0): ?>
                <?php foreach ($blogs as $blog): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($blog['title']); ?></td>
                        <td><?php echo ucfirst($blog['status']); ?></td>
                        <td><?php echo $blog['created_at']; ?></td>
                        <td>
                            <a href="blogs.php?edit=1&blog_id=<?php echo $blog['id']; ?>" class="action-btn edit">Edit</a>
                            <a href="blogs.php?delete=1&blog_id=<?php echo $blog['id']; ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this blog?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No blogs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .blog-form { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; }
    .editor { min-height: 300px; }
    .submit-btn { background: #2d6a4f; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    .submit-btn:hover { background: #1a3c34; }
    .blog-list table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
    .blog-list th, .blog-list td { padding: 15px; text-align: left; border-bottom: 1px solid #eceff1; }
    .blog-list th { background: #2d6a4f; color: white; }
    .action-btn { padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px; }
    .action-btn.edit { background: #4f46e5; color: white; }
    .action-btn.delete { background: #d32f2f; color: white; }
    .message { padding: 10px; background: #e8f5e9; color: #2e7d32; border-radius: 5px; margin-bottom: 20px; }
</style>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '.editor',
        plugins: 'advlist autolink lists link image charmap print preview hr anchor pagebreak',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image'
    });
</script>