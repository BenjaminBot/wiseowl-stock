# Proposed purchasing, VAT and customs approach

Date: 2026-11-01. Status: options for review on the newer PC checkout. No application changes are authorised or implemented by this document. Source findings are in [invoice sample analysis](invoice-samples-2026-11-01.md).

## The problem to solve

The user describes customs charges as part of purchasing and finds the per-item Purchase tax field confusing. The samples show why: supplier invoices, import declarations and physical receipts are separate documents/events with different currencies, groupings and dates. Supplier VAT can be zero while import VAT is due. Customs duty and VAT are different components of a payment to Customs.

In the inspected checkout, ReceiveStock uses purchase_tax_rate to divide VAT out of an inclusive supplier cost, and snapshots a separate sales rate. It has no shipment customs charge model. Receipt/batch currency currently covers both cost and selling price, with EUR selling-price labels. These observations must be checked against the newer version before designing migrations.

## Proposed concepts and labels

| Concept | Meaning | Suggested place in the interface |
| --- | --- | --- |
| Supplier invoice cost | Cost and currency actually shown by the supplier | Invoice and line review; receipt cost reference |
| Supplier VAT | VAT charged by the supplier, with amount and documented treatment | Invoice review; optional advanced receipt fields |
| Selling VAT | VAT applicable to the confirmed selling price | Receipt pricing and catalogue defaults, independently confirmed |
| Import VAT | VAT assessed at importation | Customs declaration and its groups |
| Customs duty | Tariff duty assessed at importation | Customs declaration and its groups |
| Freight, insurance and clearance | Separate costs, with their own documents and VAT where applicable | Shipment charges |
| Landed cost | Reviewed purchase cost plus applicable allocated costs | Cost breakdown/report, with provisional/final state |

Keep supplier VAT support for domestic and other purchases. Do not rename the existing purchase-tax field to Customs or feed a customs percentage into its inclusive-cost conversion. A clearer small interface change, if still needed in the newer version, is Supplier VAT and Cost includes supplier VAT?, while customs sits at shipment level. Do not hardcode a zero supplier rate for all imported purchases.

## Proposed relationships

Invoice, shipment, customs declaration and receipt should not share one identity:

- One import shipment may consolidate multiple suppliers/invoices. The mixed declaration is an example.
- One declaration can cover two invoices for different intended destinations. Sandcastle is an example.
- An invoice can arrive in multiple shipments and receiving sessions. A shipment can have more than one declaration or amendment.
- A customs group can cover many invoice lines. A line may need quantities/values mapped across groups or shipments. Use explicit portions when a split occurs, with conservation checks, rather than assigning its full quantity to each group.
- Confirmed receipt lines refer to actual accepted stock. Customs assessments and invoice documents can be recorded before or after receipt without posting stock.

Possible records, subject to newer-schema review: supplier invoice/lines, shipment/invoice portions, customs declaration/revisions, customs groups, group-to-line portions, shipment charges, cost allocation revisions and receipt links. Use focused Laravel actions, policies and migrations in the existing application; these are responsibilities, not mandatory class/table names.

## Evidence to retain

For invoices: supplier identity, invoice/reference/date with source type, source currency, stated totals, net/VAT/gross breakdown as actually provided, line quantities and displayed prices/totals, source row/page, document hash/version and approved interpretations.

For declarations: MRN and revision, assessment date/status, source references, invoice currency/value, customs exchange rate and direction, statistical value, and separate declared duty/VAT bases. At group level retain original commodity code, country of origin, preference/procedure information where supplied, quantity/weight units, tax code, rate, assessed amount, currency and source page. Store source facts separately from locally computed checks.

For each charge: supplier/agent/document, type, amount/currency, VAT breakdown, scope, allocation basis, and whether it is already included in another source value. A clearance agent's total may contain reimbursed customs VAT/duty plus its own fee: link the disbursements to the declaration rather than counting them twice.

Keep separate statuses for draft/assessed/superseded, paid/unpaid if payment tracking is later requested, and cost provisional/final. A document labelled awaiting payment is not evidence of payment or stock arrival.

## Currency and exact arithmetic

Store original GBP invoice values even if stock reporting and selling prices use EUR. A GBP supplier cost must never be relabelled EUR. Before invoice-based receipt posting, establish how a reviewed source amount becomes the EUR receipt cost accepted by the current inventory operation.

Retain separate exchange-rate snapshots for customs valuation and the chosen inventory-cost conversion. A bank settlement rate may be different again; full payment/FX accounting is outside this feature unless separately requested. Each snapshot needs currency pair, explicit direction, date, source, precision and approved override reason. Example: 0.8595 GBP per EUR means GBP / rate = EUR.

Use exact decimal arithmetic. Preserve displayed supplier unit prices and line amounts independently. Where approved line-total allocation is used, calculate effective unit cost at adequate precision and retain the exact line total and remainder. Show the difference from the displayed unit price. Do not silently repair the source or drop residual pennies across partial arrivals.

## Customs calculations and assessed amounts

Use the customs document's assessed amount as the recorded assessment. Calculate checks separately. In these samples, tax bases and payable amounts are shown in whole euros; do not generalise that presentation into a legal rounding algorithm.

