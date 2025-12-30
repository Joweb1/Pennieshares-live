<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/init.php';

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
    
    // --- Step 1: Drop all existing MySQL tables ---
    echo "\n--- Dropping all existing MySQL tables ---\n";
    $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables_stmt = $pdo_mysql->query("SHOW TABLES");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "Dropping table: {$table}\n";
        $pdo_mysql->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "All existing MySQL tables dropped.\n";

    // --- Step 2: Re-create all tables in MySQL (including previously MySQL-only tables) ---
    echo "\n--- Creating all necessary tables in MySQL ---\n";

    // Tables that were already in MySQL
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
    echo "Created table: users\n";

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS payment_proofs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status INT DEFAULT 1,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Created table: payment_proofs\n";
    
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
    echo "Created table: kyc_verifications\n";
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(255) PRIMARY KEY,
        `value` TEXT
    )");
    echo "Created table: settings\n";
    
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
    echo "Created table: email_queue\n";
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS expo_push_tokens (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, token VARCHAR(255) NOT NULL UNIQUE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);");
    echo "Created table: expo_push_tokens\n";
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, endpoint TEXT NOT NULL, p256dh TEXT NOT NULL, auth TEXT NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);");
    echo "Created table: push_subscriptions\n";
    
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
    echo "Created table: user_broker_interactions\n";


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
    echo "Created table: asset_types\n";

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
    echo "Created table: assets\n";

    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS payouts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        receiving_asset_id INT, 
        triggering_asset_id INT NOT NULL,
        company_fund_type TEXT, 
        amount DECIMAL(10, 2) NOT NULL,
        payout_type TEXT NOT NULL, 
        created_at DATETIME NOT NULL
    );");
    echo "Created table: payouts\n";
    
    $pdo_mysql->exec("CREATE TABLE IF NOT EXISTS company_funds (
        id INT PRIMARY KEY, 
        total_company_profit DECIMAL(10, 2) DEFAULT 0.00,
        total_reservation_fund DECIMAL(10, 2) DEFAULT 0.00,
        total_generational_pot DECIMAL(10, 2) DEFAULT 0.00,
        total_shared_pot DECIMAL(10, 2) DEFAULT 0.00,
        last_updated DATETIME
    );");
    echo "Created table: company_funds\n";

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
    echo "Created table: pending_profits\n";
    
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
    echo "Created table: asset_type_stats\n";
    
    // --- Step 3: Transfer data from SQLite to MySQL ---
    echo "\n--- Transferring data from SQLite to MySQL ---\n";
    
    $sqlite_tables = [
        'users', 'asset_types', 'assets', 'payouts', 'company_funds', 
        'pending_profits', 'asset_type_stats', 'payment_proofs', 'kyc_verifications',
        'expo_push_tokens', 'push_subscriptions', 'user_broker_interactions', 'settings'
    ];

    $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 0;");
    foreach ($sqlite_tables as $table) {
        echo "Transferring data for table: {$table}\n";
        
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
            
            // Prepare insert statement only once
            if ($first_chunk) {
                if(empty($rows[0])) {
                    echo "Table is empty, skipping.\n";
                    break;
                }
                $columns = array_keys($rows[0]);
                $columns_str = '`' . implode('`,`', $columns) . '`';
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $insert_sql = "INSERT IGNORE INTO `{$table}` ({$columns_str}) VALUES ({$placeholders})";
                $insert_stmt = $pdo_mysql->prepare($insert_sql);
                $first_chunk = false;
            }

            // Data cleaning for specific tables
            if ($table === 'asset_types') {
                foreach ($rows as &
$row) {
                    if ($row['dividing_price'] === '') {
                        $row['dividing_price'] = null;
                    }
                }
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
                // Optionally, break the outer loop if one chunk fails
                break;
            }
            
            $offset += $limit;
        }
        echo "Transferred " . $total_rows_transferred . " rows for table: {$table}\n";
    }
    $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    //Re-seeding settings data
    $pdo_mysql->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('market_status', 'closed')");
    $pdo_mysql->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('mail_delivery_mode', 'cron')");


    echo "\n--- Data migration completed successfully! ---\n";

} catch (Exception $e) {
    die("An error occurred during migration: " . $e->getMessage());
}

echo "</pre>";
?>
