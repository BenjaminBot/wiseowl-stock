# Verification record

## 2026-10-28: Receiving dropdown encoding repair

Reproduced the user's malformed-UTF-8 error in a Livewire call to the product selector's getOptionsForJs method. The receiving page had a Windows-1252 separator byte in product labels. Converted that source to UTF-8. The regression test now passes (2 assertions) for option loading and search with a Maltese product title. Browser verification confirmed product options load while preserving the user's selected delivery. Other application source files were checked for invalid UTF-8; none were found.

## 2026-10-28: Inline intake locations

Four PostgreSQL/Livewire tests passed (29 assertions): create/select a receiving location while retaining unsaved reference, create/select a location in a destination repeater, reject duplicate names inside the modal, and reject a clerk's crafted attempt to mount the create action. Laravel Pint passed on the changed PHP files.

## 2026-10-28: Demo stock seeding

Implemented and ran the explicit local-only DemoDataSeeder using an existing manager. Verified 15 products (12 books/3 other), 17 receipt batches, and 136 physical units. Three dedicated PostgreSQL tests pass (17 assertions), covering repeatability, preserved edits/credentials, existing ISBN collision handling, and refusal outside local/testing. No account credentials or permissions were changed. See demo-data.md for details.

## 2026-10-28: Phase 1 artifacts

Passed:

- Eleven ISBN validation/normalisation cases, including ISBN-10 with X, equivalent identifiers, 979, invalid checksums/lengths, non-book EAN, and empty input.
- Local Markdown link targets checked by the verification script.
- DOM-stub checks for manual entry, equivalent-ISBN repeat indication, invalid input rejection, and insecure-origin fallback.
- Scanner JavaScript syntax check with Node.js.
- Desktop browser: example ISBN -> Check ISBN -> canonical identifier and one test-list entry.
- Git initialised on main with no remote.

The DOM stub is a lightweight logic check, not a browser/device replacement. No actual camera or external decoder has been tested. The screenshot at outputs/scanner-proof/preview.png records the desktop result.

Reproduce from repository root:

```sh
node tests/scanner-proof.cjs
node --check outputs/scanner-proof/scanner.js
git diff --cached --check
```

Pending: actual iPhone/Safari and Android camera checks over trusted HTTPS; owner/Reb workflow walkthrough; metadata provider coverage/usage review; all Laravel/database integration checks, since no backend has been scaffolded.

No production inventory or external stock system has been changed. The temporary desktop server is for verification only. See [scanning-proof.md](scanning-proof.md) for serving and device-test instructions.


## 2026-10-28: Docker/Laravel foundation

- Confirmed firmware virtualisation enabled after reboot, both WSL distributions running, and Docker Engine responsive.
- Copied the checkout/Git metadata into the new Repositories/Codex workspace, preserving the original.
- Built the PHP 8.4 container, installed locked Laravel/Filament packages, and applied all migrations to PostgreSQL 17.
- Generated Filament panel/resources/pages using installed generators.
- Eighteen tests passed (63 assertions), including server-side permissions, screen rendering, Livewire receipt submission, allocation conservation, decimal conversions, explicit price confirmation, batch price preservation, rollback, immutable history, and concurrent receipt retry.
- An initial test configuration let container environment values select the empty development database. No real stock/accounts existed. Fixed Tests/TestCase.php to force the isolated database before migration hooks and reran successfully; development seed restored.
- Composer strict validation and audit passed; Blade templates compiled. Laravel Pint applied.
- Browser login initially exposed a Docker-first DB_HOST mismatch; .env and .env.example now use database, and the login renders normally.
- User reported a successful iPhone photo barcode recognition in the earlier experiment. Exact device/browser details and live-camera validation are still pending.

No operational sales/reservation engine, cover lookup, full mobile intake, or production deployment has been completed.

## 2026-10-28: Integrated receiving scanner

The full PostgreSQL suite passes: 33 tests, 161 assertions. This includes 7 new identification tests for canonical ISBN matching, no stock/product writes on scan, repeat/unknown draft preservation, explicit book switching, duplicate receipt notices, authorisation, and product selection beyond the initial option limit. Nine Node lifecycle/validation tests pass, including capture deduplication, photo/error handling, delayed-start teardown, background release, permission fallback and post-save resume.

The complete Vite/esbuild build and runtime npm dependency audit passed. Browser verification decoded tests/fixtures/isbn-9780140328721.png on the real receiving page and selected the matching demo catalogue book through Livewire. No receiving save was performed during that check. Actual iPhone/Safari live capture and physical camera permission remain pending.

