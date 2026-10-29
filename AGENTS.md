# Project instructions

These instructions apply throughout this repository. Read `README.md` and `docs/README.md` before changing behaviour. Follow subsequent explicit user instructions and update this file when they change project conventions. Do not present implementation proposals as user-approved requirements.

## User-directed conventions

- Maintain a root `README.md` and Markdown workflows/documentation under `docs/`.
- Follow Laravel and Filament conventions. Prefer the installed versions' generators and standard application layout.
- Use Git locally with origin at https://github.com/BenjaminBot/wiseowl-stock.git, configured at the user's request on 2026-10-29. Publish or push only when requested.
- Record future user-directed conventions here, with a date and a short explanation. Keep current instructions coherent; remove superseded rules rather than accumulating contradictions.
- The application must be Dockerised. Defer NAS/cloud deployment choices.
- Prioritise phone intake on iPhones, with Android considered. Dedicated scanners and keyboard input are additional input methods.
- Keep offline support, Flutter, public storefront replacement, and POS compliance work outside the MVP.

## Laravel implementation conventions

These are project implementation defaults, subject to the installed framework versions and later user adjustments.

- Use standard `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, and `tests/` directories when scaffolding.
- Use singular PascalCase Eloquent models, plural snake_case tables, conventional foreign keys, migrations, factories, relationships, and casts.
- Save source files and generated text explicitly as UTF-8. When generating files from Python on Windows, pass `encoding='utf-8'`; do not rely on the platform encoding. Exercise Livewire JSON responses when testing dynamically loaded options.
- Keep route/controller/Livewire/Filament code focused on presentation, validation, authorisation, and invoking application operations.
- Place reusable business operations in focused classes such as `App\Actions\Inventory\ReceiveStock`. `app/Actions` is a project choice, not a mandatory Laravel directory. Do not introduce repositories, a second ORM, or a module framework without a concrete need.
- Use Eloquent and the query builder, model policies and Laravel Gates, dependency injection, configuration files, Laravel HTTP client, queues, and Storage facilities where appropriate.
- Read environment variables through configuration. Never commit `.env`, real credentials, real customer data, or private supplier documents. Maintain `.env.example` when the application exists.
- Validate server-side even when a Filament schema already validates in the UI. Use Form Requests for HTTP controller endpoints when appropriate; do not force them into Livewire lifecycle methods.
- Use integer quantities and exact decimal monetary arithmetic. Never use floating-point arithmetic for persisted financial calculations. Store explicit currency and cost tax basis.
- Keep external lookups outside stock transactions; give them timeouts, caching, and failure handling.
- Use migrations for schema changes. Never rewrite applied migrations in a deployed system to conceal changes.

## Filament conventions

- Generate resources, pages, schemas, and tables using the installed Filament version. Preserve its generated organisation and namespaces; do not copy obsolete major-version examples.
- Use resources for catalogue/configuration CRUD and a custom page for fast intake.
- Use Filament form components, schemas, tables, actions, notifications, and policy integration before inventing alternatives.
- Keep intake location creation inline through Filament Select create-option actions. Reuse the Locations schema and enforce its policy in the action and write callback; preserve unsaved session inputs and automatically select the created record.
- Keep reusable schema/table configuration in generated classes where the installed version places them.
- Use Blade/Livewire and the Filament theme pipeline for custom UI. Do not introduce Angular or Bootstrap into the panel by default.
- Enforce authorisation inside executable actions and application boundaries, including direct requests. Hidden navigation or disabled buttons are insufficient.
- Do not expose raw stock balance editing through generic resource forms. Invoke stock operations with an audit reason.
- Use accessible labels, visible errors, large touch targets, and layouts usable on narrow screens. Camera input, manual entry, and later scanner input must call the same identification flow.

## Inventory rules that implementation must preserve

- Distinguish physical location, exclusive channel allocation, and customer reservation.
- One physical copy must never be made available twice. Do not implement website allocation as another copy of physical stock.
- Stock batch cost, condition, tax snapshots, and selling price must remain distinguishable for the same ISBN.
- Prices require explicit confirmation for every received line; never save on scan.
- Duplicate scans within an intake prompt Modify / Add more / Cancel. Adding is a new line, not a silent increment.
- Receiving, sales, transfers, reallocation, reservations, and corrections must be transactional, authorised, and attributable.
- Use idempotency keys and database uniqueness for retryable writes. Use locking/version checks to prevent overselling and stale edits.
- Posted stock history is immutable; corrections use linked compensating records. Never delete stock history to fix a count.
- Lookup failures and missing cover images must not block valid stock intake. Late responses must not overwrite another scanned item's data or staff edits.

## Verification and documentation

- Add meaningful tests for stock invariants, concurrency, retry behaviour, authorisation, validation, and framework integration.
- Run `docker compose exec app php artisan test --compact` and `docker compose exec app vendor/bin/pint --test app database routes tests`. Prepare the isolated database with `stock:prepare-test-database`. Tests must force `wise_owl_testing` before migration hooks; never run destructive tests on the development database. Do not use `--parallel` until per-process test databases are implemented.
- Test locking/concurrency against the selected production database engine; SQLite alone is insufficient evidence.
- Update the relevant workflow, acceptance scenarios, and decision log alongside behaviour changes.
- Do not claim iPhone camera support has been validated until an actual device test is recorded.
- Keep experiments outside future `app/` and `resources/` implementation. Never treat demo browser state as production inventory.

## Instruction history

| Date | Instruction |
| --- | --- |
| 2026-10-28 | Maintain README, Markdown docs, and this instruction file; follow Laravel and Filament conventions. |
| 2026-10-28 | Initialise local Git; user will add the remote later. |
| 2026-10-29 | User supplied the GitHub origin URL and authorised the initial push. |

## Implemented foundation (2026-10-28)

Laravel 13 / Filament 5 / Livewire 4 use the generated resource, schema, table, and page layout. Docker uses PHP 8.4 and PostgreSQL 17. Selling prices include tax; purchase cost defaults to excluding tax. Use Brick Math for decimal conversions. Keep the PHP dependency lockfile and never commit local environment values. The initial manual receipt page is not the completed phone intake workflow.

## Scanner integration

Use the npm-locked html5-qrcode decoder bundled with esbuild and published as a Filament AlpineComponent. Keep camera/photo decoding on the client and send only the ISBN to the authorised Livewire identification method. Never post stock on recognition. Preserve the current draft on repeated/unknown scans and require confirmation before switching books. Run `docker compose run --rm --no-deps frontend npm run test:scanner`. After JavaScript changes, build with `npm run build:scanner` inside the frontend service and run `php artisan filament:assets` inside app. Do not claim actual iPhone live-camera validation from mocked lifecycle tests or a desktop photo check.

## Online catalogue enrichment

Keep BookLookup HTTP requests outside inventory transactions and outside the initial Livewire identification round trip. Use the asynchronous authorised read endpoint, timeouts, bounded caching and provider throttling. Preserve ISBN/token guards and explicit apply/save controls; never auto-overwrite staff fields or import provider prices. SaveIntakeBook is the intake catalogue write boundary. Validate cover hosts and never persist API keys in URLs. Remote covers remain provider URLs with provenance; own uploads use Laravel Storage.

Run frontend npm test for scanner and lookup lifecycle tests. The full npm run build compiles the custom Filament theme plus both components; publish with filament:assets. The frontend container requires the read-only installed vendor volume for the theme import.

## Alternative edition covers (2026-10-29)

Broader cover lookup must be explicit. Open Library alternatives use the verified book's linked work; Google Books may use the verified title and author as clearly labelled possible matches. Keep exact-ISBN results separate from alternative editions, with visible warnings and source edition details. Preserve match_type and edition_isbn when saving ProductImage. Selecting another edition's image must never change Product.isbn or silently apply its metadata. Keep the shared provider rate limit and asynchronous stale-response guards.

## Broader image providers (2026-10-29)

Keep Google Books and Brave credentials in Laravel configuration sourced from ignored local environment values. Web image search is explicit and separate from exact ISBN metadata. Label web candidates as unverified, retain source links and persist match_type=web without changing ISBN or metadata. Only trusted cached candidate keys can be selected; do not issue extra billable requests on selection. Validate public HTTPS image/source URLs, use only Brave thumbnails for preview, reject local/IP/credentialed URLs and never fetch arbitrary image URLs server-side. Do not scrape Google Images or Amazon; manual search links are allowed.

## Google alternative matches (2026-10-29)

Find more covers uses Open Library by default and includes Google title/author matches only after explicit per-book opt-in. Title matches must not appear as exact ISBN matches or change product metadata/ISBN. Filter unrelated titles, retain candidate edition/source information and label uncertain matches visibly. Cache provider results separately; one failed source must not hide another's covers. Resolve missing Open Library author names in background metadata enrichment, with caching and the shared request limiter. Initial scanning remains independent.

## Google quota control (2026-10-29)

User requires Google Books lookup to be optional to conserve quota. Keep the seeded default on Open Library automatic and Google manual; managers may explicitly change provider capability modes in Lookup Providers. In manual mode require explicit Search Google Books for each current ISBN; reset opt-in on the next lookup target. Propagate the flag to alternative search only after opt-in. Keep default and Google caches separate and reuse cached Open Library data. Applying suggestions or selecting exact/alternative Google covers must read cached candidates without issuing Google API requests.

## Provider configuration and catalogue enrichment (2026-10-29)

Use LookupProvider/LookupSettings as the central mode registry, separating metadata and image capabilities with automatic/manual/disabled policies. Enforce modes server-side before cached results or outbound requests; keep provider raw caches separate from policy-filtered results. Settings are manager-only; credentials remain environment-managed and must not appear in tables/forms. Future metadata, image or scraping services need registered adapters/capabilities, not arbitrary user-entered URLs.

Descriptions are editable plain text on Product, including non-books. Retrieve Open Library work descriptions and author names asynchronously, preserving Google opt-in/default modes. Web suggestions never overwrite staff drafts automatically; apply and save remain explicit. Use these details must stay usable while enrichment is pending, expand the draft and show visible confirmation. Keep missing metadata in the review workflow. A larger catalogue-details UI redesign is follow-up work requested by the user, not completed in this change.

## Canonical book classification (2026-10-29)

User requires searchable language and binding dropdowns with automatic provider matching and consistent filtering. Use BookLanguage and BookFormat for all catalogue write/read paths. Store canonical language codes (prefer ISO alpha-2, retain supported alpha-3 where no alpha-2 exists) and fixed binding keys; display English labels. Preserve unmatched provider/legacy text in separate original fields and allow manual selection or deferral. Never guess a binding from Google printType=BOOK. Existing catalogue saves and metadata apply remain explicit. Supplier/publisher catalogue enrichment is a requested future capability: automatically retrieve and match metadata by ISBN, including descriptions, through registered adapters; integration details remain pending supplier formats/access.
