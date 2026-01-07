<?php
// test_get_kyc_status.php

// Include necessary files
require_once __DIR__ . '/../../src/functions.php';

// Mock the session for testing purposes
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'testuser',
    'email' => 'testuser@example.com',
    'fullname' => 'Test User'
];

// --- Test Case 1: User with a 'pending' KYC status ---
echo "--- Test Case 1: User with a 'pending' KYC status ---
";

// Mock the database call
$pdo_mysql = new class {
    public function prepare($query) {
        return new class {
            public function execute($params) {}
            public function fetchColumn() {
                return 'pending';
            }
        };
    }
};

// Include the API file to execute it
include __DIR__ . '/get_kyc_status.php';

// Unset the mock
unset($pdo_mysql);


// --- Test Case 2: User with an 'approved' KYC status ---
echo "\n\n--- Test Case 2: User with an 'approved' KYC status ---
";

// Mock the database call
$pdo_mysql = new class {
    public function prepare($query) {
        return new class {
            public function execute($params) {}
            public function fetchColumn() {
                return 'approved';
            }
        };
    }
};

// Include the API file to execute it
include __DIR__ . '/get_kyc_status.php';

// Unset the mock
unset($pdo_mysql);


// --- Test Case 3: User who has not submitted KYC ---
echo "\n\n--- Test Case 3: User who has not submitted KYC ---
";

// Mock the database call
$pdo_mysql = new class {
    public function prepare($query) {
        return new class {
            public function execute($params) {}
            public function fetchColumn() {
                return false;
            }
        };
    }
};

// Include the API file to execute it
include __DIR__ . '/get_kyc_status.php';

// Unset the mock
unset($pdo_mysql);

?>