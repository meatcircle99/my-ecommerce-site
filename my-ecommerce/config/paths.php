<?php
// Define absolute paths for the project root
define('ROOT_PATH', 'D:\\XAMPP\\htdocs\\my-ecommerce\\');
define('CONFIG_PATH', ROOT_PATH . 'config\\');
define('INCLUDES_PATH', ROOT_PATH . 'includes\\');
define('PAGES_PATH', ROOT_PATH . 'pages\\');

// Include database configuration
require_once CONFIG_PATH . 'db.php';
?>