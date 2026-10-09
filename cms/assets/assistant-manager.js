// CMS: KI-Assistent konfigurieren (Sektion "assistant") – Modus, Anbieter- und Modellkette, Texte, Funktionen, Tests
(function(){
 'use strict';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let model=null;const loaded={};   // geladene Modelllisten je Anbieter (nur im Browser)
 // Vorlagen für neue Anbieter (OpenAI-kompatible Schnittstellen); Modellnamen sind Vorschläge – „Modelle laden“ zeigt, was der Anbieter wirklich anbietet
 const TEMPLATES=[
  ['Eigener OpenAI-kompatibler Anbieter','Eigener Anbieter','','',true,false],
  ['OpenAI','OpenAI (GPT)','https://api.openai.com/v1','gpt-4o-mini',true,false],
  ['Anthropic Claude','Anthropic Claude','https://api.anthropic.com/v1','claude-sonnet-4-5',true,false],
  ['DeepSeek','DeepSeek','https://api.deepseek.com/v1','deepseek-chat',true,false],
  ['Together AI','Together AI','https://api.together.xyz/v1','meta-llama/Llama-3.3-70B-Instruct-Turbo',true,false],
  ['xAI Grok','xAI Grok','https://api.x.ai/v1','grok-3-mini',true,false],
  ['Perplexity','Perplexity','https://api.perplexity.ai','sonar',true,false],
  ['Fireworks AI','Fireworks AI','https://api.fireworks.ai/inference/v1','accounts/fireworks/models/llama-v3p3-70b-instruct',true,false],
  ['Ollama (lokal auf diesem Server)','Ollama (lokal)','http://localhost:11434/v1','llama3.2',false,true],
  ['LM Studio (lokal auf diesem Server)','LM Studio (lokal)','http://localhost:1234/v1','local-model',false,true],
 ];
 function cmsRoot(){ try{ return (typeof CMS!=='undefined'&&CMS)?CMS:(window.CMS||{}); }catch(e){ return window.CMS||{}; } }
 function cfg(){ if(!model)model=JSON.parse(JSON.stringify(cmsRoot().assistant||{})); if(!Array.isArray(model.providers))model.providers=[]; model.providers.forEach(p=>{if(!Array.isArray(p.models))p.models=String(p.models||'').split(/[\n,;]+/).map(x=>x.trim()).filter(Boolean);}); return model; }
 function set(path,value){ const parts=path.split('.');let o=cfg();for(let i=0;i<parts.length-1;i++){o[parts[i]]=o[parts[i]]??{};o=o[parts[i]];}o[parts[parts.length-1]]=value; }
 function provider(i){ return cfg().providers[i]; }
 function field(id,label,value,opts={}){
  const on=`oninput="AssistantManager.set('${esc(id)}',this.value)"`;
  if(opts.type==='textarea')return `<div><label class="news-lbl">${esc(label)}</label><textarea class="fc w-100" rows="${opts.rows||3}" placeholder="${esc(opts.ph||'')}" ${on}>${esc(value)}</textarea>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
  return `<div><label class="news-lbl">${esc(label)}</label><input class="fc w-100" type="${opts.type||'text'}" ${opts.step?'step="'+opts.step+'" min="'+(opts.min??0)+'" max="'+(opts.max??2)+'"':''} value="${esc(value)}" placeholder="${esc(opts.ph||'')}" ${on}>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
 }
 function select(id,label,value,options,hint){
  return `<div><label class="news-lbl">${esc(label)}</label><select class="fc w-100" onchange="AssistantManager.set('${esc(id)}',this.value);AssistantManager.redraw()">${options.map(([v,l])=>`<option value="${esc(v)}" ${v===value?'selected':''}>${esc(l)}</option>`).join('')}</select>${hint?'<div class="hint" style="margin-top:4px">'+esc(hint)+'</div>':''}</div>`;
 }
 function render(keep){
  const host=document.getElementById('assistantEditor');if(!host)return;
  if(keep!==true)model=null;const a=cfg();
  const f=a.features||{};
  const feat=(k,label,desc,also)=>`<label class="wm-check" style="display:flex;gap:10px;align-items:flex-start;margin:0"><input type="checkbox" ${f[k]!==false?'checked':''} onchange="AssistantManager.set('features.${k}',this.checked);${also?`AssistantManager.set('features.${also}',this.checked)`:''}"><span><b>${esc(label)}</b><br><small class="hint">${esc(desc)}</small></span></label>`;
  host.innerHTML=`
   <div class="section-grid" style="margin-bottom:10px">
    <label class="wm-check" style="margin:0"><input type="checkbox" ${a.enabled!==false?'checked':''} onchange="AssistantManager.set('enabled',this.checked)"> Assistent auf der Website anzeigen</label>
    ${field('name','Name des Assistenten',a.name||'',{ph:'Assistent'})}
    ${field('rate_limit','Max. Fragen je Besucher und Stunde',a.rate_limit||40,{type:'number',hint:'Schutz vor Missbrauch; pro IP-Adresse.'})}
    ${field('max_tokens','Max. Antwortlänge (Tokens)',a.max_tokens||420,{type:'number'})}
    ${field('temperature','Kreativität (Temperatur 0–1,5)',a.temperature??0.2,{type:'number',step:'0.1',min:0,max:1.5,hint:'Niedrig = sachlich und gleichbleibend, hoch = abwechslungsreicher.'})}
   </div>
   ${field('greeting','Begrüßung im Chat',a.greeting||'',{type:'textarea',rows:2})}
   <div style="height:10px"></div>
   ${field('knowledge','Wissen über uns (wird jeder Antwort mitgegeben)',a.knowledge||'',{type:'textarea',rows:6,ph:'z.B. Wer wir sind, was wir anbieten, Öffnungszeiten, Preise, Kontaktwege …',hint:'Freitext, max. 6000 Zeichen. Je konkreter, desto besser antwortet der Assistent.'+' Beiträge deiner Website werden zusätzlich passend zur Frage herangezogen.'})}
   <div style="height:10px"></div>
   ${field('system_prompt','Zusätzliche Anweisungen (optional)',a.system_prompt||'',{type:'textarea',rows:2,ph:'z.B. Duze die Besucher, antworte kurz, verweise bei Preisfragen auf die Kontaktseite …'})}
   <div style="height:10px"></div>
   ${field('privacy_note','Datenschutz-Hinweis im Chat',a.privacy_note||'',{type:'textarea',rows:2})}
   <div class="widget-category-title" style="margin-top:16px">Funktionen</div>
   <div class="section-grid">
    ${feat('pages','Inhalte der Website','Passende Beiträge zur Frage heraussuchen und für die Antwort nutzen.','news')}
    ${feat('research','Live-Recherche','Wetter, Schlagzeilen und Wikipedia-Auszüge abrufen, wenn die Frage danach klingt (externe Dienste).')}
   </div>
   <div class="widget-category-title" style="margin-top:16px">KI-Anbieter und Modelle</div>
   <div class="section-grid" style="margin-bottom:6px">
    ${select('order_mode','Reihenfolge',a.order_mode||'manual',[['manual','Manuell – genau meine Reihenfolge (oben zuerst)'],['auto','Automatisch – kostenlose zuerst, schnelle Modelle nach vorn']],'Manuell: Es wird der erste aktive Anbieter genutzt, der antwortet, mit seinen Modellen in der angegebenen Reihenfolge. Automatisch: kostenlose Anbieter mit Key, dann Key-freie Dienste, zuletzt kostenpflichtige; gemessen schnellere Modelle rücken vor.')}
   </div>
   <p class="hint" style="margin:0 0 8px">Fällt ein Anbieter aus, wird er 3 Minuten übersprungen. Anbieter mit „Key nötig“ sind erst aktiv, wenn ein API-Key hinterlegt ist. Keys werden nie an Besucher ausgeliefert. Pro Anbieter wählst du das Hauptmodell und beliebig viele Reservemodelle; „Modelle laden“ fragt den Anbieter nach seiner Liste.</p>
   <div id="assistantStatus" class="hint" style="margin:0 0 8px"></div>
   <div id="assistantProviders">${(a.providers||[]).map((p,i)=>providerHtml(p,i)).join('')}</div>
   <p class="hint" style="margin-top:10px"><i class="fas fa-circle-info"></i> Schlüssel, eigene Anbieter (z. B. Ollama) und alle weiteren Anbieter verwaltest du zentral in der <a href="#" onclick="cmsTab('aicenter',document.querySelector('[data-tab=aicenter]'));window.AiCenter?.open();return false">KI-Zentrale</a>. Hier legst du nur fest, welche davon der Assistent in welcher Reihenfolge nutzt.</p>
  `;
 }
 function modelChips(p,i){
  const list=Array.isArray(p.models)?p.models:[];
  return list.map((m,j)=>`<span style="display:inline-flex;gap:4px;align-items:center;padding:3px 6px;margin:2px 4px 2px 0;border-radius:999px;border:1px solid var(--line)"><span style="font-size:.78rem">${esc(m)}</span><span id="assistantModelTest_${i}_${j}" class="hint" style="font-size:.68rem"></span><button class="btn-g" title="Nach vorn" ${j<=0?'disabled':''} onclick="AssistantManager.moveModel(${i},${j},-1)" style="padding:0 5px"><i class="fas fa-arrow-left"></i></button><button class="btn-g" title="Nach hinten" ${j>=list.length-1?'disabled':''} onclick="AssistantManager.moveModel(${i},${j},1)" style="padding:0 5px"><i class="fas fa-arrow-right"></i></button><button class="btn-g" title="Als Hauptmodell" onclick="AssistantManager.makePrimary(${i},${j})" style="padding:0 5px"><i class="fas fa-star"></i></button><button class="btn-g" title="Dieses Modell testen" onclick="AssistantManager.testModel(${i},'${esc(m).replace(/'/g,'&#39;')}',${j})" style="padding:0 5px"><i class="fas fa-plug-circle-check"></i></button><button class="btn-g" title="Entfernen" onclick="AssistantManager.removeModel(${i},${j})" style="padding:0 5px"><i class="fas fa-xmark"></i></button></span>`).join('')||'<span class="hint">Keine Reservemodelle.</span>';
 }
 function modelList(p,i){
  const l=loaded[i];if(!l)return '';
  if(l.error)return `<div class="hint" style="color:#ff8e8e;margin-top:6px">${esc(l.error)}</div>`;
  const have=new Set([p.model,...(p.models||[])]);
  return `<details open style="margin-top:6px"><summary class="hint">${l.models.length} Modelle beim Anbieter</summary><input class="fc w-100" placeholder="Modelle filtern …" oninput="AssistantManager.filterModels(${i},this.value)" style="margin:6px 0"><div id="assistantModelList_${i}" style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:10px">${l.models.map(m=>modelRow(i,m,have.has(m.id))).join('')}</div></details>`;
 }
 function modelRow(i,m,has){
  return `<div class="assistant-mrow" data-m="${esc(m.id.toLowerCase())}" style="display:flex;gap:8px;align-items:center;padding:4px 8px;border-bottom:1px dashed var(--line)"><span style="flex:1;word-break:break-all;font-size:.8rem">${esc(m.id)}</span>${m.free===true?'<span class="hint" style="color:var(--ok,#7ee2b8)">kostenlos</span>':''}${m.ctx?`<span class="hint">${Math.round(m.ctx/1000)}k</span>`:''}${has?'<span class="hint">verwendet</span>':`<button class="btn-g" onclick="AssistantManager.pickModel(${i},'${esc(m.id).replace(/'/g,'&#39;')}','main')">Haupt</button><button class="btn-g" onclick="AssistantManager.pickModel(${i},'${esc(m.id).replace(/'/g,'&#39;')}','reserve')">Reserve</button>`}</div>`;
 }
 function keyBox(p){
  const link='<a href="#" onclick="cmsTab(\'aicenter\',document.querySelector(\'[data-tab=aicenter]\'));window.AiCenter?.open();return false">KI-Zentrale</a>';
  if(!p.needs_key&&p.key_source==='')return `<div class="hint">Kein Schlüssel nötig. Optional in der ${link} hinterlegbar.</div>`;
  if(p.key_source==='central')return `<div class="hint" style="color:var(--ok,#7ee2b8)"><i class="fas fa-key"></i> Schlüssel zentral hinterlegt (${link})</div>`;
  if(p.key_source==='assistant')return `<div class="hint" style="color:var(--warn,#ffc96b)"><i class="fas fa-key"></i> Schlüssel noch im Assistenten gespeichert – in der ${link} unter „Alte Schlüssel übernehmen“ zentral ablegen.</div>`;
  return `<div class="hint" style="color:var(--warn,#ffc96b)"><i class="fas fa-key"></i> ${p.needs_key?'Schlüssel nötig – ':'Optional – '}in der ${link} eintragen.</div>`;
 }
 function providerHtml(p,i){
  const hasKey=!!(p.has_key||p.key_source);const keyState=p.needs_key?(hasKey?'<span style="color:var(--ok,#7ee2b8)"><i class="fas fa-key"></i> Key hinterlegt</span>':'<span style="color:var(--warn,#ffc96b)"><i class="fas fa-key"></i> Key nötig</span>'):'<span style="color:var(--ok,#7ee2b8)"><i class="fas fa-unlock"></i> ohne Key</span>';
  const n=(cfg().providers||[]).length;
  return `<div class="card" style="padding:12px;margin:8px 0;border:1px solid var(--line)">
   <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
    <label class="wm-check" style="margin:0"><input type="checkbox" ${p.enabled!==false?'checked':''} onchange="AssistantManager.setP(${i},'enabled',this.checked)"> <b>${esc(p.label||p.id)}</b></label>
    <span class="hint" style="font-size:.7rem">${p.free?'kostenlos':'kostenpflichtig'} · ${keyState}</span>
    <span style="flex:1"></span>
    <span id="assistantTest_${i}" class="hint" style="font-size:.72rem"></span>
    <button class="btn-g" onclick="AssistantManager.test(${i})"><i class="fas fa-plug-circle-check"></i> Testen</button>
    <button class="btn-g" title="Nach oben" ${i<=0?'disabled':''} onclick="AssistantManager.move(${i},-1)"><i class="fas fa-arrow-up"></i></button>
    <button class="btn-g" title="Nach unten" ${i>=n-1?'disabled':''} onclick="AssistantManager.move(${i},1)"><i class="fas fa-arrow-down"></i></button>
    ${(!p.builtin&&!p.central)?`<button class="btn-g" title="Anbieter entfernen" onclick="AssistantManager.remove(${i})"><i class="fas fa-trash"></i></button>`:''}
   </div>
   <div class="section-grid">
    <div><label class="news-lbl">Hauptmodell</label><div style="display:flex;gap:6px"><input class="fc w-100" value="${esc(p.model||'')}" oninput="AssistantManager.setP(${i},'model',this.value)" placeholder="Modellname"><button class="btn-g" type="button" onclick="AssistantManager.loadModels(${i})" title="Beim Anbieter nachfragen, welche Modelle es gibt"><i class="fas fa-list"></i> Modelle laden</button></div>${p.id==='openrouter'?'<div class="hint" style="margin-top:4px">Die aktuell besten kostenlosen OpenRouter-Modelle werden automatisch erkannt und zuerst nacheinander probiert; ein hier eingetragenes Modell kommt danach als Reserve.</div>':''}</div>
    <div><label class="news-lbl">Zugang (API-Schlüssel)</label>${keyBox(p)}</div>
    ${(p.builtin||p.central)?`<div><label class="news-lbl">Endpunkt</label><div class="hint" style="word-break:break-all">${esc(p.base_url)}</div></div>`:`<div><label class="news-lbl">Basis-URL (OpenAI-kompatibel, ohne /chat/completions)</label><input class="fc w-100" value="${esc(p.base_url||'')}" placeholder="https://api.example.com/v1 (lokal: http://localhost:11434/v1)" oninput="AssistantManager.setP(${i},'base_url',this.value)"></div><div><label class="news-lbl">Bezeichnung</label><input class="fc w-100" value="${esc(p.label||'')}" oninput="AssistantManager.setP(${i},'label',this.value)"></div><div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><label class="wm-check" style="margin:0"><input type="checkbox" ${p.needs_key!==false?'checked':''} onchange="AssistantManager.setP(${i},'needs_key',this.checked);AssistantManager.redrawProviders()"> API-Key nötig</label><label class="wm-check" style="margin:0"><input type="checkbox" ${p.free?'checked':''} onchange="AssistantManager.setP(${i},'free',this.checked)"> kostenlos (Reihenfolge „Automatisch“)</label></div>`}
   </div>
   ${p.id==='openrouter'?'':`<div style="margin-top:8px"><label class="news-lbl">Weitere Modelle (Reserve – werden bei Fehlern oder Langsamkeit in dieser Reihenfolge probiert, bis zu 20)</label><div>${modelChips(p,i)}</div><div style="display:flex;gap:6px;margin-top:6px"><input id="assistantAddModel_${i}" class="fc" style="max-width:360px" placeholder="Modellname hinzufügen" onkeydown="if(event.key==='Enter'){AssistantManager.addModel(${i});event.preventDefault()}"><button class="btn-g" type="button" onclick="AssistantManager.addModel(${i})"><i class="fas fa-plus"></i> Hinzufügen</button></div></div>`}
   <div id="assistantModels_${i}">${modelList(p,i)}</div>
  </div>`;
 }
 function setP(i,k,v){ const p=provider(i);if(!p)return;p[k]=v; }
 function move(i,d){ const list=cfg().providers;const j=i+d;if(j<0||j>=list.length)return;[list[i],list[j]]=[list[j],list[i]];[loaded[i],loaded[j]]=[loaded[j],loaded[i]];rerenderProviders(); }
 function remove(i){ cfg().providers.splice(i,1);delete loaded[i];rerenderProviders(); }
 function addProvider(t){ const x=TEMPLATES[parseInt(t,10)||0]||TEMPLATES[0];cfg().providers.push({id:'custom_'+Date.now().toString(36),label:x[1],type:'openai',base_url:x[2],model:x[3],models:[],api_key:'',enabled:true,builtin:false,free:!!x[5],needs_key:x[4]});rerenderProviders(); }
 function rerenderProviders(){ const h=document.getElementById('assistantProviders');if(h)h.innerHTML=(cfg().providers||[]).map((p,i)=>providerHtml(p,i)).join(''); }
 function addModel(i){ const el=document.getElementById('assistantAddModel_'+i);const v=(el?.value||'').trim();const p=provider(i);if(!p||!v)return;if(!/^[\w.:\/@+-]{1,120}$/.test(v))return toast('Ungültiger Modellname',true);if(v===p.model||p.models.includes(v))return toast('Dieses Modell ist schon eingetragen',true);if(p.models.length>=20)return toast('Höchstens 20 Reservemodelle',true);p.models.push(v);rerenderProviders(); }
 function removeModel(i,j){ const p=provider(i);if(!p)return;p.models.splice(j,1);rerenderProviders(); }
 function moveModel(i,j,d){ const p=provider(i),k=j+d;if(!p||k<0||k>=p.models.length)return;[p.models[j],p.models[k]]=[p.models[k],p.models[j]];rerenderProviders(); }
 function makePrimary(i,j){ const p=provider(i);if(!p)return;const m=p.models[j];p.models.splice(j,1);if(p.model)p.models.unshift(p.model);p.model=m;rerenderProviders(); }
 function pickModel(i,id,where){ const p=provider(i);if(!p)return;if(where==='main'){ if(p.model&&!p.models.includes(p.model))p.models.unshift(p.model);p.models=p.models.filter(x=>x!==id);p.model=id; } else if(!p.models.includes(id)&&id!==p.model){ if(p.models.length>=20)return toast('Höchstens 20 Reservemodelle',true);p.models.push(id); } rerenderProviders(); }
 function filterModels(i,q){ q=String(q||'').toLowerCase().trim();document.querySelectorAll('#assistantModelList_'+i+' .assistant-mrow').forEach(r=>{r.style.display=(!q||r.dataset.m.includes(q))?'flex':'none'}); }
 async function loadModels(i){
  const p=provider(i);if(!p)return;const box=document.getElementById('assistantModels_'+i);
  if(box)box.innerHTML='<div class="hint" style="margin-top:6px"><i class="fas fa-spinner fa-spin"></i> Modelle werden abgefragt …</div>';
  try{
   const d=await window.cmsApi('assistant_models',{provider:p.id,base_url:p.base_url||'',api_key:(p.api_key&&p.api_key!=='__clear__')?p.api_key:''});
   loaded[i]=d.ok?{models:d.models||[]}:{error:d.error||'Modelle nicht abrufbar'};
  }catch(e){ loaded[i]={error:e.message}; }
  if(box)box.innerHTML=modelList(p,i);
 }
 function toast(m,bad){ try{window.cmsToast(m,!!bad);}catch(e){} }
 async function save(){ const a=cfg();a.rate_limit=parseInt(a.rate_limit,10)||40;a.max_tokens=parseInt(a.max_tokens,10)||420;a.temperature=parseFloat(a.temperature);if(isNaN(a.temperature))a.temperature=0.2;await window.saveSection('assistant',a);model=null;render(); }
 async function test(i){
  const el=document.getElementById('assistantTest_'+i);const p=provider(i);if(!p)return;
  if(el)el.innerHTML='<i class="fas fa-spinner fa-spin"></i> Test läuft …';
  try{
   // ungespeicherte Keys zuerst sichern, damit der Server sie kennt
   await window.saveSection('assistant',cfg());
   const d=await window.cmsApi('assistant_test',{provider:p.id});
   if(el)el.innerHTML=d.ok?`<span style="color:var(--ok,#7ee2b8)"><i class="fas fa-circle-check"></i> OK · ${esc(d.model||'')} · ${d.ms} ms</span>`:`<span style="color:#ff8e8e"><i class="fas fa-circle-xmark"></i> ${esc(d.error||'Fehler')}</span>`;
  }catch(e){ if(el)el.innerHTML='<span style="color:#ff8e8e">'+esc(e.message)+'</span>'; }
 }
 async function testModel(i,m,j){
  const el=document.getElementById('assistantModelTest_'+i+'_'+j);const p=provider(i);if(!p)return;
  if(el)el.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
  try{
   await window.saveSection('assistant',cfg());
   const d=await window.cmsApi('assistant_test',{provider:p.id,model:m});
   if(el)el.innerHTML=d.ok?`<span style="color:var(--ok,#7ee2b8)">OK ${d.ms} ms</span>`:`<span style="color:#ff8e8e" title="${esc(d.error||'')}">Fehler</span>`;
  }catch(e){ if(el)el.innerHTML='<span style="color:#ff8e8e">'+esc(e.message)+'</span>'; }
 }
 async function testAll(){ for(let i=0;i<(cfg().providers||[]).length;i++){const p=provider(i);if(p.enabled===false||(p.needs_key&&!p.has_key&&!p.api_key))continue;await test(i);} }
 function clearKey(i){ const p=provider(i);if(!p)return;if(!confirm('Gespeicherten API-Key für „'+(p.label||p.id)+'“ wirklich entfernen?'))return;p.api_key='__clear__';p.has_key=false;save(); }
 async function status(){
  const el=document.getElementById('assistantStatus');if(!el)return;
  try{
   const d=await window.cmsApi('assistant_status');
   const rows=(d.providers||[]).map(p=>`<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:4px 0;border-bottom:1px dashed var(--line)"><b style="min-width:120px">${esc(p.id)}</b><span>${p.ready?'<span style="color:var(--ok,#7ee2b8)">bereit</span>':'<span style="color:var(--muted)">inaktiv'+(p.enabled&&!p.has_key?' (Key fehlt)':'')+'</span>'}</span>${p.paused_until?`<span style="color:#ffc96b">pausiert bis ${esc(p.paused_until)}</span>`:''}${p.last_error?`<span style="color:#ff8e8e">letzter Fehler ${esc(p.last_fail_at.replace('T',' ').slice(0,19))}: ${esc(p.last_error)}</span>`:''}</div>`).join('');
   el.innerHTML=`<div style="font-weight:800;margin-bottom:4px">Live-Status der Anbieter-Kette${d.curl?'':' · <span style="color:#ff8e8e">curl fehlt auf dem Server!</span>'}</div>${rows}`;
  }catch(e){ el.textContent='Status nicht abrufbar: '+e.message; }
 }
 const _render=render;
 // redraw: Ansicht neu zeichnen, ohne die ungespeicherten Eingaben zu verwerfen (Modell bleibt im Speicher)
 function redraw(){ _render(true);status(); }
 window.AssistantManager={render:()=>{_render();status();},redraw,redrawProviders:rerenderProviders,set,setP,move,remove,addProvider,addModel,removeModel,moveModel,makePrimary,pickModel,filterModels,loadModels,save,test,testModel,testAll,status,clearKey};
})();
