<?php
require_once __DIR__ . '/../src/init.php';

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$user = $_SESSION['user'];
$userId = $user['id'];

// Default redirect URL
$redirectUrl = "/shares";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['asset_type_id'])) {
    header("Location: {$redirectUrl}");
    exit();
}

$assetTypeId = filter_input(INPUT_POST, 'asset_type_id', FILTER_VALIDATE_INT);
$pin = trim($_POST['transaction_pin'] ?? '');

// Update redirect URL to go back to the specific asset group
if ($assetTypeId) {
    $redirectUrl = "/grouped_assets?asset_type_id={$assetTypeId}";
}

if (!$assetTypeId) {
    $_SESSION['sell_asset_message'] = 'Error: Invalid asset type specified.';
    $_SESSION['sell_asset_status'] = 'error';
} elseif (empty($pin) || !preg_match('/^\d{4}$/', $pin)) {
    $_SESSION['sell_asset_message'] = "Error: Please enter a valid 4-digit transaction PIN.";
    $_SESSION['sell_asset_status'] = 'error';
} else {
    $result = sellAllExpiredAssetsOfType($userId, $assetTypeId, $pin);
    $_SESSION['sell_asset_message'] = $result['message'];
    $_SESSION['sell_asset_status'] = $result['success'] ? 'success' : 'error';
}

header("Location: {$redirectUrl}");
exit();
?>