<section id="panel-sysstatus" class="panel">
  <div class="card">
    <div class="th">
      <div><div class="wp-page-title">Systemstatus &amp; Updates</div><div class="wp-subtitle">Zustand von ElvadoPress, Server und Erweiterungen – mit verständlichen Erklärungen – und das Update-Center für Core, WordPress, Plugins, Themes und Pakete.</div></div>
      <div class="ap-add"><button class="btn-g" type="button" onclick="SysStatus.load()"><i class="fas fa-arrows-rotate"></i> Aktualisieren</button><button class="btn-a" type="button" id="ssCheck" onclick="SysStatus.check()"><i class="fas fa-magnifying-glass"></i> Jetzt nach Updates suchen</button></div>
    </div>
    <div class="lb-tabs" role="tablist" style="margin-bottom:12px"><button type="button" class="on" data-sstab="status">Systemstatus</button><button type="button" data-sstab="updates">Update-Center</button></div>
    <div id="ssRoot"><p class="hint">Lade …</p></div>
  </div>
</section>
