```php
<?php
session_start();
require_once 'database.php';

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$message = "";
$message_type = "";

// Fetch the current user's information
$stmt = $conn->prepare(
    "SELECT full_name, email, phone, role
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Update profile when the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($full_name === '' || $email === '') {
        $message = "Full name and email are required.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {
        // Check whether another account uses this email
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND id != ?"
        );

        $check->bind_param("si", $email, $user_id);
        $check->execute();

        $email_result = $check->get_result();

        if ($email_result->num_rows > 0) {
            $message = "That email address is already registered.";
            $message_type = "error";

        } else {
            // Save the updated profile
            $update = $conn->prepare(
                "UPDATE users
                 SET full_name = ?, email = ?, phone = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "sssi",
                $full_name,
                $email,
                $phone,
                $user_id
            );

            if ($update->execute()) {
                $message = "Your profile was updated successfully!";
                $message_type = "success";

                // Refresh the displayed user information
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone'] = $phone;

                // Update the session name if your application uses it
                $_SESSION['full_name'] = $full_name;

            } else {
                $message = "Unable to update your profile. Please try again.";
                $message_type = "error";
            }

            $update->close();
        }

        $check->close();
    }
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | SmartFind</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        nav {
            background: #0f172a;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        nav .links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        nav .links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        nav .links a:hover {
            color: #38bdf8;
        }

        .container {
            max-width: 750px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .heading {
            margin-bottom: 25px;
        }

        .heading h1 {
            margin-bottom: 8px;
            font-size: 30px;
        }

        .heading p {
            color: #64748b;
            line-height: 1.6;
        }

        .profile-card {
            background: white;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.06);
        }

        .avatar {
            width: 85px;
            height: 85px;
            margin: 0 auto 18px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 36px;
            font-weight: bold;
        }

        .account-name {
            text-align: center;
            margin-bottom: 30px;
        }

        .account-name h2 {
            margin: 0 0 8px;
        }

        .role {
            display: inline-block;
            padding: 6px 12px;
            background: #e0f2fe;
            color: #075985;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .help {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
        }

        .btn {
            width: 100%;
            border: none;
            background: #2563eb;
            color: white;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: #2563eb;
            text-decoration: none;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 25px 15px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            nav {
                padding: 18px 5%;
            }

            .container {
                margin: 25px auto;
            }

            .profile-card {
                padding: 22px;
            }

            .heading h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<nav>
    <a href="index.php" class="logo">SmartFind</a>

    <div class="links">
        <a href="index.php">Home</a>
        <a href="browse.php">Browse Items</a>
        <a href="dashboard.php">Dashboard</a>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>

        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="heading">
        <h1>My Profile 👤</h1>
        <p>Manage your personal information and keep your SmartFind account up to date.</p>
    </div>

    <div class="profile-card">

        <div class="avatar">
            <?= strtoupper(substr(trim($user['full_name']), 0, 1)) ?>
        </div>

        <div class="account-name">
            <h2><?= escape($user['full_name']) ?></h2>

            <span class="role">
                <?= escape($user['role']) ?>
            </span>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= escape($message_type) ?>">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php">

            <div class="form-group">
                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= escape($user['full_name']) ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escape($user['email']) ?>"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= escape($user['phone'] ?? '') ?>"
                    maxlength="30"
                    placeholder="Enter your phone number"
                >

                <span class="help">
                    Keep your contact information up to date.
                </span>
            </div>

            <button type="submit" class="btn">
                Save Changes
            </button>

        </form>

        <a href="dashboard.php" class="back-link">
            ← Back to My Dashboard
        </a>

    </div>

</div>

<footer>
    &copy; <?= date('Y') ?> SmartFind. Helping people reunite with lost belongings.
</footer>

</body>
</html>
```
