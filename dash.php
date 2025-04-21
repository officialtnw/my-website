<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION["email"])) {
    header("Location: /login");
    exit();
}

require_once 'includes/db_connect.php';
require_once 'stripe_config.php';

// Database connection
$conn = get_db_connection();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Validate the database schema to ensure required fields are nullable.
 * @param mysqli $conn Database connection
 * @throws Exception If schema validation fails
 */
function validateSchema($conn) {
    $result = $conn->query("DESCRIBE users");
    $schema_errors = [];

    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'coach_id' && $row['Null'] === 'NO' && $row['Default'] === NULL) {
            $schema_errors[] = "Field 'coach_id' must be nullable or have a default value in users table.";
        }
        if ($row['Field'] === 'parent_coach_id' && $row['Null'] === 'NO' && $row['Default'] === NULL) {
            $schema_errors[] = "Field 'parent_coach_id' must be nullable or have a default value in users table.";
        }
    }
    $result->free();

    if (!empty($schema_errors)) {
        $error_message = "Schema validation failed:\n" . implode("\n", $schema_errors);
        error_log($error_message);
        die($error_message);
    }
}

// Validate schema after establishing connection
validateSchema($conn);

// Define Price IDs for Stripe
$stripe_price_ids = [
    'basic' => 'price_1R9eACIKQRhPPdf84ANlcHjg',
    'pro' => 'price_1R9eAjIKQRhPPdf8WfRxnIHJ',
    'elite' => 'price_1R9eB8IKQRhPPdf8vbkGR95B',
];

// Fetch current user's details using email
$email = $_SESSION["email"];
$stmt = $conn->prepare("SELECT id, first_name, last_name, rank_perms, unit_preference, max_clients, coach_id, subscription_plan, subscription_status FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($current_user_id, $first_name, $last_name, $rank_perms, $unit_preference, $max_clients, $coach_id, $subscription_plan, $subscription_status);
if (!$stmt->fetch()) {
    error_log("No user found for email: $email");
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
} else {
    error_log("Fetched user with ID: $current_user_id, email: $email");
    $_SESSION['user_id'] = $current_user_id; // Store user_id in session
}
$stmt->close();

// Fetch the Coach's or Owner's referral code
$referral_code = null;
if ($rank_perms == 1 || $rank_perms == 2) {
    $stmt = $conn->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->bind_result($referral_code);
    $stmt->fetch();
    $stmt->close();

    if (empty($referral_code)) {
        $referral_code = ($rank_perms == 1 ? "OWNER" : "COACH") . "{$current_user_id}" . rand(100, 999);
        $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $stmt->bind_param("si", $referral_code, $current_user_id);
        $stmt->execute();
        $stmt->close();
    }
}

$unit_preference = $unit_preference ?? 'kg';

// Determine the user to view (admin can view others)
$view_user_id = isset($_GET['user_id']) && $rank_perms == 1 ? (int)$_GET['user_id'] : $current_user_id;

// Fetch viewed user's details
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, fitness_goals, protein, carbs, fats, unit_preference, stripe_customer_id, current_weight, age, package_weeks, phone, start_weight, checkin_day, coach_id, max_clients, parent_coach_id FROM users WHERE id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$stmt->bind_result($user_id, $view_first_name, $view_last_name, $view_email, $fitness_goals, $protein, $carbs, $fats, $view_unit_preference, $stripe_customer_id, $current_weight, $age, $package_weeks, $phone, $start_weight, $checkin_day, $coach_id, $view_max_clients, $parent_coach_id);
if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    header("Location: /login");
    exit();
}
$stmt->close();
$view_unit_preference = $view_unit_preference ?? 'kg';

// Use parent_coach_id as the coach_id for clients
if ($rank_perms != 1 && $rank_perms != 2) {
    $coach_id = $parent_coach_id; // For clients, use parent_coach_id as the coach_id
}

// Debug: Log the coach_id
error_log("Coach ID for user $view_user_id: $coach_id");

// Initialize subscription variables
$subscription_status = $subscription_status ?? 'inactive';
$stripe_subscription_id = null;
$pending_plan_id = null;
$next_payment_date = null;
$subscription_max_clients = $view_max_clients ?? 0;

// Fetch subscription data
$stmt = $conn->prepare("SELECT stripe_subscription_id, status, plan_id, max_clients FROM subscriptions WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$stmt->bind_result($stripe_subscription_id, $sub_status, $pending_plan_id, $sub_max_clients);
if ($stmt->fetch()) {
    $subscription_status = $sub_status;
    $subscription_plan = $pending_plan_id;
    $subscription_max_clients = $sub_max_clients ?? $view_max_clients;
}
$stmt->close();

// Initialize coach_clients array early
$coach_clients = [];
$total_clients = 0; // Initialize to avoid undefined variable
$total_pages = 1;   // Initialize to avoid undefined variable

// Handle payment initiation for coaches
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["initiate_payment"]) && $rank_perms == 2 && $subscription_status === 'pending') {
    try {
        if (!$stripe_customer_id) {
            $customer = \Stripe\Customer::create([
                'email' => $view_email,
                'name' => "$first_name $last_name",
            ]);
            $stripe_customer_id = $customer->id;
            $stmt = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
            $stmt->bind_param("si", $stripe_customer_id, $current_user_id);
            $stmt->execute();
            $stmt->close();
        }

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $stripe_price_ids[$subscription_plan],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'customer' => $stripe_customer_id,
            'success_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/dash?success=1',
            'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/dash',
        ]);

        header("Location: " . $session->url);
        exit();
    } catch (\Stripe\Exception\ApiErrorException $e) {
        $error = "Error initiating payment: " . $e->getMessage();
    }
}

// Handle payment success
if (isset($_GET['success']) && $_GET['success'] == 1 && $rank_perms == 2) {
    if ($stripe_customer_id && $subscription_status === 'pending') {
        try {
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $stripe_customer_id,
                'limit' => 1,
            ]);

            $valid_statuses = ['active', 'trialing'];
            $filtered_subscriptions = array_filter($subscriptions->data, function($sub) use ($valid_statuses) {
                return in_array($sub->status, $valid_statuses);
            });

            if (!empty($filtered_subscriptions)) {
                $subscription = reset($filtered_subscriptions);
                $stripe_subscription_id = $subscription->id;
                $next_payment_date = date('Y-m-d', $subscription->current_period_end);

                $stmt = $conn->prepare("SELECT max_clients FROM subscription_plans WHERE plan_name = ? AND rank_id = 2");
                $stmt->bind_param("s", $subscription_plan);
                $stmt->execute();
                $stmt->bind_result($new_max_clients);
                if (!$stmt->fetch()) {
                    $error = "Invalid subscription plan in database.";
                    $stmt->close();
                    $conn->close();
                    exit();
                }
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, stripe_subscription_id, status, plan_id, max_clients) VALUES (?, ?, 'paid', ?, ?) ON DUPLICATE KEY UPDATE stripe_subscription_id = ?, status = 'paid', max_clients = ?");
                $stmt->bind_param("isssis", $current_user_id, $stripe_subscription_id, $subscription_plan, $new_max_clients, $stripe_subscription_id, $new_max_clients);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET subscription_status = 'paid', max_clients = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_max_clients, $current_user_id);
                $stmt->execute();
                $stmt->close();

                $subscription_status = 'paid';
                $_SESSION["subscription_status"] = 'paid';
                $subscription_max_clients = $new_max_clients;
                $success = "Payment completed! Your subscription is now active.";
            } else {
                $error = "No active subscription found after payment.";
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = "Error verifying subscription: " . $e->getMessage();
        }
    }
}

/**
 * Add a new client under a coach or owner.
 * @param mysqli $conn Database connection
 * @param int|null $current_user_id ID of the coach or owner adding the client
 * @param string $first_name Client's first name
 * @param string $last_name Client's last name
 * @param string $email Client's email
 * @param string $password Client's password (hashed)
 * @param string $unit_preference Unit preference (default 'kg')
 * @param string $subscription_status Subscription status (default 'pending')
 * @return array Result array with 'success' (bool), 'message' (string), and 'client_id' (int, if successful)
 */
function addClient($conn, $current_user_id, $first_name, $last_name, $email, $password, $unit_preference = 'kg', $subscription_status = 'pending') {
    $result = ['success' => false, 'message' => '', 'client_id' => null];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $result['message'] = "All client details (first name, last name, email, password) are required.";
        error_log("Add client failed: " . $result['message']);
        return $result;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $result['message'] = "Email already in use.";
        error_log("Add client failed: " . $result['message']);
        $stmt->close();
        return $result;
    }
    $stmt->close();

    // Validate current_user_id and set coach_id
    $coach_id = null;
    if ($current_user_id) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            error_log("Invalid current user ID: $current_user_id does not exist. Setting coach_id to NULL.");
        } else {
            $coach_id = $current_user_id;
        }
        $stmt->close();
    } else {
        error_log("Current user ID is not set. Setting coach_id to NULL.");
    }

    // Insert the new client
    $rank_perms = 3; // Client role
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, rank_perms, parent_coach_id, coach_id, unit_preference, subscription_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $result['message'] = "Prepare failed: " . $conn->error;
        error_log("Add client failed: " . $result['message']);
        return $result;
    }

    error_log("Binding params for new client: first_name=$first_name, last_name=$last_name, email=$email, rank_perms=$rank_perms, parent_coach_id=" . ($current_user_id ?? 'NULL') . ", coach_id=" . ($coach_id ?? 'NULL') . ", unit_preference=$unit_preference, subscription_status=$subscription_status");
    $stmt->bind_param("ssssiiiss", $first_name, $last_name, $email, $password, $rank_perms, $current_user_id, $coach_id, $unit_preference, $subscription_status);
    
    if ($stmt->execute()) {
        $result['success'] = true;
        $result['message'] = "Client added successfully.";
        $result['client_id'] = $stmt->insert_id;
        error_log("Client added successfully: ID=" . $result['client_id']);
    } else {
        $result['message'] = "Error creating client: " . $stmt->error;
        error_log("Add client failed: " . $result['message']);
    }
    $stmt->close();
    
    return $result;
}

