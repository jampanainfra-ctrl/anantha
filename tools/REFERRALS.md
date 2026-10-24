# Referral administration

Open `/ceoadmin/referrals/` after signing in, or choose **Referrals & Logs** in the sidebar.

- Set **Uses allowed per code**. The default is 5. Tick **Apply usage limit to current codes too** to change current limits; otherwise the limit applies to new/replacement codes.
- Set the validity period in days; 0 means no expiry. Existing expiry dates do not change. An admin-issued replacement starts a fresh validity period.
- Use **Replace code** beside a member to permanently retire the current code and issue a new one. Codes never rotate automatically, even when exhausted or expired.
- Follow **View member logs** for claims, exhaustion, issuance, replacement, bonus changes, removal, cancellations and imported historic referrals. Search and pagination apply to codes and logs.
- Booking Details → Referrals shows the member's current referral code, usage and history link. Booking references never change when referral codes are replaced.

Each new booking receives a separate `ANR-...` referral code. Existing booking-based referral codes remain usable until expiry, exhaustion or manual replacement. Legacy claims and their original dates are imported into the ledger and logs.

A referred booking consumes one use, including an enquiry; repeated saves do not consume more uses or create duplicate logs. One booking can claim one referral only. Removing/cancelling a referral clears bonuses without returning the consumed use; a removed referral cannot be claimed again. Historical referrals remain editable even after their code is retired.

New claims reject self-referrals, matching contact details, cancelled referrers, invalid codes and retired/expired/exhausted codes. Phone lookup returns the current code. New booking/referral codes are cryptographically generated, reserved in a permanent database registry, and protected by unique constraints. Concurrent claims lock and check the code inside the booking transaction so a final slot cannot be used twice. Registry entries and old code records are never deleted by application actions.

Bonus amounts, cumulative bonus cap and lifetime paid-referral limit remain separate controls in **Rate Card**. Issuing a replacement does not reset the lifetime bonus limits. Admin can explicitly enter a zero bonus. Referral changes through Rate Card are logged too.

The additive schema setup runs automatically and is included in `database.sql`. It does not change existing booking references or remove client records. Public pages never expose admin referral logs.

Shared form enhancements supply “Please enter …” / “Please select …” prompts and red required markers, including AJAX-added admin controls. Native date/file inputs retain their browser controls.

Validation: `D:\xampp\php\php.exe tools/test_referrals.php`. The suite creates a fresh `anantha_referral_test_*` database and drops only that database at completion. It tests migration, manual replacement, concurrency, self/duplicate claims, lifetime caps, rollback and the actual public/admin save handlers; it does not add test bookings to the live database.
