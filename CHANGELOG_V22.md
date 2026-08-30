# V22 Changes

- Minimum fish/shrimp weight: 150g.
- Fish and shrimp now use quantity + approximate weight steppers.
- Weight display uses kg with 3 decimals.
- Weight changes in 50g steps.
- All non-fish/non-shrimp items are quantity based.
- Branch selection removed from the cart and moved into the single-page checkout.
- Backend enforces the same pricing rules to prevent client-side calculation mistakes.
- Order items store pricing mode for reliable admin recalculation.
- Admin weight input enforces 0.150kg minimum and 0.050kg steps.
- Added explicit pricing-mode visibility when managing menu items.
- Synchronized admin.html/admin.php.
