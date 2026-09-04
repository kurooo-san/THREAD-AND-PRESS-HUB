# Manual QR Payment — Thread & Press Hub

A no-gateway, no-business-permit payment flow: the customer pays via a static
**InstaPay / GCash / Maya** QR, uploads a receipt screenshot + reference
number, and an **admin verifies** the proof before the order is marked **Paid**.

No third-party payment API is used. This is intentional and sufficient for a
manual-verification workflow.

---

## What was built

| File | Purpose |
|------|---------|
| `migrate_payment_qr.sql` | Adds the `payment_submissions` table (record + audit). Non-destructive. |
| `payment-qr.php` | **Customer** checkout: shows amount + QR, collects proof & reference. |
| `serve-proof.php` | **Authenticated** proof-image server (owner or admin only). |
| `admin/payment-verification.php` | **Admin** queue: preview, approve/reject, duplicate-reference block, delete proof. |
| `admin/payment-settings.php` | **Admin** config: enable channels, set account details, upload QR images. |
| `includes/payment-config.php` | Constants, channel config (JSON), duplicate check, `getPaymentStatus()` chatbot hook. |
| `includes/payment-proof-handler.php` | Secure upload: real MIME check, EXIF strip, randomized filename. |
| `scripts/cleanup-proofs.php` | Retention cleanup (CLI). Deletes proof files after the retention window. |
| `seed_payment_demo.php` | Seeds one demo order in *Awaiting Verification* with a placeholder proof. |
| `storage/` | Protected storage root (`.htaccess` denies all direct web access). |
| `images/payment-qr/` | Public merchant QR images (the bundled `instapay-default.png`). |

Data lives in **MySQL** (the existing `orders` table already had
`payment_status`, `payment_reference`, `payment_proof`). The new
`payment_submissions` table holds the full submission history and verification
audit trail. QR channel display config lives in an admin-editable JSON file.

---

## Setup

1. **Run the migration** (creates the one new table):
   ```
   C:\xampp\php\php.exe -r "$c=new mysqli('localhost','root','','threadpresshub');$c->multi_query(file_get_contents('migrate_payment_qr.sql'));"
   ```
   or import `migrate_payment_qr.sql` in phpMyAdmin.

2. **Configure your QR channels** — log in as an admin and open
   **Admin → Payment Settings** (`admin/payment-settings.php`):
   - Toggle each channel (InstaPay / GCash / Maya) on/off.
   - Set the **display name**, **account name**, and **account number**.
   - Upload the merchant **QR image** for each channel (PNG/JPG/WEBP, ≤ 3 MB).

   A default InstaPay channel ships enabled using `images/payment-qr/instapay-default.png`
   so the flow works out of the box.

3. **(Optional) retention window** — set `PAYMENT_PROOF_RETENTION_DAYS` in `.env`
   (default **90**). See `.env.example`.

4. **(Optional) demo data**:
   ```
   C:\xampp\php\php.exe seed_payment_demo.php
   ```

---

## Customer flow

1. From an order awaiting payment, the customer opens
   `payment-qr.php?order_id={id}`.
2. The page shows **"Pay exactly ₱X,XXX.XX"**, the QR for the chosen channel,
   and step-by-step instructions.
3. The customer scans, **types the exact amount**, pays, screenshots the
   receipt, then uploads it + enters the **reference number** and ticks the
   consent box.
4. On submit: a `payment_submissions` record is created (status
   *Pending Verification*) and the order moves to **Awaiting Verification**.
5. If a payment is later **rejected**, the customer sees the reason on this
   same page and can re-submit.

## Admin verification flow

1. **Admin → Payments** (`admin/payment-verification.php`) lists everything
   *Awaiting Verification*: order, amount, channel, reference, submitted time,
   and a proof thumbnail (served only through `serve-proof.php`).
2. Click the thumbnail to open the full screenshot.
3. **Approve** → submission *Verified*, order *Paid* (a still-pending order is
   bumped to *Confirmed*); reviewer + timestamp recorded.
