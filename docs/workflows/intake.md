# Intake workflow

## 1. Create or resume a session

Set supplier, delivery reference/date, receiving location, currency, intended sales destinations, and physical destination locations. A destination can be a website, a named shop, or a named bookfair. Configure the condition default (New) and website physical separation (On).

Managers can create a missing receiving or destination location using the plus action beside its selector. The modal uses the Locations form's name validation, creates the location immediately, and selects it without saving or leaving the intake session. Existing session inputs are preserved. Cancelling the session later does not remove the independently created location. Clerks can select existing locations but cannot create them inline; both action authorisation and the write callback enforce the same policy as the Locations screen.

With separation on, select the website's physical storage location. With separation off, website and other allocated stock may share a location; allocation accounting is unchanged. Configuration is reused across scans. Do not ask for locations for each title.

Proposed states: Draft -> Open -> Closed. Draft setup records no stock. Every confirmed line in Open posts stock immediately. Closing must coordinate with in-flight saves; a save cannot post into a session already closed. Reopening is an authorised, audited action.

If a destination location is a place stock has not physically reached yet, receive at the actual receiving location with the intended channel allocation, then transfer it later. Do not claim that stock is at a fair merely because it is intended for that fair.

Changing session defaults affects subsequent lines only. Existing movements retain their original locations and allocations.

## 2. Identify the item

Tap Scan book and request rear-camera access. On a recognised book barcode, pause scanning and open the form. Accept manual ISBN entry and keyboard scanner input through the same path. Validate ISBN checksum; normalise ISBN-10 and its equivalent ISBN-13 to avoid duplicate catalogue products. Invalid input gives a visible error without creating stock. Non-book goods use their identifier/SKU flow later; an arbitrary EAN must not be accepted as an ISBN.

Search the local catalogue first. For a new ISBN, create only a provisional draft until Save. Fetch external metadata and cover suggestions asynchronously. Tie each response to the current product/request and discard stale responses. Unavailable metadata permits a provisional product with internal SKU, identifier, and a provisional display name.

## 3. Resolve duplicate scans in this session

If the ISBN already has posted lines in the current session, show their total and a list of lines (condition, quantity, cost, price, destinations).

- **Modify:** choose a specific line. Show its current version; do not silently overwrite another user's update. Corrections must be represented in the stock history. Reject or guide corrections if copies have already sold, moved, or been reserved.
- **Add more:** start a new line with its own quantity, condition, cost, price, and split. Show the already-received quantity as context; never interpret the new quantity as a replacement total.
- **Cancel:** leave stock unchanged and return to scanning.

Matching an existing catalogue ISBN from a previous delivery is a normal receipt, not a session duplicate. Never add quantity automatically on scan.

## 4. Review metadata and cover

Show title, author, edition/format, and current selected cover when known. Show candidate thumbnails as they arrive, labelled with source and edition match. Permit select, own upload, or defer. A missing cover/description creates an incomplete catalogue task, not a failed intake.

Fetched suggestions do not overwrite existing catalogue edits. Applying suggestions to an existing product is explicit. The final application must distinguish a failed lookup, an empty result, and an outstanding request.

## 5. Confirm commercial details

- Enter cost per copy by default, labelled Excluding tax. Allow changing to tax-inclusive input or line total.
- Retain entered amount, tax basis, tax category/rate snapshot, and normalised cost. Validate exact decimal arithmetic and quantity before converting a line total.
- Select/confirm applicable purchase and sales tax categories; do not assume they are identical. Legal rates are configuration, not guessed constants.
- Show previous selling price as a clearly marked reference. Require explicit confirmation or a new price on every line, even for familiar ISBNs.
- Store selling price for the batch. Updating other existing stock is a distinct action with a scope preview and confirmation.
- Default condition to New; allow Almost new and Used. Mixed conditions use separate lines.

Selling-price tax basis, cost allocation rounding, and permitted precision must be resolved before Phase 2's monetary schema is finalised.

## 6. Quantity and destinations

Enter positive whole quantity received. Show only session-selected destinations, plus Unallocated. Enter a split for this scan. A calculated remainder can reduce typing but must remain visible and explicitly included in the save summary.

Example: receive 10; website 3; Christmas fair 5; unallocated 2. Persist exactly 10 physical units. A location and a channel are dimensions of those units, not extra quantities.

Reject fractional/negative quantities, over-allocation, and unexplained shortfalls. Permit zero for an individual destination. No automatic 'keep two online' rule.

## 7. Save and scan next

Before posting, show total, split, condition, confirmed price, cost basis, and destination locations. Save product/batch, receipt, movements, balances, and audit metadata atomically. No external API call belongs inside this database transaction.

Use one idempotency key per submitted operation. Retrying the same payload returns the original result; reuse of its key with a different payload is rejected. Disable repeated taps while saving, but rely on server guarantees as well.

On success, show the saved quantity and reopen scanning through a user-safe camera lifecycle. Recent entries stay accessible. On a failed/ambiguous response, keep the form and key; verify/retry without duplicating stock. Never announce success before server acknowledgement.

## 8. Finish delivery

Review posted lines and totals; flag incomplete catalogue work separately. Closing does not require all covers to be found. Closing records who completed the session and when. Stock counts and receipt totals remain distinct from current availability after later sales.

## Current online lookup implementation

After camera/photo/manual identification, the page loads online ISBN suggestions independently of the stock form. Managers explicitly apply details, choose/upload/defer a cover, and save catalogue changes. Unknown ISBNs can be created provisionally without a provider response. Existing stock drafts survive repeated scans and require confirmation before switching books. Catalogue saving does not receive stock; the normal quantity, batch pricing confirmation and allocation save remains separate. See [online book lookup](../book-lookup.md) for provider limits, review queue and permissions.

## Optional broader cover selection

If exact-ISBN images are unsuitable, tap Find more covers. Compare the clearly labelled alternative edition images against the physical book, select one and explicitly save the catalogue. The scan's ISBN, catalogue metadata and receiving draft remain independent of the alternative image's edition.

### Consistent language and binding

When applying a catalogue suggestion, recognised language/binding aliases select the corresponding dropdown entry. Confirm these against the physical copy. If a provider value cannot be matched, its original text remains visible; choose manually or leave the dropdown empty for later review. Unknown language/binding does not prevent receiving stock. Products can be filtered by these canonical selections.
