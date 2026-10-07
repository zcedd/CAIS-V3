# CAIS feature roadmap

Suggestions for the Centralized Assistance Information System, grounded in the current Laravel + Inertia + React codebase.

## What already exists

- Department-scoped programs (flat list; no parent or batch), beneficiaries (individual and organization), and assistance requests
- Status timeline, bulk status update, transfer between programs, Excel export
- Assistance items encoded as requested lines; delivery can only mark those lines and cannot exceed requested quantity (partial delivery splits the row)
- Items catalog, funds linked to programs, demographics dashboard
- In-app stale-request notifications, guided tour, Fortify auth with 2FA
- Spatie roles/permissions seeded but not enforced in policies (department match only)
- Activity log on some models with no staff UI
- `everify_logs` table with no in-app verification flow

---

## Highest-impact product features

### 1. Duplicate and eligibility control

There is no fuzzy matching. Staff can create two records for the same person and encode the same person in multiple programs with no warning.

- Possible-duplicate alerts on create (name + birthday + barangay + ID number)
- Hard block or supervisor override when the same beneficiary already has an open request in the same program (or same parent program across batches)
- Cross-program history at encode time
- Program rules: max assistance per year, cooldown, item caps, PWD / 4Ps / solo parent / IP filters (apply at parent program, not only the current batch; caps count **released** items, including additional and substitute)

### 2. Document and proof of assistance

Statuses include Pending Documentation and Verified, but there is nowhere to attach files.

- Upload IDs, indigency certificates, delivery photos, signed acknowledgment
- Checklist per program (required docs before Verified / Delivered)
- Printable acknowledgment receipt / release form with CAIS number, items actually released (requested vs additional/substitute), and signature block
- Optional QR code on the receipt that opens the assistance profile

### 3. Real inventory, not just an item catalog

Items are names + units. Quantity is recorded on the request, but stock is not deducted.

- Stock on hand per department/program
- Allocation of stock to a program
- Auto-deduct on Delivered (including additional and substitute lines), restore on cancel/deny
- Low-stock alerts
- Batch / expiry for perishable relief goods

### 3a. UNSPSC classification for items

Do not import the full United Nations Standard Products and Services Code (UNSPSC) catalog as CAIS items. UNSPSC has tens of thousands of commodity codes; stuffing them into `items` would break department catalogs, program linking, and assistance encoding.

Use UNSPSC as a **classification** on each department item:

- Seed a read-only `unspsc_codes` lookup (segment → family → class → commodity, 8-digit code + title)
- Add optional `unspsc_code_id` on `items`
- Searchable picker on create/edit item (code or title), with the four-level path shown
- Keep local item name and unit of measurement as today (e.g. “Rice 25kg” classified as a UNSPSC food commodity)
- Prefer a curated subset for social assistance / relief / medical / education goods, with an option to search the full set
- Report delivered assistance and spend by UNSPSC segment/family for PhilGEPS, COA, and donor alignment

License: UNSPSC codes are maintained by GS1; confirm the version and usage terms before bundling a code list.

### _3b. Requested vs delivered assistance items_

Do not rewrite the original request when staff release something that was not applied for. Delivery today can only pick existing pending `assistance_item` rows, and quantity cannot exceed requested. Extras therefore cannot be recorded honestly. Editing the assistance is worse: it deletes all lines and recreates them as not received.

Keep one assistance, two facts: **what was requested** and **what was released**.

| Line                     | Meaning                             | Treatment                                                                        |
| ------------------------ | ----------------------------------- | -------------------------------------------------------------------------------- |
| Requested                | What they applied for               | Stays pending until that quantity is handed over                                 |
| Delivered (from request) | Fulfillment of a requested line     | Current split-row behavior (`is_received = true`)                                |
| **Additional**           | Given at release, never on the form | New received row, `requested_quantity = 0`, reason required                      |
| **Substitute**           | Replaces a requested item           | Requested line marked substituted (not delivered); new received row linked to it |

Three cases (do not mix them):

- **Additional item** — requested rice, also gave oil. Create a received oil line. Reason required (`leftover pack`, `on-site assessment`, `medical add-on`). Must still be a program item. Counts toward inventory, fund, and eligibility.
- **Substitute** — requested rice, gave noodles. Do not delete rice. Mark rice substituted, add noodles received, set `substituted_for_assistance_item_id`.
- **Over-quantity** — requested 2 rice, gave 3. Deliver 2 against the request plus 1 additional rice with a reason. Do not bump requested quantity to 3.

What not to do:

