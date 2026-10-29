# Stock operations

All operations require authorisation, actor, timestamp, stock batch, quantity, and a reason/reference as appropriate. Quantities must be positive inputs; the operation determines signed ledger effects. Retain history and use compensating records for corrections.

## Search and opening stock

Outside intake, scanning searches only. Show product, condition, batch price, location, allocated channel, committed quantity, and availability. A matching ISBN can have several batches; never hide that behind an ambiguous sale action.

Initial stock enters through an opening-count session with count date and attribution, not a fictitious supplier delivery. Avoid double-counting books already received after cutover.

## Manual sale

Scan or search, choose condition/batch and location, enter quantity, choose selling channel and source allocation, and confirm price. For multiple matching prices, require an explicit batch choice. Prefer a suggested FIFO batch only where staff can identify the physical copies and the price/condition matches.

Commit sale and stock decrement atomically. A reserved copy can only be fulfilled through its reservation or explicitly released first. This is inventory sales recording, not a fiscal till or tax-compliant receipt system.

### Shop sells website-allocated stock

Offer Sell from website allocation. Record the shop as the actual sales channel and decrement the website allocation at the copy's actual location. Create a pending manual Squarespace stock-update task in the same transaction until integration exists. Staff mark that external update complete; do not imply Squarespace has already changed.

The opposite case applies too: a manually recorded website order consumes website stock even before any automated connector exists. External availability can drift during manual coordination; show pending tasks clearly.

## Transfer location

Choose batch/source location, destination, quantity, and whether the channel allocation stays the same. A transfer changes location, never total owned stock. Check availability and reservations. Proposed MVP records a confirmed physical move; dispatch/in-transit/arrival tracking is deferred unless operationally necessary.

## Reallocate channel

Move available units from one channel to another, including Unallocated, without moving physical stock. Committed units cannot be reallocated. When website physical separation is enabled, prompt for the physical move if needed; record a location change only when actually confirmed.

## Customer reservations

Create a reservation against a batch/location/channel, making that quantity unavailable to others. Release/cancellation restores availability; fulfilment decreases on-hand and ends the commitment exactly once. Reservation expiry is explicit and configurable; do not silently expire an order awaiting payment without an agreed policy.

Proposed statuses: Active, Fulfilled, Released, Expired. MVP fulfilment is whole-reservation; partial fulfilment needs an explicit extension or separately split reservations.

## Damage, returns, and corrections

- Damage: remove from sellable availability, retaining stock in a non-sellable status/location until disposal or resolution.
- Supplier return/disposal: decrease owned stock with a reason and reference.
- Customer return: record separately from sale cancellation; inspect condition, choose batch/price and destination, then return it to sellable stock if appropriate.
- Correction: append a linked reversing/adjusting operation. Do not delete posted history, and do not allow negative balances to conceal stock that has already left.

## Counts and reconciliation

Record expected and counted quantities by location, batch/condition, and allocation where identifiable. Store a count-time snapshot. Either freeze affected stock briefly or reconcile intervening movements; never overwrite a live balance with an old count. Require reason and permitted-user approval before posting discrepancies as adjustments.
