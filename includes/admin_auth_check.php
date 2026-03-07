<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    header('Location: ' . $baseUrl . '/guest-no-access.php');
    exit;
}

// Redirect customers to customer-no-access page
if ($_SESSION['role'] === 'customer') {
    header('Location: ' . $baseUrl . '/customer-no-access.php');
    exit;
}

// Ensure only grocery admins can access
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/guest-no-access.php');
    exit;
}
