# -*- coding: utf-8 -*-
"""
Opschoonscript voor B2B Promo Campagne E-maillijsten.
Gebruik dit script vóór het importeren in WinProx om hard bounces en spamklachten te voorkomen.

Gebruik:
python scripts/marketing/clean_promo_emails.py <input.xlsx/csv> <output_hq.xlsx> <output_afgekeurd.csv>
"""
import sys
import pandas as pd
import re
import urllib.parse
from difflib import SequenceMatcher
from pathlib import Path
import dns.resolver
from concurrent.futures import ThreadPoolExecutor

WEBMAILS = {
    # Global
    'gmail.com', 'hotmail.com', 'yahoo.com', 'icloud.com', 'outlook.com', 
    'live.com', 'msn.com', 'me.com', 'mac.com', 'mail.com', 'ymail.com', 'googlemail.com',
    # ES
    'hotmail.es', 'yahoo.es', 'outlook.es', 'live.es', 'telefonica.net', 'orange.es',
    # FR
    'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'laposte.net', 'aliceadsl.fr', 
    'voila.fr', 'club-internet.fr', 'neuf.fr', 'bbox.fr', 'numericable.fr', 
    'gmx.fr', 'yahoo.fr', 'hotmail.fr', 'live.fr', 'msn.fr', 'outlook.fr',
    # NL/BE
    'ziggo.nl', 'kpnmail.nl', 'planet.nl', 'hetnet.nl', 'telfort.nl', 'xs4all.nl',
    'telenet.nl', 'upcmail.nl', 'casema.nl', 'skynet.be', 'telenet.be', 'proximus.be',
    # IT/DE
    'libero.it', 'virgilio.it', 'tiscali.it', 'alice.it', 'tin.it',
    'web.de', 'gmx.de', 't-online.de', 'freenet.de'
}

KNOWN_CHAINS = {
    'accor', 'melia', 'marriott', 'hilton', 'ihg', 'barcelo', 'radisson', 
    'wyndham', 'louvrehotels', 'bestwestern', 'hyatt', 'bbandb', 'campanile', 
    'kyriad', 'premiereclasse', 'dorchestercollection', 'shangri-la',
    'ritz', 'fourseasons', 'mandarinoriental', 'peninsula', 'belmond',
    'nh-hotels', 'vinccihoteles', 'ilunionhotels', 'petitpalace', 'parador', 
    'hotelesglobales', 'riuhotels', 'iberostar', 'palladiumhotelgroup', 
    'eurostarshotels', 'h10hotels', 'cataloniahotels', 'sirenishotels'
}

GENERICS = [
    # ES/EN
    'hotel', 'hostal', 'resort', 'spa', 'boutique', 'apartamentos', 
    'pension', 'suites', 'rural', 'casa', 'hostel', 'hosteria', 
    'rooms', 'apartments', 'inn', 'bedandbreakfast', 'bnb', 'villa', 'finca',
    # FR
    'htel', 'chateau', 'auberge', 'relais', 'domaine', 'manoir', 'chalet', 
    'logis', 'residence', 'appart', 'gite', 'motel', 'palace'
]

dns_cache = {}

def check_mx(domain):
    if domain in dns_cache:
        return dns_cache[domain]
    try:
        if dns.resolver.resolve(domain, 'MX', lifetime=2):
            dns_cache[domain] = True
            return True
    except: pass
    try:
        if dns.resolver.resolve(domain, 'A', lifetime=2):
            dns_cache[domain] = True
            return True
    except: pass
    dns_cache[domain] = False
    return False

def clean_string_for_match(text):
    t = str(text).lower()
    return re.sub(r'[^a-z]', '', t)

def sanitize_email(email):
    e = str(email).strip()
    e = urllib.parse.unquote(e)
    e = urllib.parse.unquote(e)
    e = e.lower()
    
    # 1. Verwijder vervuiling vóór "email:" (bv. "80\r\nemail:info@...")
    e = re.sub(r'^.*email\s*:\s*', '', e, flags=re.DOTALL)
    
    # 2. Verwijder vastgeplakte telefoonnummers (bv. "+33534436340contact@...")
    e = re.sub(r'^(\+|00)?\d{9,15}', '', e)
    
    # 3. Verwijder URL restanten
    e = re.sub(r'^%20', '', e)
    e = re.sub(r'^//', '', e)
    
    return e.strip()

