<?php
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) mkdir($dataDir, 0777, true);

$dbPath = $dataDir . '/forms.db';
require_once 'db.php';
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create submissions table
$db->exec("CREATE TABLE IF NOT EXISTS submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id INTEGER,
    responses TEXT,
    submitted_at TEXT
)");

// Get form_id from URL
$form_id = $_GET['form_id'] ?? null;
if (!$form_id) die("Form ID missing");

// Fetch form
$stmt = $db->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$form_id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$form) die("Form not found");

// Decode form structure
$form_structure = json_decode($form['structure'], true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responses = json_encode($_POST);
    $stmt = $db->prepare("INSERT INTO submissions (form_id, responses, submitted_at) VALUES (?, ?, datetime('now'))");
    $stmt->execute([$form_id, $responses]);

    echo "<script>
        alert('Form submitted successfully!');
        window.location='thankyou.php';
    </script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fill Form - <?= htmlspecialchars($form['title']) ?></title>
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: #012e00;
        margin: 0;
        padding: 0;
    }
    header {
        background:#197c28e6;
        color: white;
        padding: 15px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    header img {
        height: 45px;
    }
    header h1 {
        font-size: 20px;
        margin: 0;
    }
    .container {
        width: 80%;
        margin: 30px auto;
        background:#ecfbffcf;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 30px;
    }
    input[type="text"], input[type="email"], input[type="number"], textarea, select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        margin-top: 10px;
        font-size: 14px;
    }
    button {
        background:#0c1b0b;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        margin-top: 15px;
    }
    button:hover {
        background: #06a027ff;
    }
    .form-field {
        margin-bottom: 15px;
    }
</style>
</head>
<body>

<header>
    <img src="logo.png.jpeg" alt="SmartCardAI Logo">
    <h1><?= htmlspecialchars($form['title']) ?></h1>
</header>

<div class="container">
    <form method="post">
        <?php foreach ($form_structure as $field): 
            $label = is_array($field['label']) ? implode(' ', $field['label']) : $field['label'];
            $name = is_array($field['name']) ? implode('_', $field['name']) : $field['name'];
        ?>
            <div class="form-field">
                <label><?= htmlspecialchars($label) ?></label><br>
                <?php if ($field['type'] == 'text' || $field['type'] == 'email' || $field['type'] == 'number'): ?>
                    <input type="<?= $field['type'] ?>" name="<?= htmlspecialchars($name) ?>" required>
                <?php elseif ($field['type'] == 'textarea'): ?>
                    <textarea name="<?= htmlspecialchars($name) ?>" required></textarea>
                <?php elseif ($field['type'] == 'select' && isset($field['options'])): ?>
                    <select name="<?= htmlspecialchars($name) ?>" required>
                        <?php foreach ($field['options'] as $opt): ?>
                            <option><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field['type'] == 'radio' && isset($field['options'])): ?>
                    <?php foreach ($field['options'] as $opt): ?>
                        <label><input type="radio" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($opt) ?>"> <?= htmlspecialchars($opt) ?></label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit">Submit</button>
    </form>
</div>

</body>
</html>

