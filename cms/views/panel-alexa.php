<section id="panel-alexa" class="panel">
  <div class="card">
    <div class="th" style="margin-bottom:10px">
      <div><div class="wp-page-title">Alexa-Skill</div><div class="wp-subtitle"><?= rrw_pack_available()?'Der Skill „RicoReWi Radio“ für Amazon Echo.':'Dein eigener Skill für Amazon Echo.' ?> Sender, Reihenfolge, Texte und Wartung steuerst du hier – der Skill holt sich die Einstellungen alle paar Minuten, ohne neue Prüfung bei Amazon. Was du bei Amazon einreichen musst, lädst du unten herunter.</div></div>
      <button class="btn-a" onclick="AlexaManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button>
    </div>
    <div id="alexaManager"></div>
  </div>
</section>
