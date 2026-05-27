# mySalesBuddy — Project Handover

**Date:** 2026-05-27
**Author:** Candidate
**Stack:** Laravel 11 (PHP 8.3) + Vue 3 (TypeScript) + PostgreSQL 16 + Redis 7

---

## 1. Executive Summary

mySalesBuddy is a sales-call coaching platform. Sales reps connect a Google Meet
URL, an AI bot (Recall.ai) joins the call and records, the transcript is
processed by OpenAI to produce structured coaching feedback (strengths,
improvements, objections, next steps, talk-time ratio). Reps can rate coaching
sections, export PDFs, and manage notetaker / prompt / notification preferences.

The codebase is structured around a Laravel 11 API serving a Vue 3 SPA. All
backend writes go through transactional Actions/Services. External integrations
are gated by feature-specific service classes (RecallAiService, OpenAiService,
GoogleOAuthService) which can be faked in tests.

---

## 2. Architecture

```
┌──────────────────┐      ┌──────────────────┐      ┌───────────────────┐
│   Vue 3 SPA      │      │  Laravel 11 API  │      │  PostgreSQL 16    │
│  (Pinia, Echo)   ├──────┤  (Sanctum SPA)   ├──────┤  (uuids, JSONB)   │
└──────────────────┘      └─────────┬────────┘      └───────────────────┘
                                    │
                  ┌─────────────────┼─────────────────────┐
                  │                 │                     │
            ┌─────▼─────┐    ┌──────▼─────┐        ┌──────▼─────┐
            │ Recall.ai │    │  OpenAI    │        │  Redis 7   │
            │  webhooks │    │  GPT-4o    │        │ queue+cache│
            └───────────┘    └────────────┘        └──────┬─────┘
                                                          │
                                                   ┌──────▼─────┐
                                                   │  Horizon   │
                                                   │  workers   │
                                                   └────────────┘
```

Webhooks from Recall.ai arrive at `/api/webhooks/recall` and are verified by
the `VerifyRecallWebhookSignature` middleware (Standard Webhooks / Svix HMAC).
Each event type fans out to a queued job (`ProcessBotInCallJob`,
`ProcessTranscriptReadyJob`, etc.) so the webhook returns 200 quickly.

Realtime status updates are broadcast over Pusher / Soketi to the SPA via
Laravel Echo.

---

## 3. Phase Summary

| Phase | Scope | Tests |
|-------|-------|-------|
| 1 | Schema & migrations (UUIDs, enums via CHECK constraints, JSONB) | n/a |
| 2 | Auth, validation, exception handler, throttle messages | 14 |
| 3 | OAuth (Google Calendar), encrypted token storage | 13 |
| 4 | Recall.ai bot dispatch, one-bot-per-URL invariant | 16 |
| 5 | Transcript processing, OpenAI coaching pipeline, PDF export | 32 |
| 6 | Notifications (in-app + email), preferences, ratings | 21 |
| 7 | Webhooks (Standard Webhooks signature scheme), idempotency | 18 |
| 8 | Frontend Vitest harness + backend coverage tooling | +119 vitest |
| 9 | OWASP Top 10 hardening, performance audit, handover | +48 |

Final counts: **202 PHP tests / 487 assertions**, **119 Vitest tests**.

---

## 4. Ambiguity Resolutions

| Topic | Spec ambiguity | Resolution |
|-------|----------------|------------|
| Recall.ai signature method | Spec referenced `X-Recall-Signature: sha256=…` | Implemented Standard Webhooks (Svix) HMAC-SHA256 via `webhook-signature` header (`v1,<base64>`), since Recall.ai migrated to Svix-style delivery. Middleware also accepts legacy `svix-*` headers. |
| Meeting scope mutation | Spec did not state whether scope can change after creation | Locked: no PATCH endpoint exists. `scope` is only writable on `POST /api/meetings`. Enforced by test `test_meeting_scope_cannot_be_changed_after_creation`. |
| Coaching trigger frequency | Spec did not bound retries | Manual trigger is allowed any number of times when meeting is `ready`; each call produces a new `CoachingAnalysis` row and the latest is returned by `/coaching` GET. |
| PDF disk in tests | Spec hardcoded `s3` | StorageService reads `config('security.avatar_disk')` / `config('security.pdf_disk')` (default `s3` in production). Tests fake `s3` explicitly. |
| CSP `unsafe-inline` for scripts | Spec asked for strict CSP | Kept `'unsafe-inline'` and `'unsafe-eval'` in `script-src` for Vite dev/manifest compatibility. **Production must replace with nonces** — see section 8 below. |
| `preventLazyLoading` in production | Default is "off" | Enabled in every environment except production via `App\Providers\AppServiceProvider::boot()`. Production keeps lazy loading on for safety; CI runs with it disabled. |

