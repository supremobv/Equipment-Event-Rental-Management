# User manual

## First sign-in

Your administrator provides an email and temporary password. Sign in, then replace the temporary password with your personal password. It must contain at least 12 characters, uppercase and lowercase letters, a number and a symbol. Change it later through **Account security** in the sidebar. Password changes revoke other database sessions. Use **Sign out** at the bottom of the sidebar when finished.

Accounts are created by administrators; there is no public registration. An inactive account cannot sign in or continue using an existing session. Contact an administrator for password recovery.

## Make it your business

Administrators open **Business settings** to set the business name, tagline, logo, email, phone, address, currency, default tax and rental terms. Logos accept PNG/JPG/WebP, up to 2 MB and 3000 × 3000 pixels. The sample logo is an original vector marquee mark, with no third-party brand licensing dependency.

Set currency before your first booking. Once bookings exist, currency is locked to prevent relabeling old amounts. There is no exchange conversion. All supported currencies use two-decimal pricing in this MVP. New and edited quotes use the current tax rate; saved bookings keep their rate and price snapshots. Business contact details and terms on printed documents reflect current settings.

## 1. Organize equipment

Open **Equipment**, add categories if needed, then **Add equipment**. Give each type a unique SKU, category, owned quantity, daily rate, replacement value and storage location. Quantities represent a pool of interchangeable units, not individual serial numbers.

Owned quantity includes rented and maintenance units; available stock is calculated separately. Use **Archived** to stop offering a type on new quotes. Re-enable archived equipment before fulfilling an existing reservation. Historical bookings keep item descriptions and prices. Reducing quantity is blocked while any confirmed/out reservation or open maintenance hold references that type. Resolve commitments first. Existing records are preserved instead of permanently deleted.

## 2. Add customers

Open **Customers → Add customer**, enter their name and phone, and optionally company, email, billing address and internal notes. Search by name, email or company. Open **View / edit** to change contact details and see their latest 30 bookings. Customer notes are internal.

## 3. Check availability

Choose **Availability**, set the first and last rental days and click **Check availability**. Both days are included. A one-day rental charges one day; Monday through Tuesday charges two.

Availability subtracts peak confirmed stock usage over the chosen period, every currently checked-out unit and open maintenance holds. Quotes do not reserve equipment. A checked-out rental keeps its stock until an operator completes its return, including when overdue. This conservative rule can reduce advance availability until equipment is physically returned. Maintenance holds have no automatic expiry; a target date is a reminder, not a release instruction.

## 4. Create a quote

Choose **Bookings → New booking**. Select the customer, name the event, choose dates, pickup/delivery and venue. Add equipment lines and quantities; one equipment type can appear only once. Click **Add equipment** for more rows and × to remove a row. The equipment estimate updates immediately; saved totals come from server calculations. Quotes support up to 50 lines and 366 inclusive rental days, starting no more than one year in the past and ending no more than five years ahead.

Enter an optional discount, delivery/service fee and refundable security deposit. Discount cannot exceed the equipment subtotal. Tax applies to equipment less discount plus delivery. Deposit is separate from the rental total and income. Save the quote to review the full calculation.

Only quotes can be edited. Editing refreshes prices from the equipment catalog and tax from settings. For a major change after confirmation, cancel and refund as appropriate, then create a replacement quote. No hidden destructive modifications are performed on confirmed financial records.

## 5. Confirm and prepare

Open the quote and select **Confirm booking**. The server verifies stock and reserves it atomically. If stock is insufficient, the quote remains unchanged and an actionable message is shown. Review dates, quantities, maintenance and checked-out rentals, then edit and retry. Expired rental periods cannot be confirmed.

Use **Print / PDF** to open a printable quotation or invoice. The browser's Print dialog can save it as PDF. The document includes customer details, line items, tax, delivery, deposit, payment ledger and rental conditions. Internal booking notes are excluded. PDF generation is client-side printing; there is no separate PDF server dependency.

## 6. Record payments and deposits

Admins, managers and finance staff can record transactions inside a confirmed booking:

| Type | Purpose | Limit |
| --- | --- | --- |
| Rental payment | Record money received toward charges | Remaining rental balance |
| Security deposit received | Record refundable collateral | Required deposit less held deposit |
| Rental refund | Record rental money already refunded | Net rental payments received |
| Security deposit refund | Record collateral returned after inspection/cancellation | Deposit still held |

