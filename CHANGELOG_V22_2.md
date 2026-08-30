# V22.2

- Fixed order-review classification: only fish/shrimp are weight-based.
- Soup with shrimp, salads, sides, drinks, etc. are quantity-based even when their names contain seafood words.
- Admin no longer infers weight from a legacy weight field. It uses the stored pricing_mode strictly.
- Quantity items hide weight fields entirely in order review, WhatsApp final-price message, and receipt.
- Weight items retain 150g minimum and 50g increments.
- Checkout branch remains one normal customer field inside the same checkout form; no separate branch step.
