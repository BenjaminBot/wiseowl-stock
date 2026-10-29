# Scope and decisions

Recorded 2026-10-28. A Dockerised development backend and manual receiving slice now exist; the operational MVP is not complete.

## Confirmed requirements

| Area | Decision |
| --- | --- |
| Purpose | Fast stock intake and stock management for Wise Owl Bookshop |
| Catalogue | Books first; generic products and categories must allow toys and other goods |
| Stack | Dockerised; follow Laravel and Filament conventions |
| Input | Phone camera first, primarily iPhones; consider Android and later hardware scanners |
| Session | Configure supplier, receiving location, intended destinations, and destination locations once |
| Quantities | Enter total quantity and explicit per-destination split for every scan |
| Unallocated | Permit an explicit unallocated remainder |
| Website stock | Physically separated by default, toggleable to shared location |
| Condition | New by default; almost new and used supported, including separate lines of one ISBN |
| Cost | Usually per copy, typically tax-exclusive; label the basis and support line totals/inclusive entry |
| Price | Tax-inclusive batch selling price; previous values are suggestions requiring explicit confirmation |
| Price changes | Updating existing stock prices is an explicit separate choice |
| Duplicate scan | Show already recorded quantity; Modify, Add more, or Cancel |
| Scan outside intake | Search catalogue; do not receive stock |
| Metadata | Fetch suggestions, show cover candidates immediately as available, never block valid intake |
| Cover | Select a candidate, upload, or defer |
| Connectivity | Online MVP with failed-request form preservation; offline/Flutter deferred |
| Users | Concurrent users, including shared deliveries; attribute saves to users |
| Operations | Manual sales, transfers, reallocation, reservations, damage, returns, adjustments, counts |
| Existing website | Squarespace remains initially; manual stock coordination until integration |
| Git | Local repository now; user adds remote later |
| Documentation | Root README and AGENTS; workflows and other docs in Markdown |

## Proposed implementation decisions

- Use a Laravel monolith and Filament panel, with an intake custom page and shared application action classes.
- Use exclusive channel allocations initially. Shared physical storage does not imply shared selling availability.
- Keep a stock ledger plus transactionally maintained balances; track stock by batch, location, and allocation together.
- Post stock when each line is saved. Closing an intake marks completion; it does not receive the stock again.
- Correct posted entries using compensating operations, subject to remaining stock and reservation checks.
- Use EUR as the initial configurable currency; confirm before implementation.
- Permit provisional products using an internal SKU and scanned identifier when metadata is unavailable; do not require catalogue enrichment to save.
- No unapproved automatic allocation rules, repricing, or metadata overwrite.

## Pending choices and verification

These do not block the specification. Resolve each before implementing the affected behaviour.

| Item | Resolve by |
| --- | --- |
| Selling price tax basis | Confirmed inclusive; stored to 2 decimals |
| Confirm supplier cost tax basis; purchase and sale tax categories may differ | Phase 2 |
| Runtime selection | Implemented: PHP 8.4, Laravel 13, Filament 5, PostgreSQL 17; Composer lockfile retained |
| Identify batches at sale when one ISBN has multiple prices/conditions | Phase 4; proposed explicit batch selection, optional labels |
| Opening stock count and cutover procedure | Phase 4/5 |
| Camera reliability on actual iPhone models and browsers | Phase 1 device test |
| Metadata coverage for Maltese titles and image storage/use permissions | Phase 1/3 provider assessment |
| Who may reprice existing stock, reconcile counts, and edit shared sessions | Phase 2/4 permissions |
| Whether default locations need per-line overrides | Phase 3; proposed explicit override only |

## Change protocol

2026-10-28: Planned expected shipments/CSV invoice import as another entry point to receiving. Imported lines become prefilled Confirm receipt forms that mirror scanning. Partial deliveries, entirely/partly missing items and different/unexpected arrivals are normal; preserve the imported baseline and provide a final discrepancy report. Keep ordinary intake without a pre-entered shipment. See workflows/expected-shipments.md.

2026-10-28: Added inline creation to receiving and destination location selectors during intake setup. It follows existing manager-only location creation permissions; it does not grant new permissions to clerks. A location is saved independently from the still-unsaved intake session.

