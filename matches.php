<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function escapeHTML($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function normalizeText($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function similarityScore($first, $second) {
    $first = normalizeText($first);
    $second = normalizeText($second);

    if ($first === '' || $second === '') {
        return 0;
    }

    if ($first === $second) {
        return 100;
    }

    similar_text($first, $second, $percentage);
    return $percentage;
}

// Fetch available lost and found reports.
$sql = "
    SELECT id, user_id, report_type, item_name, category,
           description, location, report_date, status
    FROM reports
    WHERE status IN ('open', 'under_review')
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . escapeHTML($conn->error));
}

$lostItems = [];
$foundItems = [];

while ($row = $result->fetch_assoc()) {
    if ($row['report_type'] === 'lost') {
        $lostItems[] = $row;
    } elseif ($row['report_type'] === 'found') {
        $foundItems[] = $row;
    }
}

// Compare lost reports against found reports.
$matches = [];

foreach ($lostItems as $lost) {
    foreach ($foundItems as $found) {

        // Do not match reports belonging to the same user.
        if ((int)$lost['user_id'] === (int)$found['user_id']) {
            continue;
        }

        $nameScore = similarityScore(
            $lost['item_name'],
            $found['item_name']
        );

        $categoryScore = similarityScore(
            $lost['category'],
            $found['category']
        );

        $descriptionScore = similarityScore(
            $lost['description'],
            $found['description']
        );

        $locationScore = similarityScore(
            $lost['location'],
            $found['location']
        );

        $score =
            ($nameScore * 0.45) +
            ($categoryScore * 0.25) +
            ($descriptionScore * 0.20) +
            ($locationScore * 0.10);

        if ($score >= 30) {
            $matches[] = [
                'lost' => $lost,
                'found' => $found,
                'score' => round($score),
                'name' => round($nameScore),
                'category' => round($categoryScore),
                'description' => round($descriptionScore),
                'location' => round($locationScore)
            ];
        }
    }
}

usort($matches, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

$matches = array_slice($matches, 0, 50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Possible Matches | SmartFind</title>

    <link rel="stylesheet" href="style.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .navbar {
            background: #172554;
            color: white;
            padding: 18px 5%;
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
            margin-right: 15px;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 35px auto;
        }

        .heading {
            text-align: center;
            margin-bottom: 30px;
        }

        .heading h1 {
            color: #172554;
        }

        .notice {
            background: #fff7ed;
            padding: 15px;
            border-left: 4px solid #f97316;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .match-card {
            background: white;
            padding: 24px;
            margin-bottom: 24px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
        }

        .score {
            color: #166534;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .item-panel {
            padding: 18px;
            background: #f8fafc;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .item-panel h3 {
            color: #172554;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .lost {
            background: #fee2e2;
            color: #991b1b;
        }

        .found {
            background: #dcfce7;
            color: #166534;
        }

        .details {
            background: #eef2ff;
            padding: 15px;
            margin-top: 18px;
            border-radius: 8px;
        }

        .empty {
            text-align: center;
            background: white;
            padding: 35px 20px;
            border-radius: 10px;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            background: #172554;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 8px 5px 0 0;
        }

        @media (max-width: 650px) {
            .comparison {
                grid-template-columns: 1fr;
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
        <a href="logout.php">Logout</a>
    </div>
</nav>

<main class="container">

    <div class="heading">
        <h1>🧠 Possible Item Matches</h1>
        <p>Find lost items by comparing them with found-item reports.</p>
    </div>

    <div class="notice">
        <strong>Important:</strong>
        A high similarity score suggests a possible match but does not
        prove ownership. Verify the item's details before returning it.
    </div>

    <?php if (empty($matches)): ?>

        <div class="empty">
            <h2>No Possible Matches Yet</h2>

            <p>
                There are currently no reports meeting the matching
                threshold. Add lost and found reports to test the feature.
            </p>

            <a href="browse.php" class="btn">Browse Items</a>
            <a href="report_lost.php" class="btn">Report Lost Item</a>
            <a href="report_found.php" class="btn">Report Found Item</a>
        </div>

    <?php else: ?>

        <p>
            <strong><?= count($matches) ?> possible match(es) found.</strong>
        </p>

        <?php foreach ($matches as $match): ?>

            <article class="match-card">

                <div class="score">
                    <?= escapeHTML($match['score']) ?>% Similarity
                </div>

                <div class="comparison">

                    <section class="item-panel">
                        <span class="badge lost">Lost Item</span>

                        <h3>
                            <?= escapeHTML($match['lost']['item_name']) ?>
                        </h3>

                        <p>
                            <strong>Category:</strong>
                            <?= escapeHTML($match['lost']['category']) ?>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= escapeHTML($match['lost']['location']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= escapeHTML($match['lost']['report_date']) ?>
                        </p>

                        <p>
                            <strong>Description:</strong><br>
                            <?= nl2br(escapeHTML($match['lost']['description'])) ?>
                        </p>
                    </section>

                    <section class="item-panel">
                        <span class="badge found">Found Item</span>

                        <h3>
                            <?= escapeHTML($match['found']['item_name']) ?>
                        </h3>

                        <p>
                            <strong>Category:</strong>
                            <?= escapeHTML($match['found']['category']) ?>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= escapeHTML($match['found']['location']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= escapeHTML($match['found']['report_date']) ?>
                        </p>

                        <p>
                            <strong>Description:</strong><br>
                            <?= nl2br(escapeHTML($match['found']['description'])) ?>
                        </p>
                    </section>

                </div>

                <div class="details">
                    <strong>Similarity Breakdown</strong>

                    <p>Item name: <?= escapeHTML($match['name']) ?>%</p>
                    <p>Category: <?= escapeHTML($match['category']) ?>%</p>
                    <p>Description: <?= escapeHTML($match['description']) ?>%</p>
                    <p>Location: <?= escapeHTML($match['location']) ?>%</p>
                </div>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

</body>
</html>

<?php
$result->free();
$conn->close();
?>