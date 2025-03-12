<?php
// Session is already started in dashboard.php
include '../config/db.php';

// Current and previous month for comparison
$currentMonthStart = date('Y-m-01');
$previousMonthStart = date('Y-m-01', strtotime('-1 month'));
$previousMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Current and previous year for user earnings comparison
$currentYear = date('Y');
$previousYear = $currentYear - 1;

// Real-time data queries
try {
    // Total Users
    $totalUsersStmt = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $totalUsersStmt->fetchColumn();

    // Users Change (percentage change from previous month)
    $previousMonthUsersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?");
    $previousMonthUsersStmt->execute([$previousMonthStart, $previousMonthEnd]);
    $previousMonthUsers = $previousMonthUsersStmt->fetchColumn();
    $usersChange = $previousMonthUsers > 0 ? (($totalUsers - $previousMonthUsers) / $previousMonthUsers * 100) : ($totalUsers > 0 ? 100 : 0);
    $usersChange = round($usersChange, 1);

    // Extra Users Earned (calculate from orders tied to users)
    $currentYearUserEarningsStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE YEAR(created_at) = ?");
    $currentYearUserEarningsStmt->execute([$currentYear]);
    $currentYearUserEarnings = $currentYearUserEarningsStmt->fetchColumn();

    $previousYearUserEarningsStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE YEAR(created_at) = ?");
    $previousYearUserEarningsStmt->execute([$previousYear]);
    $previousYearUserEarnings = $previousYearUserEarningsStmt->fetchColumn();

    $extraUsersEarned = $currentYearUserEarnings - $previousYearUserEarnings;

    // Total Orders
    $totalOrdersStmt = $conn->query("SELECT COUNT(*) FROM orders");
    $totalOrders = $totalOrdersStmt->fetchColumn();

    // Orders Change
    $previousMonthOrdersStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at <= ?");
    $previousMonthOrdersStmt->execute([$previousMonthStart, $previousMonthEnd]);
    $previousMonthOrders = $previousMonthOrdersStmt->fetchColumn();
    $ordersChange = $previousMonthOrders > 0 ? (($totalOrders - $previousMonthOrders) / $previousMonthOrders * 100) : ($totalOrders > 0 ? 100 : 0);
    $ordersChange = round($ordersChange, 1);

    // Extra Orders
    $extraOrders = $totalOrders - $previousMonthOrders;

    // Total Sales
    $totalSalesStmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders");
    $totalSales = $totalSalesStmt->fetchColumn();

    // Sales Change
    $previousMonthSalesStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE created_at >= ? AND created_at <= ?");
    $previousMonthSalesStmt->execute([$previousMonthStart, $previousMonthEnd]);
    $previousMonthSales = $previousMonthSalesStmt->fetchColumn();
    $salesChange = $previousMonthSales > 0 ? (($totalSales - $previousMonthSales) / $previousMonthSales * 100) : ($totalSales > 0 ? 100 : 0);
    $salesChange = round($salesChange, 1);

    // Extra Sales Earned
    $extraSalesEarned = $totalSales - $previousMonthSales;

    // This Week Earnings
    $thisWeekEarningsStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $thisWeekEarningsStmt->execute();
    $thisWeekEarnings = $thisWeekEarningsStmt->fetchColumn();

    // Income Graph Data (weekly and monthly)
    $weeklyLabels = [];
    $weeklyData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime($date));
        $weeklyLabels[] = $dayName;
        $dailyEarningsStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ?");
        $dailyEarningsStmt->execute([$date]);
        $dailyEarnings = $dailyEarningsStmt->fetchColumn();
        $weeklyData[] = $dailyEarnings;
    }

    $monthlyLabels = [];
    $monthlyData = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M', strtotime($month));
        $monthlyLabels[] = $monthName;
        $monthlyEarningsStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $monthlyEarningsStmt->execute([$month]);
        $monthlyEarnings = $monthlyEarningsStmt->fetchColumn();
        $monthlyData[] = $monthlyEarnings;
    }

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Meatcircle Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Georgia&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #F3F4F6, #E5E7EB);
            margin: 0;
            padding: 0;
            color: #1F2937;
        }
        .welcome-container {
            width: 100%;
            margin: 0;
            padding: 30px;
            background: #FFFFFF;
            border-radius: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .welcome-title {
            font-family: 'Georgia', serif;
            font-size: 2.5rem;
            color: #2D6A4F;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .welcome-title i {
            color: #7C3AED;
            font-size: 2rem;
            transition: transform 0.3s ease;
        }
        .welcome-title:hover i {
            transform: rotate(15deg);
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .dashboard-card {
            background: linear-gradient(145deg, #F9FAFB, #F3F4F6);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(45, 106, 79, 0.2);
        }
        .dashboard-card h3 {
            font-size: 1.1rem;
            color: #4B5563;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .dashboard-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: baseline;
            gap: 5px;
        }
        .dashboard-card .change {
            font-size: 1rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 500;
        }
        .dashboard-card .change.positive {
            background: #10B981;
            color: white;
        }
        .dashboard-card .change.negative {
            background: #EF4444;
            color: white;
        }
        .dashboard-card .extra {
            font-size: 0.9rem;
            color: #6B7280;
            font-style: italic;
        }
        .graph-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        .graph-card {
            background: linear-gradient(145deg, #F9FAFB, #F3F4F6);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .graph-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.2);
        }
        .graph-card h3 {
            font-size: 1.3rem;
            color: #2D6A4F;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .graph-card .period-toggle {
            margin-bottom: 15px;
        }
        .graph-card .period-toggle button {
            padding: 8px 15px;
            border: none;
            background: #E5E7EB;
            border-radius: 20px;
            cursor: pointer;
            margin: 0 5px;
            font-weight: 500;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .graph-card .period-toggle button.active {
            background: #7C3AED;
            color: white;
        }
        .graph-card .period-toggle button:hover {
            background: #D1D5DB;
        }
        .stats-card {
            margin-bottom: 15px;
        }
        .stats-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #FBBF24;
            margin: 5px 0;
        }
        canvas {
            max-height: 250px;
            width: 100%;
        }
        .placeholder-text {
            height: 250px;
            background: linear-gradient(135deg, #E0F2FE, #C7E0F4);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
            font-size: 1rem;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .welcome-container {
                padding: 20px;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .graph-section {
                grid-template-columns: 1fr;
            }
            .welcome-title {
                font-size: 2rem;
            }
            .dashboard-card .value {
                font-size: 1.5rem;
            }
            .graph-card h3 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1 class="welcome-title"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>

        <!-- Dashboard Metrics -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-users"></i> Total Users</h3>
                <div class="value"><?php echo number_format($totalUsers); ?> <span class="change <?php echo $usersChange >= 0 ? 'positive' : 'negative'; ?>"><?= $usersChange >= 0 ? '↑' : '↓'; ?> <?php echo abs($usersChange); ?>%</span></div>
                <div class="extra">You made an extra ₹<?php echo number_format($extraUsersEarned, 2); ?> this year</div>
            </div>
            <div class="dashboard-card">
                <h3><i class="fas fa-shopping-cart"></i> Total Orders</h3>
                <div class="value"><?php echo number_format($totalOrders); ?> <span class="change <?php echo $ordersChange >= 0 ? 'positive' : 'negative'; ?>"><?= $ordersChange >= 0 ? '↑' : '↓'; ?> <?php echo abs($ordersChange); ?>%</span></div>
                <div class="extra">You made extra <?php echo number_format($extraOrders); ?> this year</div>
            </div>
            <div class="dashboard-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Sales</h3>
                <div class="value">₹<?php echo number_format($totalSales, 2); ?> <span class="change <?php echo $salesChange >= 0 ? 'positive' : 'negative'; ?>"><?= $salesChange >= 0 ? '↑' : '↓'; ?> <?php echo abs($salesChange); ?>%</span></div>
                <div class="extra">You made an extra ₹<?php echo number_format($extraSalesEarned, 2); ?> this year</div>
            </div>
        </div>

        <!-- Graphs Section -->
        <div class="graph-section">
            <div class="graph-card">
                <h3><i class="fas fa-chart-bar"></i> Income Overview</h3>
                <div class="period-toggle">
                    <button>Month</button>
                    <button class="active">Week</button>
                </div>
                <div class="stats-card">
                    <div>This Week</div>
                    <div class="value">₹<?php echo number_format($thisWeekEarnings, 2); ?></div>
                </div>
                <canvas id="incomeChart"></canvas>
            </div>
            <div class="graph-card">
                <h3><i class="fas fa-eye"></i> Unique Visitor (Placeholder)</h3>
                <div class="period-toggle">
                    <button class="active">Week</button>
                    <button>Month</button>
                </div>
                <div class="stats-card">
                    <div>No Data</div>
                    <div class="value">₹0</div>
                </div>
                <div class="placeholder-text">
                    Add a 'sessions' or 'page_views' table to enable this graph.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Income Chart (Bar Graph)
        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        const incomeChart = new Chart(incomeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weeklyLabels); ?>,
                datasets: [{
                    label: 'Earnings (INR)',
                    data: <?php echo json_encode($weeklyData); ?>,
                    backgroundColor: '#2D6A4F',
                    borderColor: '#2D6A4F',
                    borderWidth: 1,
                    barThickness: 20
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '₹' + value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
                    },
                    x: { ticks: { font: { size: 12 } } }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: context => `₹${context.raw.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` } }
                }
            }
        });

        // Period Toggle Functionality
        document.querySelectorAll('.period-toggle button').forEach(button => {
            button.addEventListener('click', function() {
                const toggle = this.parentElement;
                const isIncome = toggle.parentElement.querySelector('h3').textContent === 'Income Overview';
                toggle.querySelector('.active').classList.remove('active');
                this.classList.add('active');
                if (isIncome) {
                    if (this.textContent === 'Month') {
                        incomeChart.data.labels = <?php echo json_encode($monthlyLabels); ?>;
                        incomeChart.data.datasets[0].data = <?php echo json_encode($monthlyData); ?>;
                    } else {
                        incomeChart.data.labels = <?php echo json_encode($weeklyLabels); ?>;
                        incomeChart.data.datasets[0].data = <?php echo json_encode($weeklyData); ?>;
                    }
                    incomeChart.update();
                }
            });
        });
    </script>
</body>
</html>