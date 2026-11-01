```php
<?php
session_start();

require_once "database.php";

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $report_date = $_POST['report_date'] ?? '';

    $allowed_categories = [
        'Electronics',
        'Documents',
        'Bags',
        'Clothing',
        'Keys',
        'Jewelry',
        'Other'
    ];

    // Validate date
    $date = DateTime::createFromFormat('!Y-m-d', $report_date);

    $valid_date = $date &&
        $date->format('Y-m-d') === $report_date;

    if (
        $item_name === '' ||
        !in_array($category, $allowed_categories, true) ||
        $description === '' ||
        $location === '' ||
        !$valid_date ||
        $date > new DateTime('today')
    ) {
        $error = "Please complete all fields correctly and select a valid date.";
    } else {

        $image_path = null;

        // Handle optional image upload
        if (
            isset($_FILES['item_image']) &&
            $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {

                $error = "The image upload failed. Please try again.";

            } elseif ($_FILES['item_image']['size'] > 3 * 1024 * 1024) {

                $error = "The image must be smaller than 3 MB.";

            } else {

                $tmp_file = $_FILES['item_image']['tmp_name'];

                $allowed_mimes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $image_functions = [
                    'image/jpeg' => IMAGETYPE_JPEG,
                    'image/png'  => IMAGETYPE_PNG,
                    'image/webp' => IMAGETYPE_WEBP
                ];

                $image_info = @getimagesize($tmp_file);

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp_file);

                // Verify genuine image
                if (
                    !$image_info ||
                    !isset($allowed_mimes[$mime]) ||
                    !isset($image_functions[$mime]) ||
                    $image_info[2] !== $image_functions[$mime]
                ) {

                    $error = "Only valid JPG, PNG, or WebP images are allowed.";

                } else {

                    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {

                        $error = "The uploads folder is missing or not writable.";

                    } else {

                        $filename = bin2hex(random_bytes(16))
                            . '.' . $allowed_mimes[$mime];

                        if (
                            move_uploaded_file(
                                $tmp_file,
                                $upload_dir . DIRECTORY_SEPARATOR . $filename
                            )
                        ) {

                            $image_path = 'uploads/' . $filename;

                        } else {

                            $error = "Unable to save the uploaded image.";

                        }
                    }
                }
            }
        }

        // Save report into database
        if ($error === '') {

            $user_id = (int) $_SESSION['user_id'];

            try {

                $stmt = $conn->prepare(
                    "INSERT INTO reports
                    (user_id, report_type, item_name, category,
                     description, location, report_date, image_path)
                    VALUES (?, 'lost', ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issssss",
                    $user_id,
                    $item_name,
                    $category,
                    $description,
                    $location,
                    $report_date,
                    $image_path
                );

                $stmt->execute();

                $stmt->close();

                $success = "Your lost-item report has been submitted successfully.";

                $_POST = [];

            } catch (Throwable $e) {

                error_log("Lost report error: " . $e->getMessage());

                if ($image_path && is_file(__DIR__ . '/' . $image_path)) {
                    unlink(__DIR__ . '/' . $image_path);
                }

                $error = "Unable to save your report. Please try again.";

            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item | SmartFind</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .report-container {
            max-width: 650px;
            margin: 45px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,.06);
        }

        .report-container h1 {
            color: #172554;
            margin-bottom: 8px;
        }

        .report-container > p {
            color: #64748b;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
        }

        .submit-button {
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
    </style>
</head>

<body>

<header class="navbar">
    <div class="logo">Smart<span>Find</span></div>

    <nav>
        <a href="index.php">Home</a>
        <a href="report_found.php">Report Found</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="report-container">

    <h1>Report a Lost Item</h1>
    <p>Provide accurate details to help someone identify your missing item.</p>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="browse.php">Browse existing reports</a>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label for="item_name">Item Name</label>
            <input
                type="text"
                id="item_name"
                name="item_name"
                maxlength="150"
                required
                value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="category">Category</label>

            <select id="category" name="category" required>
                <option value="">Select category</option>

                <?php foreach ([
                    'Electronics',
                    'Documents',
                    'Bags',
                    'Clothing',
                    'Keys',
                    'Jewelry',
                    'Other'
                ] as $cat): ?>

                    <option
                        value="<?= htmlspecialchars($cat) ?>"
                        <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($cat) ?>
                    </option>

                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Item Description</label>

            <textarea
                id="description"
                name="description"
                rows="4"
                maxlength="5000"
                required
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="location">Where Did You Lose It?</label>

            <input
                type="text"
                id="location"
                name="location"
                maxlength="255"
                placeholder="e.g. Nakuru town, near the bus station"
                required
                value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="report_date">Date Lost</label>

            <input
                type="date"
                id="report_date"
                name="report_date"
                max="<?= date('Y-m-d') ?>"
                required
                value="<?= htmlspecialchars($_POST['report_date'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="item_image">Item Photograph (Optional)</label>

            <input
                type="file"
                id="item_image"
                name="item_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            >

            <small>JPG, PNG, or WebP. Maximum size: 3 MB.</small>
        </div>

        <button type="submit" class="btn primary submit-button">
            Submit Lost-Item Report
        </button>

    </form>

</div>

</body>
</html>
```
