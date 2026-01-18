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
            
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Handle user activation/deactivation
if (isset($_GET['toggle_user']) && $is_super_admin) {
    $user_id = intval($_GET['toggle_user']);
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header('Location: dashboard.php');
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
        header('Location: dashboard.php');
        exit;
    }
}

// Handle general settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_general_settings']) && $is_super_admin) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
    if (isset($_POST['website_name'])) {
        $stmt->bind_param("ss", $_POST['website_name'], 'website_name');
        $stmt->execute();
    }
    
    if (isset($_POST['website_description'])) {
        $stmt->bind_param("ss", $_POST['website_description'], 'website_description');
        $stmt->execute();
    }
    
    if (isset($_POST['support_email'])) {
        $stmt->bind_param("ss", $_POST['support_email'], 'support_email');
        $stmt->execute();
    }
    
    if (isset($_POST['company_address'])) {
        $stmt->bind_param("ss", $_POST['company_address'], 'company_address');
        $stmt->execute();
    }
    
    if (isset($_POST['company_phone'])) {
        $stmt->bind_param("ss", $_POST['company_phone'], 'company_phone');
        $stmt->execute();
    }
    
    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $stmt->bind_param("ss", $maintenance_mode, 'maintenance_mode');
    $stmt->execute();
    
    $email_verification = isset($_POST['email_verification']) ? '1' : '0';
    $stmt->bind_param("ss", $email_verification, 'email_verification');
    $stmt->execute();
    
    $sms_verification = isset($_POST['sms_verification']) ? '1' : '0';
    $stmt->bind_param("ss", $sms_verification, 'sms_verification');
    $stmt->execute();
    
    // Handle file uploads for logo and favicon
    if (isset($_FILES['website_logo']) && $_FILES['website_logo']['error'] == 0) {
        $upload_dir = 'assets/images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES['website_logo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_types)) {
            $logo_filename = 'logo_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $logo_filename;
            
            if (move_uploaded_file($_FILES['website_logo']['tmp_name'], $target_path)) {
                $stmt->bind_param("ss", $target_path, 'website_logo');
                $stmt->execute();
            }
        }
    }
    
    if (isset($_FILES['website_favicon']) && $_FILES['website_favicon']['error'] == 0) {
        $upload_dir = 'assets/images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['ico', 'png', 'jpg', 'jpeg'];
        $file_ext = strtolower(pathinfo($_FILES['website_favicon']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_types)) {
            $favicon_filename = 'favicon_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $favicon_filename;
            
            if (move_uploaded_file($_FILES['website_favicon']['tmp_name'], $target_path)) {
                $stmt->bind_param("ss", $target_path, 'website_favicon');
                $stmt->execute();
            }
        }
    }
    
    header('Location: dashboard.php');
    exit;
}

// Handle platform settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_platform_settings']) && $is_super_admin) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
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
    
    $auto_approve_cards = isset($_POST['auto_approve_cards']) ? '1' : '0';
    $stmt->bind_param("ss", $auto_approve_cards, 'auto_approve_cards');
    $stmt->execute();
    
    if (isset($_POST['terms_url'])) {
        $stmt->bind_param("ss", $_POST['terms_url'], 'terms_url');
        $stmt->execute();
    }
    
    if (isset($_POST['privacy_url'])) {
        $stmt->bind_param("ss", $_POST['privacy_url'], 'privacy_url');
        $stmt->execute();
    }
    
    if (isset($_POST['refund_policy'])) {
        $stmt->bind_param("ss", $_POST['refund_policy'], 'refund_policy');
        $stmt->execute();
    }
    
    header('Location: dashboard.php');
    exit;
}

