<?php
require_once __DIR__ . '/../includes/admin_layout.php';
admin_page_start('Gallery', 'gallery');
$ready = db_ready();
if ($ready) {
    ensure_gallery_category_schema();
}
$gallery = $ready ? db()->query("SELECT * FROM gallery_images ORDER BY display_order ASC, id DESC")->fetchAll() : [];
$categories = $ready ? db()->query("SELECT * FROM gallery_categories WHERE is_active=1 ORDER BY display_order ASC, category_name ASC")->fetchAll() : [];
?>
<div class="top">
  <div><h1>Gallery</h1><p>Upload photos, choose a category, and control the public gallery order.</p></div>
  <div class="top-actions"><a class="btn gold" href="#add-gallery">Add Image</a></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>

<section class="card upload-card" id="add-gallery">
    <div class="section-head"><div><h2>Upload Image</h2><p>Add one photo at a time. Choose where it should appear in the public gallery.</p></div></div>
    <form class="form-grid gallery-form upload-form" method="POST" enctype="multipart/form-data" action="actions.php">
      <input type="hidden" name="action" value="add_gallery"><input type="hidden" name="back" value="gallery.php">
      <label>Image title<input name="title" required placeholder="Evening celebration"></label>
      <div class="category-select-row">
        <label>Category<select name="category"><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?php echo h($category['category_name']); ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
        <button class="icon-add" type="button" id="showCategoryInput" aria-label="Add category">+</button>
      </div>
      <label class="new-category-field" id="newCategoryField" style="display:none">New category<input name="new_category" placeholder="Wedding"></label>
      <label>Sequence<input type="number" name="display_order" value="10"></label>
      <label>Image file<input type="file" name="gallery_image" accept="image/*" required></label>
      <button class="btn gold">Upload Image</button>
    </form>
</section>

<section class="card">
  <div class="section-head"><div><h2>Gallery Images</h2><p>These images appear on the public gallery page in sequence order.</p></div></div>
  <div class="gallery-grid-admin">
    <?php foreach ($gallery as $image): ?>
      <article class="gallery-item">
        <img src="../<?php echo h($image['image_path']); ?>" alt="">
        <div class="gallery-item-body">
          <div>
            <strong><?php echo h($image['title']); ?></strong>
            <span><?php echo h($image['category']); ?> - Sequence <?php echo h($image['display_order']); ?></span>
          </div>
          <form method="POST" action="actions.php" onsubmit="return confirm('Delete this gallery image?');">
            <input type="hidden" name="action" value="delete_gallery">
            <input type="hidden" name="back" value="gallery.php">
            <input type="hidden" name="image_id" value="<?php echo (int) $image['id']; ?>">
            <button class="btn red">Delete</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$gallery): ?><div class="empty-state">No images uploaded yet. Use Upload image to add the first one.</div><?php endif; ?>
  </div>
</section>
<script>
  const categoryButton = document.getElementById('showCategoryInput');
  const categoryField = document.getElementById('newCategoryField');
  if (categoryButton && categoryField) {
    categoryButton.addEventListener('click', () => {
      categoryField.style.display = 'block';
      const input = categoryField.querySelector('input');
      if (input) input.focus();
    });
  }
</script>
<?php admin_page_end(); ?>
