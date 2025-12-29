<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<pre>";

try {
    // --- Database Connections ---
    $db_file = __DIR__ . '/database/mydatabase.sqlite';
    $pdo_sqlite = new PDO("sqlite:" . $db_file);
    $pdo_sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Successfully connected to SQLite database.\n";

    // MySQL connection is already available via init.php as $pdo_mysql
    if ($pdo_mysql) {
        echo "Successfully connected to MySQL database.\n";
    } else {
        die("Could not connect to MySQL database.");
    }
    
    // --- Step 1: Create users table in MySQL if it doesn't exist ---
    echo "\n--- Ensuring 'users' table exists in MySQL ---\n";
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
    echo "Table 'users' is ready in MySQL.\n";

    
    // --- Step 2: Transfer data from SQLite to MySQL for 'users' table ---
    echo "\n--- Transferring data from SQLite to MySQL for 'users' table ---\n";
    
    $table = 'users';
    $offset = 0;
    $limit = 1000; // Process 1000 rows at a time
    $total_rows_transferred = 0;
    $first_chunk = true;

    while (true) {
        $select_stmt = $pdo_sqlite->prepare("SELECT * FROM {$table} LIMIT :limit OFFSET :offset");
        $select_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $select_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $select_stmt->execute();
        $rows = $select_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            break; // No more rows to fetch
        }
        
        if ($first_chunk) {
            $columns = array_keys($rows[0]);
            $columns_str = '`' . implode('`,`', $columns) . '`';
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $insert_sql = "INSERT IGNORE INTO `{$table}` ({$columns_str}) VALUES ({$placeholders})";
            $insert_stmt = $pdo_mysql->prepare($insert_sql);
            $first_chunk = false;
        }

        $pdo_mysql->beginTransaction();
        try {
            foreach ($rows as $row) {
                $insert_stmt->execute(array_values($row));
            }
            $pdo_mysql->commit();
            $rows_in_chunk = count($rows);
            $total_rows_transferred += $rows_in_chunk;
        } catch (Exception $e) {
            $pdo_mysql->rollBack();
            echo "Error transferring a chunk for table {$table}: " . $e->getMessage() . "\n";
            break;
        }
        
        $offset += $limit;
    }
    echo "Transferred " . $total_rows_transferred . " rows for table: {$table}\n";
    
    echo "\n--- User data migration completed successfully!---\n";

} catch (Exception $e) {
    die("An error occurred during migration: " . $e->getMessage());
}

echo "</pre>";
?>
