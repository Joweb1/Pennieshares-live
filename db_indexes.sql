-- Recommended Indexes for Pennieshares Database Performance Improvement

-- On 'users' table:
-- These indexes will speed up searches by username, email, and partner_code,
-- as well as joins involving these columns.
ALTER TABLE users ADD INDEX idx_users_username (username);
ALTER TABLE users ADD INDEX idx_users_email (email);
ALTER TABLE users ADD INDEX idx_users_partner_code (partner_code);
-- Assuming 'id' is already the PRIMARY KEY, it is automatically indexed.
-- If not, ensure it is: ALTER TABLE users ADD PRIMARY KEY (id);

-- On 'assets' table:
-- These indexes will speed up filtering by user_id and asset_type_id,
-- and improve join performance.
-- Indexes on status columns will optimize filtering for active/completed/expired assets.
ALTER TABLE assets ADD INDEX idx_assets_user_id (user_id);
ALTER TABLE assets ADD INDEX idx_assets_asset_type_id (asset_type_id);
ALTER TABLE assets ADD INDEX idx_assets_is_completed (is_completed);
ALTER TABLE assets ADD INDEX idx_assets_is_manually_expired (is_manually_expired);
ALTER TABLE assets ADD INDEX idx_assets_expires_at (expires_at);
ALTER TABLE assets ADD INDEX idx_assets_sale_status (sale_status(255));

-- On 'asset_types' table:
-- Assuming 'id' is already the PRIMARY KEY, it is automatically indexed.
-- If not, ensure it is: ALTER TABLE asset_types ADD PRIMARY KEY (id);

-- On 'pending_profits' table:
-- These indexes will speed up filtering by user_id, credited status, payout type,
-- and ordering by credit_at.
ALTER TABLE pending_profits ADD INDEX idx_pending_profits_user_id (user_id);
ALTER TABLE pending_profits ADD INDEX idx_pending_profits_is_credited (is_credited);
ALTER TABLE pending_profits ADD INDEX idx_pending_profits_payout_type (payout_type);
ALTER TABLE pending_profits ADD INDEX idx_pending_profits_credit_at (credit_at);

-- On 'wallet_transactions' table:
-- These indexes will speed up filtering by user_id and ordering by created_at.
ALTER TABLE wallet_transactions ADD INDEX idx_wallet_transactions_user_id (user_id);
ALTER TABLE wallet_transactions ADD INDEX idx_wallet_transactions_created_at (created_at);
