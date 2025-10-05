<?php
// manager_dashboard.php
$db = new PDO('sqlite:' . __DIR__ . '/forms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch forms
$forms = $db->query("SELECT id, title, created_at FROM forms ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manager Dashboard - SmartCardAI</title>
<style>
/* simple styling */
body { font-family: 'Segoe UI', sans-serif; background:#012e00; padding:30px; }
h1 { color:#c7e7c5; }
table { width:100%; border-collapse:collapse; margin-top:18px; background:#ecfbffcf; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
th,td { padding:12px; border-bottom:1px solid #eee; text-align:left; }
th { background:#197c28e6; color:#fff; }
button { background:#0c1b0b; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }
table { width:100%; border-collapse:collapse; margin-top:18px; background:#ecfbffcf; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
table { width:100%; border-collapse:collapse; margin-top:18px; background:#ecfbffcf; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
.query-box { margin-top:28px; background:#ecfbffcf; padding:18px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.06); }
textarea { width:90%; height:35px; padding:10px; border-radius:6px; border:1px solid #ccc; font-size:14px; }
.select-inline{display:inline-block;margin-right:10px;}
.response-box { margin-top:14px; padding:12px; background:#f9fafc; border-left:4px solid #1a237e; white-space:pre-wrap; }
header img {
    height: 50px;  /* adjust as needed */
    margin-right:20px;
}
</style>
</head>
<body>
<header>
    <h1>Manager Dashboard</h1>

<p style="color:#ffffff;>List of saved forms — click <strong>View Submissions</strong> to inspect them.</p></header>
<div class="container">

    <!-- Return to Form Generator Button -->
    <div class="nav-button">
        <a href="index.php"><button type="button">Return to Form Generator</button></a>
    </div>
<table>
  <tr><th>Form Title</th><th>Created At</th><th>Actions</th></tr>
  <?php foreach($forms as $f): ?>
    <tr>
      <td><?= htmlspecialchars($f['title']) ?></td>
      <td><?= htmlspecialchars($f['created_at']) ?></td>
      <td>
        <a href="view_submissions.php?form_id=<?= $f['id'] ?>"><button>View Submissions</button></a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="query-box">
  <h2>Ask Gemini about submissions</h2>
  <div>
    <label class="select-inline">Context form:
      <select id="formSelect">
        <option value="">— Use all forms —</option>
        <?php foreach($forms as $f): ?>
          <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button onclick="prefillExample()">Example Q</button>
  </div>

  <p style="margin-top:10px">Type a question about submissions (e.g. "Show average age" or "List entries with Blood Group O+")</p>
  <textarea id="query" placeholder="Type your question here..."></textarea>
  <br>
  <button style="margin-top:10px" onclick="askGemini()">Ask Gemini</button>

  <div id="responseBox" class="response-box" style="display:none"></div>
</div>

<script>
async function askGemini(){
  const query = document.getElementById('query').value.trim();
  if(!query) return alert('Please enter a question.');
  const formId = document.getElementById('formSelect').value || null;

  const box = document.getElementById('responseBox');
  box.style.display='block';
  box.innerText = 'Processing...';
  
try {
    const res = await fetch('query_gemini.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ query, form_id: formId })
    });
    const data = await res.json();
    if(data.error) box.innerText = 'Error: ' + data.error;
    else box.innerText = data.answer || '[no answer returned]';
  } catch (e) {
    box.innerText = 'Request failed: ' + e.message;
  }
}


function prefillExample(){
  document.getElementById('query').value = 'Show me the average age from the selected form.';
}
</script>

</body>
</html>
