# CAIS Field user guide

CAIS Field is the phone app for releasing officers. Use it at the venue to record who received assistance, take the live photo and signature, and mark someone absent. The phone keeps those records when there is no internet and sends them to CAIS when the connection returns.

Start, schedule, approve, and complete a distribution on the CAIS website. The phone does not do those steps. It also cannot void a release, assign personnel, or reprint a receipt that was issued on the web.

You need a staff account that can release assistance (`distribution.execute`) and issue receipts. Sign in with the same email and password you use on the website.

## Before you leave the office

1. On the website, schedule the distribution and choose **Start distribution**. The batch must be **Scheduled** or **In progress**. A scheduled batch can be downloaded, but a release is accepted only after the distribution has started.
2. Install **CAIS Field** on the phone and allow the camera. The live photo must come from that camera. A picture from the gallery is rejected.
3. Ask the office for the server address. It is the web address of CAIS, for example `https://cais.example.gov`. On the sign-in screen, enter it in **Server address**.
4. Sign in while you still have a connection, then download the batch. After that, the roster stays on the phone.

The sample address `http://10.0.2.2:8000` is only for an Android emulator on the same computer as CAIS. A phone in the field uses the office server address.

## Sign in

1. Open **CAIS Field**.
2. Enter your email, password, and server address.
3. Choose **Sign in**.

If two-factor authentication is on, the next screen asks for a code. Enter the code from your authenticator app, or a recovery code, then choose **Continue**. A wrong code stays on that screen so you can try again. If the challenge expires, sign in again. The challenge lasts about five minutes.

The home screen is **Distributions**. The dark bar shows **Online**, **Offline**, **Checking connection**, or **Syncing**, plus how many releases are waiting or need attention. Tap it to open the sync queue. **Sync now** sends waiting records immediately. **Settings** opens your account.

## Download a batch

**Available to download** lists scheduled and in-progress distributions in your department that your account can release. Each card shows the title, batch code, status, venue, program, and schedule.

Choose **Download for offline use**. The batch moves to **On this device**, with the beneficiary count. Open it with **Open roster**.

**Refresh from server** downloads the roster again. Use it when someone else has released people on the website and you want those outcomes on the phone. Refresh needs a connection.

When the phone is offline, **Available to download** is empty and the batches already on the phone stay usable.

## Find a person

The roster shows the batch title, code, status, and venue, then the counts: served, partial, not started, waiting to sync, absent, need attention, and excluded.

Search by name, CAIS number, or assistance request ID. Tap a person to open the release.

| Status | Meaning |
| --- | --- |
| Not started | Approved, and no release is saved on this phone yet |
| Waiting to sync | A release or absence is saved on the phone and has not been accepted |
| Served | The approved quantity or amount was released |
| Partial | Some of the assistance was released. You can release the rest later |
| Absent | The person did not collect |
| Excluded | This person is not approved for release |
| Needs attention | The server refused the saved record. Open the sync queue and read the reason |

You cannot start another release while one is waiting to sync or needs attention. Served, absent, and excluded people are locked. A partial release can be continued up to the remaining quantity or amount.

If your account cannot release this batch, the screen says so and the form stays closed.

## Record a release

Choose **Release**. The form has six steps. **Next** moves forward. **Back** returns to the previous step. Nothing is stored until you choose **Save on this device** on the last step.

If your account cannot issue receipts, you can read the person but you cannot save the release.

### 1. Who is receiving

Read the privacy notice and the retention notice at the top.

- Choose **Beneficiary** or **Authorized representative**.
- Enter **Name of person receiving**.
- For a representative, enter **Relationship to the beneficiary** and **How was the authorization checked?**
- Choose how identity was checked: **Control number**, **Request reference**, or **Officer attestation**.
- Tick **I confirmed the recipient.**
- Tick **The privacy notice was acknowledged.**

### 2. What to release

Each line shows the item, whether it is goods, cash, or a service, the unit, and what is still remaining. The quantity and amount fields start at the remaining whole number.

- Use whole numbers greater than zero.
- Leave a line blank to release it later.
- If a line has both a quantity and an amount, enter both, or leave both blank.
- Entering less than the remaining amount makes the release partial. You will need a note before you can save.

A line with nothing remaining cannot be released again.

### 3. Live photo

Take the photo with the phone camera. **Retake photo** replaces it.