---

## 5. Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_KEY` | yes | — | Generated via `php artisan key:generate` |
| `APP_URL` | yes | `http://localhost` | Base URL used in OAuth redirects and CSP |
| `DB_CONNECTION` | yes | `pgsql` | PostgreSQL required (uses JSONB + pg_trgm) |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | yes | — | DB credentials |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | yes | `127.0.0.1:6379` | Queue + cache + broadcasting |
| `QUEUE_CONNECTION` | yes | `redis` | Use `sync` only in tests |
| `BROADCAST_CONNECTION` | yes | `pusher` | `null` in tests |
| `CACHE_STORE` | yes | `redis` | `array` in tests (Laravel 11 name — not `CACHE_DRIVER`) |
| `SESSION_DRIVER` | yes | `redis` | `array` in tests |
| `FILESYSTEM_DISK` | yes | `s3` | `local` in tests |
| `RECALL_API_KEY` | yes | — | Recall.ai bearer token |
| `RECALL_SIGNING_SECRET` | yes | — | `whsec_<base64-key>` — base64-decoded for HMAC |
| `RECALL_BASE_URL` | no | `https://eu-central-1.recall.ai/api/v1/` | EU region by default |
| `OPENAI_API_KEY` | yes | — | OpenAI bearer token |
| `OPENAI_MODEL` | no | `gpt-4o` | Override per environment |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | yes | — | Google Calendar OAuth credentials |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | yes (prod) | — | S3 for avatars + PDFs |
| `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_ID` / `PUSHER_HOST` / `PUSHER_PORT` | yes | — | Realtime broadcasting |
| `SANCTUM_STATEFUL_DOMAINS` | yes | `localhost,127.0.0.1` | Cookie-based SPA auth |
| `SECURITY_LOG_LEVEL` | no | `info` | Threshold for `security` channel |
| `SECURITY_LOG_DAYS` | no | `90` | Retention for failed-auth log file |

---

## 6. Security Posture (OWASP Top 10 mapping)

| OWASP | Mitigation |
|-------|------------|
| A01 Broken Access Control | `Gate::policy()` registered for `Meeting`, `CoachingAnalysis`, `AppNotification` in `AppServiceProvider`. All ownership checks use `$this->authorize(...)` in controllers. Covered by `tests/Feature/Security/AccessControlTest.php`. |
| A02 Cryptographic Failures | OAuth tokens encrypted at rest via `App\Casts\EncryptedString` (Laravel `Crypt`). Passwords hashed with bcrypt (cost 12 in prod, 4 in tests). `tests/Unit/Security/EncryptionTest.php`. |
| A03 Injection | Eloquent / query builder used everywhere — no raw concatenation. TranscriptSegment search uses parameter binding (`ILIKE ?`). `tests/Unit/Security/SqlInjectionTest.php`. |
| A04 Insecure Design | One-bot-per-URL rule enforced inside DB transaction with `lockForUpdate()` in `App\Actions\DispatchBotAction`. AuditLog `save()` rejects updates (immutable). Meeting scope cannot be mutated after creation. |
| A05 Security Misconfiguration | `App\Http\Middleware\SecurityHeaders` appends X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (https only), and CSP. Registered globally in `bootstrap/app.php`. |
| A06 Vulnerable Components | `composer audit` and `npm audit --audit-level=moderate` exposed as scripts and as a `dependency-audit` job in CI. |
| A07 Auth Failures | Rate limits on login (20/min), register (20/min), oauth/google/redirect (10/min), avatar (20/min), prompt store (20/min). Failed login attempts logged to `security` channel via `App\Listeners\LogFailedAuthAttempt`. |
| A08 Software/Data Integrity | AuditLog model rejects updates. Webhook signatures verified with HMAC-SHA256 timing-safe comparison + 5-min timestamp tolerance. |
| A09 Logging Failures | Dedicated `security` log channel (daily, 90-day retention) in `config/logging.php`. Failed-auth listener writes IP, email, user-agent on every `Illuminate\Auth\Events\Failed`. |
| A10 SSRF | `App\Rules\AllowedMeetingUrl` whitelists hosts from `config('security.allowed_meeting_hosts')`, rejects localhost / RFC1918 / link-local / IPv6 ULA / cloud metadata IPs. Applied to `StoreMeetingRequest::external_meeting_url`. `tests/Unit/Rules/AllowedMeetingUrlTest.php`. |

---

## 7. Performance

