<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to connect to the database.']);
    exit;
}

if (!isset($_GET['checkin_id']) || !isset($_GET['form_type']) || !isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: checkin_id, form_type, or user_id']);
    exit;
}

$checkin_id = (int)$_GET['checkin_id'];
$form_type = $_GET['form_type'];
$user_id = (int)$_GET['user_id'];
$session_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Validate form_type
if (!in_array($form_type, ['daily', 'weekly'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid form type']);
    exit;
}

// Determine if the requester is a coach or admin
$rank_perms = 0;
$stmt = $conn->prepare("SELECT rank_perms FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($rank_perms);
$stmt->fetch();
$stmt->close();

$where_clause = "id = ? AND form_type = ?";
$params = "is";
$types = [$checkin_id, $form_type];

if ($rank_perms == 1) {
    // Admin can view any check-in
    $where_clause .= " AND user_id = ?";
    $params .= "i";
    $types[] = $user_id;
} elseif ($rank_perms == 2) {
    // Coach can view their clients' check-ins
    $where_clause .= " AND user_id IN (SELECT id FROM users WHERE parent_coach_id = ?)";
    $params .= "i";
    $types[] = $session_user_id;
} else {
    // Client can only view their own
    $where_clause .= " AND user_id = ?";
    $params .= "i";
    $types[] = $session_user_id;
    if ($user_id !== $session_user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
}

$query = "SELECT submission_data, submitted_at FROM checkin_submissions WHERE $where_clause";
$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query preparation failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param($params, ...$types);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'submission_data' => json_decode($row['submission_data'], true),
        'when' => (new DateTime($row['submitted_at']))->format('d M, Y')
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Check-in not found or permission denied']);
}

$stmt->close();
$conn->close();
?>