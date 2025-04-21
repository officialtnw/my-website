<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION["email"])) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';

// Database connection
$conn = get_db_connection();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch current user's details
$email = $_SESSION["email"];
$stmt = $conn->prepare("SELECT id, first_name, last_name, rank_perms FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($current_user_id, $first_name, $last_name, $rank_perms);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();

// Validate checkin_id and user_id from URL
$checkin_id = isset($_GET['checkin_id']) ? (int)$_GET['checkin_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$form_type = isset($_GET['form_type']) ? $_GET['form_type'] : 'weekly';
$compare_checkin_id = isset($_GET['compare_checkin_id']) ? (int)$_GET['compare_checkin_id'] : null;

if (!$checkin_id || !$user_id || !in_array($form_type, ['daily', 'weekly'])) {
    $error = "Invalid check-in ID, user ID, or form type.";
    $conn->close();
    header("Location: /dash");
    exit();
}

// Function to fetch check-in details
function getCheckinDetails($conn, $checkin_id, $form_type, $user_id, $coach_id, $rank_perms) {
    $where_clause = "id = ? AND form_type = ?";
    $params = "is";
    $types = [$checkin_id, $form_type];

    if ($rank_perms == 1) {
        $where_clause .= " AND user_id = ?";
        $params .= "i";
        $types[] = $user_id;
    } elseif ($rank_perms == 2) {
        $where_clause .= " AND user_id IN (SELECT id FROM users WHERE parent_coach_id = ?)";
        $params .= "i";
        $types[] = $coach_id;
    } else {
        $where_clause .= " AND user_id = ?";
        $params .= "i";
        $types[] = $user_id;
    }

    $stmt = $conn->prepare("SELECT submission_data, submitted_at FROM checkin_submissions WHERE $where_clause");
    $stmt->bind_param($params, ...$types);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return [
            'submission_data' => json_decode($row['submission_data'], true),
            'when' => (new DateTime($row['submitted_at']))->format('d M, Y')
        ];
    }
    return null;
}

// Fetch the current check-in details
$checkin_details = getCheckinDetails($conn, $checkin_id, $form_type, $user_id, $current_user_id, $rank_perms);
if (!$checkin_details) {
    $error = "Check-in not found or you do not have permission to view it.";
    $conn->close();
    header("Location: /dash");
    exit();
}

// Fetch other check-ins for comparison dropdown
$other_checkins = [];
$stmt = $conn->prepare("SELECT id, submitted_at FROM checkin_submissions WHERE user_id = ? AND form_type = ? AND id != ? ORDER BY submitted_at DESC");
$stmt->bind_param("isi", $user_id, $form_type, $checkin_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $other_checkins[] = [
        'id' => $row['id'],
        'when' => (new DateTime($row['submitted_at']))->format('d M, Y')
    ];
}
$stmt->close();

// Fetch comparison check-in details if selected
$compare_checkin_details = null;
if ($compare_checkin_id) {
    $compare_checkin_details = getCheckinDetails($conn, $compare_checkin_id, $form_type, $user_id, $current_user_id, $rank_perms);
    if (!$compare_checkin_details) {
        $error = "Comparison check-in not found or you do not have permission to view it.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Check-in - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .dashboard-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .sidebar {
            width: 100%;
            background: #505050;
            color: white;
            padding: 20px;
            box-sizing: border-box;
        }
        .dashboard-container {
            flex: 1;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffe6e6;
            border-radius: 5px;
        }
        .checkin-details h3 {
            margin-bottom: 15px;
            font-size: 1.5em;
            color: #333;
        }
        .checkin-details .video-section {
            margin-bottom: 20px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
            text-align: center;
            color: #666;
        }
        .checkin-details .data-section p {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .checkin-details .data-section p strong {
            flex: 0 0 50%;
            color: #333;
        }
        .checkin-details .data-section p span {
            flex: 0 0 50%;
            color: #666;
            text-align: left;
        }
        .comparison-section {
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .comparison-section select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .comparison-section button {
            padding: 8px 16px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .comparison-section button:hover {
            background-color: #1557b0;
        }
        .comparison-details {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .comparison-details .comparison-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .comparison-details .comparison-row div {
            flex: 1;
            padding: 10px;
        }
        .comparison-details .comparison-row div:first-child {
            border-right: 1px solid #ddd;
        }
        .back-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #1a73e8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-btn:hover {
            background-color: #1557b0;
        }
        @media (min-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 250px;
                height: 100vh;
            }
            .dashboard-container {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="sidebar-logo">
                <img src="/assets/images/logo.png" alt="Logo">
            </div>
            <div class="sidebar-icons">
                <i class="fas fa-bell"></i>
                <i class="fas fa-comment"></i>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/dash'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <?php if ($rank_perms == 1 || $rank_perms == 2): ?>
                <div class="sidebar-item" onclick="window.location.href='/edit-user'">
                    <i class="fas fa-cog"></i>
                    <span>Manage</span>
                </div>
            <?php endif; ?>
            <div class="sidebar-item" onclick="window.location.href='/logout'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </nav>

        <div class="dashboard-container">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card checkin-details">
                <h3>Current Check-in: <?php echo $checkin_details['when']; ?></h3>
                <div class="video-section">
                    <p>No check-in video uploaded.</p>
                </div>
                <div class="data-section">
                    <?php foreach ($checkin_details['submission_data'] as $key => $value): ?>
                        <p><strong><?php echo htmlspecialchars($key); ?>:</strong> <span><?php echo htmlspecialchars($value ?: '-'); ?></span></p>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($other_checkins)): ?>
                    <div class="comparison-section">
                        <button onclick="compareCheckin()">Compare with Previous Check-in</button>
                        <select id="compareCheckinSelect">
                            <option value="">Select Other Check-in</option>
                            <?php foreach ($other_checkins as $checkin): ?>
                                <option value="<?php echo $checkin['id']; ?>"><?php echo "Check in on: " . $checkin['when']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <a href="/dash" class="back-btn">Go Back</a>
            </div>

            <?php if ($compare_checkin_details): ?>
                <div class="card comparison-details">
                    <h3>Comparison: Current vs Previous</h3>
                    <div class="comparison-row">
                        <div><strong>Current Check-in (<?php echo $checkin_details['when']; ?>)</strong></div>
                        <div><strong>Previous Check-in (<?php echo $compare_checkin_details['when']; ?>)</strong></div>
                    </div>
                    <?php foreach ($checkin_details['submission_data'] as $key => $value): ?>
                        <div class="comparison-row">
                            <div><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value ?: '-'); ?></div>
                            <div><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($compare_checkin_details['submission_data'][$key] ?? '-'); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function compareCheckin() {
        const compareCheckinId = document.getElementById('compareCheckinSelect').value;
        if (compareCheckinId) {
            window.location.href = `/view_checkin.php?checkin_id=<?php echo $checkin_id; ?>&form_type=<?php echo $form_type; ?>&user_id=<?php echo $user_id; ?>&compare_checkin_id=${compareCheckinId}`;
        } else {
            alert('Please select a check-in to compare.');
        }
    }
    </script>
</body>
</html>