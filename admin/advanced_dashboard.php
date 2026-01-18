<?php
session_start();

// Check if user is logged in and is admin/super admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/config.php';

// Check if user is super admin
$is_super_admin = false;
$user_check = $conn->query("SELECT super_admin FROM users WHERE id = ".$_SESSION['user_id'])->fetch_assoc();
if ($user_check && $user_check['super_admin']) {
    $is_super_admin = true;
}

// Get all users
$users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Get all cards with user info
$cards_result = $conn->query("SELECT c.*, u.username FROM cards c JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");

// Get all transactions
$transactions_result = $conn->query("SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 20");

// Get all notifications
$notifications_result = $conn->query("SELECT n.*, u.username FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 10");

// Get all settings
$settings_result = $conn->query("SELECT * FROM settings ORDER BY setting_key ASC");
$settings = [];
while($setting = $settings_result->fetch_assoc()) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_cards = $conn->query("SELECT COUNT(*) as count FROM cards")->fetch_assoc()['count'];
$total_transactions = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'];
$pending_cards = $conn->query("SELECT COUNT(*) as count FROM cards WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_cards = $conn->query("SELECT COUNT(*) as count FROM cards WHERE status = 'approved'")->fetch_assoc()['count'];
$total_balance = $conn->query("SELECT SUM(balance) as total FROM users")->fetch_assoc()['total'] ?: 0;

// Get recent activity
$recent_activity = $conn->query("
    (SELECT 'card' as type, id, created_at, CONCAT('New card submitted by ', u.username) as description 
     FROM cards c JOIN users u ON c.user_id = u.id 
     ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'transaction' as type, id, created_at, CONCAT('New transaction by ', u.username) as description 
     FROM transactions t JOIN users u ON t.user_id = u.id 
     ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'user' as type, id, created_at, CONCAT('New user registered: ', username) as description 
     FROM users 
     ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC 
    LIMIT 10
");

// Handle card status update
if (isset($_GET['update_status']) && isset($_GET['card_id'])) {
    $card_id = intval($_GET['card_id']);
    $status = $_GET['update_status'];
    
    if (in_array($status, ['pending', 'approved', 'rejected', 'paid'])) {
        $stmt = $conn->prepare("UPDATE cards SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $card_id);
        
        if ($stmt->execute()) {
            // If card is approved/paid, add commission
            if ($status === 'paid') {
                $card_data = $conn->query("SELECT * FROM cards WHERE id = $card_id")->fetch_assoc();
                $commission_rate = floatval($settings['commission_rate'] ?? 2.00);
                $commission_amount = ($card_data['total_amount'] * $commission_rate) / 100;
                
                $stmt_commission = $conn->prepare("INSERT INTO commissions (user_id, card_id, amount, percentage) VALUES (?, ?, ?, ?)");
                $stmt_commission->bind_param("iidd", $card_data['user_id'], $card_data['id'], $commission_amount, $commission_rate);
                $stmt_commission->execute();
                $stmt_commission->close();
            }
            
            header('Location: advanced_dashboard.php');
            exit;
        }
    }
}

// Handle card deletion
if (isset($_GET['delete_card']) && $is_super_admin) {
    $card_id = intval($_GET['delete_card']);
    $stmt = $conn->prepare("DELETE FROM cards WHERE id = ?");
    $stmt->bind_param("i", $card_id);
    
    if ($stmt->execute()) {
        header('Location: advanced_dashboard.php');
        exit;
    }
}

// Handle user activation/deactivation
if (isset($_GET['toggle_user']) && $is_super_admin) {
    $user_id = intval($_GET['toggle_user']);
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header('Location: advanced_dashboard.php');
        exit;
    }
}

// Handle user role update
if (isset($_POST['update_role']) && $is_super_admin) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];
    $super_admin = isset($_POST['super_admin']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE users SET role = ?, super_admin = ? WHERE id = ?");
    $stmt->bind_param("sii", $new_role, $super_admin, $user_id);
    
    if ($stmt->execute()) {
        header('Location: advanced_dashboard.php');
        exit;
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings']) && $is_super_admin) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
    if (isset($_POST['maintenance_mode'])) {
        $stmt->bind_param("ss", $_POST['maintenance_mode'], 'maintenance_mode');
        $stmt->execute();
    }
    
    if (isset($_POST['min_deposit'])) {
        $stmt->bind_param("ss", $_POST['min_deposit'], 'min_deposit');
        $stmt->execute();
    }
    
    if (isset($_POST['max_withdrawal'])) {
        $stmt->bind_param("ss", $_POST['max_withdrawal'], 'max_withdrawal');
        $stmt->execute();
    }
    
    if (isset($_POST['commission_rate'])) {
        $stmt->bind_param("ss", $_POST['commission_rate'], 'commission_rate');
        $stmt->execute();
    }
    
    header('Location: advanced_dashboard.php');
    exit;
}

// Handle price updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_prices']) && $is_super_admin) {
    $paypal_usd = floatval($_POST['paypal_usd']);
    $paypal_uk = floatval($_POST['paypal_uk']);
    $apple_gift = floatval($_POST['apple_gift']);
    $ach_bank = floatval($_POST['ach_bank']);
    
    $stmt = $conn->prepare("UPDATE prices SET rate = ? WHERE currency_type = 'PayPal_USD'");
    $stmt->bind_param("d", $paypal_usd);
    $stmt->execute();
    
    $stmt = $conn->prepare("UPDATE prices SET rate = ? WHERE currency_type = 'PayPal_UK'");
    $stmt->bind_param("d", $paypal_uk);
    $stmt->execute();
    
    $stmt = $conn->prepare("UPDATE prices SET rate = ? WHERE currency_type = 'Apple_Gift_Card'");
    $stmt->bind_param("d", $apple_gift);
    $stmt->execute();
    
    $stmt = $conn->prepare("UPDATE prices SET rate = ? WHERE currency_type = 'ACH_Bank'");
    $stmt->bind_param("d", $ach_bank);
    $stmt->execute();
    
    header('Location: advanced_dashboard.php');
    exit;
}

// Get current prices
$prices_result = $conn->query("SELECT * FROM prices ORDER BY currency_type ASC");
$prices = [];
while($price = $prices_result->fetch_assoc()) {
    $prices[$price['currency_type']] = $price['rate'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Admin Dashboard - Money Exchange Platform</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8fafc;
            padding-top: 20px;
            padding-bottom: 40px;
        }
        .admin-header {
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
        .admin-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #d1fae5; color: #059669; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .status-paid { background-color: #dbeafe; color: #2563eb; }
        .status-completed { background-color: #bbf7d0; color: #166534; }
        .status-cancelled { background-color: #ddd6fe; color: #7c2d12; }
        .action-btn {
            margin-right: 5px;
        }
        .super-admin-tag {
            background-color: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        .nav-tabs .nav-link.active {
            background-color: #2563eb;
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            color: white;
        }
        .activity-card { background-color: #2563eb; }
        .activity-transaction { background-color: #10b981; }
        .activity-user { background-color: #f59e0b; }
        .sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            height: fit-content;
        }
        .quick-action {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            text-align: center;
            transition: transform 0.2s;
        }
        .quick-action:hover {
            transform: translateY(-3px);
        }
        .quick-action i {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #2563eb;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-crown me-2"></i>Advanced Admin Dashboard</h1>
                    <p class="mb-0">Money Exchange Platform Management</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="me-3">Role: <?php echo $_SESSION['role']; ?><?php if ($is_super_admin): ?> <span class="super-admin-tag">SUPER ADMIN</span><?php endif; ?></span>
                    <a href="../logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cards-tab" data-bs-toggle="tab" data-bs-target="#cards" type="button" role="tab">Cards</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">Transactions</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button>
                    </li>
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Reports</button>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="tab-content mt-3">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $total_users; ?></div>
                                    <div class="text-muted">Total Users</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $total_cards; ?></div>
                                    <div class="text-muted">Total Cards</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $total_transactions; ?></div>
                                    <div class="text-muted">Transactions</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-number">$<?php echo number_format($total_balance, 2); ?></div>
                                    <div class="text-muted">Total Balance</div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="admin-card">
                            <h3><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h3>
                            <div class="row">
                                <div class="col-md-2 col-6">
                                    <a href="users.php" class="text-decoration-none">
                                        <div class="quick-action">
                                            <i class="fas fa-users"></i>
                                            <div>Manage Users</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="transactions.php" class="text-decoration-none">
                                        <div class="quick-action">
                                            <i class="fas fa-exchange-alt"></i>
                                            <div>Transactions</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="reports.php" class="text-decoration-none">
                                        <div class="quick-action">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                            <div>Reports</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="#settings" class="text-decoration-none" data-bs-toggle="tab" data-bs-target="#settings">
                                        <div class="quick-action">
                                            <i class="fas fa-cog"></i>
                                            <div>Settings</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="#rates" class="text-decoration-none" data-bs-toggle="tab" data-bs-target="#settings">
                                        <div class="quick-action">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <div>Rates</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="#" class="text-decoration-none">
                                        <div class="quick-action">
                                            <i class="fas fa-bell"></i>
                                            <div>Notifications</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="admin-card">
                            <h3><i class="fas fa-history me-2 text-primary"></i>Recent Activity</h3>
                            <div class="list-group">
                                <?php while($activity = $recent_activity->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div>
                                        <span class="activity-icon activity-<?php echo $activity['type']; ?>">
                                            <?php if($activity['type'] === 'card'): ?>
                                                <i class="fas fa-credit-card"></i>
                                            <?php elseif($activity['type'] === 'transaction'): ?>
                                                <i class="fas fa-exchange-alt"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </span>
                                        <strong><?php echo $activity['description']; ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Cards Tab -->
                    <div class="tab-pane fade" id="cards" role="tabpanel">
                        <div class="admin-card">
                            <h3><i class="fas fa-list me-2 text-primary"></i>Card Management</h3>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $cards_result->data_seek(0); // Reset pointer
                                        while($card = $cards_result->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><?php echo $card['id']; ?></td>
                                            <td><?php echo htmlspecialchars($card['username']); ?></td>
                                            <td><?php echo htmlspecialchars($card['card_type']); ?></td>
                                            <td>$<?php echo number_format($card['amount'], 2); ?></td>
                                            <td><?php echo $card['quantity']; ?></td>
                                            <td>$<?php echo number_format($card['total_amount'], 2); ?></td>
                                            <td><span class="status-badge status-<?php echo $card['status']; ?>"><?php echo ucfirst($card['status']); ?></span></td>
                                            <td><?php echo date('M j, Y', strtotime($card['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewCardModal<?php echo $card['id']; ?>"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                                        <?php if($card['status'] !== 'approved' && $card['status'] !== 'paid'): ?>
                                                            <li><a class="dropdown-item" href="?update_status=approved&card_id=<?php echo $card['id']; ?>"><i class="fas fa-check me-2"></i>Approve</a></li>
                                                        <?php endif; ?>
                                                        <?php if($card['status'] !== 'rejected'): ?>
                                                            <li><a class="dropdown-item" href="?update_status=rejected&card_id=<?php echo $card['id']; ?>"><i class="fas fa-times me-2"></i>Reject</a></li>
                                                        <?php endif; ?>
                                                        <?php if($card['status'] === 'approved'): ?>
                                                            <li><a class="dropdown-item" href="?update_status=paid&card_id=<?php echo $card['id']; ?>"><i class="fas fa-money-bill me-2"></i>Mark Paid</a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="?delete_card=<?php echo $card['id']; ?>" onclick="return confirm('Are you sure you want to delete this card?')"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- View Card Details Modal -->
                                        <div class="modal fade" id="viewCardModal<?php echo $card['id']; ?>" tabindex="-1" aria-labelledby="viewCardModalLabel<?php echo $card['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="viewCardModalLabel<?php echo $card['id']; ?>">Card Details #<?php echo $card['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>User:</strong> <?php echo htmlspecialchars($card['username']); ?></p>
                                                                <p><strong>Card Type:</strong> <?php echo htmlspecialchars($card['card_type']); ?></p>
                                                                <p><strong>Amount per Card:</strong> $<?php echo number_format($card['amount'], 2); ?></p>
                                                                <p><strong>Quantity:</strong> <?php echo $card['quantity']; ?></p>
                                                                <p><strong>Total Amount:</strong> $<?php echo number_format($card['total_amount'], 2); ?></p>
                                                                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($card['payment_method']); ?></p>
                                                                <p><strong>Payment Number:</strong> <?php echo htmlspecialchars($card['payment_number']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Coupon Code:</strong> <?php echo $card['coupon_code'] ? htmlspecialchars($card['coupon_code']) : 'None'; ?></p>
                                                                <p><strong>Status:</strong> <span class="status-badge status-<?php echo $card['status']; ?>"><?php echo ucfirst($card['status']); ?></span></p>
                                                                <p><strong>Transaction ID:</strong> <?php echo $card['transaction_id'] ? htmlspecialchars($card['transaction_id']) : 'N/A'; ?></p>
                                                                <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($card['created_at'])); ?></p>
                                                                <p><strong>Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($card['updated_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <?php if($card['card_details']): ?>
                                                        <div class="mb-3">
                                                            <strong>Additional Details:</strong><br>
                                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($card['card_details'])); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if($card['card_image']): ?>
                                                        <div class="mb-3">
                                                            <strong>Card Image:</strong><br>
                                                            <img src="../<?php echo htmlspecialchars($card['card_image']); ?>" alt="Card Image" class="img-fluid rounded" style="max-height: 200px;">
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if($card['status'] !== 'approved' && $card['status'] !== 'paid'): ?>
                                                            <a href="?update_status=approved&card_id=<?php echo $card['id']; ?>" class="btn btn-success">Approve</a>
                                                        <?php endif; ?>
                                                        <?php if($card['status'] !== 'rejected'): ?>
                                                            <a href="?update_status=rejected&card_id=<?php echo $card['id']; ?>" class="btn btn-danger">Reject</a>
                                                        <?php endif; ?>
                                                        <?php if($card['status'] === 'approved'): ?>
                                                            <a href="?update_status=paid&card_id=<?php echo $card['id']; ?>" class="btn btn-info">Mark Paid</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Other tabs content would go here -->
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <h5><i class="fas fa-chart-line me-2 text-primary"></i>Quick Stats</h5>
                    <div class="row text-center mb-3">
                        <div class="col-6 mb-2">
                            <div class="p-2 bg-primary bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-primary"><?php echo $pending_cards; ?></div>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="p-2 bg-success bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-success"><?php echo $approved_cards; ?></div>
                                <small class="text-muted">Approved</small>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h5>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">System Maintenance</h6>
                                <small>3 days ago</small>
                            </div>
                            <p class="mb-1">Scheduled maintenance on Saturday</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">New User Registration</h6>
                                <small class="text-muted">Just now</small>
                            </div>
                            <p class="mb-1">1 new user registered today</p>
                        </a>
                    </div>
                    
                    <h5 class="mt-4"><i class="fas fa-tools me-2 text-info"></i>Tools</h5>
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary btn-sm"><i class="fas fa-database me-1"></i> Backup Data</a>
                        <a href="#" class="btn btn-outline-success btn-sm"><i class="fas fa-sync me-1"></i> Sync Rates</a>
                        <a href="#" class="btn btn-outline-warning btn-sm"><i class="fas fa-shield-alt me-1"></i> Security Scan</a>
                        <a href="#" class="btn btn-outline-info btn-sm"><i class="fas fa-chart-bar me-1"></i> Export Data</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>