<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/config.php';

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get current prices
$prices_result = $conn->query("SELECT * FROM prices ORDER BY currency_type ASC");

// Get user's pending cards
$pending_cards = $conn->query("SELECT * FROM cards WHERE user_id = $user_id AND status = 'pending' ORDER BY created_at DESC");

// Get user's submitted cards history
$history_cards = $conn->query("SELECT * FROM cards WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");

// Calculate total sell amount
$total_sell_result = $conn->query("SELECT SUM(total_amount) as total FROM cards WHERE user_id = $user_id AND status = 'paid'");
$total_sell = $total_sell_result->fetch_assoc()['total'] ?: 0;

// Handle new card submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $card_type = $_POST['card_type'];
    $coupon = $_POST['coupon'] ?? null;
    $payment_method = $_POST['payment_method'];
    $payment_number = $_POST['payment_number'];
    $amount = floatval($_POST['amount']);
    $quantity = intval($_POST['quantity']);
    $total_amount = $amount * $quantity;
    $card_details = $_POST['card_details'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    
    // Handle card image upload
    $card_image = null;
    if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] == 0) {
        $upload_dir = 'uploads/cards/';
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['card_image']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_types)) {
            $new_filename = $user_id . '_' . time() . '_' . basename($_FILES['card_image']['name']);
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['card_image']['tmp_name'], $target_path)) {
                $card_image = $target_path;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO cards (user_id, card_type, amount, quantity, total_amount, payment_method, payment_number, coupon_code, card_details, transaction_id, card_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isdssssssss", $user_id, $card_type, $amount, $quantity, $total_amount, $payment_method, $payment_number, $coupon, $card_details, $transaction_id, $card_image);
    
    if ($stmt->execute()) {
        $message = 'Card submitted successfully! Waiting for approval.';
    } else {
        $message = 'Error submitting card. Please try again.';
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Card Exchange Platform</title>
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
        .price-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .card-type {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .rate-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2563eb;
        }
        .sell-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .pending-card {
            background: #fff9db;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .history-card {
            background: #f8fafc;
            border-left: 4px solid #6b7280;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
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
        
        /* Professional Home Button Styling */
        .btn-home-professional {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            color: #2563eb !important;
            border: 2px solid #2563eb !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-home-professional:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-home-professional:focus {
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                    <p class="mb-0">Welcome back, <?php echo htmlspecialchars($username); ?>!</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="../index.php" class="btn btn-home-professional me-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Return to Homepage"><i class="fas fa-home me-1"></i> Home</a>
                    <a href="notifications.php" class="btn btn-outline-light me-2"><i class="fas fa-bell me-1"></i> Notifications</a>
                    <a href="security.php" class="btn btn-outline-light me-2"><i class="fas fa-shield-alt me-1"></i> Security</a>
                    <a href="contact.php" class="btn btn-outline-light me-2"><i class="fas fa-headset me-1"></i> Support</a>
                    <a href="logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Price List Section -->
                <div class="price-card">
                    <h3><i class="fas fa-money-bill-wave me-2 text-primary"></i>Price List — Today's USD Rates</h3>
                    <p class="text-muted">Current exchange rates for various payment methods</p>
                    
                    <div class="row">
                        <?php while($price = $prices_result->fetch_assoc()): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($price['currency_type']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($price['description']); ?></div>
                                </div>
                                <div class="rate-value">
                                    $1 = <?php echo number_format($price['rate'], 2); ?> ৳
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                        <!-- Sell New Cards Section -->
                <div class="sell-card mt-4">
                    <h3><i class="fas fa-credit-card me-2 text-primary"></i>Sell New Cards</h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Card Type *</label>
                                <select class="form-select" name="card_type" required>
                                    <option value="">Select Card Type</option>
                                    <option value="PayPal_USD">PayPal US $</option>
                                    <option value="PayPal_UK">PayPal UK £</option>
                                    <option value="Apple_Gift_Card">Apple Gift Card</option>
                                    <option value="ACH_Bank">ACH Bank</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coupon (Optional)</label>
                                <input type="text" class="form-control" name="coupon" placeholder="Enter coupon code if any">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Bikash">Bikash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Rocket">Rocket</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Apple Cash">Apple Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Number *</label>
                                <input type="text" class="form-control" name="payment_number" placeholder="Enter your account/bkash/nagad number" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Amount per Card ($) *</label>
                                <input type="number" step="0.01" class="form-control" name="amount" placeholder="Enter amount" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cards *</label>
                                <input type="number" class="form-control" name="quantity" placeholder="Number of cards" value="1" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Amount</label>
                                <input type="text" class="form-control" id="total_amount_display" readonly placeholder="Calculated automatically">
                                <input type="hidden" name="total_amount" id="total_amount_hidden">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Card Image (Optional)</label>
                            <input type="file" class="form-control" name="card_image" accept="image/*">
                            <div class="form-text">Upload a clear image of your card for verification purposes</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Additional Details (Optional)</label>
                            <textarea class="form-control" name="card_details" rows="3" placeholder="Enter any additional details about the card"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Transaction ID (Optional)</label>
                            <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction ID if available">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Submit Card</button>
                    </form>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Total Sell Stats -->
                <div class="stats-card">
                    <div class="stats-number">$<?php echo number_format($total_sell, 2); ?></div>
                    <div class="text-muted">Total Sell</div>
                </div>
                
                <!-- Your Pending Cards -->
                <div class="price-card">
                    <h5><i class="fas fa-clock me-2 text-warning"></i>Your Pending Cards</h5>
                    
                    <?php if ($pending_cards->num_rows > 0): ?>
                        <?php while($card = $pending_cards->fetch_assoc()): ?>
                        <div class="pending-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($card['card_type']); ?></strong><br>
                                    <small class="text-muted">Qty: <?php echo $card['quantity']; ?> | Amount: $<?php echo number_format($card['amount'], 2); ?></small><br>
                                    <?php if($card['card_image']): ?>
                                        <small class="text-muted">Image: Yes</small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge status-<?php echo $card['status']; ?>"><?php echo ucfirst($card['status']); ?></span><br>
                                    <small class="text-muted"><?php echo date('M j', strtotime($card['created_at'])); ?></small>
                                </div>
                            </div>
                            <?php if($card['card_details']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Details: <?php echo htmlspecialchars(substr($card['card_details'], 0, 50)) . (strlen($card['card_details']) > 50 ? '...' : ''); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No cards found.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Your Submitted Cards History -->
                <div class="price-card mt-4">
                    <h5><i class="fas fa-history me-2 text-info"></i>Your Submitted Cards History</h5>
                    
                    <?php if ($history_cards->num_rows > 0): ?>
                        <?php while($card = $history_cards->fetch_assoc()): ?>
                        <div class="history-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($card['card_type']); ?></strong><br>
                                    <small class="text-muted">$<?php echo number_format($card['amount'], 2); ?> x <?php echo $card['quantity']; ?></small><br>
                                    <?php if($card['card_image']): ?>
                                        <small class="text-muted">Image: Yes</small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge status-<?php echo $card['status']; ?>"><?php echo ucfirst($card['status']); ?></span><br>
                                    <small class="text-muted"><?php echo date('M j', strtotime($card['created_at'])); ?></small>
                                </div>
                            </div>
                            <?php if($card['card_details']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Details: <?php echo htmlspecialchars(substr($card['card_details'], 0, 50)) . (strlen($card['card_details']) > 50 ? '...' : ''); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No cards submitted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Calculate total amount when amount or quantity changes
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.querySelector('input[name="amount"]');
            const quantityInput = document.querySelector('input[name="quantity"]');
            const totalDisplay = document.getElementById('total_amount_display');
            const totalHidden = document.getElementById('total_amount_hidden');
            
            function calculateTotal() {
                const amount = parseFloat(amountInput.value) || 0;
                const quantity = parseInt(quantityInput.value) || 1;
                const total = amount * quantity;
                
                totalDisplay.value = '$' + total.toFixed(2);
                totalHidden.value = total.toFixed(2);
            }
            
            amountInput.addEventListener('input', calculateTotal);
            quantityInput.addEventListener('input', calculateTotal);
            
            // Initial calculation
            calculateTotal();
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>