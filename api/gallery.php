<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();

$category = $_GET['category'] ?? '';
if ($category !== '') {
    $stmt = db()->prepare("SELECT id, title, category, image_path, display_order FROM gallery_images WHERE is_active=1 AND category=? ORDER BY display_order ASC, id DESC");
    $stmt->execute([$category]);
    $images = $stmt->fetchAll();
} else {
    $images = db()->query("SELECT id, title, category, image_path, display_order FROM gallery_images WHERE is_active=1 ORDER BY display_order ASC, id DESC")->fetchAll();
}
json_response(['ok' => true, 'images' => $images]);
