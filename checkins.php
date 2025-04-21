<?php
session_start();

// Redirect if not logged in or not a rank owner
if (!isset($_SESSION["username"]) || $_SESSION["rank_perms"] != 1) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';

// Database connection
$conn = get_db_connection();

// Fetch current user's details
$username = $_SESSION["username"];
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($current_user_id);
$stmt->fetch();
$stmt->close();

// Handle form submission for creating/updating check-in forms
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_form"])) {
    $form_type = $_POST["form_type"];
    $fields = json_encode($_POST["fields"]); // JSON encode the form fields

    // Check if a form already exists for this type
    $stmt = $conn->prepare("SELECT id FROM checkin_form_templates WHERE form_type = ? AND created_by = ?");
    $stmt->bind_param("si", $form_type, $current_user_id);
    $stmt->execute();
    $stmt->bind_result($form_id);
    $exists = $stmt->fetch();
    $stmt->close();

    if ($exists) {
        // Update existing form
        $stmt = $conn->prepare("UPDATE checkin_form_templates SET form_fields = ? WHERE id = ?");
        $stmt->bind_param("si", $fields, $form_id);
    } else {
        // Create new form
        $stmt = $conn->prepare("INSERT INTO checkin_form_templates (form_type, created_by, form_fields) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $form_type, $current_user_id, $fields);
    }

    if ($stmt->execute()) {
        $success = "Check-in form saved successfully!";
    } else {
        $error = "Error saving check-in form: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch existing forms
$daily_form = [];
$weekly_form = [];
$stmt = $conn->prepare("SELECT form_type, form_fields FROM checkin_form_templates WHERE created_by = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['form_type'] === 'daily') {
        $daily_form = json_decode($row['form_fields'], true);
    } elseif ($row['form_type'] === 'weekly') {
        $weekly_form = json_decode($row['form_fields'], true);
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Check-in Forms - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="sidebar-item" onclick="window.location.href='/dash'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/checkins'">
                <i class="fas fa-check-circle"></i>
                <span>Checkins</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/gallery'">
                <i class="fas fa-image"></i>
                <span>Gallery</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/qa'">
                <i class="fas fa-question-circle"></i>
                <span>Q&A</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/nutrition'">
                <i class="fas fa-utensils"></i>
                <span>Nutrition</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/supplements'">
                <i class="fas fa-capsules"></i>
                <span>Supplements</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/workouts'">
                <i class="fas fa-dumbbell"></i>
                <span>Workout</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/calendar'">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/logs'">
                <i class="fas fa-file-alt"></i>
                <span>Logs</span>
            </div>
            <div class="sidebar-item active" onclick="window.location.href='/manage'">
                <i class="fas fa-cog"></i>
                <span>Manage</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/logout'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </nav>

        <div class="dashboard-container">
            <h1>Manage Check-in Forms</h1>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Daily Check-in Form Editor -->
            <div class="card">
                <h3>Daily Check-in Form</h3>
                <form method="post" id="daily-checkin-form">
                    <input type="hidden" name="form_type" value="daily">
                    <div id="daily-fields">
                        <?php if (!empty($daily_form)): ?>
                            <?php foreach ($daily_form as $index => $field): ?>
                                <div class="form-group field-group">
                                    <label>Field Name:</label>
                                    <input type="text" name="fields[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                                    <label>Field Type:</label>
                                    <select name="fields[<?php echo $index; ?>][type]" class="field-type">
                                        <option value="number" <?php echo $field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                        <option value="rating" <?php echo $field['type'] === 'rating' ? 'selected' : ''; ?>>Rating (1-10)</option>
                                        <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                    </select>
                                    <button type="button" class="remove-field btn btn-danger">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-daily-field" class="btn btn-secondary">Add Field</button>
                    <button type="submit" name="save_form" class="btn btn-primary">Save Daily Form</button>
                </form>
            </div>

            <!-- Weekly Check-in Form Editor -->
            <div class="card">
                <h3>Weekly Check-in Form</h3>
                <form method="post" id="weekly-checkin-form">
                    <input type="hidden" name="form_type" value="weekly">
                    <div id="weekly-fields">
                        <?php if (!empty($weekly_form)): ?>
                            <?php foreach ($weekly_form as $index => $field): ?>
                                <div class="form-group field-group">
                                    <label>Field Name:</label>
                                    <input type="text" name="fields[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($field['name']); ?>" required>
                                    <label>Field Type:</label>
                                    <select name="fields[<?php echo $index; ?>][type]" class="field-type">
                                        <option value="number" <?php echo $field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                        <option value="rating" <?php echo $field['type'] === 'rating' ? 'selected' : ''; ?>>Rating (1-10)</option>
                                        <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                    </select>
                                    <button type="button" class="remove-field btn btn-danger">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-weekly-field" class="btn btn-secondary">Add Field</button>
                    <button type="submit" name="save_form" class="btn btn-primary">Save Weekly Form</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    let dailyFieldIndex = <?php echo !empty($daily_form) ? count($daily_form) : 0; ?>;
    let weeklyFieldIndex = <?php echo !empty($weekly_form) ? count($weekly_form) : 0; ?>;

    document.getElementById('add-daily-field').addEventListener('click', function() {
        const fieldsContainer = document.getElementById('daily-fields');
        const fieldHtml = `
            <div class="form-group field-group">
                <label>Field Name:</label>
                <input type="text" name="fields[${dailyFieldIndex}][name]" required>
                <label>Field Type:</label>
                <select name="fields[${dailyFieldIndex}][type]" class="field-type">
                    <option value="number">Number</option>
                    <option value="rating">Rating (1-10)</option>
                    <option value="text">Text</option>
                </select>
                <button type="button" class="remove-field btn btn-danger">Remove</button>
            </div>`;
        fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        dailyFieldIndex++;
    });

    document.getElementById('add-weekly-field').addEventListener('click', function() {
        const fieldsContainer = document.getElementById('weekly-fields');
        const fieldHtml = `
            <div class="form-group field-group">
                <label>Field Name:</label>
                <input type="text" name="fields[${weeklyFieldIndex}][name]" required>
                <label>Field Type:</label>
                <select name="fields[${weeklyFieldIndex}][type]" class="field-type">
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
    </script>
</body>
</html>