<?php
require_once __DIR__ . '/../src/init.php';

function deleteOldSoldAssets() {
    global $pdo_mysql;
    try {
        $oneMonthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));
        $stmt = $pdo_mysql->prepare("DELETE FROM assets WHERE is_sold = 1 AND sold_at < ?");
        $stmt->execute([$oneMonthAgo]);
        $deletedCount = $stmt->rowCount();
        
        // Log the action
        $logMessage = date('[Y-m-d H:i:s]') . " Cron job: Deleted {$deletedCount} sold assets older than one month.\n";
        file_put_contents(__DIR__ . '/../logs/cron.log', $logMessage, FILE_APPEND);
        
        echo "Deleted {$deletedCount} old sold assets.\n";

    } catch (PDOException $e) {
        $logMessage = date('[Y-m-d H:i:s]') . " Cron job error: Failed to delete old sold assets. Error: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/../logs/cron.log', $logMessage, FILE_APPEND);
        echo "Error: " . $e->getMessage() . "\n";
    }
}

deleteOldSoldAssets();
?>
