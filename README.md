# Pennieshares

## Project Overview

This project is a PHP-based web application called "pennieshares". It appears to be a platform for an investment or MLM (Multi-Level Marketing) scheme where users can buy "assets" and earn returns. The application includes user authentication, a wallet system, asset management, a broker system, and KYC verification.

## Core Technologies

*   **Backend:** PHP
*   **Database:** SQLite
*   **Frontend:** The file structure suggests that the frontend is a mix of PHP-generated HTML and JavaScript.
*   **Dependencies:**
    *   `phpmailer/phpmailer`: For sending emails.
    *   `minishlink/web-push`: For web push notifications.
    *   `vlucas/phpdotenv`: For managing environment variables.

## Application Structure

*   `index.php`: The main entry point and router for the application.
*   `config/database.php`: Manages the SQLite database connection and schema.
*   `src/functions.php`: Contains core application logic, including user management, wallet transactions, and broker interactions.
*   `src/email_functions.php`: Handles email sending using PHPMailer and email templates.
*   `src/assets_functions.php`: Contains the logic for managing assets, including buying, selling, and calculating payouts.
*   `pages/`: Contains the different pages of the application, such as login, register, dashboard, and admin panels.
*   `database/mydatabase.sqlite`: The SQLite database file.
*   `vendor/`: Contains the project's dependencies managed by Composer.

## Key Features

*   **User Authentication:** Users can register, log in, reset their passwords, and verify their accounts via OTP.
*   **Wallet System:** Users have a wallet to store their funds. They can credit, debit, and transfer funds to other users (brokers).
*   **Asset Management:** Users can buy assets of different types. The application calculates and distributes payouts based on a complex system of "generations" and "shared pots". Assets can also expire or be sold.
*   **Broker System:** The application has a concept of "brokers," who seem to be a special type of user. Users can transfer funds to brokers.
*   **KYC Verification:** Users need to go through a KYC (Know Your Customer) process, which involves submitting documents for verification.
*   **Admin Panel:** There are several admin pages for managing users, assets, and KYC verifications.
*   **Push Notifications:** The application can send web push notifications and Expo push notifications to users.
*   **Email Notifications:** The application sends various email notifications for events like registration, transactions, and asset purchases.
