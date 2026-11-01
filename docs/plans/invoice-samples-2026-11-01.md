# Invoice and customs sample analysis

Date: 2026-11-01. Status: evidence and planning input, not implemented functionality or an approved implementation specification.

The user has a newer application version on another PC. This checkout was inspected at `a7a34f0`. Only Markdown documentation is being changed here. Reconcile these proposals with that newer version before changing code, schema or existing workflows. Do not assume gaps observed here still exist there.

Read this evidence first, then the proposed [tax and customs approach](purchasing-tax-customs.md) and [invoice import workflow](invoice-imports.md). These extend the previously agreed [expected shipment direction](../workflows/expected-shipments.md).

## Sources and handling

Seven supplier files were supplied, representing five invoices. The two 66 Books invoices each have both PDF and XLSX representations. The earlier two customs PDFs were also reviewed. Originals remain outside the repository. No source invoices, scans, extracted line listings, bank details, contact details or local attachment paths should be committed with this plan. The limited invoice references and aggregate findings below allow the owner to locate the private evidence later.

| Reference | Supplied filename | Evidence inspected |
| --- | --- | --- |
| H | HANNANTS Wise Owl Publications Sept 2026.pdf | Both pages, line table and totals; invoice 1385360 |
| M-P | Wise Owl Publications Inv No 61523 Malta.pdf | 17-page PDF, complete text extraction, visual first/last pages |
| M-X | Wise Owl Publications Inv No 61523 Malta.xlsx | Table 1, all populated product and summary rows, stored values/types and formulas |
| G-P | Wise Owl Publications Inv No 61524 - gozo.pdf | 20-page PDF, complete text extraction, visual first/last pages |
| G-X | Wise Owl Publications Inv No 61524 gozo.xlsx | Table 1, all populated product and summary rows, stored values/types and formulas |
| S-G | Sandcastle Books Ltd - Invoice 1326946.pdf | Three-page invoice, complete line extraction and totals |
| S-M | Sandcastle Books Ltd - Invoice 1326944.pdf | Three-page invoice, complete line extraction and visual footer |
| C-M | 261021024858.pdf | 26 scanned pages: mixed-goods declaration, additional sheets, tax sheets and totals |
| C-S | 261020025642.pdf | All four scanned pages: Sandcastle declaration, classification and tax sheet |

All five supplier PDFs contain selectable text. Both customs PDFs are scans with no useful extracted text. A supplier PDF having text does not make its tables reliable without reconciliation. The workbook inspection used read-only Python extraction; no source files were edited or recalculated.

## Invoice totals and quantities

GBP throughout this table. Quantities are source invoice units, not proof of physical arrival or of the number of individual books inside sets.

| Invoice | Product rows | Quantity | Sum of displayed line amounts | Stated invoice total | Difference: lines minus stated total |
| --- | ---: | ---: | ---: | ---: | ---: |
| Hannants 1385360 | 53 | 96 | 836.85 | 836.85 | 0.00 |
| 66 Books 61523, Malta | 1,091 | 11,535 | 22,619.20 | 22,619.10 | +0.10 |
| 66 Books 61524, Gozo | 1,290 | 6,154 | 11,894.16 | 11,892.88 | +1.28 |
| Sandcastle 1326944 | 65 | 1,231 | 2,263.15 | 2,263.15 | 0.00 |
| Sandcastle 1326946 | 65 | 610 | 1,119.00 | 1,119.00 | 0.00 |

The 66 Books PDF and XLSX ordered product identifiers and displayed line amounts agree for all 1,091 / 1,290 rows. Thus the differences above persist across formats; they are not simply an Excel-versus-PDF extraction difference. Their footer customs summary values sum exactly to the stated invoice totals, and footer quantities sum to the stated quantities. Keep all three levels: product line amounts, summary-group amounts and invoice total. The reason for the discrepancies is not established.

Neither workbook contains formulas. Values are supplier-supplied constants, not totals we can establish by recalculating spreadsheet formulas. This analysis cross-checks identifiers and amounts across formats, not every title, format, weight and quantity cell in the PDFs.

## Findings that affect imports

### 66 Books layout and money

- These XLSX files are print layouts, not simple rectangular data tables. They contain many merged cells, split currency symbols, numeric and textual amounts and shifting column positions within the same sheet. For example, Malta line amounts begin in Q and later appear as currency strings in O. Gozo quantities appear in I and later J. Detect sections and column roles rather than using one fixed set of column indexes for the entire file.
- Product rows end before customs summaries. Summary quantity/value rows repeat the same goods and must never become additional expected stock.
- Displayed unit cost multiplied by quantity frequently differs from the displayed line total: Malta has 939 such rows; Gozo has 957. Example M-X row 18: 7 copies at displayed GBP 1.60, displayed line amount GBP 11.17 rather than GBP 11.20. This may reflect underlying price precision or supplier calculations, but no cause is proved. Preserve both numbers; do not silently recalculate the invoice from displayed unit costs.
- Malta has eight explicit zero-quantity product rows and four blank-quantity rows. Some rows carry out-of-stock text. Preserve source status and distinguish zero from unknown. Blank quantity is an issue to review, not an automatic zero or an expected arrival. The quantity sums above include known quantities only; the blank rows have displayed dash amounts.
- Amount dashes are source tokens. Any supplier-profile rule interpreting a dash as zero must retain the original token and distinguish it from missing or failed extraction.
- Both XLSX date cells store 2026-05-10, whereas the PDFs display 05/10/2026 and the exporter declarations explicitly display 05/10/2026. In context 5 October is strongly indicated, but the two representations conflict. Show the conflict and require review; changing date display formatting cannot repair a stored May date.
- Supplier titles may be truncated. Preserve source text; any catalogue enrichment remains a separate suggestion and explicit save.

