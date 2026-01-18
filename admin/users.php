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

// Get statistics
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['active_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
$stats['inactive_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 0")->fetch_assoc()['count'];
$stats['admin_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$stats['super_admin_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE super_admin = 1")->fetch_assoc()['count'];

// Handle user actions
if (isset($_GET['toggle_user']) && $is_super_admin) {
    $user_id = intval($_GET['toggle_user']);
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header('Location: users.php');
        exit;
    }
}

if (isset($_GET['delete_user']) && $is_super_admin) {
    $user_id = intval($_GET['delete_user']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header('Location: users.php');
        exit;
    }
}

if (isset($_GET['verify_user']) && $is_super_admin) {
    $user_id = intval($_GET['verify_user']);
    $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        header('Location: users.php');
        exit;
    }
}

// Handle user updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user']) && $is_super_admin) {
    $user_id = intval($_POST['user_id']);
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $super_admin = isset($_POST['super_admin']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $verified = isset($_POST['verified']) ? 1 : 0;
    $balance = floatval($_POST['balance']);
    
    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, super_admin = ?, is_active = ?, verified = ?, balance = ? WHERE id = ?");
    $stmt->bind_param("sssiiiis", $username, $email, $role, $super_admin, $is_active, $verified, $balance, $user_id);
    
    if ($stmt->execute()) {
        $message = "User updated successfully!";
    } else {
        $error = "Error updating user.";
    }
    
    $stmt->close();
}

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user']) && $is_super_admin) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $super_admin = isset($_POST['super_admin']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $verified = isset($_POST['verified']) ? 1 : 0;
    $balance = floatval($_POST['balance']);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, super_admin, is_active, verified, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiiid", $username, $email, $password, $role, $super_admin, $is_active, $verified, $balance);
    
    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $error = "Error creating user.";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Super Admin Dashboard</title>
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
        .user-header {
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
        .user-card {
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
        .status-active { background-color: #d1fae5; color: #059669; }
        .status-inactive { background-color: #fee2e2; color: #dc2626; }
        .status-verified { background-color: #dbeafe; color: #2563eb; }
        .status-unverified { background-color: #fef3c7; color: #d97706; }
        .role-admin { background-color: #ddd6fe; color: #7c3aed; }
        .role-user { background-color: #ddd6fe; color: #4f46e5; }
        .super-admin-tag {
            background-color: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        .action-btn {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <!-- User Header -->
    <header class="user-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-users me-2"></i>User Management</h1>
                    <p class="mb-0">Manage all platform users and their permissions</p>
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

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                    <div class="text-muted">Total Users</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['active_users']; ?></div>
                    <div class="text-muted">Active</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['inactive_users']; ?></div>
                    <div class="text-muted">Inactive</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['admin_users']; ?></div>
                    <div class="text-muted">Admins</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['super_admin_users']; ?></div>
                    <div class="text-muted">Super Admins</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_users'] - $stats['active_users']; ?></div>
                    <div class="text-muted">Unverified</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - User List -->
            <div class="col-md-8">
                <div class="user-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-list me-2 text-primary"></i>All Users</h3>
                        <?php if ($is_super_admin): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-1"></i> Add User
                        </button>
                        <?php endif; ?>
                    </div>
                    
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
                                    <th>Verification</th>
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
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['super_admin']): ?>
                                            <span class="super-admin-tag">SA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['verified'] ? 'verified' : 'unverified'; ?>">
                                            <?php echo $user['verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($is_super_admin): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewUserModal<?php echo $user['id']; ?>"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>"><i class="fas fa-edit me-2"></i>Edit User</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="?toggle_user=<?php echo $user['id']; ?>">
                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'times' : 'check'; ?> me-2"></i>
                                                    <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </a></li>
                                                <?php if (!$user['verified']): ?>
                                                <li><a class="dropdown-item" href="?verify_user=<?php echo $user['id']; ?>"><i class="fas fa-check-circle me-2"></i>Verify User</a></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete_user=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"><i class="fas fa-trash me-2"></i>Delete User</a></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- View User Modal -->
                                <div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">User Details - <?php echo htmlspecialchars($user['username']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
                                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                                        <p><strong>Role:</strong> <span class="status-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></p>
                                                        <p><strong>Super Admin:</strong> <?php echo $user['super_admin'] ? 'Yes' : 'No'; ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Balance:</strong> $<?php echo number_format($user['balance'], 2); ?></p>
                                                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></p>
                                                        <p><strong>Verified:</strong> <span class="status-badge status-<?php echo $user['verified'] ? 'verified' : 'unverified'; ?>"><?php echo $user['verified'] ? 'Verified' : 'Unverified'; ?></span></p>
                                                        <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></p>
                                                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edit User Modal -->
                                <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit User - <?php echo htmlspecialchars($user['username']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="update_user" value="1">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Username</label>
                                                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Role</label>
                                                                <select name="role" class="form-select">
                                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Balance ($)</label>
                                                                <input type="number" step="0.01" class="form-control" name="balance" value="<?php echo $user['balance']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input type="checkbox" class="form-check-input" name="super_admin" id="super_admin_<?php echo $user['id']; ?>" <?php echo $user['super_admin'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="super_admin_<?php echo $user['id']; ?>">Super Admin</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active_<?php echo $user['id']; ?>" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="is_active_<?php echo $user['id']; ?>">Active User</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input type="checkbox" class="form-check-input" name="verified" id="verified_<?php echo $user['id']; ?>" <?php echo $user['verified'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="verified_<?php echo $user['id']; ?>">Verified</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
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
            
            <!-- Right Column - Quick Actions -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <?php if ($is_super_admin): ?>
                <div class="user-card">
                    <h5><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importUsersModal">
                            <i class="fas fa-file-import me-2"></i>Import Users
                        </button>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                            <i class="fas fa-layer-group me-2"></i>Bulk Actions
                        </button>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#massEmailModal">
                            <i class="fas fa-envelope me-2"></i>Mass Email
                        </button>
                    </div>
                </div>
                
                <!-- User Verification -->
                <div class="user-card">
                    <h5><i class="fas fa-user-check me-2 text-primary"></i>Verification</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-user-clock me-2"></i>Pending: <?php echo $stats['total_users'] - $stats['active_users']; ?>
                        </button>
                        <button class="btn btn-outline-success">
                            <i class="fas fa-check-circle me-2"></i>Verified: <?php echo $stats['active_users']; ?>
                        </button>
                    </div>
                </div>
                
                <!-- User Roles -->
                <div class="user-card">
                    <h5><i class="fas fa-user-tag me-2 text-primary"></i>Roles</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Admins</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['admin_users']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Super Admins</span>
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['super_admin_users']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Regular Users</span>
                            <span class="badge bg-secondary rounded-pill"><?php echo $stats['total_users'] - $stats['admin_users'] - $stats['super_admin_users']; ?></span>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="create_user" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" placeholder="Enter username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" placeholder="Enter email" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Initial Balance ($)</label>
                                    <input type="number" step="0.01" class="form-control" name="balance" placeholder="0.00" value="0.00">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="super_admin" id="new_super_admin">
                                        <label class="form-check-label" for="new_super_admin">Super Admin</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_active" id="new_is_active" checked>
                                        <label class="form-check-label" for="new_is_active">Active User</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="verified" id="new_verified" checked>
                                        <label class="form-check-label" for="new_verified">Verified</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Users Modal -->
    <div class="modal fade" id="importUsersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Upload CSV File</label>
                            <input type="file" class="form-control" accept=".csv">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sample Format</label>
                            <pre>username,email,password,role,balance,verified</pre>
                        </div>
                        <button type="submit" class="btn btn-primary">Import Users</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionsModal" tabindex="-1">
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
                                <option value="activate">Activate Selected</option>
                                <option value="deactivate">Deactivate Selected</option>
                                <option value="verify">Verify Selected</option>
                                <option value="delete">Delete Selected</option>
                                <option value="assign_role">Assign Role</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Filters</label>
                            <select class="form-select">
                                <option value="">All Users</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                                <option value="verified">Verified Only</option>
                                <option value="unverified">Unverified Only</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Action</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mass Email Modal -->
    <div class="modal fade" id="massEmailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mass Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" placeholder="Email subject">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" rows="5" placeholder="Enter your message"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recipients</label>
                            <select class="form-select">
                                <option value="all">All Users</option>
                                <option value="active">Active Users Only</option>
                                <option value="admin">Admins Only</option>
                                <option value="verified">Verified Users Only</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Email</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>