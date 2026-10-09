<section id="panel-assistant" class="panel">
  <div class="card">
    <div class="th">
      <div class="tt"><i class="fas fa-wand-magic-sparkles"></i>KI-Assistent</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn-g" onclick="AssistantManager.testAll()"><i class="fas fa-plug-circle-check"></i> Alle Anbieter testen</button>
        <button class="btn-a" onclick="AssistantManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button>
      </div>
    </div>
    <p class="hint" style="margin:0 0 10px">Dein eigener KI-Assistent: ein Chat-Fenster unten rechts auf deiner Website (mit WordPress-Theme), das Fragen aus deinen Beiträgen und deinem Wissen beantwortet. Wähle unten Anbieter und Modelle. Es wird immer der erste Anbieter genommen, der tatsächlich antwortet – Besucher wählen kein Modell. Antwortet kein Anbieter, antwortet der Assistent direkt aus den Inhalten der Website (ohne KI).</p>
    <div id="assistantEditor"></div>
  </div>
</section>
