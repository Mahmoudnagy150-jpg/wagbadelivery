Sea Gull Ultimate OWNER PRO V23.3 — FINAL QA

Critical fix:
- Restored customer-page initialization so categories and menu items render immediately on page load.
- The page now renders the embedded menu first, then refreshes public settings/menu from the server.
- Added a guarded bootstrap so a public API/settings failure cannot prevent the menu from appearing.

Pricing rules retained:
- Fish: weight-priced; minimum 150g; customer chooses count and approximate weight per fish.
- Shrimp: weight-priced; minimum 150g; customer chooses total approximate weight only.
- Everything else: quantity-priced; no weight field and no 150g minimum.

Checkout/customer confirmation retained from V23.x.
