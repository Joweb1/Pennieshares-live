<?php
require_once __DIR__ . '/../src/init.php';

$lock_file = __DIR__ . '/process.lock';

if (file_exists($lock_file)) {
    // Another process is running. Exit silently.
    exit;
}

// Create the lock file
touch($lock_file);

// Ensure the lock file is removed on script exit
register_shutdown_function('unlink', $lock_file);

// Process pending profits
processPendingProfits();

// The lock file is automatically removed by the shutdown function.
?>