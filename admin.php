<?php
session_start();
require_once 'database.php';

// Generate a CSRF token for the administrator's session.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Protect the dashboard: admins only.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Verify the user's role using the database.
$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();

$adminUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$adminUser || $adminUser['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied. This page is for administrators only.");
}

function escapeHTML($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Process claim approval or rejection.
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

// Reject requests without a valid CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    http_response_code(403);
    exit('Security check failed. Please refresh the page and try again.');
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';


    if (!$claimId || !in_array($action, ['approve', 'reject'], true)) {
        $message = "Invalid claim request.";
        $messageType = 'error';
    } else {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        // Retrieve the claim and ensure it is still pending.
        $check = $conn->prepare("
            SELECT id, report_id, claimant_id
            FROM claims
            WHERE id = ? AND status = 'pending'
            LIMIT 1
        ");

        $check->bind_param("i", $claimId);
        $check->execute();
        $claim = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$claim) {
            $message = "This claim no longer exists or has already been reviewed.";
            $messageType = 'error';
        } else {
            $conn->begin_transaction();

            try {
                if ($newStatus === 'approved') {
                    // Lock the report and verify it is still open.
                    $reportStmt = $conn->prepare("
                        SELECT id, status
                        FROM reports
                        WHERE id = ?
                          AND report_type = 'found'
                        FOR UPDATE
                    ");

                    $reportStmt->bind_param("i", $claim['report_id']);
                    $reportStmt->execute();
                    $report = $reportStmt->get_result()->fetch_assoc();
                    $reportStmt->close();

                    if (!$report || $report['status'] !== 'open') {
                        throw new Exception(
                            "The found item is no longer open for claims."
                        );
                    }

                    // Approve this claim only if it is still pending.
                    $updateClaim = $conn->prepare("
                        UPDATE claims
                        SET status = 'approved'
                        WHERE id = ? AND status = 'pending'
                    ");

                    $updateClaim->bind_param("i", $claimId);
                    $updateClaim->execute();

                    if ($updateClaim->affected_rows !== 1) {
                        $updateClaim->close();
                        throw new Exception(
                            "The claim has already been processed."
                        );
                    }

                    $updateClaim->close();

                    // Mark the item resolved.
                    $updateReport = $conn->prepare("
                        UPDATE reports
                        SET status = 'resolved'
                        WHERE id = ? AND status = 'open'
                    ");

                    $updateReport->bind_param("i", $claim['report_id']);
                    $updateReport->execute();

                    if ($updateReport->affected_rows !== 1) {
                        $updateReport->close();
                        throw new Exception(
                            "Could not update the item's status."
                        );
                    }

                    $updateReport->close();

                    // Reject other pending claims for the same item.
                   $updateReport->close();

// Find other users who have pending claims for this item.
$otherClaims = $conn->prepare("
    SELECT claimant_id
    FROM claims
    WHERE report_id = ?
      AND id <> ?
      AND status = 'pending'
");

$otherClaims->bind_param(
    "ii",
    $claim['report_id'],
    $claimId
);

$otherClaims->execute();

$otherClaimants = $otherClaims->get_result()->fetch_all(MYSQLI_ASSOC);

$otherClaims->close();

// Reject the remaining pending claims.
$rejectOthers = $conn->prepare("
    UPDATE claims
    SET status = 'rejected'
    WHERE report_id = ?
      AND id <> ?
      AND status = 'pending'
");

$rejectOthers->bind_param(
    "ii",
    $claim['report_id'],
    $claimId
);

$rejectOthers->execute();
$rejectOthers->close();

// Notify everyone whose claim was automatically rejected.
$otherMessage = "Your claim for report #"
    . $claim['report_id']
    . " was not successful because another claimant's claim was approved.";

$otherType = 'claim_rejected';

$notifyOthers = $conn->prepare("
    INSERT INTO notifications
    (user_id, message, type, related_report_id)
    VALUES (?, ?, ?, ?)
");

foreach ($otherClaimants as $otherClaimant) {

    $otherUserId = (int) $otherClaimant['claimant_id'];

    $notifyOthers->bind_param(
        "issi",
        $otherUserId,
        $otherMessage,
        $otherType,
        $claim['report_id']
    );

    $notifyOthers->execute();
}

$notifyOthers->close();
                    ");

                    $rejectOthers->bind_param(
                        "ii",
                        $claim['report_id'],
                        $claimId
                    );

                    $rejectOthers->execute();
                    $rejectOthers->close();

                } else {
                    $updateClaim = $conn->prepare("
                        UPDATE claims
                        SET status = 'rejected'
                        WHERE id = ? AND status = 'pending'
                    ");

                    $updateClaim->bind_param("i", $claimId);
                    $updateClaim->execute();

                    if ($updateClaim->affected_rows !== 1) {
                        $updateClaim->close();
                        throw new Exception(
                            "The claim has already been processed."
                        );
                    }

                    $updateClaim->close();
                }
                // Send a notification to the claimant.
$notificationMessage = $newStatus === 'approved'
    ? "Good news! Your claim for report #"
        . $claim['report_id']
        . " has been approved. The item has been marked as resolved."
    : "Your claim for report #"
        . $claim['report_id']
        . " has been rejected.";

$notificationType = $newStatus === 'approved'
    ? 'claim_approved'
    : 'claim_rejected';

$notify = $conn->prepare("
    INSERT INTO notifications
        (user_id, message, type, related_report_id)
    VALUES (?, ?, ?, ?)
");

$notify->bind_param(
    "issi",
    $claim['claimant_id'],
    $notificationMessage,
    $notificationType,
    $claim['report_id']
);

$notify->execute();
$notify->close();
                $conn->commit();

                $message = $newStatus === 'approved'
                    ? "Claim approved. The item is now marked as resolved."
                    : "Claim rejected successfully.";

            } catch (Throwable $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Dashboard statistics.
$totalUsers = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM users"
)->fetch_assoc()['total'];

$totalLost = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM reports WHERE report_type = 'lost'"
)->fetch_assoc()['total'];

$totalFound = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM reports WHERE report_type = 'found'"
)->fetch_assoc()['total'];

$pendingClaims = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM claims WHERE status = 'pending'"
)->fetch_assoc()['total'];

// Fetch claims for review.
$claimsQuery = "
    SELECT
        claims.id AS claim_id,
        claims.report_id,
        claims.claimant_id,
        claims.message,
        claims.status AS claim_status,
        claims.created_at,
        reports.item_name,
        reports.location,
        reports.status AS report_status,
        users.full_name AS claimant_name,
        users.email AS claimant_email
    FROM claims
    JOIN reports ON claims.report_id = reports.id
    JOIN users ON claims.claimant_id = users.id
    ORDER BY
        CASE WHEN claims.status = 'pending' THEN 0 ELSE 1 END,
        claims.created_at DESC
    LIMIT 100
";

$claimsResult = $conn->query($claimsQuery);

if (!$claimsResult) {
    die("Could not load claims. Check your database tables.");
}

// Fetch recent reports.
$reportsQuery = "
    SELECT
        reports.id,
        reports.report_type,
        reports.item_name,
        reports.category,
        reports.location,
        reports.status,
        reports.created_at,
        users.full_name
    FROM reports
    LEFT JOIN users ON reports.user_id = users.id
    ORDER BY reports.created_at DESC
    LIMIT 100
";

$reportsResult = $conn->query($reportsQuery);

if (!$reportsResult) {
    die("Could not load reports. Check your database tables.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | SmartFind</title>

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

        .navbar {
            background: #172554;
            color: white;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }

        .container {
            width: 94%;
            max-width: 1400px;
            margin: 30px auto;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h1 {
            color: #172554;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
            border-top: 4px solid #2563eb;
        }

        .stat-card h3 {
            color: #64748b;
            font-size: 15px;
        }

        .stat-card p {
            font-size: 30px;
            font-weight: bold;
            color: #172554;
            margin-bottom: 0;
        }

        .section {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
        }

        .section h2 {
            color: #172554;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            text-align: left;
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        th {
            background: #172554;
            color: white;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .approved, .resolved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected, .closed {
            background: #fee2e2;
            color: #991b1b;
        }

        .open {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .under_review {
            background: #ede9fe;
            color: #5b21b6;
        }

        .lost {
            color: #b91c1c;
            font-weight: bold;
        }

        .found {
            color: #15803d;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            margin: 3px;
            color: white;
            cursor: pointer;
            font-size: 13px;
        }

        .approve {
            background: #15803d;
        }

        .reject {
            background: #b91c1c;
        }

        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 25px;
        }

        @media (max-width: 600px) {
            .navbar a {
                margin-left: 0;
                margin-right: 12px;
            }

            .section {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <h2>🔎 SmartFind Admin</h2>

    <div>
        <a href="index.php">Home</a>
        <a href="browse.php">Browse Items</a>
        <a href="matches.php">Matches</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="welcome">
        <h1>Administrator Dashboard</h1>
        <p>
            Welcome, <?= escapeHTML($adminUser['full_name']) ?>.
            Manage reports and review item claims below.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= $messageType === 'error'
            ? 'error-message'
            : 'success-message' ?>">
            <?= escapeHTML($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <h3>Registered Users</h3>
            <p><?= $totalUsers ?></p>
        </div>

        <div class="stat-card">
            <h3>Lost Reports</h3>
            <p><?= $totalLost ?></p>
        </div>

        <div class="stat-card">
            <h3>Found Reports</h3>
            <p><?= $totalFound ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Claims</h3>
            <p><?= $pendingClaims ?></p>
        </div>

    </section>

    <section class="section">

        <h2>📋 Item Claims</h2>

        <p>Review claims submitted by registered users.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Item</th>
                        <th>Claimant</th>
                        <th>Explanation</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($claimsResult->num_rows > 0): ?>

                    <?php while ($claim = $claimsResult->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int)$claim['claim_id'] ?></td>

                            <td>
                                <strong><?= escapeHTML($claim['item_name']) ?></strong><br>
                                Location: <?= escapeHTML($claim['location']) ?><br>
                                Report status:
                                <?= escapeHTML($claim['report_status']) ?>
                            </td>

                            <td>
                                <?= escapeHTML($claim['claimant_name']) ?><br>
                                <?= escapeHTML($claim['claimant_email']) ?>
                            </td>

                            <td>
                                <?= nl2br(escapeHTML($claim['message'])) ?>
                            </td>

                            <td>
                                <span class="badge <?= escapeHTML($claim['claim_status']) ?>">
                                    <?= escapeHTML($claim['claim_status']) ?>
                                </span>
                            </td>

                            <td>
                                <?php if (
                                    $claim['claim_status'] === 'pending'
                                    && $claim['report_status'] === 'open'
                                ): ?>

                                   <form method="POST" action="admin.php"
                                  onsubmit="return confirm('Are you sure you want to approve this claim?');">

<!-- CSRF security token -->
                                   <input type="hidden" name="csrf_token"
                                 value="<?= escapeHTML($_SESSION['csrf_token']) ?>">

                                   <input type="hidden" name="claim_id"
                                 value="<?= (int)$claim['claim_id'] ?>">

                                   <input type="hidden" name="action"
                                 value="approve">

                                   <button type="submit" class="btn approve">
                                   Approve
                                   </button>

                                       </form>

                                       <form method="POST" action="admin.php"
                               onsubmit="return confirm('Are you sure you want to reject this claim?');">

<!-- CSRF security token -->
                                    <input type="hidden" name="csrf_token"
                              value="<?= escapeHTML($_SESSION['csrf_token']) ?>">

                          <input type="hidden" name="claim_id"
                              value="<?= (int)$claim['claim_id'] ?>">

                  <input type="hidden" name="action"
                              value="reject">

             <button type="submit" class="btn reject">
                      Reject
        </button>


                                    </form>

                                <?php else: ?>
                                    No action available
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No claims have been submitted yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </section>

    <section class="section">

        <h2>📦 Recent Lost and Found Reports</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Reported By</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($reportsResult->num_rows > 0): ?>

                    <?php while ($report = $reportsResult->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int)$report['id'] ?></td>

                            <td class="<?= escapeHTML($report['report_type']) ?>">
                                <?= escapeHTML($report['report_type']) ?>
                            </td>

                            <td><?= escapeHTML($report['item_name']) ?></td>

                            <td><?= escapeHTML($report['category']) ?></td>

                            <td><?= escapeHTML($report['location']) ?></td>

                            <td><?= escapeHTML($report['full_name'] ?: 'Unknown') ?></td>

                            <td>
                                <span class="badge <?= escapeHTML($report['status']) ?>">
                                    <?= escapeHTML($report['status']) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty">
                            No reports have been submitted yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </section>

</main>

</body>
</html>

<?php
$conn->close();
?>