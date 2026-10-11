<?php
require_once __DIR__ . '/../includes/admin_layout.php';
admin_page_start('Blog Images', 'blog-images');
$ready = db_ready();
$pages = seo_blog_pages();
$selectedPage = trim($_GET['page'] ?? 'reception-venue-visakhapatnam');
if (!array_key_exists($selectedPage, $pages)) {
    $selectedPage = 'reception-venue-visakhapatnam';
}

$images = [];
if ($ready) {
    ensure_blog_images_schema();
    $stmt = db()->prepare('SELECT * FROM blog_images WHERE page_slug=? AND is_active=1 ORDER BY display_order ASC, id ASC');
    $stmt->execute([$selectedPage]);
    $images = $stmt->fetchAll();
}
?>
<div class="top">
  <div><h1>Blog Images</h1><p>Add and remove images for each SEO/blog page. Reception images are managed here too.</p></div>
  <div class="top-actions"><a class="btn gold" href="#add-blog-image">Add Blog Image</a></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>

<section class="card">
  <div class="section-head"><div><h2>Select Blog</h2><p>Choose which blog page images you want to manage.</p></div></div>
  <form class="form-grid gallery-form" method="GET" action="blog-images.php">
    <label class="wide">Blog page
      <select name="page" onchange="this.form.submit()">
        <?php foreach ($pages as $slug => $label): ?>
          <option value="<?php echo h($slug); ?>" <?php echo $slug === $selectedPage ? 'selected' : ''; ?>><?php echo h($label); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn gold">Load Blog</button></noscript>
  </form>
</section>

<section class="card upload-card" id="add-blog-image">
  <div class="section-head"><div><h2>Add Image</h2><p>Upload an image for <?php echo h($pages[$selectedPage]); ?>.</p></div></div>
  <form class="form-grid gallery-form upload-form" method="POST" enctype="multipart/form-data" action="actions.php">
    <input type="hidden" name="action" value="add_blog_image">
    <input type="hidden" name="back" value="blog-images.php?page=<?php echo h($selectedPage); ?>">
    <input type="hidden" name="page_slug" value="<?php echo h($selectedPage); ?>">
    <label>Image title<input name="title" required placeholder="Reception stage setup"></label>
    <label class="wide">Alt text<input name="alt_text" placeholder="Outdoor reception stage and dinner setup at Anantha"></label>
    <label>Sequence<input type="number" name="display_order" value="10"></label>
    <label>Image file<input type="file" name="blog_image" accept="image/*" required></label>
    <button class="btn gold">Upload Image</button>
  </form>
</section>

<section class="card">
  <div class="section-head">
    <div><h2><?php echo h($pages[$selectedPage]); ?> Images</h2><p>These images appear in the blog gallery section. The first image is also used as the hero image.</p></div>
    <a class="btn" href="../<?php echo h($selectedPage); ?>.php" target="_blank">View Page</a>
  </div>
  <div class="gallery-grid-admin">
    <?php foreach ($images as $image): ?>
      <article class="gallery-item">
	        <img src="../<?php echo h($image['image_path']); ?>" alt="">
	        <div class="gallery-item-body">
	          <form class="mini-form" method="POST" action="actions.php">
	            <input type="hidden" name="action" value="save_blog_image">
	            <input type="hidden" name="back" value="blog-images.php?page=<?php echo h($selectedPage); ?>">
	            <input type="hidden" name="image_id" value="<?php echo (int) $image['id']; ?>">
	            <label>Title<input name="title" required value="<?php echo h($image['title']); ?>"></label>
	            <label>Sequence<input type="number" name="display_order" value="<?php echo h((string) $image['display_order']); ?>"></label>
	            <label class="full">Alt text<input name="alt_text" value="<?php echo h($image['alt_text']); ?>"></label>
	            <button class="btn gold">Update</button>
	          </form>
	          <form method="POST" action="actions.php" onsubmit="return confirm('Remove this image from this blog page?');">
            <input type="hidden" name="action" value="delete_blog_image">
            <input type="hidden" name="back" value="blog-images.php?page=<?php echo h($selectedPage); ?>">
            <input type="hidden" name="image_id" value="<?php echo (int) $image['id']; ?>">
            <button class="btn red">Delete</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$images): ?><div class="empty-state">No active images for this blog page. Use Add Image to upload one.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
