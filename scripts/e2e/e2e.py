#!/usr/bin/env python3
"""Browser-Tests (Playwright) für ElvadoPress: echte Klickwege in der Verwaltung und auf der Website.

Aufruf:  python3 scripts/e2e/e2e.py            (Chromium von Playwright)
         E2E_CHROME=/usr/bin/google-chrome python3 scripts/e2e/e2e.py
         E2E_WORDPRESS=1 …                      zusätzlich: echtes WordPress einrichten (lädt von wordpress.org) und die Website damit ausliefern
Legt eine Wegwerf-Kopie des CMS an, startet `php -S` mit scripts/e2e/router.php, richtet ElvadoPress über den Einrichtungsassistenten ein
und prüft jeden Weg auf JavaScript-Fehler. Bildschirmfotos landen in $E2E_SHOTS (Standard: <Kopie>/shots).
"""
import json, os, shutil, socket, subprocess, sys, tempfile, time, traceback, urllib.parse, urllib.request
from playwright.sync_api import sync_playwright

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
USER, PW = 'admin1', 'Sehr-gutes-Passwort-42'
results = []


def check(name, ok, info=''):
    results.append((name, bool(ok), info))
    print(('  ok  ' if ok else 'FAIL  ') + name + ('' if ok or not info else ': ' + str(info)[:300]), flush=True)


def free_port():
    s = socket.socket(); s.bind(('127.0.0.1', 0)); p = s.getsockname()[1]; s.close(); return p


def http(method, url, data=None, headers=None):
    req = urllib.request.Request(url, data=data, method=method, headers=headers or {})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            return r.status, r.read().decode('utf-8', 'replace'), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers)


def make_site():
    tmp = tempfile.mkdtemp(prefix='elvado-e2e-')
    shutil.copytree(os.path.join(REPO, 'cms'), os.path.join(tmp, 'cms'), ignore=shutil.ignore_patterns('data', 'plugins', 'backups', 'media', 'generated', 'wp-engine', 'node_modules'))
    os.makedirs(os.path.join(tmp, 'cms', 'data'), exist_ok=True)
    for f in os.listdir(os.path.join(REPO, 'cms', 'data')):
        if f.endswith('.example') or f == '.htaccess':
            shutil.copy(os.path.join(REPO, 'cms', 'data', f), os.path.join(tmp, 'cms', 'data', f))
    for f in ('index.php', '.htaccess'):
        shutil.copy(os.path.join(REPO, f), os.path.join(tmp, f))
    for d in ('wp-content/uploads',):
        os.makedirs(os.path.join(tmp, 'cms', d), exist_ok=True)
    for f in ('db.php',):
        p = os.path.join(tmp, 'cms', 'wp-content', f)
        if os.path.exists(p): os.unlink(p)
    return tmp


def install(base):
    st, html, h = http('GET', base + '/cms/install.php')
    import re
    csrf = re.search(r'name="csrf" value="([a-f0-9]{32})"', html).group(1)
    body = urllib.parse.urlencode({'csrf': csrf, 'do': 'install', 'site_name': 'E2E Testseite', 'language': 'de', 'timezone': 'Europe/Berlin', 'username': USER,
                                   'email': 'a@example.org', 'password': PW, 'password2': PW, 'db_driver': 'none', 'sample': '1', 'install_mode': 'recommended'}).encode()
    st, html, h = http('POST', base + '/cms/install.php', body, {'Content-Type': 'application/x-www-form-urlencoded', 'Cookie': 'elvado_inst=' + csrf, 'Origin': base})
    return 'Die Einrichtung ist abgeschlossen' in html


