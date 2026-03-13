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

// --- START SEC UPDATE FEEDER ---
try {
    $trackingFile = __DIR__ . '/../database/sec_email_sent.json';
    $sentUserIds = [];

    // Load existing tracking data
    if (file_exists($trackingFile)) {
        $content = file_get_contents($trackingFile);
        $sentUserIds = json_decode($content, true) ?: [];
    }

    // Ensure the directory exists
    if (!is_dir(dirname($trackingFile))) {
        mkdir(dirname($trackingFile), 0777, true);
    }

    // Select 10 users with status = 2 who haven't received the SEC update email yet
    $placeholders = count($sentUserIds) > 0 ? ' AND id NOT IN (' . implode(',', array_map('intval', $sentUserIds)) . ')' : '';
    $sql = "SELECT id, username, email FROM users WHERE status = 2" . $placeholders . " LIMIT 10";

    $stmt = $pdo_mysql->prepare($sql);
    $stmt->execute();
    $usersToNotify = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($usersToNotify)) {
        echo "Feeder: Queueing SEC updates for " . count($usersToNotify) . " users...\n";

        foreach ($usersToNotify as $user) {
            // 1. Queue Email (via sendNotificationEmail which calls queueEmail)
            $data = [
                'username' => $user['username'],
                'id_card_url' => BASE_URL . '/idcard'
            ];
            $subject = "Important Regulatory Update Regarding Your Holdings";
            sendNotificationEmail('sec_update', $data, $user['email'], $subject);

            // 3. Mark as sent in tracker
            $sentUserIds[] = (int)$user['id'];
        }
        // Save updated tracking data
        file_put_contents($trackingFile, json_encode($sentUserIds));
    } else {
        echo "Feeder: No more SEC updates to queue.\n";
    }
} catch (Exception $e) {
    error_log("SEC Feeder failed: " . $e->getMessage());
    echo "SEC Feeder failed: " . $e->getMessage() . "\n";
}
// --- END SEC UPDATE FEEDER ---

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

    $successfulJobs = 0;
    $failedJobs = 0;

    foreach ($jobs as $job) {
        $jobId = $job['id'];
        // 2. Mark as processing
        $updateStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1, processed_at = NOW() WHERE id = ?");
        $updateStmt->execute([$jobId]);

        // 3. Attempt to send the email
        $success = sendEmailImmediate($job['recipient_email'], $job['subject'], $job['body']);

        // 4. Update status based on outcome
        if ($success) {
            $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
            $finalStmt->execute([$jobId]);
            $successfulJobs++;
        } else {
            $finalStmt = $pdo_mysql->prepare("UPDATE email_queue SET status = 'failed', error_message = 'Mailer failed to send' WHERE id = ?");
            $finalStmt->execute([$jobId]);
            $failedJobs++;
        }
        
        // Optional: sleep for a short duration to avoid overwhelming the mail server
        sleep(1);
    }

    echo "Finished processing email queue.\n";
    echo "Summary: {$successfulJobs} successful, {$failedJobs} failed.\n";

} catch (Exception $e) {
    error_log("Cron job (process_mail_queue.php) failed: " . $e->getMessage());
    echo "An error occurred: " . $e->getMessage() . "\n";
}
