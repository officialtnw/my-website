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

// Fetch current user's details
$email = $_SESSION["email"];
$stmt = $conn->prepare("SELECT id, first_name, last_name, rank_perms, max_clients, subscription_plan FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($current_user_id, $first_name, $last_name, $rank_perms, $max_clients, $subscription_plan);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();

// Check permissions (Owner or Coach)
if (!isset($rank_perms) || ($rank_perms != 1 && $rank_perms != 2)) {
    $_SESSION['error_message'] = "Access denied! Only Owners and Coaches can access this page.";
    header("Location: /dash");
    exit();
}

// Initialize variables
$user_to_edit = null;
$edit_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$users = [];
$clients_left = 0;

// Fetch users based on permissions
if ($rank_perms == 1) {
    $result = $conn->query("SELECT id, first_name, last_name, email, max_clients, checkin_day FROM users WHERE rank_perms != 1");
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'first_name' => htmlspecialchars($row['first_name']),
            'last_name' => htmlspecialchars($row['last_name']),
            'email' => htmlspecialchars($row['email']),
            'max_clients' => $row['max_clients'],
            'checkin_day' => $row['checkin_day']
        ];
    }
    $result->free();
} elseif ($rank_perms == 2) {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, max_clients, checkin_day FROM users WHERE parent_coach_id = ? AND rank_perms = 3");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'first_name' => htmlspecialchars($row['first_name']),
            'last_name' => htmlspecialchars($row['last_name']),
            'email' => htmlspecialchars($row['email']),
            'max_clients' => $row['max_clients'],
            'checkin_day' => $row['checkin_day']
        ];
    }
    $stmt->close();

    // Calculate clients left
    $total_clients_allowed = $max_clients ?? 0;
    $current_clients_count = count($users);
    $clients_left = ($total_clients_allowed === NULL) ? 'Unlimited' : max(0, $total_clients_allowed - $current_clients_count);
}

// Fetch user to edit
if ($edit_user_id > 0) {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, max_clients, rank_perms, fitness_goals, protein, carbs, fats FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($rank_perms == 2) {
            $stmt_check = $conn->prepare("SELECT parent_coach_id FROM users WHERE id = ?");
            $stmt_check->bind_param("i", $edit_user_id);
            $stmt_check->execute();
            $stmt_check->bind_result($client_parent_coach_id);
            $stmt_check->fetch();
            $stmt_check->close();
            if ($client_parent_coach_id != $current_user_id) {
                $_SESSION['error_message'] = "Access denied! You can only edit your own clients.";
                header("Location: /edit-user");
                exit();
            }
        }
        $user_to_edit = $row;
    } else {
        $_SESSION['error_message'] = "User not found!";
        header("Location: /edit-user");
        exit();
    }
    $stmt->close();
}

// Fetch check-in form templates
$daily_form = [];
$weekly_form = [];
$stmt = $conn->prepare("SELECT form_type, form_fields FROM checkin_form_templates WHERE coach_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['form_type'] === 'daily') {
        $daily_form = json_decode($row['form_fields'], true) ?? [];
    } elseif ($row['form_type'] === 'weekly') {
        $weekly_form = json_decode($row['form_fields'], true) ?? [];
    }
}
$stmt->close();

// Handle form submissions for check-in forms
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_form"])) {
    $form_type = $_POST["form_type"];
    $fields = json_encode($_POST["fields"]);

    $stmt = $conn->prepare("SELECT id FROM checkin_form_templates WHERE form_type = ? AND coach_id = ?");
    $stmt->bind_param("si", $form_type, $current_user_id);
    $stmt->execute();
    $stmt->bind_result($form_id);
    $exists = $stmt->fetch();
    $stmt->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE checkin_form_templates SET form_fields = ? WHERE form_type = ? AND coach_id = ?");
        $stmt->bind_param("ssi", $fields, $form_type, $current_user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO checkin_form_templates (coach_id, form_type, form_fields) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $current_user_id, $form_type, $fields);
    }

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Check-in form saved successfully!";
        if ($form_type === 'daily') {
            $daily_form = json_decode($fields, true);
        } elseif ($form_type === 'weekly') {
            $weekly_form = json_decode($fields, true);
        }
    } else {
        $_SESSION['error_message'] = "Error saving check-in form: " . $stmt->error;
    }
    $stmt->close();
    header("Location: /edit-user" . ($edit_user_id ? "?user_id=$edit_user_id" . (isset($_GET['tab']) ? "&tab=" . $_GET['tab'] : "") : ""));
    exit();
}