def main():
    tmp = make_site()
    shots = os.environ.get('E2E_SHOTS') or os.path.join(tmp, 'shots')
    os.makedirs(shots, exist_ok=True)
    port = free_port(); base = f'http://127.0.0.1:{port}'
    log = open(os.path.join(tmp, 'server.log'), 'w')
    srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', tmp, os.path.join(REPO, 'scripts', 'e2e', 'router.php')], stdout=log, stderr=log)
    try:
        for _ in range(50):
            try:
                socket.create_connection(('127.0.0.1', port), 0.2).close(); break
            except OSError:
                time.sleep(0.1)
        check('Einrichtungsassistent', install(base))
        with sync_playwright() as p:
            opts = {'args': ['--no-sandbox']}
            if os.environ.get('E2E_CHROME'):
                opts['executable_path'] = os.environ['E2E_CHROME']
            browser = p.chromium.launch(**opts)
            run(browser, base, shots, tmp)
            browser.close()
    except Exception:
        traceback.print_exc(); check('Ablauf ohne Ausnahme', False, traceback.format_exc()[-300:])
    finally:
        srv.terminate()
    failed = [r for r in results if not r[1]]
    print(f"{len(results) - len(failed)} von {len(results)} Browser-Prüfungen bestanden")
    if failed and os.path.exists(os.path.join(tmp, 'server.log')):
        print(open(os.path.join(tmp, 'server.log')).read()[-2000:])
    if not os.environ.get('E2E_KEEP'):
        shutil.rmtree(tmp, ignore_errors=True)
    sys.exit(1 if failed else 0)


