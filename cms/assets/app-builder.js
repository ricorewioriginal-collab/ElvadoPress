'use strict';
// App-Builder: Startseite, Farben, Senderreihenfolge und "Mehr"-Menü der Apps im CMS gestalten.
// Die Daten liegen in apps.managed["marke:plattform"].builder und werden von den Apps über app_config geladen.
window.AppBuilder=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const BLOCKS={hero:'Willkommens-Karte',tiles:'Kacheln',stations:'Senderliste',text:'Text-Karte',link:'Link-Karte'};
 const TILES={favorites:'Favoriten',schedule:'Sendeplan',podcast:'Podcast',community:'Mitmachen',news:'News & Magazin',assistant:'KI-Assistent',directory:'Radioverzeichnis',shops:'Shops',help:'Hilfe',link:'Eigener Link'};
 const MENU_BASE={podcast:'Podcast',news:'News & Magazin',help:'Hilfe & Bedienung',shops:'Shops',assistant:'KI-Assistent'};
 // Einträge der Hersteller-Apps (RicoReWi-Paket); im eigenständigen CMS nicht angeboten
 const MENU_PACK={anmacha:'anmacha.de',portal:'Radioportal'};
 function menu(){return window.CMS_PACKS_AVAILABLE&&window.CMS_PACKS_AVAILABLE['ricorewi-radio']===false?MENU_BASE:Object.assign({},MENU_BASE,MENU_PACK);}
 const DEF={accent:'#b57cff',from:'#120d3f',to:'#5b1c84'};
 const root=()=>{try{return (typeof CMS!=='undefined'&&CMS)?CMS:(window.CMS||{});}catch(e){return window.CMS||{};}};
 const cfg=k=>{const c=window.AppsManager.get(k);if(!c)return null;const b=c.builder=c.builder||{};b.theme=b.theme||{};b.home=b.home||[];b.stations=b.stations||{order:[],hidden:[]};b.stations.order=b.stations.order||[];b.stations.hidden=b.stations.hidden||[];b.more_menu=b.more_menu||{hide:[],custom:[]};b.more_menu.hide=b.more_menu.hide||[];b.more_menu.custom=b.more_menu.custom||[];return b;};
 const A=k=>`'${esc(k)}'`;
 const coreStations=()=>((root().core_network&&root().core_network.stations)||[]).map(s=>String(s).toLowerCase());
 // wirksame Reihenfolge: gespeicherte zuerst, übrige Core-Sender dahinter
 function order(b){const core=coreStations();const o=b.stations.order.filter(x=>core.includes(x));core.forEach(x=>{if(!o.includes(x))o.push(x);});return o;}
 const field=(l,v,path,k,ph,max,wide)=>`<div${wide?' class="ap-wide"':''}><label class="news-lbl">${l}</label><input class="fc w-100" maxlength="${max||80}" value="${esc(v||'')}" placeholder="${esc(ph||'')}" oninput="AppBuilder.set(${A(k)},'${path}',this.value)"></div>`;
 const area=(l,v,path,k,max)=>`<div class="ap-wide"><label class="news-lbl">${l}</label><textarea class="fc w-100" rows="3" maxlength="${max||600}" oninput="AppBuilder.set(${A(k)},'${path}',this.value)">${esc(v||'')}</textarea></div>`;
 const color=(l,v,path,k,def)=>`<div><label class="news-lbl">${l}</label><div style="display:flex;gap:6px;align-items:center"><input type="color" value="${esc(v||def)}" oninput="AppBuilder.set(${A(k)},'${path}',this.value)"><button class="btn-g" title="Standard" onclick="AppBuilder.set(${A(k)},'${path}','',true)">Standard</button></div></div>`;
 function ctl(k,i,n,fn){return `<span class="ap-ctl"><button class="btn-g" ${i<=0?'disabled':''} onclick="AppBuilder.${fn}(${A(k)},${i},-1)" title="Nach oben"><i class="fas fa-arrow-up"></i></button><button class="btn-g" ${i>=n-1?'disabled':''} onclick="AppBuilder.${fn}(${A(k)},${i},1)" title="Nach unten"><i class="fas fa-arrow-down"></i></button></span>`;}
 function block(k,blk,i,n){
  const p=`home.${i}.`;let body='';
  if(blk.type==='hero')body=field('Kleine Zeile',blk.eyebrow,p+'eyebrow',k,'z. B. WILLKOMMEN',80)+field('Überschrift',blk.title,p+'title',k,'',160)+area('Text',blk.text,p+'text',k,600);
  else if(blk.type==='tiles'){
   const tiles=(blk.tiles||[]).map((t,ti)=>`<div class="ap-tile"><b>${esc(TILES[t.id]||t.id)}</b>
     <input class="fc" maxlength="40" placeholder="eigener Titel (optional)" value="${esc(t.title||'')}" oninput="AppBuilder.set(${A(k)},'${p}tiles.${ti}.title',this.value)">
     ${t.id==='link'?`<input class="fc" placeholder="https://…" value="${esc(t.url||'')}" oninput="AppBuilder.set(${A(k)},'${p}tiles.${ti}.url',this.value)">`:''}
     <span class="ap-ctl"><button class="btn-g" ${ti<=0?'disabled':''} onclick="AppBuilder.moveTile(${A(k)},${i},${ti},-1)"><i class="fas fa-arrow-up"></i></button><button class="btn-g" ${ti>=(blk.tiles||[]).length-1?'disabled':''} onclick="AppBuilder.moveTile(${A(k)},${i},${ti},1)"><i class="fas fa-arrow-down"></i></button><button class="btn-g" onclick="AppBuilder.delTile(${A(k)},${i},${ti})"><i class="fas fa-xmark"></i></button></span></div>`).join('');
   const opts=Object.keys(TILES).map(id=>`<option value="${id}">${esc(TILES[id])}</option>`).join('');
   body=field('Überschrift (optional)',blk.title,p+'title',k,'z. B. Schnellzugriff',60,true)+`<div class="ap-wide">${tiles||'<p class="hint">Noch keine Kacheln.</p>'}<div class="ap-add"><select class="fc" id="apbT-${esc(k)}-${i}">${opts}</select><button class="btn-g" onclick="AppBuilder.addTile(${A(k)},${i})" ${(blk.tiles||[]).length>=8?'disabled':''}><i class="fas fa-plus"></i> Kachel</button></div></div>`;
  }
  else if(blk.type==='stations')body=field('Überschrift',blk.title,p+'title',k,'z. B. Unsere Sender',60)+`<div><label class="news-lbl">Anzahl (0 = alle)</label><input class="fc w-100" type="number" min="0" max="40" value="${+blk.limit||0}" oninput="AppBuilder.set(${A(k)},'${p}limit',+this.value||0)"></div>`;
  else if(blk.type==='text')body=field('Überschrift',blk.title,p+'title',k,'',80,true)+area('Text',blk.text,p+'text',k,600);
  else if(blk.type==='link')body=field('Überschrift',blk.title,p+'title',k,'',80)+field('Link (https)',blk.url,p+'url',k,'https://…',300)+field('Beschriftung',blk.label,p+'label',k,'Mehr erfahren',40)+area('Text',blk.text,p+'text',k,400);
  return `<div class="ap-blk"><div class="ap-blk-h"><b>${esc(BLOCKS[blk.type]||blk.type)}</b>${ctl(k,i,n,'moveBlock')}<button class="btn-g" onclick="AppBuilder.delBlock(${A(k)},${i})" title="Entfernen"><i class="fas fa-trash"></i></button></div><div class="ap-grid">${body}</div></div>`;
 }
 function stationsEditor(k,b){
  const o=order(b);if(!o.length)return '<p class="hint">Keine Core-Sender vorhanden.</p>';
  return o.map((s,i)=>`<div class="ap-st"><label class="ap-check"><input type="checkbox" ${b.stations.hidden.includes(s)?'':'checked'} onchange="AppBuilder.toggleStation(${A(k)},'${esc(s)}',this.checked)"> ${esc(s)}</label>${ctl(k,i,o.length,'moveStation')}</div>`).join('');
 }
 function customEditor(k,b){
  const c=b.stations.custom=b.stations.custom||[];
  const rows=c.map((x,i)=>`<div class="ap-tile"><input class="fc" maxlength="40" placeholder="Sendername" value="${esc(x.title||'')}" oninput="AppBuilder.set(${A(k)},'stations.custom.${i}.title',this.value)"><input class="fc" placeholder="Stream-Adresse https://…" value="${esc(x.stream||'')}" oninput="AppBuilder.set(${A(k)},'stations.custom.${i}.stream',this.value)"><input class="fc" placeholder="Logo-Adresse https://… (optional)" value="${esc(x.logo||'')}" oninput="AppBuilder.set(${A(k)},'stations.custom.${i}.logo',this.value)"><button class="btn-g" onclick="AppBuilder.delCustom(${A(k)},${i})" title="Entfernen"><i class="fas fa-trash"></i></button></div>`).join('');
  return `<div class="ap-sub">Eigene Sender (beliebige https-Streams, max. 20)</div>${rows||'<p class="hint">Noch keine eigenen Sender.</p>'}<button class="btn-g" onclick="AppBuilder.addCustom(${A(k)})" ${c.length>=20?'disabled':''}><i class="fas fa-plus"></i> Sender</button>`;
 }
 function menuEditor(k,b){
  const MENU=menu(),hide=Object.keys(MENU).map(m=>`<label class="ap-check"><input type="checkbox" ${b.more_menu.hide.includes(m)?'':'checked'} onchange="AppBuilder.toggleMenu(${A(k)},'${m}',this.checked)"> ${esc(MENU[m])}</label>`).join('');
  const cu=b.more_menu.custom.map((c,i)=>`<div class="ap-tile"><input class="fc" maxlength="40" placeholder="Titel" value="${esc(c.title||'')}" oninput="AppBuilder.set(${A(k)},'more_menu.custom.${i}.title',this.value)"><input class="fc" placeholder="https://…" value="${esc(c.url||'')}" oninput="AppBuilder.set(${A(k)},'more_menu.custom.${i}.url',this.value)"><input class="fc" maxlength="60" placeholder="Untertitel (optional)" value="${esc(c.sub||'')}" oninput="AppBuilder.set(${A(k)},'more_menu.custom.${i}.sub',this.value)"><button class="btn-g" onclick="AppBuilder.delMenu(${A(k)},${i})"><i class="fas fa-xmark"></i></button></div>`).join('');
  return `<div class="ap-checks">${hide}</div><div class="ap-sub">Eigene Einträge (max. 6, öffnen im Browser)</div>${cu}<button class="btn-g" onclick="AppBuilder.addMenu(${A(k)})" ${b.more_menu.custom.length>=6?'disabled':''}><i class="fas fa-plus"></i> Eintrag</button>`;
 }
 function preview(b){
  const ac=b.theme.accent||DEF.accent,f=b.theme.hero_from||DEF.from,t=b.theme.hero_to||DEF.to;
  const o=order(b).filter(s=>!b.stations.hidden.includes(s)).concat((b.stations.custom||[]).filter(c=>c.title&&c.stream).map(c=>c.title));
  const parts=b.home.map(x=>{
   if(x.type==='hero')return `<div class="pv-hero" style="background:linear-gradient(135deg,${esc(f)},${esc(t)})"><small>${esc(x.eyebrow||'')}</small><b>${esc(x.title||'Willkommen')}</b><span>${esc(x.text||'')}</span><i style="background:${esc(ac)}">▶ Jetzt hören</i></div>`;
   if(x.type==='tiles')return `${x.title?`<div class="pv-h">${esc(x.title)}</div>`:''}<div class="pv-tiles">${(x.tiles||[]).map(tl=>`<div>${esc(tl.title||TILES[tl.id]||tl.id)}</div>`).join('')}</div>`;
   if(x.type==='stations')return `${x.title?`<div class="pv-h">${esc(x.title)}</div>`:''}<div class="pv-st">${(+x.limit?o.slice(0,+x.limit):o).map(s=>`<div>${esc(s)}</div>`).join('')}</div>`;
   if(x.type==='text')return `<div class="pv-card"><b>${esc(x.title||'')}</b><span>${esc(x.text||'')}</span></div>`;
   if(x.type==='link')return `<div class="pv-card"><b>${esc(x.title||'')}</b><span>${esc(x.text||'')}</span><i style="color:${esc(ac)}">${esc(x.label||'Öffnen')} ›</i></div>`;
   return '';
  }).join('');
  return `<div class="pv-phone"><div class="pv-bar" style="color:${esc(ac)}">● App-Vorschau</div>${parts||'<p class="hint" style="padding:12px">Leer: Es wird die Standard-Startseite der App gezeigt.</p>'}</div>`;
 }
 function html(k){
  const b=cfg(k);if(!b)return '';
  const add=Object.keys(BLOCKS).map(t=>`<button class="btn-g" onclick="AppBuilder.addBlock(${A(k)},'${t}')" ${b.home.length>=14?'disabled':''}><i class="fas fa-plus"></i> ${esc(BLOCKS[t])}</button>`).join('');
  return `<div class="ap-builder" data-apb="${esc(k)}"><div class="ap-bcols"><div class="ap-bleft">
   <label class="ap-check"><input type="checkbox" ${b.enabled?'checked':''} onchange="AppBuilder.set(${A(k)},'enabled',this.checked,true)"> <b>Builder aktiv</b> – die App übernimmt Farben, Startseite, Sender und Menü aus dem CMS (aus = Standard-Layout)</label>
   <div class="ap-sub">Farben</div><div class="ap-grid">${color('Akzentfarbe (Buttons, Highlights)',b.theme.accent,'theme.accent',k,DEF.accent)}${color('Karte von',b.theme.hero_from,'theme.hero_from',k,DEF.from)}${color('Karte bis',b.theme.hero_to,'theme.hero_to',k,DEF.to)}</div>
   <div class="ap-sub">Startseite (von oben nach unten; leer = Standard-Startseite)</div>${b.home.map((x,i)=>block(k,x,i,b.home.length)).join('')}<div class="ap-add">${add}</div>
   <div class="ap-sub">Sender: Reihenfolge &amp; Sichtbarkeit (Core-Netzwerk)</div>${stationsEditor(k,b)}${customEditor(k,b)}
   <div class="ap-sub">„Mehr“-Menü</div>${menuEditor(k,b)}
  </div><div class="ap-bright">${preview(b)}</div></div></div>`;
 }
 function redraw(k){const el=document.querySelector(`[data-apb="${CSS.escape(k)}"]`);if(el)el.outerHTML=html(k);}
 function refreshPreview(k){const el=document.querySelector(`[data-apb="${CSS.escape(k)}"] .ap-bright`);const b=cfg(k);if(el&&b)el.innerHTML=preview(b);}
 function setPath(o,path,val){const p=path.split('.');for(let i=0;i<p.length-1;i++){o=o[p[i]]=o[p[i]]||{};}o[p[p.length-1]]=val;}
 const api={html,
  set(k,path,val,redo){const b=cfg(k);if(!b)return;setPath(b,path,val);redo?redraw(k):refreshPreview(k);},
  addBlock(k,t){const b=cfg(k);if(!b||b.home.length>=14)return;const n={hero:{type:'hero',eyebrow:'',title:'',text:''},tiles:{type:'tiles',title:'',tiles:[]},stations:{type:'stations',title:'',limit:0},text:{type:'text',title:'',text:''},link:{type:'link',title:'',text:'',url:'',label:''}}[t];if(n){b.home.push(n);redraw(k);}},
  delBlock(k,i){const b=cfg(k);b.home.splice(i,1);redraw(k);},
  moveBlock(k,i,d){const b=cfg(k),j=i+d;if(j<0||j>=b.home.length)return;[b.home[i],b.home[j]]=[b.home[j],b.home[i]];redraw(k);},
  addTile(k,i){const b=cfg(k),blk=b.home[i];const sel=document.getElementById('apbT-'+k+'-'+i);if(!blk||!sel||(blk.tiles=blk.tiles||[]).length>=8)return;blk.tiles.push({id:sel.value,title:'',sub:'',url:''});redraw(k);},
  delTile(k,i,t){cfg(k).home[i].tiles.splice(t,1);redraw(k);},
  moveTile(k,i,t,d){const a=cfg(k).home[i].tiles,j=t+d;if(j<0||j>=a.length)return;[a[t],a[j]]=[a[j],a[t]];redraw(k);},
  moveStation(k,i,d){const b=cfg(k),o=order(b),j=i+d;if(j<0||j>=o.length)return;[o[i],o[j]]=[o[j],o[i]];b.stations.order=o;redraw(k);},
  toggleStation(k,s,on){const b=cfg(k),h=b.stations.hidden.filter(x=>x!==s);if(!on)h.push(s);b.stations.hidden=h;refreshPreview(k);},
  toggleMenu(k,m,on){const b=cfg(k),h=b.more_menu.hide.filter(x=>x!==m);if(!on)h.push(m);b.more_menu.hide=h;},
  addCustom(k){const b=cfg(k),c=b.stations.custom=b.stations.custom||[];if(c.length>=20)return;c.push({title:'',stream:'',logo:''});redraw(k);},
  delCustom(k,i){cfg(k).stations.custom.splice(i,1);redraw(k);},
  addMenu(k){const b=cfg(k);if(b.more_menu.custom.length>=6)return;b.more_menu.custom.push({title:'',sub:'',url:''});redraw(k);},
  delMenu(k,i){cfg(k).more_menu.custom.splice(i,1);redraw(k);}
 };
 return api;
})();
