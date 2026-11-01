# Wise Owl Stock Management

Phone-first inventory management for Wise Owl Bookshop: books first, with generic products for other categories.

## Current implementation

The Laravel/Filament foundation runs in Docker with PostgreSQL. It includes staff-only login, manager-controlled catalogue/configuration resources, delivery session setup, a receiving page with camera, barcode-photo and manual ISBN identification, and a stock overview by batch/location/allocation.

Receipts require price confirmation and an exact allocation split. They atomically create a receipt, batch, balances and audit ledger entries. Repeat submissions are idempotent. Costs use exact decimal arithmetic; selling prices include tax. The standalone scanner proof remains separate.

This is a development foundation, not the operational MVP. Full duplicate Modify/Add/Cancel handling, posted corrections, transfers, sales, and reservations are still to be implemented.

## Local setup

Requires Docker Desktop with a working WSL2 engine. Tested runtime: PHP 8.4.27, Laravel 13.39.0, Filament 5.11.0, Livewire 4.4.8, PostgreSQL 17. Composer dependencies are locked.

1. Copy `.env.example` to `.env` and set a nonempty local `DB_PASSWORD`. Keep `DB_HOST=database` for Docker.
2. Run from this repository root:

```sh
docker compose build app
docker compose run --rm --no-deps app composer install --no-interaction
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm --no-deps frontend npm ci
docker compose run --rm --no-deps frontend npm run build
docker compose run --rm --no-deps app php artisan filament:assets
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan stock:create-staff
```

The account command asks for name/email and a hidden password. Answer Yes to catalogue/configuration management for the initial manager. No default credentials or demo accounts are seeded. Do not use `make:filament-user` alone: it does not grant the staff flags required by this application.

