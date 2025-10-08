<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to DB
$db = new PDO('sqlite:' . __DIR__ . '/forms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create forms table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    structure TEXT NOT NULL,
    created_at TEXT NOT NULL
)");

// Define form structure to preview
$form_structure = [
    ['type' => 'text', 'label' => 'Name', 'name' => 'name'],
    ['type' => 'email', 'label' => 'Email', 'name' => 'email'],
    ['type' => 'select', 'label' => 'Country', 'name' => 'country', 'options' => ['USA', 'Canada', 'Other']]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $title = trim($_POST['title'] ?? '');
    $structure = $_POST['structure'] ?? '';

    if ($title !== '' && $structure !== '') {
        try {
            $stmt = $db->prepare("INSERT INTO forms (title, structure, created_at) VALUES (?, ?, datetime('now'))");
            $success = $stmt->execute([$title, $structure]);
            if ($success) {
                // Show alert and redirect
                echo "<script>
                    alert('Form saved successfully!');
                    window.location.href = 'manager_dashboard.php';
                </script>";
                exit;
            } else {
                echo "<p style='color:red;'>Failed to save form.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color:red;'>DB error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color:red;'>Title or structure missing.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Form Generator</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background:#012e00; color:#fff; padding:20px; }
    input, select, textarea, button { font-size: 14px; padding: 8px; margin: 6px 0; border-radius: 5px; border: none; }
    input[type="text"], select, textarea { width: 100%; }
    button { background:#06a027; color:#fff; cursor:pointer; border:none; }
    button:hover { background:#04a01f; }
    label { font-weight: 600; }
    .form-preview { background:#ecfbffcf; padding: 20px; border-radius: 12px; color:#000; max-width: 600px; }
</style>
</head>
<body>

<h1>Create & Save Your Form</h1>

<div class="form-preview">
    <h3>Form Preview</h3>

    <form method="post" action="">
        <label for="title">Form Title:</label><br />
        <input type="text" id="title" name="title" placeholder="Enter form title" required /><br /><br />

        <?php foreach ($form_structure as $field): 
            $label = $field['label'];
            $name = $field['name'];
        ?>
            <label><?= htmlspecialchars($label) ?></label><br />
            <?php if (in_array($field['type'], ['text', 'email', 'number'])): ?>
                <input type="<?= htmlspecialchars($field['type']) ?>" name="<?= htmlspecialchars($name) ?>" disabled />
            <?php elseif ($field['type'] == 'textarea'): ?>
                <textarea name="<?= htmlspecialchars($name) ?>" disabled></textarea>
            <?php elseif ($field['type'] == 'select' && isset($field['options'])): ?>
                <select name="<?= htmlspecialchars($name) ?>" disabled>
                    <?php foreach ($field['options'] as $opt): ?>
                        <option><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <br /><br />
        <?php endforeach; ?>

        <?php
        $structure_json = json_encode($form_structure);
        if ($structure_json === false) {
            die('Error encoding form structure to JSON');
        }
        ?>
        <input type="hidden" name="structure" value='<?= htmlspecialchars($structure_json, ENT_QUOTES) ?>' />

        <button type="submit" name="save_form">Save & Share</button>
    </form>
</div>

</body>
</html>
