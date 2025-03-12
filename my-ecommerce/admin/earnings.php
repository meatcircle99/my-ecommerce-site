<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Initialize date variables with defaults
$date_from = isset($_GET['date_from']) && !empty(trim($_GET['date_from'])) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) && !empty(trim($_GET['date_to'])) ? trim($_GET['date_to']) : '';
$specific_date = isset($_GET['specific_date']) && !empty(trim($_GET['specific_date'])) ? trim($_GET['specific_date']) : '';
$month_year = isset($_GET['month_year']) && !empty(trim($_GET['month_year'])) ? trim($_GET['month_year']) : '';

// Determine the date range or period to query
$where_clause = "WHERE 1=1";
$params = [];

if ($date_from && $date_to) {
    $where_clause .= " AND DATE(created_at) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
} elseif ($specific_date) {
    $where_clause .= " AND DATE(created_at) = :specific_date";
    $params[':specific_date'] = $specific_date;
} elseif ($month_year) {
    $where_clause .= " AND DATE_FORMAT(created_at, '%Y-%m') = :month_year";
    $params[':month_year'] = $month_year;
} else {
    // Default to today if no filter is applied
    $today = date('Y-m-d');
    $where_clause .= " AND DATE(created_at) = :today";
    $params[':today'] = $today;
}

// Fetch total amount earned for the selected period
$total_earned_sql = "
    SELECT SUM(total_amount) as total_earned 
    FROM orders 
    $where_clause";
$total_earned_stmt = $conn->prepare($total_earned_sql);
foreach ($params as $key => $value) {
    $total_earned_stmt->bindValue($key, $value);
}
$total_earned_stmt->execute();
$total_earned = $total_earned_stmt->fetch(PDO::FETCH_ASSOC)['total_earned'] ?? 0;

// Fetch payment method breakdown for the selected period
$payment_methods_sql = "
    SELECT payment_method, SUM(total_amount) as amount 
    FROM orders 
    $where_clause 
    GROUP BY payment_method";
$payment_methods_stmt = $conn->prepare($payment_methods_sql);
foreach ($params as $key => $value) {
    $payment_methods_stmt->bindValue($key, $value);
}
$payment_methods_stmt->execute();
$payment_methods = $payment_methods_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="earnings-container">
    <div class="header-section">
        <h2><i class="fas fa-money-bill-wave"></i> Earnings Overview</h2>
    </div>

    <!-- Date Filter Section -->
    <div class="date-filter">
        <form method="GET" action="dashboard.php" id="date-filter-form">
            <input type="hidden" name="page" value="earnings">
            <div class="filter-row">
                <div class="filter-item">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-item">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-item">
                    <label for="specific_date">Specific Date</label>
                    <input type="date" name="specific_date" id="specific_date" value="<?php echo htmlspecialchars($specific_date); ?>">
                </div>
                <div class="filter-item">
                    <label for="month_year">Month/Year</label>
                    <input type="month" name="month_year" id="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                </div>
                <button type="submit" class="filter-button"><i class="fas fa-filter"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-card total-earned">
            <h3>Total Amount Earned</h3>
            <p class="amount">₹<?php echo number_format($total_earned, 2); ?></p>
            <p class="period">
                <?php if ($date_from && $date_to): ?>
                    From <?php echo htmlspecialchars($date_from); ?> to <?php echo htmlspecialchars($date_to); ?>
                <?php elseif ($specific_date): ?>
                    On <?php echo htmlspecialchars($specific_date); ?>
                <?php elseif ($month_year): ?>
                    For <?php echo htmlspecialchars($month_year); ?>
                <?php else: ?>
                    Today: <?php echo $today; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="summary-card payment-methods">
            <h3>Earnings by Payment Method</h3>
            <?php if (empty($payment_methods)): ?>
                <p class="no-data">No earnings recorded for the selected period.</p>
            <?php else: ?>
                <table class="breakdown-table">
                    <tbody>
                        <?php foreach ($payment_methods as $method): ?>
                            <tr>
                                <td class="method-name"><?php echo htmlspecialchars($method['payment_method'] ?: 'Unknown'); ?>:</td>
                                <td class="method-amount">₹<?php echo number_format($method['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .earnings-container {
        width: 100%;
        margin: 0;
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .header-section {
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }

    h2 {
        font-family: 'Georgia', serif;
        font-size: 1.75rem;
        color: #2d3748;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }

    h2 i {
        color: #2b6cb0; /* Professional blue for icon */
    }

    .date-filter {
        background: #f7fafc;
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }

    .filter-item {
        flex: 1;
        min-width: 200px;
    }

    .filter-item label {
        display: block;
        font-size: 0.9rem;
        font-weight: 500;
        color: #4a5568;
        margin-bottom: 5px;
    }

    .filter-item input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.9rem;
        color: #2d3748;
        background: #fff;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .filter-item input:focus {
        outline: none;
        border-color: #2b6cb0;
        box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
    }

    .filter-button {
        padding: 8px 16px;
        background: #2c7a7b; /* Rich teal for button */
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: background 0.3s ease, transform 0.2s ease;
    }

    .filter-button:hover {
        background: #225e5f; /* Darker teal on hover */
        transform: translateY(-1px);
    }

    .filter-button:active {
        transform: translateY(0);
    }

    .summary-section {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .summary-card {
        flex: 1;
        min-width: 300px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: box-shadow 0.3s ease;
    }

    .summary-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .summary-card h3 {
        font-family: 'Georgia', serif;
        font-size: 1.25rem;
        color: #2d3748;
        margin: 0 0 15px;
        font-weight: 600;
    }

    .total-earned .amount {
        font-size: 2rem;
        font-weight: 700;
        color: #2b6cb0;
        margin: 0 0 5px;
    }

    .total-earned .period {
        font-size: 0.95rem;
        color: #718096;
        margin: 0;
    }

    .payment-methods {
        display: flex;
        flex-direction: column;
    }

    .breakdown-table {
        width: 100%;
        border-collapse: collapse;
    }

    .breakdown-table tr {
        border-bottom: 1px solid #edf2f7;
    }

    .breakdown-table tr:last-child {
        border-bottom: none;
    }

    .breakdown-table td {
        padding: 10px 0;
        font-size: 0.95rem;
    }

    .method-name {
        color: #4a5568;
        font-weight: 500;
    }

    .method-amount {
        color: #2d3748;
        font-weight: 600;
        text-align: right;
    }

    .no-data {
        text-align: center;
        color: #a0aec0;
        font-style: italic;
        padding: 20px 0;
        font-size: 0.95rem;
    }

    @media (max-width: 768px) {
        .filter-row {
            flex-direction: column;
            gap: 10px;
        }
        .filter-item {
            min-width: 100%;
        }
        .summary-section {
            flex-direction: column;
        }
        h2 {
            font-size: 1.5rem;
        }
        .total-earned .amount {
            font-size: 1.75rem;
        }
        .summary-card h3 {
            font-size: 1.1rem;
        }
    }
</style>