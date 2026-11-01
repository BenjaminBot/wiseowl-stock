# Proposed invoice imports and arrival confirmation

Date: 2026-11-01. Status: plan ideas for the newer checkout, not implemented or approved schema. The user's confirmed direction is to input supplier invoices, automate creation of an order/expected-arrival record and subsequently confirm what physically arrives. Implementation options below remain open.

Read [sample evidence](invoice-samples-2026-11-01.md), [tax/customs options](purchasing-tax-customs.md), and the existing [expected shipment workflow](../workflows/expected-shipments.md). Preserve its single receiving path, partial-arrival support and discrepancy report.

## Suggested user workflow

1. Upload invoice files or enter lines manually. Recognise supplier/layout, group PDF/XLSX copies of the same invoice, and show the original alongside extracted values.
2. Review invoice identity, dates/currency, source line quantities/costs, catalogue matches and reconciliation issues. Show missing, conflicting or uncertain values where staff can resolve them.
3. Explicitly approve creation of an expected order/arrival record. This records what was invoiced/expected and does not increase physical stock. A supplier invoice is evidence of billing, not proof an order was sent or accepted; do not mark it as a newly placed purchase order.
4. Associate invoices with shipments and optional customs/transport documents. Suggested Malta/Gozo destination labels require confirmation and remain separate from actual receiving location and exclusive channel allocation.
5. On arrival, select an expected line or scan/search to identify it. Open the existing receiving form with reviewed cost references and quantity context. Staff confirm actual quantity, condition, selling price and split. Keep the existing per-line selling-price confirmation requirement.
6. Post through the existing authorised stock operation, with an expected-line link and remaining-quantity concurrency checks at the same transaction boundary. Successful receipt changes inventory once.
7. Continue across deliveries. Finish fully received or close with explicit discrepancies, independently of customs payment and cost finalisation. Produce invoice-versus-received and shipment-charge reconciliation reports.

Possible screen labels: Import invoices, Review import, Expected arrivals, Confirm arrival, Customs and shipment costs. Final naming should fit the newer application's existing vocabulary. Confirm whether the owner wants a full pre-invoice purchase-order lifecycle later; do not add it merely because the word order was used.

## Import stages and boundaries

| Stage | Permitted result | Required boundary |
| --- | --- | --- |
| Extract | Staged source facts and issues | No physical stock or silent catalogue writes |
| Review | Approved interpretations and product matches | Authorised edits with source provenance |
| Create expectations | Invoice and expected lines | Transactional, idempotent approval; still no stock |
| Confirm receipt | Actual receipt/batch/balances/ledger | Existing receipt invariants plus expected-quantity locks |
| Reconcile charges | Reviewed customs and cost links | No additional stock posting |

Keep extraction and external metadata calls outside inventory transactions. For large files, use background extraction with visible status and resumable review. Posting an approved invoice should not hold a database transaction while parsing a PDF or contacting a provider.

## Format strategy

Recommended order: start with structured CSV/manual entry and a reviewed 66 Books XLSX adapter, then supplier-specific text PDF adapters for Sandcastle/Hannants, then broader PDF/OCR support. XLSX is preferable when available but still contains real source inconsistencies and requires reconciliation. A generic column mapper complements supplier adapters; it should not promise arbitrary print-layout extraction.

| Format/source | Proposed support | Specific requirements from these files |
| --- | --- | --- |
| CSV/manual | Baseline common import contract | Explicit currency and quantities; preserve raw identifiers and source lines |
| 66 Books XLSX | First structured supplier adapter | Section/column-shift detection, merged cells, text/numeric money, date conflicts, footer groups, repeated IDs and zero/blank quantities |
| Sandcastle text PDF | First simple PDF adapter | Repeated headers, 65 lines per sample, invoice-level currency/HS/origin/weight, separate VAT amounts and Malta/Gozo references |
| Hannants text PDF | Mixed-goods adapter | Supplier codes, origins, commodity codes, weights, carriage separate from products, Date Of Order separate from invoice date |
| Customs scan | Manual declaration capture with optional assisted OCR later | Exact tax codes/bases/amounts and MRN review; many product groups; no ISBN-level matching assumed |
| Other scanned invoices | Later assisted extraction | Explicit uncertainty review, source page links and total checks before approval |

