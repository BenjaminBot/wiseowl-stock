# Mobile screen specification

Design for a narrow phone screen first. Use labelled inputs, 16px-or-larger input text, approximately 44px minimum touch targets, numeric/decimal keyboards, visible focus, and a save area that is not hidden by the iPhone keyboard or safe area. Desktop uses the same workflow with more space.

## A. Session list and setup

List open/recent sessions with supplier, delivery reference, operator(s), and received totals. Create/resume opens configuration: actual receiving location; intended destinations and destination locations; website separation toggle; condition default; cost basis; currency. Session-wide defaults are obvious and editable with future-lines-only notice.

Implemented: receiving and intended destination location selectors have a native Filament plus action for managers. A Create location modal validates a required unique name and automatically selects the new record. The unsaved intake form remains in place. Inline creation is denied to users without location-create permission.

## B. Ready to scan

Show session identity, destination summary, received totals, Scan book, manual ISBN, and recent entries. Camera access begins only on a user action. Manual input remains available if permission is denied. Keyboard scanners submit through the same lookup handler without changing context.

## C. Camera

Rear camera preference, wide barcode framing guide, instructions to include the entire barcode, and a visible Stop/back control. Decode only book EAN-13 for the initial experiment. On valid ISBN: latch the result once, stop/pause decoding, show form. On non-book/invalid code: visible explanation and continue. Release tracks when navigating away or backgrounding; resume via an explicit action if needed.

## D. Item entry

Order on mobile:

1. Title/ISBN and condition.
2. Visible horizontal cover candidates and upload/defer controls.
3. Quantity received and per-destination split with visible Unallocated remainder.
4. Unit/line cost, tax basis/categories, selling price, and required price confirmation.
5. Receiving/destination location summary from session defaults.
6. Save & scan next and Save & return.

Never populate quantities from the previous book. Reset price confirmation for every new/additional line. Previous prices are labelled suggestions. Editing any price after confirmation resets that confirmation. Retain session defaults without retaining stale per-book choices.

Metadata loading or failure leaves quantity/price controls usable. No provider response may replace a staff-edited field. No image selection is required to save.

## E. Duplicate entry

Show ISBN, already-received total, and existing lines with condition/price/split and operator. Buttons: Modify existing entry; Add more copies; Cancel. Modify chooses the exact line when there are several. Distinguish additional quantity from replacement total in both labels and save summary.

## F. Save/recovery states

| State | UI behaviour |
| --- | --- |
| Invalid | Inline field errors plus summary; preserve input and focus first error |
| Saving | Disable duplicate submission; retain draft and idempotency key |
| Success | Server receipt identifier, quantity and split; next scan available |
| Network failure | Keep input; retry/verify operation with same key |
| Concurrent edit | Show current server entry and user's attempted values; require deliberate resolution |
| Session closed elsewhere | Keep attempted input; allow new/resumed session, never silently change destination |
| Lookup unavailable | Explain missing metadata and permit receipt with provisional identity |

## G. Recent lines and completion

Recent entries show received quantity, destination split, price, condition, operator, and incomplete catalogue status. Corrections are explicit actions. Session completion review totals receipts, not current stock. Shared-session screens refresh/version-check before destructive corrections.
