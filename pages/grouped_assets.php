<?php
require_once __DIR__ . '/../src/init.php';

$user = $_SESSION['user'];
$userId = $user['id'];

// Get asset_type_id from URL
$assetTypeId = $_GET['asset_type_id'] ?? null;

if (!$assetTypeId) {
    // Redirect or show an error if no asset type is specified
    header('Location: /shares');
    exit();
}

// Fetch the details of the asset type
$assetTypeStmt = $pdo_mysql->prepare("SELECT * FROM asset_types WHERE id = ?");
$assetTypeStmt->execute([$assetTypeId]);
$assetType = $assetTypeStmt->fetch(PDO::FETCH_ASSOC);

if (!$assetType) {
    // Redirect or show an error if asset type is invalid
    header('Location: /shares');
    exit();
}

// Fetch all individual assets for this user and asset type
$assetsStmt = $pdo_mysql->prepare(
    "SELECT *, (total_generational_received + total_shared_received) as total_earned
     FROM assets 
     WHERE user_id = ? AND asset_type_id = ? 
     ORDER BY created_at DESC"
);
$assetsStmt->execute([$userId, $assetTypeId]);
$individualAssets = $assetsStmt->fetchAll(PDO::FETCH_ASSOC);

$completedAssetsCount = 0;
$expiredAssetsCount = 0;
$now = new DateTime();
foreach ($individualAssets as $asset) {
    if ($asset['is_completed'] && !$asset['is_sold']) {
        $completedAssetsCount++;
    }
    $isExpired = $asset['is_manually_expired'] || ($asset['expires_at'] && new DateTime($asset['expires_at']) < $now);
    if ($isExpired && !$asset['is_completed'] && !$asset['is_sold']) {
        $expiredAssetsCount++;
    }
}