// Handle client addition for coaches and owners
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_client"]) && ($rank_perms == 1 || $rank_perms == 2)) {
    $client_first_name = trim($_POST["client_first_name"]);
    $client_last_name = trim($_POST["client_last_name"]);
    $client_email = trim($_POST["client_email"]);
    $client_password = password_hash("p@ssword", PASSWORD_DEFAULT);
    $default_unit_preference = 'kg';
    $default_subscription_status = 'pending';

    // Fetch current number of clients for the coach
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE parent_coach_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_clients_count = $result->fetch_assoc()['total'];
    $stmt->close();

    if ($rank_perms == 2 && $subscription_max_clients !== NULL && $current_clients_count >= $subscription_max_clients) {
        $error = "You have reached your maximum client limit!";
    } else {
        // Add the client using the new function
        $addClientResult = addClient($conn, $current_user_id, $client_first_name, $client_last_name, $client_email, $client_password, $default_unit_preference, $default_subscription_status);
        
        if ($addClientResult['success']) {
            $success = "Account has been created for " . htmlspecialchars($client_first_name . " " . $client_last_name);
            $coach_clients[] = [
                'id' => $addClientResult['client_id'],
                'first_name' => $client_first_name,
                'last_name' => $client_last_name,
                'email' => $client_email
            ];
        } else {
            $error = $addClientResult['message'];
        }
    }
}

// Handle check-in deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_checkin"])) {
    $checkin_id = (int)$_POST["checkin_id"];
    $stmt = $conn->prepare("DELETE FROM checkin_submissions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $checkin_id, $view_user_id);
    if ($stmt->execute()) {
        $success = "Check-in deleted successfully!";
        error_log("Check-in ID $checkin_id deleted successfully for user_id=$view_user_id");
        header("Location: /dash");
        exit();
    } else {
        $error = "Error deleting check-in: " . $stmt->error;
        error_log($error);
    }
    $stmt->close();
}

// Fetch coach's custom check-in form template (daily)
$daily_checkin_form = [];
if ($coach_id || $rank_perms == 1 || $rank_perms == 2) {
    $form_coach_id = $coach_id ?: $current_user_id;
    $stmt = $conn->prepare("SELECT form_fields FROM checkin_form_templates WHERE coach_id = ? AND form_type = 'daily'");
    $stmt->bind_param("i", $form_coach_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $daily_checkin_form = json_decode($row['form_fields'], true) ?? [];
    }
    $stmt->close();
}

if (empty($daily_checkin_form)) {
    $daily_checkin_form = [
        ['name' => 'Weight', 'type' => 'number'],
        ['name' => 'Mood', 'type' => 'rating'],
        ['name' => 'Notes', 'type' => 'text']
    ];
}

// Fetch coach's weekly check-in form template for clients
$weekly_checkin_form = [];
if ($coach_id) { // Only fetch for clients who have a coach
    $stmt = $conn->prepare("SELECT form_fields FROM checkin_form_templates WHERE coach_id = ? AND form_type = 'weekly'");
    $stmt->bind_param("i", $coach_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $weekly_checkin_form = json_decode($row['form_fields'], true) ?? [];
        error_log("Weekly check-in form fetched for coach_id $coach_id: " . json_encode($weekly_checkin_form));
    } else {
        error_log("No weekly check-in form found for coach_id $coach_id");
    }
    $stmt->close();
}

// Fallback if no weekly form is found - updated to match the screenshot
if (empty($weekly_checkin_form)) {
    $weekly_checkin_form = [
        ['name' => 'Average Weekly Weight Last Week', 'type' => 'number'],
        ['name' => 'Average Weekly Weight This Week', 'type' => 'number'],
        ['name' => 'Weekly Weight Loss or gain', 'type' => 'number'],
        ['name' => 'Average Daily Steps', 'type' => 'number'],
        ['name' => 'Quality of sleep', 'type' => 'rating'],
        ['name' => 'Hunger', 'type' => 'rating'],
        ['name' => 'Digestion', 'type' => 'rating'],
        ['name' => 'How did you go this week with your Diet/Macros. Did you deviate? If so briefly explain.', 'type' => 'text'],
        ['name' => 'Do you want any meals changed, if so which meal and your preference?', 'type' => 'text'],
        ['name' => 'How do you feel/overall well being?', 'type' => 'text'],
        ['name' => 'How was your gym performance and pumps this week (did you progress on most exercises)', 'type' => 'text']
    ];
    error_log("Using default weekly check-in form: " . json_encode($weekly_checkin_form));
}

// Check if today is the check-in day for the client
$is_checkin_day = false;
if ($checkin_day) {
    $current_day = date('l'); // Get the current day of the week (e.g., "Monday")
    $is_checkin_day = (strtolower($current_day) === strtolower($checkin_day));
    error_log("Current day: $current_day, Check-in day: $checkin_day, Is check-in day: " . ($is_checkin_day ? 'true' : 'false'));
}