* All hot-path queries are covered by composite indexes — see
  `tests/Feature/Performance/IndexVerificationTest.php`. Critical indexes:
  * `meetings (user_id, status)`, `(user_id, scheduled_at)`, `(user_id, deleted_at)`, unique `recall_bot_id`
  * `transcript_segments (meeting_id, start_ms)` + GIN trigram on `body`
  * `notifications (user_id, created_at DESC)` and `(user_id, read_at)`
  * `audit_log (user_id, event_type)` and `(entity_type, entity_id)`
  * `coaching_analyses (meeting_id, created_at DESC)`
* `Model::preventLazyLoading()` is enabled outside production via
  `AppServiceProvider::boot()`. All controllers eager-load relations they
  consume:
  * `MeetingController::show` → `transcriptSegments`, `latestCoachingAnalysis`
  * `CoachingController::show` → `ratings` on the latest analysis
  * `CoachingController::trigger` → `loadMissing('user')` on the meeting before audit logging
  * `CoachingAnalysisPolicy` calls `loadMissing('meeting')` before owner comparison
* Pagination: 20 per page on `/api/meetings`. Notifications use server-side
  filtering on `read_at IS NULL`.
* Webhooks return 200 immediately; all heavy work is queued.

---

## 8. Manual Steps Before Production

1. **Generate APP_KEY**: `php artisan key:generate` (do NOT reuse staging key).
2. **Provision S3 bucket + IAM user** with `PutObject`, `GetObject`,
   `DeleteObject`, `GetObjectAcl` and configure `AWS_*` env vars. Bucket must
   block public ACLs — files are served via temporary signed URLs only.
3. **Provision Recall.ai webhook endpoint** in the Recall dashboard pointing
   to `https://<domain>/api/webhooks/recall`. Copy the `whsec_…` secret into
   `RECALL_SIGNING_SECRET`.
4. **Configure Google OAuth credentials** in the Google Cloud console — add
   `https://<domain>/api/oauth/google/callback` as an authorized redirect URI
   and enable the Calendar API.
5. **Stand up Soketi / Pusher** for broadcasting and update `PUSHER_*` env
   vars. Make sure `BROADCAST_CONNECTION=pusher` in production.
6. **Replace CSP `'unsafe-inline'` / `'unsafe-eval'` with nonces.** The
   current CSP allows inline scripts for Vite dev manifest compatibility.
   Update `config/security.php` (`csp.script-src`) to use a nonce middleware
   in production. Run a CSP report-only deploy first to catch regressions.
7. **Run `php artisan horizon:install` + supervise the worker process** (or
   equivalent process manager). `php artisan schedule:run` must run every
   minute (cron) for `PurgeSoftDeletedMeetingsJob` and similar.

---

## 9. What I Would Do Differently

* **Nonce-based CSP.** Inline scripts should be eliminated from the SPA shell
  template (`resources/views/app.blade.php`) and replaced with a per-request
  nonce injected into both the Vite tag and the CSP header.
* **Per-IP login lockout instead of per-IP throttle.** Throttle limits don't
  defend a single attacker behind a CDN; a per-account lockout with backoff
  would be stronger. Hook into the `Failed` listener to track and lock.
* **Move OpenAI prompt versions into encrypted JSON blobs** so user-supplied
  prompt text is opaque at rest like OAuth tokens already are.
* **Replace polling avatar / PDF URL generation with persistent signed URLs**
  cached in Redis for the duration of the file's expected validity — saves a
  round-trip to S3 STS on every meeting view.
* **Add OpenTelemetry tracing** around webhook → job → broadcast chains so
  failures inside Horizon are observable end-to-end.

---

## 10. Tests

```bash
php artisan test            # 202 tests, ~30s
npx vitest run               # 119 tests, ~7s
composer audit               # checks Packagist advisories
npm run audit                # checks npm advisories
```

Coverage thresholds: 80% backend (clover), 70% frontend (v8). Reports are
written to `storage/coverage/` (backend) and `storage/coverage/frontend/`
(frontend) and uploaded as artifacts by CI.

---

## 11. Key Files

* `bootstrap/app.php` — middleware registration, exception rendering
* `app/Providers/AppServiceProvider.php` — Gate policies, event listeners, `preventLazyLoading`
* `app/Http/Middleware/SecurityHeaders.php` — CSP + standard headers
* `app/Http/Middleware/VerifyRecallWebhookSignature.php` — webhook HMAC verification
* `app/Rules/AllowedMeetingUrl.php` — SSRF guard
* `app/Listeners/LogFailedAuthAttempt.php` — auth-failure audit trail
* `app/Policies/{Meeting,CoachingAnalysis,AppNotification}Policy.php`
* `app/Actions/DispatchBotAction.php` — transactional one-bot-per-URL enforcement
* `config/security.php` — allowed hosts, CSP directives, encrypted columns
* `config/logging.php` — `security` log channel
* `.github/workflows/ci.yml` — backend / frontend / dependency-audit jobs
