# Sea Gull V38 — Flexible Delivery & Live Alerts

- Manual delivery override added while preserving automatic distance-based quote at 10 EGP/km.
- Delivery override is audit-logged and forces price/customer reconfirmation when changed.
- Customer tracking edit form no longer disappears during realtime polling.
- Added reliable admin event polling from audit log for customer edits, confirmations, cancellations, price approval and receipts.
- Customer cancellation and confirmation now trigger in-page alarm/toast and browser notification when permission is enabled.
- Customer receipt/WhatsApp action opens WhatsApp directly on the customer's saved number and includes the full receipt details in the message; image sharing remains available where the browser supports file sharing.
- New orders continue to trigger automatic browser print attempts using the prepared print window.
- PHP and JavaScript syntax validated.
