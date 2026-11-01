```php
<?php
session_start();
require_once 'database.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$message = "";

// Create CSRF token for form security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die("Invalid request. Please refresh the page and try again.");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {

        $stmt = $conn->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE user_id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $message = "All notifications have been marked as read.";

    } elseif ($action === 'mark_read') {

        $notification_id = filter_input(
            INPUT_POST,
            'notification_id',
            FILTER_VALIDATE_INT
        );

        if ($notification_id) {
            $stmt = $conn->prepare(
                "UPDATE notifications
                 SET is_read = 1
                 WHERE id = ? AND user_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $notification_id,
                $user_id
            );

            $stmt->execute();
            $stmt->close();

            $message = "Notification marked as read.";
        }
    }
}

// Get unread notification count
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = ? AND is_read = 0"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$unread_count = (int) $stmt->get_result()
    ->fetch_assoc()['unread_count'];

$stmt->close();

// Get notifications belonging to this user
$stmt = $conn->prepare(
    "SELECT id, message, type, related_report_id,
            is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$notifications = $stmt->get_result();

function escapeNotification($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Notifications | SmartFind</title>

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

        .links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        .links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .links a:hover {
            color: #38bdf8;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
        }

        .count {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 10px;
        }

        .mark-all {
            border: none;
            background: #2563eb;
            color: white;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .mark-all:hover {
            background: #1d4ed8;
        }

        .notification-list {
            display: grid;
            gap: 15px;
        }

        .notification {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
        }

        .notification.unread {
            border-left: 5px solid #2563eb;
            background: #f8fbff;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .notification-message {
            line-height: 1.6;
            color: #475569;
            overflow-wrap: anywhere;
        }

        .notification-meta {
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
            margin-bottom: 10px;
        }

        .badge.match {
            background: #dcfce7;
            color: #166534;
        }

        .badge.claim {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.general {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .mark-read {
            background: white;
            color: #2563eb;
            border: 1px solid #2563eb;
            border-radius: 6px;
            padding: 8px 11px;
            cursor: pointer;
            white-space: nowrap;
        }

        .mark-read:hover {
            background: #eff6ff;
        }

        .empty {
            background: white;
            padding: 50px 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .empty .icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .empty h2 {
            margin-bottom: 10px;
        }

        .empty p {
            color: #64748b;
            line-height: 1.6;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: #2563eb;
            text-decoration: none;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 30px 15px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            nav {
                padding: 18px 5%;
            }

            .container {
                margin: 25px auto;
            }

            .notification {
                flex-direction: column;
            }

            .mark-read {
                align-self: flex-start;
            }

            .page-header h1 {
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
        <a href="profile.php">My Profile</a>

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>

        <a href="logout.php">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="page-header">
        <div>
            <h1>Notifications 🔔</h1>

            <p>
                Stay updated about your reports and claims.
            </p>

            <span class="count">
                <?= $unread_count ?>
                unread notification<?= $unread_count === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($unread_count > 0): ?>
            <form method="POST" action="notifications.php">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapeNotification($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="action"
                       value="mark_all_read">

                <button type="submit" class="mark-all">
                    Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?= escapeNotification($message) ?>
        </div>
    <?php endif; ?>

    <div class="notification-list">

        <?php if ($notifications->num_rows > 0): ?>

            <?php while ($notification = $notifications->fetch_assoc()): ?>

                <?php
                    $type = strtolower($notification['type']);

                    if (strpos($type, 'match') !== false) {
                        $badge_class = 'match';
                        $badge_label = 'ITEM MATCH';

                    } elseif (strpos($type, 'claim') !== false) {
                        $badge_class = 'claim';
                        $badge_label = 'CLAIM UPDATE';

                    } else {
                        $badge_class = 'general';
                        $badge_label = 'GENERAL';
                    }
                ?>

                <div class="notification <?= $notification['is_read'] ? '' : 'unread' ?>">

                    <div class="notification-content">

                        <span class="badge <?= $badge_class ?>">
                            <?= escapeNotification($badge_label) ?>
                        </span>

                        <div class="notification-title">
                            <?= $notification['is_read'] ? 'Notification' : '🔵 New Notification' ?>
                        </div>

                        <div class="notification-message">
                            <?= escapeNotification($notification['message']) ?>
                        </div>

                        <?php if (!empty($notification['related_report_id'])): ?>
                            <div class="notification-meta">
                                Related report ID:
                                #<?= (int) $notification['related_report_id'] ?>
                            </div>
                        <?php endif; ?>

                        <div class="notification-meta">
                            <?= escapeNotification($notification['created_at']) ?>
                        </div>

                    </div>

                    <?php if (!$notification['is_read']): ?>
                        <form method="POST" action="notifications.php">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= escapeNotification($_SESSION['csrf_token']) ?>">

                            <input type="hidden"
                                   name="action"
                                   value="mark_read">

                            <input type="hidden"
                                   name="notification_id"
                                   value="<?= (int) $notification['id'] ?>">

                            <button type="submit" class="mark-read">
                                Mark as Read
                            </button>

                        </form>
                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                <div class="icon">🔔</div>

                <h2>No Notifications Yet</h2>

                <p>
                    You don't have any notifications right now.
                    We'll display updates here when notifications
                    are generated by the system.
                </p>
            </div>

        <?php endif; ?>

    </div>

    <a href="dashboard.php" class="back-link">
        ← Back to My Dashboard
    </a>

</main>

<footer>
    &copy; <?= date('Y') ?> SmartFind |
    Helping people reunite with lost belongings.
</footer>

</body>
</html>

<?php
$stmt->close();
?>
```
