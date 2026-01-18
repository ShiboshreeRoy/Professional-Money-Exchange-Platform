<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/config.php';

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message']);
    
    if (empty($subject) || empty($message_content)) {
        $message = 'Subject and message are required.';
    } elseif (strlen($subject) < 5) {
        $message = 'Subject must be at least 5 characters long.';
    } elseif (strlen($message_content) < 10) {
        $message = 'Message must be at least 10 characters long.';
    } else {
        // Insert support ticket
        $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')");
        $stmt->bind_param("iss", $user_id, $subject, $message_content);
        
        if ($stmt->execute()) {
            $message = 'Your support ticket has been submitted successfully. We will respond shortly.';
            
            // Create notification for admin
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $title = 'New Support Ticket';
            $msg = 'User ' . $_SESSION['username'] . ' has submitted a new support ticket.';
            $type = 'info';
            $stmt_notif->bind_param("isss", 1, $title, $msg, $type); // Assuming admin user id is 1
            $stmt_notif->execute();
            $stmt_notif->close();
        } else {
            $message = 'Error submitting ticket. Please try again.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - Card Exchange Platform</title>
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
        .contact-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .support-option {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .support-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-headset me-2"></i>Contact Support</h1>
                    <p class="mb-0">Get help with your account and transactions</p>
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
            <div class="col-lg-8 mx-auto">
                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="support-option text-center">
                            <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                            <h5>Live Chat</h5>
                            <p class="mb-0">Chat with our support team instantly</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="support-option text-center">
                            <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                            <h5>Email Support</h5>
                            <p class="mb-0">Send us an email and get a response</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="support-option text-center">
                            <i class="fas fa-clock fa-2x text-info mb-2"></i>
                            <h5>24/7 Support</h5>
                            <p class="mb-0">We're available around the clock</p>
                        </div>
                    </div>
                </div>
                
                <div class="contact-card">
                    <h3><i class="fas fa-paper-plane me-2 text-primary"></i>Submit a Ticket</h3>
                    <p class="text-muted">Fill out the form below to contact our support team</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" placeholder="Briefly describe your issue" required minlength="5">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="5" placeholder="Describe your issue in detail" required minlength="10"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Submit Ticket</button>
                    </form>
                </div>
                
                <div class="contact-card mt-4">
                    <h3><i class="fas fa-question-circle me-2 text-success"></i>Frequently Asked Questions</h3>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                    How long does card processing take?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Most cards are processed within 24 hours. However, some may take up to 48 hours depending on verification requirements.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                    What payment methods do you accept?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We accept PayPal, Apple Gift Cards, ACH Bank transfers, and various mobile banking options.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                    How do I track my card status?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can track your card status in the dashboard under the 'Your Pending Cards' and 'Your Submitted Cards History' sections.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>