# Sea Gull V39 — Final Operations Upgrade

- Automatic new-order print trigger retained and strengthened with a persistent print window opened from the login gesture; local print de-duplication prevents repeat printing after refresh.
- Removed the Notifications button. Live alerts start automatically after admin login/session restoration; browser notifications are requested automatically when permitted, plus in-page alarm/title/toast fallbacks.
- Added daily order serial number (`daily_serial`) and order day (`order_day`); serial resets to 1 each Cairo calendar day and prints on 80mm receipts.
- Upgraded admin order detail wording and delivery controls.
- Delivery remains automatic by distance at the configured rate, with manager/owner +10 / -10 manual adjustment buttons and a return-to-auto button. Manual changes remain audited and force customer price reconfirmation.
- Customer order editing now supports adding completely new menu items, not only changing existing items.
- Customer cancellation page now shows a professional re-order message after cancellation.
- Receipt image sharing now prepares the real 80mm-style receipt image and opens the customer's WhatsApp chat before invoking native file sharing where supported. Web browsers cannot programmatically attach a file to a specific WhatsApp chat without WhatsApp Business/API integration; native sharing remains the secure browser-supported path.
- Added customer-location QR code to the printable 80mm receipt (via QuickChart QR image URL) so delivery staff can scan and open the location.
- Preserved all existing roles, workflow, audit log, customer tokens, branch logic, pricing, KDS, delivery distance calculation, and database contents.