// Pagination for submissions
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$latest_submissions = [];
$total_submissions = 0;

if ($edit_user_id > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkin_submissions WHERE coach_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $current_user_id, $edit_user_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    if ($count_row = $count_result->fetch_assoc()) {
        $total_submissions = $count_row['total'];
    }
    $stmt->close();

    $total_pages = ceil($total_submissions / $per_page);

    $stmt = $conn->prepare("
        SELECT cs.id, cs.submitted_at, cs.submission_data, cs.form_type, u.first_name, u.last_name, u.checkin_day 
        FROM checkin_submissions cs 
        JOIN users u ON cs.user_id = u.id 
        WHERE cs.coach_id = ? AND cs.user_id = ?
        ORDER BY cs.submitted_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiii", $current_user_id, $edit_user_id, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $submission_data = json_decode($row['submission_data'], true);
        $row['submission_data'] = $submission_data;
        $latest_submissions[] = $row;
    }
    $stmt->close();
}

// Handle user profile updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update"])) {
    $new_fitness_goals = trim($_POST["fitness_goals"] ?? '');
    
    if ($rank_perms == 1) {
        $new_first_name = trim($_POST["first_name"]);
        $new_last_name = trim($_POST["last_name"]);
        $new_email = trim($_POST["email"]);
        $new_password = trim($_POST["password"] ?? '');
        $new_max_clients = (int)$_POST["max_clients"];
        $new_rank_perms = (int)$_POST["rank_perms"];

        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $new_email, $edit_user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $_SESSION['error_message'] = "Email already taken!";
        } else {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ?, fitness_goals = ?, max_clients = ?, rank_perms = ? WHERE id = ?";
                $params = [$new_first_name, $new_last_name, $new_email, $hashed_password, $new_fitness_goals, $new_max_clients, $new_rank_perms, $edit_user_id];
                $types = "sssssiii";
            } else {
                $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, fitness_goals = ?, max_clients = ?, rank_perms = ? WHERE id = ?";
                $params = [$new_first_name, $new_last_name, $new_email, $new_fitness_goals, $new_max_clients, $new_rank_perms, $edit_user_id];
                $types = "ssssiii";
            }

            $update = $conn->prepare($query);
            $update->bind_param($types, ...$params);
            if ($update->execute()) {
                $_SESSION['success_message'] = "Profile updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating user: " . $conn->error;
            }
            $update->close();
        }
        $check->close();
    } else {
        $query = "UPDATE users SET fitness_goals = ? WHERE id = ?";
        $update = $conn->prepare($query);
        $update->bind_param("si", $new_fitness_goals, $edit_user_id);
        if ($update->execute()) {
            $_SESSION['success_message'] = "Fitness goals updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating fitness goals: " . $conn->error;
        }
        $update->close();
    }
    
    header("Location: /edit-user?user_id=$edit_user_id" . (isset($_GET['tab']) ? "&tab=" . $_GET['tab'] : ""));
    exit();
}

