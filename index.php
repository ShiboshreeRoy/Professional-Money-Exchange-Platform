<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

require_once 'config/config.php';

// Get current prices
$prices_result = $conn->query("SELECT * FROM prices ORDER BY currency_type ASC");
$prices = [];
while($price = $prices_result->fetch_assoc()) {
    $prices[$price['currency_type']] = $price['rate'];
}

// Set default rates if not found in database
$paypal_usd = $prices['PayPal_USD'] ?? 115.00;
$paypal_uk = $prices['PayPal_UK'] ?? 113.00;
$apple_gift = $prices['Apple_Gift_Card'] ?? 104.00;
$ach_bank = $prices['ACH_Bank'] ?? 115.00;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Exchange Platform - Buy & Sell Digital Cards</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/images/bg-pattern.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .hero-section p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .features-section {
            padding: 80px 0;
            background: white;
        }
        .feature-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .feature-icon {
            font-size: 3rem;
            color: #2563eb;
            margin-bottom: 20px;
        }
        .cta-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            text-align: center;
        }
        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .btn-hero {
            background: linear-gradient(135deg, #f97316 0%, #f59e0b 100%);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 30px;
            margin: 10px;
        }
        .btn-hero:hover {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
        }
        .stats-section {
            padding: 60px 0;
            background: #f8fafc;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2563eb;
        }
        .footer {
            background: #1e293b;
            color: white;
            padding: 40px 0 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-exchange-alt me-2"></i>CardExchange
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#">Home</a>
                <a class="nav-link" href="#features">Features</a>
                <a class="nav-link" href="#rates">Rates</a>
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Buy & Sell Digital Cards Safely</h1>
            <p>Fast, secure, and reliable platform for exchanging PayPal, Apple Gift Cards, and more</p>
            <div class="mt-4">
                <a href="login.php" class="btn btn-hero">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                </a>
                <a href="register.php" class="btn btn-light">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div>Happy Customers</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">$250K+</div>
                        <div>Traded</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div>Support</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">99.9%</div>
                        <div>Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Why Choose Our Platform?</h2>
                <p class="text-muted">Experience the best card exchange service with our premium features</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure Transactions</h4>
                        <p>Bank-level security ensures your transactions are always protected with advanced encryption.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h4>Fast Processing</h4>
                        <p>Quick verification and instant processing for all your card exchange transactions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4>Best Rates</h4>
                        <p>Competitive exchange rates with no hidden fees or surprise charges.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Image Carousel Section -->
    <section class="features-section bg-light" id="carousel">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Popular Cards & Offers</h2>
                <p class="text-muted">Check out our popular card types and special offers</p>
            </div>
            
            <div id="cardCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#cardCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#cardCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#cardCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner rounded">
                    <div class="carousel-item active">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <img src="assets/images/Paypal_2014_logo.png" class="d-block w-100 rounded" alt="PayPal Card" style="max-height: 300px; object-fit: contain;">
                            </div>
                            <div class="col-md-6 text-center text-md-start">
                                <h3>PayPal US $</h3>
                                <p class="lead">Exchange PayPal dollars at the best rates in the market!</p>
                                <p class="text-muted">Rate: $1 = <?php echo number_format($paypal_usd, 2); ?> ৳</p>
                                <p>Our PayPal exchange service offers the highest rates with instant processing. Simply submit your PayPal card details and get your money in minutes.</p>
                                <a href="login.php" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <img src="assets/images/apple.jpg" class="d-block w-100 rounded" alt="Apple Gift Card" style="max-height: 300px; object-fit: contain;">
                            </div>
                            <div class="col-md-6 text-center text-md-start">
                                <h3>Apple Gift Card</h3>
                                <p class="lead">Trade your Apple gift cards for cash at competitive rates!</p>
                                <p class="text-muted">Rate: $1 = <?php echo number_format($apple_gift, 2); ?> ৳</p>
                                <p>Looking to convert your Apple gift cards to cash? We offer the best rates for all denominations. Fast processing and secure transactions guaranteed.</p>
                                <a href="login.php" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <img src="assets/images/ach.png" class="d-block w-100 rounded" alt="ACH Bank" style="max-height: 300px; object-fit: contain;">
                            </div>
                            <div class="col-md-6 text-center text-md-start">
                                <h3>ACH Bank Transfer</h3>
                                <p class="lead">Fast and secure ACH bank transfers with competitive rates!</p>
                                <p class="text-muted">Rate: $1 = <?php echo number_format($ach_bank, 2); ?> ৳</p>
                                <p>Our ACH bank transfer service provides a secure way to exchange funds with excellent rates. Perfect for larger transactions with guaranteed security.</p>
                                <a href="login.php" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#cardCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#cardCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </section>
    
    <!-- Current Rates Section -->
    <section class="features-section" id="rates">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Current Exchange Rates</h2>
                <p class="text-muted">Real-time rates for various payment methods</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><i class="fab fa-paypal text-primary me-2"></i>PayPal US</h5>
                                    <p class="card-text">
                                        <strong>$1 = <?php echo number_format($paypal_usd, 2); ?> ৳</strong><br>
                                        <small class="text-muted">USD to BDT Exchange Rate</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><i class="fab fa-paypal text-primary me-2"></i>PayPal UK</h5>
                                    <p class="card-text">
                                        <strong>£1 = <?php echo number_format($paypal_uk, 2); ?> ৳</strong><br>
                                        <small class="text-muted">GBP to BDT Exchange Rate</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><i class="fab fa-apple text-success me-2"></i>Apple Gift Card</h5>
                                    <p class="card-text">
                                        <strong>$1 = <?php echo number_format($apple_gift, 2); ?> ৳</strong><br>
                                        <small class="text-muted">Gift Card to BDT Rate</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><i class="fas fa-university text-info me-2"></i>ACH Bank</h5>
                                    <p class="card-text">
                                        <strong>$1 = <?php echo number_format($ach_bank, 2); ?> ৳</strong><br>
                                        <small class="text-muted">ACH to BDT Exchange Rate</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Start Trading?</h2>
            <p class="mb-4">Join thousands of satisfied customers who trust our platform for their card exchange needs</p>
            <a href="register.php" class="btn btn-light btn-lg">
                <i class="fas fa-user-plus me-2"></i>Sign Up Now
            </a>
            <p class="mt-3">Already have an account? <a href="login.php" class="text-white fw-bold">Login here</a></p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-exchange-alt me-2"></i>CardExchange</h5>
                    <p class="text-muted">The safest and fastest way to buy and sell digital cards online.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">Home</a></li>
                        <li><a href="#features" class="text-white">Features</a></li>
                        <li><a href="#rates" class="text-white">Exchange Rates</a></li>
                        <li><a href="login.php" class="text-white">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> support@cardexchange.com</li>
                        <li><i class="fas fa-phone me-2"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-clock me-2"></i> 24/7 Support</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; 2026 CardExchange. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>