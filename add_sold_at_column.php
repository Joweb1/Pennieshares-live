<?php
require_once __DIR__ . '/src/init.php';

try {
    // Check if the column already exists
    $stmt = $pdo_mysql->query("SHOW COLUMNS FROM `assets` LIKE 'sold_at'");
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($columnExists) {
        echo "The 'sold_at' column already exists in the 'assets' table.\n";
    } else {
        // Add the new column
        $pdo_mysql->exec("ALTER TABLE `assets` ADD COLUMN `sold_at` DATETIME NULL DEFAULT NULL AFTER `is_sold`");
        echo "Successfully added the 'sold_at' column to the 'assets' table.\n";
    }

} catch (PDOException $e) {
    die("Database operation failed: " . $e->getMessage() . "\n");
}
?>
