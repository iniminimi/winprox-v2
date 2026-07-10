# Register van verwerkingsactiviteiten — concept (ROPA‑achtig)

**Status:** sjabloon voor intern gebruik — aan te vullen en juridisch te valideren. Niet exhaustief.

| Verwerkingsactiviteit | Doel / rechtsgrond (concept) | Categorieën betrokkenen | Bewaartermijn (richting) | Verwerker(s) / doorgeven |
|----------------------|-------------------------------|-------------------------|---------------------------|---------------------------|
| Platformaccounts en login | Uitvoering overeenkomst / gerechtvaardigd belang (beveiliging) | Gebruikers, beheerders | Zie `data-retention-policy.md` | Hosting, e‑mail indien van toepassing |
| Meldingen en taken | Uitvoering overeenkomst (vastgoedbeheer) | Bewonerscontacten, gebruikers, workers | Zie retentiebeleid | Zoals in productflow |
| ESG-metingen (optionele module) | Uitvoering overeenkomst (compliance/inspectie) | Beheerders, workers, QR-melders | Zie retentiebeleid (zelfde als meldingen/taken) | Zoals in productflow |
| Eigenaarscommunicatie | Uitvoering overeenkomst (instructie klant) | Eigenaars, zoals door klant ingevoerd | Zie retentiebeleid | E‑mailprovider |
| Facturatie / Stripe | Uitvoering overeenkomst / wettelijke verplichting | Facturatiecontacten | Naar boekhoudvereisten | Stripe, zie subprocessors (niet operationeel - toekomstige functionaliteit) |
| AI vertalingen (Ollama) | Uitvoering overeenkomst (meertalige ondersteuning) | Meldingsteksten, gebruikers | Zie retentiebeleid (issue translations) | Lokale Ollama instance (optioneel, configureerbaar) |
| Activity / audit logs | Gerechtvaardigd belang (beveiliging, verantwoording) | Gebruikers (actoren) | Configureerbaar; default zie retentie | — |

**Opmerking:** De klant (tenant) is typisch **verwerkingsverantwoordelijke** voor klantdata; WinProx optreedt als **verwerker** tenzij anders contractueel bepaald.
