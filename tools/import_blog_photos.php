<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/config.php';

// Only retire the old bundled blog illustrations. Custom uploads are preserved.
$legacy = ['occasion-reception', 'gallery-celebration-evening', 'gallery-day-lawn',
    'occasion-engagement', 'occasion-haldi', 'occasion-mehendi', 'occasion-family',
    'occasion-sangeet', 'occasion-birthday', 'room-birthday-celebration',
    'occasion-anniversary', 'occasion-corporate', 'hero-nature-event'];
foreach (default_blog_images() as $images) {
    foreach ($images as $image) {
        if (!is_file(__DIR__ . '/../' . $image[1])) {
            throw new RuntimeException('Missing blog photo: ' . $image[1]);
        }
    }
}
ensure_blog_images_schema();
$pdo = db();
$pdo->beginTransaction();
try {
    $retire = $pdo->prepare('UPDATE blog_images SET is_active=0 WHERE page_slug=? AND image_path=?');
    foreach (array_keys(default_blog_images()) as $slug) {
        foreach ($legacy as $name) {
            foreach (['.png', '-optimized.jpg'] as $extension) {
                $retire->execute([$slug, 'assets/' . $name . $extension]);
            }
        }
    }
    $pdo->commit();
    echo "Blog photos saved for " . count(default_blog_images()) . " blog pages. Custom uploads preserved.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