$page_title = htmlspecialchars($assetType['name']) . " Details";
$session_message = $_SESSION['sell_asset_message'] ?? null;
$session_status = $_SESSION['sell_asset_status'] ?? null;
unset($_SESSION['sell_asset_message'], $_SESSION['sell_asset_status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo $page_title; ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
    :root {
      --primary-text: #1f2937;
      --secondary-text: #6b7280;
      --positive: #16a34a;
      --negative: #dc2626;
      --background: #f9fafb;
      --card-bg: #ffffff;
      --card-bg-hover: #f9fafb;
      --border: #e5e7eb;
      --accent: #2563eb;
      --header-bg: #ffffff;
      --shadow-color: rgba(0,0,0,0.05);
      --success-bg: rgba(4, 120, 87, 0.1);
      --success-text: #065f46;
      --error-bg: rgba(185, 28, 28, 0.1);
      --error-text: #991b1b;
    }

    html[data-theme="dark"] {
      --primary-text: #f9fafb;
      --secondary-text: #9ca3af;
      --positive: #4ade80;
      --negative: #f87171;
      --background: #111827;
      --card-bg: #1f2937;
      --card-bg-hover: #374151;
      --border: #374151;
      --accent: #3b82f6;
      --header-bg: #1f2937;
      --shadow-color: rgba(0,0,0,0.2);
      --success-bg: rgba(74, 222, 128, 0.1);
      --success-text: #a7f3d0;
      --error-bg: rgba(248, 113, 113, 0.1);
      --error-text: #fca5a5;
    }
    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--background);
        color: var(--primary-text);
    }
    .main-content {
      padding: 1rem;
    }
    .table-section {
      padding: 1rem 0;
    }
    .section-title {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      padding-bottom: 0.75rem;
      color: var(--primary-text);
    }
    .table-container {
      overflow-x: auto;
      border-radius: 0.75rem;
      border: 1px solid var(--border);
      background-color: var(--header-bg);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }
    th, td {
      text-align: left;
      padding: 1rem;
      font-size: 0.7rem;
      vertical-align: middle;
      white-space: nowrap;
    }
    th {
      font-weight: 500;
      color: var(--secondary-text);
      border-bottom: 1px solid var(--border);
    }
    tr:not(:last-child) td {
      border-bottom: 1px solid var(--border);
    }
    .asset-value {
        color: var(--secondary-text);
    }
    .positive {
        color: var(--positive);
    }
    .progress-container {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .progress-bar-bg {
      width: 5.5rem;
      height: 0.25rem;
      border-radius: 0.125rem;
      background-color: var(--border);
    }
    .progress-bar {
      height: 100%;
      border-radius: 0.125rem;
      background-color: var(--accent);
    }
    .status-btn {
      min-width: 5.25rem;
      cursor: pointer;
      border-radius: 0.5rem;
      height: 2rem;
      padding: 0 0.75rem;
      color: var(--primary-text);
      font-weight: 500;
      border: none;
      font-size: 0.7rem;
    }
    .status-active { background-color: rgba(34, 197, 94, 0.2); color: #22c55e; }
    .status-completed { background-color: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    .status-expired { background-color: rgba(239, 68, 68, 0.2); color: #ef4444; }
    .back-chevron {
        color: var(--primary-text);
    }

    /* Modal Styles */
    .pin-modal {
        position: fixed;
        inset: 0;
        background-color: var(--background);
        color: var(--primary-text);
        display: none;
        flex-direction: column;
        padding: 1rem;
        z-index: 50;
    }
    .pin-modal.visible {
        display: flex;
    }

    /* Alert Box */
    .alert-box {
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .alert-success {
        background-color: var(--success-bg);
        color: var(--success-text);
    }
    .alert-error {
        background-color: var(--error-bg);
        color: var(--error-text);
    }
</style>
</head>
<body>
<div class="max-w-md mx-auto pb-20">
  <header class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
    <a href="/shares" class="material-icons back-chevron">chevron_left</a>
    <h1 class="font-bold text-base text-primary"><?php echo htmlspecialchars($assetType['name']); ?></h1>
    <div></div>
  </header>

  <main class="main-content">
    <?php if ($session_message): ?>
    <div id="alertBox" class="alert-box <?php echo $session_status === 'success' ? 'alert-success' : 'alert-error'; ?>">
        <span><?php echo htmlspecialchars($session_message); ?></span>
        <button onclick="document.getElementById('alertBox').style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <div class="flex items-center mb-2">
      <img alt="Asset Logo" id="assetLogo" class="h-20 w-20 mr-4 rounded-md" src="<?php echo htmlspecialchars($assetType['image_link']); ?>"/>
      <div>
        <h2 class="font-bold text-xl" id="assetName"><?php echo htmlspecialchars($assetType['name']); ?></h2>
      </div>
    </div>

    <div class="mb-3">
      <p id="priceDisplay" class="text-2xl font-bold">SV<?php echo number_format($assetType['price'], 2); ?></p>
    </div>

    <div class="flex items-center mb-3 text-xs text-gray-600 dark:text-gray-400 gap-2">
        <a href="/buy_shares?asset_type_id=<?php echo $assetTypeId; ?>" class="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white font-bold py-2 px-4 rounded-lg shadow-md transform hover:-translate-y-0.5 transition text-xs">Buy More</a>
        <?php if ($completedAssetsCount > 0): ?>
            <a href="/buy_shares?asset_type_id=<?php echo $assetTypeId; ?>&intent=sell_completed" class="bg-yellow-500 hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transform hover:-translate-y-0.5 transition text-xs">Sell <?php echo $completedAssetsCount; ?> Completed</a>
        <?php endif; ?>
        <?php if ($expiredAssetsCount > 0): ?>
            <button id="sellExpiredBtn" class="bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transform hover:-translate-y-0.5 transition text-xs">
                Sell <?php echo $expiredAssetsCount; ?> Expired
            </button>
        <?php endif; ?>
    </div>

    <div class="table-section">
        <h2 class="section-title">My Assets</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date Purchased</th>
                        <th>Total Earned</th>
                        <th>Estimated Quantity</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($individualAssets)): ?>
                        <tr><td colspan="5" style="text-align: center;">You do not have any assets of this type yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($individualAssets as $asset): ?>
                            <?php
                                $payoutCap = $assetType['payout_cap'];
                                $progress = ($payoutCap > 0) ? ($asset['total_earned'] / $payoutCap) * 100 : 0;
                                $progress = min(100, $progress); // Cap progress at 100%
                                $estimatedQuantity = ($assetType['dividing_price'] > 0) ? (($assetType['price'] * 100) / $assetType['dividing_price']) : 0;
                                $status = 'Active';
                                $statusClass = 'status-active';
                                if ($asset['is_sold']) {
                                    $status = 'Sold';
                                    $statusClass = 'status-expired';
                                } elseif ($asset['is_completed']) {
                                    $status = 'Completed';
                                    $statusClass = 'status-completed';
                                } elseif ($asset['sale_status'] === 'pending') {
                                    $status = 'Selling';
                                    $statusClass = 'status-completed'; // Using blue for "in progress" status
                                } elseif ($asset['is_manually_expired'] || ($asset['expires_at'] && new DateTime($asset['expires_at']) < $now)) {
                                    $status = 'Expired';
                                    $statusClass = 'status-expired';
                                }
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($asset['created_at'])); ?></td>
                                <td>
                                    <div class="asset-value positive">+SV <?php echo number_format($asset['total_earned'], 2); ?></div>
                                </td>
                                <td>
                                    <div class="asset-value"><?php echo number_format($estimatedQuantity, 2); ?></div>
                                </td>
                                <td>
                                    <div class="progress-container">
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                                        </div>
                                        <span><?php echo number_format($progress, 0); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <button class="status-btn <?php echo $statusClass; ?>"><?php echo $status; ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>
</div>

<!-- PIN Modal for Selling Expired Assets -->
<div id="sellExpiredModal" class="pin-modal">
    <div class="flex flex-col h-full">
        <header class="flex justify-between items-center mb-4">
            <button id="closeSellExpiredModal" class="text-text-primary dark:text-text-primary-dark">
                <span class="material-icons">arrow_back</span>
            </button>
            <h2 class="text-lg font-bold">Confirm Sale</h2>
            <div></div>
        </header>
        <main class="flex-grow flex flex-col justify-center items-center">
            <div class="text-center">
                <p class="text-lg text-secondary-text dark:text-secondary-text">Enter PIN to confirm sale of</p>
                <p class="text-lg font-bold text-text-primary dark:text-text-primary mb-2"><strong><?php echo $expiredAssetsCount; ?></strong> expired shares of <strong><?php echo htmlspecialchars($assetType['name']); ?></strong></p>
                
                <div class="my-4 flex justify-center">
                    <div id="pinDisplayContainer" class="flex space-x-2">
                                                <input type="password" id="pinInput1" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-card-bg dark:bg-card-bg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary pin-modal-input" readonly>
                        <input type="password" id="pinInput2" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-card-bg dark:bg-card-bg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary pin-modal-input" readonly>
                        <input type="password" id="pinInput3" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-card-bg dark:bg-card-bg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary pin-modal-input" readonly>
                        <input type="password" id="pinInput4" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-card-bg dark:bg-card-bg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary pin-modal-input" readonly>
                    </div>
                </div>
            </div>
        </main>
        <div class="pb-12">
            <form id="sellExpiredForm" method="post" action="sell_all_expired_assets">
                <input type="hidden" name="asset_type_id" value="<?php echo $assetTypeId; ?>">
                <input type="hidden" name="transaction_pin" id="transaction_pin_hidden">
                <button type="submit" id="confirmSellBtn" class="w-full bg-red-500 hover:bg-red-600 text-white py-4 rounded-full text-lg font-medium mb-6" disabled>
                    Confirm Sale
                </button>
            </form>
            <div id="pinNumpad" class="grid grid-cols-3 gap-y-4 text-center text-3xl font-light">
                <button type="button" class="text-text-primary dark:text-text-primary">1</button>
                <button type="button" class="text-text-primary dark:text-text-primary">2</button>
                <button type="button" class="text-text-primary dark:text-text-primary">3</button>
                <button type="button" class="text-text-primary dark:text-text-primary">4</button>
                <button type="button" class="text-text-primary dark:text-text-primary">5</button>
                <button type="button" class="text-text-primary dark:text-text-primary">6</button>
                <button type="button" class="text-text-primary dark:text-text-primary">7</button>
                <button type="button" class="text-text-primary dark:text-text-primary">8</button>
                <button type="button" class="text-text-primary dark:text-text-primary">9</button>
                <div></div>
                <button type="button" class="text-text-primary dark:text-text-primary">0</button>
                <button type="button" id="pinBackspaceBtn" class="text-red-500"><span class="material-icons">backspace</span></button>
            </div>
        </div>
    </div>
</div>

<script>
  (function() {
      const savedTheme = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
  })();

  document.addEventListener('DOMContentLoaded', function() {
    const sellExpiredBtn = document.getElementById('sellExpiredBtn');
    const sellExpiredModal = document.getElementById('sellExpiredModal');
    const closeSellExpiredModal = document.getElementById('closeSellExpiredModal');
    const pinInputs = document.querySelectorAll('#pinDisplayContainer input');
    const pinNumpad = document.getElementById('pinNumpad');
    const pinBackspaceBtn = document.getElementById('pinBackspaceBtn');
    const transactionPinHidden = document.getElementById('transaction_pin_hidden');
    const confirmSellBtn = document.getElementById('confirmSellBtn');
    const sellExpiredForm = document.getElementById('sellExpiredForm');

    if (sellExpiredBtn) {
        sellExpiredBtn.addEventListener('click', () => {
            sellExpiredModal.classList.add('visible');
        });
    }

    if(closeSellExpiredModal) {
        closeSellExpiredModal.addEventListener('click', () => {
            sellExpiredModal.classList.remove('visible');
            resetPin();
        });
    }

    if (pinNumpad) {
        pinNumpad.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' && e.target.id !== 'pinBackspaceBtn') {
                const num = e.target.textContent;
                let currentPin = transactionPinHidden.value;
                if (currentPin.length < 4) {
                    pinInputs[currentPin.length].value = num;
                    transactionPinHidden.value += num;
                }
                if (transactionPinHidden.value.length === 4) {
                    confirmSellBtn.disabled = false;
                }
            }
        });
    }

    if (pinBackspaceBtn) {
        pinBackspaceBtn.addEventListener('click', () => {
            let currentPin = transactionPinHidden.value;
            if (currentPin.length > 0) {
                pinInputs[currentPin.length - 1].value = '';
                transactionPinHidden.value = currentPin.slice(0, -1);
            }
            confirmSellBtn.disabled = true;
        });
    }
    
    if(sellExpiredForm) {
        sellExpiredForm.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to sell all <?php echo $expiredAssetsCount; ?> expired assets of this type?')) {
                e.preventDefault();
                resetPin();
                sellExpiredModal.classList.remove('visible');
            }
        });
    }

    function resetPin() {
        pinInputs.forEach(input => input.value = '');
        transactionPinHidden.value = '';
        confirmSellBtn.disabled = true;
    }
  });
</script>
</body>
</html>