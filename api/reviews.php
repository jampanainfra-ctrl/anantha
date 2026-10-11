<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(strip_tags($_POST['client_name'] ?? ''));
    $text = trim(strip_tags($_POST['review_text'] ?? ''));
    $event = trim(strip_tags($_POST['event_type'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    if ($name === '' || $text === '') {
        json_response(['ok' => false, 'error' => 'Name and review are required.'], 422);
    }
    $stmt = db()->prepare("INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, 'pending', 'public')");
    $stmt->execute([$name, $event, $rating, $text]);
    json_response(['ok' => true, 'message' => 'Review submitted for approval.'], 201);
}

$reviews = db()->query("SELECT id, client_name, event_type, rating, review_text, created_at FROM reviews WHERE status='published' ORDER BY created_at DESC")->fetchAll();
json_response(['ok' => true, 'reviews' => $reviews]);