Record new product decisions here with date, rationale, and affected docs. Record user-directed coding conventions in `../AGENTS.md`. User corrections supersede earlier proposals; update obsolete text rather than leaving contradictory requirements.

## Implementation decisions, 2026-10-28

- Separate `wise_owl_testing` PostgreSQL database, enforced in the test bootstrap before migrations.
- Unit purchase cost is normalised to four decimal places using half-up rounding, retaining original cost input, basis and mode. Selling price input permits two decimals. Purchase and sales tax rates are snapshotted independently.
- Current receipt audit identity is ReceiptLine (idempotency key, fingerprint, actor), linked to immutable ledger entries. A general StockOperation table is still a proposed extension for other stock operations.
- Currency is EUR for this first development slice; multi-currency handling is deferred.
- Ordinary catalogue resources are manager-write/staff-read. Staff can create sessions and receive stock. Posted session setup is locked until versioned editing is implemented.

## 2026-10-28: Implemented online ISBN enrichment

Open Library is the default provider; Google Books is optional with a configured API key. Accept exact canonical ISBN matches only. Lookup is asynchronous and read-only; metadata application, cover selection and catalogue saving are explicit. Missing provider fields do not erase manually entered details. Managers retain catalogue write permission; stock receiving permissions remain separate. Unknown books can be saved provisionally and reviewed later.

Store selected remote cover URLs and provenance without downloading provider images; allow JPEG/PNG/WebP own uploads. A catalogue version prevents stale edits. Lookup failure, missing cover and unavailable author names do not prevent ordinary receiving. Pricing remains a separate explicit per-batch decision.

## 2026-10-29: Alternative-edition cover choices

User requested broader cover choices after a book returned only one exact-ISBN image. Add an explicit Find more covers action for editions of the same linked Open Library work. Keep exact and alternative groups separate, show edition warnings/details, and persist candidate edition provenance. Broader images must not change the scanned ISBN or auto-apply another edition's metadata. The initial intake lookup remains quick and independent.

## 2026-10-29: Cover lookup recovery

Allow a longer bounded timeout only for explicit alternative-cover requests, retry transient failures once with a short pause, preserve loaded choices and distinguish connectivity/rate limits from no additional images. Initial metadata lookup remains independently bounded.

## 2026-10-29: Wider image sources

User approved Google Books plus broader image search. Retain the existing Google Books adapter and add optional key-configured Brave Image Search. Keep explicit search, source links and review-required web candidates separate from exact ISBN/linked-edition results. Use manual Google Images/Amazon search links as a key-free fallback; do not scrape either service. Keys stay in local configuration. No account creation, subscription purchase or live-key configuration has been performed.

## 2026-10-29: Resolve Google/Brave cover-picker confusion

The reported screen showed an unconfigured Brave message after adding a valid Google key. Integrate Google title/author cover search into Find more covers and explicitly name the separate Brave button. Label Google candidates as possible editions matched by title, preserve scanned ISBN/metadata, deduplicate exact matches and keep per-provider failure isolation.

## 2026-10-29: Google opt-in to limit quota

At the user's request, Google Books is now off by default for every book. A separate Search Google Books action opts in for that ISBN; it enables Google results in subsequent Find more covers requests. Reset opt-in when moving to another book. Keep provider caches separate and never spend Google quota during cover selection or metadata application.

## 2026-10-29: Provider organisation and editable descriptions

User requested author resolution, per-book descriptions, a central provider configuration area with metadata/image separation, and a fix for Use these details. Implemented manager-only capability modes with defaults preserved; existing environment keys remain private. Open Library enriches names/work descriptions asynchronously, Google descriptions require its configured lookup mode, and manual text remains editable. No new paid API or scraping provider is enabled.

The disabled action was caused by waiting for pending enrichment. Applying already available metadata now stays enabled, expands the form and gives feedback. The larger catalogue-details redesign remains planned.

## 2026-10-29: Canonical catalogue language and binding

Confirmed: use dropdowns and automatic matching for provider language/binding variants, enabling consistent filtering. Implemented with Symfony Intl language data plus ISO bibliographic aliases and conservative binding aliases. Original unmatched values are retained separately. A single primary language remains the current model; individual multiple-language assignments are future work. Supplier/publisher automatic enrichment is requested for a later phase, with concrete feed formats/access still pending.
