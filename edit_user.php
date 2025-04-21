<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION["username"])) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';

$conn = get_db_connection();

// Fetch current user's details
$username = $_SESSION["username"];
$stmt = $conn->prepare("SELECT id, rank_perms FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($current_user_id, $rank_perms);
$stmt->fetch();
$stmt->close();

// Check if user is admin
if ($rank_perms != 1) {
    $_SESSION['error_message'] = "Access denied! Only Owners can edit users.";
    header("Location: /dash");
    exit();
}

$user_to_edit = null;
if (isset($_GET['user_id'])) {
    $edit_user_id = (int)$_GET['user_id'];
    $stmt = $conn->prepare("SELECT id, username, email, max_clients, rank_perms, fitness_goals, protein, carbs, fats FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_to_edit = $row;
    } else {
        $_SESSION['error_message'] = "User not found!";
        header("Location: /dash");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "No user ID provided!";
    header("Location: /dash");
    exit();
}

// Handle form submission (Update user details including password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update"])) {
    $new_username = trim($_POST["username"]);
    $new_email = trim($_POST["email"]);
    $new_password = trim($_POST["password"] ?? '');
    $new_fitness_goals = trim($_POST["fitness_goals"] ?? '');
    $new_protein = !empty($_POST["protein"]) ? (int)$_POST["protein"] : null;
    $new_carbs = !empty($_POST["carbs"]) ? (int)$_POST["carbs"] : null;
    $new_fats = !empty($_POST["fats"]) ? (int)$_POST["fats"] : null;
    $new_max_clients = (int)$_POST["max_clients"];
    $new_rank_perms = (int)$_POST["rank_perms"];

    // Check for duplicate username or email
    $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->bind_param("ssi", $new_username, $new_email, $edit_user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['error_message'] = "Username or email already taken!";
    } else {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET username = ?, email = ?, password = ?, fitness_goals = ?, protein = ?, carbs = ?, fats = ?, max_clients = ?, rank_perms = ? WHERE id = ?";
            $params = [$new_username, $new_email, $hashed_password, $new_fitness_goals, $new_protein, $new_carbs, $new_fats, $new_max_clients, $new_rank_perms, $edit_user_id];
            $types = "ssssiiiiii";
        } else {
            $query = "UPDATE users SET username = ?, email = ?, fitness_goals = ?, protein = ?, carbs = ?, fats = ?, max_clients = ?, rank_perms = ? WHERE id = ?";
            $params = [$new_username, $new_email, $new_fitness_goals, $new_protein, $new_carbs, $new_fats, $new_max_clients, $new_rank_perms, $edit_user_id];
            $types = "sssiiiiii";
        }

        $update = $conn->prepare($query);
        $update->bind_param($types, ...$params);
        if ($update->execute()) {
            $_SESSION['success_message'] = "Profile updated successfully!";
            // Update the user_to_edit array to reflect changes
            $user_to_edit['username'] = $new_username;
            $user_to_edit['email'] = $new_email;
            $user_to_edit['fitness_goals'] = $new_fitness_goals;
            $user_to_edit['protein'] = $new_protein;
            $user_to_edit['carbs'] = $new_carbs;
            $user_to_edit['fats'] = $new_fats;
            $user_to_edit['max_clients'] = $new_max_clients;
            $user_to_edit['rank_perms'] = $new_rank_perms;
        } else {
            $_SESSION['error_message'] = "Error updating user: " . $conn->error;
        }
        $update->close();
    }
    $check->close();
}

// Handle prescribing a workout
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["prescribe_workout"])) {
    $exercise_id = (int)$_POST["exercise_id"];
    $day_of_week = $_POST["day_of_week"];
    $sets = $_POST["sets"] ?? [];

    // Validate exercise_id exists in the exercises table
    $check = $conn->prepare("SELECT id FROM exercises WHERE id = ?");
    $check->bind_param("i", $exercise_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid exercise selected. The exercise does not exist.";
    } else {
        $stmt = $conn->prepare("INSERT INTO prescribed_workouts (user_id, exercise_id, day_of_week) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $edit_user_id, $exercise_id, $day_of_week);
        if ($stmt->execute()) {
            $prescribed_workout_id = $stmt->insert_id;

            foreach ($sets as $index => $set) {
                $reps = trim($set["reps"]);
                $weight = !empty($set["weight"]) ? (float)$set["weight"] : null;
                $set_number = $index + 1;

                if (!preg_match('/^\d+$|^\d+-\d+$/', $reps)) {
                    $_SESSION['error_message'] = "Invalid reps format for set $set_number. Use single digits (e.g., 10) or ranges (e.g., 10-12).";
                    break;
                }

                $set_stmt = $conn->prepare("INSERT INTO prescribed_working_sets (prescribed_workout_id, set_number, prescribed_reps, prescribed_weight) VALUES (?, ?, ?, ?)");
                $set_stmt->bind_param("iisd", $prescribed_workout_id, $set_number, $reps, $weight);
                if (!$set_stmt->execute()) {
                    $_SESSION['error_message'] = "Error adding set $set_number: " . $set_stmt->error;
                    break;
                }
                $set_stmt->close();
            }
            if (!isset($_SESSION['error_message'])) {
                $_SESSION['success_message'] = "Workout added successfully!";
            }
        } else {
            $_SESSION['error_message'] = "Error adding workout: " . $stmt->error;
        }
        $stmt->close();
    }
    $check->close();
}

// Handle delete user request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete"])) {
    if ($edit_user_id == $current_user_id) {
        $_SESSION['error_message'] = "You cannot delete your own account!";
    } else {
        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $edit_user_id);
        if ($delete->execute()) {
            $_SESSION['success_message'] = "User deleted successfully! Redirecting...";
            header("Refresh: 2; url=/dash");
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
        }
        $delete->close();
    }
}

