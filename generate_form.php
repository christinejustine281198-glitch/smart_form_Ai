<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $nl_query = $_POST['nl_query'];

    // Call Gemini API (replace YOUR_GEMINI_API_KEY)
    $api_key = "YOUR_GEMINI_API_KEY";
    $model = "gemini-2.0-flash";

    $data = [
        "model" => $model,
        "prompt" => "Generate a JSON form with fields based on: $nl_query"
    ];

    $ch = curl_init("https://api.openai.com/v1/responses");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $form_json = $result['output'][0]['content'][0]['text'] ?? '{"fields":[]}';

    // Save form to SQLite
    $stmt = $db->prepare("INSERT INTO forms (title, form_json) VALUES (?, ?)");
    $stmt->execute([$title, $form_json]);
    $form_id = $db->lastInsertId();

    $share_link = "http://localhost/smartcard_form/fill_form.php?id=$form_id";
    $message = "✅ Form saved! Share link: <a href='$share_link'>$share_link</a>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SmartCardAI Form Generator</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Poppins', sans-serif;
    }
    .container {
      max-width: 700px;
      margin-top: 50px;
      background: white;
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
    }
    .logo {
      display: block;
      margin: 0 auto 15px;
      width: 80px;
    }
    h1 {
      text-align: center;
      font-weight: 700;
      color: #1e3a8a;
    }
    h5 {
      text-align: center;
      color: #6c757d;
      margin-bottom: 30px;
    }
    .btn-primary {
      width: 100%;
      background-color: #1e3a8a;
      border: none;
    }
    .btn-primary:hover {
      background-color: #0d2561;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Logo -->
    <img src="logo.png" alt="SmartCardAI Logo" class="logo">

    <!-- Headings -->
    <h1>SmartCardAI Form Generator</h1>
    <h5>Generate dynamic forms using Gemini AI</h5>

    <!-- Form -->
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Form Title</label>
        <input type="text" name="title" class="form-control" placeholder="Enter form title" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Describe your form</label>
        <textarea name="nl_query" class="form-control" rows="5" placeholder="e.g. A feedback form with name, email, rating, and comments" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Generate Form</button>
    </form>

    <?php if (!empty($message)): ?>
      <div class="alert alert-success mt-4">
        <?= $message ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
