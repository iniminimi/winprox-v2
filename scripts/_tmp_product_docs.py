import json

checkmate_item = {
 'nl': "Per klant maandstatistieken: bezoeken, gewerkte tijd en uren per werkadres — exporteerbaar als CSV of print.",
 'en': "Per-customer monthly statistics: visits, worked time and hours per work address — exportable as CSV or print.",
 'fr': "Statistiques mensuelles par client : visites, temps travaillé et heures par adresse de travail — exportables en CSV ou à imprimer.",
 'de': "Monatsstatistiken pro Kunde: Besuche, Arbeitszeit und Stunden pro Arbeitsadresse — exportierbar als CSV oder Druck.",
 'es': "Estadísticas mensuales por cliente: visitas, tiempo trabajado y horas por dirección de trabajo — exportables en CSV o imprimibles.",
 'it': "Statistiche mensili per cliente: visite, tempo lavorato e ore per indirizzo di lavoro — esportabili in CSV o stampa.",
}

rapporten_item = {
 'nl': "Klantstatistieken (Time): per klant gewerkte tijd, bezoeken en uren per werkadres per maand.",
 'en': "Customer statistics (Time): per customer worked time, visits and hours per work address per month.",
 'fr': "Statistiques client (Time) : temps travaillé, visites et heures par adresse de travail, par mois.",
 'de': "Kundenstatistiken (Time): Arbeitszeit, Besuche und Stunden pro Arbeitsadresse pro Kunde und Monat.",
 'es': "Estadísticas de cliente (Time): tiempo trabajado, visitas y horas por dirección de trabajo al mes.",
 'it': "Statistiche cliente (Time): tempo lavorato, visite e ore per indirizzo di lavoro, per mese.",
}

for loc in ('nl', 'en', 'fr', 'de', 'es', 'it'):
    p = f'lang/{loc}/product_docs.json'
    d = json.load(open(p, encoding='utf-8'))
    full = d['features']['full']
    checkmate = next(i for i in full if i.get('badge') == 'RSZ/CIAO' or i.get('title') == 'Checkmate')
    rapporten = next(i for i in full if i.get('title') in ('Rapporten', 'Reports', 'Rapports', 'Berichte', 'Informes', 'Report'))
    if checkmate_item[loc] not in checkmate['items']:
        checkmate['items'].append(checkmate_item[loc])
    if rapporten_item[loc] not in rapporten['items']:
        rapporten['items'].append(rapporten_item[loc])
    json.dump(d, open(p, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    open(p, 'a', encoding='utf-8').write('\n')
    print(loc, 'ok:', checkmate['title'], '/', rapporten['title'])
