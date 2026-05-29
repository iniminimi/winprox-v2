<!DOCTYPE html>
<html lang="nl" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WinProx — preview</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell">
    <div class="wp-container wp-stack">

        <header class="wp-stack">
            <h1 class="wp-page-title">WinProx</h1>
            <p>Preview van het <strong>standard</strong>-thema en de gedeelde componenten. Dit is een tijdelijke stijlpagina.</p>
        </header>

        <section class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">Knoppen</h2>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <button class="btn btn--primary">Primair</button>
                <button class="btn btn--ghost">Ghost</button>
                <button class="btn btn--warning">Waarschuwing</button>
                <button class="btn btn--danger">Gevaar</button>
                <button class="btn btn--primary" disabled>Uitgeschakeld</button>
            </div>
        </section>

        <section class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">Statussen (pillen)</h2>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <span class="wp-pill wp-pill--new">Nieuw (Open)</span>
                <span class="wp-pill wp-pill--progress">In uitvoering</span>
                <span class="wp-pill wp-pill--done">Afgehandeld</span>
                <span class="wp-pill wp-pill--closed">Gesloten</span>
            </div>
        </section>

        <section class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">Moderatie — melding wacht op controle</h2>
            <p>Beschrijving en foto's van een QR-melding blijven geblurd tot goedkeuring.</p>
            <div class="wp-pending-review" data-pending-label="Wacht op controle">
                <p>Voorbeeldbeschrijving die een melder via de QR-code heeft ingestuurd en die nog niet is gecontroleerd.</p>
            </div>
        </section>

        <section class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">Formulier</h2>
            <div>
                <label class="wp-label" for="demo-desc">Omschrijving</label>
                <textarea id="demo-desc" class="wp-textarea" placeholder="Beschrijf de melding..."></textarea>
                <p class="wp-hint">Voorbeeld van een tekstveld met het standaardthema.</p>
            </div>
        </section>

    </div>
</body>
</html>