def run(browser, base, shots, tmp):
    page = browser.new_page(viewport={'width': 1400, 'height': 900})
    errors = []
    page.on('pageerror', lambda e: errors.append('pageerror: ' + str(e)[:200]))
    page.on('response', lambda r: r.status >= 500 and errors.append(f'http {r.status} {r.url[:120]}'))
    page.on('dialog', lambda d: d.accept())

    def tab(t, wait=2000):
        page.evaluate(f"document.querySelector('.tab[data-tab=\"{t}\"]').click()"); page.wait_for_timeout(wait)

    # Anmeldung
    page.goto(base + '/cms/'); page.wait_for_selector('#cmsLoginUser', state='visible')
    page.fill('#cmsLoginUser', USER); page.fill('#cmsLoginPass', PW); page.click('#cmsLoginSubmit')
    page.wait_for_selector('#cmsApp', state='visible', timeout=20000); page.wait_for_timeout(1500)
    check('Anmeldung über die Oberfläche', page.is_visible('#cmsApp'))
    tok = page.evaluate("sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')")

    # Jeder Bereich öffnet ohne JavaScript-Fehler
    tabs = page.evaluate("[...document.querySelectorAll('.tab[data-tab]')].map(t=>t.dataset.tab).filter((v,i,a)=>a.indexOf(v)===i)")
    bad = []
    for t in tabs:
        errors.clear(); tab(t, 1200)
        if errors: bad.append(t + ': ' + '; '.join(errors[:2]))
    check(f'Alle {len(tabs)} Bereiche ohne JavaScript-/Serverfehler', not bad, ' | '.join(bad[:5]))

    # Beitrag im Block-Editor schreiben und veröffentlichen
    errors.clear(); tab('news', 2500)
    page.click('#newsNewBtn'); page.wait_for_timeout(1500)
    page.fill('#newsTitle', 'E2E Beitrag')
    page.locator('#newsBody [contenteditable=true]').first.click()
    page.keyboard.type('Erster Absatz.'); page.keyboard.press('Enter'); page.keyboard.type('Zweiter Absatz.')
    check('Block-Editor: Enter erzeugt neuen Absatz ohne Zeichenverlust', page.evaluate("[...document.querySelectorAll('#newsBody .epb-text')].map(e=>e.textContent).join('|')") == 'Erster Absatz.|Zweiter Absatz.')
    page.keyboard.press('Enter'); page.keyboard.type('/'); page.wait_for_timeout(400)
    check('Block-Editor: „/“ öffnet das Block-Menü, nicht die Suche', page.evaluate("document.querySelectorAll('.epb-ins-item').length") > 3)
    page.keyboard.press('Escape'); page.keyboard.press('Backspace'); page.keyboard.press('Backspace')
    page.select_option('#newsStatus', 'published')
    page.evaluate("[...document.querySelectorAll('#newsEditorCard button')].find(b=>b.innerText.trim()==='Speichern').click()"); page.wait_for_timeout(2500)
    href = page.evaluate("(()=>{const r=[...document.querySelectorAll('#newsList .news-admin-row')].find(r=>r.innerText.includes('E2E Beitrag'));const a=r&&r.querySelector('a[title=\"Ansehen\"]');return a?a.href:''})()")
    check('Beitragsliste verlinkt die echte Beitragsadresse', href.startswith(base + '/') and '#news' not in href, href)
    st, html, _ = http('GET', href or base + '/')
    check('Veröffentlichter Beitrag erscheint auf der Website', st == 200 and 'Zweiter Absatz.' in html, st)
    page.screenshot(path=os.path.join(shots, 'beitrag.png'))

    # Feed und robots.txt
    st, body, h = http('GET', base + '/rss.xml')
    check('RSS-Feed liefert XML mit dem Beitrag', st == 200 and 'E2E Beitrag' in body, st)
    st, body, h = http('GET', base + '/robots.txt')
    check('robots.txt vorhanden', st == 200 and 'Sitemap:' in body, st)

    # Live Builder: Theme aktivieren, Text ändern, veröffentlichen
    errors.clear(); tab('livebuilder', 3500)
    if page.is_visible('#lbNotice a[data-activate]'):
        page.click('#lbNotice a[data-activate]'); page.wait_for_timeout(2500)
    page.fill('#lbFields input >> nth=0', 'E2E Startseite'); page.wait_for_timeout(3500)
    page.click('#lbPublish'); page.wait_for_timeout(3000)
    st, html, _ = http('GET', base + '/')
    check('Live Builder: veröffentlichte Änderung auf der Startseite', 'E2E Startseite' in html and not errors, '; '.join(errors[:2]))
    page.screenshot(path=os.path.join(shots, 'livebuilder.png'))

    # Mehrsprachige Website
    tab('settings', 2000)
    page.check('#mlOn'); page.check('[data-ml-lang="en"]'); page.click('#mlCard button:has-text("Speichern")'); page.wait_for_timeout(1500)
    tab('news', 2500)
    pid = page.evaluate("(()=>{const r=[...document.querySelectorAll('#newsList .news-admin-row')].find(r=>r.innerText.includes('E2E Beitrag'));return r?+r.querySelector('.news-row-check').dataset.id:0})()")
    page.evaluate(f"NewsMagazine.edit({pid})"); page.wait_for_timeout(2000)
    page.click('#newsTranslations button:has-text("Übersetzung anlegen")'); page.wait_for_timeout(3000)
    page.fill('#newsTitle', 'E2E Post English'); page.select_option('#newsStatus', 'published')
    page.evaluate("[...document.querySelectorAll('#newsEditorCard button')].find(b=>b.innerText.trim()==='Speichern').click()"); page.wait_for_timeout(2500)
    st, en, _ = http('GET', base + '/en/?nc=1'); st2, de, _ = http('GET', base + '/?nc=2')
    check('Mehrsprachige Website: /en/ zeigt die Übersetzung, / das Original', 'E2E Post English' in en and 'E2E Post English' not in de and 'hreflang="en"' in de, f'{st} {st2}')

    # Verwaltung auf Englisch
    page.evaluate("AdminLang.set('en')"); page.wait_for_timeout(4000)
    page.wait_for_selector('#cmsApp', state='visible')
    check('Verwaltung auf Englisch', page.evaluate("document.documentElement.lang") == 'en' and 'Media library' in page.inner_text('body') and 'Settings' in page.inner_text('body'))
    page.screenshot(path=os.path.join(shots, 'englisch.png'))
    page.evaluate("AdminLang.set('de')"); page.wait_for_timeout(3000)

    # Echtes WordPress (optional, braucht Netz)
    if os.environ.get('E2E_WORDPRESS'):
        errors.clear(); tab('wpengine', 3000)
        page.click('button:has-text("WordPress mit einem Klick einrichten")')
        for _ in range(120):
            page.wait_for_timeout(2000)
            if not page.evaluate("!!document.querySelector('#wpeRoot .fa-spinner')"): break
        check('Echtes WordPress mit einem Klick (SQLite)', 'Betriebsart: Aktiv' in page.inner_text('#wpeRoot'), page.inner_text('#wpeRoot')[:200])
        page.click('button:has-text("Website mit WordPress ausliefern")')
        for _ in range(90):
            page.wait_for_timeout(2000)
            if not page.evaluate("!!document.querySelector('#wpeRoot .fa-spinner')"): break
        st, html, _ = http('GET', base + '/?nc=3')
        check('Website wird von echtem WordPress ausgeliefert, Inhalte übernommen', st == 200 and 'wp-includes' in html and 'E2E Beitrag' in html, st)
        page.goto(base + '/'); page.wait_for_timeout(1500); page.screenshot(path=os.path.join(shots, 'wordpress-website.png'))


if __name__ == '__main__':
    main()
