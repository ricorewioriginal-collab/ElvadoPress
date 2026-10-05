'use strict';
// Verbundene Dienste (eigenständiges CMS): eigene Dienste eintragen, öffnen und die Erreichbarkeit prüfen.
// Die Daten liegen in der Sektion "services" (items: id, name, url, kind, note, check); die Prüfung läuft serverseitig (services_status).
// Mit dem RicoReWi-Paket bleibt die feste Liste aus cms-app.js (SERVICE_FIELDS).
window.ServicesManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const KINDS={website:['Website','fa-globe'],api:['Schnittstelle (API)','fa-code'],media:['Medien / Cloud','fa-cloud'],podcast:['Podcast','fa-podcast'],video:['Video / Livestream','fa-video'],mail:['E-Mail / Webmail','fa-envelope'],analytics:['Statistik','fa-chart-line'],shop:['Shop','fa-bag-shopping'],docs:['Dokumentation / Wiki','fa-book'],other:['Sonstiges','fa-plug']};
 // Vorlagen: allgemeine Dienstarten mit Namensvorschlag (keine Adressen, die trägt man selbst ein)
 const TEMPLATES=[['Website','website'],['Cloud-Speicher','media'],['Podcast','podcast'],['Livestream / Video','video'],['Webmail','mail'],['Statistik','analytics'],['Shop','shop'],['Wiki / Hilfe','docs'],['Schnittstelle (API)','api'],['Eigener Dienst','other']];
 const S={items:[],status:{},loaded:false,dirty:false};
 const active=()=>!(window.CMS_PACKS_AVAILABLE&&window.CMS_PACKS_AVAILABLE['ricorewi-radio']!==false);
 const toast=(m,bad)=>{try{window.cmsToast(m,!!bad)}catch(e){}};
 const host=()=>document.getElementById('servicesEditor');
 const origin=()=>location.origin;
 const full=u=>u&&u[0]==='/'?origin()+u:u;
 function load(){const c=(window.CMS&&window.CMS.services)||{};S.items=Array.isArray(c.items)?c.items.map(x=>({name:x.name||'',url:x.url||'',kind:KINDS[x.kind]?x.kind:'other',note:x.note||'',check:x.check!==false,id:x.id||''})):[];S.dirty=false;S.loaded=true}
 function row(s,i,n){
  const st=S.status[s.id],badge=st?`<span class="svc-dot ${st.state==='online'?'ok':st.state==='offline'?'bad':''}"></span> <span class="hint">${esc(st.message)}${st.state==='online'||st.state==='offline'?' · '+st.ms+' ms':''}</span>`:'';
  const kinds=Object.keys(KINDS).map(k=>`<option value="${k}" ${s.kind===k?'selected':''}>${esc(KINDS[k][0])}</option>`).join('');
  return `<div class="svc-own"><div class="svc-own-top"><i class="fas ${KINDS[s.kind][1]}" style="color:var(--accent)"></i>
    <input class="fc" maxlength="60" placeholder="Name, z. B. Meine Cloud" value="${esc(s.name)}" oninput="ServicesManager.set(${i},'name',this.value)">
    <select class="fc" onchange="ServicesManager.set(${i},'kind',this.value,true)">${kinds}</select></div>
   <input class="fc w-100" placeholder="https://… oder /pfad auf dieser Website" value="${esc(s.url)}" oninput="ServicesManager.set(${i},'url',this.value)">
   <input class="fc w-100" maxlength="200" placeholder="Notiz (optional), z. B. Zugang beim Admin erfragen" value="${esc(s.note)}" oninput="ServicesManager.set(${i},'note',this.value)">
   <div class="svc-own-bar"><label class="ap-check"><input type="checkbox" ${s.check?'checked':''} onchange="ServicesManager.set(${i},'check',this.checked)"> Erreichbarkeit prüfen</label>${badge}
    <span class="ap-ctl"><button class="btn-g" title="Öffnen" onclick="ServicesManager.open(${i})"><i class="fas fa-arrow-up-right-from-square"></i></button><button class="btn-g" ${i<=0?'disabled':''} onclick="ServicesManager.move(${i},-1)"><i class="fas fa-arrow-up"></i></button><button class="btn-g" ${i>=n-1?'disabled':''} onclick="ServicesManager.move(${i},1)"><i class="fas fa-arrow-down"></i></button><button class="btn-g" title="Entfernen" onclick="ServicesManager.remove(${i})"><i class="fas fa-trash"></i></button></span></div></div>`;
 }
 function render(){
  const h=host();if(!h)return;if(!S.loaded)load();
  const chips=TEMPLATES.map(([n,k],i)=>`<button class="btn-g" type="button" onclick="ServicesManager.add(${i})" ${S.items.length>=30?'disabled':''}><i class="fas ${KINDS[k][1]}"></i> ${esc(n)}</button>`).join('');
  h.innerHTML=`<div class="hint" style="margin-bottom:8px">Trage die Dienste ein, die zu deiner Website gehören – Cloud, Podcast, Statistik, Shop oder eigene Werkzeuge. Du bekommst eine Übersicht mit Links, und das CMS prüft auf Wunsch, ob sie erreichbar sind (nur öffentliche Adressen, höchstens 30 Dienste).</div>
   <div class="ap-add" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px"><b style="align-self:center">Hinzufügen:</b>${chips}</div>
   ${S.items.length?S.items.map((s,i)=>row(s,i,S.items.length)).join(''):'<div class="empty">Noch keine Dienste – wähle oben eine Vorlage.</div>'}
   ${S.dirty?'<div class="hint" style="margin-top:8px;color:var(--accent)"><i class="fas fa-circle-exclamation"></i> Ungespeicherte Änderungen – oben auf „Speichern“ klicken.</div>':''}`;
 }
 const api={active,render,
  set(i,k,v,redraw){const s=S.items[i];if(!s)return;s[k]=v;S.dirty=true;if(redraw)render()},
  add(t){if(S.items.length>=30)return;const [n,k]=TEMPLATES[t];S.items.push({name:n==='Eigener Dienst'?'':n,url:'',kind:k,note:'',check:true,id:''});S.dirty=true;render()},
  remove(i){if(!confirm('Diesen Dienst entfernen?'))return;S.items.splice(i,1);S.dirty=true;render()},
  move(i,d){const j=i+d;if(j<0||j>=S.items.length)return;[S.items[i],S.items[j]]=[S.items[j],S.items[i]];S.dirty=true;render()},
  open(i){const u=full((S.items[i]?.url||'').trim());if(!/^https?:\/\//i.test(u))return toast('Für diesen Dienst ist keine gültige Adresse hinterlegt',true);window.open(u,'_blank','noopener')},
  async save(){
   const bad=S.items.find(s=>!s.name.trim()||!/^(https?:\/\/\S+|\/\S*)$/i.test(s.url.trim()));
   if(bad)return toast('Jeder Dienst braucht einen Namen und eine Adresse (https://… oder /pfad)',true);
   await window.saveSection('services',{items:S.items.map(s=>({name:s.name.trim(),url:s.url.trim(),kind:s.kind,note:s.note.trim(),check:!!s.check}))});
   S.loaded=false;load();render();
  },
  async check(){
   const h=document.getElementById('serviceStatusHost');if(!h)return;
   if(S.dirty)toast('Es werden die gespeicherten Dienste geprüft – Änderungen vorher speichern.',true);
   h.innerHTML='<div class="empty" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i>Dienste werden geprüft…</div>';
   try{
    const d=await window.cmsApi('services_status');S.status={};(d.services||[]).forEach(s=>S.status[s.id]=s);
    h.innerHTML=(d.services||[]).length?d.services.map(s=>`<div class="svc-card"><div class="svc-head"><b><span class="svc-dot ${s.state==='online'?'ok':s.state==='offline'?'bad':''}"></span>${esc(s.name)}</b><span class="hint">${esc(KINDS[s.kind]?KINDS[s.kind][0]:'')}</span></div><div class="svc-meta">${esc(s.url)}</div><div class="svc-meta">${esc(s.message)}${s.http?' · HTTP '+s.http+' · '+s.ms+' ms':''}</div>${s.note?`<div class="svc-meta">${esc(s.note)}</div>`:''}</div>`).join(''):'<div class="empty" style="grid-column:1/-1">Noch keine gespeicherten Dienste.</div>';
    render();
   }catch(e){h.innerHTML='<div class="empty" style="grid-column:1/-1;color:var(--bad)">'+esc(e.message)+'</div>'}
  }};
 return api;
})();
