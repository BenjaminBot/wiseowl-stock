# Future phase: expected shipments and receipt confirmation

Agreed direction, 2026-10-28. Planned only; CSV import and invoice-based confirmation are not implemented yet.

## One receiving flow, two entry points

Staff can either scan/search a product during ordinary intake or select a pre-entered/imported invoice line and choose Confirm receipt. The invoice route opens the same receiving form and uses the same posting operation. It preselects the product and expected quantity/cost details, avoiding retyping and removing any requirement to scan the same item just to identify it again.

Staff confirm the actual received quantity, condition, tax/cost details, selling price and allocation split. Confirm receipt posts stock with the same validation, audit history and retry guarantees as scan-based receiving. A scan is optional in invoice mode and can identify the matching expected line quickly. Imported invoice lines are ready-to-confirm entries, not a separate stock-registration process.

Ordinary intake without any pre-entered shipment remains supported.

## Preparation

Enter invoice lines manually or import CSV. Proposed columns: ISBN/SKU, supplier description, expected quantity, unit cost, cost tax basis, optional tax category, invoice and supplier references. Preview/map/validate the file before confirming the import. Preserve the original imported quantities and cost values separately from actual receipts; avoid losing evidence by overwriting the expected values during receiving.

## Arrival and confirmation

An expected shipment can have multiple deliveries and receiving sessions. Each receipt is linked to its expected line when applicable and records only the copies actually accepted in that session. Show expected, previously received, received now and remaining quantities.

Example: expected 10; first delivery receives 6; 4 remain outstanding. A later session can confirm some or all of those remaining copies. A line may also remain entirely missing. Preserve partial receipts even if the shipment is ultimately closed short.

Support excess quantities, unexpected/different products, and explicit substitution notes. Do not count a different ISBN as fulfilment of the original product merely because it replaced it in the supplier's delivery. Record the actual product and any staff-confirmed relationship to an expected line.

## Final reconciliation and report

The delivery's final contents may legitimately differ from the invoice. Completion must support both Fully received and Closed with discrepancies; do not force invoice and received quantities to match. Distinguish still expected/on a later delivery from an unresolved shortage and a shortage accepted when closing.

Provide an invoice-versus-received report, per line and across all associated receipts, covering:

- Expected quantity and cumulative quantity actually received.
- Partially missing and entirely missing items.
- Excess quantities and unexpected products.
- Explicitly linked substitutions, with original and replacement products retained.
- Damage/rejections when the related stock operation exists.
- Invoice cost versus confirmed cost differences, with reasons.
- Receipt sessions, dates, operators, notes and closure/discrepancy status.

CSV export is the initial report proposal; PDF layout and extraction from PDF invoices are later choices. A report never posts stock again.

## Implementation boundary

Add an optional expected-shipment-line relationship to actual receipt lines through a new migration when this phase is implemented. Existing manual receipts remain valid with no expected line. Keep imported baseline data, revisions and received history distinguishable. Reuse ReceiveStock rather than implementing a second balance-writing path.

This phase complements fast intake and stock operations; it is separate from legal invoice accounting, supplier payments, and POS compliance.
