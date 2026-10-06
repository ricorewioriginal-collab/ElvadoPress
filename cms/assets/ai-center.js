/* KI-Zentrale: alle KI-Anbieter, Schlüssel, Modelle, eigenen Anbieter und Einsatzzwecke an einer Stelle (API: ai_config_get/save, ai_test, ai_models, ai_migrate_legacy, ai_logs).
   Der KI-Assistent (Website-Chat), die Texthilfen im Editor, der Website-Generator und der KI-Entwickler beziehen ihre Zugänge von hier. */
(function(){
  'use strict';
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
  var api=function(a,b){return window.cmsApi(a,b)};
  var toast=function(m,bad){try{window.cmsToast(m,!!bad)}catch(e){}};
  var TEMPLATES=[
    ['Eigener OpenAI-kompatibler Anbieter','Eigener Anbieter','','',true,false],
    ['Ollama (lokal auf diesem Server)','Ollama (lokal)','http://localhost:11434/v1','llama3.2',false,true],
    ['LM Studio (lokal auf diesem Server)','LM Studio (lokal)','http://localhost:1234/v1','local-model',false,true],
    ['Together AI','Together AI','https://api.together.xyz/v1','meta-llama/Llama-3.3-70B-Instruct-Turbo',true,false],
    ['xAI Grok','xAI Grok','https://api.x.ai/v1','grok-3-mini',true,false],
    ['Perplexity','Perplexity','https://api.perplexity.ai','sonar',true,false],
    ['Fireworks AI','Fireworks AI','https://api.fireworks.ai/inference/v1','accounts/fireworks/models/llama-v3p3-70b-instruct',true,false]
  ];
  var st=null,edit={},models={},tests={},busy=false;
  function host(){return document.getElementById('aiCenter')}

  function load(){
    var h=host();if(h&&!st)h.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Lädt …</div>';
    return api('ai_config_get').then(function(d){st=d.config;edit={custom:null,purposes:Object.assign({},st.purposes||{}),default_provider:st.default_provider||'',rate_limit:st.rate_limit,providers:{}};draw()})
      .catch(function(e){if(h)h.innerHTML='<div class="empty">'+esc(e.message||'Fehler')+(e.httpStatus===403?' – die KI-Zentrale ist nur für Administratoren.':'')+'</div>'});
  }
  function edits(id){return edit.providers[id]||(edit.providers[id]={})}
  function provs(){return st.providers}
  function usableNow(){return provs().filter(function(p){var e=edit.providers[p.id]||{};var en=e.enabled!==undefined?e.enabled:p.enabled;var hasKey=p.has_key||(e.api_key&&e.api_key!=='__clear__');return en&&(hasKey||!p.needs_key)})}

  function badge(t,c){return '<span class="aic-badge'+(c?' '+c:'')+'">'+esc(t)+'</span>'}
  function keyState(p){
    var e=edit.providers[p.id]||{};
    if(e.api_key==='__clear__')return '<span class="aic-warn"><i class="fas fa-key"></i> Schlüssel wird beim Speichern entfernt</span>';
    if(e.api_key)return '<span class="aic-ok"><i class="fas fa-key"></i> Neuer Schlüssel wird beim Speichern abgelegt</span>';
    if(p.key_source==='central')return '<span class="aic-ok"><i class="fas fa-key"></i> Schlüssel hinterlegt</span>';
    if(p.key_source==='assistant')return '<span class="aic-warn"><i class="fas fa-key"></i> Schlüssel noch im Assistenten – oben „Alte Schlüssel übernehmen“</span>';
    return p.needs_key?'<span class="aic-warn"><i class="fas fa-key"></i> Schlüssel nötig</span>':'<span class="aic-mute"><i class="fas fa-unlock"></i> Kein Schlüssel nötig</span>';
  }
  function providerCard(p){
    var e=edit.providers[p.id]||{};var en=e.enabled!==undefined?e.enabled:p.enabled;var model=e.model!==undefined?e.model:p.model;
    var ms=(models[p.id]&&models[p.id].list)||(p.models||[]).map(function(m){return {id:m}});
    var t=tests[p.id];
    var tr=t?(t.busy?'<span class="aic-mute"><i class="fas fa-spinner fa-spin"></i> Test läuft …</span>':(t.ok?'<span class="aic-ok"><i class="fas fa-circle-check"></i> OK · '+esc(t.model||'')+' · '+t.ms+' ms</span>':'<span class="aic-bad"><i class="fas fa-circle-xmark"></i> '+esc(t.error||'Fehler')+'</span>')):'';
    return '<div class="card aic-card" data-pid="'+esc(p.id)+'">'
      +'<div class="aic-head"><label class="wm-check"><input type="checkbox" '+(en?'checked':'')+' onchange="AiCenter.set(\''+esc(p.id)+'\',\'enabled\',this.checked)"> <b>'+esc(p.label)+'</b></label>'
      +(p.free?badge('kostenlos','good'):badge('kostenpflichtig'))+(p.custom?badge('eigener Anbieter'):'')+(!p.needs_key?badge('ohne Schlüssel'):'')+(p.verified?'':badge('Angaben prüfen','warn'))
      +'<span class="aic-grow"></span><span class="aic-state">'+keyState(p)+'</span></div>'
      +(p.note?'<div class="hint aic-note">'+esc(p.note)+'</div>':'')
      +'<div class="aic-grid">'
      +'<div><label class="news-lbl">API-Schlüssel'+(p.needs_key?'':' (optional)')+'</label><div class="aic-row"><input class="fc w-100" type="password" autocomplete="new-password" placeholder="'+(p.has_key?'•••••• (hinterlegt – leer lassen = behalten)':(p.needs_key?'Schlüssel einfügen':'nicht nötig'))+'" value="'+esc(e.api_key&&e.api_key!=='__clear__'?e.api_key:'')+'" oninput="AiCenter.set(\''+esc(p.id)+'\',\'api_key\',this.value)">'
      +(p.key_source==='central'?'<button class="btn-g" type="button" title="Gespeicherten Schlüssel entfernen" onclick="AiCenter.clearKey(\''+esc(p.id)+'\')"><i class="fas fa-trash"></i></button>':'')+'</div></div>'
      +'<div><label class="news-lbl">Modell</label><div class="aic-row"><input class="fc w-100" list="aicm_'+esc(p.id)+'" value="'+esc(model)+'" oninput="AiCenter.set(\''+esc(p.id)+'\',\'model\',this.value)"><datalist id="aicm_'+esc(p.id)+'">'+ms.map(function(m){return '<option value="'+esc(m.id)+'">'}).join('')+'</datalist>'
      +'<button class="btn-g" type="button" title="Beim Anbieter nachfragen, welche Modelle es gibt" onclick="AiCenter.loadModels(\''+esc(p.id)+'\')"><i class="fas fa-list"></i></button></div></div>'
      +(p.custom?'<div><label class="news-lbl">Bezeichnung</label><input class="fc w-100" value="'+esc(p.label)+'" oninput="AiCenter.setCustom(\''+esc(p.id)+'\',\'label\',this.value)"></div><div class="aic-row" style="align-items:flex-end;gap:14px"><label class="wm-check"><input type="checkbox" '+(p.needs_key?'checked':'')+' onchange="AiCenter.setCustom(\''+esc(p.id)+'\',\'needs_key\',this.checked)"> Schlüssel nötig</label><label class="wm-check"><input type="checkbox" '+(p.free?'checked':'')+' onchange="AiCenter.setCustom(\''+esc(p.id)+'\',\'free\',this.checked)"> kostenlos</label></div>':'')
      +(p.base_editable||p.custom?'<div style="grid-column:1/-1"><label class="news-lbl">Basis-Adresse'+(p.custom?' (OpenAI-kompatibel, ohne /chat/completions)':'')+'</label><input class="fc w-100" '+(p.custom?'disabled':'')+' value="'+esc(e.base_url!==undefined?e.base_url:p.base_url)+'" oninput="AiCenter.set(\''+esc(p.id)+'\',\'base_url\',this.value)"></div>':'')
      +'</div>'
      +'<div class="aic-actions"><button class="btn-g" type="button" onclick="AiCenter.test(\''+esc(p.id)+'\')"><i class="fas fa-plug-circle-check"></i> Testen</button><span class="aic-test">'+tr+'</span>'
      +(p.custom?'<span class="aic-grow"></span><button class="btn-d" type="button" onclick="AiCenter.removeCustom(\''+esc(p.id)+'\')"><i class="fas fa-trash"></i> Anbieter entfernen</button>':'')+'</div>'
      +(models[p.id]&&models[p.id].msg?'<div class="hint">'+esc(models[p.id].msg)+'</div>':'')
      +'</div>';
  }
  function purposeSel(key,label,hint){
    var cur=key==='default'?edit.default_provider:(edit.purposes[key]||'');
    var opts='<option value="">Automatisch (erster nutzbarer Anbieter)</option>'+usableNow().map(function(p){return '<option value="'+esc(p.id)+'" '+(p.id===cur?'selected':'')+'>'+esc(p.label)+'</option>'}).join('');
    return '<div><label class="news-lbl">'+esc(label)+'</label><select class="fc w-100" onchange="AiCenter.setPurpose(\''+key+'\',this.value)">'+opts+'</select>'+(hint?'<div class="hint" style="font-size:.66rem;margin-top:3px">'+esc(hint)+'</div>':'')+'</div>';
  }
  function draw(){
    var h=host();if(!h||!st)return;
    var groups={},order=[];provs().forEach(function(p){if(!groups[p.group]){groups[p.group]=[];order.push(p.group)}groups[p.group].push(p)});
    var pref=['Direkt','Kostenlos / günstig','Smart Routing','Kostenlos ohne Schlüssel','Eigene Anbieter'];
    order.sort(function(a,b){var x=pref.indexOf(a),y=pref.indexOf(b);return (x<0?99:x)-(y<0?99:y)});
    var legacy=(st.legacy_keys||[]);
    var n=usableNow().length;
    h.innerHTML='<div class="card">'
      +'<div class="th"><div><div class="tt"><i class="fas fa-brain"></i>KI-Zentrale</div><div class="hint">Alle KI-Anbieter, Schlüssel und Modelle an einer Stelle. Der KI-Assistent der Website, die Texthilfen im Editor, der Website-Generator und der KI-Entwickler nutzen diese Zugänge.</div></div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn-g" onclick="AiCenter.load()"><i class="fas fa-rotate"></i> Neu laden</button><button class="btn-a" onclick="AiCenter.save()"'+(busy?' disabled':'')+'><i class="fas fa-floppy-disk"></i> Speichern</button></div></div>'
      +'<div class="aic-status">'+(n?'<span class="aic-ok"><i class="fas fa-circle-check"></i> '+n+' Anbieter einsatzbereit</span>':'<span class="aic-warn"><i class="fas fa-triangle-exclamation"></i> Noch kein Anbieter einsatzbereit – Schlüssel eintragen oder einen kostenlosen Dienst ohne Schlüssel einschalten.</span>')+'</div>'
      +(legacy.length?'<div class="danger-note" style="margin-top:12px"><b>Alte Schlüssel gefunden.</b> Für '+esc(legacy.join(', '))+' ist noch ein Schlüssel im KI-Assistenten gespeichert. <button class="btn-a" style="margin-left:8px" onclick="AiCenter.migrate()"><i class="fas fa-arrow-right-to-bracket"></i> Alte Schlüssel übernehmen</button></div>':'')
      +'</div>'
      +'<div class="card"><div class="th"><div class="tt"><i class="fas fa-bullseye"></i>Einsatzzwecke</div></div><div class="aic-grid">'
      +purposeSel('default','Standard-Anbieter','Wenn für einen Zweck nichts gewählt ist.')
      +Object.keys(st.purpose_labels||{}).map(function(k){return purposeSel(k,st.purpose_labels[k])}).join('')
      +'<div><label class="news-lbl">Max. Anfragen je Benutzer und Stunde</label><input class="fc w-100" type="number" min="5" max="1000" value="'+esc(edit.rate_limit)+'" oninput="AiCenter.setRate(this.value)"></div>'
      +'</div><p class="hint" style="margin:10px 0 0"><i class="fas fa-circle-info"></i> Der <a href="#" onclick="cmsTab(\'assistant\',document.querySelector(\'[data-tab=assistant]\'));window.AssistantManager&&AssistantManager.render();return false">KI-Assistent</a> (Chat für Besucher) legt unter „KI-Assistent“ nur fest, welche dieser Anbieter er in welcher Reihenfolge nutzt.</p></div>'
      +order.map(function(g){return '<div class="aic-group">'+esc(g)+'</div>'+groups[g].map(providerCard).join('')}).join('')
      +'<div class="card"><div class="th"><div class="tt"><i class="fas fa-plus"></i>Eigenen Anbieter hinzufügen</div></div>'
      +'<p class="hint" style="margin:0 0 8px">Jede OpenAI-kompatible Schnittstelle, auch lokal (Ollama, LM Studio) – nach dem Hinzufügen Modell und Schlüssel eintragen und speichern.</p>'
      +'<div class="aic-row"><select id="aicTpl" class="fc" style="max-width:360px">'+TEMPLATES.map(function(t,i){return '<option value="'+i+'">'+esc(t[0])+'</option>'}).join('')+'</select><button class="btn-g" onclick="AiCenter.addCustom(document.getElementById(\'aicTpl\').value)"><i class="fas fa-plus"></i> Hinzufügen</button></div></div>'
      +'<div class="card"><div class="th"><div class="tt"><i class="fas fa-list-check"></i>Nutzung (letzte 30 Tage)</div><button class="btn-g" onclick="AiCenter.logs()"><i class="fas fa-rotate"></i> Anzeigen</button></div><div id="aicLogs" class="hint">Protokoll ohne Anfragetexte; benötigt SQLite oder eine Datenbank.</div></div>';
  }

  function set(id,k,v){var e=edits(id);e[k]=v;if(k==='enabled'||k==='api_key')redrawKeepFocus()}
  var t0=0;function redrawKeepFocus(){clearTimeout(t0);t0=setTimeout(function(){var a=document.activeElement;var inp=a&&a.tagName==='INPUT'&&a.type==='password';if(inp)return;draw()},200)}
  function setPurpose(k,v){if(k==='default')edit.default_provider=v;else edit.purposes[k]=v}
  function setRate(v){edit.rate_limit=parseInt(v,10)||60}
  function clearKey(id){if(!confirm('Gespeicherten Schlüssel für diesen Anbieter beim Speichern entfernen?'))return;edits(id).api_key='__clear__';draw()}
  function customList(){
    if(edit.custom)return edit.custom;
    edit.custom=provs().filter(function(p){return p.custom}).map(function(p){return {id:p.id,label:p.label,base_url:p.base_url,model:p.model,models:(p.models||[]).filter(function(m){return m!==p.model}),needs_key:p.needs_key,free:p.free}});
    return edit.custom;
  }
  function addCustom(i){
    var t=TEMPLATES[parseInt(i,10)||0]||TEMPLATES[0];
    var base=(t[1].toLowerCase().replace(/\(.*?\)/g,'').replace(/[^a-z0-9]+/g,'')||'eigener').slice(0,20);if(!/^[a-z]/.test(base))base='x'+base;var id=base,n=2;
    while(st.providers.some(function(p){return p.id===id}))id=base+(n++);
    customList().push({id:id,label:t[1],base_url:t[2],model:t[3]||'modell',models:[],needs_key:t[4],free:t[5]});
    st.providers.push({id:id,label:t[1],group:'Eigene Anbieter',note:'Eigener OpenAI-kompatibler Anbieter.',verified:false,kind:'openai',free:t[5],base_editable:true,custom:true,needs_key:t[4],base_url:t[2],model:t[3]||'modell',models:[],enabled:true,has_key:false,key_source:''});
    edits(id).enabled=true;
    draw();toast('Angelegt – Adresse, Modell und Schlüssel prüfen, dann speichern.');
  }
  function setCustom(id,k,v){
    customList().forEach(function(c){if(c.id===id)c[k]=v});
    st.providers.forEach(function(p){if(p.id===id)p[k]=v});
    if(k==='needs_key'||k==='free')draw();
  }
  function removeCustom(id){
    if(!confirm('Diesen Anbieter entfernen? Sein Schlüssel wird mit entfernt.'))return;
    customList();edit.custom=edit.custom.filter(function(c){return c.id!==id});edits(id).api_key='__clear__';
    st.providers=st.providers.filter(function(p){return p.id!==id});draw();
  }
  function test(id){
    tests[id]={busy:true};draw();
    var run=function(){return api('ai_test',{provider:id})};
    // ungespeicherte Eingaben zuerst sichern, damit der Server sie kennt
    save(true).then(run).then(function(d){tests[id]=d}).catch(function(e){tests[id]={ok:false,error:e.message}}).then(draw);
  }
  function loadModels(id){
    models[id]={msg:'Modelle werden abgefragt …'};draw();
    save(true).then(function(){return api('ai_models',{provider:id})}).then(function(d){models[id]={list:d.models||[],msg:(d.models||[]).length+' Modelle '+(d.source==='provider'?'beim Anbieter gefunden':'aus dem Katalog')+' – im Feld „Modell“ auswählen.'}}).catch(function(e){models[id]={msg:e.message}}).then(draw);
  }
  function refreshAssistant(){   // Assistent-Ansicht neu laden (ohne die Revisionen der übrigen Bereiche anzufassen)
    try{fetch(CRON+'?action=get&_='+Date.now(),{headers:cmsHeaders(false)}).then(function(r){return r.json()}).then(function(d){if(d&&d.config&&d.config.assistant&&typeof CMS!=='undefined')CMS.assistant=d.config.assistant}).catch(function(){})}catch(e){}
  }
  function save(quiet){
    var cfg={providers:{},purposes:edit.purposes,default_provider:edit.default_provider,rate_limit:edit.rate_limit};
    provs().forEach(function(p){var e=edit.providers[p.id];if(!e)return;var o={};['enabled','model','base_url','api_key'].forEach(function(k){if(e[k]!==undefined)o[k]=e[k]});if(Object.keys(o).length)cfg.providers[p.id]=o});
    if(edit.custom)cfg.custom=edit.custom.map(function(c){var p=provs().filter(function(x){return x.id===c.id})[0]||{};var e=edit.providers[c.id]||{};return Object.assign({},c,{base_url:e.base_url!==undefined?e.base_url:c.base_url,model:e.model!==undefined?e.model:c.model,label:c.label})});
    busy=true;
    return api('ai_config_save',{config:cfg}).then(function(d){
      st=d.config;edit={custom:null,purposes:Object.assign({},st.purposes||{}),default_provider:st.default_provider||'',rate_limit:st.rate_limit,providers:{}};busy=false;
      refreshAssistant();
      if(!quiet){toast('KI-Zentrale gespeichert ✓');draw()}
    }).catch(function(e){busy=false;toast(e.message,true);throw e});
  }
  function migrate(){
    api('ai_migrate_legacy',{}).then(function(d){refreshAssistant();st=d.config;edit.providers={};toast((d.moved||[]).length+' Schlüssel übernommen ✓');draw()}).catch(function(e){toast(e.message,true)});
  }
  function logs(){
    var el=document.getElementById('aicLogs');if(el)el.textContent='Lädt …';
    api('ai_logs').then(function(d){
      if(!el)return;if(!d.available){el.textContent='Kein Protokoll verfügbar (SQLite/Datenbank fehlt).';return}
      var s=d.summary||[];
      el.innerHTML=s.length?'<table style="width:100%;font-size:.78rem"><tr><th align="left">Anbieter</th><th>Anfragen</th><th>Fehler</th><th>Tokens</th><th>Ø ms</th></tr>'+s.map(function(r){return '<tr><td>'+esc(r.provider)+'</td><td align="center">'+esc(r.calls)+'</td><td align="center">'+esc(r.errors)+'</td><td align="center">'+esc(r.tokens)+'</td><td align="center">'+esc(r.avg_ms)+'</td></tr>'}).join('')+'</table>':'Noch keine Anfragen.';
    }).catch(function(e){if(el)el.textContent=e.message});
  }
  function open(){if(!st)load();else{draw();load()}}
  window.AiCenter={open:open,load:load,set:set,setPurpose:setPurpose,setRate:setRate,clearKey:clearKey,addCustom:addCustom,setCustom:setCustom,removeCustom:removeCustom,test:test,loadModels:loadModels,save:function(){return save(false)},migrate:migrate,logs:logs,state:function(){return st}};
})();
