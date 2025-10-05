<?php
require 'db.php';
$forms = $db->query("SELECT * FROM forms ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SmartCardAI | Forms</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6fff8;font-family:Poppins,sans-serif;}
.container{max-width:950px;margin:50px auto;background:#fff;padding:40px;border-radius:15px;box-shadow:0 6px 12px rgba(0,0,0,0.1);}
.logo{display:block;margin:0 auto 15px;width:80px;}
h1{text-align:center;color:#155724;font-weight:700;}
thead{background:#28a745;color:#fff;}
.btn-success{background:#28a745;border:none;}
.btn-success:hover{background:#218838;}
.btn-outline-success{border-color:#28a745;color:#28a745;}
.btn-outline-success:hover{background:#28a745;color:#fff;}
.submissions-badge{background:#28a745;color:#fff;border-radius:12px;padding:2px 8px;font-size:0.85rem;margin-left:5px;}
</style>
</head>
<body>
<div class="container">
<img src="logo.png" class="logo">
<h1>SmartCardAI Forms</h1>
<div class="table-responsive mt-4">
<table class="table table-striped table-bordered align-middle">
<thead>
<tr><th>ID</th><th>Title</th><th>Created At</th><th>Submissions</th><th>Share Link</th><th>Actions</th></tr>
</thead>
<tbody>
<?php if($forms): foreach($forms as $f): 
    $shareLink = "http://localhost/smartcard_form/fill_form.php?id=".$f['id'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE form_id=?");
    $stmt->execute([$f['id']]);
    $submissionCount = $stmt->fetchColumn();
?>
<tr>
<td class="text-center"><?= $f['id'] ?></td>
<td><?= htmlspecialchars($f['title']) ?></td>
<td><?= $f['created_at'] ?></td>
<td class="text-center"><span class="submissions-badge"><?= $submissionCount ?></span></td>
<td>
<input type="text" class="form-control form-control-sm" value="<?= $shareLink ?>" id="link<?= $f['id'] ?>" readonly>
<button class="btn btn-sm btn-outline-success" onclick="copyLink('link<?= $f['id'] ?>')">Copy</button>
</td>
<td class="text-center">
<a href="view_submissions.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-success">View Submissions</a>
</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-center text-muted">No forms yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="text-center mt-4"><a href="index.php" class="btn btn-outline-success">+ Create New Form</a></div>
</div>

<script>
function copyLink(inputId) {
    var copyText = document.getElementById(inputId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(()=>alert("Link copied: "+copyText.value));
}
</script>
</body>
</html>