// Handle user deletion (admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete"]) && $rank_perms == 1) {
    if ($edit_user_id == $current_user_id) {
        $_SESSION['error_message'] = "You cannot delete your own account!";
    } else {
        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $edit_user_id);
        if ($delete->execute()) {
            $_SESSION['success_message'] = "User deleted successfully! Redirecting...";
            header("Refresh: 2; url=/edit-user");
            exit();
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
        }
        $delete->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - STRV Fitness</title>
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
        .subscription-status-box {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            margin-right: 10px;
            cursor: default;
        }
        .status-paid, .status-friends_family {
            background-color: #28a745;
        }
        .status-pending {
            background-color: #dc3545;
        }
        .consultation-entry {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .consultation-entry:last-child {
            border-bottom: none;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            background: #f1f1f1;
            border-radius: 5px;
            cursor: pointer;
        }
        .tab.active {
            background: #1a73e8;
            color: white;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
            z-index: 1; /* Ensure cards are below the dropdown */
        }
        .client-list {
            margin-top: 20px;
        }
        .client-list div {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .form-actions {
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #1a73e8;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .submissions-table th, .submissions-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .pagination a {
            padding: 5px 10px;
            margin: 0 5px;
            text-decoration: none;
            color: #1a73e8;
        }
        .pagination a.active {
            background-color: #1a73e8;
            color: white;
            border-radius: 4px;
        }
        .success { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
        .stats-bar {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        .stat {
            text-align: center;
        }
        .stat i {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .search-container {
            position: relative;
            max-width: 400px;
            z-index: 1000; /* Ensure search container is above other elements */
        }
        #userSearch {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1001; /* Ensure dropdown is above all other elements */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .dropdown div {
            padding: 8px 12px;
            cursor: pointer;
        }
        .dropdown div:hover {
            background-color: #f5f5f5;
        }
        .dropdown .pagination-btn {
            padding: 8px 12px;
            text-align: center;
            background-color: #1a73e8;
            color: white;
            cursor: pointer;
        }
        .dropdown .pagination-btn:hover {
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
            <div class="sidebar-item" data-tab="dashboard" onclick="window.location.href='/dash'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="sidebar-item active" data-tab="manage" onclick="window.location.href='/edit-user'">
                <i class="fas fa-cog"></i>
                <span>Manage</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/logout'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </nav>
        <div class="dashboard-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <?php if ($rank_perms == 2 && !$edit_user_id): ?>
                <div class="card">
                    <h3>Client Statistics</h3>
                    <div class="stats-bar">
                        <div class="stat">
                            <i class="fas fa-users"></i>
                            <p><?php echo $total_clients_allowed === NULL ? 'Unlimited' : $total_clients_allowed; ?></p>
                            <p>Total Clients Allowed</p>
                        </div>
                        <div class="stat">
                            <i class="fas fa-user-check"></i>
                            <p><?php echo $current_clients_count; ?></p>
                            <p>Current Clients</p>
                        </div>
                        <div class="stat">
                            <i class="fas fa-user-plus"></i>
                            <p><?php echo $clients_left; ?></p>
                            <p>Clients Left</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($edit_user_id): ?>
                <div class="card">
                    <h2>Manage Client: <?php echo htmlspecialchars($user_to_edit['first_name'] . ' ' . $user_to_edit['last_name']); ?></h2>
                    <br>
                    <div class="tabs">
                        <div class="tab <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'checkins' ? 'active' : ''; ?>" data-tab="checkins">Checkins</div>
                        <div class="tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'nutrition' ? 'active' : ''; ?>" data-tab="nutrition">Nutrition</div>
                        <div class="tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'workout' ? 'active' : ''; ?>" data-tab="workout">Workout Program</div>
                        <div class="tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'logs' ? 'active' : ''; ?>" data-tab="logs">Logs</div>
                    </div>

                    <div class="tab-content <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'checkins' ? 'active' : ''; ?>" id="content-checkins">
                        <div class="card">
                            <h3>Latest Check-in Submissions</h3>
                            <?php if (!empty($latest_submissions)): ?>
                                <table class="submissions-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>When</th>
                                            <th>Form Type</th>
                                            <?php
                                            $form_fields = $weekly_form;
                                            if (!empty($form_fields)) {
                                                foreach ($form_fields as $field) {
                                                    echo "<th>" . htmlspecialchars($field['name']) . "</th>";
                                                }
                                            }
                                            ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latest_submissions as $index => $submission): ?>
                                            <tr>
                                                <td><?php echo $total_submissions - ($offset + $index); ?></td>
                                                <td><?php echo date('d M, Y', strtotime($submission['submitted_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($submission['form_type']); ?></td>
                                                <?php
                                                if (!empty($form_fields)) {
                                                    foreach ($form_fields as $field) {
                                                        $field_name = $field['name'];
                                                        $value = isset($submission['submission_data'][$field_name]) ? htmlspecialchars($submission['submission_data'][$field_name]) : 'N/A';
                                                        echo "<td>$value</td>";
                                                    }
                                                }
                                                ?>
                                                <td><i class="fas fa-check-circle action-icon"></i></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="pagination-controls">
                                    <div>
                                        Show 
                                        <select onchange="window.location.href='/edit-user?user_id=<?php echo $edit_user_id; ?>&tab=checkins&page=1&per_page='+this.value">
                                            <option value="12" <?php echo $per_page == 12 ? 'selected' : ''; ?>>12</option>
                                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                                        </select> 
                                        per page
                                    </div>
                                    <div>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_submissions); ?> of <?php echo $total_submissions; ?> entries
                                    </div>
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="/edit-user?user_id=<?php echo $edit_user_id; ?>&tab=checkins&page=<?php echo $page - 1; ?>">«</a>
                                        <?php endif; ?>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <a href="/edit-user?user_id=<?php echo $edit_user_id; ?>&tab=checkins&page=<?php echo $i; ?>" class="<?php echo $page == $i ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        <?php if ($page < $total_pages): ?>
                                            <a href="/edit-user?user_id=<?php echo $edit_user_id; ?>&tab=checkins&page=<?php echo $page + 1; ?>">»</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p>No submissions yet.</p>
                            <?php endif; ?>
                        </div>

                        <div class="card">
                            <h3>Edit Profile</h3>
                            <form method="post">
                                <div class="form-group">
                                    <label>First Name:</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_to_edit['first_name'] ?? ''); ?>" 
                                        <?php echo $rank_perms != 1 ? 'disabled' : 'required'; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Last Name:</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_to_edit['last_name'] ?? ''); ?>" 
                                        <?php echo $rank_perms != 1 ? 'disabled' : 'required'; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Email:</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email'] ?? ''); ?>" 
                                        <?php echo $rank_perms != 1 ? 'disabled' : 'required'; ?>>
                                </div>
                                <?php if ($rank_perms == 1): ?>
                                    <div class="form-group">
    <label>New Password (leave blank to keep current):</label>
    <input type="password" name="password" minlength="8">
</div>
<?php endif; ?>
<div class="form-group">
    <label>Fitness Goals:</label>
    <textarea name="fitness_goals"><?php echo htmlspecialchars($user_to_edit['fitness_goals'] ?? ''); ?></textarea>
</div>
<?php if ($rank_perms == 1): ?>
    <div class="form-group">
        <label>Max Clients:</label>
        <select name="max_clients" required>
            <option value="25" <?php echo ($user_to_edit['max_clients'] ?? 0) == 25 ? 'selected' : ''; ?>>25 Clients</option>
            <option value="50" <?php echo ($user_to_edit['max_clients'] ?? 0) == 50 ? 'selected' : ''; ?>>50 Clients</option>
            <option value="200" <?php echo ($user_to_edit['max_clients'] ?? 0) == 200 ? 'selected' : ''; ?>>200 Clients</option>
        </select>
    </div>
    <div class="form-group">
        <label>Account Type:</label>
        <select name="rank_perms" required>
            <option value="1" <?php echo ($user_to_edit['rank_perms'] ?? 0) == 1 ? 'selected' : ''; ?>>Admin</option>
            <option value="2" <?php echo ($user_to_edit['rank_perms'] ?? 0) == 2 ? 'selected' : ''; ?>>Coach</option>
            <option value="3" <?php echo ($user_to_edit['rank_perms'] ?? 0) == 3 ? 'selected' : ''; ?>>Client</option>
        </select>
    </div>
<?php endif; ?>
<div class="form-actions">
    <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
    <?php if ($rank_perms == 1): ?>
        <button type="submit" name="delete" class="btn btn-danger">Delete User</button>
    <?php endif; ?>
</div>
</form>
</div>
</div>

<div class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] == 'nutrition' ? 'active' : ''; ?>" id="content-nutrition">
    <div class="card">
        <h3>Nutrition Plan</h3>
        <form method="post">
            <div class="form-group">
                <label>Daily Protein (grams):</label>
                <input type="number" name="protein" value="<?php echo htmlspecialchars($user_to_edit['protein'] ?? ''); ?>" min="0">
            </div>
            <div class="form-group">
                <label>Daily Carbs (grams):</label>
                <input type="number" name="carbs" value="<?php echo htmlspecialchars($user_to_edit['carbs'] ?? ''); ?>" min="0">
            </div>
            <div class="form-group">
                <label>Daily Fats (grams):</label>
                <input type="number" name="fats" value="<?php echo htmlspecialchars($user_to_edit['fats'] ?? ''); ?>" min="0">
            </div>
            <button type="submit" name="update" class="btn btn-primary">Save Nutrition Plan</button>
        </form>
    </div>
</div>

<div class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] == 'workout' ? 'active' : ''; ?>" id="content-workout">
    <div class="card">
        <h3>Workout Program</h3>
        <p>Workout program management will be implemented here.</p>
    </div>
</div>

<div class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] == 'logs' ? 'active' : ''; ?>" id="content-logs">
    <div class="card">
        <h3>Logs</h3>
        <p>Logs will be displayed here.</p>
    </div>
</div>
</div>
<?php else: ?>
    <div class="card">
        <h2>Search User by Email First Letter</h2>
        <br>
        <div class="search-container">
            <input type="text" id="userSearch" placeholder="Type first letter of email..." autocomplete="off" maxlength="1">
            <div id="userDropdown" class="dropdown" style="display: none;"></div>
        </div>
    </div>

    <div class="card">
        <h3>Manage Daily Check-in Form</h3>
        <form method="post">
            <input type="hidden" name="form_type" value="daily">
            <div id="daily-fields">
                <?php if (!empty($daily_form)): ?>
                    <?php foreach ($daily_form as $index => $field): ?>
                        <div class="form-group field-group">
                            <label>Field Name:</label>
                            <input type="text" name="fields[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                            <label>Field Type:</label>
                            <select name="fields[<?php echo $index; ?>][type]">
                                <option value="number" <?php echo $field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                <option value="rating" <?php echo $field['type'] === 'rating' ? 'selected' : ''; ?>>Rating (1-10)</option>
                                <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                            </select>
                            <button type="button" class="remove-field btn btn-danger">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="form-group field-group">
                        <label>Field Name:</label>
                        <input type="text" name="fields[0][name]" required>
                        <label>Field Type:</label>
                        <select name="fields[0][type]">
                            <option value="number">Number</option>
                            <option value="rating">Rating (1-10)</option>
                            <option value="text">Text</option>
                        </select>
                        <button type="button" class="remove-field btn btn-danger">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="button" id="add-daily-field" class="btn btn-primary">Add Field</button>
                <button type="submit" name="save_form" class="btn btn-primary">Save Daily Form</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Manage Weekly Check-in Form</h3>
        <form method="post">
            <input type="hidden" name="form_type" value="weekly">
            <div id="weekly-fields">
                <?php if (!empty($weekly_form)): ?>
                    <?php foreach ($weekly_form as $index => $field): ?>
                        <div class="form-group field-group">
                            <label>Field Name:</label>
                            <input type="text" name="fields[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                            <label>Field Type:</label>
                            <select name="fields[<?php echo $index; ?>][type]">
                                <option value="number" <?php echo $field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                <option value="rating" <?php echo $field['type'] === 'rating' ? 'selected' : ''; ?>>Rating (1-10)</option>
                                <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                            </select>
                            <button type="button" class="remove-field btn btn-danger">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="form-group field-group">
                        <label>Field Name:</label>
                        <input type="text" name="fields[0][name]" required>
                        <label>Field Type:</label>
                        <select name="fields[0][type]">
                            <option value="number">Number</option>
                            <option value="rating">Rating (1-10)</option>
                            <option value="text">Text</option>
                        </select>
                        <button type="button" class="remove-field btn btn-danger">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="button" id="add-weekly-field" class="btn btn-primary">Add Field</button>
                <button type="submit" name="save_form" class="btn btn-primary">Save Weekly Form</button>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>
</div>

<script>
    let dailyFieldIndex = <?php echo !empty($daily_form) ? count($daily_form) : 1; ?>;
    let weeklyFieldIndex = <?php echo !empty($weekly_form) ? count($weekly_form) : 1; ?>;

    document.getElementById('add-daily-field')?.addEventListener('click', function() {
        const fieldsContainer = document.getElementById('daily-fields');
        const fieldHtml = `
            <div class="form-group field-group">
                <label>Field Name:</label>
                <input type="text" name="fields[${dailyFieldIndex}][name]" required>
                <label>Field Type:</label>
                <select name="fields[${dailyFieldIndex}][type]">
                    <option value="number">Number</option>
                    <option value="rating">Rating (1-10)</option>
                    <option value="text">Text</option>
                </select>
                <button type="button" class="remove-field btn btn-danger">Remove</button>
            </div>`;
        fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        dailyFieldIndex++;
    });

    document.getElementById('add-weekly-field')?.addEventListener('click', function() {
        const fieldsContainer = document.getElementById('weekly-fields');
        const fieldHtml = `
            <div class="form-group field-group">
                <label>Field Name:</label>
                <input type="text" name="fields[${weeklyFieldIndex}][name]" required>
                <label>Field Type:</label>
                <select name="fields[${weeklyFieldIndex}][type]">
                    <option value="number">Number</option>
                    <option value="rating">Rating (1-10)</option>
                    <option value="text">Text</option>
                </select>
                <button type="button" class="remove-field btn btn-danger">Remove</button>
            </div>`;
        fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        weeklyFieldIndex++;
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-field')) {
            e.target.parentElement.remove();
        }
    });

    const userSearch = document.getElementById('userSearch');
    const userDropdown = document.getElementById('userDropdown');
    const users = <?php echo json_encode($users); ?> || [];
    let currentPage = 1;
    const itemsPerPage = 10;

    if (userSearch) {
        console.log('Initial users array:', users);

        userSearch.addEventListener('input', debounce(function() {
            const query = userSearch.value.trim().toLowerCase();
            currentPage = 1; // Reset to first page on new search
            updateDropdown(query);
        }, 300));

        function updateDropdown(query) {
            userDropdown.innerHTML = '';
            userDropdown.style.display = 'none';

            if (query.length === 0) {
                return;
            }

            const filteredUsers = users.filter(user => 
                user.email.toLowerCase().startsWith(query)
            );

            console.log('Query:', query);
            console.log('Filtered users:', filteredUsers);

            if (filteredUsers.length === 0) {
                userDropdown.innerHTML = '<div style="padding: 8px 12px;">No users found</div>';
            } else {
                const totalPages = Math.ceil(filteredUsers.length / itemsPerPage);
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const paginatedUsers = filteredUsers.slice(startIndex, endIndex);

                paginatedUsers.forEach(user => {
                    const div = document.createElement('div');
                    div.innerHTML = `${user.first_name} ${user.last_name} <br><small>${user.email}</small>`;
                    div.addEventListener('click', () => {
                        window.location.href = `/edit-user?user_id=${user.id}`;
                    });
                    userDropdown.appendChild(div);
                });

                if (totalPages > 1 && currentPage < totalPages) {
                    const nextBtn = document.createElement('div');
                    nextBtn.textContent = 'Next Page';
                    nextBtn.className = 'pagination-btn';
                    nextBtn.addEventListener('click', () => {
                        currentPage++;
                        updateDropdown(query);
                        userDropdown.scrollTop = 0; // Scroll to top of dropdown
                    });
                    userDropdown.appendChild(nextBtn);
                }

                if (currentPage > 1) {
                    const prevBtn = document.createElement('div');
                    prevBtn.textContent = 'Previous Page';
                    prevBtn.className = 'pagination-btn';
                    prevBtn.addEventListener('click', () => {
                        currentPage--;
                        updateDropdown(query);
                        userDropdown.scrollTop = 0; // Scroll to top of dropdown
                    });
                    userDropdown.insertBefore(prevBtn, userDropdown.firstChild);
                }
            }
            userDropdown.style.display = 'block';
        }

        document.addEventListener('click', (e) => {
            if (!userSearch.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(`content-${tab.getAttribute('data-tab')}`).classList.add('active');
            window.history.pushState({}, document.title, `/edit-user?user_id=${<?php echo $edit_user_id ?: 0; ?>}&tab=${tab.getAttribute('data-tab')}`);
        });
    });
</script>
</body>
</html>