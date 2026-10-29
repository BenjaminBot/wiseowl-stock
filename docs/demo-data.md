# Development demo data

Run explicitly after creating a manager account:

```sh
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

This seeder is separate from the ordinary `DatabaseSeeder`; normal migrations/seeding do not automatically populate demo stock. It refuses environments other than local/testing and uses an existing manager as the receiving actor. It creates no users and changes no credentials or permissions.

## Contents on a fresh run

- 12 fictional book catalogue entries and 3 other products (two toys and a reading journal), with `DEMO-` SKUs.
- 6 categories, 2 suppliers, 4 locations, and website/shop/named-bookfair sales destinations, all labelled Demo.
- Two illustrative tax categories explicitly labelled Demo only. These are arbitrary test fixtures, not configured legal tax treatment.
- Two populated delivery sessions and one empty `DEMO-NEW-INTAKE` session for hands-on receiving.
- 17 audited receipt batches, totalling 136 physical units across 62 allocation/location buckets.
- One product represented in new, almost-new, and used conditions at different batch prices.

The ISBN scanner sample uses `9780140328721` only as a test identifier; its demo title is not external catalogue metadata. If that ISBN already belongs to a user-created product, the demo entry has no ISBN so it cannot overwrite or take over the existing book.

## Repeatability

Names/SKUs and deterministic UUID receipt keys identify fixtures. Existing records are reused without repricing or overwriting staff edits. Already posted demo receipts are skipped, so running the seeder again does not replenish stock. Receipt posting uses `ReceiveStock`, preserving allocation checks, cost/price snapshots, and ledger history.

The entire seed run is transactional. Do not delete posted history to clear demos; a later reset/reversal workflow must preserve the stock invariants. Until then, use the labelled fixtures for local evaluation only.

## Try it

Refresh Products, then open Stock Overview to compare batch conditions, prices, physical locations, and destination allocations. Receipts from `DEMO-DELIVERY-001` remain physically in Demo: Stockroom; toys/gifts from the second delivery are in Demo: Shop. Intended website shelves/fair boxes do not imply physical transfers.

Select `DEMO-NEW-INTAKE` on Receive Delivery to test a fresh intake without the duplicate-product warning from the populated sessions. You can also receive additional copies into a populated session by explicitly confirming additional quantity.

## Verification

`DemoDataSeederTest` verifies reseeding does not duplicate stock or change account passwords, preserves edited titles, avoids real-ISBN collisions, and rejects production before writing. Three tests pass with 17 assertions against the isolated PostgreSQL test database.