Use registered supplier/layout adapters with a common staging output and versioned parser identity. Unknown layout changes should surface as issues rather than silently guessing. Generic or AI-assisted extraction can suggest facts but cannot approve costs, create stock or infer tax treatment. Any external document-extraction service needs a separately reviewed data-handling choice; local parsing is sufficient for the supplied selectable-text invoices.

## Staged data and provenance

Possible staging contract, to adapt to the newer code:

- Document: source file identifier/hash, media type, supplier, document kind, invoice reference, raw and interpreted dates with date roles, currency, stated net/VAT/gross/charges, parser version and approval status.
- Line: stable source identity/sequence, page or sheet/cell coordinates, raw identifier and scheme, description, supplied format, pack/box information, invoiced/ordered/shipped quantities as separately labelled source facts, unit of measure, displayed unit cost, exact line amount, VAT evidence and source status.
- Customs evidence: original/canonical origin, original code and precision, declared group summaries and weights, references to the customs records described in the companion plan.
- Review: catalogue match, effective cost interpretation, unresolved issue, approved override/reason, reviewer, version and time. Preserve both source and approved values.

Source classification snapshots must survive later catalogue changes. Supplier feed/import metadata can suggest catalogue details through the existing explicit save boundary; it must not automatically overwrite descriptions, bindings, origins or titles. Do not invoke paid provider searches for every imported row by default; respect provider capability modes and Google opt-in.

## Product and line matching

Validate and canonicalise real ISBNs; support EAN/GTIN where appropriate and supplier-scoped product references for non-books. Do not treat a 978 prefix as proof of product type, tax rate or a safe catalogue match. Reject invalid ISBN classification while retaining the raw supplier reference for review. Use internal products for matched stock, not supplier descriptions as unique identity.

An invoice row remains distinct even if another row has the same ISBN. Preserve rows with different price, classification, destination or source status. Scanning a product that matches multiple expected lines should show those choices and remaining quantities rather than consuming the first match automatically. Cross-invoice scans likewise need an explicit selection when ambiguous.

Unmatched goods can remain staged while staff match or explicitly create provisional catalogue products with appropriate permission. A supplier-specific code mapping must not collide across suppliers. Set/pack contents should not be exploded into individual products until a reviewed unit conversion and stock operation exist.

## Money and total reconciliation

For each document, compare displayed unit cost times quantity, supplied line amounts, footer groups, invoice totals and any credits/charges. Present differences at the correct level. Preserve zero, blank and dash distinctly; interpretation rules must be supplier-specific and reviewed.

The 66 Books examples require explicit treatment: their line amounts exceed invoice totals by GBP 0.10 / 1.28, while footer group amounts match those totals. Options are to request a corrected supplier invoice, approve line costs with a separately recorded reconciliation difference, or approve a documented allocation of the difference. Do not silently force totals to balance. Ordinary receipt may use reviewed provisional costs if permitted, but unresolved differences prevent claiming final invoice/cost reconciliation.

Keep original currency amounts and the selected EUR conversion/rounding policy. Receiving only part of an expected line must consume only the corresponding approved source quantity/value portion, leaving a traceable remainder. Repeated per-copy rounding must not consume more than the line's approved amount. Different selling prices remain independent of imported purchase cost.

Invoice VAT of zero, a dash and no VAT field are distinct evidence. Tax classification is reviewed separately from successful extraction. Customs charges are not invoice product lines or extra received units.

## Idempotency, revisions and partial arrival

- Use a content hash to detect the same file, and a reviewed supplier/document-type/invoice-reference identity to recognise different representations of the same invoice. PDF and XLSX hashes differ; importing both must not double expectations. Do not assume references are globally unique across suppliers or document types.
- Keep revisions and credit notes explicitly linked. A changed file with the same reference requires review, not replacement of already-received history. Define document identity/fiscal-year scope from actual supplier conventions rather than guessing.
- Approving an import uses a unique operation key, fingerprint and database uniqueness. Retrying the same approval returns the original result; changed content conflicts. Concurrent approvals cannot create two expected records.
- Track expected, accepted so far, received now, remaining and explicitly approved excess. Zero/out-of-stock source rows remain informational unless a reviewer creates a separate backorder expectation; blank quantities require resolution. Do not invent original ordered quantities from Qty Pick.
- Lock the expected line/fulfilment portions when posting receipts and checking remaining quantity. Two staff cannot independently receive the same remaining five as if ten were expected. Consistent lock ordering and safe retries must extend the existing receipt transaction.
- Preserve shortages, excesses, actual different ISBNs, substitutions, rejected/damaged goods and closures as separate facts with reasons. A substitution links actual and expected products but does not silently fulfil the original ISBN.
- If stock was already received manually, an authorised reconciliation may link it to an expectation without posting stock again. Re-linking must check product, quantity portions, existing links and concurrency and leave an audit trail. Never auto-post a second receipt to make an import match history.

