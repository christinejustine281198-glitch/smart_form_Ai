<?php
// view_submissions.php
$db = new PDO('sqlite:' . __DIR__ . '/forms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$form_id = $_GET['form_id'] ?? null;
if (!$form_id) die("Form ID missing");

$formStmt = $db->prepare("SELECT * FROM forms WHERE id = ?");
$formStmt->execute([$form_id]);
$form = $formStmt->fetch(PDO::FETCH_ASSOC);
if (!$form) die("Form not found");

$subStmt = $db->prepare("SELECT * FROM submissions WHERE form_id = ? ORDER BY submitted_at DESC");
$subStmt->execute([$form_id]);
$rows = $subStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Submissions for <?= htmlspecialchars($form['title']) ?></title>
<style>
body{font-family:Segoe UI;background:#012e00;padding:24px;}
h1{color:#c7e7c5;}
table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
th,td{padding:12px;border-bottom:1px solid #eee;text-align:left;}
th{background:#197c28e6;color:#fff;}
pre{white-space:pre-wrap;}
</style>
</head>
<body>
  <a href="manager_dashboard.php">⬅ Back</a>
  <h1>Submissions for: <?= htmlspecialchars($form['title']) ?></h1>

  <table>
    <tr><th>ID</th><th>Responses (JSON)</th><th>Submitted At</th></tr>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><pre><?= htmlspecialchars($r['responses']) ?></pre></td>
        <td><?= $r['submitted_at'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
