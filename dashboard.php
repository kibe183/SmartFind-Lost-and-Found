<?php
session_start();

require_once 'database.php';

// Make sure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get the logged-in user's role.
$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT role FROM users WHERE id = ? LIMIT 1"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check whether the account exists.
if (!$user) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

// Redirect according to the user's role.
if ($user['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

header("Location: user_dashboard.php");
exit;
?>