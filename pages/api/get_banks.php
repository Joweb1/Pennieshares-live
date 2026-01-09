<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../src/functions.php';

header('Content-Type: application/json');

$banks = getPaystackBankList();

if ($banks) {
    echo json_encode($banks);
} else {
    // If the file doesn't exist or is empty, return an empty array
    echo json_encode([]);
}