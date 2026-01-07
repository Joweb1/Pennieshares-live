<?php
require_once __DIR__ . '/src/init.php';
check_auth();

// if (!isset($_SESSION['paystack_data'])) {
//     header("Location: /add_money");
//     exit;
// }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$svAmount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$pin = filter_input(INPUT_POST, 'transaction_pin', FILTER_SANITIZE_NUMBER_INT);
$loggedInUser = $_SESSION['user'];

if (!$svAmount || $svAmount <= 0 || !$pin) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

// 1. Verify PIN
if (!verifyTransactionPin($loggedInUser['id'], $pin)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction PIN.']);
    exit;
}

// 2. Check amount limit
if ($svAmount > 200) {
    // This server-side check is a fallback. The JS should prevent this.
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Transaction limit of SV200 exceeded. Contact a broker to fund your wallet.']);
    exit;
}

// 3. Prepare data for Paystack
$nairaAmount = $svAmount * 100 * 100; // Amount in kobo
$email = $loggedInUser['email'];
$reference = 'pns_' . uniqid() . '_' . $loggedInUser['id'];
$callback_url = BASE_URL . '/add_money_callback'; 

// Load Paystack secret key from .env
$paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'];
$paystackPublicKey = $_ENV['PAYSTACK_PUBLIC_KEY'];


$paystack_data = [
    'key' => $paystackPublicKey,
    'email' => $email,
    'amount' => $nairaAmount,
    'currency' => 'NGN',
    'ref' => $reference,
    'callback_url' => $callback_url,
    // Store metadata to be verified in callback
    'metadata' => [
        'user_id' => $loggedInUser['id'],
        'sv_amount' => $svAmount,
        'reference' => $reference
    ]
];

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'paystack_data' => $paystack_data]);
exit;
