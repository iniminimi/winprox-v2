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
   Schedule::command('queue:work database --stop-when-empty --max-time=50 --sleep=3 --tries=3')
       ->everyMinute()
       ->withoutOverlapping();
   ```

Na `git pull` op de server: `php artisan config:clear`.

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

Jobs met vertraging (`delay-seconds`) worden verwerkt zodra de cron-worker draait.
Op shared hosting is de timing grover dan op een VPS (± per minuut), maar betrouwbaar
zonder handmatige worker.

## Niet doen op shared hosting

- Permanente `queue:work` in SSH/screen (proces wordt vaak gekilld)
- `QUEUE_CONNECTION=sync` op productie als je promo-batch of webhooks via queue wilt
