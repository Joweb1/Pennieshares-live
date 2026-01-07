# Project Changes Summary

The project has undergone significant functional and structural changes since the last Git commit. Key updates include new features for administrators, refined business logic for asset sales, and a redesigned payment processing flow.

### ✨ New Features

*   **Admin User View**: A new admin page (`pages/admin_user_view.php`) was created. It allows an admin to search for a user by username, email, or partner code and view a comprehensive profile, including personal details, wallet information, asset portfolio (active, sold, expired, completed), and the 10 most recent wallet transactions.
*   **Freshchat Integration**: The Freshchat live chat script has been added to the `wallet`, `profile_view`, and `market` pages to enhance user support. It has been removed from all other pages and shared templates to ensure it only appears where intended.
*   **Content Management System (CMS)**: The learning and news sections (`pages/learning.php`, `pages/learning_view.php`) are now fully dynamic, fetching articles from the new `content` table in the database instead of using hardcoded mock data.

### ♻️ Refinements & Logic Changes

*   **Market Hours for Asset Sales**: All asset selling functions (`sellCompletedAssets`, `sellAllExpiredAssetsOfType`, `sellCompletedAsset`) now check if the market is open before allowing a transaction. If the market is closed, the user is shown an error message. A new helper function, `isMarketOpen()`, was created for this.
*   **Asset Sale Timestamp**: To support future features like cron-based deletion of old records, a `sold_at` DATETIME column was added to the `assets` table. All functions related to selling or approving the sale of an asset were updated to set this timestamp.
*   **Payment Flow Redesign**: The "Add Money" feature (`pages/add_money.php`) and its handler (`paystack_handler.php`) were completely rewritten. The new implementation uses a single-page modal UI for amount and PIN entry and an API-like backend to handle Paystack integration, improving the user experience and security.
*   **Helper Functions**: New helper functions like `findUser()` and `getUserAssetsWorth()` were added to `src/functions.php` and `src/assets_functions.php` to centralize and reuse common logic.

### 🗑️ Deletions

*   **`add_money_callback.php`**: This file was deleted, as its logic was integrated into the new, more robust Paystack handling flow.

### ⚙️ Other Changes

*   **Database Schema**: In `config/database.php`, the schemas for the `assets` and `content` tables were updated.
*   **Admin Navigation**: Links to the new "User View" page were added to the admin navigation menu in the `intro-template.php` file for both mobile and desktop views.
*   **Configuration**: Email addresses in `pages/about.php` and `pages/admin_kyc.php` were updated to use environment variables for better security and configuration management.
*   **Routing**: The `index.php` file was updated to recognize the new pages (`admin_user_view`, `admin_content`, etc.).
*   **Email Templates**: The `add_money_success_admin.html` and `add_money_success_user.html` templates were replaced with more professional, structured HTML designs.
