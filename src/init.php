<?php
// Start session (for authentication handling)
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Process any pending profits
triggerProcessPendingProfits();

// You can add other global initializations here