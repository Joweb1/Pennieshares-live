<?php
require_once __DIR__ . '/../../src/functions.php';
check_auth();

header('Content-Type: application/json');

$loggedInUser = $_SESSION['user'];
$loggedInUserId = $loggedInUser['id'];

function getKycStatus($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT status FROM kyc_verifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

$kycStatus = getKycStatus($pdo_mysql, $loggedInUserId);

if ($kycStatus) {
    echo json_encode(['kyc_status' => $kycStatus]);
} else {
    echo json_encode(['kyc_status' => 'not_submitted']);
}
?>