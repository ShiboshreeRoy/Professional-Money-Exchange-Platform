<?php
session_start();

// Check if user is logged in and is admin/super admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/config.php';

// Get all settings
$settings_result = $conn->query("SELECT * FROM settings");
$settings = [];
while($setting = $settings_result->fetch_assoc()) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Check if user is super admin
$is_super_admin = false;
$user_check = $conn->query("SELECT super_admin FROM users WHERE id = ".$_SESSION['user_id'])->fetch_assoc();
if ($user_check && $user_check['super_admin']) {
    $is_super_admin = true;
}

// Get statistics for reports
$stats = [];

// Total revenue (sum of all paid cards)
$revenue_result = $conn->query("SELECT SUM(total_amount) as total_revenue FROM cards WHERE status = 'paid'");
$stats['total_revenue'] = $revenue_result->fetch_assoc()['total_revenue'] ?: 0;

// Total commissions earned
$commission_result = $conn->query("SELECT SUM(amount) as total_commission FROM commissions");
$stats['total_commission'] = $commission_result->fetch_assoc()['total_commission'] ?: 0;

// Total transactions
$stats['total_transactions'] = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'];

// Total deposits
$deposit_result = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE type = 'deposit'");
$deposit_stats = $deposit_result->fetch_assoc();
$stats['total_deposits'] = $deposit_stats['count'];
$stats['total_deposit_amount'] = $deposit_stats['total'] ?: 0;

// Total withdrawals
$withdrawal_result = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE type = 'withdrawal'");
$withdrawal_stats = $withdrawal_result->fetch_assoc();
$stats['total_withdrawals'] = $withdrawal_stats['count'];
$stats['total_withdrawal_amount'] = $withdrawal_stats['total'] ?: 0;

