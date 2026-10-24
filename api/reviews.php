<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_db();
    $spacingError=required_leading_space_error($_POST,['client_name'=>'Name','review_text'=>'Review']);
    if($spacingError!=='')json_response(['ok'=>false,'error'=>$spacingError],422);
    $name = trim(strip_tags($_POST['client_name'] ?? ''));
    $text = trim(strip_tags($_POST['review_text'] ?? ''));
    $event = trim(strip_tags($_POST['event_type'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    if ($name === '' || $text === '') {
        json_response(['ok' => false, 'error' => 'Name and review are required.'], 422);
    }
    $stmt = db()->prepare("INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, 'pending', 'public')");
    $stmt->execute([$name, $event, $rating, $text]);
        notify_public_review((int)db()->lastInsertId(), $name, $rating);
    json_response(['ok' => true, 'message' => 'Your feedback has been sent privately to the venue.'], 201);
}

$google=google_reviews_data();
header('Cache-Control: no-store');
json_response(['ok'=>true,'source'=>'google','reviews'=>$google['reviews'],'rating'=>$google['rating'],'total'=>$google['total'],'url'=>GOOGLE_REVIEW_URL ?: $google['url']]);