// Updated Check-in Submission Logic with coach_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_checkin"])) {
    // Log the raw POST data for debugging
    error_log("Check-in submission received: " . json_encode($_POST));

    // Validate form_type
    $form_type = $_POST["form_type"] ?? '';
    if (!in_array($form_type, ['daily', 'weekly'])) {
        $error = "Invalid form type: " . htmlspecialchars($form_type);
        error_log($error);
    } else {
        // Validate user_id
        if (empty($view_user_id) || !is_int($view_user_id) || $view_user_id <= 0) {
            $error = "Invalid user ID: " . var_export($view_user_id, true);
            error_log($error);
        } else {
            // Validate coach_id
            if (is_null($coach_id)) {
                $error = "Coach ID is required for check-in submission but is NULL for user_id=$view_user_id";
                error_log($error);
            } else {
                // Check database connection
                if (!$conn->ping()) {
                    $error = "Database connection lost: " . $conn->error;
                    error_log($error);
                    die($error);
                }

                // Prepare submission data
                $submission_data = isset($_POST["data"]) ? json_encode($_POST["data"]) : '{}';
                $submitted_at = date('Y-m-d H:i:s');

                // Log the data being inserted
                error_log("Inserting check-in for user_id=$view_user_id, coach_id=$coach_id, form_type=$form_type, submission_data=$submission_data, submitted_at=$submitted_at");

                // Prepare and execute the query, including coach_id
                $stmt = $conn->prepare("INSERT INTO checkin_submissions (user_id, coach_id, form_type, submission_data, submitted_at) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error = "Prepare failed: " . $conn->error;
                    error_log($error);
                } else {
                    $stmt->bind_param("iisss", $view_user_id, $coach_id, $form_type, $submission_data, $submitted_at);
                    if ($stmt->execute()) {
                        $success = "Check-in submitted successfully!";
                        error_log("Check-in submitted successfully for user_id=$view_user_id");

                        // Log to a checkin_submission_logs table (if it exists)
                        $log_stmt = $conn->prepare("INSERT INTO checkin_submission_logs (user_id, form_type, submission_data, status, created_at) VALUES (?, ?, ?, 'success', NOW())");
                        if ($log_stmt) {
                            $log_stmt->bind_param("iss", $view_user_id, $form_type, $submission_data);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }

                        header("Location: /dash");
                        exit();
                    } else {
                        $error = "Error submitting check-in: " . $stmt->error;
                        error_log($error);

                        // Log the failure to checkin_submission_logs (if it exists)
                        $log_stmt = $conn->prepare("INSERT INTO checkin_submission_logs (user_id, form_type, submission_data, status, error_message, created_at) VALUES (?, ?, ?, 'failed', ?, NOW())");
                        if ($log_stmt) {
                            $log_stmt->bind_param("isss", $view_user_id, $form_type, $submission_data, $error);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// New Function: Fetch Check-in Details Server-Side
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

// Handle "View" request server-side
$view_checkin_id = isset($_GET['view_checkin']) ? (int)$_GET['view_checkin'] : null;
$view_form_type = isset($_GET['form_type']) ? $_GET['form_type'] : 'weekly'; // Default to weekly
$checkin_details = null;
if ($view_checkin_id) {
    $checkin_details = getCheckinDetails($conn, $view_checkin_id, $view_form_type, $view_user_id, $current_user_id, $rank_perms);
    if (!$checkin_details) {
        $error = "Check-in not found or you do not have permission to view it.";
    }
}

// Fetch check-in history for the viewed user (both daily and weekly)
$weekly_checkin_history = [];
$daily_checkin_history = [];
$weekly_checkin_pagination = [
    'page' => isset($_GET['weekly_checkin_page']) ? (int)$_GET['weekly_checkin_page'] : 1,
    'per_page' => 12,
    'total_records' => 0,
    'total_pages' => 1,
];
$daily_checkin_pagination = [
    'page' => isset($_GET['daily_checkin_page']) ? (int)$_GET['daily_checkin_page'] : 1,
    'per_page' => 12,
    'total_records' => 0,
    'total_pages' => 1,
];

// Fetch weekly check-in history for clients
if ($rank_perms != 1 && $rank_perms != 2) {
    // Weekly Check-ins
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkin_submissions WHERE user_id = ? AND form_type = 'weekly'");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $weekly_checkin_pagination['total_records'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $weekly_checkin_pagination['total_pages'] = ceil($weekly_checkin_pagination['total_records'] / $weekly_checkin_pagination['per_page']);
    $offset = ($weekly_checkin_pagination['page'] - 1) * $weekly_checkin_pagination['per_page'];

    $stmt = $conn->prepare("SELECT id, form_type, submission_data, submitted_at FROM checkin_submissions WHERE user_id = ? AND form_type = 'weekly' ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $view_user_id, $weekly_checkin_pagination['per_page'], $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $submission_data = json_decode($row['submission_data'], true);
        $submitted_at = new DateTime($row['submitted_at']);

        $entry = [
            'id' => $row['id'],
            'index' => $index++,
            'when' => $submitted_at->format('d M, Y'),
            'form_type' => $row['form_type'],
            'checkin_day' => $checkin_day // Assuming checkin_day is from the user's profile
        ];

        if ($submission_data) {
            foreach ($weekly_checkin_form as $field) {
                $field_name = $field['name'];
                $entry[$field_name] = isset($submission_data[$field_name]) ? $submission_data[$field_name] : '-';
            }
        }

        $weekly_checkin_history[] = $entry;
    }
    $stmt->close();

    // Daily Check-ins
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM checkin_submissions WHERE user_id = ? AND form_type = 'daily'");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_checkin_pagination['total_records'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $daily_checkin_pagination['total_pages'] = ceil($daily_checkin_pagination['total_records'] / $daily_checkin_pagination['per_page']);
    $offset = ($daily_checkin_pagination['page'] - 1) * $daily_checkin_pagination['per_page'];

    $stmt = $conn->prepare("SELECT id, form_type, submission_data, submitted_at FROM checkin_submissions WHERE user_id = ? AND form_type = 'daily' ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $view_user_id, $daily_checkin_pagination['per_page'], $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $submission_data = json_decode($row['submission_data'], true);
        $submitted_at = new DateTime($row['submitted_at']);

        $entry = [
            'id' => $row['id'],
            'index' => $index++,
            'when' => $submitted_at->format('d M, Y'),
            'form_type' => $row['form_type'],
        ];

        if ($submission_data) {
            foreach ($daily_checkin_form as $field) {
                $field_name = $field['name'];
                $entry[$field_name] = isset($submission_data[$field_name]) ? $submission_data[$field_name] : '-';
            }
        }

        $daily_checkin_history[] = $entry;
    }
    $stmt->close();
}

// Fetch check-in data for coach's clients (weekly and daily)
$weekly_checkin_table_data = [];
$daily_checkin_table_data = [];
$weekly_form_fields = [];
$daily_form_fields = [];
if ($rank_perms == 1 || $rank_perms == 2) {
    // Fetch the coach's weekly check-in form template
    $stmt = $conn->prepare("SELECT form_fields FROM checkin_form_templates WHERE coach_id = ? AND form_type = 'weekly'");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $weekly_form_fields = json_decode($row['form_fields'], true) ?? [];
        error_log("Weekly form fields for coach_id $current_user_id: " . json_encode($weekly_form_fields));
    } else {
        error_log("No weekly check-in form found for coach_id $current_user_id");
    }
    $stmt->close();

    if (empty($weekly_form_fields)) {
        $weekly_form_fields = [
            ['name' => 'Average Weekly Weight Last Week', 'type' => 'number'],
            ['name' => 'Average Weekly Weight This Week', 'type' => 'number'],
            ['name' => 'Weekly Weight Loss or gain', 'type' => 'number'],
            ['name' => 'Average Daily Steps', 'type' => 'number'],
            ['name' => 'Quality of sleep', 'type' => 'rating'],
            ['name' => 'Hunger', 'type' => 'rating'],
            ['name' => 'Digestion', 'type' => 'rating'],
            ['name' => 'How did you go this week with your Diet/Macros. Did you deviate? If so briefly explain.', 'type' => 'text'],
            ['name' => 'Do you want any meals changed, if so which meal and your preference?', 'type' => 'text'],
            ['name' => 'How do you feel/overall well being?', 'type' => 'text'],
            ['name' => 'How was your gym performance and pumps this week (did you progress on most exercises)', 'type' => 'text']
        ];
        error_log("Using default weekly form fields: " . json_encode($weekly_form_fields));
    }

    // Fetch the coach's daily check-in form template
    $stmt = $conn->prepare("SELECT form_fields FROM checkin_form_templates WHERE coach_id = ? AND form_type = 'daily'");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $daily_form_fields = json_decode($row['form_fields'], true) ?? [];
    }
    $stmt->close();

    if (empty($daily_form_fields)) {
        $daily_form_fields = [
            ['name' => 'Weight', 'type' => 'number'],
            ['name' => 'Mood', 'type' => 'rating'],
            ['name' => 'Notes', 'type' => 'text']
        ];
    }

    // Fetch weekly check-in submissions for the coach's clients
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM checkin_submissions cs
        JOIN users u ON cs.user_id = u.id
        WHERE u.parent_coach_id = ? AND cs.form_type = 'weekly'
    ");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $weekly_checkin_pagination['total_records'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $weekly_checkin_pagination['total_pages'] = ceil($weekly_checkin_pagination['total_records'] / $weekly_checkin_pagination['per_page']);
    $offset = ($weekly_checkin_pagination['page'] - 1) * $weekly_checkin_pagination['per_page'];

    $stmt = $conn->prepare("
        SELECT cs.id, cs.user_id, cs.submission_data, cs.submitted_at, cs.form_type, u.checkin_day, u.first_name, u.last_name
        FROM checkin_submissions cs
        JOIN users u ON cs.user_id = u.id
        WHERE u.parent_coach_id = ? AND cs.form_type = 'weekly'
        ORDER BY cs.submitted_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $current_user_id, $weekly_checkin_pagination['per_page'], $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $submission_data = json_decode($row['submission_data'], true);
        $submitted_at = new DateTime($row['submitted_at']);
        $checkin_day = $row['checkin_day'] ?? 'Wednesday';

        $entry = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'index' => $index++,
            'when' => $submitted_at->format('d M, Y'),
            'checkin_day' => $checkin_day,
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
        ];

        // Debug: Log the raw submission_data
        error_log("Submission data for check-in ID {$row['id']}: " . json_encode($submission_data));

        // Map submission_data to form fields
        foreach ($weekly_form_fields as $field) {
            $field_name = $field['name'];
            $entry[$field_name] = isset($submission_data[$field_name]) ? $submission_data[$field_name] : '-';
        }

        $weekly_checkin_table_data[] = $entry;
    }
    $stmt->close();

    // Debug: Log the weekly_checkin_table_data
    error_log("Weekly check-in table data: " . json_encode($weekly_checkin_table_data));

    // Fetch daily check-in submissions for the coach's clients
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM checkin_submissions cs
        JOIN users u ON cs.user_id = u.id
        WHERE u.parent_coach_id = ? AND cs.form_type = 'daily'
    ");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_checkin_pagination['total_records'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $daily_checkin_pagination['total_pages'] = ceil($daily_checkin_pagination['total_records'] / $daily_checkin_pagination['per_page']);
    $offset = ($daily_checkin_pagination['page'] - 1) * $daily_checkin_pagination['per_page'];

    $stmt = $conn->prepare("
        SELECT cs.id, cs.user_id, cs.submission_data, cs.submitted_at, cs.form_type, u.first_name, u.last_name
        FROM checkin_submissions cs
        JOIN users u ON cs.user_id = u.id
        WHERE u.parent_coach_id = ? AND cs.form_type = 'daily'
        ORDER BY cs.submitted_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $current_user_id, $daily_checkin_pagination['per_page'], $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $submission_data = json_decode($row['submission_data'], true);
        $submitted_at = new DateTime($row['submitted_at']);

        $entry = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'index' => $index++,
            'when' => $submitted_at->format('d M, Y'),
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
        ];

        foreach ($daily_form_fields as $field) {
            $field_name = $field['name'];
            $entry[$field_name] = isset($submission_data[$field_name]) ? $submission_data[$field_name] : '-';
        }

        $daily_checkin_table_data[] = $entry;
    }
    $stmt->close();
}

$weights = [];
$dates = [];
$stmt = $conn->prepare("SELECT weight, date_recorded FROM weight_history WHERE user_id = ? ORDER BY date_recorded ASC LIMIT 10");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $weight = $row['weight'];
    if ($view_unit_preference === 'lbs') {
        $weight = round($weight / 0.453592, 2);
    }
    $weights[] = $weight;
    $dates[] = (new DateTime($row['date_recorded']))->format('m/d');
}
$stmt->close();

// Pagination for coach_clients
$page = isset($_GET['client_page']) ? (int)$_GET['client_page'] : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

if ($rank_perms == 1 || $rank_perms == 2) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE parent_coach_id = ?");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_clients = $result->fetch_assoc()['total'];
    $stmt->close();

    $total_pages = ceil($total_clients / $per_page);

    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE parent_coach_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $view_user_id, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $coach_clients = [];
    while ($row = $result->fetch_assoc()) {
        $coach_clients[] = $row;
    }
    $stmt->close();
}

