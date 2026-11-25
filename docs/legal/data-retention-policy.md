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
| Time / Clock Point (live) | Planned roster, clocked hours (breaks, GPS at punch, visits, task logs) and absence requests. The current calendar year and the previous year stay on screen. In January the year before that is still visible. Open clock-ins are not removed. RSZ/CIAO submissions stay in the database. | Until 1 February, two years after that calendar year | Zip on Subscription, then delete the rows |
| Roster restore points | Previous shifts of a roster edit (worker, day, times, note), kept so an admin can undo that edit | 30 days (configurable) | Daily `winprox:retention-prune`; also deleted with the tenant |
| Time archive files | One zip per closed year (roster, clocked hours, requests, no photo files) and one zip per month of absence photos that reached 6 months | Year file: 3 years from 1 February (or from a late catch-up). Photo file: through the end of the following month, at least one month if the job runs late | Download on Subscription for the tenant admin; then the file is deleted. Also deleted with the tenant |
| Absence request photos | Optional photo with a leave / time-off / sick request (private disk; the request row stays) | 6 months on the request (warning on the dashboard from 5 months), then 1 month in the photo zip | Daily `winprox:retention-prune` copies the file into the zip before deleting it; also deleted with the tenant |
| Camera photos | Copies a paired camera forwarded over Wi-Fi (private disk). The micro-SD in the device is not WinProx data and is not deleted. | 30 days | Daily `winprox:retention-prune`; also deleted with the unit or the tenant |
| Issue translations | AI-generated translations of issue descriptions | Contract period + 36 months | Delete with parent issue |
| Owner contacts | Name, email, phone | While linked + 24 months | Delete if no legal need remains |
| Notification logs | Sent/failed recipients, subject | 24 months | Delete or aggregate stats only |
| Activity logs | Security and audit events | 24 months | Delete or anonymize |
| Error alert mails | Internal mail to WinProx (info@): error reference, error message (SQL errors may contain personal data), file/line, URL, tenant/user ID. No stack trace. | Until the error is fixed | Delete the mail after the fix |
| Application error logs | Server error log: error reference, tenant/user ID, method, URL (no query string), stack trace. SQL errors may contain personal data (entered values). No form input, passwords or tokens. | 14 days | Daily log rotation deletes older files (`LOG_DAILY_DAYS=14`) |
| QR report email holds | Unverified QR drafts (description, photos, email) pending mailbox link | Unverified: typically 60 minutes then delete (incl. photos). Confirmed hold rows: 7 days. Created issues follow Issues/tasks. | Delete |
| Operational infrastructure backups | Cloud86 daily backups of files/DB | 7 days | Rotate/overwrite by provider |
| Tenant-purge SQL snapshot | Technical snapshot after organisation wipe (no media) | Max. 30 days | Destroy |

## Operational Requirements

- **Automated cleanup**: Artisan-command `php artisan winprox:retention-prune --dry-run` toont wat verwijderd zou worden zonder te wijzigen. Zonder `--dry-run` worden records en bestanden daadwerkelijk verwijderd.
- **Closed issue media**: Verwijdert foto's van gesloten meldingen volgens retentiebeleid.
- **Application error logs**: Laravel daily log (`LOG_STACK=daily`, `LOG_DAILY_DAYS=14`). Only server administration can read `storage/logs`; users see a reference code (`WP-XXXXXX`), never technical details.
- **Roster restore points**: Verwijdert uurrooster-herstelpunten ouder dan 30 dagen (`RETENTION_ROSTER_RESTORE_POINTS_DAYS`).
- **Absence request photos**: Foto's ouder dan 6 maanden (`RETENTION_ABSENCE_PHOTOS_MONTHS`) gaan eerst in een zip van die maand. Pas daarna verdwijnt het origineel. De aanvraag blijft. Het zipbestand blijft tot het einde van de volgende maand te downloaden via Abonnement. Vanaf 5 maanden (`RETENTION_ABSENCE_PHOTOS_WARN_MONTHS`) waarschuwt het beginscherm.
- **Time year archive**: Op of na 1 februari (Europa/Brussel) gaat per tenant met Time het kalenderjaar van twee jaar eerder (op 1 februari 2027 het jaar 2025) in een zip zonder fotobestanden. Daarna worden alleen die rijen gewist, en alleen als hun id in de zip staat. Een nog open inklok blijft. Het bestand blijft 3 jaar op Abonnement. In februari vermeldt het beginscherm dat.
- **Camera photos**: Verwijdert doorgestuurde camerafoto's ouder dan 30 dagen (`config/camera.php` `retention_days`). De micro-SD in het toestel blijft buiten WinProx.
- **Inactive tenant facility data**: Verwijdert data van inactieve tenants (meldingen, foto's) volgens retentiebeleid.
- Voer steeds **`--dry-run`** uit om te tellen zonder te wissen. Productie-aanzetten pas na legal / operations sign-off.
- Admin export vóór verwijdering waar nodig: **TODO** (proces).
- Document exceptions (litigation hold, legal claims). `TODO`
- Bewaartermijnen voor **activity logs**, **sessions**, **password reset tokens**, en **failed jobs** zijn nog niet geautomatiseerd in WinProx V2 — apart te ontwerpen indien nodig.
