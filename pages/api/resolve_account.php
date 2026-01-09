<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../src/functions.php';

header('Content-Type: application/json');

$accountNumber = filter_input(INPUT_GET, 'account_number', FILTER_SANITIZE_STRING);
$bankCode = filter_input(INPUT_GET, 'bank_code', FILTER_SANITIZE_STRING);

if (empty($accountNumber) || empty($bankCode)) {
    echo json_encode(['status' => 'error', 'message' => 'Account number and bank code are required.']);
    exit;
}

$result = resolvePaystackBankAccount($accountNumber, $bankCode);

echo json_encode($result);