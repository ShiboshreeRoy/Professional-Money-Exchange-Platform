<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/config.php';

$user_id = $_SESSION['user_id'];

// Get user's notifications
$notifications_result = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");

// Mark all notifications as read
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");

// Get unread notifications count
$unread_count_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_count = $unread_count_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Card Exchange Platform</title>
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
        .dashboard-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .notification-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #2563eb;
        }
        .notification-read {
            opacity: 0.7;
        }
        .notification-date {
            font-size: 0.8em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-bell me-2"></i>Notifications</h1>
                    <p class="mb-0">Stay updated with your account activities</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a>
                    <a href="logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-envelope me-2 text-primary"></i>Your Notifications</h3>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
                    </div>
                </div>
                
                <?php if ($notifications_result->num_rows > 0): ?>
                    <?php while($notification = $notifications_result->fetch_assoc()): ?>
                    <div class="notification-card <?php echo $notification['is_read'] ? 'notification-read' : ''; ?>">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-primary">NEW</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h5>
                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="notification-date">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-<?php echo $notification['type'] === 'success' ? 'success' : ($notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'danger' ? 'danger' : 'secondary')); ?>">
                                    <?php echo ucfirst($notification['type']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4>No notifications yet</h4>
                        <p class="text-muted">You don't have any notifications at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>