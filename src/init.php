<?php
// session_start(); // Start the session at the very beginning

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Start session and check authentication
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/content_functions.php';

// Only perform authentication checks and session-dependent logic if not running from CLI
if (php_sapi_name() !== 'cli') {
    check_auth();

    // Process any pending profits
    // processPendingProfits();

    // Get current user data
    $user = $_SESSION['user'];
    $partner_code = $user['partner_code'];

    // Count referrals is now in functions.php and uses pdo_mysql
    $referral_count = countReferrals($partner_code);

    // Check and send daily login email
    checkAndSendDailyLoginEmail($user['id']);
} else {
    // In CLI context, ensure $user and $partner_code are defined if needed by other included files
    // For this script, they are not directly used outside the web context, but good practice.
    $user = null;
    $partner_code = null;
    $referral_count = 0;
}

?>
