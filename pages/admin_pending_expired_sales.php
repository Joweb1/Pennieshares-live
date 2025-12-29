<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/assets_functions.php'; // For getPendingExpiredSales
require_once __DIR__ . '/../src/functions.php'; // For getUserByIdOrName, creditUserWallet, sendNotificationEmail, sendPushNotification

check_auth();

// Admin Access Check
if (!isset($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied: You do not have administrative privileges.");
}

$actionMessage = '';
if (isset($_SESSION['action_message'])) {
    $actionMessage = $_SESSION['action_message'];
    unset($_SESSION['action_message']);
}

// Handle Admin Actions (Approve/Delay)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $assetId = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    if (!$assetId) {
        $_SESSION['action_message'] = "Error: Invalid Asset ID.";
        header("Location: admin_pending_expired_sales");
        exit();
    }

    try {
        $pdo_mysql->beginTransaction();

        $stmt = $pdo_mysql->prepare("SELECT a.id, a.user_id, a.asset_type_id, at.price as original_price, at.name as asset_type_name FROM assets a JOIN asset_types at ON a.asset_type_id = at.id WHERE a.id = ? AND a.sale_status = 'pending'");
        $stmt->execute([$assetId]);
        $pendingAsset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendingAsset) {
            $pdo_mysql->rollBack();
            $_SESSION['action_message'] = "Error: Pending asset not found or not in 'pending' status.";
            header("Location: admin_pending_expired_sales");
            exit();
        }

        $user = getUserByIdOrName($pendingAsset['user_id']);
        if (!$user) {
            $pdo_mysql->rollBack();
            $_SESSION['action_message'] = "Error: User not found for asset #{$assetId}.";
            header("Location: admin_pending_expired_sales");
            exit();
        }

        if ($_POST['action'] === 'approve_sale') {
            $salePrice = $pendingAsset['original_price'] * 0.10; // 10% of original price

            // Credit user's wallet
            $creditResult = creditUserWallet($pendingAsset['user_id'], $salePrice, "Sale of expired asset: {$pendingAsset['asset_type_name']} (ID: {$assetId})");

            if (!$creditResult) {
                $pdo_mysql->rollBack();
                $_SESSION['action_message'] = "Error: Failed to credit user wallet for asset #{$assetId}.";
                header("Location: admin_pending_expired_sales");
                exit();
            }

            // Mark asset as approved
            $updateStmt = $pdo_mysql->prepare("UPDATE assets SET sale_status = 'approved', is_sold = 1 WHERE id = ?");
            $updateStmt->execute([$assetId]);
            
            // Send success email
            $email_data = [
                'username' => $user['username'],
                'asset_name' => htmlspecialchars($pendingAsset['asset_type_name']),
                'sale_price' => number_format($salePrice, 2)
            ];
            sendNotificationEmail('expired_asset_sale_approved_user', $email_data, $user['email'], 'Your Expired Asset Sale Approved!');

            // Send push notification
            $payload = [
                'title' => 'Expired Asset Sale Approved!',
                'body' => 'Your ' . htmlspecialchars($pendingAsset['asset_type_name']) . ' has been sold and SV' . number_format($salePrice, 2) . ' credited to your wallet.',
                'icon' => 'assets/images/logo.png',
            ];
            sendPushNotification($pendingAsset['user_id'], $payload);

            $_SESSION['action_message'] = "Asset #{$assetId} sale approved. SV" . number_format($salePrice, 2) . " credited to {$user['username']}'s wallet.";

        } elseif ($_POST['action'] === 'delay_sale') {
            // Mark asset as delayed
            $updateStmt = $pdo_mysql->prepare("UPDATE assets SET sale_status = 'delayed' WHERE id = ?");
            $updateStmt->execute([$assetId]);

            // Send delay email
            $email_data = [
                'username' => $user['username'],
                'asset_name' => htmlspecialchars($pendingAsset['asset_type_name'])
            ];
            sendNotificationEmail('expired_asset_sale_delayed_user', $email_data, $user['email'], 'Expired Asset Sale Delayed');

            // Send push notification
            $payload = [
                'title' => 'Expired Asset Sale Delayed',
                'body' => 'There was an issue with the sale of your ' . htmlspecialchars($pendingAsset['asset_type_name']) . '. It will be resolved and credited within 5 working days.',
                'icon' => 'assets/images/logo.png',
            ];
            sendPushNotification($pendingAsset['user_id'], $payload);

            $_SESSION['action_message'] = "Asset #{$assetId} sale delayed. User notified.";
        }
        
        $pdo_mysql->commit();

    } catch (Exception $e) {
        if ($pdo_mysql->inTransaction()) {
            $pdo_mysql->rollBack();
        }
        error_log("Error processing expired asset sale: " . $e->getMessage());
        $_SESSION['action_message'] = "Error processing action for asset #{$assetId}: " . $e->getMessage();
    }
    header("Location: admin_pending_expired_sales");
    exit();
}


