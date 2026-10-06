<section id="panel-headerbuilder" class="panel">
  <div class="card">
    <div class="th"><div><div class="wp-page-title">Header-Builder</div><div class="wp-subtitle">Header-Elemente frei anordnen und konfigurieren.</div></div><button class="btn-a" type="button" onclick="HeaderBuilder.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
    <label style="display:flex;gap:8px;align-items:center;margin:12px 0"><input id="hbEnabled" type="checkbox"> Individuellen Header aktivieren</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <select id="hbNewType" class="fc" style="max-width:220px"><option value="link">Link / Button</option><option value="whatsapp">WhatsApp</option><option value="phone">Telefon</option><option value="email">E-Mail</option><option value="brand">Logo / Marke</option><option value="navigation">Navigation</option><option value="search">Suche</option><option value="live">Live-Element</option><option value="social">Social</option><option value="assistant">KI-Assistent</option></select>
      <button class="btn-g" type="button" onclick="HeaderBuilder.add()"><i class="fas fa-plus"></i> Element hinzufügen</button>
    </div>
    <div class="hint">Die Reihenfolge entspricht der Position im Header. Bestehende Websites bleiben unverändert, solange der individuelle Header deaktiviert ist.</div>
    <div id="hbItems" style="display:grid;gap:10px;margin-top:12px"></div>
  </div>
</section>
