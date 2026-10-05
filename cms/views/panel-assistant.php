<section id="panel-assistant" class="panel">
  <div class="card">
    <div class="th">
      <div class="tt"><i class="fas fa-wand-magic-sparkles"></i>KI-Assistent im Portal</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn-g" onclick="AssistantManager.testAll()"><i class="fas fa-plug-circle-check"></i> Alle Anbieter testen</button>
        <button class="btn-a" onclick="AssistantManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button>
      </div>
    </div>
    <p class="hint" style="margin:0 0 10px"><?php if(rrw_pack_available()): ?>Der Assistent sitzt oben rechts im Portal (auch mobil). Er beantwortet Fragen zu RicoReWi, AnMaCha und SenderWelt, nennt den laufenden Titel, den Sendeplan und die nächsten Sendungen, stellt Sender und Podcast vor und leitet Nachrichten oder Sprachnachrichten der Hörer direkt an Studiomail im <?=rrw_product_h(rrw_product_control_center())?> weiter. Hörerzahlen nennt er nie.<?php else: ?>Der Assistent beantwortet Fragen auf deiner Website und in deinen Radio-Apps, nennt den laufenden Titel und den Sendeplan deiner Sender und stellt News vor. Hörerzahlen nennt er nie.<?php endif; ?> Es wird immer der erste Anbieter genommen, der tatsächlich antwortet – Hörer wählen kein Modell. Antwortet kein Anbieter, antwortet der Assistent direkt aus den Live-Daten (ohne KI).</p>
    <div id="assistantEditor"></div>
  </div>
</section>
