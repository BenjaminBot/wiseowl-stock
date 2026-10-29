# Acceptance scenarios

These specify future application behaviour; they are not test results. The standalone camera proof covers identification only.

| ID | Given / action | Required outcome |
| --- | --- | --- |
| IN-01 | Configure fair + website session, receive several titles | Destinations/locations persist; per-book quantities and price confirmation reset |
| IN-02 | Receive 10, split website 3/fair 5/unallocated 2 | Exactly 10 physical units; dimensions remain distinguishable |
| IN-03 | Split 11 against total 10, or leave an unexplained shortfall | Save rejected, input retained |
| IN-04 | Existing ISBN has an old price | Show reference and require confirmation; no stock written on scan |
| IN-05 | Same ISBN arrives at a new price/cost | New batch; original batch unchanged unless explicit repricing action |
| IN-06 | Receive new and used copies of one ISBN | Separate lines/batches and prices; one catalogue product |
| IN-07 | Scan ISBN already received as 5 in this session | Show total/lines and Modify / Add more / Cancel |
| IN-08 | Choose Add more and enter 2 | New line for 2; session total 7, with independent price/cost/split |
| IN-09 | Modify a posted line after some copies sold | Safe compensating correction or clear rejection; no negative stock/history erasure |
| IN-10 | Metadata provider times out or has no image | Receive valid stock; incomplete catalogue task; visible lookup status |
| IN-11 | Late lookup response arrives after scanning another ISBN | Current item and staff edits remain unchanged |
| IN-12 | Select/upload/defer cover | Selection persists appropriately; defer does not block receipt |
| IN-13 | ISBN-10 and its equivalent ISBN-13 scanned | Same catalogue identity, not duplicate products |
| IN-14 | Invalid checksum or non-book EAN scanned | Clear error; no receipt created |
| IN-15 | Website separation toggle off | Allocations may share physical location without duplicating stock |
| IN-16 | Save stock intended for fair but still in stockroom | Actual location remains stockroom until confirmed transfer |
| TX-01 | Response lost after successful save, then retry | Original operation returned; one receipt/ledger posting |
| TX-02 | Same idempotency key used with changed quantity | Conflict; no second write |
| TX-03 | Two operators sell/reserve the final copy simultaneously | Only one succeeds; no negative availability |
| TX-04 | Two operators correct the same line | Stale edit detected; no lost update |
| TX-05 | Session closed while another operator saves | Serialised outcome; no unacknowledged receipt into closed session |
| TX-06 | Failure midway through stock transaction | No partial receipt/balance/ledger or task remains |
| ST-01 | Sell website allocation through shop | Stock and website allocation decrease once; shop sale and manual external task recorded |
| ST-02 | Transfer 3 units to fair | Total unchanged; source/destination balances correct |
| ST-03 | Reallocate 2 available copies website -> shop | Physical location and total unchanged |
| ST-04 | Try to sell/reallocate another customer's committed copies | Rejected unless reservation is explicitly fulfilled/released |
| ST-05 | Fulfil/release/retry reservation | Availability/stock correct; terminal action cannot repeat |
| ST-06 | Mark damaged, then search availability | Still traceable as owned stock; excluded from sellable availability |
| ST-07 | Reconcile a count while sales continue | Intervening movements handled; no stale balance overwrite |
| ST-08 | Opening count includes already received copies | Cutover controls prevent double counting |
| AU-01 | User invokes restricted correction via direct request | Denied with no stock change |
| UI-01 | iPhone camera permission denied or dependency unavailable | Helpful status and usable manual ISBN entry |
| UI-02 | Camera reads same barcode over many frames | Only one form opens; no implicit receipt |
| UI-03 | Outside intake, scan ISBN | Search only, no quantity change |
| UI-04 | Narrow iPhone screen, keyboard open | Inputs/errors/save controls remain reachable |

## Verification by phase

Phase 1: static documentation consistency/link checks, experiment logic checks, desktop manual-entry review, and an explicit pending real-device sheet.

