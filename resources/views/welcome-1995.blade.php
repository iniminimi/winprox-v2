{{--
  Easter egg: Geocities/Netscape look. Geen app-CSS, grap-pagina, niet functioneel.
  Assets in public/images/welcome/1995/ (banner_1995.png, under-construction.gif, …).
--}}
@php
    $img1995 = static function (string $file): ?string {
        $rel = "images/welcome/1995/{$file}";

        return is_file(public_path($rel)) ? asset($rel) : null;
    };
    $bannerUrl = $img1995('banner_1995.png') ?? $img1995('banner.gif');
    $constructionUrl = $img1995('under-construction.gif');
    $emailGifUrl = $img1995('email.gif');
    $bulletUrl = $img1995('bullet.gif');
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>~*~ WinProx Facility Management ~*~ Welkom op onze Website! ~*~</title>
    @include('partials.favicon')
    <style>
        * { box-sizing: border-box; }

        body {
            background-color: #c0c0c0;
            background-image:
                repeating-linear-gradient(45deg, #b8b8b8 0, #b8b8b8 2px, #c0c0c0 2px, #c0c0c0 8px);
            font-family: "Comic Sans MS", "Comic Sans", cursive, sans-serif;
            color: #000080;
            margin: 0;
            padding: 10px;
        }

        .rainbow {
            height: 6px;
            width: 100%;
            background: linear-gradient(90deg, red, orange, yellow, green, blue, indigo, violet);
            border: none;
            margin: 10px 0;
        }

        .marquee-bar {
            background: #000080;
            color: #ffff00;
            font-weight: bold;
            font-size: 20px;
            padding: 6px 0;
            border: 3px ridge #ff00ff;
            margin-bottom: 8px;
        }

        h1 {
            text-align: center;
            font-size: 42px;
            color: #ff0000;
            text-shadow: 2px 2px 0 #ffff00, -1px -1px 0 #00ffff;
            letter-spacing: 2px;
            margin: 6px 0;
        }

        .blink {
            animation: blinker 1s step-start infinite;
        }
        @keyframes blinker { 50% { opacity: 0; } }

        .construction {
            background: repeating-linear-gradient(45deg, #ffff00, #ffff00 20px, #000000 20px, #000000 40px);
            color: #ff0000;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            padding: 8px;
            border: 4px outset #ffff00;
            margin: 10px 0;
            text-shadow: 1px 1px 2px #fff;
        }

        .banner-wrap {
            text-align: center;
            margin: 8px 0 12px;
        }
        .banner-wrap img {
            max-width: 100%;
            height: auto;
            border: 2px inset #808080;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
            background: #d4d0c8;
            border: 4px outset #ffffff;
        }
        td { vertical-align: top; padding: 12px; }

        .sidebar {
            width: 200px;
            background: #000080;
            color: #ffffff;
            border-right: 4px ridge #c0c0c0;
        }
        .sidebar h3 {
            background: #ff0000;
            color: #ffff00;
            padding: 4px;
            margin-top: 0;
            text-align: center;
            border: 2px outset #ffffff;
        }
        .sidebar a {
            display: block;
            color: #00ffff;
            text-decoration: none;
            padding: 4px 2px;
            font-size: 14px;
        }
        .sidebar a:before { content: ">> "; }
        .sidebar a:hover { color: #ffff00; text-decoration: underline; background: #ff00ff; }

        .content {
            background: #ffffff;
            border: 2px inset #808080;
        }

        .content h2 {
            background: linear-gradient(90deg, #000080, #8080ff);
            color: #ffff00;
            padding: 6px;
            font-size: 22px;
            border: 2px outset #c0c0c0;
        }

        .content p { font-size: 15px; line-height: 1.5; }

        .badge-row { text-align: center; margin: 14px 0; }
        .badge {
            display: inline-block;
            background: #000080;
            color: #ffff00;
            font-weight: bold;
            font-size: 11px;
            border: 2px outset #c0c0c0;
            padding: 4px 8px;
            margin: 2px;
            font-family: "Courier New", monospace;
        }

        .counter {
            background: #000;
            color: #0f0;
            font-family: "Courier New", monospace;
            font-size: 24px;
            letter-spacing: 4px;
            padding: 6px 10px;
            border: 3px inset #888;
            display: inline-block;
        }

        .guestbook-btn,
        .cta-btn {
            background: linear-gradient(#ff6a00, #ff0000);
            color: #ffffff;
            font-weight: bold;
            padding: 10px 18px;
            border: 3px outset #ffcc00;
            font-size: 16px;
            cursor: pointer;
            text-shadow: 1px 1px 1px #000;
            text-decoration: none;
            display: inline-block;
        }
        .guestbook-btn:active,
        .cta-btn:active { border-style: inset; }

        .cta-btn--blue {
            background: linear-gradient(#0000cc, #000080);
            border-color: #00ffff;
        }

        hr.old {
            border: none;
            height: 4px;
            background: repeating-linear-gradient(90deg, #ff0000 0 10px, #ffff00 10px 20px, #00ff00 20px 30px);
        }

        .webring {
            text-align: center;
            background: #000;
            color: #0ff;
            padding: 8px;
            font-size: 13px;
            border: 3px double #ff00ff;
            margin-top: 14px;
        }

        .stamp {
            float: right;
            background: repeating-conic-gradient(#ff0000 0 10deg, #ffffff 10deg 20deg);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            color: #000080;
            border: 2px solid #000080;
            animation: spin 6s linear infinite;
        }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        footer.classic-foot {
            text-align: center;
            font-size: 12px;
            color: #000080;
            margin-top: 12px;
            padding: 8px;
        }

        .email-link { color: #ff00ff; font-weight: bold; }
        a.oldlink { color: #0000ff; }
        a.oldlink:visited { color: #800080; }

        .guestbook-fields label {
            display: block;
            margin: 6px 0 2px;
            font-size: 14px;
        }
        .guestbook-fields input,
        .guestbook-fields textarea {
            font-family: "Courier New", monospace;
            font-size: 14px;
            width: 100%;
            max-width: 28rem;
            border: 2px inset #808080;
            background: #ffffee;
        }

        .cgi-error {
            display: none;
            margin-top: 12px;
            background: #000080;
            color: #ffffff;
            font-family: "Courier New", monospace;
            font-size: 13px;
            padding: 12px;
            border: 4px outset #c0c0c0;
            white-space: pre-wrap;
        }
        .cgi-error.is-visible { display: block; }
        .cgi-error strong { color: #ffff00; }
    </style>
</head>
<body>

<div class="marquee-bar">
    <marquee behavior="scroll" direction="left" scrollamount="5">
        *** WELKOM BIJ WINPROX *** DE TOEKOMST VAN FACILITY MANAGEMENT *** NU OOK MET TIME EN ESG *** BEST BEKEKEN MET NETSCAPE NAVIGATOR 2.0 OF INTERNET EXPLORER 3.0 *** VRAAG NAAR ONZE GRATIS DEMO DISKETTE ***
    </marquee>
</div>

@if ($bannerUrl)
    <div class="banner-wrap">
        <img src="{{ $bannerUrl }}" alt="WinProx banner 1995" width="468" height="60">
    </div>
@endif

<h1>~ Win<span style="color:#00cc00;">Prox</span> Facility Management <span class="blink">★</span> ~</h1>
<p style="text-align:center; font-style:italic; color:#800080;">
    "Enterprise software beheert de data. WinProx beheert de uitvoering."
</p>

<hr class="rainbow">

<div class="construction">
    @if ($constructionUrl)
        <img src="{{ $constructionUrl }}" alt="Under construction" width="40" height="40" style="vertical-align:middle;">
    @endif
    DEZE WEBSITE IS PERMANENT IN AANBOUW — KOM SNEL TERUG VOOR MEER UPDATES!
    @if ($constructionUrl)
        <img src="{{ $constructionUrl }}" alt="" width="40" height="40" style="vertical-align:middle;">
    @endif
</div>

<table class="layout" cellspacing="0" cellpadding="0">
<tr>
    <td class="sidebar">
        <h3>NAVIGATIE</h3>
        <a class="oldlink" href="#home">Home.htm</a>
        <a class="oldlink" href="#over">Over_Ons.htm</a>
        <a class="oldlink" href="#modules">Modules.htm</a>
        <a class="oldlink" href="#gastenboek">Gastenboek.htm</a>
        <a class="oldlink" href="{{ route('register') }}">Account_Aanmaken.exe</a>
        <a class="oldlink" href="{{ route('login') }}">Inloggen.exe</a>
        <a class="oldlink" href="{{ route('welcome') }}">« Terug_naar_2026.htm</a>
        <a class="oldlink" href="{{ route('contact.index') }}">Contact.htm</a>

        <h3 style="margin-top:20px;">BEZOEKERSTELLER</h3>
        <div style="text-align:center;">
            <span class="counter" id="wp-1995-counter">004213</span>
            <p style="font-size:11px; color:#00ffff;">bezoekers sinds 1995</p>
        </div>

        <h3>WEERBERICHT</h3>
        <p style="font-size:12px; color:#ffff00; text-align:center;">
            Vandaag: zonnig<br>met kans op<br>facility-uitdagingen
        </p>

        <h3 style="margin-top:20px;">BEST VIEWED</h3>
        <p style="font-size:11px; color:#ffffff; text-align:center; border:1px solid #fff; padding:6px;">
            NETSCAPE 2.0<br>OF HIGHER<br>800×600
        </p>
    </td>

    <td class="content" id="home">
        <div class="stamp">BEST<br>VIEWED<br>800×600</div>

        <h2 id="over"> Welkom op onze homepage! </h2>
        <p>
            Hallo en <b>welkom</b> op de officiële website van <b>WinProx</b>!
            Wij zijn een modern <i>multi-tenant</i> softwareplatform voor facility management —
            locaties, units, meldingen en taken. Geen papieren werkbonnen meer!
        </p>
        <p>
            Vergeet dure en logge systemen — WinProx is <b><u>snel</u></b>,
            <b><u>betaalbaar</u></b> en <b><u>eenvoudig</u></b> in gebruik!
            Gemaakt met de nieuwste HTML 3.2-technologie.
        </p>

        <p style="text-align:center; margin: 16px 0;">
            <a class="cta-btn" href="{{ route('register') }}">★ GRATIS ACCOUNT AANMAKEN ★</a>
            &nbsp;
            <a class="cta-btn cta-btn--blue" href="{{ route('login') }}">INLOGGEN</a>
        </p>

        <hr class="old">

        <h2 id="modules"> Onze Modules</h2>
        <p>
            @if ($bulletUrl)<img src="{{ $bulletUrl }}" alt="*" width="12" height="12">@else✔@endif
            <b>WinProx Facility</b> — meldingen, taken, QR-units<br>
            @if ($bulletUrl)<img src="{{ $bulletUrl }}" alt="*" width="12" height="12">@else✔@endif
            <b>WinProx Time</b> — QR-tijdsregistratie<br>
            @if ($bulletUrl)<img src="{{ $bulletUrl }}" alt="*" width="12" height="12">@else✔@endif
            <b>WinProx ESG</b> <span class="blink">NIEUW!</span> — duurzaamheidsopvolging
        </p>

        <div class="badge-row">
            <span class="badge">100% Y2K PROOF</span>
            <span class="badge">GEEN COOKIES NODIG</span>
            <span class="badge">28.8K MODEM VRIENDELIJK</span>
            <span class="badge">GRATIS TELLER</span>
        </div>

        <hr class="old">

        <h2 id="gastenboek"> Laat een bericht achter!</h2>
        <p>Teken ons interactieve CGI-gastenboek (powered by Perl 5.004):</p>

        <form id="wp-1995-guestbook" class="guestbook-fields" action="#" method="post">
            <label>Uw Naam:
                <input type="text" name="naam" maxlength="40" autocomplete="off">
            </label>
            <label>Uw Organisatie:
                <input type="text" name="bedrijf" maxlength="40" autocomplete="off">
            </label>
            <label>Bericht:
                <textarea name="bericht" rows="4" maxlength="200"></textarea>
            </label>
            <p style="margin-top:12px;">
                <button type="submit" class="guestbook-btn"> TEKEN HET GASTENBOEK!</button>
                <button type="reset" class="guestbook-btn" style="background:linear-gradient(#888,#444);">Wis alles</button>
            </p>
        </form>

        <div id="wp-1995-cgi-error" class="cgi-error" role="alert">
<strong>CGI Error</strong>

The specified CGI application misbehaved by not returning a complete set of HTTP headers.

Script: /cgi-bin/guestbook.pl
Error: Connection reset by peer (floppy drive busy)

WinProx guestbook daemon 0.9b — please try again after inserting disk 2 of 2.
        </div>

        <hr class="old">

        <h2> Contacteer Ons</h2>
        <p>
            @if ($emailGifUrl)
                <img src="{{ $emailGifUrl }}" alt="" width="24" height="16" style="vertical-align:middle;">
            @endif
            Stuur een e-mail via
            <a class="email-link" href="{{ route('contact.index') }}">ons contactformulier (2026)</a>
            of maak meteen een
            <a class="oldlink" href="{{ route('register') }}">proefaccount</a> aan!
        </p>

        <div class="webring">
            ⟨⟨ WinProx WebRing ⟩⟩ — Site 7 van 42 —
            <a href="{{ route('welcome') }}" style="color:#ff0;"> Vorige</a> |
            <a href="{{ route('register') }}" style="color:#ff0;"> Willekeurig</a> |
            <a href="{{ route('welcome') }}" style="color:#ff0;"> Volgende</a>
        </div>
    </td>
</tr>
</table>

<hr class="rainbow">

<footer class="classic-foot">
    © 1995–{{ date('Y') }} WinProx — Alle rechten voorbehouden.<br>
    Deze pagina is een grap. De echte site is
    <a class="oldlink" href="{{ route('welcome') }}">hier</a>.<br>
    <span style="font-size:10px;">Laatst bijgewerkt: altijd gisteren — Powered by Geocities-geest</span>
</footer>

<script>
(function () {
    var el = document.getElementById('wp-1995-counter');
    var n = 4213 + Math.floor(Math.random() * 80);
    function paint() {
        el.textContent = String(n).padStart(6, '0');
    }
    paint();
    setInterval(function () {
        n += Math.floor(Math.random() * 3) + 1;
        paint();
    }, 4000);

    var form = document.getElementById('wp-1995-guestbook');
    var err = document.getElementById('wp-1995-cgi-error');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.classList.add('is-visible');
        err.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
})();
</script>

</body>
</html>
