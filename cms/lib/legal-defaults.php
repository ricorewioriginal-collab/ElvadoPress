<?php
declare(strict_types=1);
// Standardtexte für Impressum und Datenschutzerklärung (RicoReWi Radio und SenderWelt).
// Grundlage sind die bisherigen Seiten bei versteckmich.de; Abschnitte, die nur den Dienst von versteckmich.de betreffen
// (Hosting dort, Webanalyse, Marketing-Pixel, Zahlungsdienste, Kontaktformular usw.), wurden ersetzt durch das, was dieses Portal
// tatsächlich tut. Die Texte werden einmalig in CMS › Rechtliches übernommen und sind dort frei bearbeitbar.
// Keine Rechtsberatung: Inhalt bei Änderungen am Portal (neue Dienste, neue Anbieter) bitte nachziehen.

function rrw_legal_default_imprint(): string {
    return <<<'HTML'
<h2>Angaben gemäß § 5 DDG</h2>
<h3>Betreiber</h3>
<p><strong>Ricardo Ramon Reimer Wiebe</strong><br>Künstlername: RicoReWi, AnMaCha<br>Geschäftsmäßiger Anbieter</p>
<h3>Postanschrift</h3>
<p>c/o SourceArt · VM-00001652<br>Fritz-Thiele-Straße 3<br>28279 Bremen-Obervieland<br>Deutschland</p>
<h3>Kontakt</h3>
<p>E-Mail: <a href="mailto:ricorewioriginal@gmail.com">ricorewioriginal@gmail.com</a><br>Telefon: 015679 606760</p>
<h3>Umsatzsteuer</h3>
<p>Als Kleinunternehmer im Sinne von § 19 UStG wird keine Umsatzsteuer berechnet.</p>
<h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
<p>Ricardo Ramon Reimer Wiebe<br>c/o SourceArt · VM-00001652<br>Fritz-Thiele-Straße 3<br>28279 Bremen-Obervieland<br>Deutschland</p>
<h3>Angebote dieses Impressums</h3>
<p>Dieses Impressum gilt für das Radioportal <strong>RicoReWi Radio</strong> (ricorewi-radio.de), das Radioportal <strong>SenderWelt</strong> (senderwelt.de) sowie die zugehörigen Apps für Android und Windows und den Alexa-Skill.</p>
<h3>Weitere Informationen</h3>
<p>Unsere Radiostreams sind Sender von <a href="https://laut.fm" target="_blank" rel="noopener">laut.fm</a>. Alle Lizenzen werden von der laut.ag und deren Partnern übernommen. RicoReWi und AnMaCha verwalten diese Streams lediglich. Ansprechpartner bei Problemen und bei rechtlichen Ansprüchen bezüglich der Radiostreams bleibt laut.fm.</p>
<p>Im Radioverzeichnis von SenderWelt werden zusätzlich Sender von laut.fm und aus dem Verzeichnis radio-browser.info angezeigt. Streams, Programme, Namen und Inhalte dieser Sender gehören ihren jeweiligen Betreibern; wir hosten sie nicht und sind dafür nicht verantwortlich. Hinweise auf Rechtsverletzungen bitte an die oben genannte E-Mail-Adresse, wir prüfen sie umgehend.</p>
<h3>Haftung für Links</h3>
<p>Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Für diese fremden Inhalte können wir keine Gewähr übernehmen. Verantwortlich ist immer der jeweilige Anbieter oder Betreiber der Seiten. Bei Bekanntwerden von Rechtsverletzungen entfernen wir derartige Links umgehend.</p>
<h3>Urheberrecht</h3>
<p>Die durch uns erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Beiträge Dritter sind als solche gekennzeichnet. Logos und Marken der Partner und Sender gehören den jeweiligen Inhabern. Vervielfältigung, Bearbeitung und Verbreitung außerhalb der Grenzen des Urheberrechts bedürfen der schriftlichen Zustimmung.</p>
<p><a href="#datenschutz">Zur Datenschutzerklärung</a></p>
HTML;
}

