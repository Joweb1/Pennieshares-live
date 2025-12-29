<?php
// cli/send_single_mail.php

// This script is for sending a single email from the queue.
// It's triggered by exec() from the main application.

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

$lock_file = __DIR__ . '/process.lock';

if (file_exists($lock_file)) {
    // Another process is running. Exit silently.
    exit;
}

// Create the lock file
touch($lock_file);

// Ensure the lock file is removed on script exit
register_shutdown_function('unlink', $lock_file);

require_once __DIR__ . '/../src/email_functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($argv[1]) || !is_numeric($argv[1])) {
    die('A numeric job ID must be provided.' . "\n");
}

$jobId = (int)$argv[1];

try {
    // 1. Fetch the job
    $stmt = $pdo_mysql->prepare("SELECT * FROM email_queue WHERE id = ? AND status IN ('pending', 'failed')");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("No pending or failed job found with ID: {$jobId}\n");
    }

    // 2. Mark as processing
    $updateStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?");
    $updateStmt->execute([$jobId]);

    // 3. Attempt to send the email
    $success = sendEmailImmediate($job['recipient_email'], $job['subject'], $job['body']);

    // 4. Update status based on outcome
    if ($success) {
        $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'sent', processed_at = NOW() WHERE id = ?");
        $finalStmt->execute([$jobId]);
        echo "Successfully sent email for job ID: {$jobId}\n";
    } else {
        $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'failed', error_message = 'Mailer failed to send' WHERE id = ?");
        $finalStmt->execute([$jobId]);
        echo "Failed to send email for job ID: {$jobId}\n";
    }

} catch (Exception $e) {
    // If an exception occurs, try to mark the job as failed
    if (isset($pdo_mysql) && isset($jobId)) {
        $errorStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $errorStmt->execute([$e->getMessage(), $jobId]);
    }
    die("An error occurred: " . $e->getMessage() . "\n");
}
