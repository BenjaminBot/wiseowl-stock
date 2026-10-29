# Online ISBN metadata and covers

## Receiving workflow

Scan, photograph or type an ISBN in Receive Delivery. Identification selects an existing book or opens a new provisional draft. A separate request then displays exact-ISBN metadata and cover candidates without blocking the receiving form.

Managers can choose Use these details, select a cover, upload their own, or defer. Suggestions populate a draft only. Save catalogue persists changes; Create book makes an unknown ISBN available to receiving. Neither operation posts stock. Existing book details are initially collapsed to keep intake compact. Enter quantity, cost, tax, condition, selling price confirmation and allocations as usual.

Provider fields that are absent leave typed values intact. Applying a suggestion can replace its populated fields in the draft, so review before saving. No provider prices are used. Keep/defer preserves an existing cover. Own cover upload accepts JPEG, PNG or WebP up to 5 MB. Barcode photographs stay on the phone; a separate own-cover upload is intentionally sent to the server.

New provisional books default to needing review. Products has a catalogue-review filter for books with a missing cover, missing book details, or an explicit review flag. Clear the flag after checking the details. Non-manager staff can receive existing products and view suggestions, but cannot create or edit the catalogue.

## Providers and failure handling

Open Library is enabled by default. GOOGLE_BOOKS_API_KEY optionally adds Google Books; keep it in local environment configuration, never Git. BOOK_LOOKUP_ENABLED can disable lookup; BOOK_LOOKUP_USER_AGENT identifies the application. Restart/reload configuration after environment changes.

Only matching canonical ISBNs are accepted, including equivalent ISBN-10 identifiers. Coverage varies: some books have no result or image, some have several covers. Open Library edition author records commonly contain IDs rather than names. Cached names are used immediately, with background ISBN search enrichment when needed; manual entry remains available if the provider has no names.

Initial metadata requests have a two-second connection timeout and four-second total timeout per provider. The explicit alternative-cover request allows five seconds to connect and ten seconds total. Open Library requests share a one-request-per-second limiter. Cached successful metadata lasts six hours, no match fifteen minutes, and unavailable/rate-limited results ten seconds. The staff endpoint permits sixty requests per minute. A failed provider does not discard another provider's results; manual entry and provisional creation remain available.

Browser requests are aborted when changing ISBNs. Response sequence and server tokens prevent late results changing the next book. Optimistic catalogue versions reject stale saves after another user edits the same product. A concurrently created ISBN is reused without overwriting its catalogue details.

## Covers and storage

Candidate thumbnails load directly from allowlisted provider HTTPS hosts, with source links and no-referrer. Broken images cannot be selected. The selected external URL and provenance are stored; provider image bytes are not downloaded. Provider availability still affects later display. Own uploads use the public Laravel disk under book-covers; run storage:link during setup and include uploaded files in future backups. API credentials are stripped from candidate image URLs.

No automatic storefront publication or Squarespace synchronisation is included.

## Build and checks

Use the root README build commands. npm run build compiles the official Filament custom theme and both lazy Alpine components; filament:assets publishes the components. npm test runs scanner and lookup lifecycle checks. PHP feature tests cover exact matching, caching, failures, permissions, explicit saving, uploads, stale edits and provisional creation.

Live Open Library lookup was verified with ISBN 9780140328721: Fantastic Mr. Fox and two cover candidates. Google Books is covered by mocked responses; no live API key was configured. Actual iPhone camera validation remains pending.

## Provider references

