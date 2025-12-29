<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function sendEmailImmediate($to, $subject, $body) {
    $adminEmail = $_ENV['MAIL_USERNAME'] ?? null;
    $passwordEmail = $_ENV['MAIL_PASSWORD'] ?? null;
    $adminGmail = $_ENV['GMAIL_USERNAME'] ?? null;
    $passwordGmail = $_ENV['GMAIL_PASSWORD'] ?? null;

    if (empty($adminEmail) || empty($passwordEmail)) {
        error_log("Email credentials (MAIL_USERNAME, MAIL_PASSWORD) are not set in the environment variables.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'mail.pennieshares.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $adminEmail;
        $mail->Password   = $passwordEmail;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->Timeout = 20;

        //Recipients
        $mail->setFrom($adminEmail, 'Pennieshares');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Primary mailer failed. Trying Gmail... Error: {$mail->ErrorInfo}");
        try {
            //Server settings
            // $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $adminGmail;
            $mail->Password   = $passwordGmail;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
        
            $mail->Timeout = 60;

            //Recipients
            $mail->setFrom($adminGmail, 'Pennieshares');
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Secondary mailer (Gmail) also failed. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}

function queueEmail($to, $subject, $body) {
    global $pdo_mysql;

    // Add email to the queue
    $stmt = $pdo_mysql->prepare(
        "INSERT INTO email_queue (recipient_email, subject, body, status) VALUES (?, ?, ?, 'pending')"
    );
    $stmt->execute([$to, $subject, $body]);
    $jobId = $pdo_mysql->lastInsertId();

    // Check mail delivery mode
    $settingStmt = $pdo_mysql->query("SELECT `value` FROM settings WHERE `key` = 'mail_delivery_mode'");
    $deliveryMode = $settingStmt->fetchColumn();

    if ($deliveryMode === 'exec' && function_exists('exec')) {
        // Trigger background process immediately
        $command = "php " . __DIR__ . "/../cli/send_single_mail.php " . $jobId;
        // Execute in background and redirect output to /dev/null
        exec($command . " > /dev/null 2>&1 &");
    }

    return true; // Assume success as it's queued
}


function getEmailTemplate($templateName, $data) {
    $templatePath = __DIR__ . "/../email_templates/{$templateName}.html";
    if (file_exists($templatePath)) {
        $template = file_get_contents($templatePath);
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", $value ?? '', $template);
        }
        return $template;
    } else {
        return "<p>Email template not found.</p>";
    }
}

function sendNotificationEmail($template, $data, $to, $subject) {
    $genericTemplate = file_get_contents(__DIR__ . '/../email_templates/generic_template.html');
    $body = getEmailTemplate($template, $data);
    
    $emailContent = str_replace('{{header}}', $subject, $genericTemplate);
    $emailContent = str_replace('{{body}}', $body, $emailContent);

    // OTP emails must be sent immediately
    if ($template === 'otp_email') {
        return sendEmailImmediate($to, $subject, $emailContent);
    } else {
        return queueEmail($to, $subject, $emailContent);
    }
}

function send_broker_credit_email($to, $username, $amount, $broker_name) {
    $subject = "You've Received Funds from a Broker";
    $data = [
        'username' => $username,
        'amount' => $amount,
        'broker_name' => $broker_name
    ];
    return sendNotificationEmail('broker_credit_user', $data, $to, $subject);
}

function send_admin_wallet_transaction_email($to, $admin_name, $user_name, $transaction_type, $amount) {
    $subject = "Admin Wallet Transaction Notification";
    $data = [
        'admin_name' => $admin_name,
        'user_name' => $user_name,
        'transaction_type' => $transaction_type,
        'amount' => $amount,
        'date' => date('Y-m-d H:i:s')
    ];
    return sendNotificationEmail('admin_wallet_transaction_admin', $data, $to, $subject);
}

function send_user_transfer_email($to, $sender_name, $receiver_name, $amount) {
    $subject = "User Transfer Notification";
    $data = [
        'sender_name' => $sender_name,
        'receiver_name' => $receiver_name,
        'amount' => $amount,
        'date' => date('Y-m-d H:i:s')
    ];
    return sendNotificationEmail('user_transfer_admin', $data, $to, $subject);
}

function sendBrokerApplicationEmails($user, $formData) {
    $adminEmail = $_ENV['MAIL_USERNAME'];

    // Data for admin email
    $admin_data = array_merge($formData, [
        'username' => $user['username'],
        'email' => $user['email']
    ]);
    
    // Send to admin
    $admin_subject = "New Broker Application from " . $user['username'];
    sendNotificationEmail('broker_application_admin', $admin_data, $adminEmail, $admin_subject);

    // Data for user email
    $user_data = [
        'username' => $user['username']
    ];

    // Send to user
    $user_subject = "Your Broker Application has been Received";
    sendNotificationEmail('broker_application_user', $user_data, $user['email'], $user_subject);

    return true; // Assumes queuing is successful
}
?>
