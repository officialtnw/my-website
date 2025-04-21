<?php
// Start session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
SQL Queries to update the users table (if not already applied):
-- Add first_name and last_name columns
ALTER TABLE users 
    ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER id,
    ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER first_name;

-- Ensure parent_coach_id exists (for clients to link to their coach)
ALTER TABLE users 
    ADD COLUMN parent_coach_id INT DEFAULT NULL AFTER coach_id,
    ADD FOREIGN KEY (parent_coach_id) REFERENCES users(id);

-- Make email unique (for login purposes)
ALTER TABLE users 
    ADD UNIQUE (email);

-- Optional: Migrate existing usernames to first_name/last_name (if data exists)
UPDATE users SET first_name = username, last_name = '' WHERE username IS NOT NULL;

-- Drop username column (only after migration, if desired)
ALTER TABLE users 
    DROP COLUMN username;
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_type = trim($_POST["user_type"]);
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $coach_id = ($user_type === "client") ? trim($_POST["coach_id"]) : null; // This is the coach's ID entered by the client
    $subscription_plan = ($user_type === "coach") ? trim($_POST["subscription_plan"]) : null;
    $country = trim($_POST["country"]);
    $terms = isset($_POST["terms"]) ? 1 : 0;
    $registered_ip = $_SERVER['REMOTE_ADDR'];
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);
    // Set rank_perms: 2 for coach, 3 for client (1 is reserved for owner and not assignable here)
    $rank_perms = ($user_type === "coach") ? 2 : 3;
    $created_at = date('Y-m-d H:i:s');
    $subscription_status = ($user_type === "coach") ? 'pending' : null;

    // Validate inputs
    if ($password !== $confirm_password) {
        echo "<p class='error'>Passwords do not match!</p>";
    } elseif ($terms !== 1) {
        echo "<p class='error'>You must agree to the terms and conditions!</p>";
    } elseif ($user_type === "client" && empty($coach_id)) {
        echo "<p class='error'>Coach ID is required for clients!</p>";
    } elseif ($user_type === "coach" && empty($subscription_plan)) {
        echo "<p class='error'>Please select a subscription plan!</p>";
    } elseif (empty($first_name) || empty($last_name)) {
        echo "<p class='error'>First name and last name are required!</p>";
    } else {
        $conn = new mysqli("localhost", "root", "lol_123", "strv_db");

        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            echo "<p class='error'>Connection failed: " . $conn->connect_error . "</p>";
        } else {
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                echo "<p class='error'>Email already taken!</p>";
            } else {
                // Fetch max_clients from subscription_plans for coaches
                $max_clients = 0; // Default for clients
                if ($user_type === "coach") {
                    $plan_stmt = $conn->prepare("SELECT max_clients FROM subscription_plans WHERE plan_name = ? AND rank_id = 2");
                    $plan_stmt->bind_param("s", $subscription_plan);
                    $plan_stmt->execute();
                    $plan_stmt->bind_result($max_clients);
                    if (!$plan_stmt->fetch()) {
                        echo "<p class='error'>Invalid subscription plan selected!</p>";
                        $plan_stmt->close();
                        $check->close();
                        $conn->close();
                        exit();
                    }
                    $plan_stmt->close();
                }

                if ($user_type === "client") {
                    // Validate Coach ID exists and belongs to a coach (rank_perms = 2)
                    $coach_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND rank_perms = 2");
                    $coach_check->bind_param("i", $coach_id);
                    $coach_check->execute();
                    $coach_check->store_result();

                    if ($coach_check->num_rows == 0) {
                        echo "<p class='error'>Invalid Coach ID! Must be a registered coach.</p>";
                    } else {
                        // For clients, set parent_coach_id to the entered coach_id
                        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, parent_coach_id, country, rank_perms, max_clients, created_at, registered_ip, last_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssisissss", $first_name, $last_name, $email, $password_hashed, $coach_id, $country, $rank_perms, $max_clients, $created_at, $registered_ip, $registered_ip);
                    }
                    $coach_check->close();
                } else {
                    // For coaches, include subscription_plan, subscription_status, and max_clients
                    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, country, rank_perms, subscription_plan, subscription_status, max_clients, created_at, registered_ip, last_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssisssisss", $first_name, $last_name, $email, $password_hashed, $country, $rank_perms, $subscription_plan, $subscription_status, $max_clients, $created_at, $registered_ip, $registered_ip);
                }

                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id; // Get the auto-incremented ID
                    if ($user_type === "coach") {
                        // Update the coach's record to set coach_id to their own ID
                        $update_stmt = $conn->prepare("UPDATE users SET coach_id = ? WHERE id = ?");
                        $update_stmt->bind_param("ii", $new_user_id, $new_user_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    error_log("User registered successfully: email=$email, type=$user_type, id=$new_user_id, rank_perms=$rank_perms, max_clients=$max_clients" . ($user_type === "client" ? ", parent_coach_id=$coach_id" : ""));
                    $_SESSION["email"] = $email; // Use email instead of username for session
                    $_SESSION["first_name"] = $first_name; // Store first name for display
                    $_SESSION["last_name"] = $last_name;   // Store last name for display
                    $_SESSION["user_type"] = $user_type;
                    $_SESSION["user_id"] = $new_user_id;
                    $_SESSION["subscription_plan"] = $subscription_plan;
                    $_SESSION["subscription_status"] = $subscription_status;
                    $_SESSION["rank_perms"] = $rank_perms;
                    $_SESSION["max_clients"] = $max_clients;
                    echo "<p class='success'>Registration successful! " . ($user_type === "coach" ? "Your Coach ID is: $new_user_id. Payment pending." : "") . " Redirecting...</p>";
                    header("Refresh: 2; url=/dash"); // Always redirect to /dash
                    exit();
                } else {
                    error_log("Execute failed: " . $stmt->error);
                    echo "<p class='error'>Execute failed: " . $stmt->error . "</p>";
                }
                $stmt->close();
            }
            $check->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <link rel="stylesheet" href="/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #e74c3c;
            --primary-hover: #c0392b;
            --background: #f9f9f9;
            --white: #fff;
            --text-color: #333;
            --border-color: #ddd;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 10px;
        }

        .logo {
            width: 100%;
            max-width: 150px;
            height: auto;
            margin-bottom: 2vh;
        }

        .register-container {
            background: var(--white);
            padding: 5vw;
            border-radius: 10px;
            box-shadow: var(--shadow);
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .register-container h2 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            margin-bottom: 2vh;
            color: var(--text-color);
        }

        .register-container input, .register-container select {
            width: 100%;
            padding: 2.5vw;
            margin: 1.5vh 0;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: clamp(0.9rem, 3vw, 1rem);
            box-sizing: border-box;
        }

        .register-container button {
            width: 100%;
            padding: 2.5vw;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: clamp(0.9rem, 3vw, 1rem);
            transition: background-color 0.3s ease;
        }

        .register-container button:hover {
            background-color: var(--primary-hover);
        }

        .register-container p {
            margin-top: 2vh;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }

        .register-container a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .register-container a:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            margin-bottom: 1.5vh;
        }

        .success {
            color: green;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            margin-bottom: 1.5vh;
        }

        .terms-container {
            display: flex;
            align-items: center;
            margin: 1.5vh 0;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }

        .terms-container input {
            width: auto;
            margin-right: 10px;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 4vw;
            }
            .logo {
                max-width: 120px;
            }
        }

        @media (min-width: 768px) {
            .register-container {
                padding: 30px;
            }
            .register-container input,
            .register-container select,
            .register-container button {
                padding: 10px;
            }
        }

        .hidden { display: none; }
    </style>
    <script>
        function toggleFields() {
            const userType = document.getElementById("user_type").value;
            const coachIdInput = document.getElementById("coach_id_input");
            const subscriptionField = document.getElementById("subscription_field");

            // Hide all fields by default
            coachIdInput.classList.add("hidden");
            subscriptionField.classList.add("hidden");

            if (userType === "client") {
                coachIdInput.classList.remove("hidden");
            } else if (userType === "coach") {
                subscriptionField.classList.remove("hidden");
            }
        }
    </script>
