<!-- submit.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Booking - Vic Party Hire</title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f9f9f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .response-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .response-container h2 { color: #333; margin-bottom: 20px; }
        .response-container p { margin: 10px 0; }
        .error { color: red; }
        .success { color: green; }
        .response-container a { color: #e74c3c; text-decoration: none; }
        .response-container a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="response-container">
        <h2>Consultation Booking</h2>
        <?php
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        error_log("Accessed submit.php with method: " . $_SERVER["REQUEST_METHOD"]);
        error_log("POST Data: " . print_r($_POST, true));
        error_log("Request URI: " . $_SERVER["REQUEST_URI"]);

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $name = trim($_POST["name"] ?? '');
            $email = trim($_POST["email"] ?? '');
            $phone = trim($_POST["phone"] ?? '');
            $preferred_date = $_POST["preferred_date"] ?? '';
            $preferred_time = $_POST["preferred_time"] ?? '';

            if (empty($name) || empty($email) || empty($phone) || empty($preferred_date) || empty($preferred_time)) {
                echo "<p class='error'>All fields are required!</p>";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo "<p class='error'>Invalid email format!</p>";
            } else {
                $conn = new mysqli("localhost", "root", "lol_123", "strv_db");
                if ($conn->connect_error) {
                    echo "<p class='error'>Connection failed: " . $conn->connect_error . "</p>";
                    error_log("DB Connection failed: " . $conn->connect_error);
                } else {
                    $stmt = $conn->prepare("INSERT INTO consultations (name, email, phone, preferred_date, preferred_time) VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt) {
                        echo "<p class='error'>Prepare failed: " . $conn->error . "</p>";
                        error_log("Prepare failed: " . $conn->error);
                    } else {
                        $stmt->bind_param("sssss", $name, $email, $phone, $preferred_date, $preferred_time);
                        if ($stmt->execute()) {
                            echo "<p class='success'>Consultation booked successfully! We’ll contact you soon.</p>";
                            echo "<p>Redirecting to home...</p>";
                            header("Refresh: 3; url=/home");
                            error_log("Consultation booked: $name, $email");
                        } else {
                            echo "<p class='error'>Error: " . $stmt->error . "</p>";
                            error_log("Execute failed: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                    $conn->close();
                }
            }
        } else {
            echo "<p class='error'>Please submit the consultation form from the home page.</p>";
            echo "<p>Request method received: " . htmlspecialchars($_SERVER["REQUEST_METHOD"]) . "</p>";
            echo "<p>Request URI: " . htmlspecialchars($_SERVER["REQUEST_URI"]) . "</p>";
        }
        ?>
        <p><a href="/home">Back to Home</a></p>
    </div>
</body>
</html>