Duty calculation depends on tariff, value and origin, rather than a universal rate on books. [European Commission: calculation of customs duties](https://taxation-customs.ec.europa.eu/customs/common-customs-tariff-cct/calculation-customs-duties_en).

Import VAT starts from customs value and includes applicable duties and qualifying incidental costs not already included. Keep bases distinct and prevent adding the same freight twice. A statistical value in box 46 must not automatically replace every tax base. [European Commission: taxable amount, importation and currency considerations](https://taxation-customs.ec.europa.eu/taxation/vat/vat-directive/taxable-amount_en).

Store zero duty separately from missing duty. Supplier VAT amount zero does not determine import VAT. Treat supplier commodity/origin data as evidence requiring review, not an automatic legal classification. Normalise origins for matching while retaining originals; retain code length and any reviewed 8-to-10-digit mapping. Do not invent a TARIC suffix or infer preferential entitlement from UK dispatch alone. Malta's tariff browser is a review aid, not an implemented integration: [MTCA duty calculator](https://edapps.mtca.gov.mt/customs-ucc-portal/tariffBrowser/CalculateComplex).

## Landed cost options

Recommended planning direction: show a transparent cost breakdown and allow receiving with provisional costs while assessed or transport charges are outstanding. Make finalisation a separate authorised operation.

| Component | Proposed treatment, subject to accounting confirmation |
| --- | --- |
| Supplier amount excluding recoverable supplier VAT | Base purchase cost, translated with the approved inventory FX snapshot |
| Customs duty | Separate allocated import cost |
| Freight/insurance/eligible clearance costs | Separately allocated to their applicable goods |
| Recoverable supplier/import VAT | Track separately from inventory cost |
| Non-recoverable VAT | Include only according to the confirmed accounting policy |
| VAT recovery not established | Show unresolved treatment; do not assume full recovery or finalise misleading margins |

VAT recovery depends on registration, business use and other conditions. The user's registration/recovery entitlement has not been established. Record a reviewed recovery status/portion rather than inferring it from possession of a VAT number. [MTCA: claiming input VAT](https://mtca.gov.mt/business-tax/vat1/vat-compliance/vat-refunds).

Allocation choices should be explicit:

1. Map assessed duty/import VAT to the relevant customs group first. Do not spread duty from toys over zero-duty books just because they share a container.
2. Within a reviewed group, value-based allocation is a candidate default for value-based tax reporting. Retain the actual assessed group total; reconcile rounding exactly.
3. Freight may suit weight, volume, pallet share or value. Use only available, confirmed measures. Group weights do not prove per-ISBN weights.
4. Permit documented manual allocation where source evidence is incomplete. Show what remains unallocated. A quantity-only split is an explicit option, not an automatic rule across unlike goods.
5. For rounding, a deterministic largest-remainder allocation is a proposed method: round shares to currency precision and distribute residual minor units in a stable documented order. It must reconcile exactly to the charge and remain reproducible.

Do not spread the entire mixed-declaration charge across the currently supplied invoices: invoice coverage is incomplete. Preserve an unmapped portion until the missing evidence is supplied.

## Late costs, shortages and corrections

Receipt history is immutable. Adding freight or a customs adjustment later must create a linked cost allocation/adjustment revision, not edit the historical receipt, resubmit ReceiveStock, change quantity or reset the selling price. Distinguish original supplier cost, latest approved landed cost and valuation adjustment history. Treatment of already-sold stock requires an agreed valuation/accounting approach before claiming accurate historical profit.

For partial arrivals, use approved invoice-line/charge portions and carry remaining cost/remainders forward. A shortage or damaged item does not automatically reduce a customs assessment. Track supplier credits and customs amendments independently, linking them to originals. Avoid allocating a whole invoice's charges to just the first partial delivery.

## Decisions needed on the newer checkout

1. Which of these concepts already exist in its current code and migrations?
2. What VAT recovery treatment has the accountant confirmed, including any partial/non-recoverable portion?
3. Which inventory FX rate/date is used, and what evidence supports the Sandcastle EUR 340 difference?
4. How should 66 Books line/summary discrepancies be approved or resolved? What tolerances permit review without pretending the invoice balances?
5. Which freight/clearance documents exist, and how should their charges be allocated across Malta/Gozo and mixed goods?
6. Must receiving proceed before costs are final? Recommended: yes, visibly provisional, with accurate quantity controls retained.
7. Which reporting is wanted initially: shipment reconciliation and estimated margin, or a broader valuation/accounting integration?

## Acceptance ideas

- Supplier invoice VAT zero and import VAT positive coexist without double-counting.
- Several invoices share one declaration; a single invoice can have multiple partial arrivals.
- No VAT-inclusive supplier cost is reduced by an import-VAT rate by mistake.
- Original GBP cost, reviewed EUR cost and customs FX remain traceable and distinct.
- Customs assessed totals survive check-calculation/rounding differences unchanged.
- Cost allocations sum to each charge exactly and never include goods outside their reviewed scope.
- Unresolved recovery, missing invoices and unmapped charges remain visible.
- Retrying an assessment/allocation save creates no duplicates; concurrent revisions reject stale approval.
- Late cost revisions never mutate posted stock history or change physical quantity, allocation or confirmed selling prices.

Official references were checked on 2026-11-01. They support the distinctions above, not a determination of this business's tax position. This document proposes inventory-recording behaviour, not tax-return filing or customs declaration automation.
