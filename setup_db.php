<?php
$db = new PDO('sqlite:forms.db');

// Create forms table
$db->exec("CREATE TABLE IF NOT EXISTS forms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    manager_id INTEGER,
    title TEXT,
    form_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create submissions table
$db->exec("CREATE TABLE IF NOT EXISTS submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id INTEGER,
    employee_id TEXT,
    submission_json TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo "Database and tables created successfully!";
?>
