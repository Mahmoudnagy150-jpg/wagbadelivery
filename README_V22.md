# Sea Gull Ultimate OWNER PRO V22 — ULTIMATE UX

Production candidate built from V21.

## Customer flow
- Cart stays clean: no branch selection in the cart.
- Branch is selected inside the single-page checkout together with name, phone, address, location, payment and notes.
- Fish and shrimp use count + approximate weight. Minimum 150g per piece; weight adjusts in 50g steps.
- All other menu items are count-based.
- Approximate weight is displayed as kg with three decimals (0.750 = 750g, 1.500 = 1.5kg).
- Final price is confirmed by the branch after reviewing actual weight.

## Pricing rules
- Fish/shrimp: quantity × weight(kg) × price/kg.
- All other items: quantity × unit price.
- VAT: 14% once.
- Delivery is added by the branch.
- Online discount is applied once.

## Admin
- `admin.html` is the central admin/owner panel.
- `admin.php`, `/admin`, `/dashboard`, and `/owner` are supported entry points.
- Owner controls menu prices and categories; branch staff handle order quantities/actual weights.

## Deployment
Upload the ZIP contents directly into the hosting document root (for example `htdocs`) and extract there. Keep `index.php`, `admin.html`, `api.php`, `.htaccess`, and `menu.json` in the same root folder.
