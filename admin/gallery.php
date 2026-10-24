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
    <form class="form-grid gallery-form upload-form" method="POST" enctype="multipart/form-data" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
      <input type="hidden" name="action" value="add_gallery"><input type="hidden" name="back" value="<?php echo h(app_url('gallery.php', 'admin')); ?>">
      <label>Image title<input name="title" required placeholder="Evening celebration"></label>
      <div class="category-select-row">
        <label>Category<select name="category" id="galleryCategory" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?php echo h($category['category_name']); ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
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
        <img src="<?php echo h(app_url((string)($image['image_path']), "")); ?>" alt="">
        <div class="gallery-item-body">
          <div>
            <strong><?php echo h($image['title']); ?></strong>
            <span><?php echo h($image['category']); ?> - <?php echo (int)$image['is_active']===1?'Visible':'Hidden'; ?></span>
          </div>
          <form class="mini-form" method="POST" action="<?php echo h(app_url('actions.php','admin')); ?>">
            <input type="hidden" name="action" value="save_gallery_image"><input type="hidden" name="image_id" value="<?php echo (int)$image['id']; ?>"><input type="hidden" name="back" value="<?php echo h(app_url('gallery.php','admin')); ?>">
            <label>Image title<input name="title" required maxlength="180" value="<?php echo h($image['title']); ?>"></label>
            <label>Category<select name="category" required><?php foreach(array_unique(array_merge([$image['category']],array_column($categories,'category_name'))) as $categoryName): ?><option value="<?php echo h($categoryName); ?>" <?php echo $image['category']===$categoryName?'selected':''; ?>><?php echo h($categoryName); ?></option><?php endforeach; ?></select></label>
            <label>Sequence<input type="number" step="1" name="display_order" value="<?php echo (int)$image['display_order']; ?>" required></label>
            <label class="check-label"><input type="checkbox" name="is_active" <?php echo (int)$image['is_active']===1?'checked':''; ?>> Show in public gallery</label>
            <button class="btn gold" type="submit">Save image details</button>
          </form>
          <form method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>" onsubmit="return confirm('Delete this gallery image?');">
            <input type="hidden" name="action" value="delete_gallery">
            <input type="hidden" name="back" value="<?php echo h(app_url('gallery.php', 'admin')); ?>">
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
      const open=categoryField.style.display==='none';
      categoryField.style.display = open?'block':'none';
      const input = categoryField.querySelector('input');
      document.getElementById('galleryCategory').disabled=open;
      if(input){input.required=open;if(open)input.focus();else input.value='';}
      categoryButton.setAttribute('aria-expanded',String(open));
    });
  }
</script>
<?php admin_page_end(); ?>
