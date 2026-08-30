# Sea Gull Ultimate OWNER PRO V21 — BEST UX

Built on V20 (not an older release).

## Customer UX
- Branch selection is smooth and never labeled as mandatory.
- Branch can be changed inline in the cart and again inside the single checkout screen without leaving the form.
- Checkout keeps branch, name, phone, address/location, payment and notes together in one vertical flow.
- Selected branch is cleared after successful submission so the next customer does not inherit the previous customer's branch on the same device.

## Weight UX
- Admin actual-weight input is in kilograms with 3 decimals.
- `0.750` = 750 g; `1.500` = 1.5 kg.
- Browser input is `step=0.001` and server payload is converted back to grams for the existing calculation/storage layer.
- Weight pricing remains quantity × kilograms × price/kg.

## Important
- Final customer receipt is not shown as a final approved receipt at initial order creation; the branch reviews weight, recalculates, prints and sends the final price to the customer for confirmation.
