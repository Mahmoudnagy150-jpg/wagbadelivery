# Sea Gull V37 — Live Ops & Delivery Pro

## Customer → Staff live updates
- Added near-real-time admin polling every 1 second.
- Staff/Owner receive toast + browser notification + alarm for:
  - customer order edits
  - customer price confirmation
  - customer cancellation requests / cancellations
  - final price approval changes
  - payment receipt uploads
  - delivery-fee changes and status changes
- The open order details modal refreshes automatically when its order changes.

## Customer cancellation
- Added an explicit private cancellation-link action in the customer success/tracking flow.
- Customer can cancel from the private token URL while cancellation is allowed.
- Cancellation requests remain protected by the existing role/approval rules.

## Delivery automation
- Delivery fee is now calculated server-side from restaurant branch coordinates to the customer's shared coordinates.
- Default rate: 10 EGP per kilometer.
- Distance is stored on the order.
- Staff can recalculate the automatic delivery quote.
- Staff with pricing permission can still manually increase/decrease the delivery fee before final approval.

## Professional order details
- Added compact status/age/distance/delivery summary cards.
- Added a visual order workflow timeline.
- Kept existing item/weight review, price approval, cancellation approval, WhatsApp, map, print and audit controls.

## Full receipt sharing
- Replaced the broken html2canvas dependency path with an in-browser SVG/canvas receipt generator.
- "إرسال الشيك كامل" now generates a complete receipt image and uses the device share sheet when supported, including WhatsApp as a share target.
- Fallback keeps WhatsApp text sharing available when file sharing is unavailable.

## Compatibility / safety
- Preserved the existing project structure, roles, workflow, customer token security and SQLite database.
- Added delivery fields to the existing SQLite database without deleting existing orders.
