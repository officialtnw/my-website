<?php
session_start(); // Start session at the top

// Only process form data if the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if email and password are set in $_POST
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
    $password = isset($_POST["password"]) ? $_POST["password"] : '';
    $ip_address = $_SERVER['REMOTE_ADDR']; // Get the user's IP address

    // Validate that email and password are not empty
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required!";
    } else {
        $conn = new mysqli("localhost", "root", "lol_123", "strv_db");

        if ($conn->connect_error) {
            $error_message = "Connection failed: " . $conn->connect_error;
        } else {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, password, rank_perms, subscription_plan, subscription_status, max_clients FROM users WHERE email = ?");
            if (!$stmt) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($user_id, $first_name, $last_name, $hashed_password, $rank_perms, $subscription_plan, $subscription_status, $max_clients);
                    $stmt->fetch();
                    if (password_verify($password, $hashed_password)) {
                        // Update last_ip in the database
                        $update_stmt = $conn->prepare("UPDATE users SET last_ip = ? WHERE id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param("si", $ip_address, $user_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        } else {
                            $error_message = "Failed to update IP: " . $conn->error;
                        }

                        // Set session variables
                        $_SESSION["email"] = $email;
                        $_SESSION["first_name"] = $first_name;
                        $_SESSION["last_name"] = $last_name;
                        $_SESSION["user_id"] = $user_id;
                        $_SESSION["rank_perms"] = $rank_perms;
                        $_SESSION["subscription_plan"] = $subscription_plan;
                        $_SESSION["subscription_status"] = $subscription_status;
                        $_SESSION["max_clients"] = $max_clients;
                        $success_message = "Login successful! Redirecting to dashboard...";
                        header("Refresh: 2; url=/dash"); // Redirect after 2 seconds
                    } else {
                        $error_message = "Incorrect password!";
                    }
                } else {
                    $error_message = "Email not found!";
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - STRV Fitness</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
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
            --success-bg: #e8f5e9;
            --success-text: #2ecc71;
            --error-bg: #fce4e4;
            --error-text: red;
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
            position: relative; /* For notification positioning */
        }

        .logo {
            width: 100%;
            max-width: 150px;
            height: auto;
            margin-bottom: 2vh;
        }

        .login-container {
            background: var(--white);
            padding: 5vw;
            border-radius: 10px;
            box-shadow: var(--shadow);
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .login-container h2 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            margin-bottom: 2vh;
            color: var(--text-color);
        }

        .login-container input {
            width: 100%;
            padding: 2.5vw;
            margin: 1.5vh 0;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: clamp(0.9rem, 3vw, 1rem);
            box-sizing: border-box;
        }

        .login-container button {
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

        .login-container button:hover {
            background-color: var(--primary-hover);
        }

        .login-container p {
            margin-top: 2vh;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }

        .login-container a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-container a:hover {
            text-decoration: underline;
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-size: clamp(0.9rem, 3vw, 1rem);
            box-shadow: var(--shadow);
            z-index: 1000;
            opacity: 0;
            animation: fadeInOut 2s ease-in-out;
        }

        .notification.success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-text);
        }

        .notification.error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-text);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            20% { opacity: 1; transform: translateX(-50%) translateY(0); }
            80% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 4vw;
            }

            .logo {
                max-width: 120px;
            }

            .notification {
                width: 80%;
                padding: 10px 15px;
            }
        }

        @media (min-width: 768px) {
            .login-container {
                padding: 30px;
            }

            .login-container input,
            .login-container button {
                padding: 10px;
            }

            .notification {
                padding: 15px 25px;
            }
        }
    </style>
</head>
<body>
    <img src="/images/logo.png" alt="STRV Fitness Logo" class="logo">
    <?php
    if (isset($success_message)) {
        echo "<div class='notification success'>$success_message</div>";
    } elseif (isset($error_message)) {
        echo "<div class='notification error'>$error_message</div>";
    }
    ?>
    <div class="login-container">
        <h2>Login to STRV Fitness</h2>
        <form method="post" action="/login">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>Don’t have an account? <a href="/register">Register here</a></p>
    </div>
</body>
</html>