$users = [];
$active_subscriptions_result = null;
if ($rank_perms == 1) {
    $result = $conn->query("SELECT id, first_name, last_name, email, max_clients FROM users");
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'first_name' => htmlspecialchars($row['first_name']),
            'last_name' => htmlspecialchars($row['last_name']),
            'email' => htmlspecialchars($row['email']),
            'max_clients' => $row['max_clients']
        ];
    }
    $result->free();

    $stmt = $conn->prepare("
        SELECT u.first_name, u.last_name, s.stripe_subscription_id, s.status, s.plan_id, s.max_clients
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id
        WHERE s.status IS NOT NULL
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $active_subscriptions_result = $stmt->get_result();
    $stmt->close();
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - STRV Fitness</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .consultation-entry, .client-list div {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .consultation-entry:last-child, .client-list div:last-child {
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
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .payment-section {
            max 🙂-width: 400px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .payment-section h3 {
            margin-bottom: 15px;
        }
        .payment-section p {
            margin-bottom: 10px;
        }
        .payment-section button {
            padding: 10px 20px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .payment-section button:hover {
            background-color: #c0392b;
        }
        .success { color: green; margin-bottom: 15px; }
        .error { 
            color: red; 
            margin-bottom: 15px; 
            padding: 10px; 
            background-color: #ffe6e6; 
            border-radius: 5px; 
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        .manage-clients-section .stats {
            margin-bottom: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .manage-clients-section .stats p {
            margin: 10px 0;
            font-size: 16px;
        }
        .manage-clients-section .stats i {
            margin-right: 10px;
            color: #1a73e8;
        }
        .manage-clients-section .form-group {
            margin-bottom: 20px;
        }
        .manage-clients-section .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .manage-clients-section .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .manage-clients-section .form-group input[readonly] {
            background: #f5f5f5;
            color: #666;
        }
        .manage-clients-section .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .manage-clients-section button {
            padding: 12px 25px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        .manage-clients-section button:hover {
            background-color: #1557b0;
        }
        .manage-clients-section .client-list {
            margin-top: 30px;
        }
        .manage-clients-section .client-list h4 {
            margin-bottom: 15px;
            color: #333;
        }
        .manage-clients-section .client-list div {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .manage-clients-section .client-list i {
            margin-right: 10px;
            color: #1a73e8;
        }
        .manage-clients-section .client-list strong {
            flex: 1;
            color: #333;
        }
        .manage-clients-section .client-list span {
            flex: 2;
            color: #666;
        }
        .manage-clients-section .client-list a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 500;
        }
        .manage-clients-section .client-list a:hover {
            text-decoration: underline;
        }
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
        .pagination a {
            color: #1a73e8;
            text-decoration: none;
            padding: 5px 10px;
        }
        .pagination a:hover {
            text-decoration: underline;
        }
        .checkin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .checkin-table th, .checkin-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .checkin-table th {
            background-color: #f5f5f5;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px; /* Adjust for 10 characters */
        }
        .checkin-table tr:hover {
            background-color: #f9f9f9;
        }
        .checkin-table .actions {
            text-align: right;
            position: relative;
            width: 50px; /* Ensure enough space for the action dots */
        }
        .checkin-table .actions i {
            margin-left: 10px;
            cursor: pointer;
            color: #1a73e8;
            font-size: 16px;
            display: inline-block; /* Ensure visibility */
        }
        .checkin-table .actions .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%; /* Position below the dots */
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 100px;
        }
        .checkin-table .actions:hover .dropdown {
            display: block;
        }
        .checkin-table .actions .dropdown a, .checkin-table .actions .dropdown button {
            display: block;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .checkin-table .actions .dropdown a:hover, .checkin-table .actions .dropdown button:hover {
            background-color: #f5f5f5;
        }
        .checkin-table .actions .dropdown button.delete {
            color: #dc3545;
        }
        .checkin-table .truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .manage-columns {
            position: absolute;
            right: 0;
            top: 40px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 10px;
            display: none;
            max-height: 300px;
            overflow-y: auto;
        }
        .manage-columns label {
            display: block;
            margin-bottom: 5px;
        }
        .checkin-form-container {
            margin-bottom: 20px;
        }
        .checkin-form-container .form-group {
            margin-bottom: 15px;
        }
        .checkin-form-container .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .checkin-form-container .form-group input,
        .checkin-form-container .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .checkin-form-container .form-group .rating {
            display: flex;
            gap: 5px;
        }
        .checkin-form-container .form-group .rating input {
            display: none;
        }
        .checkin-form-container .form-group .rating label {
            cursor: pointer;
            padding: 1px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkin-form-container .form-group .rating input:checked + label {
            background-color: #1a73e8;
            color: white;
        }
        .checkin-form-container button {
            padding: 10px 20px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .checkin-form-container button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
                }
        .checkin-details, .comparison-details {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            display: <?php echo $checkin_details ? 'block' : 'none'; ?>;
        }
        .checkin-details h3, .comparison-details h3 {
            margin-bottom: 15px;
            font-size: 1.5em;
            color: #333;
        }
        .checkin-details p, .comparison-details p {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .checkin-details p strong, .comparison-details p strong {
            flex: 0 0 50%;
            color: #333;
        }
        .checkin-details p span, .comparison-details p span {
            flex: 0 0 50%;
            color: #666;
            text-align: left;
        }
        .checkin-details .back-btn, .comparison-details .back-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #1a73e8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .checkin-details .back-btn:hover, .comparison-details .back-btn:hover {
            background-color: #1557b0;
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
        .compare-btn {
            background-color: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .compare-btn:hover {
            background-color: #218838;
        }
        .compare-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.5;
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
            .payment-section {
                margin-left: 270px;
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
            <div class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'dash.php' ? 'active' : ''; ?>" data-tab="dashboard">
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

        <?php if ($rank_perms == 2 && $subscription_status === 'pending'): ?>
            <div class="payment-section">
                <?php if (isset($success)): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <h3>Payment Required</h3>
                <p>Your Coach ID: <?php echo htmlspecialchars($coach_id ?? 'N/A'); ?></p>
                <p>Plan: <?php echo ucfirst($subscription_plan); ?> - $<?php echo $subscription_plan === 'basic' ? '1' : ($subscription_plan === 'pro' ? '2' : '3'); ?>/month</p>
                <p>Status: Pending</p>
                <form method="post">
                    <button type="submit" name="initiate_payment">Pay Now</button>
                </form>
            </div>
        <?php else: ?>
            <div class="dashboard-container">
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="notification" id="successNotification"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="user-info">
                    <div class="user-details">
                        <img src="/assets/images/user-placeholder.jpg" alt="User" class="user-avatar">
                        <div>
                            <h2><?php echo htmlspecialchars($view_first_name . ' ' . $view_last_name); ?></h2>
                            <p><?php echo htmlspecialchars($view_email); ?></p>
                            <p><?php echo htmlspecialchars($phone ?? 'No phone number'); ?></p>
                            <br>
                            <p><i class="fas fa-link"></i> Referral Code: <?php echo htmlspecialchars($referral_code ?? 'None'); ?></p>
                        </div>
                    </div>
                    <span class="payment-status <?php echo $subscription_status === 'paid' ? 'paid' : 'offline'; ?>">
                        <?php echo $subscription_status === 'paid' ? 'Paid' : 'Offline Payment'; ?>
                    </span>
                </div>

                <div class="stats-bar">
                    <div class="stat">
                        <p><?php echo htmlspecialchars($checkin_day ?? 'Wed'); ?></p>
                        <p>Check in day</p>
                    </div>
                    <div class="stat">
                        <p><?php echo htmlspecialchars($package_weeks ?? '-'); ?></p>
                        <p>Total Weeks</p>
                    </div>
                    <div class="stat">
                        <p><?php echo htmlspecialchars($start_weight ?? '-'); ?> kg</p>
                        <p>Start Weight</p>
                    </div>
                    <div class="stat">
                        <p><?php echo htmlspecialchars($current_weight ?? '-'); ?> kg</p>
                        <p>Current Weight</p>
                    </div>
                    <div class="stat">
                        <p><?php echo htmlspecialchars($age ?? '-'); ?></p>
                        <p>Age</p>
                    </div>
                </div>

                <div class="tabs">
                    <div class="tab active" data-tab="dashboard">Dashboard</div>
                    <div class="tab" data-tab="checkins">Checkins</div>
                    <div class="tab" data-tab="gallery">Gallery</div>
                    <div class="tab" data-tab="qa">Q&A</div>
                    <div class="tab" data-tab="nutrition">Nutrition</div>
                    <div class="tab" data-tab="supplements">Supplements</div>
                    <div class="tab" data-tab="workout">Workout</div>
                    <div class="tab" data-tab="calendar">Calendar</div>
                    <div class="tab" data-tab="logs">Logs</div>
                    <?php if ($rank_perms == 1 || $rank_perms == 2): ?>
                        <div class="tab" data-tab="manage-clients">Manage Clients</div>
                    <?php endif; ?>
                </div>

                <div class="tab-content active" id="content-dashboard">
                    <div class="card">
                        <h3>Daily Check-in</h3>
                        <div class="checkin-form-container" id="daily-checkin">
                            <form method="post" class="checkin-form">
                                <input type="hidden" name="form_type" value="daily">
                                <?php if (!empty($daily_checkin_form)): ?>
                                    <p>Form Fields: <?php echo count($daily_checkin_form); ?></p>
                                    <?php foreach ($daily_checkin_form as $index => $field): ?>
                                        <br>
                                        <div class="form-group">
                                            <label><?php echo htmlspecialchars($field['name']); ?>:</label>
                                            <?php if ($field['type'] === 'number'): ?>
                                                <input 
                                                    type="number" 
                                                    name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                    placeholder="Enter <?php echo htmlspecialchars($field['name']); ?>" 
                                                    min="0" 
                                                    step="0.01" 
                                                    required
                                                >
                                                <?php if (strtolower($field['name']) === 'weight'): ?>
                                                    <span><?php echo htmlspecialchars($view_unit_preference); ?></span>
                                                <?php endif; ?>
                                            <?php elseif ($field['type'] === 'rating'): ?>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                                        <input 
                                                            type="radio" 
                                                            name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                            value="<?php echo $i; ?>" 
                                                            id="daily-<?php echo $index; ?>-<?php echo $i; ?>"
                                                            <?php echo $i === 5 ? 'checked' : ''; ?>
                                                        >
                                                        <label for="daily-<?php echo $index; ?>-<?php echo $i; ?>"><?php echo $i; ?></label>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php elseif ($field['type'] === 'text'): ?>
                                                <textarea 
                                                    name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                    placeholder="Enter <?php echo htmlspecialchars($field['name']); ?>" 
                                                    rows="3"
                                                ></textarea>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <button type="submit" name="submit_checkin">Submit</button>
                                <?php else: ?>
                                    <p>No check-in form has been set up by your coach. Please contact them to configure your daily check-in form.</p>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Weight Progress</h3>
                        <div class="chart-container">
                            <canvas id="weightChart" class="weight-history-chart"></canvas>
                        </div>
                    </div>

                    <?php if ($rank_perms == 1): ?>
                        <div class="card">
                            <h3>Manage Users</h3>
                            <div class="search-container">
                                <input type="text" id="userSearch" placeholder="Search by email...">
                                <div id="userDropdown" class="dropdown"></div>
                            </div>
                            <div id="userActions" style="display: none;">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="user_id" id="selectedUserId">
                                    <select name="new_plan_id" required>
                                        <option value="">Change Plan</option>
                                        <option value="basic">Basic Plan ($1/month)</option>
                                        <option value="pro">Pro Plan ($2/month)</option>
                                        <option value="elite">Elite Plan ($3/month)</option>
                                    </select>
                                    <button type="submit" name="change_plan">Update Plan</button>
                                </form>
                                <form method="post" style="display: inline; margin-left: 10px;">
                                    <input type="hidden" name="user_id" id="friendsFamilyUserId">
                                    <button type="submit" name="assign_friends_family" class="friends-family-btn">Friends & Family</button>
                                </form>
                                <form method="post" style="display: inline; margin-left: 10px;">
                                    <input type="hidden" name="user_id" id="cancelUserId">
                                    <button type="submit" name="cancel_subscription" class="delete-btn">Cancel Subscription</button>
                                </form>
                                <a href="#" id="editUserLink" class="action-button" style="margin-left: 10px;">Edit User</a>
                                <a href="#" id="viewWorkoutsLink" class="action-button" style="margin-left: 10px;">View Workouts</a>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Active Subscriptions (Latest 5)</h3>
                            <div class="subscription-table">
                                <?php
                                if ($active_subscriptions_result && $active_subscriptions_result->num_rows > 0) {
                                    while ($row = $active_subscriptions_result->fetch_assoc()) {
                                        $sub_first_name = htmlspecialchars($row['first_name']);
                                        $sub_last_name = htmlspecialchars($row['last_name']);
                                        $sub_status = $row['status'];
                                        $sub_plan_id = $row['plan_id'];
                                        $sub_stripe_id = $row['stripe_subscription_id'];
                                        $sub_max_clients = $row['max_clients'];
                                        $sub_next_payment = 'N/A';

                                        $plan_name = $sub_plan_id === 'basic' ? 'Basic ($1/month)' : 
                                                    ($sub_plan_id === 'pro' ? 'Pro ($2/month)' : 
                                                    ($sub_plan_id === 'elite' ? 'Elite ($3/month)' : 'Unknown'));

                                        $status_class = ($sub_status === 'paid' || $sub_status === 'friends_family') ? 'paid' : 'pending';

                                        if ($sub_status === 'paid' && $sub_stripe_id) {
                                            try {
                                                $subscription = \Stripe\Subscription::retrieve($sub_stripe_id);
                                                $sub_next_payment = date('Y-m-d', $subscription->current_period_end);
                                            } catch (\Stripe\Exception\ApiErrorException $e) {
                                                $sub_next_payment = 'Error fetching date';
                                            }
                                        }

                                        echo "<div>";
                                        echo "<strong>$sub_first_name $sub_last_name</strong> | ";
                                        echo "<span class='subscription-status-box status-$status_class'>" . ucfirst($sub_status) . "</span> | ";
                                        echo "$plan_name | Max Clients: " . ($sub_max_clients === NULL ? 'Unlimited' : $sub_max_clients) . " | Next Payment: $sub_next_payment";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div>No active subscriptions found.</div>";
                                }
                                if ($active_subscriptions_result) {
                                    $active_subscriptions_result->free();
                                }
                                ?>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Consultations</h3>
                            <div class="workout-table">
                                <?php
                                $consult_result = $conn->query("SELECT id, name, email, phone, preferred_date, preferred_time, created_at FROM consultations ORDER BY created_at DESC LIMIT 10");
                                if ($consult_result->num_rows > 0) {
                                    while ($consult_row = $consult_result->fetch_assoc()) {
                                        echo "<div class='consultation-entry'>";
                                        echo "<strong>ID:</strong> {$consult_row['id']} | ";
                                        echo htmlspecialchars($consult_row['name']) . " | ";
                                        echo htmlspecialchars($consult_row['email']) . " | ";
                                        echo htmlspecialchars($consult_row['phone']) . " | ";
                                        echo htmlspecialchars($consult_row['preferred_date']) . " " . htmlspecialchars($consult_row['preferred_time']) . " | ";
                                        echo substr($consult_row['created_at'], 0, 10);
                                        echo "</div>";
                                    }
                                    echo "<form method='post' style='margin-top: 20px;'>";
                                    echo "<button type='submit' name='delete_consultations' class='delete-btn'>Delete Consultations</button>";
                                    echo "</form>";
                                } else {
                                    echo "<div>No consultation requests found.</div>";
                                }
                                $consult_result->free();
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-content" id="content-checkins">
                    <div class="card">
                        <?php if ($rank_perms == 1 || $rank_perms == 2): ?>
                            <!-- Coaches/Admins View -->
                            <h3>Weekly Check-in Forms of Clients</h3>
                            <button class="compare-btn" onclick="compareLatestWeeklyCheckins('coach')">Compare Latest and Last Week's Check-ins</button>
                            <div class="checkin-table-container" style="position: relative;">
                                <div style="position: absolute; right: 0; top: -40px;">
                                    <button id="manage-columns-btn-weekly-coach" style="padding: 8px 16px; background-color: #1a73e8; color: white; border: none; border-radius: 4px; cursor: pointer;">Manage Columns</button>
                                    <div id="manage-columns-weekly-coach" class="manage-columns">
                                        <label><input type="checkbox" checked data-column="0" onchange="toggleColumn('weekly-checkin-table-coach', 0)"> #</label><br>
                                        <label><input type="checkbox" checked data-column="1" onchange="toggleColumn('weekly-checkin-table-coach', 1)"> Client Name</label><br>
                                        <label><input type="checkbox" checked data-column="2" onchange="toggleColumn('weekly-checkin-table-coach', 2)"> When</label><br>
                                        <label><input type="checkbox" checked data-column="3" onchange="toggleColumn('weekly-checkin-table-coach', 3)"> Check-in Day</label><br>
                                        <?php foreach ($weekly_form_fields as $index => $field): ?>
                                            <label><input type="checkbox" <?php echo $index < 8 ? 'checked' : ''; ?> data-column="<?php echo $index + 4; ?>" onchange="toggleColumn('weekly-checkin-table-coach', <?php echo $index + 4; ?>)"> <?php echo htmlspecialchars($field['name']); ?></label><br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <table class="checkin-table" id="weekly-checkin-table-coach">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo htmlspecialchars(strlen('Client Name') > 10 ? substr('Client Name', 0, 10) . '...' : 'Client Name'); ?></th>
                                            <th><?php echo htmlspecialchars(strlen('When') > 10 ? substr('When', 0, 10) . '...' : 'When'); ?></th>
                                            <th><?php echo htmlspecialchars(strlen('Check-in Day') > 10 ? substr('Check-in Day', 0, 10) . '...' : 'Check-in Day'); ?></th>
                                            <?php 
                                            // Limit to first 8 fields by default
                                            $visible_fields = array_slice($weekly_form_fields, 0, 8);
                                            foreach ($visible_fields as $field): ?>
                                                <th><?php echo htmlspecialchars(strlen($field['name']) > 10 ? substr($field['name'], 0, 10) . '...' : $field['name']); ?></th>
                                            <?php endforeach; ?>
                                            <?php 
                                            // Add hidden columns for fields beyond the first 8
                                            $hidden_fields = array_slice($weekly_form_fields, 8);
                                            foreach ($hidden_fields as $field): ?>
                                                <th style="display: none;"><?php echo htmlspecialchars(strlen($field['name']) > 10 ? substr($field['name'], 0, 10) . '...' : $field['name']); ?></th>
                                            <?php endforeach; ?>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="weeklyCheckinTableBodyCoach">
                                        <?php if (!empty($weekly_checkin_table_data)): ?>
                                            <?php foreach ($weekly_checkin_table_data as $entry): ?>
                                                <tr data-checkin-id="<?php echo $entry['id']; ?>" data-user-id="<?php echo $entry['user_id']; ?>">
                                                    <td><?php echo htmlspecialchars($entry['index']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['when']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['checkin_day']); ?></td>
                                                    <?php 
                                                    // Display first 8 fields
                                                    foreach ($visible_fields as $field): ?>
                                                        <td class="truncate"><?php echo htmlspecialchars(strlen($entry[$field['name']]) > 20 ? substr($entry[$field['name']], 0, 20) . '...' : $entry[$field['name']]); ?></td>
                                                    <?php endforeach; ?>
                                                    <?php 
                                                    // Add hidden columns for fields beyond the first 8
                                                    foreach ($hidden_fields as $field): ?>
                                                        <td class="truncate" style="display: none;"><?php echo htmlspecialchars(strlen($entry[$field['name']]) > 20 ? substr($entry[$field['name']], 0, 20) . '...' : $entry[$field['name']]); ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="actions">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                        <div class="dropdown">
                                                            <a href="/view_checkin.php?checkin_id=<?php echo $entry['id']; ?>&form_type=weekly&user_id=<?php echo $entry['user_id']; ?>">View</a>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="checkin_id" value="<?php echo $entry['id']; ?>">
                                                                <button type="submit" name="delete_checkin" class="delete">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo count($weekly_form_fields) + 5; ?>">No weekly check-in data available for your clients.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="pagination">
                                    <span>Show <?php echo $weekly_checkin_pagination['per_page']; ?> per page</span>
                                    <span style="margin-left: 10px;">Showing <?php echo count($weekly_checkin_table_data); ?> out of <?php echo $weekly_checkin_pagination['total_records']; ?> entries</span>
                                    <?php if ($weekly_checkin_pagination['total_pages'] > 1): ?>
                                        <div style="margin-top: 10px;">
                                            <?php if ($weekly_checkin_pagination['page'] > 1): ?>
                                                <a href="/dash?weekly_checkin_page=<?php echo $weekly_checkin_pagination['page'] - 1; ?>"><i class="fas fa-arrow-left"></i></a>
                                            <?php endif; ?>
                                            <span style="margin: 0 10px;"><?php echo $weekly_checkin_pagination['page']; ?></span>
                                            <?php if ($weekly_checkin_pagination['page'] < $weekly_checkin_pagination['total_pages']): ?>
                                                <a href="/dash?weekly_checkin_page=<?php echo $weekly_checkin_pagination['page'] + 1; ?>"><i class="fas fa-arrow-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="comparison-details" id="weekly-comparison-details-coach"></div>

                            <h3>Daily Client Check-ins</h3>
                            <div class="checkin-table-container" style="position: relative;">
                                <div style="position: absolute; right: 0; top: -40px;">
                                    <button id="manage-columns-btn-daily-coach" style="padding: 8px 16px; background-color: #1a73e8; color: white; border: none; border-radius: 4px; cursor: pointer;">Manage Columns</button>
                                    <div id="manage-columns-daily-coach" class="manage-columns">
                                        <label><input type="checkbox" checked data-column="0" onchange="toggleColumn('daily-checkin-table-coach', 0)"> #</label><br>
                                        <label><input type="checkbox" checked data-column="1" onchange="toggleColumn('daily-checkin-table-coach', 1)"> Client Name</label><br>
                                        <label><input type="checkbox" checked data-column="2" onchange="toggleColumn('daily-checkin-table-coach', 2)"> When</label><br>
                                        <?php foreach ($daily_form_fields as $index => $field): ?>
                                            <label><input type="checkbox" checked data-column="<?php echo $index + 3; ?>" onchange="toggleColumn('daily-checkin-table-coach', <?php echo $index + 3; ?>)"> <?php echo htmlspecialchars($field['name']); ?></label><br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <table class="checkin-table" id="daily-checkin-table-coach">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo htmlspecialchars(strlen('Client Name') > 10 ? substr('Client Name', 0, 10) . '...' : 'Client Name'); ?></th>
                                            <th><?php echo htmlspecialchars(strlen('When') > 10 ? substr('When', 0, 10) . '...' : 'When'); ?></th>
                                            <?php foreach ($daily_form_fields as $field): ?>
                                                <th><?php echo htmlspecialchars(strlen($field['name']) > 10 ? substr($field['name'], 0, 10) . '...' : $field['name']); ?></th>
                                            <?php endforeach; ?>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="dailyCheckinTableBodyCoach">
                                        <?php if (!empty($daily_checkin_table_data)): ?>
                                            <?php foreach ($daily_checkin_table_data as $entry): ?>
                                                <tr data-checkin-id="<?php echo $entry['id']; ?>" data-user-id="<?php echo $entry['user_id']; ?>">
                                                    <td><?php echo htmlspecialchars($entry['index']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['when']); ?></td>
                                                    <?php foreach ($daily_form_fields as $field): ?>
                                                        <td class="truncate"><?php echo htmlspecialchars($entry[$field['name']]); ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="actions">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                        <div class="dropdown">
                                                            <a href="/view_checkin.php?checkin_id=<?php echo $entry['id']; ?>&form_type=daily&user_id=<?php echo $entry['user_id']; ?>">View</a>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="checkin_id" value="<?php echo $entry['id']; ?>">
                                                                <button type="submit" name="delete_checkin" class="delete">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo count($daily_form_fields) + 4; ?>">No daily check-in data available for your clients.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="pagination">
                                    <span>Show <?php echo $daily_checkin_pagination['per_page']; ?> per page</span>
                                    <span style="margin-left: 10px;">Showing <?php echo count($daily_checkin_table_data); ?> out of <?php echo $daily_checkin_pagination['total_records']; ?> entries</span>
                                    <?php if ($daily_checkin_pagination['total_pages'] > 1): ?>
                                        <div style="margin-top: 10px;">
                                            <?php if ($daily_checkin_pagination['page'] > 1): ?>
                                                <a href="/dash?daily_checkin_page=<?php echo $daily_checkin_pagination['page'] - 1; ?>"><i class="fas fa-arrow-left"></i></a>
                                            <?php endif; ?>
                                            <span style="margin: 0 10px;"><?php echo $daily_checkin_pagination['page']; ?></span>
                                            <?php if ($daily_checkin_pagination['page'] < $daily_checkin_pagination['total_pages']): ?>
                                                <a href="/dash?daily_checkin_page=<?php echo $daily_checkin_pagination['page'] + 1; ?>"><i class="fas fa-arrow-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Clients View -->
                            <h3>Weekly Check-in</h3>
                            <div class="checkin-form-container" id="weekly-checkin">
                                <?php if (!empty($weekly_checkin_form)): ?>
                                    <?php if (!$is_checkin_day): ?>
                                        <p>Weekly check-ins can only be submitted on your designated check-in day: <strong><?php echo htmlspecialchars($checkin_day); ?></strong>. Today is <strong><?php echo date('l'); ?></strong>.</p>
                                    <?php endif; ?>
                                    <br>
                                    <form method="post" class="checkin-form">
                                        <input type="hidden" name="form_type" value="weekly">
                                        <p>Form Fields: <?php echo count($weekly_checkin_form); ?></p>
                                        <?php foreach ($weekly_checkin_form as $index => $field): ?>
                                            <br>
                                            <div class="form-group">
                                                <label><?php echo htmlspecialchars($field['name']); ?>:</label>
                                                <?php if ($field['type'] === 'number'): ?>
                                                    <input 
                                                        type="number" 
                                                        name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                        placeholder="Enter <?php echo htmlspecialchars($field['name']); ?>" 
                                                        min="0" 
                                                        step="0.01" 
                                                        required
                                                    >
                                                    <?php if (strtolower($field['name']) === 'weight'): ?>
                                                        <span><?php echo htmlspecialchars($view_unit_preference); ?></span>
                                                    <?php endif; ?>
                                                <?php elseif ($field['type'] === 'rating'): ?>
                                                    <div class="rating">
                                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                                            <input 
                                                                type="radio" 
                                                                name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                                value="<?php echo $i; ?>" 
                                                                id="weekly-<?php echo $index; ?>-<?php echo $i; ?>"
                                                                <?php echo $i === 5 ? 'checked' : ''; ?>
                                                            >
                                                            <label for="weekly-<?php echo $index; ?>-<?php echo $i; ?>"><?php echo $i; ?></label>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php elseif ($field['type'] === 'text'): ?>
                                                    <textarea 
                                                        name="data[<?php echo htmlspecialchars($field['name']); ?>]" 
                                                        placeholder="Enter <?php echo htmlspecialchars($field['name']); ?>" 
                                                        rows="3"
                                                    ></textarea>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="submit" name="submit_checkin" <?php echo $is_checkin_day ? '' : 'disabled'; ?>>Submit</button>
                                    </form>
                                <?php else: ?>
                                    <p>No weekly check-in form has been set up by your coach. Please contact them to configure your weekly check-in form.</p>
                                <?php endif; ?>
                            </div>

                            <h3>Weekly Check-in History</h3>
                            <button class="compare-btn" onclick="compareLatestWeeklyCheckins('client')">Compare Latest and Last Week's Check-ins</button>
                            <div class="checkin-table-container">
                                <table class="checkin-table" id="weekly-checkin-table-client">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo htmlspecialchars(strlen('When') > 10 ? substr('When', 0, 10) . '...' : 'When'); ?></th>
                                            <?php foreach ($weekly_checkin_form as $field): ?>
                                                <th><?php echo htmlspecialchars(strlen($field['name']) > 10 ? substr($field['name'], 0, 10) . '...' : $field['name']); ?></th>
                                            <?php endforeach; ?>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="weeklyCheckinTableBodyClient">
                                        <?php if (!empty($weekly_checkin_history) && !empty($weekly_checkin_form)): ?>
                                            <?php foreach ($weekly_checkin_history as $entry): ?>
                                                <tr data-checkin-id="<?php echo $entry['id']; ?>" data-user-id="<?php echo $view_user_id; ?>">
                                                    <td><?php echo htmlspecialchars($entry['index']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['when']); ?></td>
                                                    <?php foreach ($weekly_checkin_form as $field): ?>
                                                        <td>
                                                            <?php echo htmlspecialchars($entry[$field['name']]); ?>
                                                            <?php if (strtolower($field['name']) === 'weight'): ?>
                                                                <?php echo ' ' . htmlspecialchars($view_unit_preference); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="actions">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                        <div class="dropdown">
                                                            <a href="/view_checkin.php?checkin_id=<?php echo $entry['id']; ?>&form_type=weekly&user_id=<?php echo $view_user_id; ?>">View</a>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="checkin_id" value="<?php echo $entry['id']; ?>">
                                                                <button type="submit" name="delete_checkin" class="delete">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo count($weekly_checkin_form) + 2; ?>">No weekly check-in history available or no form template defined by your coach.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="pagination">
                                    <span>Show <?php echo $weekly_checkin_pagination['per_page']; ?> per page</span>
                                    <span style="margin-left: 10px;">Showing <?php echo count($weekly_checkin_history); ?> out of <?php echo $weekly_checkin_pagination['total_records']; ?> entries</span>
                                    <?php if ($weekly_checkin_pagination['total_pages'] > 1): ?>
                                        <div style="margin-top: 10px;">
                                            <?php if ($weekly_checkin_pagination['page'] > 1): ?>
                                                <a href="/dash?weekly_checkin_page=<?php echo $weekly_checkin_pagination['page'] - 1; ?>"><i class="fas fa-arrow-left"></i></a>
                                            <?php endif; ?>
                                            <span style="margin: 0 10px;"><?php echo $weekly_checkin_pagination['page']; ?></span>
                                            <?php if ($weekly_checkin_pagination['page'] < $weekly_checkin_pagination['total_pages']): ?>
                                                <a href="/dash?weekly_checkin_page=<?php echo $weekly_checkin_pagination['page'] + 1; ?>"><i class="fas fa-arrow-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="comparison-details" id="weekly-comparison-details-client"></div>

                            <h3>Daily Check-in History</h3>
                            <div class="checkin-table-container">
                                <table class="checkin-table" id="daily-checkin-table-client">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo htmlspecialchars(strlen('When') > 10 ? substr('When', 0, 10) . '...' : 'When'); ?></th>
                                            <?php foreach ($daily_checkin_form as $field): ?>
                                                <th><?php echo htmlspecialchars(strlen($field['name']) > 10 ? substr($field['name'], 0, 10) . '...' : $field['name']); ?></th>
                                            <?php endforeach; ?>
                                            <th></th>
                                        </tr>
                                                                       </thead>
                                    <tbody id="dailyCheckinTableBodyClient">
                                        <?php if (!empty($daily_checkin_history) && !empty($daily_checkin_form)): ?>
                                            <?php foreach ($daily_checkin_history as $entry): ?>
                                                <tr data-checkin-id="<?php echo $entry['id']; ?>" data-user-id="<?php echo $view_user_id; ?>">
                                                    <td><?php echo htmlspecialchars($entry['index']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['when']); ?></td>
                                                    <?php foreach ($daily_checkin_form as $field): ?>
                                                        <td>
                                                            <?php echo htmlspecialchars($entry[$field['name']]); ?>
                                                            <?php if (strtolower($field['name']) === 'weight'): ?>
                                                                <?php echo ' ' . htmlspecialchars($view_unit_preference); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="actions">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                        <div class="dropdown">
                                                            <a href="/view_checkin.php?checkin_id=<?php echo $entry['id']; ?>&form_type=daily&user_id=<?php echo $view_user_id; ?>">View</a>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="checkin_id" value="<?php echo $entry['id']; ?>">
                                                                <button type="submit" name="delete_checkin" class="delete">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo count($daily_checkin_form) + 2; ?>">No daily check-in history available or no form template defined by your coach.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="pagination">
                                    <span>Show <?php echo $daily_checkin_pagination['per_page']; ?> per page</span>
                                    <span style="margin-left: 10px;">Showing <?php echo count($daily_checkin_history); ?> out of <?php echo $daily_checkin_pagination['total_records']; ?> entries</span>
                                    <?php if ($daily_checkin_pagination['total_pages'] > 1): ?>
                                        <div style="margin-top: 10px;">
                                            <?php if ($daily_checkin_pagination['page'] > 1): ?>
                                                <a href="/dash?daily_checkin_page=<?php echo $daily_checkin_pagination['page'] - 1; ?>"><i class="fas fa-arrow-left"></i></a>
                                            <?php endif; ?>
                                            <span style="margin: 0 10px;"><?php echo $daily_checkin_pagination['page']; ?></span>
                                            <?php if ($daily_checkin_pagination['page'] < $daily_checkin_pagination['total_pages']): ?>
                                                <a href="/dash?daily_checkin_page=<?php echo $daily_checkin_pagination['page'] + 1; ?>"><i class="fas fa-arrow-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-content" id="content-gallery">
                    <div class="card">
                        <h3>Gallery</h3>
                        <p>Gallery content will be displayed here.</p>
                    </div>
                </div>

                <div class="tab-content" id="content-qa">
                    <div class="card">
                        <h3>Q&A</h3>
                        <p>Q&A content will be displayed here.</p>
                    </div>
                </div>

                <div class="tab-content" id="content-nutrition">
                    <div class="card">
                        <h3>Macros</h3>
                        <p>Protein: <?php echo $protein !== null ? htmlspecialchars($protein) : '0'; ?>g</p>
                        <p>Fats: <?php echo $fats !== null ? htmlspecialchars($fats) : '0'; ?>g</p>
                        <p>Carbs: <?php echo $carbs !== null ? htmlspecialchars($carbs) : '0'; ?>g</p>
                    </div>
                </div>

                <div class="tab-content" id="content-supplements">
                    <div class="card">
                        <h3>Supplements</h3>
                        <p>Supplements content will be displayed here.</p>
                    </div>
                </div>

                <div class="tab-content" id="content-workout">
                    <div class="card">
                        <h3>Workout</h3>
                        <p>Workout content will be displayed here.</p>
                    </div>
                </div>

                <div class="tab-content" id="content-calendar">
                    <div class="card">
                        <h3>Calendar</h3>
                        <p>Calendar content will be displayed here.</p>
                    </div>
                </div>

                <div class="tab-content" id="content-logs">
                    <div class="card">
                        <h3>Logs</h3>
                        <p>Logs content will be displayed here.</p>
                    </div>
                </div>

                <?php if ($rank_perms == 1 || $rank_perms == 2): ?>
                    <div class="tab-content" id="content-manage-clients">
                        <div class="card manage-clients-section">
                            <h3>Manage Your Clients</h3>
                            <div class="stats">
                                <p><i class="fas fa-users"></i> <strong>Max Clients Allowed:</strong> <?php echo $subscription_max_clients === NULL ? 'Unlimited' : $subscription_max_clients; ?></p>
                                <p><i class="fas fa-user-check"></i> <strong>Current Clients:</strong> <?php echo $total_clients; ?></p>
                            </div>
                            <?php if ($rank_perms == 1 || $subscription_max_clients === NULL || count($coach_clients) < $subscription_max_clients): ?>
                                <form method="post">
                                    <h4>Add New Client</h4>
                                    <br>
                                    <div class="form-group">
                                        <label>First Name:</label>
                                        <input type="text" name="client_first_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name:</label>
                                        <input type="text" name="client_last_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email:</label>
                                        <input type="email" name="client_email" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Password:</label>
                                        <input type="text" name="client_password" value="p@ssword" readonly>
                                        <small>(Preset to "p@ssword")</small>
                                    </div>
                                    <button type="submit" name="add_client">Add Client</button>
                                </form>
                            <?php else: ?>
                                <p>You have reached your client limit. Upgrade your plan to add more clients.</p>
                            <?php endif; ?>
                            <div class="client-list">
                                <h4>Your Clients (Latest 5)</h4>
                                <?php if (!empty($coach_clients)): ?>
                                    <?php foreach ($coach_clients as $client): ?>
                                        <div>
                                            <i class="fas fa-user"></i>
                                            <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                            <span><?php echo htmlspecialchars($client['email']); ?></span>
                                            <a href="/dash?user_id=<?php echo $client['id']; ?>">View/Edit</a>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($total_pages > 1): ?>
                                        <div class="pagination">
                                            <?php if ($page > 1): ?>
                                                <a href="/dash?client_page=<?php echo $page - 1; ?>"><i class="fas fa-arrow-left"></i> Previous</a>
                                            <?php endif; ?>
                                            <?php if ($page < $total_pages): ?>
                                                <a href="/dash?client_page=<?php echo $page + 1; ?>">Next <i class="fas fa-arrow-right"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p>No clients assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Weight Chart
        window.weightData = {
            labels: <?php echo json_encode($dates); ?>,
            weights: <?php echo json_encode($weights); ?>
        };
        window.unitPreference = '<?php echo $view_unit_preference; ?>';
        window.users = <?php echo $rank_perms == 1 ? json_encode($users) : '[]'; ?>;

        const weightCtx = document.getElementById('weightChart')?.getContext('2d');
        if (weightCtx) {
            new Chart(weightCtx, {
                type: 'bar',
                data: {
                    labels: window.weightData.labels,
                    datasets: [{
                        label: 'Weight (' + window.unitPreference + ')',
                        data: window.weightData.weights,
                        backgroundColor: '#1a73e8',
                        borderColor: '#1a73e8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: { display: true, text: 'Weight (' + window.unitPreference + ')' },
                            ticks: { precision: 1 }
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // Tab Switching Logic
        const topTabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        topTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                topTabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                const content = document.getElementById(`content-${tabId}`);
                if (content) {
                    content.classList.add('active');
                }

                document.querySelectorAll('.checkin-details').forEach(detail => detail.style.display = 'none');
                document.querySelectorAll('.comparison-details').forEach(detail => detail.style.display = 'none');
            });
        });

        // Sidebar Dashboard Click
        const sidebarDashboard = document.querySelector('.sidebar-item[data-tab="dashboard"]');
        if (sidebarDashboard) {
            sidebarDashboard.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.sidebar-item').forEach(s => s.classList.remove('active'));
                sidebarDashboard.classList.add('active');
                topTabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                const dashboardTab = document.querySelector('.tab[data-tab="dashboard"]');
                if (dashboardTab) {
                    dashboardTab.classList.add('active');
                }
                const dashboardContent = document.getElementById('content-dashboard');
                if (dashboardContent) {
                    dashboardContent.classList.add('active');
                }

                document.querySelectorAll('.checkin-details').forEach(detail => detail.style.display = 'none');
                document.querySelectorAll('.comparison-details').forEach(detail => detail.style.display = 'none');
            });
        }

        // User Search Functionality
        const userSearch = document.getElementById('userSearch');
        const userDropdown = document.getElementById('userDropdown');
        const userActions = document.getElementById('userActions');
        const selectedUserId = document.getElementById('selectedUserId');
        const friendsFamilyUserId = document.getElementById('friendsFamilyUserId');
        const cancelUserId = document.getElementById('cancelUserId');
        const editUserLink = document.getElementById('editUserLink');
        const viewWorkoutsLink = document.getElementById('viewWorkoutsLink');

        if (userSearch && userDropdown && userActions && selectedUserId && friendsFamilyUserId && cancelUserId && editUserLink && viewWorkoutsLink) {
            userSearch.addEventListener('input', () => {
                const query = userSearch.value.toLowerCase();
                userDropdown.innerHTML = '';
                if (query.length < 2) {
                    userDropdown.style.display = 'none';
                    userActions.style.display = 'none';
                    return;
                }

                const filteredUsers = window.users.filter(user => 
                    user.email.toLowerCase().includes(query) || 
                    (user.first_name + ' ' + user.last_name).toLowerCase().includes(query)
                );

                if (filteredUsers.length === 0) {
                    userDropdown.innerHTML = '<div>No users found</div>';
                } else {
                    filteredUsers.forEach(user => {
                        const div = document.createElement('div');
                        div.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
                        div.style.padding = '10px';
                        div.style.cursor = 'pointer';
                        div.addEventListener('click', () => {
                            userSearch.value = user.email;
                            userDropdown.style.display = 'none';
                            userActions.style.display = 'block';
                            selectedUserId.value = user.id;
                            friendsFamilyUserId.value = user.id;
                            cancelUserId.value = user.id;
                            editUserLink.href = `/edit-user?user_id=${user.id}`;
                            viewWorkoutsLink.href = `/workouts?user_id=${user.id}`;
                        });
                        userDropdown.appendChild(div);
                    });
                }
                userDropdown.style.display = 'block';
            });
        }

        // Success Notification
        const notification = document.getElementById('successNotification');
        if (notification) {
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Debug Form Submission
        const checkinForms = document.querySelectorAll('.checkin-form');
        checkinForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                console.log('Check-in form submitted:', new FormData(form));
            });
        });

        // Error Notification Function
        function showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.style.backgroundColor = '#dc3545';
            notification.textContent = message;
            document.body.appendChild(notification);
            notification.style.display = 'block';
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Hide Check-in Details
        window.hideDetails = function(formType, userType) {
            document.getElementById(`${formType}-checkin-details-${userType}`).style.display = 'none';
            document.getElementById(`${formType}-comparison-details-${userType}`).style.display = 'none';
        };

        // Compare Latest and Last Week's Check-ins
        window.compareLatestWeeklyCheckins = function(userType) {
            const table = document.getElementById(`weekly-checkin-table-${userType}`);
            if (!table) {
                showErrorNotification('No weekly check-in data available to compare.');
                console.warn(`Table weekly-checkin-table-${userType} not found.`);
                return;
            }
            const rows = table.querySelectorAll('tbody tr');
            console.log('Number of rows in weekly check-in table:', rows.length);

            if (rows.length < 2) {
                showErrorNotification('Not enough check-ins to compare. You need at least two weekly check-ins.');
                console.warn('Not enough check-ins to compare:', rows.length);
                return;
            }

            const latestCheckinId = rows[0].getAttribute('data-checkin-id');
            const previousCheckinId = rows[1].getAttribute('data-checkin-id');
            const userId = rows[0].getAttribute('data-user-id');

            console.log('Comparing check-ins:', { latestCheckinId, previousCheckinId, userId });

            if (!latestCheckinId || !previousCheckinId || !userId) {
                showErrorNotification('Missing required parameters for comparison.');
                console.error('Missing parameters for comparison:', { latestCheckinId, previousCheckinId, userId });
                return;
            }

            const url = `https://${window.location.host}/compare_checkins.php?latest_id=${latestCheckinId}&previous_id=${previousCheckinId}&user_id=${userId}`;
            console.log('Fetching comparison data from:', url);

            fetch(url)
                .then(response => {
                    console.log('Fetch response status:', response.status);
                    if (!response.ok) {
                        if (response.status === 404) {
                            throw new Error('Check-ins not found or you do not have permission to view them.');
                        } else if (response.status === 500) {
                            throw new Error('Server error during comparison. Please try again later.');
                        } else {
                            throw new Error(`Request failed with status: ${response.status}`);
                        }
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Fetch response data:', data);
                    if (data.error) {
                        showErrorNotification(`Error: ${data.error}`);
                        return;
                    }

                    const comparisonDiv = document.getElementById(`weekly-comparison-details-${userType}`);
                    let html = `<h3>Comparison: Latest vs Last Week</h3>`;
                    html += `<div class="comparison-row"><div><strong>Latest Check-in (${data.latest.when})</strong></div><div><strong>Last Week (${data.previous.when})</strong></div></div>`;
                    for (const [key, value] of Object.entries(data.latest.submission_data)) {
                        const prevValue = data.previous.submission_data[key] || '-';
                        html += `<div class="comparison-row"><div><strong>${key}:</strong> ${value || '-'}</div><div><strong>${key}:</strong> ${prevValue}</div></div>`;
                    }
                    html += `<a href="#" class="back-btn" onclick="hideDetails('weekly', '${userType}'); return false;">Go Back</a>`;
                    comparisonDiv.innerHTML = html;
                    comparisonDiv.style.display = 'block';

                    document.getElementById(`weekly-checkin-details-${userType}`).style.display = 'none';
                })
                .catch(error => {
                    console.error('Error fetching comparison data:', error);
                    showErrorNotification(`Unable to load comparison: ${error.message}. Please try again or contact support at support@strv.com`);
                });
        };

        // Disable Compare Button if Not Enough Check-ins
        function updateCompareButtonState(userType) {
            const table = document.getElementById(`weekly-checkin-table-${userType}`);
            if (!table) {
                console.warn(`Table weekly-checkin-table-${userType} not found on the page.`);
                return;
            }
            const rows = table.querySelectorAll('tbody tr');
            const compareButton = table.parentElement.previousElementSibling;
            if (rows.length < 2) {
                compareButton.disabled = true;
                compareButton.title = 'You need at least two weekly check-ins to compare.';
                compareButton.style.opacity = '0.5';
                compareButton.style.cursor = 'not-allowed';
            } else {
                compareButton.disabled = false;
                compareButton.title = '';
                compareButton.style.opacity = '1';
                compareButton.style.cursor = 'pointer';
            }
        }

        // Update Compare Button State on Page Load
        const userType = '<?php echo ($rank_perms == 1 || $rank_perms == 2) ? "coach" : "client"; ?>';
        updateCompareButtonState(userType);

        // Toggle Manage Columns dropdown visibility
        function toggleManageColumns(tableId) {
            console.log('Toggling manage columns for table:', tableId); // Debug log
            const dropdown = document.getElementById(`manage-columns-${tableId}`);
            if (!dropdown) {
                console.error(`Dropdown manage-columns-${tableId} not found.`);
                return;
            }
            const currentDisplay = dropdown.style.display;
            dropdown.style.display = currentDisplay === 'block' ? 'none' : 'block';
            console.log('Dropdown display set to:', dropdown.style.display); // Debug log
        }

        // Show/hide table columns based on checkbox state
        function toggleColumn(tableId, columnIndex) {
            console.log(`Toggling column ${columnIndex} for table ${tableId}`); // Debug log
            const table = document.getElementById(tableId);
            if (!table) {
                console.error(`Table ${tableId} not found.`);
                return;
            }
            const rows = table.getElementsByTagName('tr');
            const checkbox = document.querySelector(`#manage-columns-${tableId} input[data-column="${columnIndex}"]`);
            if (!checkbox) {
                console.error(`Checkbox for column ${columnIndex} not found in manage-columns-${tableId}.`);
                return;
            }
            const display = checkbox.checked ? '' : 'none';
            console.log(`Setting display to ${display} for column ${columnIndex}`); // Debug log
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName(i === 0 ? 'th' : 'td');
                if (cells[columnIndex]) {
                    cells[columnIndex].style.display = display;
                } else {
                    console.warn(`Cell at column ${columnIndex} not found in row ${i} of table ${tableId}`);
                }
            }
        }

        // Close dropdown if clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.manage-columns');
            dropdowns.forEach(dropdown => {
                const button = dropdown.previousElementSibling;
                if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                    dropdown.style.display = 'none';
                    console.log('Dropdown closed due to outside click:', dropdown.id); // Debug log
                }
            });
        });

        // Add event listeners for Manage Columns buttons
        const manageColumnsBtnWeekly = document.getElementById('manage-columns-btn-weekly-coach');
        if (manageColumnsBtnWeekly) {
            manageColumnsBtnWeekly.addEventListener('click', () => {
                toggleManageColumns('weekly-coach');
            });
        }

        const manageColumnsBtnDaily = document.getElementById('manage-columns-btn-daily-coach');
        if (manageColumnsBtnDaily) {
            manageColumnsBtnDaily.addEventListener('click', () => {
                toggleManageColumns('daily-coach');
            });
        }

        // Check URL for view_checkin parameter and adjust tab visibility
        const urlParams = new URLSearchParams(window.location.search);
        const viewCheckinId = urlParams.get('view_checkin');
        if (viewCheckinId) {
            topTabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            const checkinsTab = document.querySelector('.tab[data-tab="checkins"]');
            checkinsTab.classList.add('active');
            document.getElementById('content-checkins').classList.add('active');
        }
    });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>