## Document access and retention

Use private Laravel Storage with authorised downloads, bounded upload size/page/archive limits and background-job limits. Validate file types and isolate parsing. Treat file text as data: never execute macros, formula payloads, external links, embedded instructions or document-supplied actions. Preserve formulas as source text when relevant; evaluate only through a deliberate controlled path. Escape exported CSV cells that could execute as formulas in spreadsheet software.

Keep original private files, source hashes and review history according to an agreed retention/backup policy. Parser logs should avoid full invoice bodies, bank data and credentials. Repository fixtures should be synthetic/redacted and cover the observed shapes, not copies of private invoices. The existing rule against committing private supplier documents remains in force.

## Suggested delivery sequence

1. **Rebase the design on the newer application:** inspect schema/actions/screens/tests first; compare this evidence with current capabilities. Resolve owner/accountant questions in the tax plan and confirm terminology.
2. **Expected arrivals:** manual/CSV entry, reviewed catalogue matching, line identity, import approval and partial receipt links through the existing stock boundary. Clarify the minimal currency conversion needed before receipt posting.
3. **66 Books XLSX:** supplier adapter with source preview, paired-PDF reconciliation, section detection and all observed issues surfaced. Keep the two date interpretations and costs distinguishable.
4. **Sandcastle and Hannants PDFs:** adapters feeding exactly the same review contract. Do not build separate receiving paths per supplier.
5. **Shipment/customs recording:** multiple invoices/declarations, manual assessed amounts and reviewed mappings; no requirement to calculate tariffs automatically.
6. **Landed costs and late adjustments:** exact allocations, provisional/final reporting and immutable adjustment history, after valuation/recovery policy is confirmed.
7. **Optional assisted OCR and broader supplier layouts:** only after accuracy, access and review behaviour are established on representative examples.

Steps can be resequenced if the newer version already implements some capabilities. Avoid bundling full tax accounting, customs filing, supplier payment execution, automatic ordering or storefront/POS changes into this feature.

## Acceptance scenarios for implementation

| Scenario | Expected result |
| --- | --- |
| Import PDF then XLSX of invoice 61523 | One invoice/expectation with two representations and date conflict; no duplicate stock |
| Review 66 Books totals | GBP 0.10 / 1.28 differences visible; approval does not erase original amounts |
| Repeated identifier with conflicting commodity codes | Separate invoice rows and explicit review; no automatic tariff or deduplication |
| Out-of-stock and blank quantity | Informational zero distinct from unresolved blank; neither silently creates a positive arrival |
| Pack/box fields alongside Qty Pick. | No accidental multiplication of received units |
| Sandcastle invoices share one customs group | 1,841 source units and GBP 3,382.15 reconcile, supplier VAT zero and import VAT EUR 214 remain separate |
| Partial mixed-declaration invoice coverage | Unmapped GBP 11,852.82 remains visible and charges are not all spread over supplied invoices |
| Import succeeds, no arrival confirmed | Physical stock/batches/ledger unchanged |
| Expected 10, receive 6 then 4 | Two audited receipts, total 10, no repeat-import duplication |
| Two users confirm same remaining quantity | One valid fulfilment or explicit reviewed excess; no silent double receipt |
| Retry after ambiguous save response | Same posted result returned once with original operation key |
| Catalogue enrichment fails | Manual review/valid receipt still works; staff fields remain intact |
| Customs/freight arrives after stock | Linked cost revision only; quantity/history/selling prices unchanged |
| Link a previous manual receipt | Expectation reconciliation changes, stock does not post again |
| Direct unauthorised import/approval/receipt/download | Server rejects it regardless of hidden UI |

Use synthetic fixtures for parsing/reconciliation and PostgreSQL integration tests for approval/receipt concurrency. Extend lifecycle/UI tests for delayed extraction and stale review responses. Apply the repository's isolated-test-database and required verification commands when implementation begins. No implementation test pass is claimed by this planning document.
