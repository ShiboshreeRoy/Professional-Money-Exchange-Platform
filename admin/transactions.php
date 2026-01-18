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

// Get all transactions
$transactions_result = $conn->query("
    SELECT t.*, u.username, u.email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC
");

// Get all users for filtering
$users_result = $conn->query("SELECT id, username FROM users ORDER BY username ASC");

// Handle transaction status update
if (isset($_GET['update_transaction_status']) && $is_super_admin) {
    $transaction_id = intval($_GET['update_transaction_status']);
    $new_status = $_GET['status'];
    
    if (in_array($new_status, ['pending', 'completed', 'failed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $transaction_id);
        
        if ($stmt->execute()) {
            header('Location: transactions.php');
            exit;
        }
    }
}

// Handle new transaction creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_transaction']) && $is_super_admin) {
    $user_id = intval($_POST['user_id']);
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $currency_from = $_POST['currency_from'];
    $currency_to = $_POST['currency_to'];
    $rate = floatval($_POST['rate']);
    $fee = floatval($_POST['fee']);
    $status = $_POST['status'];
    $reference = $_POST['reference'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, currency_from, currency_to, rate, fee, status, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddddsiss", $user_id, $type, $amount, $currency_from, $currency_to, $rate, $fee, $status, $reference, $notes);
    
    if ($stmt->execute()) {
        $message = "Transaction created successfully!";
    } else {
        $error = "Error creating transaction.";
    }
    
    $stmt->close();
}

// Handle transaction deletion
if (isset($_GET['delete_transaction']) && $is_super_admin) {
    $transaction_id = intval($_GET['delete_transaction']);
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    
    if ($stmt->execute()) {
        header('Location: transactions.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - Super Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            padding-top: 20px;
            padding-bottom: 40px;
        }
        .transaction-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .transaction-card {
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
        .status-completed { background-color: #d1fae5; color: #059669; }
        .status-failed { background-color: #fee2e2; color: #dc2626; }
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
    </style>
</head>
<body>
    <!-- Transaction Header -->
    <header class="transaction-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-exchange-alt me-2"></i>Transaction Management</h1>
                    <p class="mb-0">Manage all platform transactions</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="me-3">Role: <?php echo $_SESSION['role']; ?><?php if ($is_super_admin): ?> <span class="super-admin-tag">SUPER ADMIN</span><?php endif; ?></span>
                    <a href="dashboard.php" class="btn btn-outline-light"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column - Transaction List -->
            <div class="col-md-8">
                <div class="transaction-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-list me-2 text-primary"></i>All Transactions</h3>
                        <?php if ($is_super_admin): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                            <i class="fas fa-plus me-1"></i> Add Transaction
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Rate</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($transaction = $transactions_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $transaction['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($transaction['username']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($transaction['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $transaction['type'] === 'deposit' ? 'success' : ($transaction['type'] === 'withdrawal' ? 'danger' : 'primary'); ?>">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $transaction['currency_from']; ?> <?php echo number_format($transaction['amount'], 2); ?><br>
                                        <small class="text-muted"><?php echo $transaction['currency_to']; ?> <?php echo number_format($transaction['amount'] * $transaction['rate'], 2); ?></small>
                                    </td>
                                    <td>
                                        <?php echo $transaction['currency_from']; ?>/<?php echo $transaction['currency_to']; ?> = <?php echo number_format($transaction['rate'], 4); ?><br>
                                        <small class="text-muted">Fee: <?php echo number_format($transaction['fee'], 2); ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                                    <td>
                                        <?php if ($is_super_admin): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewTransactionModal<?php echo $transaction['id']; ?>"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editTransactionModal<?php echo $transaction['id']; ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="?update_transaction_status=<?php echo $transaction['id']; ?>&status=pending"><i class="fas fa-clock me-2"></i>Pending</a></li>
                                                <li><a class="dropdown-item" href="?update_transaction_status=<?php echo $transaction['id']; ?>&status=completed"><i class="fas fa-check me-2"></i>Completed</a></li>
                                                <li><a class="dropdown-item" href="?update_transaction_status=<?php echo $transaction['id']; ?>&status=failed"><i class="fas fa-times me-2"></i>Failed</a></li>
                                                <li><a class="dropdown-item" href="?update_transaction_status=<?php echo $transaction['id']; ?>&status=cancelled"><i class="fas fa-ban me-2"></i>Cancelled</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete_transaction=<?php echo $transaction['id']; ?>" onclick="return confirm('Are you sure you want to delete this transaction?')"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- View Transaction Modal -->
                                <div class="modal fade" id="viewTransactionModal<?php echo $transaction['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Transaction Details #<?php echo $transaction['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>ID:</strong> <?php echo $transaction['id']; ?></p>
                                                        <p><strong>User:</strong> <?php echo htmlspecialchars($transaction['username']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($transaction['email']); ?></p>
                                                        <p><strong>Type:</strong> <span class="badge bg-<?php echo $transaction['type'] === 'deposit' ? 'success' : ($transaction['type'] === 'withdrawal' ? 'danger' : 'primary'); ?>"><?php echo ucfirst($transaction['type']); ?></span></p>
                                                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Amount:</strong> <?php echo $transaction['currency_from']; ?> <?php echo number_format($transaction['amount'], 2); ?></p>
                                                        <p><strong>Converted:</strong> <?php echo $transaction['currency_to']; ?> <?php echo number_format($transaction['amount'] * $transaction['rate'], 2); ?></p>
                                                        <p><strong>Rate:</strong> <?php echo number_format($transaction['rate'], 4); ?></p>
                                                        <p><strong>Fee:</strong> <?php echo number_format($transaction['fee'], 2); ?></p>
                                                        <p><strong>Reference:</strong> <?php echo htmlspecialchars($transaction['reference']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Notes:</strong><br>
                                                    <p><?php echo htmlspecialchars($transaction['notes']); ?></p>
                                                </div>
                                                <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></p>
                                                <p><strong>Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($transaction['updated_at'])); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edit Transaction Modal -->
                                <div class="modal fade" id="editTransactionModal<?php echo $transaction['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Transaction #<?php echo $transaction['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">User</label>
                                                                <select name="user_id" class="form-select" disabled>
                                                                    <?php 
                                                                    $users_result_copy = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
                                                                    while($user_opt = $users_result_copy->fetch_assoc()): 
                                                                    ?>
                                                                    <option value="<?php echo $user_opt['id']; ?>" <?php echo $user_opt['id'] == $transaction['user_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($user_opt['username']); ?>
                                                                    </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Type</label>
                                                                <select name="type" class="form-select">
                                                                    <option value="deposit" <?php echo $transaction['type'] === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                                                    <option value="withdrawal" <?php echo $transaction['type'] === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                                                    <option value="exchange" <?php echo $transaction['type'] === 'exchange' ? 'selected' : ''; ?>>Exchange</option>
                                                                    <option value="commission" <?php echo $transaction['type'] === 'commission' ? 'selected' : ''; ?>>Commission</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Amount</label>
                                                                <input type="number" step="0.01" class="form-control" name="amount" value="<?php echo $transaction['amount']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Currency From</label>
                                                                <input type="text" class="form-control" name="currency_from" value="<?php echo $transaction['currency_from']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Currency To</label>
                                                                <input type="text" class="form-control" name="currency_to" value="<?php echo $transaction['currency_to']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Rate</label>
                                                                <input type="number" step="0.0001" class="form-control" name="rate" value="<?php echo $transaction['rate']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Fee</label>
                                                                <input type="number" step="0.01" class="form-control" name="fee" value="<?php echo $transaction['fee']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="pending" <?php echo $transaction['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="completed" <?php echo $transaction['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                    <option value="failed" <?php echo $transaction['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                                    <option value="cancelled" <?php echo $transaction['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reference</label>
                                                        <input type="text" class="form-control" name="reference" value="<?php echo $transaction['reference']; ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes</label>
                                                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($transaction['notes']); ?></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Update Transaction</button>
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
            
            <!-- Right Column - Stats and Filters -->
            <div class="col-md-4">
                <!-- Stats Cards -->
                <div class="transaction-card">
                    <h5><i class="fas fa-chart-pie me-2 text-primary"></i>Transaction Stats</h5>
                    <?php
                    // Get transaction stats
                    $stats = [];
                    $types = ['deposit', 'withdrawal', 'exchange', 'commission'];
                    foreach($types as $type) {
                        $result = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE type = '$type'");
                        $stats[$type] = $result->fetch_assoc();
                    }
                    
                    $status_counts = [];
                    $statuses = ['pending', 'completed', 'failed', 'cancelled'];
                    foreach($statuses as $status) {
                        $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = '$status'");
                        $status_counts[$status] = $result->fetch_assoc()['count'];
                    }
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-success"><?php echo $stats['deposit']['count']; ?></div>
                                <small class="text-muted">Deposits</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-danger bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-danger"><?php echo $stats['withdrawal']['count']; ?></div>
                                <small class="text-muted">Withdrawals</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-primary bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-primary"><?php echo $stats['exchange']['count']; ?></div>
                                <small class="text-muted">Exchanges</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <div class="h5 mb-0 text-warning"><?php echo $stats['commission']['count']; ?></div>
                                <small class="text-muted">Commissions</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Status Distribution:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Pending</span>
                                <span class="badge bg-warning rounded-pill"><?php echo $status_counts['pending']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Completed</span>
                                <span class="badge bg-success rounded-pill"><?php echo $status_counts['completed']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Failed</span>
                                <span class="badge bg-danger rounded-pill"><?php echo $status_counts['failed']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Cancelled</span>
                                <span class="badge bg-secondary rounded-pill"><?php echo $status_counts['cancelled']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($is_super_admin): ?>
                <div class="transaction-card">
                    <h5><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                            <i class="fas fa-plus me-2"></i>New Transaction
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                            <i class="fas fa-layer-group me-2"></i>Bulk Actions
                        </button>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#filterModal">
                            <i class="fas fa-filter me-2"></i>Filter Transactions
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="create_transaction" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User *</label>
                                    <select name="user_id" class="form-select" required>
                                        <option value="">Select User</option>
                                        <?php 
                                        $users_result->data_seek(0); // Reset pointer
                                        while($user = $users_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Type *</label>
                                    <select name="type" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <option value="deposit">Deposit</option>
                                        <option value="withdrawal">Withdrawal</option>
                                        <option value="exchange">Exchange</option>
                                        <option value="commission">Commission</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount *</label>
                                    <input type="number" step="0.01" class="form-control" name="amount" placeholder="Enter amount" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency From *</label>
                                    <input type="text" class="form-control" name="currency_from" placeholder="e.g., USD, EUR" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Currency To *</label>
                                    <input type="text" class="form-control" name="currency_to" placeholder="e.g., BDT, GBP" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rate *</label>
                                    <input type="number" step="0.0001" class="form-control" name="rate" placeholder="Exchange rate" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fee</label>
                                    <input type="number" step="0.01" class="form-control" name="fee" placeholder="Transaction fee" value="0.00">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                        <option value="failed">Failed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" name="reference" placeholder="Transaction reference number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about the transaction"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Transaction</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Select Action</label>
                            <select class="form-select">
                                <option value="">Choose an action...</option>
                                <option value="update_status">Update Status</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <input type="date" class="form-control" name="start_date">
                            <input type="date" class="form-control mt-2" name="end_date">
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Action</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Transactions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <select class="form-select">
                                <option value="">All Users</option>
                                <?php 
                                $users_result->data_seek(0); // Reset pointer
                                while($user = $users_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select">
                                <option value="">All Types</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="exchange">Exchange</option>
                                <option value="commission">Commission</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Range</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" step="0.01" class="form-control" placeholder="Min">
                                </div>
                                <div class="col-6">
                                    <input type="number" step="0.01" class="form-control" placeholder="Max">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>