4. **Reject** → requires a reason (shown to the customer); the order returns to
   a payable state for re-submission.
5. **Duplicate-reference guard** — if the same reference number is already used
   on another order, the row is flagged and approval is **blocked** until the
   admin explicitly ticks *"I have verified this is not a duplicate."*
6. **Delete proof** removes the screenshot file but keeps the record for audit.

Every approve / reject / delete is written to the existing `audit_log` via
`logAudit()`. A "Recently reviewed" panel gives an at-a-glance audit view.

---

## Privacy & data handling (RA 10173)

- **Storage:** proofs live in `storage/payment-proofs/{orderId}/` with random
  filenames. A `.htaccess` (`Require all denied`) blocks **all** direct web
  access — confirmed returning HTTP 403. Files are served **only** through the
  authenticated `serve-proof.php`, which checks the requester is the order's
  owner or an admin.
- **EXIF stripped:** every upload is re-encoded through GD, which removes all
  embedded metadata (EXIF/GPS) and neutralises non-image payloads.
- **Real MIME validation:** the server sniffs the actual MIME type (`finfo` +
  `getimagesize`) — the browser-supplied type and file extension are never
  trusted. Limit: PNG/JPG/WEBP, ≤ 5 MB.
- **Consent:** the customer must explicitly consent before uploading, and a
  short privacy notice states what is collected, why, who sees it, and the
  retention period.
- **Retention:** `scripts/cleanup-proofs.php` deletes proof files
  `PAYMENT_PROOF_RETENTION_DAYS` after an order is completed/cancelled, keeping
  only the non-personal record. Admins can also delete a proof on demand.
- No file contents or personal data are ever written to logs.

### Scheduling the cleanup

Run daily via Windows Task Scheduler (or cron):
```
C:\xampp\php\php.exe C:\xampp\htdocs\thread-and-presshub\scripts\cleanup-proofs.php
```
Preview first with `--dry-run`.

---

## Chatbot hook (not built here)

`includes/payment-config.php` exposes a read-only function for the separate
chatbot module:

```php
require_once __DIR__ . '/includes/payment-config.php';
$status = getPaymentStatus($orderId);
// ['found'=>bool, 'code'=>'pending|awaiting_verification|paid|rejected|not_found',
//  'label'=>string, 'reason'=>?string]
```

It returns status only — never proof images or personal data. A commented
mount point in that file shows where the chatbot endpoint should call it. No
chatbot UI or logic is implemented here.

---

## Security checklist (verified)

- ✅ CSRF token on every form (`verifyCsrfToken()`).
- ✅ Prepared statements for all DB access.
- ✅ Output escaped with `htmlspecialchars(..., ENT_QUOTES)`.
- ✅ Auth gates: customer redirected to login; admin pages admin-only; proof
  handler returns 401/403/404 appropriately.
- ✅ Upload: real MIME + size cap + EXIF strip + randomized filename + non-web
  storage + traversal-safe path resolution.
- ✅ No secrets in client code; `.env` git-ignored, `.env.example` shipped.

---

## Known limitations & future enhancements

- **Static QR** means the customer types the amount manually — by design. A
  small percentage may pay the wrong amount; the admin panel flags any
  submission whose amount ≠ order total.
- **Manual verification** is human-paced (minutes–hours), not instant.
- For true automation, integrating a real gateway
  (**PayMongo / Maya Business / GCash for Business**) is a documented future
  enhancement. Those require **business registration / KYC** and API
  credentials — out of scope for this manual workflow.
- For maximum hardening, move `storage/` **outside** the web root entirely
  (currently it is inside the project but `.htaccess`-protected, which the
  brief explicitly permits).
- The older per-channel pages (`payment_gcash.php`, `payment_maya.php`) are
  left untouched; `payment-qr.php` is the recommended unified entry point and
  can replace them by updating the "Pay" links in `orders.php`.
