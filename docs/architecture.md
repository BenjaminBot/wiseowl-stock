# Architecture and Laravel/Filament conventions

## Direction

Build one Dockerised Laravel application with a Filament staff panel. Use a dedicated intake page, ordinary resources for catalogue/configuration, application actions for inventory operations, and a relational database. No separate Angular app, microservices, or offline synchronisation in the initial scope.

The running development stack is PHP 8.4.27, Laravel 13.39.0, Filament 5.11.0, Livewire 4.4.8 and PostgreSQL 17. Composer resolved the stack with a PHP 8.4 platform target, and composer.lock is retained. The Docker build and PostgreSQL integration tests have run successfully.

## Expected layout

Follow generators for the selected versions. This tree is illustrative; optional directories are created when needed.

```text
app/
  Actions/Inventory/          # Project convention: shared use cases
  Enums/
  Filament/
    Pages/                   # Dedicated intake/stock workspaces
    Resources/               # Generator-owned resource organisation
  Http/Controllers/          # Future external HTTP endpoints
  Http/Requests/
  Jobs/                      # Metadata/image tasks where appropriate
  Models/
  Policies/
  Providers/
bootstrap/
config/
database/{factories,migrations,seeders}/
public/
resources/{css,js,views}/
routes/
tests/{Feature,Unit}/
docs/
```

Use singular model names, conventional snake_case tables/keys, Eloquent relationships and casts, migrations, factories, policies, and Laravel's HTTP/Storage/queue facilities. Keep Filament's installed major-version schema/table organisation. Do not invent a parallel framework structure.

## Operation boundary

An intake page or future controller validates input, authorises the operation, and invokes an action such as `ReceiveStock`. That action rechecks invariants, claims an idempotency key, locks affected records in deterministic order, and writes the receipt/batch, ledger, balances, and audit metadata in a single transaction.

Examples of focused actions: ReceiveStock, CorrectReceiptLine, TransferStock, ReallocateStock, ReserveStock, ReleaseReservation, FulfilReservation, RecordSale, ReconcileCount. These are proposed names, not existing classes.

Do not hold locks while calling a metadata service, downloading an image, or updating Squarespace. Dispatch post-commit work or use a durable outbox when integration is added. Store manual external-update tasks in the stock transaction now.

## Concurrency and retries

- Persist a unique operation key and request fingerprint. Repeat same request -> original result; changed request with same key -> conflict.
- Lock the actual batch/location/allocation balances before checking availability. Handle initial balance creation with unique constraints and retry, not only read-before-insert.
- Add version checks for corrections and shared-session configuration changes.
- Coordinate closing a session with receiving into it using the same locking boundary.
- Retry deadlocks only with idempotent transaction logic; keep side effects outside retried closures.
- Validate behaviour against the selected production database; do not infer row-lock safety from SQLite tests.

## Metadata and images

Use provider adapters behind a small lookup boundary, not provider-specific logic throughout the UI. Evaluate Open Library and Google Books for exact ISBN matching. Cache by canonical ISBN, bound request durations, distinguish failures from empty results, and preserve staff edits. Verify current usage/storage terms before persisting remote images. Missing metadata is not a failed stock operation.

## Camera

The initial standalone proof uses an explicit EAN-13 decoder rather than relying solely on native BarcodeDetector support. Production will integrate the selected scanner through the normal Vite/Filament asset pipeline. Test actual iPhones. Use HTTPS trusted by the phone for camera access, rear-camera preference, explicit permission/start, and release on exit/backgrounding.

## Authentication and authorisation

Use individual accounts, panel access control, policies for products/configuration, and explicit permissions for stock actions. Enforce permissions in executable paths, not just navigation. Define clerk/manager permissions during Phase 2 rather than granting all staff unrestricted corrections and repricing.

## Containers and portability

Phase 2 will add a local Compose stack for the web application and relational database, plus queue/scheduler processes if needed. Use environment configuration and persistent volumes for database and uploaded images. Keep local source bind mounts separate from production image design. Establish backup/restore during the pilot. NAS architecture, cloud hosting, public domains, and production exposure remain deferred.

## Official references

- [Laravel directory structure](https://laravel.com/docs/13.x/structure)
- [Laravel transactions](https://laravel.com/docs/13.x/database#database-transactions)
- [Filament resources](https://filamentphp.com/docs/5.x/resources/overview)
- [Filament custom pages](https://filamentphp.com/docs/5.x/navigation/custom-pages)
- [Filament installation requirements](https://filamentphp.com/docs/5.x/introduction/installation)
- [Open Library ISBN and edition lookup](https://openlibrary.org/dev/docs/api/books)
- [Google Books search](https://developers.google.com/books/docs/v1/using)
- [Camera secure-context requirements](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)
- [Scanner library integration](https://scanapp.org/html5-qrcode-docs/docs/intro)

## Current implementation boundary

The general StockOperation/outbox model remains future work. The implemented receiving operation uses ReceiptLine as its operation record, with idempotency key/fingerprint/actor and immutable linked ledger entries. Receipt saves lock the intake session and create fresh batch balances transactionally. Two-process retry verification passed on PostgreSQL. Sales/reservation/transfer locking remains unimplemented and unverified.

Filament serves published panel assets and a custom Vite theme importing the installed official theme. Scanner and book lookup are bundled as lazy Alpine components. The development web process uses Artisan Serve; production serving remains a later deployment decision.

## Implemented scanner assets

The receiving page now uses a Filament AlpineComponent compiled from resources/js/components/isbn-scanner.js. npm locks html5-qrcode 2.3.8; a Docker Node tools service runs esbuild/Vite and client tests. Build artifacts remain ignored. Publish them with filament:assets after building. A content hash in the component URL avoids stale browser bundles. The page retains manual ISBN entry if scanner assets are unavailable. Neither photos nor camera frames are uploaded; only ISBN identification requests reach Livewire. Online catalogue providers are integrated through Services/Books/BookLookup and an authenticated, authorised, throttled read endpoint. Browser requests run asynchronously with abort and token guards. Explicit catalogue writes use Actions/Catalogue/SaveIntakeBook with policy checks and optimistic version validation, separate from receiving transactions. See [book lookup](book-lookup.md).
