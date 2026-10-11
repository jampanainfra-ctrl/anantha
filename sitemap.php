<?php
declare(strict_types=1);
header('Content-Type: application/xml; charset=UTF-8');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^a-z0-9.:-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');
$baseUrl = $scheme . '://' . $host . $basePath . '/';
$pages = ['index.php','venue.php','gallery.php','rules.php','availability.php','review.php','wedding-venue-visakhapatnam.php','event-lawns-visakhapatnam.php','function-hall-madhurawada.php','engagement-venue-visakhapatnam.php','birthday-party-venue-visakhapatnam.php','haldi-mehendi-venue-visakhapatnam.php','reception-venue-visakhapatnam.php','outdoor-event-venue-visakhapatnam.php','party-venue-madhurawada.php','event-venue-kommadi.php','wedding-lawns-madhurawada.php','venues-near-rushikonda.php','venues-near-madhurawada.php','300-guest-event-venue.php','private-event-venue-visakhapatnam.php'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
  <url><loc><?php echo htmlspecialchars($baseUrl . $page, ENT_XML1, 'UTF-8'); ?></loc></url>
<?php endforeach; ?>
</urlset>
