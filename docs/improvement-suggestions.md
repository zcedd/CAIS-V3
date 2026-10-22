# CAIS improvement suggestions

Follow-on suggestions for the website and CAIS Field, grounded in the current Laravel + Inertia + React codebase and the Expo app under `mobile/`.

The items in [cais-feature-roadmap.md](cais-feature-roadmap.md) that are already built are not repeated here: duplicate and eligibility checks, document attachments, stock ledger, UNSPSC classification, requested vs released lines, parent programs with batches, workflows and queues, distribution approvals, public intake and tracking, and the eVerify flow.

## Website

### Quick wins

#### 1. Low-stock reminders

Stock movements already call `notifyIfNeeded()`. `stock:notify-low` is scheduled daily, alongside the other reminders, as a sweep for the case a movement does not cover: staff set or raise `low_stock_threshold` on stock that is already on hand. The sweep only looks at balances where `low_stock_notified` is false, so the daily run does not repeat an alert.

The scheduler still has to be running in production (`schedule:run` or `schedule:work`). `composer run dev` starts the queue listener and does not start the scheduler.

#### 2. Field-sync panel on the distribution

`distribution_sync_operations` records every mobile release, replay, and conflict. Nothing under `resources/js/pages/user/distributions` reads it. A Field sync panel on the batch and venue pages should show:

- pending operations per officer
- conflicts with the server reason
- device-captured time against the time the server accepted the record

That lets the office see a stuck phone before the batch is completed.

#### 3. Mobile device management

Sanctum tokens are issued per device (`IssueMobileToken`). There is no screen to list or revoke them. Add a Devices section on admin users: device name, last used, and revoke. That covers a lost phone.

#### 4. QR scan at the venue desk

`@yudiel/react-qr-scanner` is only used in the eVerify form. The venue desk still starts from a name search. Scanning the receipt QR (already public at `public.distribution-receipts.verify`) or a printed CAIS QR card should open the release directly.

### Features still open from the roadmap

#### 5. Audit tab

`LogsActivity` is on beneficiary, individual, item, and assistance item. It is not on assistance, program, fund, or distribution batch, and no page reads the activity log. `DistributionAudit` already exists. Surface it, and add an Audit tab on assistance, beneficiary, program, and fund.

#### 6. Household grouping

[household-family-grouping.md](household-family-grouping.md) is specified. There is no model, migration, or UI for it.

#### 7. Notifications beyond the bell

Every notification uses the database channel except `UserInvitedNotification`, which sends mail. Candidates:

- email digest for stale, assigned, and due items
- SMS or email to a public applicant when the tracked request changes status
- email when a distribution approval is decided

#### 8. Admin lookup maintenance

Admin covers users, workflows, and approval policies. Barangays, modes of request, document types, unit measurements, and sectors still need a seeder edit or a direct database change.

#### 9. Data privacy

The system stores birthday, mobile, PWD, 4Ps, ethnicity, and ID numbers. Still missing:

- role-based masking of mobile and ID numbers
- a retention schedule staff can see
- a consent flag on encode

### Backend

#### 10. Queue exports and PDF rendering

`Excel::download` in `AssistanceController` and `DistributionBatchController`, and Dompdf in `DistributionReceiptService`, run inside the request. There is no `app/Jobs` directory. Dispatch a job, store the file, and notify with a download link. Pre-render the receipt PDF when a transaction is finalized so the phone's "Open official receipt" call fetches a file instead of rendering one.

#### 11. Cache dashboard aggregates

`DashboardService` is about 1,170 lines and caches almost nothing. Deferred props already keep the page responsive, but every open recomputes the aggregates. Cache per department and filter set, and invalidate on status change. For the executive and administrator dashboards, a nightly summary table is enough.

#### 12. Beneficiary search

`GlobalSearchService` matches with `LIKE`. A MySQL `FULLTEXT` index on the name columns, or Laravel Scout, will hold up as `individuals` grows.

### Frontend

#### 13. Split the largest pages

These pages are each over 1,200 lines. Extract step or section components. The dashboard folder already does this.

