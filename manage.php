<?php
session_start();

// Ensure session is tied to the correct user
if (!isset($_SESSION["username"])) {
    header("Location: /login");
    exit();
}

$conn = new mysqli("localhost", "root", "lol_123", "strv_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
$stmt = $conn->prepare("SELECT id, username, email, fitness_goals, protein, carbs, fats, unit_preference FROM users WHERE id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$stmt->bind_result($user_id, $view_username, $email, $fitness_goals, $protein, $carbs, $fats, $view_unit_preference);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();
$view_unit_preference = $view_unit_preference ?? 'kg';

// Restrict access to owner rank only
if ($rank_perms != 1) {
    header("Location: /dash");
    exit();
}

// Handle adding a new exercise
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_exercise"])) {
    $exercise_name = trim($_POST["exercise_name"]);
    $exercise_category = $_POST["exercise_category"];
    if (!empty($exercise_name)) {
        $stmt = $conn->prepare("INSERT INTO exercises (name, category) VALUES (?, ?)");
        $stmt->bind_param("ss", $exercise_name, $exercise_category);
        if ($stmt->execute()) {
            $success = "Exercise added successfully!";
        } else {
            $error = "Error adding exercise: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Exercise name cannot be empty.";
    }
}

// Handle editing an exercise
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_exercise"])) {
    $exercise_id = (int)$_POST["exercise_id"];
    $exercise_name = trim($_POST["exercise_name"]);
    $exercise_category = $_POST["exercise_category"];
    if (!empty($exercise_name)) {
        $stmt = $conn->prepare("UPDATE exercises SET name = ?, category = ? WHERE id = ?");
        $stmt->bind_param("ssi", $exercise_name, $exercise_category, $exercise_id);
        if ($stmt->execute()) {
            $success = "Exercise updated successfully!";
        } else {
            $error = "Error updating exercise: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Exercise name cannot be empty.";
    }
}

// Handle deleting an exercise
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_exercise"])) {
    $exercise_id = (int)$_POST["exercise_id"];
    $stmt = $conn->prepare("DELETE FROM exercises WHERE id = ?");
    $stmt->bind_param("i", $exercise_id);
    if ($stmt->execute()) {
        $success = "Exercise deleted successfully!";
    } else {
        $error = "Error deleting exercise: " . $stmt->error;
    }
    $stmt->close();
}

// Handle category filtering
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'Push';
$category_clause = "WHERE category = '$category_filter'";

// Handle updating units
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_units"])) {
    $new_unit = $_POST["unit_preference"];
    $stmt = $conn->prepare("UPDATE users SET unit_preference = ? WHERE id = ?");
    $stmt->bind_param("si", $new_unit, $current_user_id);
    if ($stmt->execute()) {
        $success = "Unit preference updated!";
        $unit_preference = $new_unit;
        if ($view_user_id == $current_user_id) {
            $view_unit_preference = $new_unit;
        }
    } else {
        $error = "Error updating unit preference: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exercises - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --dark: #1a1a1a;
            --gray: #7a7a7a;
            --light: #f5f5f5;
            --white: #ffffff;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --green: #2ecc71;
            --red: #e74c3c;
            --navbar-bg: #ffffff;
            --navbar-hover: #2d3134;
            --navbar-border: #0b0e11;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            min-height: 100vh;
            padding: 2vw;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 2vw;
            max-width: 1200px;
            width: 100%;
            background: var(--white);
            border-radius: 12px;
            padding: 2vw;
            box-shadow: var(--shadow);
        }

        .navbar {
            width: 100%;
            padding: 1vw;
            display: flex;
            justify-content: center;
            gap: 1vw;
            flex-wrap: wrap;
        }

        .navbar a {
            padding: clamp(6px, 1vw, 8px) clamp(12px, 2vw, 16px);
            background: var(--light);
            border: 1px solid var(--gray);
            border-radius: 4px;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            font-weight: 500;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .navbar a:hover, .navbar a.active {
            background: var(--navbar-hover);
            color: var(--white);
            border-color: var(--navbar-border);
        }

        .sidebar, .main-content {
            width: 100%;
        }

        .card {
            padding: 2vw;
            border-radius: 8px;
            overflow-x: auto;
        }

        .logo {
            width: 100%;
            max-width: 150px;
            height: auto;
            margin: 0 auto 2vh;
            display: block;
        }

        h1 {
            font-size: clamp(1.3rem, 3vw, 1.5rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 2vh;
            text-align: center;
        }

        h2 {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5vh;
        }

        h3 {
            font-size: clamp(0.9rem, 2vw, 1rem);
            font-weight: 500;
            color: var(--gray);
            margin-bottom: 1vh;
        }

        p {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: var(--dark);
            margin: 1vh 0;
        }

        .unit-preference select {
            padding: clamp(6px, 1vw, 8px);
            border: none;
            border-radius: 8px;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            width: 100%;
            margin-top: 1vh;
            cursor: pointer;
            background: var(--white);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .unit-preference select:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--primary);
        }

        .tabs {
            display: flex;
            gap: 1vw;
            margin-bottom: 1.5vh;
            overflow-x: auto;
            white-space: nowrap;
            justify-content: center;
        }

        .tab {
            padding: clamp(6px, 1vw, 8px) clamp(12px, 2vw, 16px);
            background: var(--light);
            border: 1px solid var(--gray);
            border-radius: 4px;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab:hover, .tab.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .exercise-table {
            width: 100%;
            font-size: clamp(0.75rem, 2vw, 0.85rem);
            border-collapse: collapse;
            overflow-y: auto;
            max-height: 50vh;
        }

        .exercise-table th, .exercise-table td {
            padding: clamp(8px, 1.5vw, 10px);
            border-bottom: 1px solid var(--light);
            text-align: left;
        }

        .exercise-table th {
            font-weight: 600;
            background: var(--light);
        }

        .exercise-table td form {
            display: flex;
            align-items: center;
            gap: 1vw;
            flex-wrap: wrap;
        }

        input[type="text"], select {
            width: clamp(100px, 20vw, 120px);
            padding: clamp(5px, 1vw, 8px);
            border: 1px solid var(--gray);
            border-radius: 4px;
            font-size: clamp(0.75rem, 2vw, 0.85rem);
            background: var(--light);
            transition: all 0.2s ease;
        }

        input[type="text"]:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--primary);
            background: var(--white);
        }

        button {
            padding: clamp(6px, 1vw, 8px) clamp(10px, 2vw, 12px);
            background: #3a3e41;
            color: var(--white);
            border: none;
            border-radius: 4px;
            font-size: clamp(0.75rem, 2vw, 0.85rem);
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button:hover {
            background: #212426;
        }

        .delete-btn {
            background: var(--red);
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .add-set {
            background: var(--green);
        }

        .add-set:hover {
            background: #27ae60;
        }

        .error, .success {
            padding: 1.5vw;
            margin-bottom: 2vh;
            border-radius: 8px;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            text-align: center;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }

        .error {
            background: #fce4e4;
            color: var(--red);
        }

        .success {
            background: #e8f5e9;
            color: var(--green);
        }

        .success.fade-out {
            opacity: 0;
        }

        .form-group {
            margin-bottom: 2vh;
        }

        .form-group label {
            display: block;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            font-weight: 500;
            color: var(--gray);
            margin-bottom: 0.5vh;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            max-width: 300px;
        }

        .filter-form {
            margin-bottom: 2vh;
            display: flex;
            gap: 1vw;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-form label {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            font-weight: 500;
            color: var(--gray);
        }

        .filter-form select {
            padding: clamp(6px, 1vw, 8px);
            border: 1px solid var(--gray);
            border-radius: 4px;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .action-button {
            padding: clamp(6px, 1vw, 8px) clamp(10px, 2vw, 12px);
            background: #1c577d;
            color: var(--white);
            text-decoration: none;
            border: none;
            border-radius: 4px;
            font-size: clamp(0.75rem, 2vw, 0.85rem);
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .action-button:hover {
            background: #00558d;
        }

        .sidebar-actions {
            margin-top: 2vh;
            text-align: center;
            display: flex;
            flex-wrap: wrap;
            gap: 1vw;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1vw;
                gap: 1vw;
            }
            .card {
                padding: 1vw;
            }
            .navbar {
                padding: 0.5vw;
            }
            .navbar a, .tab {
                padding: clamp(5px, 1vw, 6px) clamp(10px, 2vw, 12px);
                font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            }
            .exercise-table {
                display: block;
                overflow-x: auto;
            }
            .exercise-table th, .exercise-table td {
                padding: clamp(5px, 1vw, 8px);
            }
            .exercise-table td form {
                flex-direction: column;
                align-items: stretch;
            }
            input[type="text"], select {
                width: 100%;
                margin-bottom: 1vh;
            }
            button {
                width: 100%;
                margin-bottom: 1vh;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form select {
                width: 100%;
            }
            .logo {
                max-width: 120px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1vw;
            }
            h1 { font-size: clamp(1.1rem, 2.5vw, 1.2rem); }
            h2 { font-size: clamp(0.9rem, 2vw, 1rem); }
            h3 { font-size: clamp(0.8rem, 1.8vw, 0.9rem); }
            p, input, button, select {
                font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            }
            .exercise-table th, .exercise-table td {
                padding: clamp(4px, 1vw, 5px);
            }
            .form-group input[type="text"],
            .form-group select {
                max-width: 100%;
            }
        }

        @media (min-width: 768px) {
            .card {
                padding: 20px;
            }
            .navbar {
                padding: 10px;
            }
            input[type="text"], select {
                padding: 8px;
            }
            button {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if ($rank_perms == 1): ?>
            <div class="navbar">
                <a href="/dash">Dashboard</a>
                <a href="/manage_exercises" class="active">Manage Exercises</a>
            </div>
        <?php endif; ?>

        <div class="sidebar">
            <img src="/images/logo.png" alt="STRV Fitness Logo" class="logo">
            <h1>Welcome, <?php echo htmlspecialchars($view_username); ?>!</h1>
            <div class="card">
                <h2>Profile</h2>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($view_username); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Fitness Goals:</strong> <?php echo htmlspecialchars($fitness_goals ?? 'Not set'); ?></p>
            </div>
            <div class="card">
                <h2>Macros</h2>
                <p><strong>Protein:</strong> <?php echo $protein !== null ? htmlspecialchars($protein) . 'g' : 'Not set'; ?></p>
                <p><strong>Carbs:</strong> <?php echo $carbs !== null ? htmlspecialchars($carbs) . 'g' : 'Not set'; ?></p>
                <p><strong>Fats:</strong> <?php echo $fats !== null ? htmlspecialchars($fats) . 'g' : 'Not set'; ?></p>
            </div>
            <div class="card">
                <h2>Units</h2>
                <form method="post" action="/manage_exercises<?php echo $rank_perms == 1 && $view_user_id != $current_user_id ? "?user_id=$view_user_id" : ''; ?>">
                    <select name="unit_preference" onchange="this.form.submit()">
                        <option value="kg" <?php echo $unit_preference === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                        <option value="lbs" <?php echo $unit_preference === 'lbs' ? 'selected' : ''; ?>>Pounds (lbs)</option>
                    </select>
                    <input type="hidden" name="update_units" value="1">
                </form>
            </div>
            <div class="sidebar-actions">
                <button onclick="window.location.href='/dash'" class="action-button">My Dashboard</button>
                <button onclick="window.location.href='/logout'" class="action-button">Logout</button>
            </div>
        </div>

        <div class="main-content">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success" id="successMessage"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="card">
                <h1>Manage Exercises</h1>
                <h2>Add New Exercise</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="exercise_name">Exercise Name</label>
                        <input type="text" id="exercise_name" name="exercise_name" required>
                    </div>
                    <div class="form-group">
                        <label for="exercise_category">Category</label>
                        <select id="exercise_category" name="exercise_category" required>
                            <option value="Push">Push</option>
                            <option value="Pull">Pull</option>
                            <option value="Legs">Legs</option>
                            <option value="Core">Core</option>
                        </select>
                    </div>
                    <button type="submit" name="add_exercise">Add Exercise</button>
                </form>
            </div>

            <div class="card">
                <h2>Existing Exercises</h2>
                <div class="filter-form">
                    <form method="get">
                        <label for="category">Filter by Category:</label>
                        <select id="category" name="category" onchange="this.form.submit()">
                            <option value="Push" <?php echo $category_filter === 'Push' ? 'selected' : ''; ?>>Push</option>
                            <option value="Pull" <?php echo $category_filter === 'Pull' ? 'selected' : ''; ?>>Pull</option>
                            <option value="Legs" <?php echo $category_filter === 'Legs' ? 'selected' : ''; ?>>Legs</option>
                            <option value="Core" <?php echo $category_filter === 'Core' ? 'selected' : ''; ?>>Core</option>
                        </select>
                    </form>
                </div>
                <table class="exercise-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, name, category FROM exercises $category_clause";
                        $result = $conn->query($query);
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row['id'] . "</td>";
                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                                echo "<td>";
                                echo "<form method='post'>";
                                echo "<input type='hidden' name='exercise_id' value='" . $row['id'] . "'>";
                                echo "<input type='text' name='exercise_name' value='" . htmlspecialchars($row['name']) . "' required>";
                                echo "<select name='exercise_category' required>";
                                echo "<option value='Push'" . ($row['category'] === 'Push' ? ' selected' : '') . ">Push</option>";
                                echo "<option value='Pull'" . ($row['category'] === 'Pull' ? ' selected' : '') . ">Pull</option>";
                                echo "<option value='Legs'" . ($row['category'] === 'Legs' ? ' selected' : '') . ">Legs</option>";
                                echo "<option value='Core'" . ($row['category'] === 'Core' ? ' selected' : '') . ">Core</option>";
                                echo "</select>";
                                echo "<button type='submit' name='edit_exercise'>Update</button>";
                                echo "<button type='submit' name='delete_exercise' class='delete-btn'>Delete</button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4'>No exercises found for this category.</td></tr>";
                        }
                        $result->free();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.classList.add('fade-out');
                    setTimeout(() => {
                        successMessage.remove();
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>