# Package editor

`/ceoadmin/packages/` has one **Save all changes** button. Edit the table, add new rows with **Add package**, and stage removals with **Remove**. **Undo** restores an existing row before saving. All changes, including edited rows hidden by search or filters, are submitted together. Search and filters operate on the already loaded rows, so they do not discard drafts.

Only changed rows are sent. Saving temporarily locks the editor. Failed saves keep the entered values on screen. The page warns before navigating away with pending changes. Required and range errors appear beneath the affected field in red; hidden invalid rows are revealed for correction.

The `save_packages_bulk` admin action requires CSRF verification and a final completeness marker. The marker protects against partial updates caused by PHP input limits. Server validation covers required names, guest ranges, prices, discount bounds, reason length and order. The save uses one transaction and verifies row versions under locks to avoid overwriting changes made in another session. One failure rolls the complete save back.

Packages referenced by bookings cannot be removed through the bulk editor; turn off **Active** instead. Standalone legacy `save_package` and `delete_package` actions remain available for existing integrations, while the new screen uses the single bulk action. No database schema migration is required for this editor.

`D:\xampp\php\php.exe tools/test_package_bulk.php` runs isolated transaction, creation, update, deletion, validation, concurrency, CSRF and incomplete-submission checks. Test mail is paused and the temporary database and private settings are removed afterward.
