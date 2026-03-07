<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    header('Location: ' . $baseUrl . '/guest-no-access.php');
    exit;
}

// Redirect admins to admin-no-access page
if ($_SESSION['role'] === 'grocery_admin') {
    header('Location: ' . $baseUrl . '/admin-no-access.php');
    exit;
}

// Ensure only customers can access
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . $baseUrl . '/customer-no-access.php');
    exit;
}