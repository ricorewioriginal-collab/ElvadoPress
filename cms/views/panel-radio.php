<section id="panel-radio" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-radio"></i>Radio</div><div class="hint">Sender, Datenquelle und Sendeplan für das Theme „ElvadoPress Radio“. Als Quelle dient die laut.fm-API oder dein eigener Icecast-/Shoutcast-Server. Der Server fragt die Quelle ab und speichert das Ergebnis kurz zwischen – Besucher belasten deinen Streaming-Server nicht.</div></div>
          <div style="display:flex;gap:7px;flex-wrap:wrap"><a class="btn-g" href="/" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Website</a><button class="btn-a" onclick="RadioAdmin.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        </div>
        <div id="rdNotice" class="hint" style="display:none;margin-bottom:10px"></div>
        <div class="tt" style="margin:6px 0 8px"><i class="fas fa-tower-broadcast"></i>Sender</div>
        <div id="rdStations"></div>
        <div style="margin:8px 0 18px"><button class="btn-g" onclick="RadioAdmin.addStation()"><i class="fas fa-plus"></i> Sender hinzufügen</button></div>
        <div class="tt" style="margin:6px 0 8px"><i class="fas fa-sliders"></i>Anzeige</div>
        <div class="section-grid" style="margin-bottom:12px">
          <div><label class="news-lbl">Titelverlauf (Anzahl)</label><input id="rdHist" class="fc w-100" type="number" min="1" max="20"></div>
          <div><label class="news-lbl">Aktualisierung (Sekunden)</label><input id="rdPoll" class="fc w-100" type="number" min="10" max="120"></div>
          <div style="grid-column:1/-1"><label style="display:flex;gap:8px;align-items:center"><input id="rdCover" class="switch" type="checkbox"> Cover automatisch suchen (iTunes-Suche, serverseitig, zwischengespeichert)</label></div>
          <div style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap"><label><input id="rdShowHistory" class="switch" type="checkbox"> Titelverlauf</label><label><input id="rdShowSchedule" class="switch" type="checkbox"> Sendeplan</label><label><input id="rdShowStations" class="switch" type="checkbox"> Senderliste</label><label><input id="rdShowNews" class="switch" type="checkbox"> News</label> <span class="hint">(auf der Startseite)</span></div>
        </div>
        <div class="tt" style="margin:6px 0 8px"><i class="fas fa-link"></i>Links auf der Startseite</div>
        <div id="rdLinks"></div>
        <div style="margin:8px 0 18px"><button class="btn-g" onclick="RadioAdmin.addLink()"><i class="fas fa-plus"></i> Link</button></div>
        <div class="tt" style="margin:6px 0 4px"><i class="fas fa-calendar-days"></i>Sendeplan</div>
        <div class="hint" style="margin-bottom:8px">Leer lassen: bei laut.fm wird der Sendeplan automatisch aus den Playlists des Senders übernommen. Eigene Einträge haben Vorrang.</div>
        <div id="rdSched"></div>
        <div style="margin:8px 0"><button class="btn-g" onclick="RadioAdmin.addSlot()"><i class="fas fa-plus"></i> Sendung</button></div>
        <div id="rdMsg" class="hint" style="margin-top:8px"></div>
      </div>
</section>
