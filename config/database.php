<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define BASE_URL
if (isset($_ENV['APP_URL'])) {
    define('BASE_URL', $_ENV['APP_URL']);
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_path = ($script_name == '/') ? '' : $script_name;
    define('BASE_URL', $protocol . $host . $base_path);
}

try {
    // --- MySQL Connection ---
    $mysql_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $mysql_db = $_ENV['DB_DATABASE'] ?? 'pennieshares';
    $mysql_user = $_ENV['DB_USERNAME'] ?? 'root';
    $mysql_pass = $_ENV['DB_PASSWORD'] ?? '';

    try {
        $pdo_mysql = new PDO("mysql:host=$mysql_host", $mysql_user, $mysql_pass);
        $pdo_mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_mysql->exec("CREATE DATABASE IF NOT EXISTS `$mysql_db`");
        $pdo_mysql->exec("USE `$mysql_db`");
    } catch (PDOException $e) {
        die("MySQL connection failed: " . $e->getMessage());
    }

    // --- MySQL Schema ---

    // --- Create Users Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        fullname VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        username VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(255) NOT NULL,
        referral VARCHAR(255) NOT NULL,
        stage INT DEFAULT 1,
        partner_code VARCHAR(255) UNIQUE,
        password VARCHAR(255) NOT NULL,
        reset_token VARCHAR(255),
        reset_expires DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status INT DEFAULT 1 NOT NULL,
        wallet_balance DECIMAL(10, 2) DEFAULT 0.00,
        is_admin INT DEFAULT 0,
        is_broker INT DEFAULT 0,
        is_verified INT NOT NULL DEFAULT 0,
        last_login_email_sent DATE,
        otp_code VARCHAR(255),
        otp_expires_at DATETIME,
        total_return DECIMAL(10, 2) DEFAULT 0.00,
        performance_chart_data TEXT,
        performance_value DECIMAL(10, 2),
        performance_change DECIMAL(10, 2),
        last_performance_update DATE,
        transaction_pin VARCHAR(255),
        earnings_paused INT DEFAULT 0,
        has_received_referral_bonus INT DEFAULT 0
    )");
    // --- Create Payment Proofs Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS payment_proofs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status INT DEFAULT 1,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    // --- Create KYC Verifications Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS kyc_verifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        full_name VARCHAR(255),
        dob VARCHAR(255),
        address TEXT,
        state VARCHAR(255),
        bvn VARCHAR(255),
        nin VARCHAR(255),
        passport_path VARCHAR(255),
        national_id_path VARCHAR(255),
        proof_of_address_path VARCHAR(255),
        selfie_path VARCHAR(255),
        status VARCHAR(255) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    // --- Create Settings Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(255) PRIMARY KEY,
        `value` TEXT
    )");
    $pdo_mysql->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('market_status', 'closed')");
    $pdo_mysql->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('mail_delivery_mode', 'cron')");

    // --- Create Email Queue Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS email_queue (
        id INT PRIMARY KEY AUTO_INCREMENT,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME,
        attempts INT DEFAULT 0,
        error_message TEXT
    )");

    // --- Create Expo Push Tokens Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS expo_push_tokens (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, token VARCHAR(255) NOT NULL UNIQUE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);");

    // --- Create Push Subscriptions Table (for web push) (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, endpoint TEXT NOT NULL, p256dh TEXT NOT NULL, auth TEXT NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);");

    // --- Create User Broker Interactions Table (MySQL) ---
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS user_broker_interactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        broker_user_id INT NOT NULL,
        is_favorite INT DEFAULT 0,
        last_transfer_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME,
        UNIQUE(user_id, broker_user_id),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(broker_user_id) REFERENCES users(id) ON DELETE CASCADE
    );");
    // Tables migrated from SQLite
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS asset_types (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) UNIQUE NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        payout_cap DECIMAL(10, 2) NOT NULL,
        duration_months INT NOT NULL,
        reservation_fund_contribution DECIMAL(10, 2) NOT NULL,
        image_link TEXT,
        category TEXT,
        dividing_price DECIMAL(10, 2)
    );");

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS assets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        asset_type_id INT NOT NULL,
        parent_id INT,
        generation INT DEFAULT 1,
        children_count INT DEFAULT 0,
        total_generational_received DECIMAL(10, 2) DEFAULT 0.00,
        total_shared_received DECIMAL(10, 2) DEFAULT 0.00,
        is_completed INT DEFAULT 0,
        is_manually_expired INT DEFAULT 0,
        is_sold INT DEFAULT 0,
        created_at DATETIME NOT NULL,
        expires_at DATETIME,
        completed_at DATETIME,
        sale_status TEXT,
        FOREIGN KEY(asset_type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
        FOREIGN KEY(parent_id) REFERENCES assets(id) ON DELETE SET NULL
    );");

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS payouts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        receiving_asset_id INT, 
        triggering_asset_id INT NOT NULL,
        company_fund_type TEXT, 
        amount DECIMAL(10, 2) NOT NULL,
        payout_type TEXT NOT NULL, 
        created_at DATETIME NOT NULL
    );");
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS company_funds (
        id INT PRIMARY KEY, 
        total_company_profit DECIMAL(10, 2) DEFAULT 0.00,
        total_reservation_fund DECIMAL(10, 2) DEFAULT 0.00,
        total_generational_pot DECIMAL(10, 2) DEFAULT 0.00,
        total_shared_pot DECIMAL(10, 2) DEFAULT 0.00,
        last_updated DATETIME
    );");

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type TEXT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS pending_profits (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        receiving_asset_id INT NOT NULL,
        fractional_amount DECIMAL(10, 2) NOT NULL,
        payout_type TEXT NOT NULL,
        credit_at DATETIME NOT NULL,
        is_credited INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(receiving_asset_id) REFERENCES assets(id) ON DELETE CASCADE
    )");
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS asset_type_stats (
        id INT PRIMARY KEY AUTO_INCREMENT,
        asset_type_id INT NOT NULL,
        timestamp BIGINT NOT NULL,
        open_price DECIMAL(10, 2) NOT NULL,
        high_price DECIMAL(10, 2) NOT NULL,
        low_price DECIMAL(10, 2) NOT NULL,
        close_price DECIMAL(10, 2) NOT NULL,
        volume INT,
        FOREIGN KEY(asset_type_id) REFERENCES asset_types(id) ON DELETE CASCADE
    )");
    
    // Initial insert for company_funds if it doesn't exist
    $pdo_mysql->exec("INSERT IGNORE INTO company_funds (id) VALUES (1)");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
