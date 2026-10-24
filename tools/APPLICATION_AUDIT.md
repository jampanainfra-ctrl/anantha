# Application audit — 24 October 2026

The local application was reviewed across public pages, admin screens, booking and payment workflows, referrals, packages, gallery management, account recovery, email, routing and access control. This report describes local verification; it does not certify the deployed website.

## Fixes made during this audit

- Cancelling a booking now changes only that booking. The previous code could cancel other bookings on the same date.
- Admin booking submissions validate calendar dates, past-date changes, booking statuses, capacity and occupied dates on the server. Reopening a cancelled booking cannot bypass an existing booking on that date.
- Admin POST forms include CSRF tokens, including dynamically loaded table rows. Mutation endpoints reject missing or invalid tokens, unsupported actions and unsafe return destinations. Login is protected and logout uses POST.
- Public calendar responses no longer expose customer names or event descriptions. Excluding a booking from calendar conflicts requires an authenticated admin.
- Customer identity images are served through an authenticated endpoint. Direct access to the private upload directory and internal application directories is blocked.
- Gallery images have editable titles, categories, display order and visibility. Uploads report failures clearly and validate image format and size.
- Public review responses use the Google review source. Submitted venue feedback remains private.
- PIN lookup handles unavailable network services and malformed input without breaking the form.
- Admin output buffering allows redirects without headers-already-sent errors.

## Verification results

| Automated functional coverage | Passed |
| --- | ---: |
| Discounts, payments and pricing | 76 |
| Referrals | 47 |
| Required fields and whitespace | 40 |
| Verified returning customers | 28 |
| Single-save package editor | 24 |
| Admin account recovery | 28 |
| Google review integration logic | 24 |
| Clean routes | 33 |
| Admin management and access control | 37 |
| Email queue and PHP/SMTP transports | 44 |
| **Total** | **381** |

Additional checks passed:

- PHP syntax: 89 application and test files.
- JavaScript syntax: 6 asset files.
- Read-only HTTP checks: 94 checks covering 30 public pages, 34 local assets, protected admin/API routes and private paths.
- Browser layout checks: 44 admin page/viewport combinations and 16 public page/viewport combinations, with no horizontal page overflow or detected JavaScript exceptions in the checked public screens.
- CSRF token coverage: 102 rendered admin POST forms and three dynamically rendered management tables.
- Browser interaction checks for package editing, gallery category selection and metadata forms, inline validation and the returning-customer flow.

The database-backed regression tests use isolated temporary test databases. Mail transport tests use a local sink and do not send external email. Browser checks use an authenticated local test session.

## Run the checks again

From the project root in PowerShell:

```powershell
.\tools\run_checks.ps1
```

Apache and the local database must be available. Use `-SkipHttp` to omit the read-only Apache checks. PHP and Node binary paths can be supplied with `-PhpBinary` and `-NodeBinary`.

## Live verification still required

- Deploy the local changes before testing production. A read-only live smoke check returned successful responses for the home, availability, gallery, rules, review and admin-login pages, but the live package-editor script was missing. The deployed site therefore does not match this checkout.
- Email delivery to a real inbox remains unverified. Local email sending is disabled and the local PHP configuration has no working mail relay. Configure and enable the hosting mail service, then check customer notifications, admin alerts and password resets in the live email logs and receiving inboxes.
- A Google Places API key has not been configured. The admin connection controls and integration logic are tested; fetching actual Google reviews requires that key and a live provider check.
- Actual browser-to-server upload success and production upload limits were not exhaustively tested. Image validation failures, optional uploads, gallery metadata updates and private document access were checked.
- The browser matrix is a representative sample, not a guarantee for every device, browser or possible dataset. Review production workflows after deployment using a controlled test booking.
