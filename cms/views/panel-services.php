<section id="panel-services" class="panel">
      <div class="card">
        <div class="th">
          <div><div class="tt"><i class="fas fa-plug"></i>Zusammenhängende Dienste</div><div class="hint"><?=rrw_pack_available()?'Bekannte AnMaCha-/RicoReWi-Dienste werden automatisch erkannt. Manuell eingetragene Werte überschreiben nur die jeweilige Vorgabe.':'Deine eigenen Dienste: Links, Notizen und Erreichbarkeitsprüfung.'?></div></div>
          <div style="display:flex;gap:7px"><button class="btn-g" onclick="loadServiceStatus()"><i class="fas fa-heart-pulse"></i> Status prüfen</button><button class="btn-a" onclick="saveServices()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        </div>
        <div id="servicesEditor"></div>
        <div id="serviceStatusHost" class="svc-status-grid"></div>
      </div>
    </section>