### Identifiers, origins, formats and units

- The heading ISBN includes ordinary EAN-like identifiers and alphanumeric supplier references such as PLAYSMARTBAG and LPNWE368523445. Do not pass every source identifier through ISBN validation or reject all non-ISBN products.
- Gozo contains 28 identifier groups repeated across multiple rows. Preserve invoice line identity and sequence even after matching rows to one product. Malta has no repeated identifiers in the inspected product rows.
- Gozo identifier 9781917349918 appears on rows 39 and 63 with different descriptions and commodity codes (9504908000 versus 4820103000). The notebook-like description and conflicting classifications require review, not automatic deduplication or tariff selection.
- The same identifier can have different source origins and costs across invoices. Origin and classification must remain historical line evidence, not a global product value overwritten by the latest import.
- Supplier formats include bf, cf, fl, bb, hb ++, hb ct, pack, box, BMS, novelty, stationery and variants. Only verified supplier aliases should map to canonical book bindings. Preserve modifiers and unknown values; do not assume every code describes a binding.
- Pack Qty, number of boxes and Qty Pick. are distinct. A set described as eight titles is not automatically eight stock units per invoiced unit. Preserve packaging facts and defer splitting sets until the owner confirms how those products are sold.
- Hannants uses supplier product codes, 8-digit commodity codes, alpha-2 origins and a Weight column. The 66 Books files mix 8- and 10-digit codes and alpha-3 origins. Preserve original code length and canonical country mapping separately. Confirm whether Hannants weights are per unit before allocating freight using them.
- Invoice labels such as Malta, Gozo and CIANA GOZO suggest intended destination. They do not prove receipt at a physical location or establish a channel allocation.

### Supplier VAT evidence

Sandcastle explicitly shows GBP 0.00 VAT on all lines and at invoice total. 66 Books shows a dash in its VAT total, delivery zero and no separate per-line VAT field. Hannants shows carriage zero and no separate VAT amount/rate breakdown in the supplied invoice. Store these distinctions; absent or dash VAT is not sufficient evidence to infer a legal exemption, zero-rating reason, or Maltese import VAT rate.

Hannants labels 14/09/26 as Date Of Order, not explicitly invoice date. Avoid mapping all document dates to the same field.

## Linking invoices to customs

### Sandcastle: exact aggregate reconciliation

The C-S declaration explicitly references supporting documents 1326944/1326946. Their supplier amounts sum to GBP 3,382.15, quantities to 1,841 and stated weights to 750 kg. All three agree with the declaration. Both invoices state code 4903000000 and China origin, consistent with its single customs group.

C-S records statistical value EUR 4,275.02, a tax-sheet base of EUR 4,275, A00 duty EUR 0 at 0%, B00 import VAT EUR 214 at 5%, total payable EUR 214. The supplier invoices show zero supplier VAT while Customs charges import VAT. These are separate events; both can be recorded accurately without applying import VAT to the supplier invoice.

At the declared exchange rate of 0.8595 GBP per EUR, GBP 3,382.15 / 0.8595 is approximately EUR 3,935.02. The statistical value is EUR 340 higher. Freight/insurance or another valuation adjustment is possible, but these documents do not identify its cause. Keep that amount unexplained pending supporting evidence. Statistical value, customs-duty base and VAT base must have distinct fields, even when similar here.

### Mixed goods: strong matches, incomplete invoice coverage

C-M records GBP 47,201.65 total invoice value and total taxes EUR 3,188: A00 duty EUR 21 and B00 import VAT EUR 3,167.

The supplied Hannants and two 66 Books invoice totals sum to GBP 35,348.83, leaving GBP 11,852.82 of declared value outside these supplied invoices. C-M item 38 is a book group with exactly that GBP value; that is an arithmetic association, not identification of a missing invoice or proof of its supplier.

Examples of group matches:

| Invoice evidence | Declaration group | Matching quantity / GBP amount |
| --- | --- | --- |
| 61523 footer, CHN printed books | C-M item 14 | 3,049 / 7,317.46 |
| 61523 footer, GBR printed books | C-M item 15 | 8,225 / 14,640.49 |
| 61524 footer, CHN printed books | C-M item 24 | 3,413 / 6,924.33 |
| 61524 footer, GBR printed books | C-M item 25 | 2,509 / 4,252.82 |
| Hannants German-origin book | C-M item 10 | 1 / 62.99 |

These matches support a suggested relationship for staff review, not automatic proof of every line-to-group mapping. Gozo line-level classifications do not reproduce every footer group: its detail includes classification conflicts and lacks some groups present in the footer. Do not allocate group taxes solely from unreviewed detail codes.

C-M book groups illustrate 0% duty and 5% import VAT; other groups show 18% VAT and some nonzero duty. For example, item 32 uses duty base EUR 144 at 4.7%, assessed duty EUR 7, then VAT base EUR 151 at 18%, assessed VAT EUR 27. These are observed assessments, not a future tariff table or universal rounding rule.

## Limits and follow-up evidence

The analysis reconciles the supplied invoice evidence and visible assessments. It does not establish VAT recovery eligibility, an accounting valuation policy, customs correctness, payment or physical receipt. No documents were uploaded to external extraction services.

Before implementation, resolve the 66 Books date/amount/classification differences, the EUR 340 valuation adjustment, missing mixed-declaration invoice coverage, the meaning of pack quantities, and the business's VAT recovery treatment. Keep these as review issues rather than inventing missing data.
