<?php
// --- Constants for Fund Distribution ---
define('GENERATIONAL_POT_ALLOCATION', 7.50);
define('PAYOUT_PER_GENERATION_EVENT_FROM_POT', 1.50); // 7.5 / 5 generations
define('SHARED_POT_ALLOCATION', 5.00);
define('COMPANY_PROFIT_ALLOCATION', 3.00);
define('REFERRAL_BONUS', 2.50);
define('FIXED_ALLOCATIONS_TOTAL', GENERATIONAL_POT_ALLOCATION + SHARED_POT_ALLOCATION + COMPANY_PROFIT_ALLOCATION + REFERRAL_BONUS); // 7.5 + 5 + 3 + 2.5 = 18

define('CHILDREN_PER_ASSET', 2);
define('MAX_GENERATIONS_PAYOUT_DEPTH', 5);


// --- Helper Functions ---
function getAssetTypes() {
    global $pdo_mysql;
    return $pdo_mysql->query("SELECT * FROM asset_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function getCompanyFunds() {
    global $pdo_mysql;
    return $pdo_mysql->query("SELECT * FROM company_funds WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}

// --- New Function to Check and Mark Expired Assets ---
function checkAndMarkExpiredAssets() {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    // Select assets that have an expiry date, are not already completed, not already marked manually expired, and whose expiry date is in the past
    $stmt = $pdo_mysql->prepare("SELECT a.id, a.user_id, at.name as asset_name FROM assets a JOIN asset_types at ON a.asset_type_id = at.id WHERE expires_at IS NOT NULL AND expires_at < :now AND is_completed = 0 AND is_manually_expired = 0");
    $stmt->execute([':now' => $now]);
    $expiredAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredAssets as $asset) {
        $updateStmt = $pdo_mysql->prepare("UPDATE assets SET is_manually_expired = 1 WHERE id = ?");
        $updateStmt->execute([$asset['id']]);

        // Send email notification
        /*
        $user = getUserByIdOrName($asset['user_id']);
        if ($user) {
            $emailBody = getAssetStatusEmailTemplate(
                $user['username'],
                $asset['asset_name'],
                'Expired',
                "Your asset '{$asset['asset_name']}' has reached its expiration date and is no longer active."
                . "\nAsset ID: #{$asset['id']}"
            );
            sendEmail($user['email'], $user['username'], "Asset Expired - Pennieshares", $emailBody);
        }
        */
    }
    return count($expiredAssets); // Returns the number of assets newly marked as expired
}

function findEligibleParent() {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    // Find an active asset that is not completed, not manually expired,
    // not expired by date, and has less than CHILDREN_PER_ASSET children.
    // Order by created_at ASC to prioritize older assets for parenting.
    $stmt = $pdo_mysql->prepare(
        "SELECT id, generation\n        FROM assets\n        WHERE is_completed = 0\n          AND is_manually_expired = 0\n          AND (expires_at IS NULL OR expires_at > :now)\n          AND children_count < :children_per_asset\n        ORDER BY created_at ASC\n        LIMIT 1"
    );
    $stmt->execute([
        ':now' => $now,
        ':children_per_asset' => CHILDREN_PER_ASSET
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// --- Core Logic Functions ---



function getAncestorsForPayout($childAssetId, $maxDepth = MAX_GENERATIONS_PAYOUT_DEPTH) {
    global $pdo_mysql;
    $ancestors = []; 
    $currentAssetId = $childAssetId;
    $depth = 0;
    $now = date('Y-m-d H:i:s'); // For checking if ancestor is currently expired

    while ($depth < $maxDepth) {
        $stmt = $pdo_mysql->prepare("SELECT parent_id FROM assets WHERE id = ?");
        $stmt->execute([$currentAssetId]);
        $parentId = $stmt->fetchColumn();

        if ($parentId) {
            $ancestorDetailsStmt = $pdo_mysql->prepare("
                SELECT a.id, a.user_id, a.asset_type_id, at.payout_cap, a.is_completed, a.is_manually_expired, a.expires_at, a.total_generational_received
                FROM assets a
                JOIN asset_types at ON a.asset_type_id = at.id
                WHERE a.id = ?
            ");
            $ancestorDetailsStmt->execute([$parentId]);
            $ancestorDetail = $ancestorDetailsStmt->fetch(PDO::FETCH_ASSOC);
            if ($ancestorDetail) {
                // Determine if effectively expired now, even if not yet marked by checkAndMarkExpiredAssets
                $ancestorDetail['is_currently_expired'] = ($ancestorDetail['expires_at'] && $ancestorDetail['expires_at'] < $now) || $ancestorDetail['is_manually_expired'] == 1;
                $ancestors[] = $ancestorDetail;
            }
            $currentAssetId = $parentId;
            $depth++;
        } else {
            break; 
        }
    }
    return $ancestors;
}


// --- buyAsset Function (Updated with expiration check and logging) ---
function buyAsset($userId, $assetTypeId, $numAssetsToBuy = 1) {
    global $pdo_mysql;
    error_log("buyAsset called: userId={$userId}, assetTypeId={$assetTypeId}, numAssetsToBuy={$numAssetsToBuy}");
    $overallResults = ['purchases' => [], 'summary' => [], 'expired_check_count' => 0];
    $now = date('Y-m-d H:i:s');

    // Perform expiration check before any purchases
    $expiredCount = checkAndMarkExpiredAssets();
    $overallResults['expired_check_count'] = $expiredCount;

    $assetTypeStmt = $pdo_mysql->prepare("SELECT * FROM asset_types WHERE id = ?");
    $assetTypeStmt->execute([$assetTypeId]);
    $assetType = $assetTypeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$assetType) {
        throw new Exception("Invalid Asset Type ID {$assetTypeId}.");
    }

    $totalCost = $numAssetsToBuy * $assetType['price'];

    // Check user wallet balance (already debited by calling function)

    for ($count = 0; $count < $numAssetsToBuy; $count++) {
        $currentPurchaseResult = [
            'asset_id' => null, 'message' => '', 'parent_update' => '',
            'company_profit_log' => '', 'reservation_direct_log' => '',
            'generational_payouts_log' => [], 'shared_payouts_log' => [],
            'referral_bonus_log' => ''
        ];

        $expires_at = null;
        if ($assetType['duration_months'] > 0) {
            $dt = new DateTime($now);
            $dt->add(new DateInterval("P{$assetType['duration_months']}M"));
            $expires_at = $dt->format('Y-m-d H:i:s');
        }

        $eligibleParentInfo = findEligibleParent();
        $parentId = null;
        $newAssetGeneration = 1;
        if ($eligibleParentInfo) {
            $parentId = $eligibleParentInfo['id'];
            $newAssetGeneration = $eligibleParentInfo['generation'] + 1;
        }
        $stmt = $pdo_mysql->prepare("INSERT INTO assets (user_id, asset_type_id, parent_id, generation, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $assetTypeId, $parentId, $newAssetGeneration, $now, $expires_at]);
        $newAssetId = $pdo_mysql->lastInsertId();
        $currentPurchaseResult['asset_id'] = $newAssetId;
        $currentPurchaseResult['message'] = "Asset #{$newAssetId} ({$assetType['name']}) created for user #{$userId}. Expires: ".($expires_at ?? 'Never').".";

        if ($parentId) {
            $pdo_mysql->prepare("UPDATE assets SET children_count = children_count + 1 WHERE id = ?")->execute([$parentId]);
            $currentPurchaseResult['parent_update'] = "Parent Asset #{$parentId} children_count incremented.";
        }
        
        // Payout logic
        $assetPrice = $assetType['price'];
        $remainingAmount = $assetPrice;

        // 0. Referral Bonus Allocation
        $buyer = getUserByIdOrName($userId);
        if ($buyer && !empty($buyer['referral'])) {
            $referrer = getUserByIdOrName($buyer['referral']);
            if ($referrer) {
                $giveBonus = false;
                // Scenario 1: Referrer is a broker
                if ($referrer['is_broker'] == 1) {
                    $giveBonus = true;
                }
                // Scenario 2: Referrer is not a broker and has not received the bonus before
                elseif ($referrer['is_broker'] == 0 && $referrer['has_received_referral_bonus'] == 0) {
                    $giveBonus = true;
                    // Mark that this non-broker has now received their one-time bonus
                    $pdo_mysql->prepare("UPDATE users SET has_received_referral_bonus = 1 WHERE id = ?")->execute([$referrer['id']]);
                }

                if ($giveBonus) {
                    $referralBonusAmount = REFERRAL_BONUS;
                    
                    // Manually credit wallet to avoid double email from generic credit function
                    $pdo_mysql->prepare("UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :id")->execute([':amount' => $referralBonusAmount, ':id' => $referrer['id']]);
                    // Also add to total_return for asset partner bonus
                    $pdo_mysql->prepare("UPDATE users SET total_return = total_return + :amount WHERE id = :id")->execute([':amount' => $referralBonusAmount, ':id' => $referrer['id']]);
                    
                    // Manually log the transaction for the referrer
                    $logStmt = $pdo_mysql->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$referrer['id'], 'asset_partner_bonus', $referralBonusAmount, "Asset partner bonus from " . $buyer['username']]);

                    // Send the specific email notification for the bonus
                    $email_data = [
                        'referrer_username' => $referrer['username'],
                        'bonus_amount'      => number_format($referralBonusAmount, 2),
                        'new_user_username' => $buyer['username']
                    ];
                    sendNotificationEmail('asset_partner_bonus_user', $email_data, $referrer['email'], 'You Received an Asset Partner Bonus!');
                    // Send push notification to referrer
                    $referrer_payload = [
                        'title' => 'Referral Bonus!',
                        'body' => 'You received a SV' . number_format($referralBonusAmount, 2) . ' bonus from ' . $buyer['username'] . '.',
                        'icon' => 'assets/images/logo.png',
                    ];
                    sendPushNotification($referrer['id'], $referrer_payload);

                    $remainingAmount -= $referralBonusAmount;
                    $currentPurchaseResult['referral_bonus_log'] = "Referrer #{$referrer['id']} ({$referrer['username']}) received ₦" . number_format($referralBonusAmount, 2) . ".";
                } else {
                    $currentPurchaseResult['referral_bonus_log'] = "Referrer #{$referrer['id']} ({$referrer['username']}) is not eligible for a bonus on this purchase.";
                }
            }
        }

        // 1. Company Profit Allocation
        $companyProfitAmount = COMPANY_PROFIT_ALLOCATION;
        $pdo_mysql->prepare("UPDATE company_funds SET total_company_profit = total_company_profit + ? WHERE id = 1")->execute([$companyProfitAmount]);
        $remainingAmount -= $companyProfitAmount;
        $currentPurchaseResult['company_profit_log'] = "Company profit increased by ₦" . number_format($companyProfitAmount, 2) . ".";

        // 2. Generational Pot Allocation
        $generationalPotAmount = GENERATIONAL_POT_ALLOCATION;
        $pdo_mysql->prepare("UPDATE company_funds SET total_generational_pot = total_generational_pot + ? WHERE id = 1")->execute([$generationalPotAmount]);
        $remainingAmount -= $generationalPotAmount;
        $currentPurchaseResult['generational_pot_log'] = "Generational pot increased by ₦" . number_format($generationalPotAmount, 2) . ".";

        // 3. Shared Pot Allocation
        $sharedPotAmount = SHARED_POT_ALLOCATION;
        $pdo_mysql->prepare("UPDATE company_funds SET total_shared_pot = total_shared_pot + ? WHERE id = 1")->execute([$sharedPotAmount]);
        $remainingAmount -= $sharedPotAmount;
        $currentPurchaseResult['shared_pot_log'] = "Shared pot increased by ₦" . number_format($sharedPotAmount, 2) . ".";

        // 4. Reservation Fund Allocation (remaining amount after fixed allocations)
        $reservationFundAmount = $remainingAmount; // This should be the remainder after fixed allocations
        $pdo_mysql->prepare("UPDATE company_funds SET total_reservation_fund = total_reservation_fund + ? WHERE id = 1")->execute([$reservationFundAmount]);
        $currentPurchaseResult['reservation_fund_log'] = "Reservation fund increased by ₦" . number_format($reservationFundAmount, 2) . ".";

        // Generational Payouts
        $ancestors = getAncestorsForPayout($newAssetId);
        foreach ($ancestors as $depth => $ancestor) {
            if ($depth >= MAX_GENERATIONS_PAYOUT_DEPTH) break;

            // Check if ancestor is active and not completed/expired
            if ($ancestor['is_completed'] == 0 || $ancestor['is_currently_expired'] == false) {
                $payoutAmount = PAYOUT_PER_GENERATION_EVENT_FROM_POT;

                // Check payout cap
                $potentialTotalEarned = $ancestor['total_generational_received'] + $payoutAmount;
                if ($ancestor['payout_cap'] > 0 && $potentialTotalEarned > $ancestor['payout_cap']) {
                    $payoutAmount = $ancestor['payout_cap'] - $ancestor['total_generational_received'];
                    if ($payoutAmount < 0) $payoutAmount = 0; // Ensure payout is not negative
                }

                if ($payoutAmount > 0) {
                    // Deduct from generational pot
                    $pdo_mysql->prepare("UPDATE company_funds SET total_generational_pot = total_generational_pot - ? WHERE id = 1")->execute([$payoutAmount]);
                    // Credit ancestor asset
                    $pdo_mysql->prepare("UPDATE assets SET total_generational_received = total_generational_received + ? WHERE id = ?")->execute([$payoutAmount, $ancestor['id']]);

                    // Schedule fractional payouts
                    $fractionalAmount = $payoutAmount / 5;
                    for ($i = 0; $i < 5; $i++) {
                        $creditAt = date('Y-m-d H:i:s', time() + mt_rand(1, 72 * 3600));
                        $pdo_mysql->prepare("INSERT INTO pending_profits (user_id, receiving_asset_id, fractional_amount, payout_type, credit_at) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$ancestor['user_id'], $ancestor['id'], $fractionalAmount, 'generational', $creditAt]);
                    }

                    // Log payout
                    $pdo_mysql->prepare("INSERT INTO payouts (receiving_asset_id, triggering_asset_id, amount, payout_type, created_at) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$ancestor['id'], $newAssetId, $payoutAmount, 'generational', $now]);
                    $currentPurchaseResult['generational_payouts_log'][] = "Asset #{$ancestor['id']} received ₦" . number_format($payoutAmount, 2) . " (Gen. " . ($depth + 1) . ").";

                    // Mark asset as completed if cap reached
                    $updatedTotalGenerationalReceivedStmt = $pdo_mysql->prepare("SELECT total_generational_received FROM assets WHERE id = ?");
                    $updatedTotalGenerationalReceivedStmt->execute([$ancestor['id']]);
                    $updatedTotalGenerationalReceived = $updatedTotalGenerationalReceivedStmt->fetchColumn();

                    if ($ancestor['payout_cap'] > 0 && $updatedTotalGenerationalReceived >= $ancestor['payout_cap']) {
                        $pdo_mysql->prepare("UPDATE assets SET is_completed = 1 WHERE id = ?")->execute([$ancestor['id']]);
                        $currentPurchaseResult['generational_payouts_log'][] = "Asset #{$ancestor['id']} marked completed (cap reached).";
                    }
                }
            }
        }

        // Shared Payouts: Distribute SHARED_POT_ALLOCATION among all active assets
        $activeAssetsStmt = $pdo_mysql->prepare("SELECT a.id, a.user_id, a.is_completed, a.is_manually_expired, at.name as asset_type_name FROM assets a JOIN asset_types at ON a.asset_type_id = at.id WHERE a.is_completed = 0 AND a.is_manually_expired = 0 AND (a.expires_at IS NULL OR a.expires_at > :now)");
        $activeAssetsStmt->execute([':now' => $now]);
        $activeAssets = $activeAssetsStmt->fetchAll(PDO::FETCH_ASSOC);

        $numActiveAssets = count($activeAssets);
        if ($numActiveAssets > 0) {
            $fractionalSharedPayout = SHARED_POT_ALLOCATION / $numActiveAssets;
            foreach ($activeAssets as $activeAsset) {
            if($activeAsset['is_completed'] == 0 || $activeAsset['is_manually_expired'] == 0){
               // Credit asset's total_shared_received
                $pdo_mysql->prepare("UPDATE assets SET total_shared_received = total_shared_received + ? WHERE id = ?")->execute([$fractionalSharedPayout, $activeAsset['id']]);
                // Schedule fractional payouts
                $fractionalAmount = $fractionalSharedPayout / 4;
                for ($i = 0; $i < 4; $i++) {
                    $creditAt = date('Y-m-d H:i:s', time() + mt_rand(1, 72 * 3600));
                    $pdo_mysql->prepare("INSERT INTO pending_profits (user_id, receiving_asset_id, fractional_amount, payout_type, credit_at) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$activeAsset['user_id'], $activeAsset['id'], $fractionalAmount, 'shared', $creditAt]);
                        }
                }

                $currentPurchaseResult['shared_payouts_log'][] = "Asset #{$activeAsset['id']} received ₦" . number_format($fractionalSharedPayout, 2) . " (Shared).";
            }
        } else {
            $currentPurchaseResult['shared_payouts_log'][] = "No active assets to distribute shared payout.";
        }

        $overallResults['purchases'][] = $currentPurchaseResult;
    }

    $overallResults['summary'][] = "Successfully purchased {$numAssetsToBuy} of '{$assetType['name']}'. Total Cost: SV" . number_format($totalCost, 2) . ".";
    
    // Send email notification for asset purchase to admin
    $user = getUserByIdOrName($userId);
    if ($user) {
        $admin_data = [
            'username' => $user['username'],
            'asset_name' => $assetType['name'],
            'price' => number_format($totalCost, 2)
        ];
        sendNotificationEmail('asset_purchase_admin', $admin_data, 'penniepoint@gmail.com', 'New Asset Purchase');

        // Send comprehensive email to user
        $user_email_data = [
            'username' => $user['username'],
            'asset_name' => $assetType['name'],
            'quantity' => $numAssetsToBuy,
            'price_per_unit' => number_format($assetType['price'], 2),
            'total_cost' => number_format($totalCost, 2),
            'payout_cap' => number_format($assetType['payout_cap'], 2),
            'duration' => ($assetType['duration_months'] > 0 ? $assetType['duration_months'] . ' months' : 'Unlimited'),
            'asset_image_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/" . str_replace('../', '', $assetType['image_link']) // Construct full URL
        ];
        sendNotificationEmail('asset_purchase_user', $user_email_data, $user['email'], 'Your Asset Purchase Confirmation');

        // Send push notification for asset purchase
        $buyer_payload = [
            'title' => 'Asset Purchased!',
            'body' => 'You have successfully purchased ' . $numAssetsToBuy . ' of ' . $assetType['name'] . '. Total cost: SV' . number_format($totalCost, 2) . '.',
            'icon' => 'assets/images/logo.png',
        ];
        sendPushNotification($userId, $buyer_payload);
    }

    return $overallResults;
}


// --- Functions for User Dashboard ---
function getUserAssets($userId) {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo_mysql->prepare("
        SELECT a.*, at.name as asset_type_name, at.price as asset_price, at.payout_cap as type_payout_cap, at.image_link,
               (a.total_generational_received + a.total_shared_received) as total_earned,
               CASE 
                   WHEN a.is_sold = 1 THEN 'Sold'
                   WHEN a.is_completed = 1 THEN 'Completed'
                   WHEN a.is_manually_expired = 1 THEN 'Expired'
                   WHEN (a.expires_at IS NOT NULL AND a.expires_at < :now) THEN 'Expired'
                   ELSE 'Active'
               END as current_status
        FROM assets a
        JOIN asset_types at ON a.asset_type_id = at.id
        WHERE a.user_id = :user_id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId, ':now' => $now]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserAssetsWorth($userId) {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo_mysql->prepare("
        SELECT SUM(at.payout_cap) 
        FROM assets a
        JOIN asset_types at ON a.asset_type_id = at.id
        WHERE a.user_id = :user_id
        AND a.is_completed = 0
        AND a.is_manually_expired = 0
        AND (a.expires_at IS NULL OR a.expires_at > :now)
    ");
    $stmt->execute([':user_id' => $userId, ':now' => $now]);
    return $stmt->fetchColumn() ?? 0;
}

function getGroupedUserAssets($userId) {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo_mysql->prepare("
        SELECT 
            at.id as asset_type_id,
            at.name as asset_type_name,
            at.image_link,
            at.price as original_price,
            at.payout_cap as type_payout_cap,
            at.dividing_price,
            COUNT(a.id) as total_assets_count,
            SUM(CASE WHEN a.is_completed = 1 THEN 1 ELSE 0 END) as completed_assets_count,
            SUM(CASE WHEN a.is_sold = 1 THEN 1 ELSE 0 END) as sold_assets_count,
            SUM(a.total_generational_received) as total_generational_received_grouped,
            SUM(a.total_shared_received) as total_shared_received_grouped,
            SUM(a.total_generational_received + a.total_shared_received) as total_earned_grouped
        FROM assets a
        JOIN asset_types at ON a.asset_type_id = at.id
        WHERE a.user_id = :user_id
        GROUP BY at.id, at.name, at.image_link, at.price, at.payout_cap, at.dividing_price
        ORDER BY at.name ASC
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPayouts($userId) {
    global $pdo_mysql;
    
    // Step 1: Get payouts from MySQL (was SQLite)
    $stmt = $pdo_mysql->prepare("
        SELECT p.*, ta.user_id as triggering_user_id, ta.id as triggering_asset_display_id
        FROM payouts p
        JOIN assets ra ON p.receiving_asset_id = ra.id
        JOIN assets ta ON p.triggering_asset_id = ta.id
        WHERE ra.user_id = :user_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($payouts)) {
        return [];
    }

    // Step 2: Get the usernames from MySQL (already was MySQL)
    $triggeringUserIds = array_unique(array_column($payouts, 'triggering_user_id'));
    if (!empty($triggeringUserIds)) {
        $placeholders = implode(',', array_fill(0, count($triggeringUserIds), '?'));
        $userStmt = $pdo_mysql->prepare("SELECT id, username FROM users WHERE id IN ($placeholders)");
        $userStmt->execute($triggeringUserIds);
        $users = $userStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Step 3: Manually merge usernames into the results
        foreach ($payouts as &$payout) {
            $payout['triggering_username'] = $users[$payout['triggering_user_id']] ?? 'Unknown';
        }
    }

    return $payouts;
}

// --- Functions for Chart Data ---
function getOverallIncomeStats() {
    global $pdo_mysql;
    $companyFunds = getCompanyFunds();
    $payoutsTotals = $pdo_mysql->query("
        SELECT 
            SUM(CASE WHEN payout_type = 'generational' THEN amount ELSE 0 END) as total_generational,
            SUM(CASE WHEN payout_type = 'shared' THEN amount ELSE 0 END) as total_shared
        FROM payouts
    ")->fetch(PDO::FETCH_ASSOC);

    return [
        'company_profit' => $companyFunds['total_company_profit'],
        'reservation_fund' => $companyFunds['total_reservation_fund'],
        'total_generational_paid' => $payoutsTotals['total_generational'] ?? 0,
        'total_shared_paid' => $payoutsTotals['total_shared'] ?? 0
    ];
}

function getAssetStatusDistribution() {
    global $pdo_mysql;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo_mysql->prepare("
        SELECT 
            SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold_count,
            SUM(CASE WHEN is_completed = 1 AND is_sold = 0 THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN is_completed = 0 AND is_sold = 0 AND (is_manually_expired = 1 OR (expires_at IS NOT NULL AND expires_at < :now)) THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN is_completed = 0 AND is_sold = 0 AND is_manually_expired = 0 AND (expires_at IS NULL OR expires_at >= :now) THEN 1 ELSE 0 END) as active_count
        FROM assets
    ");
    $stmt->execute([':now' => $now]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAssetBranding($asset_type_id) {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("SELECT name, image_link FROM asset_types WHERE id = ?");
    $stmt->execute([$asset_type_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'name' => $asset['name'] ?? 'Unknown Asset',
        'image' => $asset['image_link'] ?? null
    ];
}

function getPendingExpiredSales() {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare(
        "SELECT a.id as asset_id, a.user_id, a.created_at as asset_created_at,
                at.name as asset_type_name, at.price as original_price,
                u.username, u.email
         FROM assets a
         JOIN asset_types at ON a.asset_type_id = at.id
         JOIN users u ON a.user_id = u.id
         WHERE a.sale_status = 'pending'
         ORDER BY a.created_at ASC"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sellCompletedAssets($userId, $assetTypeId, $quantity, $pin) {
    global $pdo_mysql;

    if (!isMarketOpen()) {
        return ['success' => false, 'message' => 'Market is closed. You can only sell assets when the market is open.'];
    }

    if (!verifyTransactionPin($userId, $pin)) {
        return ['success' => false, 'message' => 'Invalid transaction PIN.'];
    }


    try {
        $pdo_mysql->beginTransaction();

        // Check for available completed assets
        $stmt = $pdo_mysql->prepare("SELECT id FROM assets WHERE user_id = ? AND asset_type_id = ? AND is_completed = 1 AND is_sold = 0");
        $stmt->execute([$userId, $assetTypeId]);
        $completedAssets = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($completedAssets) < $quantity) {
            $pdo_mysql->rollBack();
            return ['success' => false, 'message' => 'Insufficient completed assets to sell.'];
        }

        // Get asset type details
        $stmt = $pdo_mysql->prepare("SELECT price FROM asset_types WHERE id = ?");
        $stmt->execute([$assetTypeId]);
        $assetPrice = $stmt->fetchColumn();

        if (!$assetPrice) {
            $pdo_mysql->rollBack();
            return ['success' => false, 'message' => 'Invalid asset type.'];
        }

        $sellingFee = 0.1799;
        $amountPerAsset = $assetPrice * (1 - $sellingFee);
        $totalAmount = $amountPerAsset * $quantity;

        // Mark assets as sold
        $assetsToSell = array_slice($completedAssets, 0, $quantity);
        $placeholders = rtrim(str_repeat('?,', count($assetsToSell)), ',');
        $stmt = $pdo_mysql->prepare("UPDATE assets SET is_sold = 1, sold_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($assetsToSell);

        // Credit user wallet
        $assetName = getAssetBranding($assetTypeId)['name'];
        $description = "Sold {$quantity} x " . $assetName;
        creditUserWallet($userId, $totalAmount, $description);

        $pdo_mysql->commit();

        // Send notifications
        // $user = getUserByIdOrName($userId);
        // sendNotificationEmail(...) - Implement this later
        // sendPushNotification(...) - Implement this later

        return ['success' => true, 'message' => "Successfully sold {$quantity} assets."];

    } catch (Exception $e) {
        $pdo_mysql->rollBack();
        return ['success' => false, 'message' => 'An error occurred during the selling process.'];
    }
}

function sellAllExpiredAssetsOfType($userId, $assetTypeId, $pin) {
    global $pdo_mysql;

    if (!isMarketOpen()) {
        return ['success' => false, 'message' => 'Market is closed. You can only sell assets when the market is open.'];
    }

    // 1. Verify transaction PIN
    if (!verifyTransactionPin($userId, $pin)) {
        return ['success' => false, 'message' => 'Invalid transaction PIN.'];
    }

    try {
        $pdo_mysql->beginTransaction();

        // 2. Find all expired, unsold assets of the specified type for the user
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo_mysql->prepare(
            "SELECT a.id, at.price as original_price, at.name as asset_name
             FROM assets a
             JOIN asset_types at ON a.asset_type_id = at.id
             WHERE a.user_id = :user_id 
               AND a.asset_type_id = :asset_type_id
               AND (a.is_manually_expired = 1 OR (a.expires_at IS NOT NULL AND a.expires_at < :now))
               AND a.sale_status IS NULL
               AND a.is_completed = 0" // Ensure not completed
        );
        $stmt->execute([':user_id' => $userId, ':asset_type_id' => $assetTypeId, ':now' => $now]);
        $assetsToMarkPending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assetsToMarkPending)) {
            $pdo_mysql->rollBack();
            return ['success' => false, 'message' => 'No eligible expired assets found to mark for sale.'];
        }

        $assetIds = array_column($assetsToMarkPending, 'id');
        $assetName = $assetsToMarkPending[0]['asset_name']; // Assuming all assets are of the same type

        // 3. Mark assets with 'pending' sale_status
        if (!empty($assetIds)) {
            $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
            $updateStmt = $pdo_mysql->prepare("UPDATE assets SET sale_status = 'pending' WHERE id IN ({$placeholders})");
            $updateStmt->execute($assetIds);
        }

        $pdo_mysql->commit();

        // Send initial email to user
        $user = getUserByIdOrName($userId);
        if ($user) {
            $email_data = [
                'username' => $user['username'],
                'asset_name' => htmlspecialchars($assetName),
                'quantity' => count($assetsToMarkPending)
            ];
            // I will create this template later
            sendNotificationEmail('expired_asset_sale_pending_user', $email_data, $user['email'], 'Your Expired Asset Sale is Pending');
            // Send push notification
            $payload = [
                'title' => 'Expired Asset Sale Pending',
                'body' => 'Your request to sell ' . count($assetsToMarkPending) . ' of ' . htmlspecialchars($assetName) . ' is pending admin approval. You will be credited within 5 working days.',
                'icon' => 'assets/images/logo.png',
            ];
            sendPushNotification($userId, $payload);
        }


        return ['success' => true, 'message' => count($assetsToMarkPending) . " expired asset(s) marked for pending sale. Awaiting admin approval."];

    } catch (Exception $e) {
        if ($pdo_mysql->inTransaction()) {
            $pdo_mysql->rollBack();
        }
        error_log("Error marking expired assets for pending sale: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during the process. Please try again.'];
    }
}


/**
 * Generates 3 months of mock OHLC data for a given asset type.
 *
 * @param int $assetTypeId The ID of the asset type.
 * @param float $initialDividingPrice The initial dividing price to base the mock data on.
 */
function generateInitialAssetTypeStats($assetTypeId, $initialDividingPrice) {
    global $pdo_mysql;
    $now = new DateTime();
    $interval = new DateInterval('P3M'); // 3 months
    $startDate = (clone $now)->sub($interval);

    $currentPrice = $initialDividingPrice;

    // Generate daily data for 3 months
    for ($i = 0; $i < 90; $i++) { // Approximately 90 days for 3 months
        $date = (clone $startDate)->add(new DateInterval("P{$i}D"));
        $timestamp = $date->getTimestamp() * 1000; // Highcharts expects milliseconds

        // Simulate price fluctuation around the current price
        $open = $currentPrice;
        $close = $currentPrice + (mt_rand(-20, 20) / 100) * $currentPrice; // +/- 20% of current price
        $high = max($open, $close) + (mt_rand(0, 10) / 100) * $currentPrice;
        $low = min($open, $close) - (mt_rand(0, 10) / 100) * $currentPrice;

        // Ensure prices stay within bounds (30-200)
        $open = max(30.0, min(200.0, $open));
        $close = max(30.0, min(200.0, $close));
        $high = max(30.0, min(200.0, $high));
        $low = max(30.0, min(200.0, $low));

        // Ensure consistency
        $high = max($open, $close, $high);
        $low = min($open, $close, $low);

        $volume = mt_rand(1000, 100000); // Random volume

        $stmt = $pdo_mysql->prepare("INSERT INTO asset_type_stats (asset_type_id, timestamp, open_price, high_price, low_price, close_price, volume) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$assetTypeId, $timestamp, $open, $high, $low, $close, $volume]);

        $currentPrice = $close; // Next day's price starts near this close
    }
}

/**
 * Simulates a minute-by-minute price change for the dividing price.
 *
 * @param float $currentDividingPrice The current dividing price.
 * @return float The new simulated dividing price.
 */
function simulateMinutePriceChange($currentDividingPrice) {
    $changePercentage = mt_rand(5, 40) / 1000; // 0.5% to 4% change
    $changeDirection = (mt_rand(0, 1) == 1) ? 1 : -1; // +1 or -1
    $changeAmount = $currentDividingPrice * $changePercentage * $changeDirection;

    $newDividingPrice = $currentDividingPrice + $changeAmount;

    // Ensure price stays within bounds (30-200)
    $newDividingPrice = max(30.0, min(200.0, $newDividingPrice));

    return round($newDividingPrice, 2); // Round to 2 decimal places
}

/**
 * Fetches historical OHLC data for a given asset type and range.
 *
 * @param int $assetTypeId The ID of the asset type.
 * @param string $range The desired data range (e.g., '1D', '1W', '1M', '3M', '1Y').
 * @return array An array of OHLC data in Highcharts format [[timestamp, open, high, low, close]].
 */
function getAssetTypeStats($assetTypeId, $range) {
    global $pdo_mysql;
    $now = new DateTime();
    $startDate = null;
    $interval = 'minute'; // Default to minute for 1D

    switch ($range) {
        case '1D':
            $startDate = (clone $now)->sub(new DateInterval('PT24H')); // Last 24 hours
            break;
        case '1W':
            $startDate = (clone $now)->sub(new DateInterval('P7D'));
            $interval = 'day'; // Aggregate to daily for longer ranges
            break;
        case '1M':
            $startDate = (clone $now)->sub(new DateInterval('P1M'));
            $interval = 'day';
            break;
        case '3M':
            $startDate = (clone $now)->sub(new DateInterval('P3M'));
            $interval = 'day';
            break;
        case '1Y':
            $startDate = (clone $now)->sub(new DateInterval('P1Y'));
            $interval = 'day';
            break;
        default:
            $startDate = (clone $now)->sub(new DateInterval('P3M')); // Default to 3 months
            $interval = 'day';
            break;
    }

    $startTimestamp = $startDate->getTimestamp() * 1000;

    $data = [];
    if ($interval === 'minute') {
        $stmt = $pdo_mysql->prepare("SELECT timestamp, open_price, high_price, low_price, close_price FROM asset_type_stats WHERE asset_type_id = ? AND timestamp >= ? ORDER BY timestamp ASC");
        $stmt->execute([$assetTypeId, $startTimestamp]);
        $data = $stmt->fetchAll(PDO::FETCH_NUM);
    } else {
        // Aggregate to daily OHLC for longer ranges
        $stmt = $pdo_mysql->prepare("
            SELECT
                UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(timestamp / 1000))) * 1000 as day_timestamp,
                AVG(open_price) as open_p,
                MAX(high_price) as high_p,
                MIN(low_price) as low_p,
                AVG(close_price) as close_p
            FROM asset_type_stats
            WHERE asset_type_id = ? AND timestamp >= ?
            GROUP BY day_timestamp
            ORDER BY day_timestamp ASC
        ");
        $stmt->execute([$assetTypeId, $startTimestamp]);
        $rawDailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawDailyData as $row) {
            $data[] = [
                (int)$row['day_timestamp'],
                (float)$row['open_p'],
                (float)$row['high_p'],
                (float)$row['low_p'],
                (float)$row['close_p']
            ];
        }
    }

    return $data;
}

function addAssetType($name, $price, $payoutCap, $durationMonths, $imageLink = null, $category = null) {
    global $pdo_mysql;
    try {
        $reservationContribution = $price - FIXED_ALLOCATIONS_TOTAL;
        $dividingPrice = 55.00; // Set initial dividing price as per requirement

        $stmt = $pdo_mysql->prepare("INSERT INTO asset_types (name, price, payout_cap, duration_months, reservation_fund_contribution, image_link, category, dividing_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $payoutCap, $durationMonths, $reservationContribution, $imageLink, $category, $dividingPrice]);
        $assetTypeId = $pdo_mysql->lastInsertId();

        // Generate initial historical data for the new asset type
        generateInitialAssetTypeStats($assetTypeId, $dividingPrice);

        return $assetTypeId;
    } catch (PDOException $e) {
        error_log("Error adding asset type: " . $e->getMessage());
        return false;
    }
}

function updateAssetType($assetTypeId, $name, $price, $payoutCap, $durationMonths, $imageLink = null, $category = null, $dividingPrice = null) {
    global $pdo_mysql;
    try {
        $reservationContribution = $price - FIXED_ALLOCATIONS_TOTAL;
        $sql = "UPDATE asset_types SET name = ?, price = ?, payout_cap = ?, duration_months = ?, reservation_fund_contribution = ?, category = ?, dividing_price = ?";
        $params = [$name, $price, $payoutCap, $durationMonths, $reservationContribution, $category, $dividingPrice];

        if ($imageLink) {
            $sql .= ", image_link = ?";
            $params[] = $imageLink;
        }

        $sql .= " WHERE id = ?";
        $params[] = $assetTypeId;

        $stmt = $pdo_mysql->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating asset type: " . $e->getMessage());
        return false;
    }
}

function markAssetExpired($assetId) {
    global $pdo_mysql;
    try {
        $stmt = $pdo_mysql->prepare("UPDATE assets SET is_manually_expired = 1 WHERE id = ?");
        $stmt->execute([$assetId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking asset as expired: " . $e->getMessage());
        return false;
    }
}

function markAssetCompleted($assetId) {
    global $pdo_mysql;
    try {
        $stmt = $pdo_mysql->prepare("UPDATE assets SET is_completed = 1, completed_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$assetId]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Fetch asset and user details for email
            $assetStmt = $pdo_mysql->prepare("SELECT a.user_id, at.name as asset_name FROM assets a JOIN asset_types at ON a.asset_type_id = at.id WHERE a.id = ?");
            $assetStmt->execute([$assetId]);
            $asset = $assetStmt->fetch(PDO::FETCH_ASSOC);

            if ($asset) {
                $user = getUserByIdOrName($asset['user_id']);
                if ($user) {
                    /*
                    $emailBody = getAssetStatusEmailTemplate(
                        $user['username'],
                        $asset['asset_name'],
                        'Completed',
                        "Congratulations! Your asset '{$asset['asset_name']}' has reached its payout cap and is now completed."
                        . "\nAsset ID: #{$assetId}"
                    );
                    sendEmail($user['email'], $user['username'], "Asset Completed - Pennieshares", $emailBody);
                    */
                }
            }
        }
        return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking asset as completed: " . $e->getMessage());
        return false;
    }
}

function sellCompletedAsset($userId, $assetId, $pin) {
    global $pdo_mysql; 

    if (!isMarketOpen()) {
        return ['success' => false, 'message' => 'Market is closed. You can only sell assets when the market is open.'];
    }

    // 1. Verify transaction PIN
    if (!verifyTransactionPin($userId, $pin)) {
        return ['success' => false, 'message' => 'Invalid transaction PIN.'];
    }

    try {
        // 2. Verify asset ownership and status
        $stmt = $pdo_mysql->prepare(
            "SELECT a.id, a.user_id, a.is_completed, a.is_sold, at.price as original_price, at.name as asset_name"
            . " FROM assets a"
            . " JOIN asset_types at ON a.asset_type_id = at.id"
            . " WHERE a.id = ? AND a.user_id = ?"
        );
        $stmt->execute([$assetId, $userId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) {
            return ['success' => false, 'message' => 'Asset not found or does not belong to you.'];
        }
        if ($asset['is_completed'] == 0) {
            return ['success' => false, 'message' => 'Asset is not completed yet.'];
        }
        if ($asset['is_sold'] == 1) {
            return ['success' => false, 'message' => 'Asset has already been sold.'];
        }

        // 3. Calculate sale price (70% of original)
        $salePrice = $asset['original_price'] * 0.70;

        // 4. Credit user's wallet
        $creditResult = creditUserWallet($userId, $salePrice, "Sale of completed asset: {$asset['asset_name']}");

        if (!$creditResult) {
            return ['success' => false, 'message' => 'Failed to credit wallet.'];
        }

        // 5. Mark asset as sold
        $updateStmt = $pdo_mysql->prepare("UPDATE assets SET is_sold = 1, sold_at = NOW() WHERE id = ?");
        $updateStmt->execute([$assetId]);

        // Optionally, you might want to delete the asset instead of just marking it as sold
        // $deleteStmt = $pdo_mysql->prepare("DELETE FROM assets WHERE id = ?");
        // $deleteStmt->execute([$assetId]);

        return ['success' => true, 'message' => "Asset '{$asset['asset_name']}' sold successfully for SV" . number_format($salePrice, 2) . "."];

    } catch (PDOException $e) {
        error_log("Error selling completed asset: " . $e->getMessage());
        return ['success' => false, 'message' => 'A database error occurred during asset sale.'];
    }
}





function deleteAssetType($assetTypeId) {
    global $pdo_mysql;
    try {
        // Temporarily disable foreign key checks
        $pdo_mysql->exec('SET FOREIGN_KEY_CHECKS = 0;');

        // Start a transaction
        $pdo_mysql->beginTransaction();

        // Delete all assets associated with this asset type
        // $pdo_mysql->prepare("DELETE FROM assets WHERE asset_type_id = ?")->execute([$assetTypeId]);

        // Now delete the asset type itself
        $stmt = $pdo_mysql->prepare("DELETE FROM asset_types WHERE id = ?");
        $stmt->execute([$assetTypeId]);

        // Commit the transaction
        $pdo_mysql->commit();

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo_mysql->rollBack();
        error_log("Error deleting asset type: " . $e->getMessage());        echo "Error deleting asset type: " . $e->getMessage() . "\n";
        return false;
    } finally {
        // Re-enable foreign key checks, regardless of success or failure
        $pdo_mysql->exec('SET FOREIGN_KEY_CHECKS = 1;');
    }
}

function getPaginatedAssets($limit, $offset, $searchQuery = '') {
    global $pdo_mysql;

    $userIds = [];
    if (!empty($searchQuery)) {
        $stmt = $pdo_mysql->prepare("SELECT id FROM users WHERE username LIKE ? OR email LIKE ? OR partner_code LIKE ?");
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($userIds)) {
            return []; // No users found, so no assets to return
        }
    }

    $sql = "SELECT a.*, at.name as asset_type_name, at.payout_cap as type_payout_cap, at.price as asset_price,
            (a.total_generational_received + a.total_shared_received) as total_earned
            FROM assets a 
            JOIN asset_types at ON a.asset_type_id = at.id";
    
    $params = [];
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql .= " WHERE a.user_id IN ($placeholders)";
        $params = $userIds;
    }

    $sql .= " ORDER BY a.id ASC LIMIT ? OFFSET ?";

    $stmt = $pdo_mysql->prepare($sql);

    // Bind parameters
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Manually add username to each asset
    if (!empty($assets)) {
        $allUserIds = array_column($assets, 'user_id');
        $userPlaceholders = implode(',', array_fill(0, count($allUserIds), '?'));
        $userStmt = $pdo_mysql->prepare("SELECT id, username FROM users WHERE id IN ($userPlaceholders)");
        $userStmt->execute($allUserIds);
        $users = $userStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($assets as &$asset) {
            $asset['username'] = $users[$asset['user_id']] ?? 'Unknown';
        }
    }

    return $assets;
}

function getTotalAssetCount($searchQuery = '') {
    global $pdo_mysql;

    if (empty($searchQuery)) {
        $stmt = $pdo_mysql->query("SELECT COUNT(*) FROM assets");
        return $stmt->fetchColumn();
    }

    // If there is a search query, find matching users first
    $stmt = $pdo_mysql->prepare("SELECT id FROM users WHERE username LIKE ? OR email LIKE ? OR partner_code LIKE ?");
    $searchTerm = '%' . $searchQuery . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        return 0; // No users found, so asset count is 0
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT COUNT(*) FROM assets WHERE user_id IN ($placeholders)";
    
    $stmt = $pdo_mysql->prepare($sql);
    $stmt->execute($userIds);
    return $stmt->fetchColumn();
}

function generateStatsForExistingAssets() {
    global $pdo_mysql;
    $assetTypes = getAssetTypes();
    $generatedCount = 0;
    foreach ($assetTypes as $assetType) {
        // Check if stats already exist
        $stmt = $pdo_mysql->prepare("SELECT COUNT(*) FROM asset_type_stats WHERE asset_type_id = ?");
        $stmt->execute([$assetType['id']]);
        if ($stmt->fetchColumn() == 0) {
            // No stats, so generate them
            $initialDividingPrice = mt_rand(4000, 10000) / 100; // Random float between 40.00 and 100.00
            
            // Update the asset_type with this new dividing price
            $updateStmt = $pdo_mysql->prepare("UPDATE asset_types SET dividing_price = ? WHERE id = ?");
            $updateStmt->execute([$initialDividingPrice, $assetType['id']]);

            // Generate the historical data
            generateInitialAssetTypeStats($assetType['id'], $initialDividingPrice);
            $generatedCount++;
        }
    }
    return $generatedCount;
}

