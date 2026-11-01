<?php
session_start();
require_once "database.php";

$error = "";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST['email'] ?? ""));
    $password = $_POST['password'] ?? "";

    if (
        !is_string($email) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !is_string($password)
    ) {
        $error = "Invalid email or password.";
    } else {
        // Remove expired attempts older than 15 minutes.
        $conn->query(
            "DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        // Count failed attempts for this email and IP.
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $attemptStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM login_attempts
             WHERE email = ?
               AND ip_address = ?
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        $attemptStmt->bind_param("ss", $email, $ipAddress);
        $attemptStmt->execute();
        $attemptResult = $attemptStmt->get_result()->fetch_assoc();
        $attemptStmt->close();

        if ((int)($attemptResult['total'] ?? 0) >= 5) {
            $error = "Too many failed login attempts. Please try again in 15 minutes.";
        } else {
            $stmt = $conn->prepare(
                "SELECT id, full_name, email, password, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                // Clear failed attempts after a successful login.
                $clearStmt = $conn->prepare(
                    "DELETE FROM login_attempts
                     WHERE email = ? AND ip_address = ?"
                );

                $clearStmt->bind_param("ss", $email, $ipAddress);
                $clearStmt->execute();
                $clearStmt->close();

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $stmt->close();

                header("Location: index.php");
                exit;
            } else {
                // Record failed login attempt.
                $recordStmt = $conn->prepare(
                    "INSERT INTO login_attempts (email, ip_address)
                     VALUES (?, ?)"
                );

                $recordStmt->bind_param("ss", $email, $ipAddress);
                $recordStmt->execute();
                $recordStmt->close();

                $error = "Invalid email or password.";
            }

            $stmt->close();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SmartFind</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .auth-container {
            max-width: 440px;
            margin: 65px auto;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,.07);
        }

        .auth-container h1 {
            color: #172554;
            margin-bottom: 8px;
        }

        .auth-container p {
            color: #64748b;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        .auth-button {
            width: 100%;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 7px;
        }

        .auth-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<header class="navbar">
    <div class="logo">Smart<span>Find</span></div>
    <nav>
        <a href="index.php">Home</a>
        <a href="register.php">Register</a>
    </nav>
</header>

<div class="auth-container">

    <h1>Welcome Back!</h1>
    <p>Login to continue using SmartFind.</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="username"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>

        <button type="submit" class="btn primary auth-button">
            Login
        </button>

    </form>

    <div class="auth-link">
        Don't have an account?
        <a href="register.php">Create one</a>
    </div>

</div>

</body>
</html>
```