## 2026-10-28: Online ISBN catalogue enrichment

The final PostgreSQL suite passes: 48 tests, 219 assertions. Twelve JavaScript tests pass across scanner and lookup lifecycle behavior. PHP coverage includes exact ISBN matching, provider failures/caching, authorised lookup access, explicit catalogue saves, provisional creation, cover selection/uploads, stale edit rejection and preserving manually entered fields when a suggestion has no value. Pint passes for 97 files; Blade compilation and the Vite/esbuild build pass. The retained proof checks pass for 11 ISBN cases and 35 local documentation links.

A live Open Library request and the actual Receive Delivery page returned Fantastic Mr. Fox (9780140328721) and two loaded cover candidates. Selecting a cover changed the draft without saving catalogue or stock records. The detail editor is collapsed for existing products. Preview: outputs/book-lookup-online.png. Google Books uses mocked responses only because no live API key was configured. Actual iPhone live-camera testing remains pending.

## 2026-10-29: Alternative edition covers

The full isolated PostgreSQL suite passes: 54 tests, 245 assertions. All 15 JavaScript tests pass. The new tests cover linked-work lookup, duplicate/invalid cover exclusion, separate caching, missing work links, disabled providers, rate limiting/failures, staff authorization, explicit selection/save provenance, unchanged product ISBN/stock, and stale alternative responses. Pint passes across 99 PHP files. The Vite/esbuild build and Blade compilation pass.

Live Open Library lookup for 9780140328721 returned two exact cover candidates and twelve alternative edition candidates. The real receiving page displayed separate groups, edition titles/details and warnings. Selecting an alternative changed the draft only; no catalogue or stock save was performed. A 390 by 844 desktop viewport check confirmed touch controls and cover cards fit a narrow layout; this does not substitute for actual iPhone camera/device validation. Preview: outputs/alternative-covers-mobile.png. Documentation and AGENTS.md reflect the workflow and persisted ProductImage edition provenance.

## 2026-10-29: Reported False Alarm lookup failure

Reproduced an intermittent Open Library connection exception for work OL21675187W. A successful request returned four editions with one cover ID, already offered by the exact ISBN lookup. Added bounded connection recovery and clearer messages. The full isolated PostgreSQL suite passes: 56 tests, 250 assertions. All 18 JavaScript tests pass. Pint passes for 99 files. Rebuilt/published the lookup component. Live UI verified the no-additional-images outcome without saving catalogue or stock data.

## 2026-10-29: Google Books and broader web image integration

Added optional Brave Image Search and visible provider configuration states alongside the existing Google Books adapter. The final full PostgreSQL suite passes: 63 tests, 303 assertions. All 21 JavaScript tests pass. Pint passes across 101 PHP files, the complete Vite/esbuild build and Blade compilation pass, and the retained proof passes 11 ISBN cases and 39 local documentation links.

New coverage includes missing/disabled keys, provider auth and quota errors, request-header credential handling, bounded/deduplicated candidates, public HTTPS URL restrictions (including numeric/trailing-dot local-host variants), trusted cache-only selection, unchanged product ISBN/metadata/stock draft and stale web responses. Mocked Brave results persist source provenance and match_type=web only on explicit save.

In the actual receiving page, False Alarm retained its exact Open Library cover. Search web images correctly explained that Brave was not connected, and Google Images/Amazon links contained the current ISBN. No catalogue or stock save occurred. Preview: outputs/cover-providers.png. Neither Google Books nor Brave is live-enabled because keys are not configured; external result coverage/quotas remain unverified. Key-free Internet Archive/Commons alternatives were discussed but are not integrated.

## 2026-10-29: Live Google Books key validation

User added their Google Books key to local environment configuration. Cleared Laravel configuration cache and verified HTTP 200 responses without authentication/quota errors. Exact ISBN 9781541647473 (and equivalent ISBN-10) returned zero Google results. Title/author search for False Alarm by Lomborg returned one book with a cover, identified as edition 9781541647626. The application correctly excludes this different ISBN from exact matches. Refreshed only the two tested ISBN lookup caches. No catalogue/stock records or credentials were printed or changed.

## 2026-10-29: Google alternative covers now load in receiving