// Handle theme settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_theme_settings']) && $is_super_admin) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
    if (isset($_POST['website_theme'])) {
        $stmt->bind_param("ss", $_POST['website_theme'], 'website_theme');
        $stmt->execute();
    }
    
    if (isset($_POST['primary_color'])) {
        $stmt->bind_param("ss", $_POST['primary_color'], 'primary_color');
        $stmt->execute();
    }
    
    if (isset($_POST['secondary_color'])) {
        $stmt->bind_param("ss", $_POST['secondary_color'], 'secondary_color');
        $stmt->execute();
    }
    
    header('Location: dashboard.php');
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
    
    header('Location: dashboard.php');
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
    <title>Super Admin Dashboard - Money Exchange Platform</title>
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
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-crown me-2"></i>Super Admin Dashboard</h1>
                    <p class="mb-0">Money Exchange Platform Management</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="me-3">Role: <?php echo $_SESSION['role']; ?><?php if ($is_super_admin): ?> <span class="super-admin-tag">SUPER ADMIN</span><?php endif; ?></span>
                    <a href="../notifications.php" class="btn btn-outline-light me-2"><i class="fas fa-bell me-1"></i> Notifications</a>
                    <a href="../contact.php" class="btn btn-outline-light me-2"><i class="fas fa-headset me-1"></i> Support</a>
                    <a href="../logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
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
                <button class="nav-link" id="general-settings-tab" data-bs-toggle="tab" data-bs-target="#general-settings" type="button" role="tab">General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="theme-settings-tab" data-bs-toggle="tab" data-bs-target="#theme-settings" type="button" role="tab">Theme</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pricing-settings-tab" data-bs-toggle="tab" data-bs-target="#pricing-settings" type="button" role="tab">Pricing</button>
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
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus me-2"></i>Add User
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                                <i class="fas fa-bullhorn me-2"></i>Broadcast
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                                <i class="fas fa-tools me-2"></i>Maintenance
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-warning w-100 mb-2" onclick="location.reload()">
                                <i class="fas fa-sync me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="admin-card">
                    <h3><i class="fas fa-history me-2 text-primary"></i>Recent Activity</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Description</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-primary">Card</span></td>
                                    <td>john_doe</td>
                                    <td>Submitted PayPal card worth $50</td>
                                    <td>2 minutes ago</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">Approval</span></td>
                                    <td>jane_smith</td>
                                    <td>Card approved by admin</td>
                                    <td>15 minutes ago</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-info">Deposit</span></td>
                                    <td>mike_wilson</td>
                                    <td>Deposited $100 via bank transfer</td>
                                    <td>1 hour ago</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">Withdrawal</span></td>
                                    <td>sarah_jones</td>
                                    <td>Requested withdrawal of $200</td>
                                    <td>3 hours ago</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div class="tab-pane fade" id="users" role="tabpanel">
                <div class="admin-card">
                    <h3><i class="fas fa-users me-2 text-primary"></i>User Management</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $users_result->data_seek(0); // Reset pointer
                                while($user = $users_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?><?php if ($user['super_admin']): ?> <span class="super-admin-tag">SA</span><?php endif; ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($is_super_admin): ?>
                                            <form method="POST" class="d-inline">
                                                <select name="new_role" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="update_role" value="1">
                                            </form>
                                        <?php else: ?>
                                            <?php echo ucfirst($user['role']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                    <td>
                                        <?php if ($is_super_admin): ?>
                                            <a href="?toggle_user=<?php echo $user['id']; ?>" class="btn btn-sm btn-<?php echo $user['is_active'] ? 'danger' : 'success'; ?> btn-sm">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($is_super_admin): ?>
                                            <button class="btn btn-sm btn-info action-btn" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">Edit</button>
                                            <button class="btn btn-sm btn-danger action-btn">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Edit User Modal -->
                                <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <div class="mb-3">
                                                        <label class="form-label">Username</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Role</label>
                                                        <select name="new_role" class="form-select">
                                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" name="super_admin" id="super_admin_<?php echo $user['id']; ?>" <?php echo $user['super_admin'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="super_admin_<?php echo $user['id']; ?>">Super Admin</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Balance</label>
                                                        <input type="number" step="0.01" class="form-control" name="balance" value="<?php echo $user['balance']; ?>">
                                                    </div>
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="update_role" value="1">
                                                    <button type="submit" class="btn btn-primary">Update User</button>
                                                </form>
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
                                        <?php if($card['status'] !== 'approved' && $card['status'] !== 'paid'): ?>
                                            <a href="?update_status=approved&card_id=<?php echo $card['id']; ?>" class="btn btn-sm btn-success action-btn">Approve</a>
                                        <?php endif; ?>
                                        <?php if($card['status'] !== 'rejected'): ?>
                                            <a href="?update_status=rejected&card_id=<?php echo $card['id']; ?>" class="btn btn-sm btn-danger action-btn">Reject</a>
                                        <?php endif; ?>
                                        <?php if($card['status'] === 'approved'): ?>
                                            <a href="?update_status=paid&card_id=<?php echo $card['id']; ?>" class="btn btn-sm btn-info action-btn">Mark Paid</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Transactions Tab -->
            <div class="tab-pane fade" id="transactions" role="tabpanel">
                <div class="admin-card">
                    <h3><i class="fas fa-exchange-alt me-2 text-primary"></i>Transaction Management</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $transactions_result->data_seek(0); // Reset pointer
                                while($transaction = $transactions_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo $transaction['id']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                    <td><span class="badge bg-<?php echo $transaction['type'] === 'deposit' ? 'success' : ($transaction['type'] === 'withdrawal' ? 'danger' : 'primary'); ?>"><?php echo ucfirst($transaction['type']); ?></span></td>
                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td><?php echo $transaction['currency_from']; ?> → <?php echo $transaction['currency_to']; ?></td>
                                    <td><span class="status-badge status-<?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info action-btn">View</button>
                                        <button class="btn btn-sm btn-warning action-btn">Edit</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- General Settings Tab -->
            <div class="tab-pane fade" id="general-settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="admin-card">
                            <h3><i class="fas fa-cogs me-2 text-primary"></i>General Settings</h3>
                            <p class="text-muted">Configure general website settings</p>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_general_settings" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Website Name</label>
                                    <input type="text" class="form-control" name="website_name" value="<?php echo htmlspecialchars($settings['website_name'] ?? 'Card Exchange Platform'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Website Description</label>
                                    <textarea class="form-control" name="website_description" rows="3"><?php echo htmlspecialchars($settings['website_description'] ?? 'Professional Card Exchange Service'); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Website Logo</label>
                                    <input type="file" class="form-control" name="website_logo" accept="image/*">
                                    <div class="form-text">Current: <?php echo htmlspecialchars($settings['website_logo'] ?? 'assets/images/logo.png'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Website Favicon</label>
                                    <input type="file" class="form-control" name="website_favicon" accept=".ico,.png,.jpg,.jpeg">
                                    <div class="form-text">Current: <?php echo htmlspecialchars($settings['website_favicon'] ?? 'assets/images/favicon.ico'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Support Email</label>
                                    <input type="email" class="form-control" name="support_email" value="<?php echo htmlspecialchars($settings['support_email'] ?? 'support@example.com'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Company Address</label>
                                    <textarea class="form-control" name="company_address" rows="2"><?php echo htmlspecialchars($settings['company_address'] ?? '123 Business Street, City, Country'); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Company Phone</label>
                                    <input type="text" class="form-control" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>> Maintenance Mode
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" name="email_verification" value="1" <?php echo ($settings['email_verification'] ?? '1') === '1' ? 'checked' : ''; ?>> Require Email Verification
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" name="sms_verification" value="1" <?php echo ($settings['sms_verification'] ?? '0') === '1' ? 'checked' : ''; ?>> Require SMS Verification
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save General Settings</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="admin-card">
                            <h3><i class="fas fa-sliders-h me-2 text-warning"></i>Platform Settings</h3>
                            <p class="text-muted">Configure platform operational settings</p>
                            
                            <form method="POST">
                                <input type="hidden" name="update_platform_settings" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Minimum Deposit Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="min_deposit" value="<?php echo htmlspecialchars($settings['min_deposit'] ?? '10'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Maximum Withdrawal Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="max_withdrawal" value="<?php echo htmlspecialchars($settings['max_withdrawal'] ?? '10000'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Commission Rate (%)</label>
                                    <input type="number" step="0.01" class="form-control" name="commission_rate" value="<?php echo htmlspecialchars($settings['commission_rate'] ?? '2.00'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" name="auto_approve_cards" value="1" <?php echo ($settings['auto_approve_cards'] ?? '0') === '1' ? 'checked' : ''; ?>> Auto Approve Cards
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Terms & Conditions URL</label>
                                    <input type="text" class="form-control" name="terms_url" value="<?php echo htmlspecialchars($settings['terms_url'] ?? 'terms.php'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Privacy Policy URL</label>
                                    <input type="text" class="form-control" name="privacy_url" value="<?php echo htmlspecialchars($settings['privacy_url'] ?? 'privacy.php'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Refund Policy URL</label>
                                    <input type="text" class="form-control" name="refund_policy" value="<?php echo htmlspecialchars($settings['refund_policy'] ?? 'refund.php'); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-warning">Save Platform Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Theme Settings Tab -->
            <div class="tab-pane fade" id="theme-settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="admin-card">
                            <h3><i class="fas fa-palette me-2 text-primary"></i>Theme Customization</h3>
                            <p class="text-muted">Customize the appearance of the platform</p>
                            
                            <form method="POST">
                                <input type="hidden" name="update_theme_settings" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Website Theme</label>
                                    <select class="form-select" name="website_theme">
                                        <option value="default" <?php echo ($settings['website_theme'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default</option>
                                        <option value="dark" <?php echo ($settings['website_theme'] ?? 'default') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="light" <?php echo ($settings['website_theme'] ?? 'default') === 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="blue" <?php echo ($settings['website_theme'] ?? 'default') === 'blue' ? 'selected' : ''; ?>>Blue</option>
                                        <option value="green" <?php echo ($settings['website_theme'] ?? 'default') === 'green' ? 'selected' : ''; ?>>Green</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Primary Color</label>
                                    <input type="color" class="form-control form-control-color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" class="form-control form-control-color" name="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#1e40af'); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Theme Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing Settings Tab -->
            <div class="tab-pane fade" id="pricing-settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="admin-card">
                            <h3><i class="fas fa-money-bill-wave me-2 text-primary"></i>Exchange Rates</h3>
                            <form method="POST">
                                <input type="hidden" name="update_prices" value="1">
                                <div class="mb-3">
                                    <label class="form-label">PayPal US $ Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$1 =</span>
                                        <input type="number" step="0.01" class="form-control" name="paypal_usd" value="<?php echo $prices['PayPal_USD'] ?? 115.00; ?>" required>
                                        <span class="input-group-text">৳</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">PayPal UK £ Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£1 =</span>
                                        <input type="number" step="0.01" class="form-control" name="paypal_uk" value="<?php echo $prices['PayPal_UK'] ?? 113.00; ?>" required>
                                        <span class="input-group-text">৳</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Apple Gift Card Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$1 =</span>
                                        <input type="number" step="0.01" class="form-control" name="apple_gift" value="<?php echo $prices['Apple_Gift_Card'] ?? 104.00; ?>" required>
                                        <span class="input-group-text">৳</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ACH Bank Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$1 =</span>
                                        <input type="number" step="0.01" class="form-control" name="ach_bank" value="<?php echo $prices['ACH_Bank'] ?? 115.00; ?>" required>
                                        <span class="input-group-text">৳</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Rates</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab (Super Admin Only) -->
            <?php if ($is_super_admin): ?>
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <div class="admin-card">
                            <h3><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Financial Reports</h3>
                            <p class="text-muted">Access comprehensive financial reports and analytics</p>
                            <a href="reports.php" class="btn btn-primary">View Reports</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Broadcast Modal -->
    <div class="modal fade" id="broadcastModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Broadcast Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" placeholder="Message title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" rows="4" placeholder="Enter your message"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select">
                                <option value="info">Info</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="sendToAll">
                                <label class="form-check-label" for="sendToAll">Send to all users</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Broadcast</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Mode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Maintenance Message</label>
                            <textarea class="form-control" rows="3" placeholder="Enter maintenance message for users"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estimated Duration</label>
                            <input type="text" class="form-control" placeholder="e.g., 2 hours">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="enableMaintenance">
                            <label class="form-check-label" for="enableMaintenance">Enable Maintenance Mode</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        <?php if ($is_super_admin): ?>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [12000, 19000, 15000, 18000, 22000, 19500],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'doughnut',
            data: {
                labels: ['Deposits', 'Withdrawals', 'Exchanges', 'Commissions'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: ['#10b981', '#ef4444', '#3b82f6', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'New Users',
                    data: [12, 19, 15, 18, 22, 17],
                    backgroundColor: '#8b5cf6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>