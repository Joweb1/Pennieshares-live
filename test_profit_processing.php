<?php
// WARNING: This script is for testing purposes only and will perform destructive operations on your database.
// It will clear the users, assets, asset_types, pending_profits, wallet_transactions, and company_funds tables.
// DO NOT run this on a production database.

require_once __DIR__ . '/src/init.php';

// Activate test mode to prevent sending emails
$GLOBALS['is_test_mode'] = true;

echo "<pre>";
echo "=========================================\n";
echo "PENNYSHARES PROFIT PROCESSING TEST SCRIPT\n";
echo "=========================================\n\n";

function run_test() {
    global $pdo_mysql;

    try {
        // ------------------------------------------------------------------
        // 1. SETUP: Clear database and initialize company funds
        // ------------------------------------------------------------------
        echo "--> STEP 1: Clearing test data from database...\n";
        $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables_to_clear = ['users', 'asset_types', 'assets', 'pending_profits', 'wallet_transactions', 'payouts', 'company_funds'];
        foreach ($tables_to_clear as $table) {
            $pdo_mysql->exec("TRUNCATE TABLE {$table};");
        }
        $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Initialize company funds
        $pdo_mysql->exec("INSERT INTO company_funds (id, total_company_profit, total_generational_pot, total_shared_pot, total_reservation_fund) VALUES (1, 0, 0, 0, 0)");
        echo "Database cleared and company funds initialized.\n\n";

        // ------------------------------------------------------------------
        // 2. SETUP: Create Asset Types and Users
        // ------------------------------------------------------------------
        echo "--> STEP 2: Creating test asset types and users...\n";
        
        // Create an asset type
        $asset_type_id = addAssetType('Test Stock', 100, 500, 12);
        if (empty($asset_type_id)) {
            throw new Exception("Failed to create asset type.");
        }
        echo "Created asset type 'Test Stock' with ID: {$asset_type_id}\n";

        // Create User A (will receive profits)
        $user_a_id = registerUser('User A', 'usera@test.com', 'usera', '123456', '', 'password');
        if (empty($user_a_id)) {
            throw new Exception("Failed to create User A.");
        }
        verifyUserEmail($user_a_id);
        updateUserProfile($user_a_id, 'User A', '123456'); // Ensure profile is complete
        $pdo_mysql->prepare("UPDATE users SET status = 2 WHERE id = ?")->execute([$user_a_id]); // Manually verify account
        echo "Created User A with ID: {$user_a_id}\n";


        // Create User B (will buy an asset and trigger profits)
        $user_b_id = registerUser('User B', 'userb@test.com', 'userb', '789012', '', 'password');
        if (empty($user_b_id)) {
            throw new Exception("Failed to create User B.");
        }
        verifyUserEmail($user_b_id);
        updateUserProfile($user_b_id, 'User B', '789012');
         $pdo_mysql->prepare("UPDATE users SET status = 2 WHERE id = ?")->execute([$user_b_id]);
        echo "Created User B with ID: {$user_b_id}\n\n";

        // ------------------------------------------------------------------
        // 3. SETUP: Create initial asset for User A
        // ------------------------------------------------------------------
        echo "--> STEP 3: Creating an initial asset for User A...\n";
        // Give User A an initial asset so they can be a parent
        $now = date('Y-m-d H:i:s');
        $pdo_mysql->prepare("INSERT INTO assets (user_id, asset_type_id, parent_id, generation, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$user_a_id, $asset_type_id, null, 1, $now]);
        $user_a_asset_id = $pdo_mysql->lastInsertId();
        echo "Created asset #{$user_a_asset_id} for User A.\n\n";

        // ------------------------------------------------------------------
        // 4. ACTION: User B buys an asset
        // ------------------------------------------------------------------
        echo "--> STEP 4: Simulating purchase by User B to generate pending profits...\n";

        // Give User B enough money to buy the asset
        $pdo_mysql->prepare("UPDATE users SET wallet_balance = 200 WHERE id = ?")->execute([$user_b_id]);
        
        // User B buys an asset. This should make User A's asset the parent.
        buyAsset($user_b_id, $asset_type_id, 1);
        
        $pending_profits_count = $pdo_mysql->query("SELECT COUNT(*) FROM pending_profits")->fetchColumn();
        if ($pending_profits_count > 0) {
            echo "SUCCESS: {$pending_profits_count} pending profit records were created.\n\n";
        } else {
            throw new Exception("FAILURE: No pending profit records were created after asset purchase.");
        }

        // ------------------------------------------------------------------
        // 5. SETUP: Make profits due immediately
        // ------------------------------------------------------------------
        echo "--> STEP 5: Setting pending profits to be due now...\n";
        $past_date = date('Y-m-d H:i:s', time() - 100);
        $update_count = $pdo_mysql->prepare("UPDATE pending_profits SET credit_at = ? WHERE is_credited = 0")
            ->execute([$past_date]);
        echo "Updated credit_at for {$pending_profits_count} records.\n\n";

        // ------------------------------------------------------------------
        // 6. VERIFICATION (PRE-RUN)
        // ------------------------------------------------------------------
        echo "--> STEP 6: Verifying state BEFORE running profit processor...\n";
        $user_a_balance_before = getUserWalletBalance($user_a_id);
        echo "User A wallet balance (before): " . number_format($user_a_balance_before, 4) . "\n";
        $uncredited_count_before = $pdo_mysql->query("SELECT COUNT(*) FROM pending_profits WHERE is_credited = 0")->fetchColumn();
        echo "Uncredited profits (before): {$uncredited_count_before}\n\n";

        // ------------------------------------------------------------------
        // 7. ACTION: Run the profit processor
        // ------------------------------------------------------------------
        echo "--> STEP 7: Executing processPendingProfits()...\n";
        processPendingProfits();
        echo "processPendingProfits() executed.\n\n";

        // ------------------------------------------------------------------
        // 8. VERIFICATION (POST-RUN)
        // ------------------------------------------------------------------
        echo "--> STEP 8: Verifying state AFTER running profit processor...\n";
        $user_a_balance_after = getUserWalletBalance($user_a_id);
        $uncredited_count_after = $pdo_mysql->query("SELECT COUNT(*) FROM pending_profits WHERE is_credited = 0")->fetchColumn();
        $credited_count_after = $pdo_mysql->query("SELECT COUNT(*) FROM pending_profits WHERE is_credited = 1")->fetchColumn();
        
        echo "User A wallet balance (after): " . number_format($user_a_balance_after, 4) . "\n";
        echo "Uncredited profits (after): {$uncredited_count_after}\n";
        echo "Credited profits (after): {$credited_count_after}\n\n";

        // Final check
        if ($user_a_balance_after > $user_a_balance_before && $uncredited_count_after == 0 && $credited_count_after == $uncredited_count_before) {
            echo "SUCCESS: User A was credited and all pending profits were marked as processed.\n";
        } else {
            echo "FAILURE: The test conditions were not met.\n";
            if ($user_a_balance_after <= $user_a_balance_before) echo " - User A wallet balance did not increase.\n";
            if ($uncredited_count_after > 0) echo " - Not all pending profits were processed.\n";
            if ($credited_count_after != $uncredited_count_before) echo " - The number of credited profits does not match the number created.\n";
        }
        echo "\nTEST COMPLETE.\n";


    } catch (Exception $e) {
        echo "AN ERROR OCCURRED: " . $e->getMessage() . "\n";
        if ($pdo_mysql->inTransaction()) {
            $pdo_mysql->rollBack();
            echo "Transaction rolled back.\n";
        }
    } finally {
         $pdo_mysql->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }
}

run_test();

echo "</pre>";

?>
