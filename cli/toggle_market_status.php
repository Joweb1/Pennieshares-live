<?php
require_once __DIR__ . '/../src/init.php';

// Get the current market status
$stmt = $pdo_mysql->prepare("SELECT `value` FROM settings WHERE `key` = 'market_status'");
$stmt->execute();
$currentStatus = $stmt->fetchColumn();

if ($currentStatus === false) {
    // If the setting doesn't exist, create it and set it to 'closed' by default.
    $newStatus = 'closed';
    $stmt = $pdo_mysql->prepare("INSERT INTO settings (`key`, `value`) VALUES ('market_status', :new_status)");
    $stmt->execute(['new_status' => $newStatus]);
} else {
    // Toggle the status
    $newStatus = ($currentStatus === 'open') ? 'closed' : 'open';
    $stmt = $pdo_mysql->prepare("UPDATE settings SET `value` = :new_status WHERE `key` = 'market_status'");
    $stmt->execute(['new_status' => $newStatus]);
}

echo "Market status has been toggled to: " . strtoupper($newStatus) . PHP_EOL;
