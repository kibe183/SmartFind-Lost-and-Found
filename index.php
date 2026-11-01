<?php
session_start();
require_once "database.php";

$totalLost = 0;
$totalFound = 0;
$totalResolved = 0;

$result = $conn->query(
    "SELECT report_type, status, COUNT(*) AS total
     FROM reports
     GROUP BY report_type, status"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['report_type'] === 'lost'
            && $row['status'] === 'open') {
            $totalLost += (int) $row['total'];
        }

        if ($row['report_type'] === 'found'
            && $row['status'] === 'open') {
            $totalFound += (int) $row['total'];
        }

        if ($row['status'] === 'resolved') {
            $totalResolved += (int) $row['total'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Smart Lost & Found System</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="navbar">

    <div class="logo">
        Smart<span>Find</span>
    </div>
    <nav>
    <a href="index.php" class="logo">SmartFind</a>

    <div class="links">
        <a href="index.php">Home</a>
        <a href="browse.php">Browse Items</a>

        <?php if (isset($_SESSION['user_id'])): ?>

            <a href="report_lost.php">Report Lost</a>
            <a href="report_found.php">Report Found</a>
            <a href="matches.php">My Matches</a>
            <a href="dashboard.php">My Dashboard</a>
            <a href="profile.php">My Profile 👤</a>

            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="admin.php">Admin Dashboard</a>
            <?php endif; ?>

            <a href="logout.php">Logout</a>

        <?php else: ?>

            <a href="login.php">Login</a>
            <a href="register.php">Register</a>

        <?php endif; ?>
    </div>
    </nav>

</header>

<section class="hero">

    <div class="hero-content">

        <p class="eyebrow">
            FIND WHAT MATTERS
        </p>

        <h1>
            Lost Something?<br>
            <span>Let's Find It.</span>
        </h1>

        <p class="hero-description">
            A smarter way to reconnect people with their
            lost belongings. Report missing items, share
            found items, and discover possible matches
            in your community.
        </p>

        <div class="hero-buttons">

            <a href="report_lost.php" class="btn primary">
                I Lost Something
            </a>

            <a href="report_found.php" class="btn secondary">
                I Found Something
            </a>

        </div>

    </div>

</section>

<section class="statistics">

    <div class="stat-card">
        <h2><?php echo $totalLost; ?></h2>
        <p>Open Lost Reports</p>
    </div>

    <div class="stat-card">
        <h2><?php echo $totalFound; ?></h2>
        <p>Open Found Reports</p>
    </div>

    <div class="stat-card">
        <h2><?php echo $totalResolved; ?></h2>
        <p>Resolved Reports</p>
    </div>

</section>

<section class="how-it-works">

    <h2>How It Works</h2>

    <p class="section-description">
        Three simple steps to help reunite people with
        their belongings.
    </p>

    <div class="steps">

        <div class="step-card">
            <div class="step-number">01</div>
            <h3>Report an Item</h3>
            <p>
                Tell the community what you lost or found.
                Add a description, location, and date.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">02</div>
            <h3>Discover Matches</h3>
            <p>
                Search existing reports and discover
                items that could match your description.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">03</div>
            <h3>Verify and Reunite</h3>
            <p>
                Submit a claim and verify ownership
                before arranging a safe return.
            </p>
        </div>

    </div>

</section>

<footer>
    <p>
        &copy; <?php echo date("Y"); ?>
        SmartFind. Helping communities reconnect.
    </p>
</footer>

</body>
</html>