</head>
<body>
    <img src="/images/logo.png" alt="STRV Fitness Logo" class="logo">
    <div class="register-container">
        <h2>Register with STRV</h2>
        <form method="post" action="/register">
            <select name="user_type" id="user_type" onchange="toggleFields()" required>
                <option value="">Select User Type</option>
                <option value="client">Client</option>
                <option value="coach">Coach</option>
            </select>
            <input type="text" name="first_name" placeholder="First Name" required>
            <input type="text" name="last_name" placeholder="Last Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <div id="coach_id_input" class="hidden">
                <input type="text" name="coach_id" placeholder="Enter Coach ID">
            </div>
            <div id="subscription_field" class="hidden">
                <select name="subscription_plan">
                    <option value="">Select Subscription Plan</option>
                    <option value="basic">Basic - $1/month</option>
                    <option value="pro">Pro - $2/month</option>
                    <option value="elite">Elite - $3/month</option>
                </select>
            </div>
            <select name="country" required>
                <option value="">Select Country</option>
                <option value="US">United States</option>
                <option value="UK">United Kingdom</option>
                <option value="CA">Canada</option>
                <option value="AU">Australia</option>
            </select>
            <div class="terms-container">
                <input type="checkbox" name="terms" id="terms" value="1">
                <label for="terms">I agree to the <a href="/terms" target="_blank">Terms and Conditions</a></label>
            </div>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="/login">Login here</a></p>
    </div>
</body>
</html>