# Release checklist — compliance (kort)

Gebruik bij releases die **privacy-, juridische of bewaartermijn‑functionaliteit** raken. Geen vervanging van juridische review.

- [ ] Zijn **publieke juridische pagina’s** (`/privacy`, `/terms`, …) nog correct gelinkt vanuit auth, billing en footer waar van toepassing?
- [ ] Bij wijziging aan privacy/voorwaarden: **`LEGAL_DOCUMENTS_LAST_UPDATED`** in `.env` / deploy-set gezet en datum klopt? (default in `config/legal.php` is `2026-07-30`)
- [ ] Backup-/RPO-/RTO-tekst nog in lijn met technische fiche (Cloud86 dagelijks, 7 dagen, RPO≈24u, RTO best effort ≤1 werkdag)?
- [ ] Zijn er nieuwe **verwerkingen** of **subverwerkers**? Zo ja: `subprocessors`-pagina en intern register bijwerken (zie `processing-register-draft.md`).
- [ ] Zijn er nieuwe **scheduled commands** of **retentie‑instellingen**? Documenteer env‑flags (`RETENTION_*`) voor operators (o.a. activity logs, sessies, password-reset tokens, failed_jobs — zie `config/data_retention.php`).
- [ ] **Activity logs**: worden relevante beheerdersacties nog gelogd voor auditsamples?
- [ ] Productie: na deploy **`npm run build`** en caches volgens jullie runbook.
