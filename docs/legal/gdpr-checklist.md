# GDPR / AVG Checklist (WinProx)

**Toepassingsgebied:** Primair **België** en de **Europese Economische Ruimte** — EU‑AVG (Verordening 2016/679) en Belgische uitvoeringsregeling (o.a. wet 30 juli 2018). Aanvullende lokale regels kunnen gelden naarmate jullie klantenbestand ruimer wordt.

Status legend: `TODO`, `IN PROGRESS`, `DONE`

## 1) Governance

- [ ] `TODO` Appoint internal legal owner for GDPR decisions.
- [ ] `TODO` Confirm tenant is controller and WinProx is processor (default model).
- [ ] `TODO` Maintain processing activity register (ROPA).
- [ ] `IN PROGRESS` Draft register template in `processing-register-draft.md` (to be completed and owned internally).

## 2) Legal Basis and Transparency

- [ ] `TODO` Document legal basis per flow (issues, tasks, owner notifications, logs).
- [x] `DONE` Privacy / terms / DPA describe self-service organisation deletion, trial vs paid cool-down, expired-trial auto-purge, export path, and snapshot without media (2026-07-28).
- [ ] `TODO` Define when explicit consent is needed vs legitimate interest.

## 3) Contracts

- [ ] `TODO` Finalize Data Processing Agreement (DPA) template.
- [ ] `TODO` Define subprocessor list and change-notification process.
- [ ] `TODO` Define international transfer stance (if any external providers).

## 4) Data Subject Rights

- [ ] `TODO` Define process for access request (SAR).
- [ ] `TODO` Define process for rectification and erasure.
- [x] `DONE` Basic authenticated JSON export (`GET /account/data-export`) + activity log entry (`user_data_export_downloaded`). Formal SAR/process wording still `TODO`.
- [x] `DONE` Operational outline for rights handling: `docs/legal/data-subject-requests.md` (synced with public legal pages; not legal advice).
- [x] `DONE` Platform erasure paths documented (user deactivate + tenant purge trial/paid/expired_trial) in privacy/terms/DPA.

## 5) Security and Access

- [ ] `TODO` Confirm tenant isolation controls and periodic verification.
- [ ] `TODO` Confirm least-privilege access for admins/support.
- [ ] `TODO` Define breach handling and notification procedure.
- [ ] `IN PROGRESS` Audit trail extended for tenant admin actions on users/organisation (activity log actions `tenant_*`).

## 6) Retention and Deletion

- [ ] `TODO` Approve retention windows in `data-retention-policy.md`.
- [ ] `TODO` Decide archive vs delete strategy for old records.
- [ ] `IN PROGRESS` Automated cleanup: **`activity_logs`** (`retention:prune-activity-logs`) plus optional **operational** jobs — **`sessions`**, **`password_reset_tokens`**, **`failed_jobs`** — via dedicated Artisan commands (all opt‑in via env). **Issues/tasks/media/buildings** not automated yet — `TODO`.

## 7) Operational Readiness

- [x] `DONE` Short release compliance reminders: `docs/legal/release-compliance-checklist.md`.
- [ ] `TODO` Train support/admin on legal request handling.
- [ ] `TODO` Review this checklist quarterly.
