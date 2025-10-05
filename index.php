<?php
// Writable directory for SQLite DB
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Database setup
$dbPath = $dataDir . '/forms.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create forms table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    structure TEXT,
    created_at TEXT
)");


$apiKey = getenv('GEMINI_API_KEY');

$success_message = "";
$form_structure = [];

// Save form
if (isset($_POST['save_form']) && !empty($_POST['structure'])) {
    $title = $_POST['title'] ?? '';
    $structure = $_POST['structure'] ?? '';

    if ($title && $structure) {
        $stmt = $db->prepare("INSERT INTO forms (title, structure, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$title, $structure]);
        $form_id = $db->lastInsertId();

        // Shareable link
        $success_message = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/fill_form.php?form_id=" . $form_id;

        // Keep form preview visible
        $form_structure = json_decode($structure, true);
    }
}

// Generate form
if (isset($_POST['generate']) && !empty($_POST['prompt'])) {
    $prompt = $_POST['prompt'];
    $data = [
        "contents" => [
            ["parts" => [["text" => "Generate a JSON array of form fields (label, type, name, options if any) for: $prompt. Return only JSON array."]]]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);
    $raw_output = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    preg_match('/\[[\s\S]*\]/', $raw_output, $matches);
    $clean_output = $matches[0] ?? '[]';
    $form_structure = json_decode($clean_output, true) ?: [];

    // Remove duplicates
    $unique = [];
    $filtered = [];
    foreach ($form_structure as $field) {
        $label_key = strtolower(is_array($field['label']) ? implode(' ', $field['label']) : $field['label']);
        if ($label_key && !in_array($label_key, $unique)) {
            $unique[] = $label_key;
            $filtered[] = $field;
        }
    }
    $form_structure = $filtered;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SmartCardAI - Dynamic Form Generator</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background:#012e00; margin:0; padding:0; }
header { background:#197c28e6; color:white; padding:15px 45px; display:flex; align-items:center; justify-content:space-between; }
header img { height:50px; }
header h1 { font-size:20px; margin:0; }
.container { width:80%; margin:30px auto; background:#ecfbffcf; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:30px; }
textarea,input[type="text"],input[type="email"],input[type="number"],select { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-top:10px; font-size:14px; }
button { background:#0c1b0b; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; margin-top:15px; }
button:hover { background:#06a027ff; }
.form-preview { margin-top:25px; padding:20px; background:#f9fafc; border-radius:10px; }
.form-field { margin-bottom:15px; }
#linkContainer { margin-top:15px; padding:10px; background:#e8f0fe; border:1px solid #90caf9; border-radius:6px; display:flex; align-items:center; gap:10px; }
#shareLink { flex:1; padding:5px; border:1px solid #ccc; border-radius:4px; }
</style>
</head>
<body>

<header>
    <img src="logo.png.jpeg" alt="SmartCardAI Logo">
    <h1><b>SmartCard AI  Form Generator</b></h1>
</header>

<div class="container">
    <h2>Generate Form using AI</h2>
    <form method="post">
        <input type="text" name="prompt" placeholder="e.g., Feedback form with name, age, and feedback" required>
        <button type="submit" name="generate">Generate Form</button>
    </form>
    

    <?php if (!empty($form_structure)) : ?>
    <div class="form-preview">
        <h3>Generated Form Preview</h3>
        <form method="post">
            <input type="hidden" name="structure" value='<?= htmlspecialchars(json_encode($form_structure), ENT_QUOTES) ?>'>
            <input type="text" name="title" placeholder="Enter Form Title" required>
            <br><br>

            <?php foreach ($form_structure as $field):
                $label = is_array($field['label']) ? implode(' ', $field['label']) : $field['label'];
                $name  = is_array($field['name']) ? implode('_', $field['name']) : $field['name'];
            ?>
            <div class="form-field">
                <label><?= htmlspecialchars($label) ?></label><br>
                <?php if (in_array($field['type'], ['text','email','number'])): ?>
                    <input type="<?= $field['type'] ?>" name="<?= htmlspecialchars($name) ?>">
                <?php elseif ($field['type'] == 'textarea'): ?>
                    <textarea name="<?= htmlspecialchars($name) ?>"></textarea>
                <?php elseif ($field['type'] == 'select' && isset($field['options'])): ?>
                    <select name="<?= htmlspecialchars($name) ?>">
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

            <button type="submit" name="save_form">Save & Share</button>
        </form>

        <?php if (!empty($success_message)) : ?>
        <div id="linkContainer">
            <input type="text" value="<?= $success_message ?>" id="shareLink" readonly>
            <button type="button" onclick="copyLink()">Copy Link</button>
        </div>

       <script>
function copyLink() {
    var copyText = document.getElementById("shareLink");
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Link copied to clipboard!");
        // Hide the entire link container after copying
        var container = document.getElementById("linkContainer");
        container.style.display = "none";
    });
}
</script>

        <?php endif; ?>

        <!-- View Saved Submissions Button -->
        <div style="margin-top:20px;">
            <a href="manager_dashboard.php">
                <button type="button">View Saved Submissions</button>
            </a>
        </div>

    </div>
    <?php endif; ?>
</div>

</body>
</html>
