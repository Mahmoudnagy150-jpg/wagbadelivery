# Sea Gull V34 — Secure Customer Actions

- Added a private, unguessable customer access token. Only SHA-256 hash is stored in the database.
- New orders return a private tracking token; customer tracking links use the token instead of exposing the phone number.
- Customer can edit an order only while it is `new` or `accepted`; edits rebuild the order from server-side menu prices and force price review + customer confirmation again.
- Customer can cancel a brand-new unconfirmed order directly with a required reason.
- Customer cancellation after price confirmation becomes a manager/owner approval request instead of silently cancelling.
- Added cancellation request fields and audit logging.
- Added owner/manager/branch approval action for customer cancellation requests.
- Added customer edit count to order records.
- Customer tracking now displays pending cancellation requests.
- Staff order screen shows customer cancellation requests and provides approval to authorized roles.
- Final WhatsApp message no longer includes an insecure order-number + phone tracking URL; the customer is instructed to use the private tracking link already issued.
