# Sea Gull V31 — Production Ready

## Major upgrades
- Added a live Kitchen Display System (KDS) for accepted, preparing, and ready orders.
- KDS refresh can run every 5 seconds and highlights orders older than 20 minutes.
- Added price history for every final-price approval and weight/quantity repricing.
- Price history records old total, new total, actor, reason, and timestamp.
- Strengthened audit trail with actor information for pricing operations.
- Preserved strict workflow: customer price confirmation is required before preparation.
- Preserved automatic invalidation of customer confirmation when weights/prices change.

## Safety
- Existing `orders.sqlite` is preserved.
- No destructive migration is performed.
