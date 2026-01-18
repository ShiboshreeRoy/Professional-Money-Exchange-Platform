# Professional Money Exchange Platform

A comprehensive card exchange platform built with PHP, MySQL, and Bootstrap 5 that allows users to exchange various payment methods (PayPal, Apple Gift Cards, ACH Bank) with real-time pricing and advanced admin management.

## 🚀 Features

- **User Registration & Authentication**: Secure signup and login system
- **Super Admin Panel**: Complete control over users, cards, transactions, and platform settings
- **Real-time Pricing**: Dynamic exchange rates management
- **Card Management**: Submit, track, and approve cards
- **Transaction Management**: Complete transaction system with deposits, withdrawals, exchanges
- **Financial Reporting**: Comprehensive analytics and reporting system
- **User Management**: Complete user administration with role assignments
- **Responsive Design**: Works on all devices
- **Database Integration**: MySQL backend with proper relationships

## 📁 Folder Structure

```
fff/
├── admin/
│   ├── dashboard.php         # Main admin dashboard
│   ├── reports.php           # Financial reports and analytics
│   ├── transactions.php      # Transaction management
│   └── users.php             # User management
├── assets/
│   ├── css/
│   │   └── custom.css        # Custom styles
│   ├── images/               # Image assets
│   └── js/
│       └── custom.js         # Custom JavaScript
├── config/
│   ├── config.php            # Database configuration
│   └── env_loader.php        # Environment loader
├── includes/
│   ├── header.php            # Header template
│   ├── footer.php            # Footer template
│   └── sidebar.php           # Sidebar template
├── .env                     # Environment variables
├── .env.example             # Environment template
├── config.php               # Database configuration
├── dashboard.php            # User dashboard
├── index.php                # Public home page
├── login.php                # Login page
├── logout.php               # Logout handler
├── register.php             # Registration page
├── setup_database.php       # Database setup script
├── update_schema.php        # Database schema updates
└── README.md                # Documentation
```

## ⚙️ Installation

### Prerequisites
- XAMPP (Apache, MySQL, PHP)
- Web browser

### Setup Steps

1. **Clone or Download** the project files to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\fff\
   ```

2. **Start XAMPP Services**:
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

3. **Setup Database**:
   - Access the setup script: `http://localhost/fff/setup_database.php`
   - Or manually create the database tables using the SQL from `setup_database.php`

4. **Update Schema**:
   - Run the schema update: `http://localhost/fff/update_schema.php`

5. **Configure Environment**:
   - Copy `.env.example` to `.env`
   - Update database credentials in `.env` file:
   ```env
   DB_HOST=localhost
   DB_USERNAME=root
   DB_PASSWORD=your_password
   DB_NAME=website_db
   ```

6. **Access the Website**:
   Open your browser and navigate to:
   ```
   http://localhost/fff/
   ```

## 🔐 Default Admin Credentials

- **Username**: admin
- **Password**: admin123

**Important**: Change the default admin password after first login for security.

## 🛠️ Database Schema

### Users Table
- `id` (Primary Key, Auto Increment)
- `username` (VARCHAR 50, Unique)
- `email` (VARCHAR 100, Unique)
- `password` (VARCHAR 255)
- `role` (ENUM: 'user', 'admin')
- `super_admin` (BOOLEAN)
- `is_active` (BOOLEAN)
- `balance` (DECIMAL 10,2)
- `verified` (BOOLEAN)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### Prices Table
- `id` (Primary Key, Auto Increment)
- `currency_type` (VARCHAR 50)
- `rate` (DECIMAL 10,2)
- `description` (TEXT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### Cards Table
- `id` (Primary Key, Auto Increment)
- `user_id` (Foreign Key to Users)
- `card_type` (VARCHAR 50)
- `amount` (DECIMAL 10,2)
- `quantity` (INT)
- `total_amount` (DECIMAL 10,2)
- `payment_method` (VARCHAR 50)
- `payment_number` (VARCHAR 50)
- `coupon_code` (VARCHAR 50, Optional)
- `status` (ENUM: 'pending', 'approved', 'rejected', 'paid')
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### Transactions Table
- `id` (Primary Key, Auto Increment)
- `user_id` (Foreign Key to Users)
- `type` (ENUM: 'deposit', 'withdrawal', 'exchange', 'commission')
- `amount` (DECIMAL 10,2)
- `currency_from` (VARCHAR 10)
- `currency_to` (VARCHAR 10)
- `rate` (DECIMAL 10,4)
- `fee` (DECIMAL 10,2)
- `status` (ENUM: 'pending', 'completed', 'failed', 'cancelled')
- `reference` (VARCHAR 100)
- `notes` (TEXT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### Commissions Table
- `id` (Primary Key, Auto Increment)
- `user_id` (Foreign Key to Users)
- `card_id` (Foreign Key to Cards)
- `amount` (DECIMAL 10,2)
- `percentage` (DECIMAL 5,2)
- `created_at` (TIMESTAMP)

### Notifications Table
- `id` (Primary Key, Auto Increment)
- `user_id` (Foreign Key to Users)
- `title` (VARCHAR 255)
- `message` (TEXT)
- `type` (ENUM: 'info', 'success', 'warning', 'error')
- `is_read` (BOOLEAN)
- `created_at` (TIMESTAMP)

### Settings Table
- `id` (Primary Key, Auto Increment)
- `setting_key` (VARCHAR 100, Unique)
- `setting_value` (TEXT)
- `description` (TEXT)
- `updated_at` (TIMESTAMP)

## 📊 User Features

### Dashboard
- View current exchange rates
- Submit new cards for exchange
- Track pending cards
- View submission history
- See total earnings

### Card Submission
- Select card type (PayPal US, PayPal UK, Apple Gift Card, ACH Bank)
- Enter amount and quantity
- Select payment method
- Add coupon codes (optional)
- Track status of submissions

## 👑 Super Admin Features

### Main Dashboard
- Overview statistics
- Manage exchange rates
- Review and approve cards
- Monitor user activity
- Platform settings

### Financial Reports
- Revenue analytics
- Transaction breakdown
- User revenue ranking
- Monthly/daily reports
- Export to CSV

### Transaction Management
- View all transactions
- Update transaction status
- Create manual transactions
- Filter and search transactions
- Bulk actions

### User Management
- View all users
- Update user roles
- Activate/deactivate users
- Verify user accounts
- Manage user balances
- Bulk user actions

## 🎨 Responsive Design

- Mobile-first approach
- Bootstrap 5 grid system
- Touch-friendly controls
- Cross-browser compatibility
- Fast loading times

## 🔒 Security Features

- Password hashing with bcrypt
- Prepared statements to prevent SQL injection
- Session management
- Input validation
- CSRF protection (basic implementation)
- Role-based access control
- Super admin permissions

## 🚀 Usage Guide

### For Users
1. Register for an account
2. Login to your dashboard
3. View current exchange rates
4. Submit cards using the "Sell New Cards" form
5. Track your submissions in the history section

### For Super Admins
1. Login with admin credentials
2. Update exchange rates as needed
3. Review pending cards and approve/reject them
4. Manage users and their permissions
5. Monitor financial reports and analytics
6. Manage all platform transactions

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is open-source and available under the MIT License.

## 🆘 Support

For support, email support@example.com or create an issue in the repository.

---

**Built with ❤️ using PHP, MySQL, Bootstrap 5, and JavaScript**