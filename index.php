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

$apiKey = getenv('API_KEY'); // Replace with your actual API key
if (!$apiKey) {
    die('API key is missing. Please set it in your environment variables.');
}

$success_message = "";
$form_structure = [];
$form_saved = false; // <-- Added flag to detect save success

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

        // Set save flag true
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
}

// Fetch saved forms for submissions tab
$saved_forms_stmt = $db->query("SELECT id, title, created_at FROM forms ORDER BY created_at DESC");
$saved_forms = $saved_forms_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ALERT SCRIPT after PHP save logic -->
<?php if ($form_saved): ?>
<script>
    alert('Form saved successfully! Your shareable link is ready.');
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>SmartCardAI - Dynamic Form Generator</title>
<style>
/* Your existing CSS here */
body { font-family: 'Segoe UI', sans-serif; background:#012e00; margin:0; padding:0; color:#000; }
header { background:#197c28e6; color:white; padding:15px 45px; display:flex; align-items:center; justify-content:space-between; }
header img { height:50px; }
header h1 { font-size:20px; margin:0; }
.container { width:80%; margin:30px auto; background:#ecfbffcf; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:30px; color:#000; }
/* ... rest of your CSS unchanged ... */
body {
  font-family: 'Segoe UI', sans-serif;
  background: #012e00;
  margin: 0;
  padding: 0;
  color: #000;
}

header {
  background: #197c28e6;
  color: white;
  padding: 15px 45px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

header img {
  height: 50px;
}

header h1 {
  font-size: 20px;
  margin: 0;
}

.container {
  width: 80%;
  margin: 30px auto;
  background: #ecfbffcf;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  padding: 30px;
  color: #000;
}

input[type="text"],
input[type="email"],
input[type="number"],
textarea,
select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
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

.nav-buttons {
  margin-bottom: 20px;
}

.nav-buttons button {
  margin-right: 10px;
  background: #0c1b0b;
}

.nav-buttons button.active {
  background: #146c20;
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

    <!-- Navigation Buttons -->
    <div class="nav-buttons">
  
        <a href="manager_dashboard.php"><button type="button">Submissions</button></a>
    </div>

    <!-- Form Generation Tab -->
    <div id="formGeneration" class="tab-content active">
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
    $name  = is_array($field['name']) ? implode('_', $field['name']) : $field['name'];
    // Normalize type for unexpected AI outputs
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
            <label>
                <input type="radio" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($opt) ?>" />
                <?= htmlspecialchars($opt) ?>
            </label>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>


                <button type="submit" name="save_form" class="submit-btn">Save & Share</button>
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
                        // Hide the entire link container after copying
                        var container = document.getElementById("linkContainer");
                        container.style.display = "none";
                    });
                }
            </script>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    

<script>
const btnGen = document.getElementById('btnGen');
const btnSub = document.getElementById('btnSub');
const formGen = document.getElementById('formGeneration');
const formSub = document.getElementById('formSubmissions');

btnGen.addEventListener('click', () => {
    btnGen.classList.add('active');
    btnSub.classList.remove('active');
    formGen.classList.add('active');
    formSub.classList.remove('active');
});

btnSub.addEventListener('click', () => {
    btnSub.classList.add('active');
    btnGen.classList.remove('active');
    formSub.classList.add('active');
    formGen.classList.remove('active');
});
</script>

</body>
</html>
