<?php
session_start();

require_once 'database.php';

// Require login before allowing claims.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the found report ID.
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = filter_input(
        INPUT_POST,
        'report_id',
        FILTER_VALIDATE_INT
    );
}

// Validate CSRF token on submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    }
}

$report = null;

// Retrieve the item being claimed.
if ($reportId && $reportId > 0) {
    $stmt = $conn->prepare("
        SELECT id, user_id, item_name, category,
               description, location, report_date, status
        FROM reports
        WHERE id = ?
          AND report_type = 'found'
          AND status = 'open'
        LIMIT 1
    ");

    $stmt->bind_param("i", $reportId);
    $stmt->execute();

    $result = $stmt->get_result();
    $report = $result->fetch_assoc();

    $stmt->close();
}

if (!$report) {
    http_response_code(404);

    $error = "The found item could not be located, or it is no longer available.";

} elseif ((int) $report['user_id'] === $userId) {

    $error = "You cannot claim an item that you reported finding.";

} elseif (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    $message = trim($_POST['message'] ?? '');

    if (strlen($message) < 15) {

        $error = "Please explain why the item belongs to you using at least 15 characters.";

    } elseif (strlen($message) > 2000) {

        $error = "Your explanation must not exceed 2,000 characters.";

    } else {

        // Prevent duplicate pending claims for the same item.
        $check = $conn->prepare("
            SELECT id
            FROM claims
            WHERE report_id = ?
              AND claimant_id = ?
              AND status = 'pending'
            LIMIT 1
        ");

        $check->bind_param("ii", $reportId, $userId);
        $check->execute();

        $existingClaim = $check->get_result()->fetch_assoc();

        $check->close();

        if ($existingClaim) {

            $error = "You already have a pending claim for this item.";

        } else {

            // Save the claim for review.
            $insert = $conn->prepare("
                INSERT INTO claims
                    (report_id, claimant_id, message, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");

            $insert->bind_param(
                "iis",
                $reportId,
                $userId,
                $message
            );

            if ($insert->execute()) {

                $success = "Your claim has been submitted successfully! It is now pending review.";

                /*
                 * NEW FEATURE:
                 * Notify the person who reported finding the item.
                 */
                $ownerId = (int) $report['user_id'];
                $itemName = $report['item_name'];

                $notificationMessage =
                    "Someone has submitted a claim for the item \"" .
                    $itemName .
                    "\". Please review the claim in SmartFind.";

                try {

                    $notification = $conn->prepare("
                        INSERT INTO notifications
                            (user_id, message, type, related_report_id, is_read, created_at)
                        VALUES (?, ?, 'claim', ?, 0, NOW())
                    ");

                    if ($notification) {

                        $notification->bind_param(
                            "isi",
                            $ownerId,
                            $notificationMessage,
                            $reportId
                        );

                        $notification->execute();
                        $notification->close();
                    }

                } catch (Throwable $e) {

                    /*
                     * Keep the successful claim even if the
                     * notification cannot be saved.
                     */
                    error_log(
                        "SmartFind notification error: " .
                        $e->getMessage()
                    );
                }

            } else {

                $error = "We could not submit your claim. Please try again.";
            }

            $insert->close();
        }
    }
}

function escapeHTML($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
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

    <title>Claim Item | SmartFind</title>

    <link rel="stylesheet" href="style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .navbar {
            background: #172554;
            color: white;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }

        .navbar a:hover {
            color: #93c5fd;
        }

        .container {
            width: 90%;
            max-width: 700px;
            margin: 40px auto;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        h1, h2 {
            color: #172554;
        }

        .item-details {
            background: #f8fafc;
            padding: 18px;
            border-radius: 8px;
            margin: 20px 0;
            line-height: 1.7;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        textarea {
            width: 100%;
            min-height: 150px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 15px;
            resize: vertical;
        }

        textarea:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            background: #172554;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin-top: 15px;
        }

        .btn:hover {
            background: #1e40af;
        }

        .message {
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .warning {
            background: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 14px;
            margin-top: 20px;
            line-height: 1.5;
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 18px 5%;
            }

            .navbar a {
                margin-left: 0;
                margin-right: 12px;
            }

            .card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <h2>🔎 SmartFind</h2>

    <div>
        <a href="index.php">Home</a>
        <a href="browse.php">Browse Items</a>
        <a href="matches.php">Possible Matches</a>
        <a href="notifications.php">🔔 Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="card">

        <h1>Claim a Found Item</h1>

        <p>
            Provide enough information to help verify that this item
            belongs to you.
        </p>

        <?php if ($error !== ''): ?>

            <div class="message error">
                <?= escapeHTML($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ''): ?>

            <div class="message success">
                <?= escapeHTML($success) ?>
            </div>

            <a href="browse.php" class="btn">
                Back to Browse Items
            </a>

            <a href="notifications.php" class="btn">
                View Notifications
            </a>

        <?php elseif ($report && $error === ''): ?>

            <div class="item-details">

                <h2><?= escapeHTML($report['item_name']) ?></h2>

                <p>
                    <strong>Category:</strong>
                    <?= escapeHTML($report['category']) ?>
                </p>

                <p>
                    <strong>Location found:</strong>
                    <?= escapeHTML($report['location']) ?>
                </p>

                <p>
                    <strong>Date reported:</strong>
                    <?= escapeHTML($report['report_date']) ?>
                </p>

                <p>
                    <strong>Description:</strong><br>
                    <?= nl2br(escapeHTML($report['description'])) ?>
                </p>

            </div>

            <form method="POST" action="claim_item.php">

                <input
                    type="hidden"
                    name="report_id"
                    value="<?= (int) $report['id'] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHTML($_SESSION['csrf_token']) ?>"
                >

                <label for="message">
                    Explain why this item belongs to you
                </label>

                <textarea
                    id="message"
                    name="message"
                    minlength="15"
                    maxlength="2000"
                    required
                    placeholder="Describe identifying details, such as a distinctive mark, contents, or other information that can help verify ownership."
                ><?= escapeHTML($_POST['message'] ?? '') ?></textarea>

                <button type="submit" class="btn">
                    Submit Claim
                </button>

                <a href="browse.php" class="btn">
                    Cancel
                </a>

            </form>

            <div class="warning">
                <strong>Important:</strong>
                Submitting a claim does not automatically establish ownership.
                Your claim must be reviewed before the item is returned.
                Avoid sharing passwords, identification numbers, or other
                unnecessary sensitive information.
            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>