// Handle delete workout request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_workout"])) {
    $workout_id = (int)$_POST["workout_id"];
    $stmt = $conn->prepare("DELETE FROM prescribed_working_sets WHERE prescribed_workout_id = ?");
    $stmt->bind_param("i", $workout_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM prescribed_workouts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $workout_id, $edit_user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Exercise deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting exercise: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch workouts for selected day (if provided)
$selected_day = $_POST["selected_day"] ?? '';
$workouts = [];
if ($selected_day) {
    $stmt = $conn->prepare("
        SELECT pw.id, e.name
        FROM prescribed_workouts pw
        JOIN exercises e ON pw.exercise_id = e.id
        WHERE pw.user_id = ? AND pw.day_of_week = ?
    ");
    $stmt->bind_param("is", $edit_user_id, $selected_day);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $workouts[] = $row;
    }
    $stmt->close();
}

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
        /* Match the styling from dash.php */
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
            background: #1a73e8;
            color: white;
            padding: 20px;
            box-sizing: border-box;
        }
        .sidebar-logo img {
            max-width: 100%;
            height: auto;
        }
        .sidebar-icons {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .sidebar-icons i {
            font-size: 1.5em;
            cursor: pointer;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 5px;
        }
        .sidebar-item.active {
            background: #1557b0;
        }
        .sidebar-item i {
            margin-right: 10px;
        }
        .sidebar-item.has-submenu .dropdown-icon {
            margin-left: auto;
        }
        .sidebar-dropdown {
            display: none;
            margin-left: 20px;
        }
        .sidebar-subitem {
            padding: 5px 0;
            cursor: pointer;
        }
        .sidebar-item.active .sidebar-dropdown {
            display: block;
        }
        .dashboard-container {
            flex: 1;
            padding: 20px;
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
        }
        .success, .error {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .edit-container, .workout-container {
            flex: 1;
            min-width: 300px;
        }
        .edit-container h2, .workout-container h2 {
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        .edit-container h3 {
            font-size: 1.2em;
            margin: 20px 0 10px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .edit-container input, .edit-container select, .edit-container textarea,
        .workout-container select {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .edit-container textarea {
            resize: vertical;
            min-height: 50px;
        }
        .edit-container button, .workout-container button {
            width: 100%;
            padding: 8px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .edit-container button:hover, .workout-container button:hover {
            background-color: #1557b0;
        }
        .edit-container .delete-btn {
            background-color: #dc3545;
        }
        .edit-container .delete-btn:hover {
            background-color: #c82333;
        }
        .edit-container .back {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #1a73e8;
            text-decoration: none;
        }
        .edit-container .back:hover {
            text-decoration: underline;
        }
        .working-set {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .working-set label {
            color: #666;
        }
        .working-set .remove-set {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 5px;
        }
        .working-set .remove-set:hover {
            background-color: #c82333;
        }
        .add-set {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .add-set:hover {
            background-color: #218838;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 300px;
            width: 90%;
            text-align: center;
        }
        .modal-content h3 {
            margin: 0 0 15px;
            color: #333;
        }
        .modal-content p {
            margin: 0 0 20px;
            color: #666;
        }
        .modal-content button {
            width: 45%;
            display: inline-block;
            margin: 0 5px;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .modal-content .confirm-btn {
            background-color: #dc3545;
            color: white;
        }
        .modal-content .confirm-btn:hover {
            background-color: #c82333;
        }
        .modal-content .cancel-btn {
            background-color: #ccc;
            color: #333;
        }
        .modal-content .cancel-btn:hover {
            background-color: #b3b3b3;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            animation: slideIn 0.5s ease-in-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .workout-list {
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        .workout-item {
            padding: 8px 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .workout-item button {
            background-color: #dc3545;
            padding: 5px 10px;
            width: auto;
        }
        .workout-item button:hover {
            background-color: #c82333;
        }
        @media (min-width: 768px) {
            .dashboard-wrapper {
                flex-direction: row;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 250px;
                height: 100vh;
            }
            .dashboard-container {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            .container {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }
            .edit-container, .workout-container {
                flex: 1;
                min-width: 400px;
            }
        }
        @media (max-width: 767px) {
            .container {
                flex-direction: column;
                gap: 10px;
            }
            .edit-container, .workout-container {
                min-width: 100%;
            }
            .edit-container input, .edit-container select, .edit-container textarea,
            .workout-container select {
                padding: 10px;
            }
            .edit-container button, .workout-container button {
                padding: 10px;
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
            <?php if ($rank_perms == 1 || $rank_perms == 2): ?>
                <div class="sidebar-item has-submenu active">
                    <i class="fas fa-cog"></i>
                    <span>Manage</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                    <div class="sidebar-dropdown" style="display: block;">
                        <div class="sidebar-subitem" onclick="window.location.href='/checkins'"><span>Check-in</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/gallery'"><span>Gallery</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/qa'"><span>Q&A</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/nutrition'"><span>Nutrition</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/supplements'"><span>Supplements</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/workouts'"><span>Workout</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/calendar'"><span>Calendar</span></div>
                        <div class="sidebar-subitem" onclick="window.location.href='/logs'"><span>Logs</span></div>
                        <?php if ($rank_perms == 2): ?>
                            <div class="sidebar-subitem" onclick="window.location.href='/manage-clients'"><span>Manage Clients</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="sidebar-item" onclick="window.location.href='/logout'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </nav>

        <div class="dashboard-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="notification" class="notification"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="container">
                <div class="edit-container">
                    <h2>Edit <?php echo htmlspecialchars($user_to_edit['username']); ?>'s Profile</h2>
                    <form method="post" action="/edit-user?user_id=<?php echo $edit_user_id; ?>" id="editForm">
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" placeholder="Username" required>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" placeholder="Email" required>
                        <input type="password" name="password" placeholder="New Password (leave blank to keep current)" minlength="8">
                        <textarea name="fitness_goals" placeholder="Fitness Goals (e.g., Lose 10 lbs)"><?php echo htmlspecialchars($user_to_edit['fitness_goals'] ?? ''); ?></textarea>
                        <input type="number" name="protein" value="<?php echo htmlspecialchars($user_to_edit['protein'] ?? ''); ?>" placeholder="Daily Protein (grams)" min="0">
                        <input type="number" name="carbs" value="<?php echo htmlspecialchars($user_to_edit['carbs'] ?? ''); ?>" placeholder="Daily Carbs (grams)" min="0">
                        <input type="number" name="fats" value="<?php echo htmlspecialchars($user_to_edit['fats'] ?? ''); ?>" placeholder="Daily Fats (grams)" min="0">
                        <select name="max_clients" required>
                            <option value="25" <?php echo $user_to_edit['max_clients'] == 25 ? 'selected' : ''; ?>>25 Clients</option>
                            <option value="50" <?php echo $user_to_edit['max_clients'] == 50 ? 'selected' : ''; ?>>50 Clients</option>
                            <option value="200" <?php echo $user_to_edit['max_clients'] == 200 ? 'selected' : ''; ?>>200 Clients</option>
                        </select>
                        <select name="rank_perms" required>
                            <option value="1" <?php echo $user_to_edit['rank_perms'] == 1 ? 'selected' : ''; ?>>Admin</option>
                            <option value="2" <?php echo $user_to_edit['rank_perms'] == 2 ? 'selected' : ''; ?>>Coach</option>
                            <option value="3" <?php echo $user_to_edit['rank_perms'] == 3 ? 'selected' : ''; ?>>Client</option>
                        </select>
                        <button type="submit" name="update">Save Changes</button>
                        <button type="button" class="delete-btn" id="deleteBtn">Delete User</button>
                    </form>

                    <h3>Add a Workout</h3>
                    <form method="post" action="/edit-user?user_id=<?php echo $edit_user_id; ?>" id="prescribeForm">
                        <select name="exercise_id" required onchange="this.form.querySelector('button[type=submit]').disabled = this.value === '';">
                            <option value="">Choose an Exercise</option>
                            <?php
                            $exercises = $conn->query("SELECT id, name FROM exercises");
                            while ($exercise = $exercises->fetch_assoc()) {
                                echo "<option value='{$exercise['id']}'>" . htmlspecialchars($exercise['name']) . "</option>";
                            }
                            $exercises->free();
                            ?>
                        </select>
                        <select name="day_of_week" required>
                            <option value="">Select a Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <div id="workingSets">
                            <div class="working-set">
                                <label>Working Set 1</label>
                                <input type="text" name="sets[0][reps]" placeholder="Reps (e.g., 10 or 10-12)" required>
                                <input type="number" name="sets[0][weight]" placeholder="Weight (kg, optional)" min="0" step="0.01">
                            </div>
                        </div>
                        <button type="button" class="add-set" onclick="addWorkingSet()">Add Another Set</button>
                        <button type="submit" name="prescribe_workout">Add Workout</button>
                    </form>

                                       <a href="/dash" class="back">Back to Dashboard</a>

                    <div class="modal" id="deleteModal">
                        <div class="modal-content">
                            <h3>Confirm Deletion</h3>
                            <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                            <button class="confirm-btn" id="confirmDelete">Yes, Delete</button>
                            <button class="cancel-btn" id="cancelDelete">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="workout-container">
                    <h2>Workout Records</h2>
                    <form method="post" action="/edit-user?user_id=<?php echo $edit_user_id; ?>" id="daySelectForm">
                        <select name="selected_day" onchange="this.form.submit()">
                            <option value="">Select a Day</option>
                            <option value="Monday" <?php echo $selected_day === 'Monday' ? 'selected' : ''; ?>>Monday</option>
                            <option value="Tuesday" <?php echo $selected_day === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                            <option value="Wednesday" <?php echo $selected_day === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                            <option value="Thursday" <?php echo $selected_day === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                            <option value="Friday" <?php echo $selected_day === 'Friday' ? 'selected' : ''; ?>>Friday</option>
                            <option value="Saturday" <?php echo $selected_day === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                            <option value="Sunday" <?php echo $selected_day === 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                        </select>
                    </form>
                    <?php if ($selected_day && !empty($workouts)): ?>
                        <div class="workout-list">
                            <?php foreach ($workouts as $workout): ?>
                                <div class="workout-item">
                                    <span><?php echo htmlspecialchars($workout['name']); ?></span>
                                    <form method="post" action="/edit-user?user_id=<?php echo $edit_user_id; ?>">
                                        <input type="hidden" name="workout_id" value="<?php echo $workout['id']; ?>">
                                        <input type="hidden" name="selected_day" value="<?php echo $selected_day; ?>">
                                        <button type="submit" name="delete_workout">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($selected_day): ?>
                        <p style="text-align: center; color: #666; font-size: 13px;">No workouts for this day.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php $conn->close(); ?>

    <script>
        // Modal for deleting user
        const deleteBtn = document.getElementById('deleteBtn');
        const deleteModal = document.getElementById('deleteModal');
        const confirmDelete = document.getElementById('confirmDelete');
        const cancelDelete = document.getElementById('cancelDelete');
        const form = document.getElementById('editForm');

        deleteBtn.addEventListener('click', function() {
            deleteModal.style.display = 'flex';
        });

        cancelDelete.addEventListener('click', function() {
            deleteModal.style.display = 'none';
        });

        confirmDelete.addEventListener('click', function() {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete';
            input.value = 'delete';
            form.appendChild(input);
            form.submit();
        });

        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });

        // Working sets management
        let setCount = 1;

        function addWorkingSet() {
            if (setCount >= 3) {
                alert("You can add up to 3 working sets. Edit the workout on the dashboard for more.");
                return;
            }
            const container = document.getElementById('workingSets');
            const setDiv = document.createElement('div');
            setDiv.className = 'working-set';
            setDiv.innerHTML = `
                <label>Working Set ${setCount + 1}</label>
                <input type="text" name="sets[${setCount}][reps]" placeholder="Reps (e.g., 10 or 10-12)" required>
                <input type="number" name="sets[${setCount}][weight]" placeholder="Weight (kg, optional)" min="0" step="0.01">
                <button type="button" class="remove-set" onclick="removeWorkingSet(this)">Remove Set</button>
            `;
            container.appendChild(setDiv);
            setCount++;
        }

        function removeWorkingSet(button) {
            button.parentElement.remove();
            const container = document.getElementById('workingSets');
            const sets = container.querySelectorAll('.working-set');
            sets.forEach((set, index) => {
                set.querySelector('label').textContent = `Working Set ${index + 1}`;
                set.querySelectorAll('input').forEach(input => {
                    const name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    input.name = name;
                });
            });
            setCount = sets.length;
        }

        // Notification display
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000);
            }
        });
    </script>
</body>
</html>