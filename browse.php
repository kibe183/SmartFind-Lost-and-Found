<?php
session_start();
require_once 'database.php';

// Get search and filter values
$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? 'all';

// Only allow valid report types
if (!in_array($type, ['all', 'lost', 'found'], true)) {
    $type = 'all';
}

// Build the query
$sql = "
    SELECT
        reports.*,
        users.full_name
    FROM reports
    LEFT JOIN users ON reports.user_id = users.id
    WHERE reports.status != 'closed'
";

$conditions = [];
$params = [];
$types = '';

if ($type !== 'all') {
    $conditions[] = "reports.report_type = ?";
    $params[] = $type;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = "(
        reports.item_name LIKE ?
        OR reports.category LIKE ?
        OR reports.description LIKE ?
        OR reports.location LIKE ?
    )";

    $keyword = "%{$search}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= 'ssss';
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY reports.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed. Please check your database connection.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Helper function to safely display text
function escapeHTML($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Items | SmartFind</title>

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
            gap: 15px;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 18px;
        }

        .navbar a:hover {
            color: #93c5fd;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .page-heading {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-heading h1 {
            color: #172554;
            margin-bottom: 10px;
        }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-form input,
        .search-form select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .search-form input {
            flex: 1;
            min-width: 200px;
        }

        .search-form select {
            min-width: 150px;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            background: #172554;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #1e40af;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }

        .report-card:hover {
            transform: translateY(-4px);
        }

        .item-image {
            width: 100%;
            height: 210px;
            object-fit: cover;
            background: #e5e7eb;
            display: block;
        }

        .no-image {
            height: 210px;
            background: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #6b7280;
        }

        .report-content {
            padding: 20px;
        }

        .report-content h3 {
            margin-top: 0;
            color: #172554;
            overflow-wrap: anywhere;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .lost {
            background: #fee2e2;
            color: #991b1b;
        }

        .found {
            background: #dcfce7;
            color: #166534;
        }

        .status {
            color: #6b7280;
            font-size: 13px;
        }

        .description {
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .empty-message {
            background: white;
            padding: 40px 20px;
            border-radius: 10px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .empty-message h3 {
            color: #172554;
        }

        .report-links {
            margin-top: 30px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 18px 5%;
            }

            .navbar a {
                margin: 0 10px 0 0;
            }

            .container {
                width: 92%;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input,
            .search-form select,
            .search-form button {
                width: 100%;
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
        <a href="report_lost.php">Report Lost</a>
        <a href="report_found.php">Report Found</a>
    </div>
</nav>

<main class="container">

    <div class="page-heading">
        <h1>Browse Lost and Found Items</h1>
        <p>Search for missing belongings or help someone recover an item.</p>
    </div>

    <section class="search-box">
        <form method="GET" action="browse.php" class="search-form">

            <input
                type="search"
                name="search"
                placeholder="Search by item, category, description or location..."
                value="<?= escapeHTML($search) ?>"
            >

            <select name="type">
                <option value="all"
                    <?= $type === 'all' ? 'selected' : '' ?>>
                    All Reports
                </option>

                <option value="lost"
                    <?= $type === 'lost' ? 'selected' : '' ?>>
                    Lost Items
                </option>

                <option value="found"
                    <?= $type === 'found' ? 'selected' : '' ?>>
                    Found Items
                </option>
            </select>

            <button type="submit" class="btn">Search Items</button>

            <a href="browse.php" class="btn">Reset</a>

        </form>
    </section>

    <section class="reports-grid">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($report = $result->fetch_assoc()): ?>

                <article class="report-card">

                    <?php
                    $imagePath = $report['image_path'] ?? '';

                    // Display only image paths saved inside the uploads folder.
                    $safeImagePath = '';

                    if (
                        $imagePath !== '' &&
                        strpos($imagePath, 'uploads/') === 0 &&
                        !str_contains($imagePath, '..') &&
                        !str_contains($imagePath, '\\')
                    ) {
                        $safeImagePath = $imagePath;
                    }
                    ?>

                    <?php if ($safeImagePath !== ''): ?>

                        <img
                            src="<?= escapeHTML($safeImagePath) ?>"
                            alt="<?= escapeHTML($report['item_name']) ?>"
                            class="item-image"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <div class="no-image">
                            No image available
                        </div>

                    <?php endif; ?>

                    <div class="report-content">

                        <span class="badge <?= escapeHTML($report['report_type']) ?>">
                            <?= escapeHTML($report['report_type']) ?>
                        </span>

                        <h3><?= escapeHTML($report['item_name']) ?></h3>

                        <p>
                            <strong>Category:</strong>
                            <?= escapeHTML($report['category']) ?>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= escapeHTML($report['location']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= escapeHTML($report['report_date']) ?>
                        </p>

                        <p class="description">
                            <strong>Description:</strong><br>

                            <?= nl2br(escapeHTML(
                                $report['description'] ?: 'No description provided.'
                            )) ?>
                        </p>

                        <p>
                            <strong>Reported by:</strong>
                            <?= escapeHTML($report['full_name'] ?: 'Unknown user') ?>
                        </p>

                        <p class="status">
                            Status: <?= escapeHTML($report['status']) ?>
                        </p>

                        <?php
$reportType = strtolower(trim($report['report_type'] ?? ''));
$reportStatus = strtolower(trim($report['status'] ?? ''));

if (
    $reportType === 'found'
    && $reportStatus === 'open'
    && isset($_SESSION['user_id'])
    && (int)$_SESSION['user_id'] !== (int)$report['user_id']
):
?>

    <a
        href="claim_item.php?id=<?= (int)$report['id'] ?>"
        class="btn"
    >
        Claim This Item
    </a>

<?php endif; ?>
                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-message">
                <h3>No items found</h3>

                <p>
                    There are no reports matching your search.
                    Try another keyword or change the filter.
                </p>

                <a href="browse.php" class="btn">View All Reports</a>
            </div>

        <?php endif; ?>

    </section>

    <div class="report-links">
        <h3>Have you lost or found something?</h3>

        <a href="report_lost.php" class="btn">Report a Lost Item</a>

        <a href="report_found.php" class="btn">Report a Found Item</a>
    </div>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>