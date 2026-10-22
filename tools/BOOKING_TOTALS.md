# Booking total and Rate Card charges

In **Admin → Booking → Payments**, enter the venue amount before discount. The preview calculates:

1. Venue amount minus the selected percentage/fixed discount.
2. Cleaning: greater of guest count × cleaning-per-guest rate and the minimum cleaning charge.
3. Refundable security deposit in full.
4. Electricity: (ending meter reading − starting meter reading) × electricity-per-unit rate. Units are calculated automatically.
5. Any selected optional monetary Rate Card items × their quantities.

The grand total includes the refundable deposit. Verified payments reduce the remaining balance; pending/rejected payments do not settle the booking. Discounts apply to the venue amount, while all charges/deposit are added afterward.

**Save Total & Charges Only** saves pricing without recording a payment. **Save Payment** saves pricing and one instalment together. A missing guest count must be completed in Booking Information before applying automatic charges.

The advance percentage produces a suggested part-payment: that percentage of services plus the complete refundable deposit. It is not an additional charge. Capacity, hold durations and referral settings are policies rather than billable items. Optional extras are monetary Rate Card entries; referral bonus settings are excluded.

`event_bookings.rate_card_snapshot` stores the applicable rate catalog, guest count, starting/ending meter readings, calculated electricity units, selected extra keys, labels, quantities, unit rates, calculated line amounts and save time. `booking_pricing_history` stores each actual pricing change, including the original venue amount, discount, payment note, complete snapshot, totals, timestamp and admin identity. Unchanged subsequent payments do not duplicate pricing versions.

Existing bookings keep their previously saved totals until admin reviews and saves Rate Card charges. Once saved, rates are frozen for that booking. Changing the guest count in Information does not silently change its bill; saving pricing recalculates quantities with its saved rates. **Use latest Rate Card for this save** explicitly replaces those rates. A deleted optional rate remains available in an old snapshot; refreshing requires deselecting any removed rate.

Payment status, the booking grid, balance summaries and after-event due amounts use the same saved grand total. Penalties remain in After Event Inspection, added once to the outstanding balance. The security-deposit refund is settled according to inspection and venue terms.

Checks: `D:\xampp\php\php.exe tools/test_discounts.php`. Tests run in a uniquely named temporary database and clean it up afterward.

Enter both meter readings when available. Both may remain blank before meter readings are recorded. Ending must be at least the starting reading; up to two decimal places are supported. Historic manually entered units remain until readings replace them. The server derives usage from the readings and stores both readings in the current snapshot and pricing history.