function rrw_legal_default_privacy(): string {
    return <<<'HTML'
<p><strong>Stand: 4. Oktober 2026</strong></p>
<p>Diese Datenschutzerklärung gilt für die Radioportale <strong>RicoReWi Radio</strong> (ricorewi-radio.de) und <strong>SenderWelt</strong> (senderwelt.de) sowie die zugehörigen Apps für Android und Windows und den Alexa-Skill. Wir halten die Verarbeitung deiner Daten so gering wie möglich: Das Portal setzt <strong>keine Cookies</strong>, nutzt <strong>keine Webanalyse</strong> und <strong>keine Werbe- oder Tracking-Dienste</strong>.</p>

<h2>1. Verantwortlicher</h2>
<p>Verantwortlich für die Datenverarbeitung im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:</p>
<p>Ricardo Ramon Reimer Wiebe<br>c/o SourceArt · VM-00001652<br>Fritz-Thiele-Straße 3<br>28279 Bremen-Obervieland<br>Deutschland<br>E-Mail: <a href="mailto:ricorewioriginal@gmail.com">ricorewioriginal@gmail.com</a></p>
<p>Weitere Angaben findest du im <a href="#impressum">Impressum</a>.</p>

<h2>2. Hosting und Server-Protokolle</h2>
<p>Das Portal wird bei der <strong>netcup GmbH</strong> (Emmy-Noether-Straße 10, 76131 Karlsruhe) auf Servern in Deutschland betrieben. Mit dem Anbieter besteht ein Vertrag zur Auftragsverarbeitung.</p>
<p>Bei jedem Aufruf des Portals verarbeitet der Webserver technisch notwendige Verbindungsdaten und speichert sie in Protokolldateien: IP-Adresse, Datum und Uhrzeit, aufgerufene Seite oder Datei, Übertragungsmenge, Statuscode sowie Browser und Betriebssystem. Das ist nötig, um die Seiten auszuliefern, Fehler zu beheben und Missbrauch abzuwehren. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einem sicheren und stabilen Betrieb). Die Protokolle werden nur so lange gespeichert, wie es dafür erforderlich ist, und danach gelöscht.</p>
<p>Die Verbindung ist durch SSL/TLS verschlüsselt (erkennbar an „https://“ in der Adresszeile).</p>

<h2>3. Keine Cookies, lokale Speicherung im Browser</h2>
<p>Wir setzen keine Cookies. Damit das Portal bequem funktioniert, speichert dein Browser einige Einstellungen lokal auf deinem Gerät (Local Storage bzw. Cache der Web-App): zum Beispiel Lautstärke, deine Favoriten, gemerkte Erinnerungen an Sendungen und deine Auswahl im Cookie-Hinweis. Diese Daten verlassen dein Gerät nicht und dienen nur der von dir gewünschten Funktion. Die Web-App (PWA) speichert außerdem Seiten und Bilder zwischen, damit sie schneller startet und offline einen letzten Stand zeigt. Rechtsgrundlage ist § 25 Abs. 2 Nr. 2 TDDDG (unbedingt erforderlich) in Verbindung mit Art. 6 Abs. 1 lit. f DSGVO. Du kannst diese Daten jederzeit in den Browsereinstellungen löschen.</p>

<h2>4. Einwilligung für externe Inhalte (Cookie-Hinweis)</h2>
<p>Beim ersten Besuch fragen wir dich, ob externe Inhalte geladen werden dürfen. Ohne deine Zustimmung (Art. 6 Abs. 1 lit. a DSGVO, § 25 Abs. 1 TDDDG) werden an Stelle der Inhalte von YouTube, Vimeo, TikTok, Instagram und anderen fremden Einbettungen nur Platzhalter angezeigt, und es werden keine Daten an diese Anbieter übertragen. Du kannst einen einzelnen Inhalt „einmal laden“ oder „immer zulassen“. Deine Auswahl speichern wir 12 Monate lokal in deinem Browser. Du kannst sie jederzeit mit Wirkung für die Zukunft ändern oder widerrufen: Link <strong>„Cookie-Einstellungen“</strong> im Seitenfuß (in der mobilen Ansicht auch im Menü „Mehr“).</p>

<h2>5. Radio-Streams und Senderdaten (laut.fm)</h2>
<p>Unsere Sender werden von <strong>laut.fm</strong> (laut.ag) bereitgestellt. Wenn du einen Sender abspielst oder dir Sender, Sendepläne, Titelinformationen und Cover anzeigen lässt, verbindet sich dein Browser (bzw. deine App) direkt mit den Servern von laut.fm. Dabei erhält laut.fm technisch notwendige Daten wie deine IP-Adresse. Das ist erforderlich, damit du den von dir gewählten Stream hören kannst (Art. 6 Abs. 1 lit. b DSGVO, hilfsweise lit. f). Für diese Verarbeitung ist laut.fm eigenverantwortlich; Informationen findest du in den Datenschutzhinweisen von laut.fm auf <a href="https://laut.fm" target="_blank" rel="noopener">laut.fm</a>. Weder RicoReWi noch AnMaCha hosten die Streams.</p>
<p>Den Sendeplan der Sender rufen wir zusätzlich über unseren Server von laut.fm ab und halten ihn dort kurz zwischen (ca. 5 Minuten), damit das Portal schneller und mit weniger Abrufen arbeitet. Dabei werden keine Daten von dir übermittelt.</p>

<h2>6. Radioverzeichnis (SenderWelt)</h2>
<p>Das Verzeichnis zeigt Sender von laut.fm und aus dem Verzeichnis <strong>radio-browser.info</strong>. Suchanfragen leitet unser Server an diese Verzeichnisse weiter, dabei wird deine IP-Adresse nicht übermittelt; die Ergebnisse speichern wir kurz zwischen. Wenn du einen Sender aus dem Verzeichnis abspielst, verbindet sich dein Browser direkt mit dem Server des jeweiligen Senderbetreibers (dessen Stream); dabei erhält der Betreiber technisch notwendige Daten wie deine IP-Adresse. Für diese fremden Streams sind die Betreiber verantwortlich. Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO.</p>

<h2>7. Externe Inhalte nach deiner Einwilligung</h2>
<p>Wenn du externe Inhalte erlaubst, stellt dein Browser eine Verbindung zu den Servern des jeweiligen Anbieters her. Dabei werden Daten wie deine IP-Adresse und Geräteinformationen übertragen, und die Anbieter können eigene Cookies oder ähnliche Technologien einsetzen. Eine Übermittlung in Drittländer (insbesondere USA) ist möglich; sie stützt sich auf den Angemessenheitsbeschluss zum EU-US Data Privacy Framework bzw. auf EU-Standardvertragsklauseln. Rechtsgrundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO, § 25 Abs. 1 TDDDG).</p>
<ul>
<li><strong>YouTube</strong> (Videos im erweiterten Datenschutzmodus, youtube-nocookie.com): Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland.</li>
<li><strong>Vimeo</strong>: Vimeo.com, Inc., 330 West 34th Street, 5th Floor, New York, NY 10001, USA.</li>
<li><strong>TikTok</strong> (Videos und Profil-Einbettungen): TikTok Technology Limited, 10 Earlsfort Terrace, Dublin, D02 T380, Irland.</li>
<li><strong>Instagram</strong> (Profil-Einbettungen): Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5, Irland.</li>
<li>Weitere im Portal eingebettete Inhalte Dritter, die entsprechend gekennzeichnet sind (z. B. fremde Seiten in Einbettungs-Widgets).</li>
</ul>
<p>Informationen zur Verarbeitung findest du in den Datenschutzerklärungen der Anbieter.</p>

