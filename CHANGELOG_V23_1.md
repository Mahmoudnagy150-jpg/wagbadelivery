# V23.1 — Order Details Recovery

- Fixed `order_items.pricing_mode` schema migration so order creation and tracking cannot silently lose item details.
- Existing orders are backfilled from menu item/category: only fish and shrimp can be weight-based; all other items are quantity-based.
- Customer tracking reads the restored order item rows and displays them.
- No admin URL is exposed in customer tracking links.
