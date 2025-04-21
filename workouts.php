<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION["username"])) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';
require_once 'stripe_config.php';

// Database connection
$conn = get_db_connection();

// Fetch current user's details
$username = $_SESSION["username"];
$stmt = $conn->prepare("SELECT id, rank_perms, unit_preference FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($current_user_id, $rank_perms, $unit_preference);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();

$unit_preference = $unit_preference ?? 'kg';

// Determine the user to view (admin can view others)
$view_user_id = isset($_GET['user_id']) && $rank_perms == 1 ? (int)$_GET['user_id'] : $current_user_id;

// Fetch viewed user's details
$stmt = $conn->prepare("SELECT id, username, email, unit_preference, stripe_customer_id FROM users WHERE id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$stmt->bind_result($user_id, $view_username, $email, $view_unit_preference, $stripe_customer_id);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();
$view_unit_preference = $view_unit_preference ?? 'kg';

// Fetch subscription status
$subscription_status = 'inactive';
$stripe_subscription_id = null;
$pending_plan_id = null;

$stmt = $conn->prepare("SELECT stripe_subscription_id, status, plan_id FROM subscriptions WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$stmt->bind_result($stripe_subscription_id, $subscription_status, $pending_plan_id);
$stmt->fetch();
$stmt->close();

// [Keep all workout-related PHP handlers unchanged: unit preference update, workout logging, prescribing, editing, deleting]

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workouts - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'dash.php' ? 'active' : ''; ?>" onclick="window.location.href='/dash'">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </div>
            <div class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'workouts.php' ? 'active' : ''; ?>" onclick="window.location.href='/workouts'">
                <i class="fas fa-dumbbell"></i>
                <span>Workouts</span>
            </div>
            <?php if ($rank_perms == 1): ?>
                <div class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'manage.php' ? 'active' : ''; ?>" onclick="window.location.href='/manage'">
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
            <h1>Your Workouts</h1>
            
            <div class="card">
                <h2>Unit Preference</h2>
                <form method="post" action="/workouts<?php echo $rank_perms == 1 && $view_user_id != $current_user_id ? "?user_id=$view_user_id" : ''; ?>">
                    <select name="unit_preference" onchange="this.form.submit()">
                        <option value="kg" <?php echo $unit_preference === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                        <option value="lbs" <?php echo $unit_preference === 'lbs' ? 'selected' : ''; ?>>Pounds (lbs)</option>
                    </select>
                    <input type="hidden" name="update_units" value="1">
                </form>
            </div>

            <div class="card">
                <?php
                $has_workouts = false;
                $stmt = $conn->prepare("SELECT COUNT(*) FROM prescribed_workouts WHERE user_id = ?");
                $stmt->bind_param("i", $view_user_id);
                $stmt->execute();
                $stmt->bind_result($workout_count);
                $stmt->fetch();
                $stmt->close();
                if ($workout_count > 0) {
                    $has_workouts = true;
                }
                ?>
                <?php if ($has_workouts && ($subscription_status === 'paid' || $subscription_status === 'friends_family' || $rank_perms == 1)): ?>
                    <div class="day-selector">
                        <select id="daySelect" onchange="navigateToDay(this.value)">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $selected_day = isset($_GET['day']) && in_array($_GET['day'], $days) ? $_GET['day'] : 'Monday';
                            foreach ($days as $day) {
                                $selected = $day === $selected_day ? 'selected' : '';
                                echo "<option value='$day' $selected>$day</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="workout-table">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT pw.id, e.name, e.description
                            FROM prescribed_workouts pw
                            JOIN exercises e ON pw.exercise_id = e.id
                            WHERE pw.user_id = ? AND pw.day_of_week = ?
                            ORDER BY pw.id ASC
                        ");
                        $stmt->bind_param("is", $user_id, $selected_day);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $exercise_number = 1;
                            while ($row = $result->fetch_assoc()) {
                                $prescribed_workout_id = $row['id'];
                                echo "<div class='exercise-entry'>";
                                echo "<h3>Exercise $exercise_number: " . htmlspecialchars($row['name']) . "</h3>";
                                echo "<p><strong>Description:</strong> " . htmlspecialchars($row['description'] ?? 'No description available') . "</p>";

                                $set_stmt = $conn->prepare("
                                    SELECT id, set_number, prescribed_reps, prescribed_weight
                                    FROM prescribed_working_sets
                                    WHERE prescribed_workout_id = ?
                                    ORDER BY set_number
                                ");
                                $set_stmt->bind_param("i", $prescribed_workout_id);
                                $set_stmt->execute();
                                $set_result = $set_stmt->get_result();

                                echo "<p><strong>Prescribed:</strong> ";
                                $working_sets = [];
                                while ($set_row = $set_result->fetch_assoc()) {
                                    $working_sets[$set_row['id']] = $set_row;
                                    echo "<span class='working-set'>Set {$set_row['set_number']}: {$set_row['prescribed_reps']} reps</span> ";
                                }
                                echo "</p>";
                                $set_result->free();
                                $set_stmt->close();

                                $set_stmt = $conn->prepare("
                                    SELECT pws.set_number, wl.logged_reps, wl.logged_weight, wl.logged_at
                                    FROM prescribed_working_sets pws
                                    LEFT JOIN workout_logs wl ON pws.id = wl.prescribed_working_set_id
                                    WHERE pws.prescribed_workout_id = ?
                                    ORDER BY pws.set_number, wl.logged_at DESC
                                ");
                                $set_stmt->bind_param("i", $prescribed_workout_id);
                                $set_stmt->execute();
                                $set_result = $set_stmt->get_result();

                                $logs_by_set = [];
                                while ($set_row = $set_result->fetch_assoc()) {
                                    if ($set_row['logged_at']) {
                                        $set_number = $set_row['set_number'];
                                        if (!isset($logs_by_set[$set_number])) {
                                            $logs_by_set[$set_number] = $set_row;
                                        }
                                    }
                                }
                                $set_result->free();
                                $set_stmt->close();

                                if (!empty($logs_by_set)) {
                                    echo "<p><strong>Latest Logs:</strong> ";
                                    foreach ($logs_by_set as $set_number => $log) {
                                        $display_weight = $log['logged_weight'];
                                        if ($view_unit_preference === 'lbs' && $display_weight !== null) {
                                            $display_weight = round($display_weight / 0.453592, 2);
                                        }
                                        echo "<span class='working-set'>Set $set_number: {$log['logged_reps']} reps" . ($display_weight !== null ? " @ $display_weight $view_unit_preference" : "") . " (" . substr($log['logged_at'], 0, 10) . ")</span> ";
                                    }
                                    echo "</p>";
                                }

                                $action_url = "/workouts?day=$selected_day";
                                if ($rank_perms == 1 && $view_user_id != $current_user_id) {
                                    $action_url .= "&user_id=$view_user_id";
                                }
                                echo "<form method='post' action='$action_url'>";
                                echo "<input type='hidden' name='prescribed_workout_id' value='$prescribed_workout_id'>";
                                foreach ($working_sets as $set_id => $set) {
                                    echo "<div class='working-set'>";
                                    echo "Set {$set['set_number']}: ";
                                    echo "<input type='number' name='logs[$set_id][reps]' placeholder='Reps' min='1'>";
                                    echo "<input type='number' name='logs[$set_id][weight]' placeholder='$view_unit_preference' min='0' step='0.01'>";
                                    echo "</div>";
                                }
                                echo "<button type='submit' name='log_workout'>Log</button>";
                                echo "</form>";

                                if ($rank_perms == 1 && $view_user_id != $current_user_id) {
                                    echo "<form method='post' action='/workouts?day=$selected_day&user_id=$view_user_id' class='edit-form'>";
                                    echo "<input type='hidden' name='prescribed_workout_id' value='$prescribed_workout_id'>";
                                    echo "<select name='exercise_id' required>";
                                    echo "<option value=''>Exercise</option>";
                                    $exercises = $conn->query("SELECT id, name FROM exercises");
                                    while ($exercise = $exercises->fetch_assoc()) {
                                        $selected = $exercise['id'] == $row['exercise_id'] ? 'selected' : '';
                                        echo "<option value='{$exercise['id']}' $selected>" . htmlspecialchars($exercise['name']) . "</option>";
                                    }
                                    $exercises->free();
                                    echo "</select>";
                                    echo "<select name='day_of_week' required>";
                                    echo "<option value=''>Day</option>";
                                    foreach ($days as $d) {
                                        $selected = $d == $selected_day ? 'selected' : '';
                                        echo "<option value='$d' $selected>$d</option>";
                                    }
                                    echo "</select>";
                                    $set_stmt = $conn->prepare("SELECT set_number, prescribed_reps, prescribed_weight FROM prescribed_working_sets WHERE prescribed_workout_id = ? ORDER BY set_number");
                                    $set_stmt->bind_param("i", $prescribed_workout_id);
                                    $set_stmt->execute();
                                    $set_result = $set_stmt->get_result();
                                    echo "<div class='working-sets' data-workout-id='$prescribed_workout_id'>";
                                    $set_index = 0;
                                    while ($set_row = $set_result->fetch_assoc()) {
                                        $display_weight = $set_row['prescribed_weight'];
                                        if ($view_unit_preference === 'lbs' && $display_weight !== null) {
                                            $display_weight = round($display_weight / 0.453592, 2);
                                        }
                                        echo "<div class='working-set'>";
                                        echo "<input type='number' name='sets[$set_index][reps]' value='{$set_row['prescribed_reps']}' placeholder='Reps' min='1' required>";
                                        echo "<input type='number' name='sets[$set_index][weight]' value='$display_weight' placeholder='$view_unit_preference' min='0' step='0.01'>";
                                        echo "<button type='button' class='remove-set' onclick='removeWorkingSet(this)'>Remove</button>";
                                        echo "</div>";
                                        $set_index++;
                                    }
                                    $set_result->free();
                                    $set_stmt->close();
                                    echo "</div>";
                                    echo "<button type='button' class='add-set' onclick='addWorkingSet(this, $prescribed_workout_id)'>Add Set</button>";
                                    echo "<button type='submit' name='edit_workout'>Update</button>";
                                    echo "<button type='submit' name='delete_workout' class='delete-btn'>Delete</button>";
                                    echo "</form>";
                                }
                                echo "</div>";
                                $exercise_number++;
                            }
                        } else {
                            echo "<p>No workouts prescribed for $selected_day.</p>";
                        }
                        $result->free();
                        $stmt->close();
                        ?>
                    </div>
                <?php elseif ($has_workouts): ?>
                    <h2>Access Denied</h2>
                    <p>You need an active subscription to view workout content. Please complete your payment setup.</p>
                <?php else: ?>
                    <p>Programs take roughly a week to prepare. I’m working on yours now!</p>
                <?php endif; ?>
            </div>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success" id="successMessage"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>
    </div>
<!-- Bottom Navigation for Mobile -->
<nav class="bottom-nav">
    <a href="/dash" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dash.php' ? 'active' : ''; ?>" data-tab="profile">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
    <a href="/workouts" class="<?php echo basename($_SERVER['PHP_SELF']) === 'workouts.php' ? 'active' : ''; ?>" data-tab="workouts">
        <i class="fas fa-dumbbell"></i>
        <span>Workouts</span>
    </a>
    <?php if ($rank_perms == 1): ?>
        <a href="/manage" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage.php' ? 'active' : ''; ?>" data-tab="manage">
            <i class="fas fa-cog"></i>
            <span>Manage</span>
        </a>
    <?php endif; ?>
    <a href="/logout" class="<?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>" data-tab="logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</nav>

    <?php
    require_once 'includes/footer.php';
    $conn->close();
    ?>

    <script>
    window.unitPreference = '<?php echo $view_unit_preference; ?>';
    const viewUserId = '<?php echo $rank_perms == 1 && $view_user_id != $current_user_id ? "&user_id=$view_user_id" : ""; ?>';

    function addWorkingSet(button, workoutId = null) {
        const container = button.previousElementSibling;
        const index = container.children.length;
        const setDiv = document.createElement('div');
        setDiv.className = 'working-set';
        setDiv.innerHTML = `
            <input type="number" name="sets[${index}][reps]" placeholder="Reps" min="1" required>
            <input type="number" name="sets[${index}][weight]" placeholder="${window.unitPreference}" min="0" step="0.01">
            <button type="button" class="remove-set" onclick="removeWorkingSet(this)">Remove</button>
        `;
        container.appendChild(setDiv);
    }

    function removeWorkingSet(button) {
        button.parentElement.remove();
    }

    function navigateToDay(day) {
        const url = `/workouts?day=${day}${viewUserId}`;
        window.location.href = url;
    }
    </script>
</body>
</html>