<h2>8. Verlinkungen zu Plattformen und Shops</h2>
<p>Im Portal und im Player verlinken wir auf Plattformen wie TikTok, Instagram, Spotify, Amazon Music, YouTube Music, Apple Music, TuneIn und radio.de sowie auf unseren Shop und unsere Podcasts. Das sind einfache Links: Erst wenn du darauf klickst, verlässt du das Portal, und es gelten die Datenschutzbestimmungen des jeweiligen Anbieters. Wir betreiben Profile auf TikTok und Instagram; für deren Seiten-Statistiken kann eine gemeinsame Verantwortlichkeit nach Art. 26 DSGVO mit dem Plattformbetreiber bestehen.</p>
<h3>Fanshops (Spreadshop)</h3>
<p>Unsere Fanshops werden über Spreadshop betrieben (sprd.net SE, Gutenbergstraße 1, 04103 Leipzig). Die Shop-Software wird erst geladen, wenn du auf der Seite „Shops“ ausdrücklich einen Shop öffnest. Dabei verbindet sich dein Browser mit den Servern von Spreadshop, die IP-Adresse und Gerätedaten erhalten und für Bestellungen, Zahlung und Versand verantwortlich sind. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Vertragsanbahnung und -erfüllung), hilfsweise lit. f. Bestellungen wickelt Spreadshop in eigener Verantwortung ab; wir erhalten keine Zahlungsdaten.</p>

