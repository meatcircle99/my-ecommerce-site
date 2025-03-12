<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';
include '../includes/header.php';

// Function to apply backlinks to text
function applyBacklinks($text, $backlinks) {
    if (empty($backlinks)) {
        return $text;
    }

    // Parse backlinks (format: keyword|URL, comma-separated)
    $backlink_pairs = explode(',', $backlinks);
    foreach ($backlink_pairs as $pair) {
        $pair = trim($pair);
        if (empty($pair)) continue;

        $parts = array_map('trim', explode('|', $pair, 2));
        if (count($parts) === 2) {
            list($keyword, $url) = $parts;
            if ($keyword && $url) {
                // Replace the keyword with a hyperlink
                $text = preg_replace("/\b" . preg_quote($keyword, '/') . "\b/", "<a href='$url' target='_blank' style='color: #124E66; text-decoration: underline;'>$keyword</a>", $text);
            }
        }
    }

    return $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover the latest blog posts from Meatcircle on meat products, recipes, and tips. Stay informed with our expert articles.">
    <meta name="keywords" content="meatcircle blogs, meat recipes, meat products, food blog, cooking tips">
    <meta name="author" content="Meatcircle Team">
    <meta name="robots" content="index, follow">
    <title>Meatcircle Blogs - Recipes, Tips & More</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .blogs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .blogs-title {
            font-size: 2.5rem;
            color: #124E66;
            text-align: center;
            margin-bottom: 40px;
            font-weight: 700;
        }
        .blogs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .blog-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .blog-card:hover {
            transform: translateY(-10px);
        }
        .blog-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .blog-content {
            padding: 20px;
        }
        .blog-title {
            font-size: 1.5rem;
            color: #124E66;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .blog-excerpt {
            font-size: 1rem;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .blog-full-content {
            display: none;
            font-size: 1rem;
            color: #333;
            margin-top: 15px;
            line-height: 1.6;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .blog-meta {
            font-size: 0.9rem;
            color: #999;
            margin-bottom: 15px;
        }
        .read-more {
            display: inline-block;
            padding: 10px 20px;
            background-color: #124E66;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        .read-more:hover {
            background-color: #0f3d52;
        }
        .read-less {
            display: none;
            padding: 10px 20px;
            background-color: #ef4444;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .read-less:hover {
            background-color: #dc2626;
        }
        @media (max-width: 768px) {
            .blogs-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <section class="blogs-container">
        <h1 class="blogs-title">Meatcircle Blogs</h1>
        <div class="blogs-grid">
            <?php
            try {
                $stmt = $conn->prepare("
                    SELECT b.*, u.username AS author_name 
                    FROM blogs b 
                    LEFT JOIN users u ON b.author_id = u.id 
                    WHERE b.status = 'published' 
                    ORDER BY b.created_at DESC
                ");
                $stmt->execute();
                $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($blogs) > 0) {
                    foreach ($blogs as $blog) {
                        echo '<article class="blog-card" data-id="' . htmlspecialchars($blog['id']) . '">';
                        if ($blog['featured_image']) {
                            echo '<img src="/my-ecommerce/images/' . htmlspecialchars($blog['featured_image']) . '" alt="' . htmlspecialchars($blog['title']) . '" class="blog-image" onerror="this.src=\'/my-ecommerce/images/default-blog.jpg\';">';
                        }
                        echo '<div class="blog-content">';
                        echo '<h2 class="blog-title">' . htmlspecialchars($blog['title']) . '</h2>';
                        echo '<p class="blog-meta">By ' . htmlspecialchars($blog['author_name'] ?? 'Meatcircle Team') . ' | ' . date('F j, Y', strtotime($blog['created_at'])) . '</p>';
                        // Apply backlinks to excerpt and content
                        $excerpt = $blog['excerpt'] ? htmlspecialchars($blog['excerpt']) : htmlspecialchars(substr($blog['content'], 0, 150)) . '...';
                        $excerpt_with_links = applyBacklinks($excerpt, $blog['backlinks']);
                        $content_with_links = applyBacklinks(nl2br(htmlspecialchars($blog['content'])), $blog['backlinks']);
                        echo '<p class="blog-excerpt">' . $excerpt_with_links . '</p>';
                        echo '<div class="blog-full-content">' . $content_with_links . '</div>';
                        echo '<button class="read-more" data-toggle="read-more-' . htmlspecialchars($blog['id']) . '">Read More</button>';
                        echo '<button class="read-less" data-toggle="read-more-' . htmlspecialchars($blog['id']) . '">Read Less</button>';
                        echo '</div>';
                        echo '</article>';
                    }
                } else {
                    echo '<p>No published blog posts available yet. Check back soon!</p>';
                }
            } catch (PDOException $e) {
                echo '<p>Error fetching blogs: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
    </section>
    <footer class="footer">
        <div class="footer-links">
            <a href="about.php">About Us</a> |
            <a href="terms.php">Terms & Conditions</a> |
            <a href="contact.php">Contact Us</a>
        </div>
        <p>© 2025 Meatcircle. All rights reserved.</p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const images = document.querySelectorAll('.blog-image');
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src || img.src;
                            observer.unobserve(img);
                        }
                    });
                });
                images.forEach(img => observer.observe(img));
            }

            document.querySelectorAll('.read-more, .read-less').forEach(button => {
                button.addEventListener('click', () => {
                    const blogId = button.getAttribute('data-toggle').replace('read-more-', '');
                    const fullContent = document.querySelector(`.blog-card[data-id="${blogId}"] .blog-full-content`);
                    const readMore = document.querySelector(`.blog-card[data-id="${blogId}"] .read-more`);
                    const readLess = document.querySelector(`.blog-card[data-id="${blogId}"] .read-less`);

                    if (fullContent.style.display === 'none' || fullContent.style.display === '') {
                        fullContent.style.display = 'block';
                        readMore.style.display = 'none';
                        readLess.style.display = 'inline-block';
                    } else {
                        fullContent.style.display = 'none';
                        readMore.style.display = 'inline-block';
                        readLess.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>