<?php
require_once 'includes/db_connect.php';

$conn = get_db_connection();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "Applying schema migrations...\n";

// Drop existing foreign key constraints
$queries = [
    "ALTER TABLE users DROP FOREIGN KEY users_ibfk_3",
    "ALTER TABLE users DROP FOREIGN KEY users_ibfk_2",
    "ALTER TABLE users MODIFY coach_id INT NULL",
    "ALTER TABLE users MODIFY parent_coach_id INT NULL",
    "ALTER TABLE users ADD CONSTRAINT users_ibfk_3 FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT",
    "ALTER TABLE users ADD CONSTRAINT users_ibfk_2 FOREIGN KEY (parent_coach_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT"
];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Executed: $query\n";
    } else {
        echo "Error executing $query: " . $conn->error . "\n";
    }
}

echo "Schema migration completed.\n";

$conn->close();
?>