<?php
session_start();
require_once 'database.php';

// Only logged-in users can access this dashboard.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

function escapeHTML($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get user information.
$userName = $_SESSION['full_name'] ?? 'User';

// Count lost reports.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM reports
     WHERE user_id = ? AND report_type = 'lost'"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$lostCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Count found reports.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM reports
     WHERE user_id = ? AND report_type = 'found'"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$foundCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Count claims submitted by this user.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM claims
     WHERE claimant_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$claimCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get the user's reports.
$stmt = $conn->prepare(
    "SELECT id, item_name, report_type, location, status, created_at
     FROM reports
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$reports = $stmt->get_result();

// Get the user's claims.
$stmt = $conn->prepare(
    "SELECT claims.id, claims.message, claims.status,
            claims.created_at, reports.item_name
     FROM claims
     INNER JOIN reports ON claims.report_id = reports.id
     WHERE claims.claimant_id = ?
     ORDER BY claims.created_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$claims = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Dashboard | SmartFind</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}

header {
    background: #172b4d;
    color: white;
    padding: 20px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

header h2 {
    margin: 0;
}

nav a {
    color: white;
    text-decoration: none;
    margin-left: 18px;
    font-weight: bold;
}

nav a:hover {
    color: #ffc107;
}

.container {
    width: 90%;
    max-width: 1200px;
    margin: 35px auto;
}

.welcome {
    margin-bottom: 25px;
}

.welcome h1 {
    margin-bottom: 8px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 35px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
}

.card h3 {
    color: #666;
    margin-top: 0;
}

.card p {
    font-size: 30px;
    font-weight: bold;
    color: #172b4d;
    margin-bottom: 0;
}

.actions {
    margin-bottom: 35px;
}

.button {
    display: inline-block;
    padding: 12px 18px;
    background: #172b4d;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin: 5px 8px 5px 0;
}

.button:hover {
    background: #294b80;
}

.section {
    background: white;
    padding: 25px;
    margin-bottom: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    min-width: 600px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th {
    background: #172b4d;
    color: white;
}

.badge {
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    text-transform: capitalize;
}

.pending, .under_review {
    background: #fff3cd;
    color: #856404;
}

.approved, .resolved {
    background: #d4edda;
    color: #155724;
}

.rejected, .closed {
    background: #f8d7da;
    color: #721c24;
}

.open {
    background: #d1ecf1;
    color: #0c5460;
}

.empty {
    color: #777;
    padding: 15px 0;
}

footer {
    text-align: center;
    padding: 20px;
    color: #777;
}

@media(max-width: 600px) {
    header {
        gap: 15px;
    }

    nav a {
        margin: 5px;
        display: inline-block;
    }

    .container {
        width: 94%;
    }
}

</style>
</head>

<body>

<header>

    <h2>SmartFind</h2>

    <nav>
        <a href="index.php">Home</a>
        <a href="browse.php">Browse Items</a>
        <a href="report_lost.php">Report Lost</a>
        <a href="report_found.php">Report Found</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
    </nav>

</header>

<div class="container">

    <div class="welcome">

        <h1>Welcome, <?= escapeHTML($userName) ?>!</h1>

        <p>Manage your reports and track your submitted claims here.</p>

    </div>

    <div class="cards">

        <div class="card">
            <h3>My Lost Items</h3>
            <p><?= (int)$lostCount ?></p>
        </div>

        <div class="card">
            <h3>My Found Items</h3>
            <p><?= (int)$foundCount ?></p>
        </div>

        <div class="card">
            <h3>My Claims</h3>
            <p><?= (int)$claimCount ?></p>
        </div>

    </div>

    <div class="actions">

        <a href="report_lost.php" class="button">+ Report Lost Item</a>

        <a href="report_found.php" class="button">+ Report Found Item</a>

        <a href="browse.php" class="button">Browse Items</a>

    </div>

    <div class="section">

        <h2>My Reported Items</h2>

        <?php if ($reports->num_rows > 0): ?>

        <table>

            <tr>
                <th>Item Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Status</th>
                <th>Date Reported</th>
            </tr>

            <?php while ($row = $reports->fetch_assoc()): ?>

            <tr>

                <td><?= escapeHTML($row['item_name']) ?></td>

                <td><?= escapeHTML($row['report_type']) ?></td>

                <td><?= escapeHTML($row['location']) ?></td>

                <td>
                    <span class="badge <?= escapeHTML($row['status']) ?>">
                        <?= escapeHTML($row['status']) ?>
                    </span>
                </td>

                <td><?= escapeHTML($row['created_at']) ?></td>

            </tr>

            <?php endwhile; ?>

        </table>

        <?php else: ?>

            <p class="empty">You haven't reported any items yet.</p>

        <?php endif; ?>

    </div>

    <div class="section">

        <h2>My Submitted Claims</h2>

        <?php if ($claims->num_rows > 0): ?>

        <table>

            <tr>
                <th>Item Name</th>
                <th>Reason Submitted</th>
                <th>Claim Status</th>
                <th>Date Submitted</th>
            </tr>

            <?php while ($row = $claims->fetch_assoc()): ?>

            <tr>

                <td><?= escapeHTML($row['item_name']) ?></td>

                <td><?= escapeHTML($row['message']) ?></td>

                <td>
                    <span class="badge <?= escapeHTML($row['status']) ?>">
                        <?= escapeHTML($row['status']) ?>
                    </span>
                </td>

                <td><?= escapeHTML($row['created_at']) ?></td>

            </tr>

            <?php endwhile; ?>

        </table>

        <?php else: ?>

            <p class="empty">You haven't submitted any claims yet.</p>

        <?php endif; ?>

    </div>

</div>

<footer>
    &copy; <?= date('Y') ?> SmartFind Lost and Found System
</footer>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>