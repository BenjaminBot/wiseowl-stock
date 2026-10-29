# Scanning in Receive Delivery

Camera, barcode-photo capture and manual ISBN entry identify books in the real Filament receiving page. The page also shows [online metadata and cover choices](book-lookup.md); managers can explicitly create unknown books or update existing catalogue details.

## Use

1. Select an open delivery session.
2. Choose Scan with camera, take/select a barcode photo, or enter an ISBN and choose Find ISBN. A keyboard barcode scanner can type into the ISBN field and submit with Enter.
3. Recognition selects the matching catalogue product. Enter quantity/cost/tax/condition, explicitly confirm the selling price, split allocations, and save normally.

Recognition does not create products or add stock. ISBN-10 input is canonicalised to ISBN-13. Invalid ISBNs are rejected. Unknown identifiers open a provisional catalogue draft while retaining the current receiving item until an explicit switch.

Scanning the same selected book preserves the current quantity/price draft. Scanning a different book prompts Switch book / Keep current item before clearing per-item data. Switching keeps the delivery selection but resets quantity, prices, confirmation, and split quantities. A pending switch blocks receiving until resolved.

For a book already received in the session, show the cumulative received count. Additional stock still requires the existing additional-quantity checkbox; the complete Modify/Add/Cancel correction workflow remains future work.

Live scanning stops after a valid decode and ignores repeated frames. Following a successful receipt it resumes if live capture was used and has not been explicitly stopped. Photo selection is always a user action; it is not automatically reopened after a save. Photos and frames remain on-device; the server receives only an ISBN string.

## Connection and devices

Live camera access requires trusted HTTPS or desktop localhost, plus browser camera permission. An ordinary HTTP LAN IP cannot enable live iPhone video capture. Photo capture/file selection and manual ISBN entry provide alternatives without installing a certificate. Deployment/LAN exposure is a separate configuration choice; the application currently binds to localhost.

Test actual iPhones for focus, light, glare, orientation and image format. Large photos are capped at 20 MB; unsupported/unreadable images show a retry/manual-input message. JPEG/PNG is the most straightforward image test path. Desktop automated checks do not prove physical iPhone camera reliability.

Backgrounding, navigation and component teardown release active camera capture, including a delayed camera-start promise. Form inputs are disabled while decoding/identifying so they cannot race an item switch.

## Build and publish

The decoder is npm-locked and bundled locally, not loaded from a CDN. Filament's lazy Alpine loader serves it only on the receiving page.

```sh
docker compose run --rm --no-deps frontend npm ci
docker compose run --rm --no-deps frontend npm run build:scanner
docker compose exec app php artisan filament:assets
```

`npm run build` also builds the scanner after Vite assets. The tools-profile frontend service is invoked explicitly; it does not run alongside the application by default. Source lives under resources/js/scanner and resources/js/components. Generated resources/js/dist and public/js/app artifacts are ignored. Rebuild and publish after changes. Published URLs include a content hash for cache invalidation.

## Verification

```sh
docker compose run --rm --no-deps frontend npm run test:scanner
docker compose exec app php artisan test --compact
```

See [the verification record](verification.md). The generated barcode PNG in tests/fixtures is a repeatable browser photo fixture, not product cover art. The original standalone proof remains separate under outputs/scanner-proof.