// Active users
$stats['active_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];

// Monthly revenue report
$monthly_revenue = [];
$months_query = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           SUM(total_amount) as monthly_revenue,
           COUNT(*) as card_count
    FROM cards 
    WHERE status = 'paid' 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

while($month = $months_query->fetch_assoc()) {
    $monthly_revenue[] = $month;
}

// Daily revenue for last 30 days
$daily_revenue = [];
$days_query = $conn->query("
    SELECT DATE(created_at) as day, 
           SUM(total_amount) as daily_revenue,
           COUNT(*) as card_count
    FROM cards 
    WHERE status = 'paid' 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

while($day = $days_query->fetch_assoc()) {
    $daily_revenue[] = $day;
}

// Top users by revenue
$top_users = [];
$users_query = $conn->query("
    SELECT u.username, u.email, SUM(c.total_amount) as user_revenue, COUNT(c.id) as card_count
    FROM users u
    JOIN cards c ON u.id = c.user_id
    WHERE c.status = 'paid'
    GROUP BY u.id
    ORDER BY user_revenue DESC
    LIMIT 10
");

while($user = $users_query->fetch_assoc()) {
    $top_users[] = $user;
}

// Transaction types breakdown
$type_breakdown = [];
$types_query = $conn->query("
    SELECT type, COUNT(*) as count, SUM(amount) as total
    FROM transactions
    GROUP BY type
");

while($type = $types_query->fetch_assoc()) {
    $type_breakdown[] = $type;
}

// Export functionality
if (isset($_GET['export']) && $is_super_admin) {
    $filename = 'financial_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Financial Report', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Add summary
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Revenue', '$' . number_format($stats['total_revenue'], 2)]);
    fputcsv($output, ['Total Commission', '$' . number_format($stats['total_commission'], 2)]);
    fputcsv($output, ['Total Transactions', $stats['total_transactions']]);
    fputcsv($output, []);
    
    // Add monthly revenue
    fputcsv($output, ['Monthly Revenue']);
    fputcsv($output, ['Month', 'Revenue', 'Cards Count']);
    foreach ($monthly_revenue as $month) {
        fputcsv($output, [$month['month'], '$' . number_format($month['monthly_revenue'], 2), $month['card_count']]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Super Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8fafc;
            padding-top: 20px;
            padding-bottom: 40px;
        }
        .report-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .super-admin-tag {
            background-color: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <!-- Report Header -->
    <header class="report-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-file-invoice-dollar me-2"></i>Financial Reports</h1>
                    <p class="mb-0">Comprehensive financial analysis and reporting</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="me-3">Role: <?php echo $_SESSION['role']; ?><?php if ($is_super_admin): ?> <span class="super-admin-tag">SUPER ADMIN</span><?php endif; ?></span>
                    <a href="dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-arrow-left me-1"></i> Back</a>
                    <?php if ($is_super_admin): ?>
                    <a href="?export=1" class="btn btn-success"><i class="fas fa-download me-1"></i> Export CSV</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="text-muted">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?php echo number_format($stats['total_commission'], 2); ?></div>
                    <div class="text-muted">Total Commission</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_transactions']; ?></div>
                    <div class="text-muted">Total Transactions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['active_users']; ?></div>
                    <div class="text-muted">Active Users</div>
                </div>
            </div>
        </div>

        <!-- Revenue Charts -->
        <div class="row">
            <div class="col-md-6">
                <div class="report-card">
                    <h3><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Revenue</h3>
                    <div class="chart-container">
                        <canvas id="monthlyRevenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h3><i class="fas fa-chart-bar me-2 text-primary"></i>Daily Revenue (Last 30 Days)</h3>
                    <div class="chart-container">
                        <canvas id="dailyRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Analysis -->
        <div class="row">
            <div class="col-md-6">
                <div class="report-card">
                    <h3><i class="fas fa-pie-chart me-2 text-primary"></i>Transaction Types</h3>
                    <div class="chart-container">
                        <canvas id="transactionTypesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h3><i class="fas fa-users me-2 text-primary"></i>Top Revenue Users</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Revenue</th>
                                    <th>Cards</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>$<?php echo number_format($user['user_revenue'], 2); ?></td>
                                    <td><?php echo $user['card_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($top_users)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row">
            <div class="col-md-12">
                <div class="report-card">
                    <h3><i class="fas fa-table me-2 text-primary"></i>Monthly Breakdown</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Revenue</th>
                                    <th>Cards Processed</th>
                                    <th>Average per Card</th>
                                    <th>Commission</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($monthly_revenue as $month): ?>
                                <?php 
                                $avg_per_card = $month['card_count'] > 0 ? $month['monthly_revenue'] / $month['card_count'] : 0;
                                $commission = ($month['monthly_revenue'] * floatval($settings['commission_rate'] ?? 2.00)) / 100;
                                ?>
                                <tr>
                                    <td><?php echo $month['month']; ?></td>
                                    <td>$<?php echo number_format($month['monthly_revenue'], 2); ?></td>
                                    <td><?php echo $month['card_count']; ?></td>
                                    <td>$<?php echo number_format($avg_per_card, 2); ?></td>
                                    <td>$<?php echo number_format($commission, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($monthly_revenue)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Monthly Revenue Chart
        const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode(array_reverse($monthly_revenue)); ?>;
        
        if (monthlyData.length > 0) {
            const monthlyLabels = monthlyData.map(item => item.month);
            const monthlyValues = monthlyData.map(item => parseFloat(item.monthly_revenue));
            
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Monthly Revenue ($)',
                        data: monthlyValues,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Daily Revenue Chart
        const dailyCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_revenue); ?>;
        
        if (dailyData.length > 0) {
            const dailyLabels = dailyData.map(item => item.day);
            const dailyValues = dailyData.map(item => parseFloat(item.daily_revenue));
            
            new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Daily Revenue ($)',
                        data: dailyValues,
                        backgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Transaction Types Chart
        const typesCtx = document.getElementById('transactionTypesChart').getContext('2d');
        const typesData = <?php echo json_encode($type_breakdown); ?>;
        
        if (typesData.length > 0) {
            const typeLabels = typesData.map(item => item.type.charAt(0).toUpperCase() + item.type.slice(1));
            const typeValues = typesData.map(item => parseFloat(item.total));
            const backgroundColors = ['#10b981', '#ef4444', '#3b82f6', '#f59e0b'];
            
            new Chart(typesCtx, {
                type: 'doughnut',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeValues,
                        backgroundColor: backgroundColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
</body>
</html>