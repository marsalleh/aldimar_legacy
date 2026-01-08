<?php
session_start();

require_once 'db_config.php';


$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        // Check if user exists with matching username AND email
        $stmt = $conn->prepare("SELECT userID, email FROM Tbl_user WHERE username = ? AND email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $userID = $user['userID'];
            $userEmail = $user['email'];

            // Update password
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE Tbl_user SET password = ? WHERE userID = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userID);

            if ($updateStmt->execute()) {
                $message = "Password updated successfully. You can now login.";
                $messageType = "success";

                // Email Notification Logic
                $to = $userEmail;
                $subject = "Password Changed - Aldimar Legacy";
                $emailBody = "Hello $username,\n\nYour password has been successfully changed.\nIf this was not you, please contact support immediately.\n\nRegards,\nAldimar Legacy Team";
                $headers = "From: no-reply@aldimarlegacy.com";

                // Attempt to send email suppression of potential warnings if server not configured
                if (@mail($to, $subject, $emailBody, $headers)) {
                    $message .= " Notification email sent.";
                } else {
                    // In local env without SMTP, this might fail, but we don't block the user
                    // $message .= " (Email could not be sent in this environment)";
                }

                echo "<script>alert('$message'); window.location.href='index.php';</script>";
                exit; // Stop execution after redirect
            } else {
                $message = "Error updating password in database.";
                $messageType = "error";
            }
            $updateStmt->close();
        } else {
            $message = "Invalid Username or Email combination.";
            $messageType = "error";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Aldimar Legacy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #5e4b8b;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            background-color: #7a5dca;
            color: white;
            border: none;
            padding: 10px 0;
            width: 100%;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #6a4cba;
        }

        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
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

        .back-link {
            display: block;
            margin-top: 15px;
            font-size: 13px;
        }

        .back-link a {
            color: #7a5dca;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Reset Password</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <p style="margin-bottom:20px; font-size:14px; color:#555;">Enter your details to reset your password
            immediately.</p>

        <style>
            .password-wrapper {
                position: relative;
                width: 100%;
                margin-bottom: 15px;
            }

            .password-wrapper input {
                margin-bottom: 0;
                /* Input margin handled by wrapper */
                padding-right: 40px;
                /* Space for the eye icon */
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
        </style>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Registered Email" required>

            <div class="password-wrapper">
                <input type="password" name="new_password" id="new_password" placeholder="New Password" required>
                <!-- Eye Icon (Show) -->
                <svg class="toggle-icon" id="toggle_new" onclick="togglePassword('new_password', 'toggle_new')"
                    viewBox="0 0 24 24">
                    <path
                        d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                </svg>
            </div>

            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password"
                    required>
                <svg class="toggle-icon" id="toggle_confirm"
                    onclick="togglePassword('confirm_password', 'toggle_confirm')" viewBox="0 0 24 24">
                    <path
                        d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                </svg>
            </div>

            <button type="submit">Reset Password</button>
        </form>

        <div class="back-link">
            <a href="index.php">Back to Login</a>
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