<?php
// cli/process_mail_queue.php

// This script is intended to be run by a cron job every minute.
// It processes a batch of pending emails from the queue.

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

$lock_file = __DIR__ . '/process.lock';

if (file_exists($lock_file)) {
    // Another process is running. Exit silently.
    echo "Another process is already running. Exiting.\n";
    exit;
}

// Create the lock file
touch($lock_file);

// Ensure the lock file is removed on script exit
register_shutdown_function('unlink', $lock_file);

require_once __DIR__ . '/../src/email_functions.php';
require_once __DIR__ . '/../config/database.php';

echo "Cron Job: Processing email queue...\n";

try {
    // 1. Fetch a batch of pending jobs (and failed jobs that can be retried)
    $stmt = $pdo_mysql->prepare("SELECT * FROM email_queue WHERE status = 'pending' OR (status = 'failed' AND attempts < 3) ORDER BY created_at ASC LIMIT 20");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        echo "No pending emails to send.\n";
        exit;
    }

    echo "Found " . count($jobs) . " emails to process.\n";

    foreach ($jobs as $job) {
        $jobId = $job['id'];
        echo "Processing job #{$jobId}... ";

        // 2. Mark as processing
        $updateStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1, processed_at = NOW() WHERE id = ?");
        $updateStmt->execute([$jobId]);

        // 3. Attempt to send the email
        $success = sendEmailImmediate($job['recipient_email'], $job['subject'], $job['body']);

        // 4. Update status based on outcome
        if ($success) {
            $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
            $finalStmt->execute([$jobId]);
            echo "SUCCESS.\n";
        } else {
            $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'failed', error_message = 'Mailer failed to send' WHERE id = ?");
            $finalStmt->execute([$jobId]);
            echo "FAILED.\n";
        }
        
        // Optional: sleep for a short duration to avoid overwhelming the mail server
        sleep(1);
    }

    echo "Email queue processing finished.\n";

} catch (Exception $e) {
    error_log("Cron job (process_mail_queue.php) failed: " . $e->getMessage());
    echo "An error occurred: " . $e->getMessage() . "\n";
}
