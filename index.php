<?php
session_start();

// Writable directory for SQLite DB
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Database setup
$dbPath = $dataDir . '/forms.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create forms table
$db->exec("CREATE TABLE IF NOT EXISTS forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    structure TEXT,
    created_at TEXT
)");

$apiKey = getenv('API_KEY') ?: 'your_fallback_key_here';

if (!$apiKey) {
    die('API key is missing. Please set it in your environment variables.');
}

$success_message = "";
$form_structure = [];
$form_saved = false;

// Save form
if (isset($_POST['save_form']) && !empty($_POST['structure'])) {
    $title = $_POST['title'] ?? '';
    $structure = $_POST['structure'] ?? '';
    if ($title && $structure) {
        $stmt = $db->prepare("INSERT INTO forms (title, structure, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$title, $structure]);
        $form_id = $db->lastInsertId();
        $success_message = "http://" . $_SERVER['HTTP_HOST'] . "/fill_form.php?form_id=" . $form_id;
        $form_structure = json_decode($structure, true);
        $form_saved = true;
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

    $_SESSION['last_prompt'] = $prompt;
    $_SESSION['last_form_structure'] = $form_structure;
}

// Refine form
if (isset($_POST['refine']) && !empty($_POST['refine_prompt'])) {
    $refinePrompt = trim($_POST['refine_prompt']);
    $originalPrompt = $_SESSION['last_prompt'] ?? '';
    $originalForm = $_SESSION['last_form_structure'] ?? [];

    if (!$originalPrompt || empty($originalForm)) {
        $form_structure = [];
    } else {
        $contextMessage = "You are refining a previously generated form.\n\n".
                          "Original prompt:\n$originalPrompt\n\n".
                          "Generated form fields (JSON):\n" . json_encode($originalForm, JSON_UNESCAPED_UNICODE) . "\n\n".
                          "User refinement instruction:\n$refinePrompt\n\n".
                          "Return ONLY the updated form fields as a clean JSON array.";

        $data = [
            "contents" => [
                ["parts" => [["text" => $contextMessage]]]
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

        $_SESSION['last_prompt'] = $originalPrompt . "\nRefinement: " . $refinePrompt;
        $_SESSION['last_form_structure'] = $form_structure;
    }
}

// Reset refinement context
if (isset($_POST['reset_context'])) {
    unset($_SESSION['last_prompt'], $_SESSION['last_form_structure']);
    $form_structure = [];
}

// Load saved forms
$saved_forms_stmt = $db->query("SELECT id, title, created_at FROM forms ORDER BY created_at DESC");
$saved_forms = $saved_forms_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($form_saved): ?>
<script>
    alert('Form saved successfully! Your shareable link is ready.');
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SmartCardAI - Dynamic Form Generator</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #ffffff;
            margin: 0;
            padding: 0;
            color: #000;
        }
        header {
            background: #000000;
            color: #ffffff;
            padding: 15px 45px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header img {
            height: 50px;
        }
        .container {
            width: 80%;
            margin: 30px auto;
            background: #ecfbffcf;
            border-radius: 12px;
            box-shadow: 0 15px 24px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        input[type="text"], input[type="email"], input[type="number"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #144e05;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }
        .form-field {
            margin-bottom: 20px;
        }
        button {
            background: #0c1b0b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }
        button:hover {
            background: #0f521d;
        }
        .submit-btn {
            background: #197c28;
            font-weight: 600;
            margin-top: 15px;
        }
        .form-preview {
            background: #ffffffd4;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #d6e9d6;
        }
        #linkContainer {
            margin-top: 15px;
            background: #dff0d8;
            padding: 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #shareLink {
            flex: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<header>
    <img src="logo.png.jpeg" alt="SmartCardAI Logo" />
    <h1><b>SmartCard AI Form Generator</b></h1>
</header>


<div class="container">
  <div class="nav-buttons">
  
        <a href="manager_dashboard.php"><button type="button">Submissions</button></a>
    </div>
    <h2>Generate Form using AI</h2>
    <form method="post">
        <input type="text" name="prompt" placeholder="e.g., Feedback form with name, age, and feedback" required />
        <button type="submit" name="generate" class="submit-btn">Generate Form</button>
    </form>

    <?php if (!empty($form_structure)) : ?>
        <div class="form-preview">
            <h3>Generated Form Preview</h3>
            <form method="post">
                <input type="hidden" name="structure" value='<?= htmlspecialchars(json_encode($form_structure), ENT_QUOTES) ?>' />
                <input type="text" name="title" placeholder="Enter Form Title" required />
                <br /><br />
                <?php foreach ($form_structure as $field): 
                    $label = is_array($field['label']) ? implode(' ', $field['label']) : $field['label'];
                    $name = is_array($field['name']) ? implode('_', $field['name']) : $field['name'];
                    $rawType = isset($field['type']) ? strtolower($field['type']) : 'text';
                    $type = match($rawType) {
                        'phone', 'phone number', 'tel' => 'tel',
                        'email' => 'email',
                        'number', 'numeric' => 'number',
                        'date' => 'date',
                        'textarea' => 'textarea',
                        'radio' => 'radio',
                        'select' => 'select',
                        default => 'text'
                    };
                ?>
                <div class="form-field">
                    <label><?= htmlspecialchars($label) ?></label><br />
                    <?php if (in_array($type, ['text', 'email', 'number', 'date', 'tel'])): ?>
                        <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($name) ?>" />
                    <?php elseif ($type === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($name) ?>"></textarea>
                    <?php elseif ($type === 'select' && isset($field['options'])): ?>
                        <select name="<?= htmlspecialchars($name) ?>">
                            <?php foreach ($field['options'] as $opt): ?>
                                <option><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'radio' && isset($field['options'])): ?>
                        <?php foreach ($field['options'] as $opt): ?>
                            <label><input type="radio" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($opt) ?>" /> <?= htmlspecialchars($opt) ?></label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <button type="submit" name="save_form" class="submit-btn">Save & Share</button>
            </form>

            <form method="post" style="margin-top: 20px;">
                <input type="text" name="refine_prompt" placeholder="Refine this form (e.g., change age to dropdown, remove message)" />
                <button type="submit" name="refine" class="submit-btn">Refine Form</button>
                <button type="submit" name="reset_context" class="submit-btn" style="background:#9e1c1c">Reset</button>
            </form>

            <?php if (!empty($success_message)) : ?>
                <div id="linkContainer">
                    <input type="text" value="<?= htmlspecialchars($success_message) ?>" id="shareLink" readonly />
                    <button type="button" onclick="copyLink()">Copy Link</button>
                </div>
                <script>
                    function copyLink() {
                        var copyText = document.getElementById("shareLink");
                        navigator.clipboard.writeText(copyText.value).then(function() {
                            alert("Link copied to clipboard!");
                            document.getElementById("linkContainer").style.display = "none";
                        });
                    }
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
