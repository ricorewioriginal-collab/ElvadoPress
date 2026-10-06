/* KI-Entwickler: Plugins, Widgets und Themes aus einer Beschreibung. Entwurf (ai_dev_plan) → Code ansehen/ändern → Prüfung (ai_dev_check) → inaktiv installieren (ai_dev_install) →
   erst auf ausdrücklichen Klick aktivieren (wp_plugin_activate / wp_theme_activate). Nur Superadmin, in der Demo gesperrt. */
(function(){
  'use strict';
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
  var api=function(a,b){return window.cmsApi(a,b)};
  var toast=function(m,bad){try{window.cmsToast(m,!!bad)}catch(e){}};
  var KINDS={plugin:['Plugin','fa-plug','Erweiterung für Funktionen: Shortcodes, Hooks, Einstellungsseiten …'],widget:['Widget','fa-puzzle-piece','Baustein für Seitenleiste/Fußbereich, zusätzlich als Shortcode nutzbar'],theme:['Theme','fa-palette','Design: Kindtheme (nur CSS) oder eigenständiges Theme mit Vorlagen']};
  var EX={
    plugin:['Ein Plugin mit dem Shortcode [countdown datum="2026-12-24"], das einen Countdown mit Tagen, Stunden und Minuten anzeigt (nur JavaScript ohne externe Bibliotheken).','Ein Plugin, das unter jedem Beitrag eine Lesezeit-Angabe und einen „Zurück nach oben“-Link einblendet, mit Einstellungsseite zum Ein- und Ausschalten.'],
    widget:['Ein Widget „Öffnungszeiten“ mit Wochentagen und Zeiten, die man im Widget-Formular einträgt; heutiger Tag wird hervorgehoben.','Ein Widget „Spruch des Tages“, das aus einer im Formular gepflegten Liste täglich einen anderen Spruch zeigt.'],
    theme:['Ein warmes Holz-Design in Braun- und Beigetönen, mit großen Überschriften in Serifenschrift, runden Schaltflächen und luftigen Abständen.','Ein dunkles, minimalistisches Design mit Neon-Grün als Akzent, scharfen Kanten und monospaced Überschriften.']
  };
  var s={pick:'',kind:'plugin',base:'child',prompt:'',busy:false,err:'',plan:null,tab:0,instruction:'',confirm:false,result:null,checking:false,checkT:0};
  function host(){return document.getElementById('aiDev')}
  function open(){s.err='';load().then(draw)}
  function load(){return api('ai_status').then(function(d){s.status=d}).catch(function(){s.status={providers:[]}})}

  var CODEY=/claude|gpt-5|gpt-4\.1|o[134]\b|gemini.*(pro|2\.5|3)|deepseek|kimi|qwen|glm|grok|coder|codestral|opus|sonnet/i;
  function modelPick(){   // Modell für diese Aufgabe: „Standard“ (laut KI-Zentrale) oder ein bestimmtes Modell eines nutzbaren Anbieters
    var ps=(s.status&&s.status.providers)||[];if(!ps.length)return '';
    var o='<option value="">Standard (Einstellung „Entwickler“ in der Zentrale)</option>';
    ps.forEach(function(p){var ms=(p.models||[]).slice();if(p.model&&ms.indexOf(p.model)<0)ms.unshift(p.model);if(!ms.length)return;
      o+='<optgroup label="'+esc(p.label)+'">'+ms.map(function(m){var v=p.id+'|'+m;return '<option value="'+esc(v)+'"'+(s.pick===v?' selected':'')+'>'+(CODEY.test(m)?'★ ':'')+esc(m)+'</option>'}).join('')+'</optgroup>'});
    return '<div style="margin-top:10px"><label class="news-lbl">Modell für diese Aufgabe</label><select class="fc w-100" onchange="AiDev.model(this.value)">'+o+'</select><div class="hint">★ = für Programmieren gut geeignet. Große Modelle schreiben besseren Code, kosten aber mehr und brauchen länger (ein Entwurf nutzt bis zu 12 000 Ausgabe-Token). Die Kosten stellt dein Anbieter nach seiner Preisliste in Rechnung.</div></div>';
  }
  function model(v){s.pick=v}
  function reqBase(){var a=(s.pick||'').split('|');return a.length===2?{provider:a[0],model:a[1]}:{}}
  function draw(){var h=host();if(!h)return;h.innerHTML=s.plan?drawPlan():drawInput()}
  function drawInput(){
    var ok=s.status&&(s.status.providers||[]).length>0;
    return '<div class="aib-hero"><h1>Programmiere mit KI – direkt im Admin.</h1><p>Beschreibe ein Plugin, Widget oder Theme. Die KI schreibt den Code, du siehst ihn vorab, wir prüfen ihn automatisch – installiert wird immer <b>inaktiv</b>.</p></div>'
      +(s.status&&!ok?'<div class="card aib-warn"><b>Noch kein KI-Anbieter eingerichtet.</b> Lege in der <a href="#" onclick="cmsTab(\'aicenter\',document.querySelector(\'[data-tab=aicenter]\'));AiCenter.open();return false">KI-Zentrale</a> einen Anbieter an. Für Code empfehlen sich leistungsfähige Modelle (z. B. Claude, GPT-4.1, Gemini Pro).</div>':'')
      +'<div class="aib-box"><div class="aid-kinds">'+Object.keys(KINDS).map(function(k){return '<button type="button" class="aid-kind'+(s.kind===k?' on':'')+'" onclick="AiDev.kind(\''+k+'\')"><i class="fas '+KINDS[k][1]+'"></i><b>'+KINDS[k][0]+'</b><span>'+esc(KINDS[k][2])+'</span></button>'}).join('')+'</div>'
      +(s.kind==='theme'?'<div style="margin-top:10px"><label class="news-lbl">Aufbau</label><select class="fc" onchange="AiDev.base(this.value)"><option value="child" '+(s.base==='child'?'selected':'')+'>Design-Variante des Baukasten-Themes (nur CSS – am sichersten)</option><option value="standalone" '+(s.base==='standalone'?'selected':'')+'>Eigenständiges Theme mit PHP-Vorlagen</option></select></div>':'')
      +modelPick()
      +'<textarea id="aidPrompt" class="aib-text" rows="6" style="margin-top:12px" placeholder="Beschreibe, was entstehen soll …" oninput="AiDev.prompt(this.value)">'+esc(s.prompt)+'</textarea>'
      +'<div class="aib-chips">'+EX[s.kind].map(function(e,i){return '<button type="button" class="aib-chip" onclick="AiDev.example('+i+')">Beispiel '+(i+1)+'</button>'}).join('')+'</div>'
      +(s.err?'<div class="aib-err"><i class="fas fa-circle-exclamation"></i> '+esc(s.err)+'</div>':'')
      +'<div class="aib-go"><button class="aib-btn" '+(s.busy||!ok?'disabled':'')+' onclick="AiDev.plan()">'+(s.busy?'<i class="fas fa-spinner fa-spin"></i> Die KI schreibt den Code … (bis zu 2 Minuten)':'<i class="fas fa-wand-magic-sparkles"></i> Entwickeln')+'</button></div></div>'
      +'<p class="hint aib-foot">Der Auftrag wird an den in der KI-Zentrale gewählten Anbieter übertragen. KI-generierter Code kann Fehler enthalten – lies ihn vor dem Aktivieren und teste zuerst auf einer Kopie. Du bist für den Einsatz verantwortlich.</p>';
  }
  function checkBox(p){
    var errs=p.errors||[],w=p.warnings||[];
    return '<div class="aid-check">'
      +(errs.length?'<div class="aic-bad"><b><i class="fas fa-circle-xmark"></i> '+errs.length+' Fehler – Installation gesperrt</b></div><ul>'+errs.map(function(e){return '<li>'+esc(e)+'</li>'}).join('')+'</ul>':'<div class="aic-ok"><b><i class="fas fa-circle-check"></i> Automatische Prüfung bestanden</b> <span class="hint">(Syntax, gefährliche Funktionen, Pfade)</span></div>')
      +(w.length?'<div class="aic-warn" style="margin-top:6px"><b><i class="fas fa-triangle-exclamation"></i> '+w.length+' Hinweise – bitte lesen</b></div><ul>'+w.map(function(e){return '<li>'+esc(e)+'</li>'}).join('')+'</ul>':'')
      +'</div>';
  }
  function drawPlan(){
    var p=s.plan,f=p.files[s.tab]||p.files[0];
    var res=s.result;
    return '<div class="card"><div class="th"><div><div class="tt"><i class="fas '+KINDS[p.kind][1]+'"></i>'+esc(p.title)+' <span class="aic-badge">'+esc(KINDS[p.kind][0])+'</span></div><div class="hint">'+esc(p.description||'')+' · Ordner <code>'+esc(p.slug)+'</code> · KI: '+esc((p.meta||{}).provider)+' / '+esc((p.meta||{}).model)+'</div></div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn-g" onclick="AiDev.back()"><i class="fas fa-arrow-left"></i> Neu beginnen</button></div></div>'
      +(p.notes&&p.notes.length?'<div class="hint" style="margin:6px 0"><b>Hinweise zur Benutzung:</b> '+p.notes.map(esc).join(' · ')+'</div>':'')
      +checkBox(p)
      +'<div class="aid-tabs">'+p.files.map(function(x,i){return '<button type="button" class="aid-tab'+(i===s.tab?' on':'')+'" onclick="AiDev.tab('+i+')">'+esc(x.path)+'</button>'}).join('')+'</div>'
      +'<textarea id="aidCode" class="aid-code" spellcheck="false" oninput="AiDev.edit(this.value)">'+esc(f?f.content:'')+'</textarea>'
      +'<div class="hint">Du kannst den Code hier selbst ändern – die Prüfung läuft danach automatisch erneut.</div>'
      +'<div class="aid-refine"><label class="news-lbl">Anpassen mit KI</label><div class="aic-row"><input id="aidInstr" class="fc w-100" placeholder="z. B. „Mach die Schrift größer und füge eine Einstellungsseite hinzu“" value="'+esc(s.instruction)+'" oninput="AiDev.instr(this.value)"><button class="btn-g" '+(s.busy?'disabled':'')+' onclick="AiDev.refine()">'+(s.busy?'<i class="fas fa-spinner fa-spin"></i>':'<i class="fas fa-wand-magic-sparkles"></i> Anpassen')+'</button></div></div>'
      +(s.err?'<div class="aib-err">'+esc(s.err)+'</div>':'')
      +(res?drawResult(res,p):'<div class="aid-install">'+(p.warnings&&p.warnings.length?'<label class="wm-check"><input type="checkbox" '+(s.confirm?'checked':'')+' onchange="AiDev.confirmW(this.checked)"> Ich habe die Hinweise gelesen und möchte trotzdem installieren.</label>':'')
        +'<button class="btn-a" '+(!p.ok||s.busy||(p.warnings&&p.warnings.length&&!s.confirm)?'disabled':'')+' onclick="AiDev.install()"><i class="fas fa-download"></i> Installieren (inaktiv)</button></div>')
      +'</div>';
  }
  function drawResult(r,p){
    return '<div class="aid-install"><div class="aic-ok"><b><i class="fas fa-circle-check"></i> '+esc(KINDS[p.kind][0])+' „'+esc(r.slug)+'“ ist '+(r.updated?'aktualisiert':'installiert')+' – noch inaktiv.</b></div>'
      +(r.activated?'<div class="aic-ok">Aktiviert ✓</div>':'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><button class="btn-a" '+(s.busy?'disabled':'')+' onclick="AiDev.activate()"><i class="fas fa-power-off"></i> Jetzt aktivieren</button><span class="hint">Aktivieren führt den Code aus. Bei einem Fehler wird das '+(p.kind==='theme'?'Theme':'Plugin')+' wieder abgeschaltet – lies zuerst den Code.</span></div>')
      +(r.error?'<div class="aib-err">'+esc(r.error)+'</div>':'')+'</div>';
  }

  // ---- Eingaben
  function kind(k){s.kind=k;draw()}
  function base(b){s.base=b}
  function prompt(v){s.prompt=v}
  function example(i){s.prompt=EX[s.kind][i];draw()}
  function instr(v){s.instruction=v}
  function tab(i){s.tab=i;draw()}
  function confirmW(v){s.confirm=v;draw()}
  function back(){s.plan=null;s.result=null;s.err='';s.confirm=false;draw()}
  function edit(v){
    var f=s.plan.files[s.tab];if(!f)return;f.content=v;s.result=null;
    clearTimeout(s.checkT);s.checkT=setTimeout(recheck,700);
  }
  function recheck(){
    api('ai_dev_check',{plan:s.plan}).then(function(d){s.plan.errors=d.errors;s.plan.warnings=d.warnings;s.plan.ok=d.ok;var el=document.querySelector('.aid-check');if(el)el.outerHTML=checkBox(s.plan);var b=document.querySelector('.aid-install .btn-a');if(b)b.disabled=!d.ok||(d.warnings.length&&!s.confirm)}).catch(function(){});
  }

  // ---- Aktionen
  function run(body,isRefine){
    if(s.busy)return;s.busy=true;s.err='';draw();
    return api('ai_dev_plan',body).then(function(d){s.plan=d.plan;s.tab=0;s.confirm=false;s.result=null;if(isRefine)s.instruction='';s.busy=false;draw()}).catch(function(e){s.busy=false;s.err=e.message||'Der Entwurf konnte nicht erstellt werden.';draw()});
  }
  function plan(){
    if((s.prompt||'').trim().length<15){s.err='Bitte beschreibe genauer, was entstehen soll (ein bis zwei Sätze).';return draw()}
    run(Object.assign({kind:s.kind,prompt:s.prompt,base:s.base},reqBase()),false);
  }
  function refine(){
    if((s.instruction||'').trim().length<5){s.err='Bitte beschreibe die gewünschte Änderung.';return draw()}
    run(Object.assign({kind:s.plan.kind,prompt:s.prompt||s.plan.description||s.plan.title,base:s.plan.child?'child':'standalone',previous:s.plan.files,instruction:s.instruction,slug:s.plan.slug},reqBase()),true);
  }
  function install(){
    if(!confirm('„'+s.plan.title+'“ installieren? Der Code wird in den Ordner '+(s.plan.kind==='theme'?'wp-content/themes':'wp-content/plugins')+'/'+s.plan.slug+' geschrieben und bleibt inaktiv.'))return;
    s.busy=true;s.err='';draw();
    api('ai_dev_install',{plan:s.plan,confirm_warnings:s.confirm}).then(function(d){s.result=d;s.busy=false;draw();toast('Installiert (inaktiv) ✓')}).catch(function(e){s.busy=false;s.err=e.message||'Installation fehlgeschlagen';draw()});
  }
  function activate(){
    var r=s.result;if(!r)return;
    if(!confirm('Jetzt aktivieren? Der Code wird auf dieser Website ausgeführt.'))return;
    s.busy=true;draw();
    var p=r.kind==='theme'?api('wp_theme_activate',{slug:r.slug}):api('wp_plugin_activate',{file:r.plugin_file});
    p.then(function(){r.activated=true;s.busy=false;draw();toast('Aktiviert ✓')}).catch(function(e){r.error='Aktivierung fehlgeschlagen: '+(e.message||'Fehler')+' – bitte Code prüfen.';s.busy=false;draw()});
  }
  window.AiDev={model:model,open:open,kind:kind,base:base,prompt:prompt,example:example,instr:instr,tab:tab,confirmW:confirmW,back:back,edit:edit,plan:plan,refine:refine,install:install,activate:activate};
})();
