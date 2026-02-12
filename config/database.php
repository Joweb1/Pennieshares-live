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
    $dsn = "mysql:host=$mysql_host;dbname=$mysql_db;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo_mysql = new PDO($dsn, $mysql_user, $mysql_pass, $options);

    // Ensure indexes exist for performance (MySQL 5.7 compatible)
    $indexes = [
        "users" => ["idx_users_username" => "username", "idx_users_email" => "email", "idx_users_partner_code" => "partner_code"],
        "assets" => ["idx_assets_user_id" => "user_id", "idx_assets_asset_type_id" => "asset_type_id", "idx_assets_is_completed" => "is_completed", "idx_assets_is_manually_expired" => "is_manually_expired", "idx_assets_expires_at" => "expires_at"],
        "pending_profits" => ["idx_pending_profits_user_id" => "user_id", "idx_pending_profits_is_credited" => "is_credited", "idx_pending_profits_credit_at" => "credit_at"],
        "wallet_transactions" => ["idx_wallet_transactions_user_id" => "user_id", "idx_wallet_transactions_created_at" => "created_at"]
    ];

    foreach ($indexes as $table => $tableIndexes) {
        foreach ($tableIndexes as $indexName => $column) {
            try {
                // Check if index exists first
                $checkIndex = $pdo_mysql->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
                if ($checkIndex->rowCount() == 0) {
                    $pdo_mysql->exec("ALTER TABLE `$table` ADD INDEX `$indexName` (`$column`)");
                }
            } catch (PDOException $e) {
                // Ignore errors like "duplicate index" or "table doesn't exist yet"
            }
        }
    }

} catch (PDOException $e) {
    // In a real application, you would log this error and show a generic error page.
    // For this context, we will avoid outputting anything to prevent breaking JSON responses.
    // You can uncomment the line below for debugging, but it will cause "headers already sent" errors.
    // error_log("Database connection failed: " . $e->getMessage());
    
    // To prevent the application from continuing with a null $pdo_mysql object.
    // This is better than die() as it allows for graceful error handling in the application.
    $pdo_mysql = null; 
}