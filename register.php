```php
<?php
session_start();
require_once "database.php";

$error = "";
$success = "";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name'] ?? "");
    $email = strtolower(trim($_POST['email'] ?? ""));
    $phone = trim($_POST['phone'] ?? "");
    $password = $_POST['password'] ?? "";
    $confirm_password = $_POST['confirm_password'] ?? "";

    if (
        $full_name === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8
    ) {
        $error = "Enter a valid name and email. Password must contain at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users
                (full_name, email, phone, password)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $phone,
                $hashed_password
            );

            if ($stmt->execute()) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | SmartFind</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .auth-container {
            max-width: 480px;
            margin: 55px auto;
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
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 17px;
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

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 7px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .auth-link {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>

<body>

<header class="navbar">
    <div class="logo">Smart<span>Find</span></div>
    <nav>
        <a href="index.php">Home</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<div class="auth-container">

    <h1>Create Account</h1>
    <p>Join SmartFind and help reunite people with their belongings.</p>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
            <a href="login.php">Login here</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="100"
                required
                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="phone">Phone Number (Optional)</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="20"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn primary auth-button">
            Create Account
        </button>

    </form>

    <div class="auth-link">
        Already registered?
        <a href="login.php">Login here</a>
    </div>

</div>

</body>
</html>
```