- Do not edit the request to add the extra, then mark delivered
- Do not put extras only in remarks
- Do not silently allow delivered quantity greater than requested on the same line
- Do not open a second assistance for the extra unless it is a different program

Walk-in pack distribution is different: if there was never a prior request, encode and deliver in one step (requested = delivered). That is request-at-counter, not an unrequested extra.

Schema (fits current `assistance_item` + `is_received` split):

- `origin`: `requested` | `additional` | `substitute` (default `requested`)
- `requested_quantity` (0 for additional)
- `substituted_for_assistance_item_id` (nullable)
- `fulfillment_reason` (required when origin is not `requested`)

UI:

- Delivered status drawer: existing undelivered requested items, plus **Add item** from the program catalog as additional or substitute
- Cap requested-line delivery at remaining requested quantity; extras go on a new line
- Assistance show: group **Requested** vs **Released**, with a short variance
- Acknowledgment receipt prints what was actually released, not only what was requested

Eligibility, inventory, and fund burn should count **released** lines, including additional and substitute.

### 4. Fund utilization, not just fund records

Funds have name, amount, year, and program links. Nothing compares budget vs actual.

- Cost per item (or per assistance)
- Allocated vs obligated vs disbursed vs remaining
- Warning when a program (or batch) is about to overspend
- Dashboard widget: burn rate by fund and by quarter
- Allot funds per batch; roll up remaining budget on the parent

### 5. Reporting pack for management and COA

Excel export exists only for a program’s assistance list.

- Assistance by barangay / sector / sex / PWD / 4Ps / IP
- Program accomplishment (requested vs delivered vs denied, TAT), rollup by parent and breakdown by batch
- Requested vs released item variance (additional, substitute, shortfall)
- Beneficiary master list with assistance history
- Inventory movement
- Assistance and spend by UNSPSC segment / family / commodity
- Fund utilization
- Stale / aging requests
- Export to Excel and PDF; save a filter set as a named report

### 5a. Parent programs with batches

Programs are a flat list today. Staff create near-duplicate programs (e.g. “Educational Assistance Q1” and “Q2”) and use transfer to move requests. There is no `parent_id` or batch.

Model a **parent program** (the scheme) with **batches** (the runs). Same idea as cycles, tranches, or FY/school-year editions.

Example: Educational Assistance 2026 → Batch 1 (Jan–Mar), Batch 2 (Apr–Jun), Batch 3 (Sep–Dec).

Keep using the `programs` table. Add nullable `parent_id` (self FK) and optional `batch_number` / `batch_name`.

| Level             | What it is                       | What lives here                                                                                        |
| ----------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------ |
| **Parent**        | The program used in reports      | Name, description, individual vs organization, default items, default custom fields, eligibility rules |
| **Batch (child)** | An open period staff encode into | Batch name/number, start/end, open/closed, funds and stock for that run, assistances                   |

Rules:

- Encode **only on a batch**, never on the parent
- One level only (parent → batch); a batch cannot have children
- Batch belongs to the same department as the parent
- Inherit `is_organization` from the parent (do not mix individual and org batches)
- Custom fields and items inherit from the parent; override on a batch only when that run differs
- Close a batch independently; parent stays active until the last batch closes
- Transfer stays as today, but only to a sibling batch under the same parent, with a reason on the timeline
- Duplicate / eligibility checks run against the **parent** (“already assisted this year in any batch”)

UI:

- Programs index shows parent cards (name, batch count, open/closed)
- Parent page: rollup KPIs, batch list, “Add batch” (copy items/fields from parent)
- Batch page: current program show (assistance table, encode, bulk status, export)
- Dashboard filter by parent, with optional batch breakdown
- Create parent first, with an option to create the first batch immediately

Standalone programs (no parent) can remain for one-off aid so existing records do not have to migrate on day one.

---

## Workflow and operations

### 6. Wire roles to the UI

Spatie roles (`super-admin`, `head`, resource packs, `supervisor`) exist. Policies already check Spatie permissions plus same department. Supervisor still cannot work across departments because URLs and policies only honor `users.department_id`.

- Encode vs verify vs deliver vs deny by permission
- Delete / transfer / bulk update as head-only (or matching resource role)
- Read-only supervisor across assigned departments — implementation spec: [supervisor-cross-department-overview.md](supervisor-cross-department-overview.md)
- Admin: users, departments, lookup tables

### 7. Assignment and queue

Statuses include Awaiting Review and Assigned to Team, but requests are not owned by a reviewer.