<h2>9. Kommentare</h2>
<p>Unter News-Beiträgen kannst du Kommentare schreiben. Dafür verarbeiten wir den von dir eingegebenen Namen (oder ein Pseudonym), den Kommentartext und den Zeitpunkt. Kommentare werden vor der Veröffentlichung geprüft. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Bereitstellung der Funktion) und lit. f (Missbrauchsabwehr). Wir speichern Kommentare, bis du ihre Löschung verlangst oder der Beitrag entfällt. Bitte gib keine personenbezogenen Daten Dritter an.</p>

<h2>10. Mitmach-Funktionen (Voting, Studiomail, Voicemail, Musikwunsch, Umfragen)</h2>
<p>Diese Funktionen laufen über unser Control Center auf unserem eigenen Server. Wir verarbeiten die Angaben, die du dort machst (zum Beispiel Name oder Pseudonym, Nachricht, Musikwunsch, Abstimmung oder bei der Voicemail eine Sprachaufnahme), um sie zu bearbeiten und im Programm zu berücksichtigen. Der Zugriff auf das Mikrofon erfolgt nur, nachdem du ihn in deinem Browser erlaubt hast. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO, bei freiwilligen Angaben lit. a bzw. lit. f. Daten löschen wir, sobald sie für den Zweck nicht mehr erforderlich sind; du kannst die Löschung jederzeit verlangen.</p>

<h2>11. KI-Assistent</h2>
<p>Wenn du den Radio-Assistenten nutzt, wird deine Frage an unseren Server gesendet und zur Beantwortung an einen externen KI-Dienst weitergegeben, dazu gegebenenfalls Hinweise zu Sendern, Sendeplan oder Wissensquellen. Für allgemeine Fragen kann unser Server außerdem öffentliche Quellen abfragen (zum Beispiel Wikipedia, Open-Meteo für Wetter, iTunes-Suche für Podcasts). Deine IP-Adresse wird dabei nicht weitergegeben. Beim KI-Anbieter kann eine Verarbeitung außerhalb der EU stattfinden. <strong>Bitte gib keine persönlichen Daten in den Assistenten ein.</strong> Die Nutzung ist freiwillig; Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (von dir ausgelöste Funktion), hilfsweise lit. f.</p>

<h2>12. Benachrichtigungen (Web-Push)</h2>
<p>Wenn du Erinnerungen an Sendungen als Benachrichtigung aktivierst, fragt dein Browser nach deiner Erlaubnis. Wir speichern dann die technische Adresse deines Browsers für Push-Nachrichten (Endpunkt und Schlüssel) sowie die von dir ausgewählten Sender und senden die Nachrichten über den Push-Dienst deines Browser-Herstellers (zum Beispiel Google, Mozilla oder Apple). Rechtsgrundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO). Du kannst sie jederzeit in den Browsereinstellungen oder im Portal widerrufen; dann löschen wir die gespeicherten Daten.</p>

