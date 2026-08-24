# Queue op gedeelde hosting (Cloud86 / Plesk)

WinProx gebruikt Laravel-queues voor o.a. promo-mails, webhooks en QR-notificaties.
Op gedeelde hosting draait **geen** permanente `queue:work`-daemon.

## Vereisten

1. **`.env` op productie**
   ```env
   QUEUE_CONNECTION=database
   ```

2. **Migraties** (eenmalig, `jobs`-tabel)
   ```bash
   php artisan migrate
   ```

3. **Plesk-cron** — elke minuut `schedule:run` (meestal al aanwezig):
   ```bash
   cd /var/www/vhosts/winprox.app/httpdocs/winprox && php artisan schedule:run >> /dev/null 2>&1
   ```

4. **Code** — `routes/console.php` plant elke minuut:
   ```php
   Schedule::command('queue:work database --max-time=55 --sleep=1 --tries=3')
       ->everyMinute()
       ->withoutOverlapping(3);
   ```

   Geen `--stop-when-empty`: anders stopt de worker na de eerste job terwijl de rest nog
   een `delay` heeft, en worden bij de volgende cron-tick meerdere mails in een korte burst
   verstuurd (±3 s tussen jobs i.p.v. de ingestelde vertraging).

   `withoutOverlapping(3)`: mutex vervalt na 3 minuten. Laravel-default is 24 uur — als Plesk
   de worker killt zonder de lock vrij te geven, blijft de mailwachtrij dan een dag stilstaan.
   `pull-deploy.sh` draait `schedule:clear-cache` zodat een vastzittende lock bij deploy loslaat.

Na deploy op de server: `./pull-deploy.sh` en `php artisan config:clear` indien nodig.

## Controleren

```bash
php artisan schedule:list    # toont o.a. queue:work every minute
crontab -l                 # of crontab -l -u <plesk-username>
```

Test handmatig:

```bash
php artisan schedule:run -v
```

## Promo-mails

Jobs met vertraging (`delay-seconds`, standaard **20** voor Cloud86 SMTP) worden verwerkt terwijl de cron-worker
pollt (max. ~55 s per minuut).

**Promo-campagnes gaan via Cloud86 SMTP** (`WINPROX_PROMO_MAILER=municipal_promo`), dezelfde limiet van
~250 mails/uur. Amazon SES is niet in gebruik (production access geweigerd, augustus 2026).

- UI/queue: `WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS` (default **20**).
- De harde **SMTP-throttle** (`PromoSmtpThrottle`) voorkomt dat queue-workers sneller sturen dan Cloud86 toelaat.

Env:

```env
WINPROX_PROMO_MAILER=municipal_promo
WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS=20
```

## Promo-mails onderbreken

Wachtende jobs blijven anders tot 3 dagen retrien. Stoppen:

1. **Platform → Promo-campagnes → Onderbreek alle verzending** (of per campagne), of
2. Op de server:
   ```bash
   php artisan marketing:pause-promo-emails
   php artisan config:clear
   ```
3. Noodrem in `.env` (na deploy van deze code):
   ```env
   WINPROX_PROMO_EMAILS_ENABLED=false
   ```
   Daarna `php artisan config:clear`. Jobs slaan over; hervatten: `true` + UI **Hervat verzending** + opnieuw in wachtrij.

Al verstuurde mails blijven verstuurd. Hervatten zet niet automatisch de rest opnieuw klaar.

**Zonder deze code (direct op de server):**
```sql
DELETE FROM jobs WHERE payload LIKE '%SendPromoCampaignEmailJob%';
DELETE FROM jobs WHERE payload LIKE '%SendMunicipalPromoLetterEmailJob%';
```
Dat haalt wachtende mails weg, maar voorkomt niet dat iemand opnieuw in de wachtrij zet.

De throttle laat een job **releasen** tot zijn slot vrij is, en elke release verbruikt een
poging. Daarom heeft `SendPromoCampaignEmailJob` géén `tries`-limiet maar een `retryUntil`
(3 dagen) plus `maxExceptions = 3`: uitstel is gratis, echte verzendfouten blijven begrensd.
Met een `tries`-limiet zou een batch van duizenden mails na ~10 minuten massaal in
`failed_jobs` belanden zonder ooit verstuurd te zijn.

## Wachtrij staat stil — eerst dit controleren

```bash
php artisan queue:monitor database:default   # aantal wachtende jobs
php artisan schedule:list                    # loopt queue:work elke minuut?
php artisan queue:failed | tail              # of jobs stilletjes sneuvelen
```

Draaien andere geplande taken wél (bv. de uurlijkse bounce-scan) maar de wachtrij niet, dan
hangt de **scheduler-mutex** van `queue:work` — niet de cron. Losmaken:

```bash
php artisan schedule:clear-cache
```

Op shared hosting kan er tussen twee worker-runs een korte pauze zitten (cron elke minuut).
Dat is normaler dan het oude patroon: één mail, dan een minuut wachten, dan 3–4 mails in
een paar seconden.

## Niet doen op shared hosting

- Permanente `queue:work` in SSH/screen (proces wordt vaak gekilld)
- `QUEUE_CONNECTION=sync` op productie als je promo-batch of webhooks via queue wilt