- Assign to a user
- My queue / team queue
- SLA clocks
- Extend stale notifications to email/SMS and to the assignee

### 8. Bulk encode and import

- Template import for beneficiaries and assistances
- Validation report (bad barangay, duplicate CAIS, missing required fields)
- Keep current row-by-row drawers for corrections

### 9. Household / family grouping

Relief is often household-based. Add a household record so staff can see that a family already received aid even if a different member applied.

Implementation spec: [household-family-grouping.md](household-family-grouping.md).

### 10. Public / kiosk intake (later)

Statuses like In Progress and Saved For Later look designed for a requester-facing form that does not exist yet.

- Barangay kiosk or SMS/web intake
- Tracking by CAIS number
- Staff still verify and approve inside CAIS

Do not build this until duplicate control and documents are solid.

---

## Beneficiary and identity

### 11. Finish identity verification

`everify_logs` exists, but there is no eVerify / PhilSys flow. IDs are stored as numbers only.

- Capture ID type + number + photo
- Optional PhilSys / eVerify lookup with verified / failed / skipped stamp
- Verification badge on the beneficiary profile

### 12. Stronger beneficiary profile

Make the show page the 360° view:

- All programs, items, amounts, dates
- Timeline of contacts
- Duplicate candidates
- Household members
- Do not assist / watchlist flag with reason

### 13. CAIS number as a real ID

Printable ID card / QR sticker for walk-in windows so encoding does not start from name search every time.

---

## Governance, audit, and admin

### 14. Activity log UI

Spatie activity log is on beneficiaries and items, but staff cannot see it. Assistance itself is not logged.

Add an Audit tab on assistance, beneficiary, program, and fund.

### 15. User and department admin

There is no in-app user management. Users belong to one home department. Supervisors will use the existing `department_user` pivot for extra departments (see [supervisor-cross-department-overview.md](supervisor-cross-department-overview.md)); assignment UI still belongs here.

- Invite / deactivate users
- Assign home department + role
- Assign supervised departments (pivot) for users with `department.supervise`
- Lookup maintenance: barangays, modes of request, statuses, units

### 16. Data privacy

The system stores birthday, mobile, PWD, 4Ps, ethnicity, and IDs.

- Role-based masking of mobile/ID
- Retention / archive policy
- Consent checkbox on encode
- Export/delete request handling if you serve the public later

---

## UX and product polish

| Gap                             | Suggestion                                                                                           |
| ------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Dashboard is view-only          | Click a KPI or barangay bar to open the filtered assistance list                                     |
| Export is program-only          | Dashboard-level export of the current filters                                                        |
| Notifications are database-only | Email digest of stale / assigned / denied                                                            |
| Tour exists                     | Role-based tours + What’s new after releases                                                         |
| Closed programs                 | Read-only archive with a reopen request; close per batch, archive parent when all batches are closed |
| Transfers                       | Require a reason and show on the timeline; restrict to sibling batches under the same parent         |
| Custom program fields           | Reuse field templates across programs; inherit from parent, override per batch                       |
| No print from show page         | One-click print profile / routing slip; receipt shows requested vs released items                    |
| Assistance items                | Group requested vs released; allow additional/substitute at delivery with a reason                   |
| Mobile encode                   | Compact encode flow for tablets in the field                                                         |

---

## Technical improvements

1. Enforce permissions in policies, not only department match.
2. Log Assistance and status changes (`LogsActivity` or dedicated audit UI with user + IP).
3. Jobs/queues for import, export, and notifications.
4. Item stock and fund ledgers as append-only tables.
5. UNSPSC as a lookup table + optional FK on items; do not treat UNSPSC commodities as stock items.
6. Parent programs via nullable `programs.parent_id`; encode only on child batches; no nested folders beyond one level.
7. Assistance items: keep request lines immutable; additional/substitute as received rows with `origin` and `fulfillment_reason`; never raise requested quantity to match an extra.
8. Proper beneficiary search (Scout/Meilisearch or stronger DB indexes).
9. Clean leftover `Project` naming on Item/Department relations.

---

## Suggested build order

**Now (fraud + accountability)**
Duplicate warnings → document attachments → printable acknowledgment → permission-based roles (including supervisor cross-department overview) → audit log UI

**Next (money and stock)**
Item inventory → requested vs delivered extras (additional/substitute) → UNSPSC classification on items → fund utilization → management/COA reports

**Then (scale operations)**
Parent programs with batches → Excel import → assignment queues / SLAs → household grouping → email/SMS

**Later (external channels)**
PhilSys/eVerify → public tracking / kiosk intake