Select cash, bank transfer, externally processed card or e-wallet, a date and optional reference. Dates cannot be in the future. These records **do not move money**, contact payment providers or store card details. Make actual transfers with your existing payment process, then record them here. A repeated submission of the same form is idempotent; it will not create a second transaction. Payments are immutable; correct mistakes through a corresponding refund and replacement payment with explanatory notes.

Quotes cannot collect money. Rental/deposit collection is blocked on cancelled bookings. Cancelling waives rental charges but does not automatically record refunds; settle those separately. Deposit refunds require a returned or cancelled booking. Rental refunds can reopen a balance on an active/returned booking; a manager should review the reason. Security deposits are not automatically applied to damage charges: refund and settle the rental balance separately for a clear ledger.

## 7. Check out equipment

On the first rental day, an operator, manager or admin inspects and counts the equipment, confirms the required deposit is held and selects **Check out equipment**. Stock availability is rechecked. Early check-out is blocked. Rental payment can remain outstanding; the required security deposit cannot. Pickup and delivery use the same handover workflow; fulfillment and venue identify how to deliver it.

## 8. Return and inspect

Select the checked-out booking. Count and inspect every unit. Add inspection notes and any agreed late/damage fee, entered as a final tax-inclusive amount, then choose **Complete return**. This returns **the entire booking** and releases its stock. Record a return only after all equipment is accounted for. Partial returns and lost-unit write-offs are outside the MVP.

Return fees increase the balance once; a completed return cannot be repeated. Immediately place damaged/dirty units on a maintenance hold before accepting new reservations for them. Collect outstanding rental charges and refund collateral as appropriate. Do not mark missing units returned simply to close a booking; resolve the discrepancy operationally first.

## 9. Maintain equipment

Open **Maintenance**, choose equipment, quantity, reason, target date and notes. Creating a hold makes those units unavailable. To protect existing promises, a hold must fit unreserved stock; the check conservatively sums all confirmed/out reservations for that equipment, even on different dates. Resolve affected bookings before opening a conflicting hold. When work is finished, select **Complete**; the quantity becomes available again. Jobs are retained as a history.

## 10. Review the business

**Overview** shows active bookings, checked-out rentals, owned units, maintenance quantities and this month's net rental receipts. Active bookings counts confirmed plus out, including future dates. Receipts exclude deposits and subtract rental refunds. The six-month chart shows cash collected, not accrual revenue or profit. Negative months display as zero-height bars with the signed amount in the label.

**Calendar** displays confirmed and checked-out rental date ranges, with month navigation. Overdue out bookings may have their dates in a previous month; use the dashboard overdue link or Checked out tab to follow up.

**Reports & finance** filters the transaction ledger by payment date and exports it to CSV. Outstanding balances and deposits held are all-time totals, intentionally independent of the payment date filter. Exported values are currency units with two decimals. Formula-like text is escaped to reduce spreadsheet formula injection. This is an operational cash report, not a general ledger, profit-and-loss statement or statutory tax filing.

## 11. Team and access

| Capability | Admin | Manager | Operations | Finance | Viewer |
| --- | --- | --- | --- | --- | --- |
| Read operational records, totals and invoices | Yes | Yes | Yes | Yes | Yes |
| Create/edit/confirm/cancel bookings | Yes | Yes | No | No | No |
| Maintain equipment/categories/customers | Yes | Yes | Yes | No | No |
| Check out/return; maintenance holds | Yes | Yes | Yes | No | No |
| Payments/refunds; finance reports/export | Yes | Yes | No | Yes | No |
| Business settings; team; activity log | Yes | No | No | No | No |

Administrators use **Team & access** to add members, choose a role, reset temporary passwords and deactivate access. Saving changes revokes that user's existing sessions. At least one active administrator must remain, and an admin cannot deactivate or demote their own account. All roles can see operational customer records and booking financial totals; use accounts only for trusted staff. There is no customer portal or per-location data isolation.

**Activity log** records successful logins/logouts, staff changes, quotes, state changes, equipment/customer changes, payments, maintenance and settings updates. It records actor, action, reference, IP and time. It is an operational audit trail, not a tamper-proof compliance archive.

## Friendly errors

Validation errors appear at the top of the form and retain non-secret input. Read the message, correct the field and save again. An access error means the role cannot perform the action. An expired session requires reopening the page and possibly signing in again. Always check transaction history before repeating a payment after a connection problem. Contact your administrator if a server error persists; include the page, action and time.