If your account is allowed to waive the live photo, and the camera cannot be used, enter **Camera waiver reason**. Without that permission, the photo is required.

### 4. Acknowledgment

Choose **Signature** and save the signature on the pad, or choose **Alternative acknowledgment** and write why the person cannot sign and what was used instead.

### 5. Witness and notes

A witness is optional. Choose **No witness**, or choose an officer from this department. The list is the one downloaded with the batch, up to 100 people.

If the release is partial, the screen asks for the reason. Enter it in **Notes**.

### 6. Confirm and save

Check the name, the lines, and that the photo and acknowledgment are present. Choose **Save on this device**.

The phone shows a **Provisional acknowledgment**: a short reference, the beneficiary, the lines, the time, and the photo’s SHA-256 when a photo was taken. That reference is not the official receipt number. The official number is issued when the phone syncs.

## Mark someone absent

Open the person and choose **Mark absent**. Enter **Why is the beneficiary absent?** and choose **Save absence on this device**.

You cannot mark someone absent after a release has been recorded for them, and you cannot release someone who is already absent.

The phone shows a provisional acknowledgment. After sync it shows an absence record number. An absence does not get a receipt.

## Sync

Waiting records upload one at a time, oldest first, when any of these happen:

- the phone reports a connection
- you bring CAIS Field back to the foreground
- you choose **Sync now**

A successful release becomes **Synced** and shows the transaction number (it starts with `DST`) and the receipt number (it starts with `AR`). Choose **Open official receipt** to download the PDF and share it. That PDF exists only after sync, and the phone needs a connection to fetch it.

A successful absence shows **Absence record** and the record number.

If the connection drops mid-upload, the record stays **Waiting**. The phone tries again and shows the attempt count.

**Needs attention** means CAIS refused the record. The photo and signature stay on the phone. Read the message, fix the cause, then choose **Retry**. The same attempt is sent again. Typical refusals:

- the distribution was not in progress
- the quantity or amount is above what was approved
- another officer already has an open release for this person
- there is not enough stock for a goods line

Stock is reduced when the release is accepted, not when you save it on the phone. Two officers can each save the last sack offline. The second upload is refused, and that phone shows **Needs attention**.

After a batch uploads, the phone downloads the roster again so served and absent counts match the server.

## Settings

**Settings** shows your name, email, and department.

**API base URL** is the server address stored on this phone. Change it and choose **Save server address**. Leave it as the sample value only when you intend to use the address built into the installed app.

**Downloaded batches** lists what is stored on the phone. **Remove from device** deletes that roster and its photos and signatures from the phone. It does not delete the distribution in CAIS. You cannot remove a batch that still has a release or absence waiting to sync. Sync those first, or resolve the ones that need attention.

**Sign out** returns to the sign-in screen. If releases are still on the phone, CAIS Field warns you. They stay on the device and upload only after the same account signs in again. Choose **Stay signed in** to keep working.

## What to do on the website

| Task | Where |
| --- | --- |
| Build, approve, and schedule the batch | Distributions on the website |
| Start the distribution so releases can be accepted | The batch, then **Start distribution** |
| Complete the batch after every approved person has an outcome | The batch, then **Complete** |
| Void a release, assign personnel, or reprint from the office | Venue desk on the website |

Released goods still reduce stock, and the assistance request moves to Delivered or Partially Delivered, when the phone’s record is accepted. Until then, the website does not show that release.

## Quick reference

| I want to… | On the phone… |
| --- | --- |
| Sign in | Email, password, server address, then **Sign in** |
| Enter a two-factor code | **Two-factor code**, then **Continue** |
| Save a roster for the venue | **Download for offline use** |
| Update a roster already on the phone | **Refresh from server** |
| Find a person | Roster search: name, CAIS number, or request ID |
| Hand over assistance | **Release**, then the six steps, then **Save on this device** |
| Record that someone did not collect | **Mark absent**, then **Save absence on this device** |
| Send saved records | The connection bar, or **Sync now** |
| Get the official PDF | After sync, **Open official receipt** |
| See why a record was refused | Sync queue, **Needs attention**, then **Retry** |
| Change the server | **Settings**, **API base URL**, **Save server address** |
| Take a batch off the phone | **Settings**, **Remove from device** |
| Leave the app | **Settings**, **Sign out** |
