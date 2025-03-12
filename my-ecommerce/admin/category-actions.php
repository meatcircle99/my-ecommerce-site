<?php
function addCategory($conn, $name, $image) {
    try {
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            return "Category '$name' already exists.";
        }
        $target_dir = "../images/";
        $target_file = $target_dir . basename($image);
        if (!empty($image) && !move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            return "Error uploading image.";
        }
        $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
        $stmt->execute([$name, $image]);
        return "Category added successfully!";
    } catch (PDOException $e) {
        return "Error adding category: " . $e->getMessage();
    }
}

function updateCategory($conn, $id, $name, $image) {
    try {
        if ($image && !move_uploaded_file($_FILES['image']['tmp_name'], "../images/" . basename($image))) {
            return "Error uploading image.";
        }
        if ($image) {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $image, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        }
        return "Category updated successfully!";
    } catch (PDOException $e) {
        return "Error updating category: " . $e->getMessage();
    }
}

function deleteCategory($conn, $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return "Category deleted successfully!";
    } catch (PDOException $e) {
        return "Error deleting category: " . $e->getMessage();
    }
}
?>