Open [the local panel](http://localhost:8000/admin). The application binds to loopback by default. This checkout is currently enabled for LAN phone testing through a local .env override; see below. The database has no host-published port.

## First receipt

1. As manager, configure locations, sales channels, suppliers, categories and confirmed tax categories. Tax rates are not supplied automatically. Unallocated is seeded as a protected system destination.
2. Create catalogue products, or create books during receiving using online ISBN suggestions or manual details. ISBN-10 is normalised to ISBN-13.
3. Create an intake session with its actual receiving location and intended destinations/locations.
4. Open Receive Delivery. Select the session, then identify a book by camera/photo/manual ISBN or select a product. Review online details and cover choices; explicitly save any catalogue changes. Then enter quantity, cost/tax basis, condition, selling price and split. Explicitly confirm the price.
5. Save; inspect Stock Overview. Stock remains at its actual receiving location. Intended fair/website locations do not silently move it.

Repeated products require explicit additional-quantity confirmation and create another batch. Existing lines cannot yet be modified. Session configuration is editable only before its first receipt; versioned changes and closing are follow-up work.

See [integrated scanning](docs/scanning.md) for camera/photo behaviour, build instructions and current limitations.

## Checks

For optional sample products and audited stock, see [development demo data](docs/demo-data.md). It is seeded explicitly with `docker compose exec app php artisan db:seed --class=DemoDataSeeder`, never as part of normal setup.

The test bootstrap forces a separate PostgreSQL database before migration hooks run, rather than trusting container environment precedence.

```sh
docker compose exec app php artisan stock:prepare-test-database
docker compose exec app php artisan test --compact
docker compose run --rm --no-deps frontend npm test
docker compose exec app vendor/bin/pint --test app database routes tests
docker compose exec app composer validate --strict
docker compose exec app composer audit --locked
```

Run tests serially until per-process databases are configured. The suite includes two concurrent PHP processes retrying a receipt against PostgreSQL. This does not yet prove future sales/reservation locking.

`docker compose stop` stops these services without deleting data. Data is kept in named volumes. Do not remove database volumes to perform routine restarts.

## Documentation

- [Project instructions](AGENTS.md)
- [Documentation index](docs/README.md)
- [Decisions](docs/decisions.md)
- [Architecture](docs/architecture.md)
- [Intake workflow](docs/workflows/intake.md)
- [Stock workflows](docs/workflows/stock-operations.md)
- [Data model](docs/data-model.md)
- [Acceptance scenarios](docs/acceptance.md)
- [Roadmap](docs/roadmap.md)
- [Verification](docs/verification.md)
- [Invoice and customs planning (2026-11-01)](docs/plans/invoice-samples-2026-11-01.md) — proposals to revisit against the newer checkout on the other PC.
- [Standalone scanner proof](docs/scanning-proof.md)

The active checkout is now `C:/Users/Benjamin Smith/Repositories/Codex`. It was copied with local Git history from the original Documents workspace; the original was left untouched. The origin remote is https://github.com/BenjaminBot/wiseowl-stock.git. Container setup is for development; production hosting remains deferred.

## Online book lookup

Receive Delivery now shows asynchronous Open Library metadata and cover candidates after ISBN identification. Managers can apply details, select a cover, upload their own, or defer. Existing information remains unchanged until Save catalogue; unknown ISBNs can become provisional books without waiting for a provider. The Products catalogue-review filter finds books needing attention. Google Books requires GOOGLE_BOOKS_API_KEY and an explicit Search Google Books request for each book. See [book lookup](docs/book-lookup.md).

The custom Filament theme and both lazy components are built by npm run build. The frontend tools container mounts the installed vendor volume read-only so it can compile the official Filament theme.

## Current phone test access

The local .env sets APP_BIND_ADDRESS=0.0.0.0. On the current network, open http://192.168.0.225:8000/admin from a phone on the same Wi-Fi/LAN. Windows has a TCP 8000 inbound rule named Wise Owl Stock phone testing, limited to LocalSubnet on Ethernet. The address may change with DHCP. The database remains unpublished.

This is HTTP development access. Barcode-photo selection and manual input can be tested; live iPhone camera capture requires trusted HTTPS. To return to local-only access, set APP_BIND_ADDRESS=127.0.0.1 and run docker compose up -d --no-deps app. Remove the named firewall rule from an administrator PowerShell when no longer needed.

## Alternative edition covers

After ISBN lookup, choose **Find more covers** to check other editions linked to the same Open Library work. Exact-ISBN covers remain in their own group. Alternatives show edition details and a clear warning; compare them with your physical copy before selecting. Save catalogue remains explicit. Availability varies by book.

## Additional cover providers

Google Books and Brave Image Search are integrated but need local API keys for live results. Receive Delivery shows provider status and offers a separate Search web images picker with source links and explicit selection. Google Images/Amazon links support manual discovery without a key. See [provider setup](docs/provider-setup.md).

## Google alternative cover search

After choosing Search Google Books for the current book, Find more covers checks Google title/author matches as well as linked Open Library editions. Possible matches show source, author, edition ISBN and a review warning. Search web images (Brave) is a separate optional provider and does not use the Google key.

## Optional Google usage

Google Books is off for each newly scanned book. Choose Search Google Books only when wanted; Find more covers then includes Google for that book. Scanning another ISBN resets the choice. Cached results are reused, and applying/selecting candidates does not spend additional Google quota.

## Provider settings, authors and descriptions

Managers now have [Lookup Providers](http://localhost:8000/admin/lookup-providers) under Configuration, with separate automatic/manual/disabled modes for book information and images. Defaults remain Open Library automatic, Google manual and Brave images manual. Keys remain in local environment configuration. See [provider configuration](docs/lookup-providers.md).

Open Library author names and book descriptions are enriched in the background. Descriptions are editable in intake and Products; missing ones remain in the catalogue review filter. Use these details now works while enrichment is pending, opens the draft and confirms that fields were copied. Nothing is persisted until explicit catalogue save. A broader catalogue workspace redesign remains follow-up work.

## Catalogue language and binding

Intake and Products have searchable Language and Book format / binding dropdowns. Provider aliases resolve to consistent stored values; unmatched text is retained for review. Products includes language/format filters and sortable readable columns. See [lookup behaviour](docs/book-lookup.md).
