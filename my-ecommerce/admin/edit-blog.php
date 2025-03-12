<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/my-ecommerce/config/db.php';

// Fetch all admins for the dropdown
try {
    $stmt = $conn->query("SELECT id, name AS username FROM admins ORDER BY name ASC"); // Use 'name' instead of 'username'
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching admins: " . htmlspecialchars($e->getMessage());
    $admins = [];
}

$blog = null;
if (isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching blog: " . htmlspecialchars($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $slug = $_POST['slug'];
        $content = $_POST['content'];
        $excerpt = $_POST['excerpt'];
        $author_id = (int)$_POST['author_id'];
        $status = $_POST['status'];
        $meta_title = $_POST['meta_title'];
        $meta_description = $_POST['meta_description'];
        $keywords = $_POST['keywords'];
        $backlinks = $_POST['backlinks'];

        $featured_image = $blog['featured_image'];
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/my-ecommerce/images/';
            $file_name = uniqid() . '_' . basename($_FILES['featured_image']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                $featured_image = $file_name;
                if ($blog['featured_image'] && file_exists($upload_dir . $blog['featured_image'])) {
                    unlink($upload_dir . $blog['featured_image']);
                }
            }
        }

        $stmt = $conn->prepare("UPDATE blogs SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, author_id = ?, status = ?, meta_title = ?, meta_description = ?, keywords = ?, backlinks = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author_id, $status, $meta_title, $meta_description, $keywords, $backlinks, $id]);

        $message = "Blog post updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating blog: " . htmlspecialchars($e->getMessage());
    }
}
?>

<div class="main-content">
    <h2>Edit Blog</h2>
    <?php if (isset($message)) echo "<p style='color: green;'>$message</p>"; ?>
    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
    <?php if ($blog): ?>
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($blog['id']); ?>">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (SEO URL):</label>
                    <input type="text" name="slug" id="slug" value="<?php echo htmlspecialchars($blog['slug']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="content">Content:</label>
                    <textarea name="content" id="content" rows="5" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="excerpt">Excerpt:</label>
                    <textarea name="excerpt" id="excerpt" rows="3"><?php echo htmlspecialchars($blog['excerpt'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="featured_image">Upload Featured Image:</label>
                    <input type="file" name="featured_image" id="featured_image">
                    <?php if ($blog['featured_image']): ?>
                        <p>Current Image: <img src="/my-ecommerce/images/<?php echo htmlspecialchars($blog['featured_image']); ?>" alt="Current" style="max-width: 200px; max-height: 100px; margin-top: 10px;"></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="author_id">Author (select from existing admins):</label>
                    <select name="author_id" id="author_id" required>
                        <?php if (empty($admins)): ?>
                            <option value="">No admins available</option>
                        <?php else: ?>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?php echo htmlspecialchars($admin['id']); ?>" <?php echo $blog['author_id'] == $admin['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($admin['username']) . " (ID: " . htmlspecialchars($admin['id']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="draft" <?php echo $blog['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $blog['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meta_title">Meta Title:</label>
                    <input type="text" name="meta_title" id="meta_title" value="<?php echo htmlspecialchars($blog['meta_title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meta_description">Meta Description:</label>
                    <textarea name="meta_description" id="meta_description" rows="3"><?php echo htmlspecialchars($blog['meta_description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="keywords">Keywords:</label>
                    <textarea name="keywords" id="keywords" rows="2"><?php echo htmlspecialchars($blog['keywords'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="backlinks">Backlinks (comma-separated):</label>
                    <textarea name="backlinks" id="backlinks" rows="2"><?php echo htmlspecialchars($blog['backlinks'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-submit" <?php echo empty($admins) ? 'disabled' : ''; ?>>Update Blog</button>
            </form>
        </div>
    <?php else: ?>
        <p>No blog found with the provided ID.</p>
    <?php endif; ?>
</div>

<style>
    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1rem;
    }
    input[type="file"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1rem;
    }
</style>