$pendingExpiredSales = getPendingExpiredSales();

$pageTitle = "Pending Expired Sales";
include __DIR__ . '/../assets/template/intro-template.php';
?>

<style>
    .admin-container {
        width: 95vw;
        margin: 2rem auto;
        margin-left: -9rem; /* Adjusted for wider layout */
        padding: 1rem;
        background-color: var(--bg-secondary);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        animation: fadeIn 0.5s ease-out;
    }

    h1 {
        color: var(--text-primary);
        text-align: center;
        margin-bottom: 1.5rem;
        font-size: 2.2rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--accent-color), #60efff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .message {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        text-align: center;
        animation: slideIn 0.4s ease-out;
    }

    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .table-responsive {
        overflow-x: auto;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 8px;
        overflow: hidden;
    }
    th, td {
        border: 1px solid var(--border-color);
        padding: 12px 15px;
        text-align: left;
        vertical-align: middle;
        color: var(--text-secondary);
    }
    th {
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.9em;
    }
    tr:nth-child(even) {
        background-color: var(--bg-tertiary);
    }
    tr:hover {
        background-color: var(--border-color);
    }
    .action-buttons form {
        display: inline-block;
        margin-right: 5px;
    }
    .action-buttons button {
        padding: 8px 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }
    .action-buttons .approve-btn {
        background-color: #28a745;
        color: white;
    }
    .action-buttons .approve-btn:hover {
        background-color: #218838;
    }
    .action-buttons .delay-btn {
        background-color: #ffc107;
        color: #212529;
    }
    .action-buttons .delay-btn:hover {
        background-color: #e0a800;
    }

    @media (max-width: 768px) {
        .admin-container {
            margin-left: auto;
            padding: 1rem;
        }
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        tr {
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        td {
            border: none;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            padding-left: 50%;
            text-align: right;
        }
        td:before {
            position: absolute;
            top: 6px;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            content: attr(data-label);
            font-weight: 600;
            color: var(--text-primary);
            text-align: left;
        }
    }
</style>

<div class="admin-container">
    <h1>Pending Expired Asset Sales</h1>

    <?php if ($actionMessage): ?>
        <div class="message <?php echo strpos(strtolower($actionMessage), 'error') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($actionMessage); ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (!empty($pendingExpiredSales)): ?>
        <table>
            <thead>
                <tr>
                    <th>Asset ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Asset Type</th>
                    <th>Original Price</th>
                    <th>Requested At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingExpiredSales as $sale): ?>
                <tr>
                    <td data-label="Asset ID"><?php echo htmlspecialchars($sale['asset_id']); ?></td>
                    <td data-label="User"><?php echo htmlspecialchars($sale['username']); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($sale['email']); ?></td>
                    <td data-label="Asset Type"><?php echo htmlspecialchars($sale['asset_type_name']); ?></td>
                    <td data-label="Original Price">SV<?php echo number_format($sale['original_price'], 2); ?></td>
                    <td data-label="Requested At"><?php echo htmlspecialchars($sale['asset_created_at']); ?></td>
                    <td data-label="Actions" class="action-buttons">
                        <form method="post" action="admin_pending_expired_sales" onsubmit="return confirm('Are you sure you want to approve this sale?');">
                            <input type="hidden" name="action" value="approve_sale">
                            <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($sale['asset_id']); ?>">
                            <button type="submit" class="approve-btn">Approve</button>
                        </form>
                        <form method="post" action="admin_pending_expired_sales" onsubmit="return confirm('Are you sure you want to delay this sale?');">
                            <input type="hidden" name="action" value="delay_sale">
                            <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($sale['asset_id']); ?>">
                            <button type="submit" class="delay-btn">Delay</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; color: var(--text-secondary);">No pending expired asset sales found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../assets/template/end-template.php'; ?>
