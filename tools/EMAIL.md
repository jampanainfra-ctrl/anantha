# Email connection

All application email uses `includes/mail.php` and notification hooks in `includes/notifications.php`. The PHP sending option calls native `mail($to, $subject, $body, $headers)` directly with configured From/To addresses and UTF-8 HTML/plain-text alternatives. PHP delivery does not require Composer. Authenticated SMTP uses PHPMailer; install its dependencies on a new deployment with `composer install --no-dev`.

## Setup

1. Open `/ceoadmin/email/` as an administrator.
2. Select **PHP mail()** to use the hosting server's mail service, or **SMTP** to use an authenticated provider. For SMTP, enter the hostname, port, encryption, username and app password directly in the admin form. Do not use chat or commit credentials to Git.
3. Set **From email** (the approved sender address) and **To email (admin alerts)**. Customer emails go to the email address saved in each booking. Choose customer updates, admin alerts, or both.
4. Save the connection. Sending is paused by default. Review queued messages before enabling delivery: enabling allows pending messages to be sent.
5. Enable delivery, queue a test email and select **Send queued emails**. Check delivery history and the recipient inbox.

Settings are stored in Git-ignored `storage/mail-settings.php`. `storage/.htaccess` denies HTTP access to the entire private storage directory. Keep equivalent denial rules if deploying on a server other than Apache. Credentials are never filled back into the browser or included in delivery errors. A blank password keeps the saved credential.

For tests, `ANANTHA_MAIL_SETTINGS_PATH` can point to a separate private PHP settings file. Never expose this file on the web.

## Events

- Customer booking enquiries, booking detail/status changes and pricing-only updates.
- Payment recording and verification updates. Pending acknowledgements explicitly say they are not verified receipts; only verified payments produce a verified receipt.
- Referral code/activity updates to the member. Admin alone replaces codes. Codes and permanent history remain unchanged.
- Admin alerts for the above, public reviews awaiting moderation, and successful management changes to packages, rates, terms, gallery and blog images, reviews, inspections and penalties.
- Customers with no valid email are skipped; the admin alert remains available. Identity documents, Aadhaar numbers, passwords, internal notes and raw form payloads are not emailed.
- Only new successful actions create mail. Existing data is not backfilled. Failed business actions create no email. Unchanged bookings/payments do not create repeat update messages.

## Delivery and retries

Emails are stored in the `email_outbox` database table. Sending is separate from the booking/payment operation; delivery errors do not undo saved records. Admin pages show queue state, attempts and sanitized failure details. Successful messages are not resent. Failed messages retry with backoff up to five attempts; an admin can explicitly retry a failed message.

Enabled web requests that create notifications dispatch small batches after the response. For unattended delivery and retries, schedule this command every minute using cron or Windows Task Scheduler (run hidden):

```
D:\xampp\php\php.exe D:\xampp\htdocs\anantha\tools\process_email_queue.php 20
```

Do not run the queue through a public HTTP endpoint. The worker accepts CLI only. SMTP uses authenticated TLS for remote providers. Unencrypted SMTP is restricted to a local test server. PHP `mail()` needs the host's existing sendmail or mail relay configuration; From and To addresses do not configure a mail server. On this XAMPP installation PHP currently points to localhost port 25, where no mail relay is running. Use a working host mail service or configure SMTP before enabling live sending. A sent log means the mail service accepted the message, not confirmed inbox arrival.

Delivery is at least once: a process crash after the SMTP server accepts a message but before its status is saved can cause a retry. Message IDs are stable per queued message. Queue claims use database locks to avoid concurrent workers sending the same message while a lease is valid.

## Validation

`D:\xampp\php\php.exe tools/test_email.php` creates and drops an isolated mail test database and private temporary configuration. It exercises paused mode, configuration validation, escaping, deduplication, retry delays, notifications, native MIME formatting, header-injection protection, and missing-customer-email handling. Optional first CLI argument is a localhost SMTP sink port, for actual PHPMailer SMTP and native PHP mail delivery tests. Tests do not send external email.

Official library: https://github.com/PHPMailer/PHPMailer
## Admin password recovery

The login screen links to `/ceoadmin/forgot-password/`. A request for an existing admin account queues a security email to that account's **registered login email**, independently of the general admin alert recipient and notification opt-outs. The public response is the same for existing accounts, unknown accounts and throttled requests.

Reset tokens contain 32 random bytes. Only a SHA-256 hash is stored in `admin_password_resets`; the emailed link expires in 30 minutes and can be used once. A new request invalidates earlier links. Requests are limited to five per account and twenty per IP per hour. Forms use CSRF protection. Password reset revokes existing admin sessions, requires signing in again, and queues a password-change notice. Passwords are never included in email.

Set `ANANTHA_APP_ORIGIN=https://your-domain.example` on deployment. It must be an origin without a path; `ANANTHA_BASE_PATH` controls the application subfolder if needed. The local default is `http://localhost`. Recovery links never use the untrusted request Host header. Recovery pages send no-store and no-referrer headers. The mail worker must use the same origin/base-path environment as the web application.

Delivery still requires an enabled, functioning mail connection. Local sending is currently paused. Register an accessible email address on the admin account before relying on recovery; the mail alert To address does not change the login email.

Run `D:\xampp\php\php.exe tools/test_admin_recovery.php` for isolated token, expiry, request-limit, password-change and session-revocation tests. No external email is sent.
