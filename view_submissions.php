<?php
// view_submissions.php

// Connect to SQLite database
try {
require_once 'db.php';
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Get form_id from query parameter
$form_id = $_GET['form_id'] ?? null;

if (!$form_id || !is_numeric($form_id)) {
    die("Invalid or missing Form ID.");
}

// Fetch the form details
$formStmt = $db->prepare("SELECT * FROM forms WHERE id = ?");
$formStmt->execute([$form_id]);
$form = $formStmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Form not found.");
}

// Fetch submissions for this form
$subStmt = $db->prepare("SELECT * FROM submissions WHERE form_id = ? ORDER BY submitted_at DESC");
$subStmt->execute([$form_id]);
$submissions = $subStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Submissions for <?= htmlspecialchars($form['title']) ?></title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #012e00;
    color: #c7e7c5;
    padding: 30px;
  }
  a.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #a0d468;
    text-decoration: none;
    font-weight: bold;
  }
  a.back-link:hover {
    text-decoration: underline;
  }
  h1 {
    margin-bottom: 24px;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    background: #ecfbffcf;
    color: #012e00;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  }
  th, td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
    text-align: left;
  }
  th {
    background: #197c28e6;
    color: white;
  }
  pre {
    white-space: pre-wrap;
    font-family: Consolas, monospace;
    margin: 0;
    max-height: 250px;
    overflow: auto;
  }
</style>
</head>
<body>

<a href="manager_dashboard.php" class="back-link">⬅ Back to Manager Dashboard</a>

<h1>Submissions for: <?= htmlspecialchars($form['title']) ?></h1>

<?php if (empty($submissions)): ?>
  <p>No submissions found for this form.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Responses (JSON)</th>
        <th>Submitted At</th>
      </tr>
    <?php if (empty($submissions)): ?>
  <p>No submissions found for this form.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Responses (JSON)</th>
        <th>Submitted At</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submissions as $sub): ?>
        <tr>
          <td><?= htmlspecialchars($sub['id']) ?></td>
          <td><pre><?= htmlspecialchars($sub['responses']) ?></pre></td>
          <td><?= htmlspecialchars($sub['submitted_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
