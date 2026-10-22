# Blue Ridge Scheduling

Patient scheduling for UNC Health Blue Ridge: a staff web app plus an AI
phone/SMS agent powered by [Retell AI](https://www.retellai.com).

- **Stack:** Laravel 12, Inertia + React, [shadcn/ui](https://ui.shadcn.com) components, SQLite.
- **Design rule:** scheduling logic is hardcoded in PHP. The AI never decides
  availability. It calls signed HTTP functions that run the rules and either
  succeed or explain why not.

```
Caller / SMS ──► Retell (STT · LLM · TTS · telephony)
                    │  signed POST (X-Retell-Signature)
                    ▼
              /retell/functions ──► SchedulingService ──► SQLite
              /retell/webhook   ──► conversations (summary only, no transcript)
              /retell/inbound   ──► dynamic variables (time, departments)
Staff browser ──► /dashboard, /appointments, /conversations (login required)
```

## Where things live

| What | File |
|---|---|
| Clinic hours, departments, capacity, lead time, holidays (**placeholders**) | `config/scheduling.php` |
| Slot generation, booking, cancel, reschedule | `app/Scheduling/SchedulingService.php` |
| Retell custom functions (verify, find, book, list, cancel, reschedule) | `app/Http/Controllers/Retell/FunctionCallController.php` |
| Signature check (port of Retell SDK `verify`) | `app/Retell/RetellSignature.php`, `app/Http/Middleware/VerifyRetellSignature.php` |
| Per-call identity verification state | `app/Retell/RetellSession.php` |
| Call/chat lifecycle webhooks | `app/Http/Controllers/Retell/WebhookController.php` |
| Outbound calls / reminder texts | `app/Retell/RetellClient.php`, `app/Console/Commands/SendAppointmentReminders.php` |
| Tool JSON for the Retell LLM | `app/Retell/ToolDefinitions.php` → `php artisan retell:tools` |
| Agent prompt + recommended Retell settings | `retell/` |
| Staff UI pages (shadcn Table, Card, Select, Dialog, Badge, ToggleGroup) | `resources/js/pages/{dashboard,appointments,conversations}` |

## Local setup (WSL)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # DEMO data only: fake patients
composer run dev                    # serves app + vite
```

Demo login: `test@example.com` / `password` (seeded). Self-registration is
disabled. Create real staff accounts with:

```bash
php artisan staff:create "Jane Doe" jane@example.org
```

Tests: `php artisan test`. On WSL, running from the Linux filesystem (`~/`)
is far faster than `/mnt/c`.

## Connecting Retell

1. **Create a Retell account** at https://www.retellai.com (new accounts get
   $10 of credit). **Sign the BAA** (free, self-serve at
   https://click-agreements.retellai.com) before any real patient data is used.
2. In `.env`, set `RETELL_API_KEY` to the key **with the webhook badge** (it
   signs every request). Optionally set `SCHEDULING_TRANSFER_NUMBER`.
3. Expose the app over HTTPS, because Retell can't reach `localhost`. For dev:
   `cloudflared tunnel --url http://localhost:8000` (or ngrok).
4. Create the agents. This also writes their IDs into `.env`:
   `php artisan retell:setup --url=https://<tunnel-host> --write-env`
   It creates the voice LLM and agent, plus the SMS LLM and chat agent, using
   `retell/prompts/*.md`, the tools from `config/scheduling.php`, webhook URLs,
   PII scrubbing and `everything_except_pii` storage. Re-running it updates
   the agents in place. Pick a voice with `--voice=<voice_id>`.
5. **Try it in the browser:** open **Test AI agent** in the app and click
   *Start browser call*. You don't need a phone number or Retell's ID check.
6. **To call a phone:** buy a number in the Retell dashboard ($2/month; it
   needs a payment method, and choose Twilio as the provider if you want SMS
   later). Set `RETELL_FROM_NUMBER`, re-run `retell:setup` to bind it, and
   complete Retell's KYC check, which is required before any outbound call.
   Then use *Call a phone* on the Test AI agent page.
7. **To text a phone:** US carriers require A2P 10DLC registration for the
   number (about 2–3 weeks; brand, campaign and SMS add-on fees apply). Once
   it's approved, set `RETELL_SMS_ENABLED=true` and re-run `retell:setup`.
   Retell can't send custom texts before that.
8. Keep **"Payload: args only" OFF** on the custom functions. The app needs
   the `call` object to tie verification to the call ID.

Every `/retell/*` route rejects unsigned requests (401). If `RETELL_API_KEY`
is unset, they return 503.

## How the AI stays inside the rules

- **Verification is server-side.** `verify_patient` stores the matched
  patient against Retell's `call_id` in cache. Book, list, cancel and
  reschedule refuse until that call is verified. The LLM can't skip it.
- **Lockout.** After 3 failed attempts per call, verification is locked and
  the agent is told to offer a transfer.
- **No enumeration.** A failed match never says which detail was wrong.
  Another patient's appointment ID looks the same as a missing one.
- **Booking re-checks the rules.** Times must be on the slot grid, within
  hours, past the lead time, inside the horizon, not a holiday, and under
  department capacity. The check repeats inside the transaction.
- **Idempotent booking.** Retell retries are keyed on call + patient + slot,
  so a retry can't double-book.
- **Change cutoff.** Patients can't cancel or reschedule within 24 hours
  through the AI. Staff can.

## PHI checklist before go-live

- [ ] BAAs signed with Retell, and with Twilio if you bring your own number.
      Confirm with UNC Health compliance and IT security.
- [ ] SQLite holds patient names, birth dates and phone numbers. Encrypt the
      disk or volume at rest, restrict file permissions, and back it up. For
      multi-server or high concurrency, move to MySQL or Postgres and add a
      row lock in `SchedulingService::book`.
- [ ] Staff SSO (Microsoft Entra ID via SAML or OIDC) instead of local passwords.
- [ ] HTTPS only. Set `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.
- [ ] `APP_DEBUG=false` and `LOG_LEVEL` at warning or higher in production.
      Database errors are logged without their bound values (see
      `bootstrap/app.php`), but debug mode still echoes SQL, including patient
      details, in error responses. Nothing logs request bodies; keep it that way.
- [ ] Retell: `data_storage_setting = everything_except_pii`, `pii_config`
      enabled, and retention days set. Webhooks still carry full transcripts,
      and this app deliberately stores only summaries.
- [ ] Replace every placeholder in `config/scheduling.php` with the real
      clinic rules.
- [ ] Have the patient-facing prompt and SMS wording reviewed: emergency
      language, TCPA consent, STOP/HELP.
- [ ] Decide whether appointments must sync with Epic. If they do, it
      replaces the local `appointments` table as the source of truth.