<h2>13. Apps für Android und Windows</h2>
<p>Die Apps laden Sender, News, Sendeplan und Podcasts aus unserem Portal und von laut.fm (siehe Abschnitt 5). Folgende Daten verarbeiten wir zusätzlich, sofern diese Funktionen im Betrieb aktiviert sind:</p>
<ul>
<li><strong>Nutzungs- und Hörstatistik:</strong> App-Version und Plattform, gehörter Sender und Hördauer, verbunden mit einer pseudonymen Gerätekennung (ein mit einem geheimen Schlüssel gebildeter Hashwert, kein Klarname und keine Geräte-ID). Dazu wird anhand der IP-Adresse grob das Herkunftsland bestimmt; die IP-Adresse selbst wird dafür nicht gespeichert. Die Auswertung dient der Verbesserung des Angebots; Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO. Kennzahlen speichern wir höchstens 90 Tage.</li>
<li><strong>Fehlerberichte:</strong> bei Abstürzen technische Angaben (Fehlermeldung, App- und Android- bzw. Windows-Version) zur Fehlerbehebung (Art. 6 Abs. 1 lit. f DSGVO).</li>
<li><strong>Updates:</strong> Direkt heruntergeladene Apps prüfen bei uns auf neue Versionen. Über unser F-Droid-Verzeichnis oder die Stores aktualisieren die jeweiligen Anbieter.</li>
<li><strong>Übertragen im WLAN (Cast/DLNA):</strong> Die App spricht dabei Geräte in deinem lokalen Netz an; es gehen keine Daten an uns.</li>
</ul>
<p>Du kannst der Auswertung widersprechen (Art. 21 DSGVO), indem du uns schreibst.</p>

<h2>14. Alexa-Skill</h2>
<p>Bei Nutzung des Alexa-Skills verarbeitet Amazon deine Sprachbefehle in eigener Verantwortung nach den Datenschutzhinweisen von Amazon. Unser Skill-Dienst erhält nur, was zur Ausführung nötig ist (welcher Sender oder welche Auskunft gewünscht ist) und ruft Sender- und Sendeplandaten bei uns bzw. bei laut.fm ab. Sofern im Betrieb aktiviert, zählen wir Aufrufe anonym (Sender und Befehlsname, ohne Personenbezug).</p>

<h2>15. Kontaktaufnahme</h2>
<p>Wenn du uns per E-Mail schreibst, verarbeiten wir deine Angaben (E-Mail-Adresse, Inhalt der Nachricht) zur Bearbeitung deiner Anfrage und für Anschlussfragen. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO, sonst lit. f. Wir löschen die Daten, sobald sie nicht mehr erforderlich sind und keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p>

<h2>16. Speicherdauer und Datensicherheit</h2>
<p>Wir speichern personenbezogene Daten nur so lange, wie es für den jeweiligen Zweck erforderlich ist oder gesetzliche Fristen bestehen. Wir schützen die Daten durch technische und organisatorische Maßnahmen, unter anderem verschlüsselte Übertragung (TLS) und Zugriffsbeschränkungen.</p>

<h2>17. Deine Rechte</h2>
<p>Du hast folgende Rechte gegenüber uns als Verantwortlichem:</p>
<ul>
<li>Auskunft (Art. 15 DSGVO)</li>
<li>Berichtigung (Art. 16 DSGVO)</li>
<li>Löschung (Art. 17 DSGVO)</li>
<li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
<li>Datenübertragbarkeit (Art. 20 DSGVO)</li>
<li>Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21 DSGVO)</li>
<li>Widerruf erteilter Einwilligungen mit Wirkung für die Zukunft (Art. 7 Abs. 3 DSGVO)</li>
</ul>
<p>Schreibe dazu einfach an <a href="mailto:ricorewioriginal@gmail.com">ricorewioriginal@gmail.com</a>.</p>
<h3>Beschwerderecht bei der Aufsichtsbehörde</h3>
<p>Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren, insbesondere in dem Mitgliedstaat deines Aufenthaltsorts, deines Arbeitsplatzes oder des Orts des mutmaßlichen Verstoßes (Art. 77 DSGVO). Für den Verantwortlichen zuständig ist die Landesbeauftragte für Datenschutz und Informationsfreiheit der Freien Hansestadt Bremen, Arndtstraße 1, 27570 Bremerhaven.</p>

<h2>18. Änderungen</h2>
<p>Wir passen diese Erklärung an, wenn sich das Angebot oder die Rechtslage ändert. Es gilt die jeweils hier veröffentlichte Fassung.</p>
<p><a href="#impressum">Zum Impressum</a></p>
HTML;
}
