<?php
function get_db_connection() {
    $conn = new mysqli("localhost", "root", "lol_123", "strv_db");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}