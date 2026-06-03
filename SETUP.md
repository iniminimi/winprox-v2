# WinProx V2 — lokale setup

Facility-herbouw van WinProx. **V1** (`winprox_old` / productieserver) is een apart project — deze repo is alleen **V2**.

## Repository

```text
https://github.com/iniminimi/winprox-v2.git
```

## Vereisten

- **PHP 8.2+** (extensies o.a. `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`)
- **Composer 2**
- **Node.js 20+** en **npm**
- **MySQL 8** (lokaal of via Laragon/XAMPP/Docker)

> Zie ook `WINPROX_RULES.md` voor architectuur- en UI-regels.

## Eerste installatie (nieuwe laptop)

```powershell
git clone https://github.com/iniminimi/winprox-v2.git
cd winprox-v2
composer install
copy .env.example .env
php artisan key:generate
```

### Database (MySQL)

Maak een lege database aan (bv. `winprox`) en pas `.env` aan:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=winprox
DB_USERNAME=root
DB_PASSWORD=
```

Daarna:

```powershell
php artisan migrate
```

Optioneel testdata:

```powershell
php artisan db:seed
```

### Frontend

```powershell
npm install
npm run build
```

### Server starten

```powershell
php artisan serve
```

Open `http://127.0.0.1:8000` (pas `APP_URL` in `.env` aan indien nodig).

Na CSS/JS-wijzigingen: opnieuw `npm run build`. Bij twijfel in de browser: harde refresh (`Ctrl+F5`).

## Wat zit níet in Git

| Bestand / map | Actie op nieuwe laptop |
|---------------|----------------------|
| `.env` | Handmatig aanmaken (kopie veilig via wachtwoordmanager) |
| `vendor/` | `composer install` |
| `node_modules/` | `npm install` |
| `storage/app/` uploads | Alleen meenemen als je die nodig hebt |

**Commit nooit** `.env` of secrets.

## Dagelijks werken (meerdere laptops)

**Start van de dag:**

```powershell
git pull
composer install
npm install
npm run build
```

(`composer install` / `npm install` alleen nodig als `composer.lock` of `package.json` gewijzigd zijn.)

**Einde sessie:**

```powershell
git add .
git commit -m "Korte beschrijving van de wijziging"
git push
```

## Vertalingen (bij wijzigingen in `lang/**`)

```powershell
npm run fix:locales
npm run check:locales
npm run check:locales:parity
```

Alle vier talen (`nl`, `en`, `fr`, `de`) moeten dezelfde sleutels hebben.

## V1 vs V2

| | V1 | V2 (deze repo) |
|---|----|----------------|
| Omgeving | Productieserver | Lokaal + later eigen deploy |
| Code | Oude codebase | `winprox-v2` op GitHub |
| Sectorkeuze | Meerdere sectoren | Alleen Facility |

Wijzigingen hier hebben **geen effect** op V1 zolang je V2 niet naar de V1-server deployt.

## Handige commando’s

```powershell
php artisan migrate
php artisan test
npm run build
npm run dev
```

## Problemen?

- **Tests falen met “could not find driver (sqlite)”** — PHPUnit gebruikt vaak SQLite in memory; installeer `pdo_sqlite` of pas de test-DB-config aan.
- **Lege styling** — `npm run build` gedraaid? `public/build/` up-to-date?
- **500 na pull** — `composer install`, `php artisan migrate`, `php artisan config:clear`