- [Open Library Books API](https://openlibrary.org/dev/docs/api/books)
- [Open Library Covers API](https://openlibrary.org/dev/docs/api/covers)
- [Open Library API usage guidance](https://openlibrary.org/developers/api)
- [Google Books API usage](https://developers.google.com/books/docs/v1/using)

## Alternative edition covers (2026-10-29)

Find more covers is an explicit additional request, separate from the initial exact-ISBN lookup. The backend uses the verified edition's Open Library work link, checking up to fifty linked editions and returning at most twelve unique additional cover URLs. It does not guess unrelated books from similar titles. Results can include translated editions and adaptations; titles, edition ISBNs, publishers, dates and formats are shown where available. Compare the image with the physical copy.

Exact-ISBN choices stay separate. Alternative cards say Other edition — check your copy, and selection uses Use this edition cover. Saving preserves match_type=alternative and the candidate edition_isbn in ProductImage alongside the source edition URL. This never changes Product.isbn or imports metadata from the alternative edition. Own uploads record match_type=upload. Previously saved provider covers default to exact.

The extra request uses the same staff authorization, timeouts and shared Open Library rate limiter as exact lookup. If it is rate-limited, retry shortly. Results are cached separately for six hours (fifteen minutes when empty). Failures do not clear exact covers or manual details. Late responses are cancelled/ignored when changing ISBNs. No linked work, no extra images, or broken thumbnails still permits upload/defer and normal receiving.

References: [Open Library API](https://openlibrary.org/developers/api), [cover API](https://openlibrary.org/dev/docs/api/covers).

## Provider connection recovery (2026-10-29)

The explicit broader search retries once after a 1.5-second pause for a connection failure, provider failure or rate limit. No unbounded retry loop is used. Changing ISBN or leaving the component invalidates a pending retry. Failure preserves previously loaded exact and alternative cover choices. The UI distinguishes provider connectivity, rate limiting, malformed/unavailable responses and a successful search with no extra images.

For False Alarm, ISBN 9781541647473, the provider returned four linked editions during verification. Only one had a cover; it was the same cover already shown for the scanned ISBN, so no additional candidates were available. An intermittent connection exception was also reproduced, which previously produced the same generic temporary-unavailable message.

## Wider web image search

Search web images adds Brave Image Search candidates in a separate review-required group. It sends only the canonical ISBN and book cover query. A missing key produces setup guidance and manual Google Images/Amazon links. Google Books joins exact ISBN results only after Search Google Books is explicitly chosen. See [provider setup](provider-setup.md). Web candidates retain source-page provenance and match_type=web; no ISBN or metadata is inferred from these images.

## Google title/author alternatives (2026-10-29)

Find more covers uses Open Library by default. It also searches Google titles/authors after Search Google Books is chosen for the current ISBN. It prefers an existing exact suggestion with author names; otherwise it can resolve a trusted Open Library author ID during this explicit search. Author results are cached for one day and use the shared provider limiter. A short rate-limit pause may be needed before retrying.

Google title searches use a bounded free-text title/author query and require the normalized base title to match, allowing subtitle differences. These are possible matches, not verified editions: cards say Matched by title, not the scanned ISBN and include available author/source/edition information. Unrelated titles and exact-ISBN duplicates are excluded. Candidate images remain alternative covers and never populate catalogue metadata. Google results are cached separately per ISBN/query/key for six hours (fifteen minutes when empty).

One source failure does not hide another source's images; partial results are labelled. Exact-ISBN metadata and cover groups stay separate. Search web images (Brave) uses its own optional key and now explicitly directs Google users to Find more covers.

## Per-book Google opt-in

A configured API key makes Google available, but initial scans, ordinary retries and Find more covers use only Open Library until Search Google Books is chosen. The choice lasts for the current lookup target and resets on a new book, page reload or catalogue refresh. Subsequent retries/alternative searches for an opted-in book may use Google, with cached results reused. Exact/default and Google cache variants are separate. Selection and metadata application resolve cached candidates rather than querying Google. If a candidate expired, search again before using it.

## Author and description enrichment

Open Library edition responses commonly contain author IDs. Cached names are used immediately; otherwise a separate ISBN search request resolves names against the same work or exact ISBN, with bounded rate-limit retries. Work descriptions load in the background after author lookup, using the verified work link. Both follow configured book-information modes and do not enable Google. A failed lookup preserves metadata/covers and allows manual entry.

Descriptions from Open Library (string or value object) and opted-in Google metadata are converted to plain text, stripped of scripts/styles and capped at 20,000 characters. Suggestions include a description preview; Use these details copies only populated values into the editable draft. It stays available while enrichment is pending, expands the editor and shows a confirmation. Arriving metadata never overwrites staff text. Save catalogue persists the final description. Missing descriptions are optional during receiving and remain in the catalogue review queue. Provider coverage varies; a boxed set may have no shared description. See [provider configuration](lookup-providers.md) for current automatic/manual rules.

## Language and book format / binding

Receive Delivery and Products use searchable dropdowns. Language is stored as a canonical ISO language code, displayed as an English name: en, eng, English and en-GB resolve to en / English; bibliographic aliases such as fre/fra and ger/deu also converge. Supported three-letter-only languages remain selectable. This is one primary language per edition; Multiple languages is available, but individual multi-language tags are not implemented. Regional/script differences are not separate filters.

Book format means binding/presentation: hardcover/hardback, paperback/softcover, board book, spiral bound, ring bound, loose leaf, boxed set, e-book, audiobook, or explicitly Other. Unknown stays blank, never silently Other. Google BOOK does not establish a binding. Provider suggestions are normalised before application, including existing cached suggestions; applying remains explicit.

Unmatched provider values remain in read-only Provider / previous fields for manual review. Existing recognised catalogue values are converted by migration; unmatched original text is retained separately, canonical value cleared and needs_review set. Canonical fields have indexes and Products filters/optional sortable columns. Rolling back the migration drops original-value fields; it does not undo code conversion. No inventory quantities or prices change.