Phases 2-4: use the configured Pest/PHPUnit runner, factories and feature tests; test policies, money calculations, transactional rollback, and API/Livewire boundaries. Test concurrency on the actual database engine. Add scanner/UI tests where they verify meaningful behaviour rather than mirroring markup.

Phase 5: receive representative real deliveries, measure steps/time, record errors, test multiple users and weak connections, reconcile a stock sample, and demonstrate backup restoration.

## Implemented online lookup checks

- Scan an existing ISBN: display exact-match metadata and covers while retaining saved catalogue values until an explicit save.
- Apply a suggestion with missing author/publisher fields: preserve manually entered values for those fields.
- Select a cover, upload an own cover or defer: no stock operation occurs; keep/defer preserves an existing saved image.
- Unknown ISBN or failed provider: create a provisional book manually and continue through normal receipt confirmation.
- Change ISBN while a lookup is pending: ignore the old response and reject its suggestion token.
- Another user changes catalogue details: reject a stale draft instead of overwriting the newer data.
- Clerk attempts a direct catalogue write: deny it server-side; existing-product receiving remains available.

These scenarios have automated coverage; device speed and iPhone camera acceptance still require a real walkthrough.

## Alternative cover acceptance

- Find more covers preserves exact covers, staff edits and receiving quantities.
- Linked editions display Other edition warnings and source edition details; invalid edition URLs and duplicate image URLs are excluded.
- Selecting an alternative changes the draft only. Explicit catalogue save persists its edition provenance without changing the product ISBN or posting stock.
- No linked work, disabled lookup, provider failure and rate limiting leave upload/defer and existing choices available.
- Changing ISBN during the broader request ignores the old response.

## Cover recovery checks

A rate-limited or failed provider request retries once automatically. A second failure stops and preserves existing choices. Changing ISBN during the retry pause prevents another request. Successful empty results explain that linked editions have no additional images. Connection failures, HTTP 429 and malformed provider data remain distinct server outcomes.

## Wider web cover checks

Missing keys explain setup without an outbound provider request. API credentials never appear in result JSON. Web results stay separate from exact covers, have source links/review warnings and preserve ISBN/metadata/stock drafts. Candidate selection reads the current ISBN cache without issuing another paid search. Untrusted URLs/candidate keys are rejected server-side. Provider errors and stale responses preserve existing choices.

## Google alternative coverage

With Google configured and Brave absent, Find more covers returns Google title/author candidates. Unrelated titles and already-exact ISBNs are filtered. Cards display title-match warnings and source/edition information. Author resolution is cached/rate-limited; a source failure does not discard other results. Explicit selection/save retains alternative provenance without changing ISBN, metadata or stock quantity.

## Quota-control acceptance

With a Google key present, scanning/initial lookup and ordinary Find more covers must send no Google request. Search Google Books explicitly opts in. Only subsequent requests for that book include the Google flag. Moving to another lookup target clears it. Default results remain isolated from cached Google results; selecting/applying Google candidates must not issue additional Google calls.

## Enrichment and provider settings

Author-ID responses resolve to names without enabling Google. Late author/description responses cannot change another ISBN or staff edits. Descriptions are cleaned, editable and saved only after review; absent provider text preserves staff content. Use these details must work during pending description lookup and expose its result visibly.

Managers configure book information and images independently. Manual providers do not run automatically; automatic modes run only supported capabilities; disabled providers make no requests and do not expose old cached results. Non-managers cannot configure providers. Missing keys remain clearly reported without revealing secrets.

## Canonical language and binding

- Applying eng, en or English selects English and saves language=en; en-GB also groups as English.
- fre/fra and ger/deu converge correctly, and supported languages without alpha-2 codes remain selectable.
- Hardcover/hardback and paperback/softcover converge to their binding choices; generic BOOK and ambiguous composite text do not guess a binding.
- Unmatched provider values remain visible and persist separately with empty canonical fields; catalogue save and intake remain possible.
- Language and format Products filters find normalised records; sortable columns display readable labels.
- Direct intake catalogue writes reject non-list canonical values. Existing raw metadata migration retains unmatched originals and marks review, without touching stock or price.
