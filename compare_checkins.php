<?php
session_start();
require_once 'includes/db_connect.php';

$conn = get_db_connection();
if (!$conn) {
    die(json_encode(['error' => 'Database connection failed']));
}

if (!isset($_GET['latest_id']) || !isset($_GET['previous_id']) || !isset($_GET['user_id'])) {
    echo json_encode(['error' => 'Missing latest_id, previous_id, or user_id']);
    exit;
}

$latest_id = (int)$_GET['latest_id'];
$previous_id = (int)$_GET['previous_id'];
$user_id = (int)$_GET['user_id'];
$session_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Determine permissions
$rank_perms = 0;
$stmt = $conn->prepare("SELECT rank_perms FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($rank_perms);
$stmt->fetch();
$stmt->close();

$where_clause = "id IN (?, ?) AND form_type = 'weekly'";
$params = "ii";
$types = [$latest_id, $previous_id];

if ($rank_perms == 1) {
    // Admin can view any
    $where_clause .= " AND user_id = ?";
    $params .= "i";
    $types[] = $user_id;
} elseif ($rank_perms == 2) {
    // Coach can view their clients
    $where_clause .= " AND user_id IN (SELECT id FROM users WHERE parent_coach_id = ?)";
    $params .= "i";
    $types[] = $session_user_id;
} else {
    // Client can only view their own
    $where_clause .= " AND user_id = ?";
    $params .= "i";
    $types[] = $session_user_id;
    if ($user_id !== $session_user_id) {
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
}

$query = "SELECT id, submission_data, submitted_at FROM checkin_submissions WHERE $where_clause";
$stmt = $conn->prepare($query);
$stmt->bind_param($params, ...$types);
$stmt->execute();
$result = $stmt->get_result();

$checkins = [];
while ($row = $result->fetch_assoc()) {
    $checkins[$row['id']] = [
        'submission_data' => json_decode($row['submission_data'], true),
        'when' => (new DateTime($row['submitted_at']))->format('d M, Y')
    ];
}

if (isset($checkins[$latest_id]) && isset($checkins[$previous_id])) {
    echo json_encode([
        'latest' => $checkins[$latest_id],
        'previous' => $checkins[$previous_id]
    ]);
} else {
    echo json_encode(['error' => 'One or both check-ins not found or permission denied']);
}

$stmt->close();
$conn->close();
?>