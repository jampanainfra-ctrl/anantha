# Clean URLs

Apache must enable `mod_rewrite` and allow this application's `.htaccess`. XAMPP is already configured for both.

| Page | Local URL |
| --- | --- |
| Home | `http://localhost/anantha/` |
| Gallery | `http://localhost/anantha/galary/` |
| Availability | `http://localhost/anantha/availability/` |
| Venue rules | `http://localhost/anantha/rules/` |
| Admin login | `http://localhost/anantha/ceoadmin/` |
| Admin dashboard | `http://localhost/anantha/ceoadmin/dashboard/` |
| Bookings | `http://localhost/anantha/ceoadmin/bookings/` |
| Booking detail | `http://localhost/anantha/ceoadmin/booking-form/?id=1#payments` |
| Rate Card | `http://localhost/anantha/ceoadmin/rate-card/` |
| Referrals | `http://localhost/anantha/ceoadmin/referrals/` |
| API availability | `http://localhost/anantha/api/availability/` |
| Sitemap | `http://localhost/anantha/sitemap/` |

All other public page and admin slugs use the same trailing-slash convention. `galary` is the requested public spelling; `/gallery/` redirects to it. Existing `.php` URLs redirect permanently (HTTP 308), retaining query strings and POST methods/bodies. Existing `/admin/` links redirect into `/ceoadmin/`. Login and authorization checks continue to run on all protected routes.

PHP files remain the internal implementation. `includes/routes.php` generates browser URLs; templates and inline AJAX use application-root paths so nested routes cannot break assets or form submissions. Fragments and query-only links remain relative to the current clean route. The sitemap and SEO canonical links use clean URLs.

The application base path is detected from the document root, supporting domain-root or subfolder installations. Unusual aliases/symlinks can set `ANANTHA_BASE_PATH` to the URL prefix (an empty string means the domain root). Apache redirect prefixes are discovered from the request path.

Run `D:\xampp\php\php.exe tools/test_routes.php` for URL-helper checks, including root and nested installations. Test scripts run only from the command line.
