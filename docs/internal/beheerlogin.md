# Beheerlogin — limiet op mislukte pogingen

Niet publiceren op winprox.app. De technische fiche noemt alleen dat herhaalde mislukte pogingen tijdelijk worden geblokkeerd, zonder drempels.

Code: `App\Actions\Auth\ThrottleBackofficeLoginAction`, aangeroepen vanuit `App\Livewire\Auth\Login`.

| Teller | Maximum mislukte pogingen | Venster |
| --- | --- | --- |
| Zelfde e-mailadres én IP | 5 | 5 minuten |
| Zelfde e-mailadres, ongeacht IP | 10 | 15 minuten |
| Zelfde IP, ongeacht e-mailadres | 100 | 15 minuten |

Een geslaagde login wist de tellers van dat e-mailadres. De IP-teller blijft, zodat één geldig wachtwoord op een gedeeld netwerk de rest van die teller niet leegmaakt.

De poging die de limiet overschrijdt, schrijft `auth.login_throttled` in het auditlog als het adres bij een gebruiker met organisatie hoort. Het wachtwoord wordt niet gelogd.

Inloggen met Microsoft vraagt geen WinProx-wachtwoord en valt niet onder deze tellers.

Een groot botnet dat per account van een ander IP komt, blijft begrensd tot 10 pogingen per 15 minuten per adres. Sneller verkeer van één adres stopt bij de IP-teller. Een randfilter (WAF) bij de hoster zit hier niet in.
