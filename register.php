<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "aldimar_db";

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = "Unknown"; // Default role

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if username or email already exists
    $check = $conn->prepare("SELECT * FROM Tbl_user WHERE username=? OR email=?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "Username or Email already exists. Please try another.";
        $messageType = "error";
    } else {
        // Insert new user
        $sql = "INSERT INTO Tbl_user (username, password, role, email, phone, name) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $username, $hashedPassword, $role, $email, $phone, $name);

        if ($stmt->execute()) {
            // Send Notification to Admin
            $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('New User Registered: $username', 'Admin', NOW())");

            // Redirect to login.php which has been refactored
            echo "<script>alert('Registration successful! Please wait for Admin to assign your role.'); window.location.href='login.php';</script>";
            exit;
        } else {
            $message = "Registration failed. Please try again.";
            $messageType = "error";
        }
    }
    $check->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register - Aldimar Legacy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .register-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        .register-box h2 {
            margin-bottom: 20px;
            font-weight: bold;
            color: #5e4b8b;
        }

        .register-box input[type="text"],
        .register-box input[type="email"],
        .register-box input[type="tel"],
        .register-box input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }

        /* Eye icon styling */
        .input-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 15px;
        }

        .input-wrapper input[type="password"],
        .input-wrapper input[type="text"] {
            width: 100%;
            padding: 10px;
            padding-right: 40px;
            /* Space for the eye icon */
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            height: 40px;
            margin-bottom: 0;
        }

        .toggle-icon {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
            fill: #7a5dca;
            user-select: none;
        }

        .register-box button {
            background-color: #7a5dca;
            color: white;
            border: none;
            padding: 10px 0;
            width: 100%;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .register-box button:hover {
            background-color: #6a4cba;
        }

        .login-link {
            margin-top: 15px;
            font-size: 14px;
        }

        .login-link a {
            color: #5e4b8b;
            font-weight: bold;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .error {
            background: #fdd;
            color: #d00;
        }

        .success {
            background: #dfd;
            color: #080;
        }
    </style>
</head>

<body>
    <div class="register-box">
        <h2>User Registration</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="tel" name="phone" placeholder="Phone Number" required>
            <input type="text" name="username" placeholder="Username" required>

            <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <!-- Eye Icon (Show) -->
                <svg class="toggle-icon" id="toggle_register" onclick="togglePassword('password', 'toggle_register')"
                    viewBox="0 0 24 24">
                    <path
                        d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                </svg>
            </div>

            <button type="submit">REGISTER</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (input.type === "password") {
                input.type = "text";
                // Switch to 'Hide' icon (Slash eye)
                icon.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
            } else {
                input.type = "password";
                // Switch back to 'Show' icon (Normal eye)
                icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            }
        }
    </script>
</body>

</html>