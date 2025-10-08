<?php
$db = new PDO('sqlite:' . __DIR__ . '/forms.db');
$forms = $db->query("SELECT * FROM forms ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($forms);
echo "</pre>";
