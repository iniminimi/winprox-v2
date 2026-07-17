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
       ->withoutOverlapping();
   ```

   Geen `--stop-when-empty`: anders stopt de worker na de eerste job terwijl de rest nog
   een `delay` heeft, en worden bij de volgende cron-tick meerdere mails in een korte burst
   verstuurd (±3 s tussen jobs i.p.v. de ingestelde vertraging).

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

Jobs met vertraging (`delay-seconds`, standaard **20**) worden verwerkt terwijl de cron-worker
pollt (max. ~55 s per minuut). Verwacht ~1 mail per ingestelde vertraging, niet bursts van
meerdere mails per seconde.

**Cloud86-limiet:** max. **~250 uitgaande mails per uur** per hostingplan. Daarom:
- UI/queue forceert minstens 20 seconden tussen bulk-mails (≈ 180/uur, marge voor andere mail);
- jobs gebruiken daarnaast een **harde SMTP-throttle** (`PromoSmtpThrottle`), zodat ook bij
  overlappende workers of `delay=0` niet sneller dan 1 mail per interval wordt verzonden.

Env (optioneel): `WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS=20`

Op shared hosting kan er tussen twee worker-runs een korte pauze zitten (cron elke minuut).
Dat is normaler dan het oude patroon: één mail, dan een minuut wachten, dan 3–4 mails in
een paar seconden.

## Niet doen op shared hosting

- Permanente `queue:work` in SSH/screen (proces wordt vaak gekilld)
- `QUEUE_CONNECTION=sync` op productie als je promo-batch of webhooks via queue wilt
