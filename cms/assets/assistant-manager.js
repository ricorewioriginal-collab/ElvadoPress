// CMS: KI-Assistent konfigurieren (Sektion "assistant") – Anbieter-Kette, Texte, Funktionen, Tests
(function(){
 'use strict';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let model=null;
 function cmsRoot(){ try{ return (typeof CMS!=='undefined'&&CMS)?CMS:(window.CMS||{}); }catch(e){ return window.CMS||{}; } }
 function cfg(){ if(!model)model=JSON.parse(JSON.stringify(cmsRoot().assistant||{})); if(!Array.isArray(model.providers))model.providers=[]; return model; }
 function set(path,value){ const parts=path.split('.');let o=cfg();for(let i=0;i<parts.length-1;i++){o[parts[i]]=o[parts[i]]??{};o=o[parts[i]];}o[parts[parts.length-1]]=value; }
 function provider(i){ return cfg().providers[i]; }
 function field(id,label,value,opts={}){
  const on=`oninput="AssistantManager.set('${esc(id)}',this.value)"`;
  if(opts.type==='textarea')return `<div><label class="news-lbl">${esc(label)}</label><textarea class="fc w-100" rows="${opts.rows||3}" placeholder="${esc(opts.ph||'')}" ${on}>${esc(value)}</textarea>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
  return `<div><label class="news-lbl">${esc(label)}</label><input class="fc w-100" type="${opts.type||'text'}" value="${esc(value)}" placeholder="${esc(opts.ph||'')}" ${on}>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
 }
 function render(){
  const host=document.getElementById('assistantEditor');if(!host)return;
  model=null;const a=cfg();
  const f=a.features||{};const own=!!a.neutral;
  const feat=(k,label,desc)=>`<label class="wm-check" style="display:flex;gap:10px;align-items:flex-start;margin:0"><input type="checkbox" ${f[k]!==false?'checked':''} onchange="AssistantManager.set('features.${k}',this.checked)"><span><b>${esc(label)}</b><br><small class="hint">${esc(desc)}</small></span></label>`;
  host.innerHTML=`
   <div class="section-grid" style="margin-bottom:10px">
    <label class="wm-check" style="margin:0"><input type="checkbox" ${a.enabled!==false?'checked':''} onchange="AssistantManager.set('enabled',this.checked)"> Assistent im Portal anzeigen</label>
    ${field('name','Name des Assistenten',a.name||'',{ph:'Radio-Assistent'})}
    ${field('rate_limit','Max. Fragen je Hörer und Stunde',a.rate_limit||40,{type:'number',hint:'Schutz vor Missbrauch; pro IP-Adresse.'})}
    ${field('max_tokens','Max. Antwortlänge (Tokens)',a.max_tokens||420,{type:'number'})}
   </div>
   ${field('greeting','Begrüßung im Chat',a.greeting||'',{type:'textarea',rows:2})}
   <div style="height:10px"></div>
   ${field('knowledge','Wissen über uns (wird jeder Antwort mitgegeben)',a.knowledge||'',{type:'textarea',rows:6,ph:own?'z.B. Wer wir sind, welche Shows und Moderatoren es gibt, Kontaktwege, Events …':'z.B. Wer AnMaCha ist, was RicoReWi Music & Media macht, was SenderWelt ist, Shows, Moderatoren, Kontaktwege, Events …',hint:'Freitext, max. 6000 Zeichen. Je konkreter, desto besser antwortet der Assistent. Live-Daten (Titel, Sendeplan, Sender, Podcast, News) kommen automatisch dazu.'})}
   ${own?`<div style="height:10px"></div>${field('stations','Deine Sender (laut.fm-Kennungen)',(a.stations||[]).join('\n'),{type:'textarea',rows:3,ph:'meinradio\nmein-zweiter-sender',hint:'Eine Kennung je Zeile (laut.fm/<kennung>). Damit nennt der Assistent den laufenden Titel und den Sendeplan. Sender aus dem Alexa-Skill werden automatisch mit verwendet.'})}`:''}
   <div style="height:10px"></div>
   ${field('system_prompt','Zusätzliche Anweisungen (optional)',a.system_prompt||'',{type:'textarea',rows:2,ph:'z.B. Duze die Hörer, erwähne bei Fragen zu Events immer unsere News-Seite …'})}
   <div style="height:10px"></div>
   ${field('privacy_note','Datenschutz-Hinweis im Chat',a.privacy_note||'',{type:'textarea',rows:2})}
   <div class="widget-category-title" style="margin-top:16px">Funktionen</div>
   <div class="section-grid">
    ${feat('nowplaying','Jetzt läuft','Aktueller Titel und zuletzt gespielte Songs je Sender (laut.fm).')}
    ${feat('schedule','Sendeplan','Laufende Sendung, nächste Sendungen, Tagesprogramm.')}
    ${feat('stations','Sender',own?'Deine Sender vorstellen und empfehlen.':'Alle Sender des Netzwerks vorstellen und empfehlen.')}
    ${own?'':feat('podcast','Podcast','AnMaCha – Der Podcast mit den neuesten Folgen.')}
    ${feat('news','News & Events','Veröffentlichte Beiträge aus dem Magazin.')}
    ${feat('favorites','Favoriten','Lieblingssender des Hörers als Kontext nutzen.')}
    ${own?'':feat('studiomail','Nachricht ans Studio','Hörer schreiben je Sender oder ans Netzwerk – landet in Studiomail.')}
    ${own?'':feat('voicemail','Sprachnachricht','Voice-Memo je Sender aufnehmen – landet in Studiomail (Voicemail).')}
   </div>
   <div class="widget-category-title" style="margin-top:16px">KI-Anbieter (Reihenfolge = Priorität, kostenlose zuerst)</div>
   <p class="hint" style="margin:-4px 0 8px">Reihenfolge der Kette: kostenlose Anbieter mit Key (z.B. Groq) zuerst, dann Key-freie Community-Dienste (Pollinations, LLM7), zuletzt kostenpflichtige. Es wird automatisch der erste aktive Anbieter genutzt, der antwortet. Fällt einer aus, wird er 10 Minuten übersprungen. Anbieter mit „Key nötig“ sind erst aktiv, wenn ein API-Key hinterlegt ist. Keys werden nie an Hörer ausgeliefert.</p>
   <div id="assistantStatus" class="hint" style="margin:0 0 8px"></div>
   <div id="assistantProviders">${(a.providers||[]).map((p,i)=>providerHtml(p,i)).join('')}</div>
   <button class="btn-g" style="margin-top:8px" onclick="AssistantManager.addProvider()"><i class="fas fa-plus"></i> Eigenen Anbieter (OpenAI-kompatibel) hinzufügen</button>
  `;
 }
 function providerHtml(p,i){
  const hasKey=!!(p.has_key||(p.api_key&&p.api_key!=='__clear__'));const keyState=p.needs_key?(hasKey?'<span style="color:var(--ok,#7ee2b8)"><i class="fas fa-key"></i> Key hinterlegt</span>':'<span style="color:var(--warn,#ffc96b)"><i class="fas fa-key"></i> Key nötig</span>'):'<span style="color:var(--ok,#7ee2b8)"><i class="fas fa-unlock"></i> ohne Key</span>';
  return `<div class="card" style="padding:12px;margin:8px 0;border:1px solid var(--line)">
   <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
    <label class="wm-check" style="margin:0"><input type="checkbox" ${p.enabled!==false?'checked':''} onchange="AssistantManager.setP(${i},'enabled',this.checked)"> <b>${esc(p.label||p.id)}</b></label>
    <span class="hint" style="font-size:.7rem">${p.free?'kostenlos':'kostenpflichtig'} · ${keyState}</span>
    <span style="flex:1"></span>
    <span id="assistantTest_${i}" class="hint" style="font-size:.72rem"></span>
    <button class="btn-g" onclick="AssistantManager.test(${i})"><i class="fas fa-plug-circle-check"></i> Testen</button>
    ${i>0?`<button class="btn-g" title="Nach oben" onclick="AssistantManager.move(${i},-1)"><i class="fas fa-arrow-up"></i></button>`:''}
    ${!p.builtin?`<button class="btn-g" onclick="AssistantManager.remove(${i})"><i class="fas fa-trash"></i></button>`:''}
   </div>
   <div class="section-grid">
    <div><label class="news-lbl">Modell</label><input class="fc w-100" value="${esc(p.model||'')}" oninput="AssistantManager.setP(${i},'model',this.value)">${p.id==='openrouter'?'<div class="hint" style="margin-top:4px">Die aktuell besten kostenlosen OpenRouter-Modelle werden automatisch erkannt und zuerst nacheinander probiert; ein hier eingetragenes Modell kommt danach als Reserve.</div>':''}</div>
    ${p.id==='openrouter'?'':`<div><label class="news-lbl">Weitere Modelle (Reserve &amp; Tempo)</label><textarea class="fc w-100" rows="3" placeholder="ein Modell pro Zeile" oninput="AssistantManager.setP(${i},'models',this.value)">${esc((Array.isArray(p.models)?p.models:String(p.models||'').split(/[\n,;]+/)).filter(Boolean).join('\n'))}</textarea><div class="hint" style="margin-top:4px">Diese Modelle desselben Anbieters werden bei Fehlern oder Langsamkeit nacheinander probiert; das schnellste gemessene rückt automatisch nach vorn (max. 8).</div></div>`}
    <div><label class="news-lbl">API-Key${p.needs_key?'':' (optional)'}</label><div style="display:flex;gap:6px"><input class="fc w-100" type="password" value="${esc(p.api_key==='__clear__'?'':(p.api_key||''))}" placeholder="${hasKey?'•••••• (hinterlegt – leer lassen = behalten)':(p.needs_key?'sk-…':'nicht nötig')}" autocomplete="new-password" oninput="AssistantManager.setP(${i},'api_key',this.value)">${hasKey?`<button class="btn-g" type="button" title="Gespeicherten Key entfernen" onclick="AssistantManager.clearKey(${i})"><i class="fas fa-eraser"></i></button>`:''}</div></div>
    ${p.builtin?`<div><label class="news-lbl">Endpunkt</label><div class="hint" style="word-break:break-all">${esc(p.base_url)}</div></div>`:`<div><label class="news-lbl">Basis-URL (OpenAI-kompatibel, ohne /chat/completions)</label><input class="fc w-100" value="${esc(p.base_url||'')}" placeholder="https://api.example.com/v1" oninput="AssistantManager.setP(${i},'base_url',this.value)"></div><div><label class="news-lbl">Bezeichnung</label><input class="fc w-100" value="${esc(p.label||'')}" oninput="AssistantManager.setP(${i},'label',this.value)"></div>`}
   </div>
  </div>`;
 }
 function setP(i,k,v){ const p=provider(i);if(!p)return;p[k]=v; if(k==='api_key'||k==='enabled'){/* Statusanzeige aktualisieren ohne Fokus zu verlieren */} }
 function move(i,d){ const list=cfg().providers;const j=i+d;if(j<0||j>=list.length)return;[list[i],list[j]]=[list[j],list[i]];rerenderProviders(); }
 function remove(i){ cfg().providers.splice(i,1);rerenderProviders(); }
 function addProvider(){ cfg().providers.push({id:'custom_'+Date.now().toString(36),label:'Eigener Anbieter',type:'openai',base_url:'',model:'',api_key:'',enabled:true,builtin:false,free:false,needs_key:true});rerenderProviders(); }
 function rerenderProviders(){ const h=document.getElementById('assistantProviders');if(h)h.innerHTML=(cfg().providers||[]).map((p,i)=>providerHtml(p,i)).join(''); }
 async function save(){ const a=cfg();a.rate_limit=parseInt(a.rate_limit,10)||40;a.max_tokens=parseInt(a.max_tokens,10)||420;await window.saveSection('assistant',a);model=null;render(); }
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
 window.AssistantManager={render:()=>{_render();status();},set,setP,move,remove,addProvider,save,test,testAll,status,clearKey};
})();
