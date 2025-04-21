<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';

$conn = get_db_connection();

// Fetch current user's details
$username = $_SESSION["username"];
$stmt = $conn->prepare("SELECT id, rank_perms, max_clients FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($current_user_id, $rank_perms, $max_clients);
$stmt->fetch();
$stmt->close();

// Check if user is a coach
if ($rank_perms != 2) {
    header("Location: /dash");
    exit();
}

// Fetch current number of clients
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE parent_coach_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$stmt->bind_result($current_client_count);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_client'])) {
    if ($current_client_count >= $max_clients) {
        $_SESSION['error_message'] = "You have reached your client limit. Upgrade your plan to add more clients.";
        header("Location: /dash");
        exit();
    }

    $client_username = $_POST['client_username'];
    $client_email = $_POST['client_email'];
    $client_password = password_hash($_POST['client_password'], PASSWORD_DEFAULT);

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $client_username, $client_email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error_message'] = "Username or email already exists.";
        header("Location: /dash");
        exit();
    }
    $stmt->close();

    // Insert new client
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, rank_perms, parent_coach_id) VALUES (?, ?, ?, 3, ?)");
    $rank_perms_client = 3; // Rank for clients
    $stmt->bind_param("sssi", $client_username, $client_email, $client_password, $rank_perms_client, $current_user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Client added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding client: " . $stmt->error;
    }
    $stmt->close();

    header("Location: /dash");
    exit();
}

$conn->close();
?>