def check_row(name, original_email):
    e = sanitize_email(original_email)
    n = str(name).strip()
    
    if not '@' in e:
        return e, "Ongeldige syntaxis (geen @)"
    if ' ' in e or ',' in e or '%' in e or '/' in e:
        return e, "Ongeldige syntaxis (bevat spaties/komma/junk)"
        
    local, domain = e.rsplit('@', 1)
    if len(domain.split('.')) < 2:
        return e, "Ongeldig domein (geen TLD)"
    
    if domain in WEBMAILS:
        return e, f"Gratis webmail ({domain})"
        
    if re.match(r'^(block|bloque|bloc)\s*\d', n, re.I):
        return e, "Schraapfout (Block/Bloque)"
        
    d_clean = clean_string_for_match(re.sub(r'^(www\.)', '', domain.rsplit('.', 1)[0]))
    
    if not any(chain in d_clean for chain in KNOWN_CHAINS):
        n_clean = clean_string_for_match(n)
        for g in GENERICS:
            n_clean = n_clean.replace(g, '')
            
        if len(n_clean) >= 3 and len(d_clean) >= 3:
            match = SequenceMatcher(None, n_clean, d_clean).find_longest_match(0, len(n_clean), 0, len(d_clean))
            if match.size < 3:
                n_full = clean_string_for_match(n)
                match_full = SequenceMatcher(None, n_full, d_clean).find_longest_match(0, len(n_full), 0, len(d_clean))
                if match_full.size < 3:
                    return e, "Domein mismatch"
                    
    return e, None

def main():
    if len(sys.argv) != 4:
        print("Gebruik: python scripts/marketing/clean_promo_emails.py <input> <output_hq> <output_afgekeurd>")
        sys.exit(1)
        
    in_file, out_hq, out_skipped = sys.argv[1], sys.argv[2], sys.argv[3]
    
    try:
        df = pd.read_excel(in_file) if in_file.endswith('.xlsx') else pd.read_csv(in_file)
    except Exception as e:
        print(f"Fout bij inlezen {in_file}: {e}")
        sys.exit(1)

    email_col = 'Email' if 'Email' in df.columns else 'email'
    name_col = 'Naam' if 'Naam' in df.columns else 'naam'

    if email_col not in df.columns or name_col not in df.columns:
        print(f"Verplichte kolommen '{name_col}' of '{email_col}' ontbreken!")
        sys.exit(1)
        
    print(f"Stap 1: Analyseren van {len(df)} rijen uit {in_file}...")
    reasons = []
    sanitized_emails = []
    
    for idx, row in df.iterrows():
        clean_e, reason = check_row(row[name_col], row[email_col])
        sanitized_emails.append(clean_e)
        reasons.append(reason)
        
    df['Email_Clean'] = sanitized_emails
    df['filter_reden'] = reasons
    
    kept = df[df['filter_reden'].isnull()].copy()
    skipped_offline = df[df['filter_reden'].notnull()].copy()
    
    print(f"Stap 2: DNS MX-checks uitvoeren op {len(kept)} adressen (multithreaded)...")
    unique_domains = kept['Email_Clean'].apply(lambda x: x.split('@')[1]).unique()
    
    with ThreadPoolExecutor(max_workers=50) as executor:
        list(executor.map(check_mx, unique_domains))
        
    dns_reasons = kept['Email_Clean'].apply(lambda e: None if check_mx(e.split('@')[1]) else "Geen DNS MX/A record (Domein offline)")
    kept['filter_reden'] = dns_reasons
    
    final_kept = kept[kept['filter_reden'].isnull()].drop(columns=['filter_reden', email_col]).rename(columns={'Email_Clean': email_col})
    final_skipped = pd.concat([skipped_offline, kept[kept['filter_reden'].notnull()]])
    
    print("\n--- RESULTATEN ---")
    print(f"Behouden (HQ): {len(final_kept)} rijen -> {out_hq}")
    print(f"Verwijderd:    {len(final_skipped)} rijen -> {out_skipped}\n")
    print(final_skipped['filter_reden'].apply(lambda x: x.split(' (')[0]).value_counts().to_string())
    
    if out_hq.endswith('.xlsx'):
        final_kept.to_excel(out_hq, index=False)
    else:
        final_kept.to_csv(out_hq, index=False, encoding='utf-8')
        
    final_skipped.to_csv(out_skipped, index=False, encoding='utf-8')
    print("Klaar!")

if __name__ == "__main__":
    main()
