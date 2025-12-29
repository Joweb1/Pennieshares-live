The project is a custom-built PHP application with no major framework, written in a procedural style. Its primary purpose is to function as a financial platform that operates on a Multi-Level Marketing (MLM) model.

**Architecture & Key Concepts:**

1.  **Front Controller:** The application uses `index.php` as a simple front controller, manually routing URLs to corresponding files in the `pages` directory.
2.  **Dual-Database System:** A key architectural decision is the use of two databases.
    *   **MySQL (`$pdo_mysql`):** Stores user-centric, persistent data such as user profiles, credentials, KYC information, and push notification tokens.
    *   **SQLite (`$pdo_sqlite`):** Stores business-critical, transactional data. This includes the `assets` users own, the `asset_types`, the ledger of `wallet_transactions`, and tables for managing the MLM-style payouts (`payouts`, `pending_profits`, `company_funds`).
3.  **Schema Management:** The database schemas for both MySQL and SQLite are defined directly in `config/database.php` using `CREATE TABLE IF NOT EXISTS` statements. The application recreates its schema on every run, which is unconventional for production systems.
4.  **Core Business Logic (MLM Engine):** The file `src/assets_functions.php` contains the main business logic. When a user "buys an asset":
    *   The asset is placed into a hierarchical tree structure.
    *   The purchase price is divided and allocated to various funds (e.g., company profit, referral bonuses, and two main pots: a "Generational Pot" and a "Shared Pot").
    *   Payouts from the "Generational Pot" are distributed to the asset's "ancestors" (up to 5 levels).
    *   Payouts from the "Shared Pot" are distributed in small fractions to all active assets in the system.
    *   These payouts are not instant but are scheduled in the `pending_profits` table to be credited at a random future time by the `processPendingProfits()` function.
5.  **User & Wallet Management:** `src/functions.php` handles all standard application functionality: user registration, login (`check_auth`), password management, and wallet operations (`creditUserWallet`, `debitUserWallet`).
6.  **Dependencies:** The project relies on `phpmailer/phpmailer` for emails (with a queuing system for performance), `minishlink/web-push` for push notifications, and `vlucas/phpdotenv` for configuration.

**Conclusion:**

The application is a self-contained, MLM-based investment platform. The financial model is entirely dependent on new money entering the system through asset purchases to pay returns to existing users. The separation of user data into MySQL and transactional data into SQLite is a notable, if unusual, design choice. The code is procedural and lacks a modern framework structure, but it implements complex business rules for its specific purpose.