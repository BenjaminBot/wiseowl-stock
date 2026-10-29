# Delivery roadmap

| Phase | Deliverables | Exit criteria | Current status |
| --- | --- | --- | --- |
| 1. Specification and mobile proof | Markdown workflows, screen states, data model, invariants, conventions, camera experiment | Walkthrough with owner/Reb and actual iPhone scanning evidence | Documents and isolated proof prepared; walkthrough and device validation pending |
| 2. Foundation | Compatible version selection, Laravel/Filament scaffold, Docker, auth/policies, migrations, catalogue/configuration | Repeatable setup and verified transactional stock foundation | Implemented with manual receipt slice; PostgreSQL tests passing |
| 3. Intake | Session defaults, camera/manual input, lookups/covers, price confirmation, splits, conditions, duplicates, safe retries | Real delivery received end-to-end on phone | Camera/photo/manual ISBN selection and online metadata/cover selection implemented; full duplicate workflow, corrections and actual device validation pending |
| 4. Operations | Manual sales, reservations, transfers, reallocation, returns/damage, opening counts/reconciliation, external update tasks | Stock maintained accurately after intake | Not started |
| 5. Pilot | Representative stock, concurrent users, recovery, backup/restore, speed/usability fixes | Day-to-day operation can be trusted | Not started |
| 6. Integrations | Website APIs and stock/order synchronisation; later POS evaluation | External sales use shared stock rules reliably | Deferred |

## Additional planned phase: expected shipments and receipt confirmation

See [the workflow](workflows/expected-shipments.md). Manually entered or CSV-imported invoice lines provide a Confirm receipt entry point to the same receiving process as scanning. Support partial/multiple arrivals, wholly missing lines, unexpected/different items, and a final invoice-versus-received discrepancy report. Normal intake without an invoice remains available. Implementation sequencing is to be decided; this is not yet part of the running application.

Phases 1-5 form the operational MVP. Phase 3 alone is an intake prototype, not a complete stock system. Do not mark Phase 1 complete until the real-device and workflow walkthrough evidence exists.

## Immediate implementation sequence

1. Review documented flows with representative scenarios, especially duplicate scans and batch pricing.
2. Run the camera proof over trusted HTTPS on actual iPhones and record results.
3. Resolve financial basis/precision and compatible framework/database versions.
4. Scaffold using official generators and Docker; preserve the root documentation and agent instructions.
5. Implement one tested vertical slice: session -> receipt -> ledger/balances -> stock view.
6. Add metadata/cover enrichment and additional operations incrementally.

Do not expand into NAS/cloud deployment, offline sync, storefront replacement, or building a fiscal POS during this sequence.

## Catalogue workspace follow-up

The provider settings, author/description enrichment and immediate apply-action feedback are implemented. A larger redesign of the catalogue-details workspace is requested for a later iteration: clearer separation of provider suggestions, cover choices and editable catalogue fields, with less clutter on phones.

## Future cover importing (2026-10-29)

Retain direct image URL importing (paste, preview and explicitly select) and broader image discovery from Google Images/Amazon search results as future options to investigate. Existing search links only open external searches; they do not import images. Any future search-page extraction or provider adapter needs its own reliability, access and image provenance review before implementation. This note records a future consideration, not an implemented feature or an approved scraping design.

## Supplier and publisher catalogue enrichment (2026-10-29)

Requested future phase: automatically obtain catalogue information, descriptions/blurbs and cover references from supplier or publisher files/feeds and match it to the exact ISBN/edition. This may complement invoice import but must not itself receive physical stock. Reuse central provider capability modes, canonical language/binding matching, caching, provenance and explicit metadata apply/save. Missing supplier fields must not erase staff information. Source-specific CSV/ONIX/API support, feed access and precedence/conflict presentation need confirmation from actual supplier examples; no supplier adapter is implemented yet.