| File | Lines (approx.) |
| --- | --- |
| `pages/user/beneficiaries/create.tsx` | 1,435 |
| `pages/user/distributions/venue.tsx` | 1,409 |
| `pages/user/assistances/show.tsx` | 1,346 |
| `components/user/beneficiaries/beneficiary-edit-drawer.tsx` | 1,339 |
| `pages/admin/workflows/workflow-editor.tsx` | 1,325 |
| `pages/user/distributions/show.tsx` | 1,280 |

#### 14. Frontend tests

Backend coverage is strong. There is no Vitest or React Testing Library setup. Start with pure logic: filter and sort helpers, the release-variance display, and workflow editor state.

## Mobile (CAIS Field)

### Reliability

#### 1. Resize photos before saving

`camera-capture.tsx` calls `takePictureAsync({ quality: 0.7 })` with no resize. The server rejects photos over `live_photo_max_kilobytes` (5,120). A current phone can exceed that, and the officer only learns it at sync time, as Needs attention, with no way to fix it on the phone. Resize to about 1,600 px with `expo-image-manipulator` before `persistPhoto`, and hash the resized file.

#### 2. Edit or discard a conflict

The user guide says Retry sends the same attempt again. "Quantity above what was approved" and "not enough stock" can never succeed on retry. Let the officer edit the queued lines down to the remaining amount, or discard the record with a reason, and keep a local note of the discard.

#### 3. Show a failed roster refresh

`reconcile()` in `mobile/src/sync/engine.ts` swallows every error. If the roster refresh fails after an upload, the phone keeps showing pre-sync statuses and says nothing. Show a banner: roster may be out of date, with the last successful refresh time.

#### 4. Minimum app version

There is no `expo-updates`, and `/me` does not return a minimum app version. Add `min_app_version` to `/me` so an old release form stops before it records a payload the server will refuse.

### Features

#### 5. QR scan on the roster

Roster search is text only. `expo-camera` already supports barcode scanning. Scanning a CAIS QR card or a previous receipt QR should open that person's release. This pairs with website item 4.

#### 6. Push notifications

There is no `expo-notifications`. Useful events: batch started or cancelled, a queued record refused by the server, roster changed by another officer.

#### 7. Short path for a single goods line

The six-step form is right for cash and mixed lines. For a one-item relief pack, a three-tap path (confirm name, photo, signature) shortens a long queue. Keep it behind the same permissions and the same evidence rules.

### Security and privacy

#### 8. App lock

The token is in SecureStore. Evidence photos, signatures, and the SQLite file are not encrypted, and the app does not lock on resume. Add a biometric or PIN lock (`expo-local-authentication`) and lock after inactivity.

#### 9. Delete evidence after a confirmed sync

`deleteEvidence` runs when the form rolls back and when Settings removes a batch. Synced photos stay on the phone until the batch is removed. Delete them once the server has accepted the operation. Waiting until the officer opens the official receipt is a reasonable option.

### Performance and code health

#### 10. Virtualize the roster

`mobile/app/(app)/batches/[id]/index.tsx` renders every row inside a `ScrollView`. A barangay roster of several hundred people will stutter. Use `FlatList` or FlashList.

#### 11. Split the release screen

`mobile/app/(app)/batches/[id]/items/[itemId]/index.tsx` is about 780 lines and holds all six steps. One component per step.

#### 12. Tests

The mobile project has no tests. These are pure and easy to cover first: `features/release/validation.ts`, `api/parse.ts`, `domain/roster.ts`, and the backoff in `sync/engine.ts`.

#### 13. Delta package refresh

`fetchPackage` downloads the whole roster every time. Add `updated_since` or an ETag so Refresh from server on a weak connection only moves changed items.

## Suggested order

**Website, this week**

Field sync panel → device revoke on admin users.

**Website, next**

Audit tab → queued exports and pre-rendered receipts → dashboard cache.

**Mobile, before the next venue**

Resize photos → editable conflicts → roster refresh banner → virtualized roster.

**Later, both**

QR scan (desk and phone) → push → app lock and evidence purge → household grouping and outbound mail or SMS.

## Open questions

- Which track is next: governance (audit, privacy, devices), field operations (mobile reliability, QR, push), or reporting and performance?
- Is SMS or email to beneficiaries in scope, and which provider (Semaphore, Twilio, agency SMTP)?
- Do beneficiaries already carry a printed CAIS ID or QR card?
- Is CAIS Field Android only, or is iOS a target? That changes background sync, biometric lock, and push.
