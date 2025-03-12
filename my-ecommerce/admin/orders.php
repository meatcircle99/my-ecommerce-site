<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim(str_replace('-', ' ', strtolower($_POST['new_status']))); // Normalize to match database format

    // Valid status transitions
    $valid_statuses = ['pending', 'accepted', 'processing', 'ready for shipping', 'shipped', 'out for delivery', 'delivered', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        try {
            // Start transaction to ensure atomic update and clear cancellation note if reverting from cancelled
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE orders SET status = :status, cancellation_note = CASE WHEN :new_status != 'cancelled' THEN NULL ELSE cancellation_note END WHERE id = :order_id");
            $stmt->execute([':status' => $new_status, ':new_status' => $new_status, ':order_id' => $order_id]);
            $conn->commit();
            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

// Pagination settings
$orders_per_page = 30;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $orders_per_page;

// Initialize search and filter variables
$search_query = isset($_GET['search']) && !empty(trim($_GET['search'])) ? trim($_GET['search']) : '';
$filter_by = isset($_GET['filter_by']) && !empty(trim($_GET['filter_by'])) ? trim($_GET['filter_by']) : '';
$filter_value = isset($_GET['filter_value']) && !empty(trim($_GET['filter_value'])) ? trim($_GET['filter_value']) : '';
$date_from = isset($_GET['date_from']) && !empty(trim($_GET['date_from'])) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) && !empty(trim($_GET['date_to'])) ? trim($_GET['date_to']) : '';

// Build the orders SQL query
$sql = "SELECT id, user_id, total_amount, status, payment_method, created_at, cancellation_note 
        FROM orders 
        WHERE 1=1";
$count_sql = "SELECT COUNT(DISTINCT id) as total 
              FROM orders 
              WHERE 1=1";
$params = [];

if ($search_query) {
    $sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at, o.cancellation_note 
            FROM orders o 
            LEFT JOIN order_items oi ON o.id = oi.order_id 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE (CAST(o.id AS CHAR) LIKE :search 
                OR DATE(o.created_at) LIKE :search 
                OR CAST(o.total_amount AS CHAR) LIKE :search 
                OR p.name LIKE :search)";
    $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                  FROM orders o 
                  LEFT JOIN order_items oi ON o.id = oi.order_id 
                  LEFT JOIN products p ON oi.product_id = p.id 
                  WHERE (CAST(o.id AS CHAR) LIKE :search 
                      OR DATE(o.created_at) LIKE :search 
                      OR CAST(o.total_amount AS CHAR) LIKE :search 
                      OR p.name LIKE :search)";
    $params[':search'] = "%$search_query%";
}

if ($filter_by && $filter_value) {
    switch ($filter_by) {
        case 'category':
            $sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at, o.cancellation_note 
                    FROM orders o 
                    JOIN order_items oi ON o.id = oi.order_id 
                    JOIN products p ON oi.product_id = p.id 
                    JOIN categories c ON p.category_id = c.id 
                    WHERE c.name = :filter_value";
            $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                          FROM orders o 
                          JOIN order_items oi ON o.id = oi.order_id 
                          JOIN products p ON oi.product_id = p.id 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE c.name = :filter_value";
            if ($search_query) {
                $sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $count_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $params[':search'] = "%$search_query%";
            }
            $params[':filter_value'] = $filter_value;
            break;
        case 'product':
            $sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at, o.cancellation_note 
                    FROM orders o 
                    JOIN order_items oi ON o.id = oi.order_id 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE p.name = :filter_value";
            $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                          FROM orders o 
                          JOIN order_items oi ON o.id = oi.order_id 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE p.name = :filter_value";
            if ($search_query) {
                $sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $count_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $params[':search'] = "%$search_query%";
            }
            $params[':filter_value'] = $filter_value;
            break;
        case 'item_name':
            $sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at, o.cancellation_note 
                    FROM orders o 
                    JOIN order_items oi ON o.id = oi.order_id 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE p.name LIKE :filter_value";
            $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                          FROM orders o 
                          JOIN order_items oi ON o.id = oi.order_id 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE p.name LIKE :filter_value";
            if ($search_query) {
                $sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $count_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                $params[':search'] = "%$search_query%";
            }
            $params[':filter_value'] = "%$filter_value%";
            break;
        case 'date':
            $sql .= " AND DATE(created_at) = :filter_value";
            $count_sql .= " AND DATE(created_at) = :filter_value";
            $params[':filter_value'] = $filter_value;
            break;
        case 'price':
            list($min, $max) = array_map('floatval', explode('-', $filter_value));
            if ($min !== '') {
                $sql .= " AND total_amount >= :min";
                $count_sql .= " AND total_amount >= :min";
                $params[':min'] = $min;
            }
            if ($max !== '') {
                $sql .= " AND total_amount <= :max";
                $count_sql .= " AND total_amount <= :max";
                $params[':max'] = $max;
            }
            break;
        case 'payment_method':
            $sql .= " AND payment_method = :filter_value";
            $count_sql .= " AND payment_method = :filter_value";
            $params[':filter_value'] = $filter_value;
            break;
        case 'status':
            $sql .= " AND status = :filter_value";
            $count_sql .= " AND status = :filter_value";
            $params[':filter_value'] = $filter_value;
            break;
    }
}

// Add date range filter
if ($date_from && $date_to) {
    $sql .= " AND DATE(created_at) BETWEEN :date_from AND :date_to";
    $count_sql .= " AND DATE(created_at) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
} elseif ($date_from) {
    $sql .= " AND DATE(created_at) >= :date_from";
    $count_sql .= " AND DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
} elseif ($date_to) {
    $sql .= " AND DATE(created_at) <= :date_to";
    $count_sql .= " AND DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Add pagination to the main query
$sql .= " ORDER BY created_at DESC LIMIT :offset, :limit";
?>

<h2><i class="fas fa-shopping-cart" style="color: #4f46e5; margin-right: 10px;"></i> Manage Orders</h2>
<div class="orders-container">
    <!-- Search and Filter Section -->
    <div class="search-filter-container">
        <div class="search-bar">
            <form method="GET" action="dashboard.php" id="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by Order ID, Date, Amount, Items...">
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From Date">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To Date">
                <button type="submit" id="search-icon"><i class="fas fa-search"></i></button>
                <input type="hidden" name="page" value="orders">
                <input type="hidden" name="p" value="<?php echo $page; ?>">
                <input type="hidden" name="filter_by" value="<?php echo htmlspecialchars($filter_by); ?>">
                <input type="hidden" name="filter_value" value="<?php echo htmlspecialchars($filter_value); ?>">
            </form>
        </div>
        <div class="filter-section">
            <form method="GET" action="dashboard.php" id="filter-form">
                <div class="filter-dropdown">
                    <button type="button" class="filter-btn"><i class="fas fa-filter"></i> Filter By <i class="fas fa-caret-down"></i></button>
                    <div class="filter-options">
                        <option data-value="category">Category</option>
                        <option data-value="product">Product</option>
                        <option data-value="item_name">Item Name</option>
                        <option data-value="date">Date</option>
                        <option data-value="price">Price (e.g., 100-500)</option>
                        <option data-value="payment_method">Payment Method</option>
                        <option data-value="status">Status</option>
                    </div>
                </div>
                <input type="hidden" name="filter_by" id="filter_by" value="<?php echo htmlspecialchars($filter_by); ?>">
                <input type="text" name="filter_value" class="filter-input" value="<?php echo htmlspecialchars($filter_value); ?>" placeholder="Select a filter...">
                <button type="submit" class="filter-submit">Apply</button>
                <input type="hidden" name="page" value="orders">
                <input type="hidden" name="p" value="<?php echo $page; ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </form>
        </div>
        <!-- Download Complete Orders Button -->
        <form method="POST" action="generate_pdf.php" target="_blank">
            <input type="hidden" name="action" value="all">
            <button type="submit" name="download_pdf" class="download-btn"><i class="fas fa-download"></i> Download All Orders as PDF</button>
        </form>
        <!-- Download Filtered Orders Form -->
        <div class="download-filtered-section">
            <button type="button" class="download-filtered-btn"><i class="fas fa-download"></i> Download Filtered PDF</button>
            <div class="download-filter-options">
                <form method="POST" action="generate_pdf.php" target="_blank">
                    <input type="hidden" name="action" value="filtered">
                    <div class="form-group">
                        <label for="date_from">Date From:</label>
                        <input type="date" name="date_from" id="date_from">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To:</label>
                        <input type="date" name="date_to" id="date_to">
                    </div>
                    <div class="form-group">
                        <label for="specific_date">Specific Date:</label>
                        <input type="date" name="specific_date" id="specific_date">
                    </div>
                    <div class="form-group">
                        <label for="user_id">User ID:</label>
                        <input type="text" name="user_id" id="user_id" placeholder="Enter User ID">
                    </div>
                    <div class="form-group">
                        <label for="item_name">Item Name:</label>
                        <input type="text" name="item_name" id="item_name" placeholder="Enter Item Name">
                    </div>
                    <button type="submit" name="download_filtered_pdf" class="filter-submit">Download Filtered PDF</button>
                </form>
            </div>
        </div>
    </div>

    <div class="orders-header">
        <div>Created At</div>
        <div>Order ID</div>
        <div>User ID</div>
        <div>Items (with Weight)</div>
        <div>Payment Method</div>
        <div>Order Status</div>
        <div>Total</div>
        <div>Action</div>
    </div>

    <?php
    try {
        // Count total orders for pagination
        $count_stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            if ($key !== ':offset' && $key !== ':limit') {
                $count_stmt->bindValue($key, $value);
            }
        }
        $count_stmt->execute();
        $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = ceil($total_orders / $orders_per_page);

        // Fetch orders
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key !== ':offset' && $key !== ':limit') {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $orders_per_page, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summarized report for date range
        if ($date_from || $date_to) {
            $summary_sql = "
                SELECT p.name AS product_name, oi.quantity AS ordered_weight, COUNT(*) AS quantity_count, SUM(oi.quantity) AS total_weight 
                FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id 
                JOIN products p ON oi.product_id = p.id 
                WHERE 1=1";
            $summary_params = [];
            if ($date_from && $date_to) {
                $summary_sql .= " AND DATE(o.created_at) BETWEEN :date_from AND :date_to";
                $summary_params[':date_from'] = $date_from;
                $summary_params[':date_to'] = $date_to;
            } elseif ($date_from) {
                $summary_sql .= " AND DATE(o.created_at) >= :date_from";
                $summary_params[':date_from'] = $date_from;
            } elseif ($date_to) {
                $summary_sql .= " AND DATE(o.created_at) <= :date_to";
                $summary_params[':date_to'] = $date_to;
            }
            $summary_sql .= " GROUP BY p.name, oi.quantity ORDER BY p.name, oi.quantity";
            $summary_stmt = $conn->prepare($summary_sql);
            foreach ($summary_params as $key => $value) {
                $summary_stmt->bindValue($key, $value);
            }
            $summary_stmt->execute();
            $summary_data = [];
            while ($row = $summary_stmt->fetch(PDO::FETCH_ASSOC)) {
                $quantity = $row['ordered_weight'];
                $product = $row['product_name'];
                if (!isset($summary_data[$product])) {
                    $summary_data[$product] = ['quantities' => [], 'total_weight' => 0];
                }
                $summary_data[$product]['quantities'][$quantity] = $row['quantity_count'];
                $summary_data[$product]['total_weight'] += $row['ordered_weight'] * $row['quantity_count'];
            }

            echo "<div class='summary-report'>";
            echo "<h3>Order Summary for Date Range: " . ($date_from ? htmlspecialchars($date_from) : 'N/A') . " to " . ($date_to ? htmlspecialchars($date_to) : 'N/A') . "</h3>";
            if (empty($summary_data)) {
                echo "<p class='no-data'>No data available for the selected date range.</p>";
            } else {
                // Form for downloading the summary as PDF
                echo "<form method='POST' action='generate_pdf.php' target='_blank' class='download-summary-form'>";
                echo "<input type='hidden' name='action' value='summary'>";
                echo "<input type='hidden' name='date_from' value='" . htmlspecialchars($date_from) . "'>";
                echo "<input type='hidden' name='date_to' value='" . htmlspecialchars($date_to) . "'>";
                echo "<input type='hidden' name='summary_data' value='" . htmlspecialchars(json_encode($summary_data)) . "'>";
                echo "<button type='submit' name='download_summary_pdf' class='download-summary-btn'><i class='fas fa-download'></i> Download Summary as PDF</button>";
                echo "</form>";

                echo "<table class='summary-table'>";
                echo "<thead>";
                echo "<tr>";
                echo "<th>Product</th>";
                echo "<th>Quantity Breakdown</th>";
                echo "<th>Total Weight</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                foreach ($summary_data as $product => $data) {
                    echo "<tr>";
                    echo "<td class='product-name'>" . htmlspecialchars($product) . "</td>";
                    echo "<td>";
                    $quantities = [];
                    foreach ($data['quantities'] as $qty => $count) {
                        $quantities[] = htmlspecialchars(number_format($qty, 2)) . " kg (" . $count . ")";
                    }
                    echo implode(", ", $quantities);
                    echo "</td>";
                    echo "<td class='total-weight'>" . number_format($data['total_weight'], 2) . " kg</td>";
                    echo "</tr>";
                }
                echo "</tbody>";
                echo "</table>";
            }
            echo "</div>";
        }

        if (count($orders) > 0) {
            foreach ($orders as $order) {
                echo "<div class='order-card'>";
                echo "<div class='order-details'>";
                echo "<div>" . htmlspecialchars($order['created_at']) . "</div>";
                echo "<div>" . htmlspecialchars($order['id']) . "</div>";
                echo "<div>" . htmlspecialchars($order['user_id']) . "</div>";

                echo "<div class='items-column'>";
                $item_stmt = $conn->prepare("
                    SELECT p.name AS product_name, oi.quantity AS ordered_weight 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = :order_id
                ");
                $item_stmt->execute([':order_id' => $order['id']]);
                $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($items) > 0) {
                    echo "<ul>";
                    foreach ($items as $item) {
                        $weight_display = !is_null($item['ordered_weight']) ? " (" . number_format($item['ordered_weight'], 2) . " kg)" : "";
                        echo "<li>" . htmlspecialchars($item['product_name']) . htmlspecialchars($weight_display) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>No items found.</p>";
                }
                echo "</div>";

                echo "<div>" . htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')) . "</div>";
                echo "<div>";
                $normalized_status = strtolower(str_replace(' ', '-', $order['status']));
                echo '<span class="status-' . $normalized_status . '">' . htmlspecialchars(ucfirst($order['status'])) . '</span>';
                if ($order['cancellation_note']) {
                    echo '<br><small style="color: #d32f2f;">Cancellation Note: ' . htmlspecialchars($order['cancellation_note']) . '</small>';
                }
                echo "</div>";
                echo "<div>₹" . number_format($order['total_amount'], 2) . "</div>";
                echo "<div class='action-column'>";
                echo "<select name='new_status' class='status-select' data-order-id='" . htmlspecialchars($order['id']) . "' onchange='updateStatus(this)'>";
                $valid_statuses = ['pending', 'accepted', 'processing', 'ready for shipping', 'shipped', 'out for delivery', 'delivered', 'cancelled'];
                foreach ($valid_statuses as $status) {
                    $selected = (str_replace('-', ' ', strtolower($order['status'])) === $status) ? 'selected' : '';
                    $display_status = str_replace(' ', ' ', ucwords(str_replace('-', ' ', $status)));
                    echo "<option value='$status' $selected>$display_status</option>";
                }
                echo "</select>";
                echo "<i class='fas fa-download download-invoice' data-order-id='" . htmlspecialchars($order['id']) . "' title='Download Invoice'></i>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<div class='no-orders'>No orders found.</div>";
        }

        // Pagination
        echo "<div class='pagination'>";
        $prev_page = $page > 1 ? $page - 1 : 1;
        $next_page = $page < $total_pages ? $page + 1 : $total_pages;
        $query_string_prev = http_build_query(array_merge($_GET, ['p' => $prev_page]));
        $query_string_next = http_build_query(array_merge($_GET, ['p' => $next_page]));
        echo "<a href='dashboard.php?$query_string_prev' class='pagination-link " . ($page <= 1 ? 'disabled' : '') . "'><i class='fas fa-chevron-left'></i> Previous</a>";
        echo "<a href='dashboard.php?$query_string_next' class='pagination-link " . ($page >= $total_pages ? 'disabled' : '') . "'><i class='fas fa-chevron-right'></i> Next</a>";
        echo "</div>";
    } catch (PDOException $e) {
        echo "<div class='no-orders'>Error fetching orders: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>

<style>
    .orders-container { width: 100%; margin: 0; padding: 10px; }
    .search-filter-container { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; align-items: center; }
    .search-bar { flex: 1; min-width: 300px; position: relative; display: flex; gap: 10px; align-items: center; }
    .search-bar input[type="text"] { flex: 2; padding: 12px 20px; border: none; border-radius: 25px; background: #ffffff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); font-size: 1rem; color: #1a3c34; transition: box-shadow 0.3s ease; }
    .search-bar input[type="date"] { flex: 1; padding: 12px 20px; border: none; border-radius: 25px; background: #ffffff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); font-size: 1rem; color: #1a3c34; transition: box-shadow 0.3s ease; }
    .search-bar button#search-icon { padding: 10px 15px; border: none; background: none; cursor: pointer; color: #2d6a4f; font-size: 1.2rem; transition: color 0.3s ease; }
    .search-bar button#search-icon:hover { color: #1a3c34; }
    .search-bar input[type="text"]:focus, .search-bar input[type="date"]:focus { outline: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
    .filter-section { position: relative; display: flex; align-items: center; gap: 10px; }
    .filter-dropdown { position: relative; }
    .filter-btn { background: linear-gradient(90deg, #2d6a4f, #1a3c34); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 5px; transition: background 0.3s ease; }
    .filter-btn:hover { background: linear-gradient(90deg, #3a7f5f, #245045); }
    .filter-options { display: none; position: absolute; top: 100%; left: 0; background: #ffffff; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 10; min-width: 150px; }
    .filter-options.show { display: block; }
    .filter-options option { padding: 10px 15px; color: #1a3c34; cursor: pointer; transition: background 0.2s ease; }
    .filter-options option:hover { background: #eef2ff; }
    .filter-input { display: none; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; background: #f9fafb; width: 200px; transition: border-color 0.3s ease; }
    .filter-input.show { display: block; }
    .filter-input:focus { border-color: #2d6a4f; outline: none; }
    .filter-submit { background: linear-gradient(90deg, #2d6a4f, #1a3c34); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .filter-submit:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
    .download-btn { background: linear-gradient(90deg, #4f46e5, #7c3aed); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 5px; transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .download-btn:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4); }
    .download-filtered-section { position: relative; }
    .download-filtered-btn { background: linear-gradient(90deg, #4f46e5, #7c3aed); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 5px; transition: background 0.3s ease; }
    .download-filtered-btn:hover { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .download-filter-options { display: none; position: absolute; top: 100%; right: 0; background: #ffffff; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 10; padding: 15px; width: 300px; }
    .download-filter-options.show { display: block; }
    .download-filter-options .form-group { margin-bottom: 15px; }
    .download-filter-options label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 5px; }
    .download-filter-options input { width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease; }
    .download-filter-options input:focus { outline: none; border-color: #6366f1; }
    .orders-header { display: grid; grid-template-columns: 1fr 0.8fr 0.8fr 2fr 1fr 1fr 1fr 0.8fr; background: #1a3c34; color: white; padding: 15px; border-radius: 8px 8px 0 0; font-weight: 600; text-align: left; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
    .order-card { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 20px; padding: 20px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .order-card:hover { transform: translateY(-5px); box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1); }
    .order-details { display: grid; grid-template-columns: 1fr 0.8fr 0.8fr 2fr 1fr 1fr 1fr 0.8fr; gap: 5px; padding-bottom: 15px; border-bottom: 1px solid #eceff1; align-items: start; }
    .order-details div { font-size: 0.95rem; color: #455a64; }
    .items-column { padding-top: 5px; }
    .items-column ul { list-style: none; padding: 0; margin: 0; max-height: 150px; overflow-y: auto; }
    .items-column li { padding: 6px 0; font-size: 0.9rem; color: #546e7a; border-bottom: 1px dashed #eceff1; }
    .items-column li:last-child { border-bottom: none; }
    .status-select {
        padding: 8px 12px;
        border: 2px solid #4CAF50;
        border-radius: 20px;
        font-size: 0.9rem;
        color: #1a3c34;
        background: #ffffff;
        cursor: pointer;
        transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url('data:image/svg+xml;utf8,<svg fill="%234CAF50" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 35px;
        width: 100%;
    }
    .status-select:hover {
        border-color: #45a049;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        background-color: #f9fff9;
    }
    .status-select:focus {
        outline: none;
        border-color: #388E3C;
        box-shadow: 0 0 0 3px rgba(56, 142, 60, 0.3);
    }
    .status-select option {
        background: #ffffff;
        color: #1a3c34;
        padding: 8px;
    }
    .status-select option:hover {
        background: #e8f5e9;
    }
    .status-pending { 
        background: #fff3e0; 
        color: #e65100; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #e65100; 
    }
    .status-accepted { 
        background: #e1f5fe; 
        color: #01579b; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #01579b; 
    }
    .status-processing { 
        background: #fff8e1; 
        color: #f57c00; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #f57c00; 
    }
    .status-ready-for-shipping { 
        background: #e0f7fa; 
        color: #00695c; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #00695c; 
    }
    .status-shipped { 
        background: #e8f5e9; 
        color: #2e7d32; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #2e7d32; 
    }
    .status-out-for-delivery { 
        background: #fffde7; 
        color: #f9a825; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #f9a825; 
    }
    .status-delivered { 
        background: #e8f5e9; 
        color: #2e7d32; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #2e7d32; 
    }
    .status-cancelled { 
        background: #ffebee; 
        color: #d32f2f; 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 0.85rem; 
        display: inline-block; 
        border: 1px solid #d32f2f; 
    }
    .no-orders { text-align: center; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); color: #78909c; font-size: 1.1rem; }
    .pagination { display: flex; justify-content: center; gap: 20px; margin-top: 20px; }
    .pagination a { background: #ffffff; color: #1a3c34; padding: 10px 20px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); transition: background 0.3s ease, transform 0.2s ease; }
    .pagination a:hover { background: #eef2ff; transform: scale(1.05); }
    .pagination a.disabled { background: #d1d5db; color: #6b7280; cursor: not-allowed; transform: none; }
    .action-column { display: flex; gap: 10px; justify-content: center; align-items: center; }
    .action-column i { cursor: pointer; font-size: 1.2rem; color: #2d6a4f; transition: color 0.3s ease; }
    .action-column i:hover { color: #1a3c34; }
    .summary-report { 
        background: #fff; 
        padding: 20px; 
        border-radius: 8px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
        margin-bottom: 20px; 
        border-left: 5px solid #2d6a4f;
    }
    .summary-report h3 { 
        color: #1a3c34; 
        margin-bottom: 20px; 
        font-size: 1.5rem; 
        border-bottom: 2px solid #eceff1; 
        padding-bottom: 10px; 
    }
    .summary-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 10px; 
    }
    .summary-table th, .summary-table td { 
        padding: 12px 15px; 
        text-align: left; 
        border-bottom: 1px solid #eceff1; 
    }
    .summary-table th { 
        background: #2d6a4f; 
        color: #fff; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 0.9rem; 
    }
    .summary-table tr:nth-child(even) { 
        background: #f9fafb; 
    }
    .summary-table tr:hover { 
        background: #f1f5f9; 
    }
    .summary-table .product-name { 
        font-weight: bold; 
        color: #1a3c34; 
        font-size: 1rem; 
    }
    .summary-table .total-weight { 
        font-weight: bold; 
        color: #ef6c00; 
        font-size: 1rem; 
    }
    .no-data { 
        text-align: center; 
        color: #78909c; 
        font-style: italic; 
        padding: 20px 0; 
    }
    .download-summary-form { 
        margin-bottom: 15px; 
        text-align: right; 
    }
    .download-summary-btn { 
        background: linear-gradient(90deg, #4f46e5, #7c3aed); 
        color: white; 
        padding: 8px 15px; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        font-size: 0.9rem; 
        display: inline-flex; 
        align-items: center; 
        gap: 5px; 
        transition: transform 0.3s ease, box-shadow 0.3s ease; 
    }
    .download-summary-btn:hover { 
        transform: scale(1.05); 
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4); 
    }
    @media (max-width: 768px) {
        .orders-header { display: none; }
        .order-details { grid-template-columns: 1fr; gap: 5px; }
        .items-column { padding-top: 5px; }
        .search-filter-container { flex-direction: column; }
        .search-bar { min-width: 100%; flex-direction: column; align-items: stretch; }
        .search-bar input[type="text"], .search-bar input[type="date"], .search-bar button#search-icon { width: 100%; margin-bottom: 5px; }
        .filter-input { width: 100%; }
        .action-column { justify-content: flex-start; }
        .download-filter-options { width: 100%; right: auto; left: 0; }
        .summary-table th, .summary-table td { padding: 8px; font-size: 0.9rem; }
        .download-summary-form { text-align: center; }
        .status-select { width: 100%; }
    }
</style>

<script>
    function updateStatus(selectElement) {
        const orderId = selectElement.getAttribute('data-order-id');
        const newStatus = selectElement.value;
        fetch('orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `update_status=1&order_id=${orderId}&new_status=${encodeURIComponent(newStatus)}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Status Update Error:', data.error);
            } else {
                // Update the selected option to reflect the change immediately
                selectElement.value = newStatus;
                // Force a refresh of the page to ensure the latest data is loaded
                location.reload();
            }
        })
        .catch(error => {
            console.error('Status Update Error:', error);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const handleSearch = (form) => {
            const formData = new FormData(form);
            const url = 'dashboard.php?' + new URLSearchParams(formData).toString();
            console.log('Search Submitted:', url);
            fetch(url, { method: 'GET' })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(data => {
                    console.log('Search Response Received');
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const newContent = doc.querySelector('.orders-container');
                    if (newContent) {
                        document.querySelector('.orders-container').innerHTML = newContent.innerHTML;
                        attachEventListeners();
                    } else {
                        document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error: Invalid response from server</div>';
                    }
                })
                .catch(error => {
                    console.error('Search Error:', error);
                    document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error loading results</div>';
                });
        };

        function attachEventListeners() {
            const filterBtn = document.querySelector('.filter-btn');
            const filterOptions = document.querySelector('.filter-options');
            const filterInput = document.querySelector('.filter-input');
            const searchForm = document.getElementById('search-form');
            const filterForm = document.getElementById('filter-form');
            const filterByInput = document.getElementById('filter_by');
            const searchIcon = document.querySelector('#search-icon');
            const downloadIcons = document.querySelectorAll('.download-invoice');
            const downloadFilteredBtn = document.querySelector('.download-filtered-btn');
            const downloadFilterOptions = document.querySelector('.download-filter-options');

            if (filterBtn && filterOptions) {
                filterBtn.addEventListener('click', () => {
                    filterOptions.classList.toggle('show');
                });
            }

            if (filterOptions) {
                filterOptions.querySelectorAll('option').forEach(option => {
                    option.addEventListener('click', () => {
                        const value = option.getAttribute('data-value');
                        filterByInput.value = value;
                        filterOptions.classList.remove('show');
                        filterInput.classList.add('show');
                        filterInput.placeholder = `Enter ${value.replace('_', ' ')}...`;
                        filterInput.type = value === 'date' ? 'date' : value === 'price' ? 'text' : 'text';
                        filterInput.focus();
                    });
                });
            }

            if (searchForm && searchIcon) {
                searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    handleSearch(searchForm);
                });
                searchIcon.addEventListener('click', (e) => {
                    e.preventDefault();
                    handleSearch(searchForm);
                });
            }

            if (filterForm) {
                filterForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(filterForm);
                    const url = 'dashboard.php?' + new URLSearchParams(formData).toString();
                    console.log('Filter Form Submitted:', url);
                    fetch(url, { method: 'GET' })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.text();
                        })
                        .then(data => {
                            console.log('Filter Response Received');
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(data, 'text/html');
                            const newContent = doc.querySelector('.orders-container');
                            if (newContent) {
                                document.querySelector('.orders-container').innerHTML = newContent.innerHTML;
                                attachEventListeners();
                            } else {
                                document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error: Invalid response from server</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Filter Error:', error);
                            document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error loading results</div>';
                        });
                });
            }

            if (downloadFilteredBtn && downloadFilterOptions) {
                downloadFilteredBtn.addEventListener('click', () => {
                    downloadFilterOptions.classList.toggle('show');
                });
            }

            const paginationLinks = document.querySelectorAll('.pagination-link');
            paginationLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = link.href;
                    console.log('Pagination Clicked:', url);
                    fetch(url)
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.text();
                        })
                        .then(data => {
                            console.log('Pagination Response Received');
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(data, 'text/html');
                            const newContent = doc.querySelector('.orders-container');
                            if (newContent) {
                                document.querySelector('.orders-container').innerHTML = newContent.innerHTML;
                                attachEventListeners();
                            } else {
                                document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error: Invalid response from server</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Pagination Error:', error);
                            document.querySelector('.orders-container').innerHTML = '<div class="no-orders">Error loading results</div>';
                        });
                });
            });

            downloadIcons.forEach(icon => {
                icon.addEventListener('click', () => {
                    const orderId = icon.getAttribute('data-order-id');
                    window.location.href = `../pages/invoice.php?order_id=${orderId}&is_admin=1`;
                });
            });
        }

        // Polling to check for updates every 30 seconds
        function pollForUpdates() {
            const currentOrders = document.querySelectorAll('.order-card');
            const orderIds = Array.from(currentOrders).map(card => card.querySelector('.order-details div:nth-child(2)').textContent.trim());

            fetch('dashboard.php?page=orders&p=<?php echo $page; ?>&poll=1&search=<?php echo urlencode($search_query); ?>&filter_by=<?php echo urlencode($filter_by); ?>&filter_value=<?php echo urlencode($filter_value); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>', {
                method: 'GET'
            })
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newContent = doc.querySelector('.orders-container');
                if (newContent) {
                    const newOrderCards = newContent.querySelectorAll('.order-card');
                    let updated = false;
                    newOrderCards.forEach(newCard => {
                        const newOrderId = newCard.querySelector('.order-details div:nth-child(2)').textContent.trim();
                        const existingCard = Array.from(currentOrders).find(card => card.querySelector('.order-details div:nth-child(2)').textContent.trim() === newOrderId);
                        if (existingCard) {
                            const newStatus = newCard.querySelector('.order-details div:nth-child(6)').innerHTML;
                            const existingStatus = existingCard.querySelector('.order-details div:nth-child(6)').innerHTML;
                            if (newStatus !== existingStatus) {
                                existingCard.querySelector('.order-details div:nth-child(6)').innerHTML = newStatus;
                                updated = true;
                            }
                        }
                    });
                    if (updated) {
                        console.log('Order status updated via poll');
                    }
                }
            })
            .catch(error => console.error('Polling Error:', error));
        }

        // Start polling
        setInterval(pollForUpdates, 30000); // Poll every 30 seconds
        attachEventListeners();

        const urlParams = new URLSearchParams(window.location.search);
        const filterBy = urlParams.get('filter_by');
        const filterValue = urlParams.get('filter_value');
        const filterInput = document.querySelector('.filter-input');
        if (filterBy && filterValue && filterInput) {
            filterInput.classList.add('show');
            filterInput.value = filterValue;
            filterInput.placeholder = `Enter ${filterBy.replace('_', ' ')}...`;
            filterInput.type = filterBy === 'date' ? 'date' : filterBy === 'price' ? 'text' : 'text';
        }
    });
</script>