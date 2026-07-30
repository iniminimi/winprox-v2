# Data Retention Policy (Draft)

This policy is a draft and requires legal approval.

**Jurisdiction:** Intended for deployments serving **Belgian** customers under **EU GDPR** and **Belgian data protection law** (implementation of the GDPR). Align retention with contractual needs, statute‑of‑limitations, and sector rules where applicable.

## Principles

- Keep only data needed for service delivery, legal duties, and security.
- Prefer anonymization or deletion when retention expires.
- Apply the same policy across backups where technically feasible.
- **Operational infrastructure backups** (Cloud86): automatic daily backups, retained **7 days**. Recovery targets: **RPO ≈ 24 hours**, **RTO best effort (typically within 1 business day)**. These are separate from the post-purge technical SQL snapshot (max. 30 days, without media).

## Retention Table

| Data category | Examples | Proposed retention | Action at end |
|---|---|---|---|
| User accounts | Name, email, role | While account is active + 24 months | Delete or anonymize |
| Buildings/units | Address and structure data | Contract period + 24 months | Delete or export then delete |
| Issues/tasks | Reports, statuses, notes | Contract period + 36 months | Archive or delete |
| ESG measurements | Indicator definitions, values, thresholds, follow-up tasks, API/webhooks | Contract period + 36 months | Delete with related records |
| IoT Connect | Gateway/sensor metadata, alarm rules, event records (not time-series) | Contract period + 36 months | Delete with tenant / related workflow records |
| Issue translations | AI-generated translations of issue descriptions | Contract period + 36 months | Delete with parent issue |
| Owner contacts | Name, email, phone | While linked + 24 months | Delete if no legal need remains |
| Notification logs | Sent/failed recipients, subject | 24 months | Delete or aggregate stats only |
| Activity logs | Security and audit events | 24 months | Delete or anonymize |
| Uploaded media | Issue photos and updates | 24 months after related record closes | Delete |
| Operational infrastructure backups | Cloud86 daily backups of files/DB | 7 days | Rotate/overwrite by provider |
| Tenant-purge SQL snapshot | Technical snapshot after organisation wipe (no media) | Max. 30 days | Destroy |

## Operational Requirements

- **Automated cleanup**: Artisan-command `php artisan winprox:retention-prune --dry-run` toont wat verwijderd zou worden zonder te wijzigen. Zonder `--dry-run` worden records en bestanden daadwerkelijk verwijderd.
- **Closed issue media**: Verwijdert foto's van gesloten meldingen volgens retentiebeleid.
- **Inactive tenant facility data**: Verwijdert data van inactieve tenants (meldingen, foto's) volgens retentiebeleid.
- Voer steeds **`--dry-run`** uit om te tellen zonder te wissen. Productie-aanzetten pas na legal / operations sign-off.
- Admin export vóór verwijdering waar nodig: **TODO** (proces).
- Document exceptions (litigation hold, legal claims). `TODO`
- Bewaartermijnen voor **activity logs**, **sessions**, **password reset tokens**, en **failed jobs** zijn nog niet geautomatiseerd in WinProx V2 — apart te ontwerpen indien nodig.
