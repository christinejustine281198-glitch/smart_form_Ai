<?php
// manager_dashboard.php

// Path to SQLite DB
$dataDir = __DIR__ . '/data';
$dbPath = $dataDir . '/forms.db';

try {
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

    require_once 'db.php';

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle deletion request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_form_id'])) {
        $formIdToDelete = (int) $_POST['delete_form_id'];

        // Delete submissions of the form
        $stmt = $db->prepare("DELETE FROM submissions WHERE form_id = ?");
        $stmt->execute([$formIdToDelete]);

        // Delete the form itself
        $stmt = $db->prepare("DELETE FROM forms WHERE id = ?");
        $stmt->execute([$formIdToDelete]);

        // Redirect to avoid resubmission on refresh
        header("Location: manager_dashboard.php");
        exit;
    }

    // Fetch all saved forms
    $forms = $db->query("SELECT id, title, created_at FROM forms ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Manager Dashboard - SmartCardAI</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background:#012e00; padding:30px; }
h1 { color:#c7e7c5; }
table { width:100%; border-collapse:collapse; margin-top:18px; background:#ecfbffcf; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
th,td { padding:12px; border-bottom:1px solid #eee; text-align:left; }
th { background:#197c28e6; color:#fff; }
button { background:#0c1b0b; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }
.query-box { margin-top:28px; background:#ecfbffcf; padding:18px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.06); }
textarea { width:90%; height:35px; padding:10px; border-radius:6px; border:1px solid #ccc; font-size:14px; }
.select-inline { display:inline-block; margin-right:10px; }
.response-box { margin-top:14px; padding:12px; background:#f9fafc; border-left:4px solid #1a237e; white-space:pre-wrap; }
.nav-button { margin-bottom: 20px; }
.nav-button a button { font-weight: 600; font-size: 16px; }

/* Delete button style */
button.delete-btn {
    background: #d9534f;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 8px;
}

button.delete-btn:hover {
    background: #c9302c;
}
</style>
</head>
<body>

<header>
    <h1>Manager Dashboard</h1>
    <p style="color:#ffffff;">List of saved forms — click <strong>View Submissions</strong> to inspect them.</p>
</header>

<div class="container">

    <div class="nav-button">
        <a href="index.php"><button type="button">Return to Form Generator</button></a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th><th>Form Title</th><th>Created At</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['id']) ?></td>
                <td><?= htmlspecialchars($f['title']) ?></td>
                <td><?= htmlspecialchars($f['created_at']) ?></td>
                <td>
                    <a href="view_submissions.php?form_id=<?= urlencode($f['id']) ?>">
                        <button type="button">View Submissions</button>
                    </a>
                    <button type="button" onclick="shareForm(<?= htmlspecialchars($f['id']) ?>)">Share</button>

                    <!-- Delete button -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this form and all its submissions?');">
                        <input type="hidden" name="delete_form_id" value="<?= htmlspecialchars($f['id']) ?>">
                        <button type="submit" class="delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="query-box">
        <h2>Ask Gemini about submissions</h2>
        <div>
            <label class="select-inline">Context form:
                <select id="formSelect">
                    <option value="">— Use all forms —</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" onclick="prefillExample()">Example Q</button>
        </div>

        <p style="margin-top:10px">Type a question about submissions (e.g. "Show average age" or "List entries with Blood Group O+")</p>
        <textarea id="query" placeholder="Type your question here..."></textarea><br>
        <button type="button" style="margin-top:10px" onclick="askGemini()">Ask Gemini</button>

        <div id="responseBox" class="response-box" style="display:none"></div>
    </div>

</div>

<script>
async function askGemini() {
    const query = document.getElementById('query').value.trim();
    if (!query) {
        alert('Please enter a question.');
        return;
    }
    const formId = document.getElementById('formSelect').value || null;

    const box = document.getElementById('responseBox');
    box.style.display = 'block';
    box.innerText = 'Processing...';

    try {
        const res = await fetch('query_gemini.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query, form_id: formId })
        });
        const data = await res.json();
        if (data.error) {
            box.innerText = 'Error: ' + data.error;
        } else {
            box.innerText = data.answer || '[no answer returned]';
        }
    } catch (e) {
        box.innerText = 'Request failed: ' + e.message;
    }
}

function prefillExample() {
    document.getElementById('query').value = 'Show me the average age from the selected form.';
}

function shareForm(formId) {
    const shareUrl = `${window.location.origin}/smart_form/fill_form.php?form_id=${formId}`;

    navigator.clipboard.writeText(shareUrl).then(() => {
        alert('Share link copied to clipboard:\n' + shareUrl);
    }).catch(() => {
        alert('Failed to copy share link. Please copy manually:\n' + shareUrl);
    });
}
</script>

</body>
</html>