The reported page's Search web images action was the unconfigured Brave provider; Google support previously covered only exact ISBN matches. Find more covers now aggregates Google title/author matches and linked Open Library editions. A quoted Google title query returned no results live; the tested free-text title/author query returned edition 9781541647626 with a cover. Exact Google ISBN responses also began returning a valid cover during this run, and the exact lookup cache namespace was refreshed for the new author-link metadata.

The actual receiving page loaded the orange Google Books exact cover alongside the yellow Open Library cover for ISBN 9781541647473. Find more covers displayed a loaded Google alternative, edition 9781541647626, marked Matched by title, not the scanned ISBN. The alternative could be selected as a draft; no product/receipt save was performed. Secrets were never printed.

The full isolated PostgreSQL suite passes: 68 tests, 331 assertions. All 22 JavaScript tests pass. Pint passes across 102 files. The component build/publish and Blade compilation pass. New coverage includes title/author query format, unrelated-title/exact-ISBN filtering, separate caches, source failure isolation, rate-limited author resolution and explicit alternative provenance saves. Preview: outputs/google-cover-options-fixed.png.

## 2026-10-29: Per-book optional Google Books

At the user's request, Google Books now stays off for normal scans and default alternative searches, even with a valid key configured. An explicit Search Google Books action enables the provider for that lookup target. The client passes google=1 only after opting in, preserves it for that book's retries/alternative search, and resets it for the next target. Default and Google caches are isolated; default Open Library data is reused. Cover selection and metadata application read Google candidates from cache without new Google calls.

The full isolated PostgreSQL suite passes: 70 tests, 341 assertions. All 23 JavaScript tests pass. Pint passes across 102 files. Component build/publishing and Blade compilation pass. Tests specifically assert that no Google request occurs by default, explicit route flags activate Google, the next ISBN resets opt-in and selection adds no provider request. The actual page showed the default-off button/message and changed to enabled only after clicking it; no catalogue/stock records were saved. Preview: outputs/google-optional.png.

## 2026-10-29: Author/description enrichment, provider settings and apply action

Resolved the reported missing Open Library author names by enriching ISBN suggestions in the background, caching verified author names and preserving manual edits. Added editable Product descriptions, Open Library work-description lookup and plain-text Google description parsing. Live Open Library returned Bjørn Lomborg for False Alarm. That book had no work description during the check; Fantastic Mr. Fox returned 1,141 description characters. Missing descriptions remain reviewable/manual rather than blocking stock intake.

Added manager-only Configuration -> Lookup Providers with independent automatic/manual/disabled modes for metadata and images. The actual page shows Open Library automatic, Google manual, Brave images manual and credential presence without exposing keys. Tests cover direct-request authorization, actor attribution, capability validation, mode-dependent requests, raw-cache reuse and invalidation of visible results after configuration changes. No new keys, accounts, subscriptions or scraping services were created.

Reproduced the reported disabled Use these details on Witcher Boxed Set while description enrichment was pending. Removed the pending-enrichment dependency. Applying now expands the draft and notifies the user. In a separate actual browser draft, it populated Witcher Boxed Set, Andrzej Sapkowski, publisher and publication date. No catalogue or stock save was performed, and the user's existing tab/draft was not changed. A wider catalogue workspace redesign remains follow-up work.

The full isolated PostgreSQL suite passes: 88 tests, 417 assertions. All 29 JavaScript tests pass. Pint passes across 115 files. Full Vite/esbuild build, published assets and Blade compilation pass. The retained proof passes 11 ISBN cases and 45 local documentation links. Previews: outputs/catalogue-details-applied.png and outputs/lookup-provider-settings.png.

## 2026-10-29: Canonical language and binding dropdowns

Intake and Products now share searchable canonical language and binding options. Lookup results, including cached payloads, are normalised before applying; known ISO/bibliographic aliases converge and unknown originals remain visible and separately stored. Products filters and sortable readable columns use the canonical fields. The new migration converts existing metadata, marks unmatched values for review and increments catalogue versions so stale drafts cannot overwrite converted entries. It does not alter inventory or prices. Supplier/publisher automatic enrichment is documented as a requested future phase, not implemented.

Verified on isolated PostgreSQL: 95 PHP tests / 467 assertions, including seven new tests for aliases, conservative binding matching, Livewire apply/save, unmatched values, direct validation, Products filtering/sorting and legacy conversion. All 29 frontend lifecycle tests pass. Pint passes across 119 files; Composer validates and reported no security advisories during the dependency installation. The development migration and Blade compilation succeeded. No new actual iPhone camera validation was performed.
