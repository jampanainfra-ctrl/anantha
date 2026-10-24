# Returning customers

On `/availability/`, a matching contact is offered **Booked with us before? Load your contact details**. The customer enters their mobile number and previous booking reference, then selects **Load my details**. The combination is checked against the database before personal details are returned. A phone-only public lookup never returns those details.

The lookup fills the latest saved name, WhatsApp number, email, PIN code, area, city/district and state for that verified mobile. Fields remain editable. The event date, occasion, guests, package, notes, terms acceptance, quote, payments and identity documents are not copied. A saved address is not overwritten by a fresh PIN lookup.

Submitting creates a new enquiry with a unique booking reference. Its `returning_customer_booking_id` points to the previous booking; the earlier row is unchanged. The admin booking editor shows **Returning customer → View previous booking** and can edit each enquiry independently. The schema addition is applied by `ensure_booking_enquiry_schema()` and is also included in `database.sql` for fresh installations.

Verification is session-bound, expires after 30 minutes and is consumed after a successful enquiry. Both lookup and returning-customer submission check a CSRF token. Successful lookup regenerates the PHP session ID. The API is POST-only and sends private, no-store headers. Public lookups are limited to five attempts per mobile and twenty per IP within 15 minutes; only hashed rate-limit keys are stored. Failed matching returns a generic explanation and no contact details.

Contacts belonging to a different primary mobile remain blocked. Returning customers cannot claim another new-member referral. Original referral usage is retained; editing an unchanged historic referral remains possible. Lifetime referral bonus limits are shared across bookings with the same primary mobile, so another event does not reset them. Dates are rechecked under the existing transaction lock, so repeated submissions for a reserved date cannot create another enquiry.

Changing the mobile clears unedited autofilled details and ignores any late lookup response. Manually edited fields are preserved. If the customer has lost the previous reference, they can contact the venue to recover it rather than retrieving private details with only a phone number.

Run `D:\xampp\php\php.exe tools/test_returning_customers.php` for isolated verification, privacy, second-record creation, original-row preservation, duplicate submission, admin editing, referral and throttling checks. Tests pause external mail and remove their temporary database and session.
