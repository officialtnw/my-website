<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "lol_123", "strv_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("INSERT INTO users (username, email, password, rank, created_at) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$username = "testuser";
$email = "test@example.com";
$password = password_hash("testpass", PASSWORD_DEFAULT);
$rank = 0;
$created_at = date('Y-m-d H:i:s');

$stmt->bind_param("sssis", $username, $email, $password, $rank, $created_at);
if ($stmt->execute()) {
    echo "User inserted successfully!";
} else {
    echo "Execute failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>