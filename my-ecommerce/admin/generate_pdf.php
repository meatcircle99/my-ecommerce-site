<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Include Dompdf library
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Initialize Dompdf with UTF-8 support
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
// Ensure UTF-8 encoding
$options->set('defaultFont', 'DejaVu Sans'); // Default font that supports many Unicode characters
$dompdf = new Dompdf($options);

// Determine action and fetch data
$action = $_POST['action'] ?? 'all';

if ($action === 'summary') {
    // Handle summary report PDF
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : 'N/A';
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : 'N/A';
    $summary_data_json = isset($_POST['summary_data']) ? $_POST['summary_data'] : '';
    $summary_data = json_decode($summary_data_json, true);

    if (empty($summary_data)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('No summary data available to generate PDF.');
    }

    // Generate HTML for summary PDF
    $html = '<!DOCTYPE html>';
    $html .= '<html>';
    $html .= '<head><meta charset="UTF-8"></head>';
    $html .= '<body>';
    $html .= '<h1 style="text-align: center;">Order Summary for Date Range: ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
    $html .= '<thead><tr style="background-color: #2d6a4f; color: white;"><th>Product</th><th>Quantity Breakdown</th><th>Total Weight</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($summary_data as $product => $data) {
        $html .= '<tr>';
        $html .= '<td style="font-weight: bold; color: #1a3c34;">' . htmlspecialchars($product) . '</td>';
        $html .= '<td>';
        $quantities = [];
        foreach ($data['quantities'] as $qty => $count) {
            $quantities[] = htmlspecialchars(number_format($qty, 2)) . ' kg (' . $count . ')';
        }
        $html .= implode(', ', $quantities);
        $html .= '</td>';
        $html .= '<td style="font-weight: bold; color: #ef6c00;">' . number_format($data['total_weight'], 2) . ' kg</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</body></html>';

    // Load HTML content
    $dompdf->loadHtml($html, 'UTF-8');

    // Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render PDF
    $dompdf->render();

    // Stream the PDF
    $dompdf->stream('order_summary_' . date('Ymd') . '.pdf', ['Attachment' => true]);
    exit;
} else {
    // Existing logic for all and filtered orders
    $pdf_sql = "SELECT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at 
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                LEFT JOIN products p ON oi.product_id = p.id 
                WHERE 1=1";
    $pdf_params = [];

    if ($action === 'filtered') {
        $date_from = isset($_POST['date_from']) && !empty(trim($_POST['date_from'])) ? trim($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) && !empty(trim($_POST['date_to'])) ? trim($_POST['date_to']) : '';
        $specific_date = isset($_POST['specific_date']) && !empty(trim($_POST['specific_date'])) ? trim($_POST['specific_date']) : '';
        $user_id = isset($_POST['user_id']) && !empty(trim($_POST['user_id'])) ? trim($_POST['user_id']) : '';
        $item_name = isset($_POST['item_name']) && !empty(trim($_POST['item_name'])) ? trim($_POST['item_name']) : '';

        if ($date_from && $date_to) {
            $pdf_sql .= " AND DATE(o.created_at) BETWEEN :date_from AND :date_to";
            $pdf_params[':date_from'] = $date_from;
            $pdf_params[':date_to'] = $date_to;
        } elseif ($specific_date) {
            $pdf_sql .= " AND DATE(o.created_at) = :specific_date";
            $pdf_params[':specific_date'] = $specific_date;
        }

        if ($user_id) {
            $pdf_sql .= " AND o.user_id = :user_id";
            $pdf_params[':user_id'] = (int)$user_id;
        }

        if ($item_name) {
            $pdf_sql .= " AND p.name LIKE :item_name";
            $pdf_params[':item_name'] = "%$item_name%";
        }
    } else {
        // For all orders, apply current search and filter if present
        $search_query = isset($_GET['search']) && !empty(trim($_GET['search'])) ? trim($_GET['search']) : '';
        $filter_by = isset($_GET['filter_by']) && !empty(trim($_GET['filter_by'])) ? trim($_GET['filter_by']) : '';
        $filter_value = isset($_GET['filter_value']) && !empty(trim($_GET['filter_value'])) ? trim($_GET['filter_value']) : '';

        if ($search_query) {
            $pdf_sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at 
                        FROM orders o 
                        LEFT JOIN order_items oi ON o.id = oi.order_id 
                        LEFT JOIN products p ON oi.product_id = p.id 
                        WHERE (CAST(o.id AS CHAR) LIKE :search 
                            OR DATE(o.created_at) LIKE :search 
                            OR CAST(o.total_amount AS CHAR) LIKE :search 
                            OR p.name LIKE :search)";
            $pdf_params[':search'] = "%$search_query%";
        }

        if ($filter_by && $filter_value) {
            switch ($filter_by) {
                case 'category':
                    $pdf_sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at 
                                FROM orders o 
                                JOIN order_items oi ON o.id = oi.order_id 
                                JOIN products p ON oi.product_id = p.id 
                                JOIN categories c ON p.category_id = c.id 
                                WHERE c.name = :filter_value";
                    if ($search_query) {
                        $pdf_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                        $pdf_params[':search'] = "%$search_query%";
                    }
                    $pdf_params[':filter_value'] = $filter_value;
                    break;
                case 'product':
                    $pdf_sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at 
                                FROM orders o 
                                JOIN order_items oi ON o.id = oi.order_id 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE p.name = :filter_value";
                    if ($search_query) {
                        $pdf_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                        $pdf_params[':search'] = "%$search_query%";
                    }
                    $pdf_params[':filter_value'] = $filter_value;
                    break;
                case 'item_name':
                    $pdf_sql = "SELECT DISTINCT o.id, o.user_id, o.total_amount, o.status, o.payment_method, o.created_at 
                                FROM orders o 
                                JOIN order_items oi ON o.id = oi.order_id 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE p.name LIKE :filter_value";
                    if ($search_query) {
                        $pdf_sql .= " AND (CAST(o.id AS CHAR) LIKE :search OR DATE(o.created_at) LIKE :search OR CAST(o.total_amount AS CHAR) LIKE :search OR p.name LIKE :search)";
                        $pdf_params[':search'] = "%$search_query%";
                    }
                    $pdf_params[':filter_value'] = "%$filter_value%";
                    break;
                case 'date':
                    $pdf_sql .= " AND DATE(o.created_at) = :filter_value";
                    $pdf_params[':filter_value'] = $filter_value;
                    break;
                case 'price':
                    list($min, $max) = array_map('floatval', explode('-', $filter_value));
                    if ($min !== '') {
                        $pdf_sql .= " AND o.total_amount >= :min";
                        $pdf_params[':min'] = $min;
                    }
                    if ($max !== '') {
                        $pdf_sql .= " AND o.total_amount <= :max";
                        $pdf_params[':max'] = $max;
                    }
                    break;
                case 'payment_method':
                    $pdf_sql .= " AND o.payment_method = :filter_value";
                    $pdf_params[':filter_value'] = $filter_value;
                    break;
                case 'status':
                    $pdf_sql .= " AND o.status = :filter_value";
                    $pdf_params[':filter_value'] = $filter_value;
                    break;
            }
        }
    }

    $pdf_sql .= " ORDER BY o.created_at DESC";

    try {
        $pdf_stmt = $conn->prepare($pdf_sql);
        foreach ($pdf_params as $key => $value) {
            $pdf_stmt->bindValue($key, $value);
        }
        $pdf_stmt->execute();
        $orders = $pdf_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate HTML for PDF with explicit UTF-8 encoding
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head><meta charset="UTF-8"></head>';
        $html .= '<body>';
        $html .= '<h1 style="text-align: center;">Orders Report</h1>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;"><th>Created At</th><th>Order ID</th><th>User ID</th><th>Items</th><th>Payment Method</th><th>Status</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($orders as $order) {
            $item_stmt = $conn->prepare("
                SELECT p.name AS product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = :order_id
            ");
            $item_stmt->execute([':order_id' => $order['id']]);
            $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
            $items_list = implode(", ", array_column($items, 'product_name'));

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($order['created_at']) . '</td>';
            $html .= '<td>' . htmlspecialchars($order['id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($order['user_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($items_list) . '</td>';
            $html .= '<td>' . htmlspecialchars($order['payment_method'] ?: 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($order['status']) . '</td>';
            $html .= '<td>₹' . number_format($order['total_amount'], 2) . '</td>'; // Ensure ₹ is encoded
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</body></html>';

        // Load HTML content
        $dompdf->loadHtml($html, 'UTF-8');

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render PDF
        $dompdf->render();

        // Stream the PDF
        $dompdf->stream('orders_report.pdf', ['Attachment' => true]);
        exit;
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error generating PDF: ' . $e->getMessage());
    }
}