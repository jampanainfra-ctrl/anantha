# Supplied blog photos

The 15 images supplied on 24 October 2026 are saved as quality-90 JPEG files under `assets/blogs/`. They are assigned by topic to the 21 blog pages through `includes/blog_image_catalog.php`. Non-blog sections retain their existing image assets.

The blog listing uses each page's first active image as its thumbnail. The same first image appears in that blog's hero, with the remaining assigned images in its gallery. Edit titles, alternative text, order and images through **Admin → Blog Images**.

On an existing deployment, copy the new assets and PHP/CSS changes, then run:

```powershell
php tools/import_blog_photos.php
```

This registers the supplied photo assignments and hides the previous bundled blog illustrations. It preserves custom uploads and avoids inserting duplicate assignments when run again. Deleted or hidden supplied photos remain under admin control on subsequent imports.
