<?php
require_once __DIR__ . '/../src/init.php';

// Admin Access Check
if (!isset($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied: You do not have administrative privileges.");
}

$searchedUser = null;
$userAssets = [];
$userTransactions = [];
$assetsWorth = 0;
$brokerStats = null;
$message = '';

if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    if (!empty($searchTerm)) {
        $searchedUser = findUser($searchTerm);
        if ($searchedUser) {
            $userAssets = getUserAssets($searchedUser['id']);
            $userTransactions = getPaginatedWalletTransactions($searchedUser['id'], 10, 0);
            $assetsWorth = getUserAssetsWorth($searchedUser['id']);
            if ($searchedUser['is_broker']) {
                $brokerStats = getBrokerReferralStats($searchedUser['id']);
            }
        } else {
            $message = "User not found.";
        }
    } else {
        $message = "Please enter a search term.";
    }
}

$pageTitle = "Admin - User View";
include __DIR__ . '/../assets/template/intro-template.php';
?>

<style>
    .admin-container {
        width: 95vw;
        margin: 2rem auto;
        margin-left: -9rem;
        padding: 1rem;
        background-color: var(--bg-secondary);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    .search-bar {
        margin-bottom: 1.5rem;
    }
    .user-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .detail-card {
        background-color: var(--bg-tertiary);
        padding: 1rem;
        border-radius: 8px;
    }
    .detail-card h4 {
        margin-bottom: 0.5rem;
        color: var(--text-secondary);
    }
    .detail-card p {
        font-weight: 600;
        color: var(--text-primary);
    }
    .status-verified { color: #28a745; }
    .status-not-verified { color: #dc3545; }
    .table-responsive {
        overflow-x: auto;
        margin-top: 1.5rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid var(--border-color);
        padding: 8px;
        text-align: left;
    }
    th {
        background-color: var(--bg-tertiary);
    }
    .broker-stats-card-v2 {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #0000CD, #B22222);
        color: #ffffff;
        padding: 1.5rem;
        border-radius: 12px;
        margin-top: 2rem;
    }
    .broker-stats-card-v2 .stat-title {
        color: #ffffff;
    }
    .broker-card-bg-circle-1, .broker-card-bg-circle-2 {
        position: absolute;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        z-index: 0;
    }
    .broker-card-bg-circle-1 { top: -3rem; right: -3rem; width: 12rem; height: 12rem; }
    .broker-card-bg-circle-2 { bottom: -4rem; left: -4rem; width: 16rem; height: 16rem; opacity: 0.5; }
    .broker-stats-v2-content {
        position: relative;
        z-index: 1;
        margin-top: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .broker-stats-v2-row {
        display: flex;
        gap: 1rem;
    }
    .broker-stat-v2-item, .broker-stat-v2-item-full {
        background-color: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(4px);
        border-radius: 0.75rem;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .broker-stat-v2-item { width: 50%; }
    .broker-stat-v2-item-full { width: 100%; }
    .broker-stat-v2-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.5rem;
        color: #ffffff;
    }
    .broker-stat-v2-icon-wrapper.users { background-color: #3B82F6; }
    .broker-stat-v2-icon-wrapper.assets { background-color: #10b981; }
    .broker-stat-v2-icon-wrapper.bonus { background-color: #EF4444; }
    .broker-stat-v2-text .broker-stat-v2-label {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }
    .broker-stat-v2-text .broker-stat-v2-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #ffffff;
        margin: 0;
    }

    @media (max-width: 768px) {
        .admin-container {
            width: 100%;
            margin-left: 0;
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
            font-weight: bold;
            color: var(--text-primary);
            text-align: left;
        }
        .search-bar form {
            display: flex;
            flex-direction: column;
        }
        .search-bar input {
            width: 100% !important;
            margin-bottom: 10px;
        }
        .broker-stats-v2-row {
            flex-direction: column;
        }
        .broker-stat-v2-item {
            width: 100%;
        }
    }
</style>

<div class="admin-container">
    <h1>User Activity Viewer</h1>

    <div class="search-bar">
        <form method="GET" action="admin_user_view">
            <input type="text" name="search" placeholder="Search by Username, Email, or Partner Code" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="width: 300px; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            <button type="submit" style="padding: 10px 15px; border-radius: 8px; border: none; background-color: var(--accent-color); color: var(--accent-text); cursor: pointer;">Search</button>
        </form>
    </div>

    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if ($searchedUser): ?>
        <h2>User Details</h2>
        <div class="user-details-grid">
            <div class="detail-card">
                <h4>Username</h4>
                <p><?php echo htmlspecialchars($searchedUser['username']); ?></p>
            </div>
            <div class="detail-card">
                <h4>Email</h4>
                <p><?php echo htmlspecialchars($searchedUser['email']); ?></p>
            </div>
            <div class="detail-card">
                <h4>Full Name</h4>
                <p><?php echo htmlspecialchars($searchedUser['fullname']); ?></p>
            </div>
            <div class="detail-card">
                <h4>Partner Code</h4>
                <p><?php echo htmlspecialchars($searchedUser['partner_code']); ?></p>
            </div>
            <div class="detail-card">
                <h4>Wallet Balance</h4>
                <p>SV <?php echo number_format($searchedUser['wallet_balance'], 2); ?></p>
            </div>
            <div class="detail-card">
                <h4>Total Return</h4>
                <p>SV <?php echo number_format($searchedUser['total_return'], 2); ?></p>
            </div>
            <div class="detail-card">
                <h4>Assets Worth</h4>
                <p>SV <?php echo number_format($assetsWorth, 2); ?></p>
            </div>
            <div class="detail-card">
                <h4>Email Verified</h4>
                <p class="<?php echo $searchedUser['is_verified'] ? 'status-verified' : 'status-not-verified'; ?>">
                    <?php echo $searchedUser['is_verified'] ? 'Yes' : 'No'; ?>
                </p>
            </div>
            <div class="detail-card">
                <h4>KYC Verified</h4>
                 <p class="<?php echo $searchedUser['status'] == 2 ? 'status-verified' : 'status-not-verified'; ?>">
                    <?php echo $searchedUser['status'] == 2 ? 'Yes' : 'No'; ?>
                </p>
            </div>
            <div class="detail-card">
                <h4>Is Broker?</h4>
                <p><?php echo $searchedUser['is_broker'] ? 'Yes' : 'No'; ?></p>
            </div>
            <div class="detail-card">
                <h4>License Status</h4>
                <p class="<?php echo $searchedUser['status'] == 2 ? 'status-verified' : 'status-not-verified'; ?>">
                    <?php echo $searchedUser['status'] == 2 ? 'Activated' : 'Not Activated'; ?>
                </p>
            </div>
        </div>

        <?php if ($searchedUser['is_broker'] && $brokerStats): ?>
            <div class="broker-stats-card-v2">
                <div class="broker-card-bg-circle-1"></div>
                <div class="broker-card-bg-circle-2"></div>
                <div class="stat-header">
                    <h2 class="stat-title">Broker Activity</h2>
                    <span class="material-icons" style="color: rgba(255,255,255,0.8);">card_travel</span>
                </div>
                <div class="broker-stats-v2-content">
                    <div class="broker-stats-v2-row">
                        <div class="broker-stat-v2-item">
                            <div class="broker-stat-v2-icon-wrapper users">
                                <span class="material-icons">group</span>
                            </div>
                            <div class="broker-stat-v2-text">
                                <p class="broker-stat-v2-label">Clients</p>
                                <p class="broker-stat-v2-value"><?php echo $brokerStats['total_referred_users']; ?></p>
                            </div>
                        </div>
                        <div class="broker-stat-v2-item">
                            <div class="broker-stat-v2-icon-wrapper assets">
                                <span class="material-icons">account_balance_wallet</span>
                            </div>
                            <div class="broker-stat-v2-text">
                                <p class="broker-stat-v2-label">Client Assets</p>
                                <p class="broker-stat-v2-value"><?php echo $brokerStats['total_assets_of_referred_users']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="broker-stats-v2-row">
                        <div class="broker-stat-v2-item-full">
                            <div class="broker-stat-v2-icon-wrapper bonus">
                                <span class="material-icons">card_giftcard</span>
                            </div>
                            <div class="broker-stat-v2-text">
                                <p class="broker-stat-v2-label">Total Bonus</p>
                                <p class="broker-stat-v2-value">SV <?php echo number_format($brokerStats['total_referral_bonus'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h2 style="margin-top: 2rem;">User Assets</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Asset Name</th>
                        <th>Status</th>
                        <th>Total Earned</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userAssets)): ?>
                        <tr>
                            <td colspan="5" data-label="Assets">No assets found for this user.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userAssets as $asset): ?>
                            <tr>
                                <td data-label="Asset ID"><?php echo $asset['id']; ?></td>
                                <td data-label="Asset Name"><?php echo htmlspecialchars($asset['asset_type_name']); ?></td>
                                <td data-label="Status"><?php echo htmlspecialchars($asset['current_status']); ?></td>
                                <td data-label="Total Earned">SV <?php echo number_format($asset['total_earned'], 2); ?></td>
                                <td data-label="Created At"><?php echo $asset['created_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top: 2rem;">Latest Wallet Transactions</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userTransactions)): ?>
                        <tr>
                            <td colspan="4" data-label="Transactions">No transactions found for this user.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userTransactions as $transaction): ?>
                            <tr>
                                <td data-label="Date"><?php echo $transaction['created_at']; ?></td>
                                <td data-label="Type"><?php echo htmlspecialchars(ucfirst($transaction['type'])); ?></td>
                                <td data-label="Amount">SV <?php echo number_format($transaction['amount'], 2); ?></td>
                                <td data-label="Description"><?php echo htmlspecialchars($transaction['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../assets/template/end-template.php';
?>
