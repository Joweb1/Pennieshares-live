<?php
require_once __DIR__ . '/src/init.php';
require_once __DIR__ . '/src/email_functions.php'; // Assuming email functions are here

check_auth();

$user = $_SESSION['user'];
$loggedInUserId = $user['id'];

// Verify Paystack transaction
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $_ENV['PAYSTACK_SECRET_KEY'],
        "Cache-Control: no-cache",
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $paystack_response = json_decode($response, true);

    if ($httpcode == 200 && $paystack_response && $paystack_response['status'] && $paystack_response['data']['status'] === 'success') {
        $amount_naira_paid = $paystack_response['data']['amount'] / 100; // Convert kobo to Naira
        $amount_sv = $amount_naira_paid / 100; // Convert Naira to SV

        // Credit user's wallet
        $creditSuccess = creditUserWallet($loggedInUserId, $amount_sv, "Deposit via Paystack (Ref: {$reference})");

        if ($creditSuccess) {
            // Schedule emails
            $user_email_data = [
                'username' => $user['username'],
                'amount_sv' => number_format($amount_sv, 2),
                'amount_naira' => number_format($amount_naira_paid, 2),
                'reference' => $reference,
            ];
            sendNotificationEmail('add_money_success_user', $user_email_data, $user['email'], 'Your Pennieshares Deposit Was Successful!');

            $admin_email_data = [
                'username' => $user['username'],
                'email' => $user['email'],
                'amount_sv' => number_format($amount_sv, 2),
                'amount_naira' => number_format($amount_naira_paid, 2),
                'reference' => $reference,
            ];
            // Assuming admin email is configured in environment
            sendNotificationEmail('add_money_success_admin', $admin_email_data, $_ENV['ADMIN_EMAIL'], 'New Deposit Received on Pennieshares');
            
            $_SESSION['add_money_status'] = 'success';
            $_SESSION['add_money_message'] = "Your wallet has been credited with SV " . number_format($amount_sv, 2);

        } else {
            $_SESSION['add_money_status'] = 'error';
            $_SESSION['add_money_message'] = "Payment successful but failed to credit your wallet. Please contact support with reference: {$reference}";
            // Admin should still be notified of this discrepancy
            $admin_email_data = [
                'username' => $user['username'],
                'email' => $user['email'],
                'amount_naira' => number_format($amount_naira_paid, 2),
                'reference' => $reference,
                'error_detail' => "Payment successful but wallet credit failed."
            ];
            sendNotificationEmail('add_money_failure_admin', $admin_email_data, $_ENV['ADMIN_EMAIL'], 'Paystack Deposit Discrepancy Alert!');
        }
    } else {
        $_SESSION['add_money_status'] = 'error';
        $_SESSION['add_money_message'] = "Payment verification failed or was not successful. Reference: {$reference}";
        // Notify admin of failed verification
        $admin_email_data = [
            'username' => $user['username'],
            'email' => $user['email'],
            'reference' => $reference,
            'error_detail' => "Paystack verification failed. HTTP Code: {$httpcode}. Response: " . json_encode($paystack_response)
        ];
        sendNotificationEmail('add_money_failure_admin', $admin_email_data, $_ENV['ADMIN_EMAIL'], 'Paystack Deposit Verification Failed!');
    }
} else {
    $_SESSION['add_money_status'] = 'error';
    $_SESSION['add_money_message'] = "No payment reference provided.";
}

header("Location: /wallet"); // Redirect user to wallet page to see updated balance/message
exit;
