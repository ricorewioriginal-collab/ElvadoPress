'use strict';
// Widget-Verwaltung wie bei WordPress: links die Bibliothek der Widget-Typen, rechts die
// aufklappbaren Widget-Bereiche des aktiven Themes mit ihren Widget-Instanzen. Jede Instanz
// hat eigenen Titel, eigene Einstellungen (Schema des Typs) und eigene Geräte-Sichtbarkeit.
// Gespeichert wird erst mit "Aktualisieren" (Rückgängig/Wiederholen bis dahin lokal).
window.WidgetsManager=(()=>{
 const T=[
  {id:'recent-posts',name:'Letzte Beiträge',icon:'fa-newspaper',cat:'Inhalte',desc:'Die neuesten News-Beiträge als Liste.',fields:[{k:'count',l:'Anzahl Beiträge',t:'int',min:1,max:10},{k:'show_date',l:'Datum anzeigen',t:'bool'},{k:'show_thumb',l:'Vorschaubild anzeigen',t:'bool'},{k:'category',l:'Nur Kategorie (leer = alle)',t:'text'}]},
  {id:'categories',name:'Kategorien',icon:'fa-folder-tree',cat:'Inhalte',desc:'Alle News-Kategorien mit Anzahl.',fields:[{k:'show_counts',l:'Anzahl anzeigen',t:'bool'}]},
  {id:'tags',name:'Schlagwörter',icon:'fa-tags',cat:'Inhalte',desc:'Schlagwort-Wolke aus den Beiträgen.',fields:[{k:'max',l:'Maximale Anzahl',t:'int',min:5,max:40}]},
  {id:'archives',name:'Archiv',icon:'fa-box-archive',cat:'Inhalte',desc:'Beiträge nach Monat.',fields:[{k:'months',l:'Monate',t:'int',min:3,max:24},{k:'show_counts',l:'Anzahl anzeigen',t:'bool'}]},
  {id:'calendar',name:'Kalender',icon:'fa-calendar-days',cat:'Inhalte',desc:'Monatskalender, Tage mit Beiträgen sind verlinkt.',fields:[]},
  {id:'search',name:'Suche',icon:'fa-magnifying-glass',cat:'Inhalte',desc:'Suchfeld mit Live-Treffern aus den News.',fields:[{k:'placeholder',l:'Platzhalter',t:'text'}]},
  {id:'recent-comments',name:'Letzte Kommentare',icon:'fa-comments',cat:'Inhalte',desc:'Die neuesten freigegebenen Kommentare.',fields:[{k:'count',l:'Anzahl',t:'int',min:1,max:10}]},
  {id:'podcast',name:'Podcast',icon:'fa-podcast',cat:'Inhalte',desc:'Neueste Podcast-Folgen, direkt abspielbar.',fields:[{k:'count',l:'Anzahl Folgen',t:'int',min:1,max:10},{k:'show_images',l:'Cover anzeigen',t:'bool'}]},
  {id:'stations',name:'Senderliste',icon:'fa-tower-broadcast',cat:'Radio',desc:'Sender mit Play-Button.',fields:[{k:'count',l:'Anzahl Sender',t:'int',min:1,max:20},{k:'layout',l:'Darstellung',t:'select',o:[['list','Liste'],['grid','Raster']]},{k:'show_genres',l:'Genres anzeigen',t:'bool'}]},
  {id:'now-playing',name:'Jetzt läuft',icon:'fa-compact-disc',cat:'Radio',desc:'Aktueller Sender und Titel des Players.',fields:[{k:'show_cover',l:'Cover anzeigen',t:'bool'}]},
  {id:'schedule',name:'Sendeplan',icon:'fa-calendar-check',cat:'Radio',desc:'Nächste Sendungen von heute.',fields:[{k:'count',l:'Anzahl Sendungen',t:'int',min:1,max:10},{k:'station',l:'Nur Sender (ID, leer = alle)',t:'station'}]},
  {id:'random-station',name:'Überrasch mich',icon:'fa-shuffle',cat:'Radio',desc:'Spielt einen zufälligen Sender.',fields:[{k:'label',l:'Beschriftung',t:'text'}]},
  {id:'favorites',name:'Meine Favoriten',icon:'fa-star',cat:'Radio',desc:'Öffnet die Favoriten des Hörers.',fields:[]},
  {id:'voting',name:'Netzwerk-Voting',icon:'fa-ranking-star',cat:'Interaktion',desc:'Link zum Sender-Voting des Netzwerks.',fields:[]},
  {id:'song-voting',name:'Song-Voting',icon:'fa-chart-simple',cat:'Interaktion',desc:'Song-Voting eines Senders.',fields:[{k:'label',l:'Beschriftung',t:'text'},{k:'station',l:'Sender (leer = Auswahl)',t:'station'}]},
  {id:'studiomail',name:'Studiomail',icon:'fa-envelope-open-text',cat:'Interaktion',desc:'Nachricht ans Studio.',fields:[{k:'label',l:'Beschriftung',t:'text'}]},
  {id:'voicemail',name:'Voicemail',icon:'fa-microphone-lines',cat:'Interaktion',desc:'Sprachnachricht aufnehmen.',fields:[{k:'label',l:'Beschriftung',t:'text'},{k:'station',l:'Sender (leer = Auswahl)',t:'station'}]},
  {id:'wunsch',name:'Musikwunsch',icon:'fa-music',cat:'Interaktion',desc:'Musikwunsch an einen Sender.',fields:[{k:'label',l:'Beschriftung',t:'text'},{k:'station',l:'Sender (leer = Auswahl)',t:'station'}]},
  {id:'poll',name:'Umfrage',icon:'fa-square-poll-vertical',cat:'Interaktion',desc:'Umfrage eines Senders.',fields:[{k:'label',l:'Beschriftung',t:'text'},{k:'station',l:'Sender (leer = Auswahl)',t:'station'}]},
  {id:'social-wall',name:'Social Wall',icon:'fa-hashtag',cat:'Social',desc:'TikTok und Instagram von RicoReWi und AnMaCha.',fields:[]},
  {id:'social-single',name:'Social-Profil',icon:'fa-at',cat:'Social',desc:'Ein einzelnes TikTok- oder Instagram-Profil.',fields:[{k:'platform',l:'Plattform',t:'select',o:[['tiktok','TikTok'],['instagram','Instagram']]},{k:'creator',l:'Profil',t:'select',o:[['ricorewi','RicoReWi'],['anmacha','AnMaCha']]}]},
  {id:'text',name:'Text',icon:'fa-align-left',cat:'Eigene',desc:'Einfacher Text, Absätze durch Leerzeile.',fields:[{k:'text',l:'Text',t:'textarea'}]},
  {id:'html',name:'Eigenes HTML',icon:'fa-code',cat:'Eigene',desc:'Beliebiger HTML-Code.',fields:[{k:'html',l:'HTML',t:'html'}]},
  {id:'button',name:'Button',icon:'fa-hand-pointer',cat:'Eigene',desc:'Ein Link als Button.',fields:[{k:'label',l:'Beschriftung',t:'text'},{k:'url',l:'Link (URL oder #seite)',t:'text'},{k:'style',l:'Stil',t:'select',o:[['primary','Akzent'],['ghost','Umrandet']]},{k:'icon',l:'Icon (z.B. fa-arrow-right)',t:'text'}]},
  {id:'menu',name:'Navigationsmenü',icon:'fa-bars',cat:'Eigene',desc:'Ein Menü aus der Menü-Verwaltung.',fields:[{k:'menu',l:'Menü',t:'select',o:[['top','Top Navigation'],['bottom','Bottom Navigation']]},{k:'style',l:'Darstellung',t:'select',o:[['list','Liste'],['chips','Chips']]}]},
  {id:'image',name:'Bild',icon:'fa-image',cat:'Medien',desc:'Ein Bild mit optionalem Link.',fields:[{k:'url',l:'Bild-URL',t:'media'},{k:'alt',l:'Alternativtext',t:'text'},{k:'link',l:'Link (optional)',t:'text'},{k:'caption',l:'Bildunterschrift',t:'text'}]},
  {id:'gallery',name:'Galerie',icon:'fa-images',cat:'Medien',desc:'Mehrere Bilder im Raster.',fields:[{k:'urls',l:'Bild-URLs (eine pro Zeile)',t:'textarea'},{k:'columns',l:'Spalten',t:'int',min:2,max:4}]},
  {id:'audio',name:'Audio',icon:'fa-volume-high',cat:'Medien',desc:'Audio-Player für eine Datei.',fields:[{k:'url',l:'Audio-URL',t:'media'},{k:'caption',l:'Beschreibung',t:'text'}]},
  {id:'embed',name:'Einbettung',icon:'fa-window-maximize',cat:'Medien',desc:'Externe Seite als iframe.',fields:[{k:'url',l:'URL',t:'text'},{k:'height',l:'Höhe (px)',t:'int',min:120,max:1400}]},
  {id:'community-social',name:'Soziales Netzwerk',icon:'fa-share-nodes',cat:'Community',desc:'Feed mit Statusbeiträgen, Likes, Folgen und Profilen (Soziales Netzwerk muss unter Community aktiviert sein).',fields:[]},
  {id:'community-forum',name:'Forum',icon:'fa-comments',cat:'Community',desc:'Diskussionsforum mit Kategorien, Themen und Antworten (Forum muss unter Community aktiviert sein).',fields:[]},
  {id:'community-account',name:'Mitgliederbereich',icon:'fa-user-circle',cat:'Community',desc:'Anmelden, Registrieren und Profil der Mitglieder (Community muss unter Community aktiviert sein).',fields:[]},
  {id:'cms-poll',name:'Umfrage (CMS)',icon:'fa-square-poll-horizontal',cat:'Interaktion',desc:'Eigene Umfrage aus Inhalte → Umfragen (unabhängig vom Radio).',fields:[{k:'poll_id',l:'Umfrage',t:'poll'}]},
  {id:'faq',name:'FAQ / Akkordeon',icon:'fa-circle-question',cat:'Eigene',desc:'Fragen und Antworten zum Aufklappen.',fields:[{k:'items',l:'Fragen & Antworten (erste Zeile = Frage, darunter die Antwort; eine Leerzeile trennt Einträge)',t:'textarea'},{k:'open_first',l:'Ersten Eintrag geöffnet zeigen',t:'bool'}]},
  {id:'countdown',name:'Countdown',icon:'fa-hourglass-half',cat:'Eigene',desc:'Zählt die Zeit bis zu einem Ereignis herunter.',fields:[{k:'target',l:'Zeitpunkt (JJJJ-MM-TT HH:MM)',t:'text'},{k:'label',l:'Beschriftung',t:'text'},{k:'done',l:'Text nach Ablauf',t:'text'}]},
  {id:'video',name:'Video',icon:'fa-circle-play',cat:'Medien',desc:'YouTube, Vimeo oder eine MP4/WebM-Datei (externe Videos erst nach Zustimmung).',fields:[{k:'url',l:'Video-Adresse',t:'text'},{k:'caption',l:'Beschreibung',t:'text'}]},
  {id:'map',name:'Karte (OpenStreetMap)',icon:'fa-map-location-dot',cat:'Medien',desc:'Kartenausschnitt mit Markierung (externe Karte erst nach Zustimmung).',fields:[{k:'lat',l:'Breitengrad (z. B. 52.5200)',t:'text'},{k:'lon',l:'Längengrad (z. B. 13.4050)',t:'text'},{k:'zoom',l:'Zoom',t:'int',min:3,max:19},{k:'height',l:'Höhe (px)',t:'int',min:150,max:800},{k:'label',l:'Linktext',t:'text'}]},
  {id:'social-links',name:'Social-Links',icon:'fa-share-nodes',cat:'Social',desc:'Eigene Links zu Profilen und Seiten.',fields:[{k:'items',l:'Einträge: Name|Adresse|Symbol (eine Zeile je Link, z. B. Instagram|https://instagram.com/name|fa-brands fa-instagram)',t:'textarea'},{k:'style',l:'Darstellung',t:'select',o:[['icons','Nur Symbole'],['list','Mit Namen']]}]},
  {id:'contact-form',name:'Kontaktformular',icon:'fa-envelope',cat:'Interaktion',desc:'Nachrichten landen unter Inhalte → Einsendungen, optional zusätzlich per E-Mail.',fields:[{k:'intro',l:'Einleitung',t:'textarea'},{k:'notify',l:'Zusätzlich per E-Mail an (optional)',t:'text'},{k:'button',l:'Beschriftung des Buttons',t:'text'},{k:'success',l:'Text nach dem Senden',t:'text'},{k:'consent',l:'Einwilligungstext',t:'text'},{k:'subject',l:'Feld „Betreff“ anzeigen',t:'bool'}]},
  {id:'newsletter',name:'Newsletter-Anmeldung',icon:'fa-paper-plane',cat:'Interaktion',desc:'Sammelt E-Mail-Adressen (Export als CSV unter Inhalte → Einsendungen).',fields:[{k:'intro',l:'Einleitung',t:'textarea'},{k:'notify',l:'Zusätzlich per E-Mail an (optional)',t:'text'},{k:'button',l:'Beschriftung des Buttons',t:'text'},{k:'success',l:'Text nach dem Anmelden',t:'text'},{k:'consent',l:'Einwilligungstext',t:'text'}]},
 ];
 const STATIONS=['ricorewi','yourtime-fm','rapradio24','schlagerpop24','chartradio24','clubradio24','anmacha24','radiofloh','rockradio24','christmasradio24','kultradio24','zockerfm','special-radio'];
 const KIND_LABEL={sidebar:'Sidebar',footer:'Footer',content:'Inhalt'};
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 /* Radio-Paket (RicoReWi-Design): ohne das Paket nicht in der Bibliothek; vorhandene Widgets bleiben erhalten */
 const PACK_RADIO=new Set(['stations','now-playing','schedule','random-station','favorites','podcast','voting','song-voting','studiomail','voicemail','wunsch','poll','social-wall','social-single']);
 const avail=()=>T.filter(t=>!PACK_RADIO.has(t.id));
 const typeOf=id=>T.find(t=>t.id===id)||{id,name:id,icon:'fa-puzzle-piece',cat:'Eigene',desc:'',fields:[]};
 const S={open:new Set(),openInst:new Set(),search:'',history:[],future:[],baseline:'',picker:null,target:'',drag:null,inited:false};
 const areas=()=>(CMS.widget_areas=CMS.widget_areas||[]);
 const inactive=()=>(CMS.widget_inactive=CMS.widget_inactive||[]);
 const snap=()=>JSON.stringify({a:areas(),i:inactive()});
 const dirty=()=>snap()!==S.baseline;
 function commit(){S.history.push(snap());if(S.history.length>40)S.history.shift();S.future=[];}
 function restore(json){const d=JSON.parse(json);CMS.widget_areas=d.a;CMS.widget_inactive=d.i;render();}
 function undo(){if(!S.history.length)return;S.future.push(snap());restore(S.history.pop());}
 function redo(){if(!S.future.length)return;S.history.push(snap());restore(S.future.pop());}
 function newInstance(typeId){const t=typeOf(typeId),settings={};t.fields.forEach(f=>{settings[f.k]=f.t==='bool'?true:f.t==='int'?(f.min||1):f.t==='select'?f.o[0][0]:'';});
  if(typeId==='recent-posts')settings.count=5;if(typeId==='stations')settings.count=6;if(typeId==='schedule')settings.count=5;if(typeId==='podcast')settings.count=3;if(typeId==='embed')settings.height=520;if(typeId==='gallery')settings.columns=3;if(typeId==='tags')settings.max=20;if(typeId==='archives')settings.months=12;if(typeId==='recent-comments')settings.count=5;
  if(typeId==='button'){settings.label='Mehr erfahren';settings.url='#';}if(typeId==='search')settings.placeholder='News durchsuchen …';if(typeId==='random-station')settings.label='Überrasch mich';
  return {id:uid('wi'),type:typeId,title:t.name,settings,visibility:{mode:'hide',desktop:false,tablet:false,mobile:false}};}
 function findInst(id){for(const a of areas()){const i=(a.widgets||[]).findIndex(w=>w.id===id);if(i>=0)return {list:a.widgets,i,area:a};}const i=inactive().findIndex(w=>w.id===id);return i>=0?{list:inactive(),i,area:null}:null;}
 function listFor(areaId){if(areaId==='__inactive')return inactive();const a=areas().find(x=>x.id===areaId);if(!a)return null;a.widgets=a.widgets||[];return a.widgets;}
 // ---- Aktionen (global erreichbar für onclick) ----
 const api={
  render, undo, redo,
  sync(){S.inited=false;S.history=[];S.future=[];S.picker=null;},
  toggleArea(id){S.open.has(id)?S.open.delete(id):S.open.add(id);render();},
  toggleInst(id){S.openInst.has(id)?S.openInst.delete(id):S.openInst.add(id);render();},
  search(v){S.search=v;renderLibrary();},
  setTarget(v){S.target=v;},
  addType(typeId,areaId){const list=listFor(areaId||S.target||firstAreaId());if(!list)return cmsToast('Bitte zuerst einen Bereich anlegen',true);commit();const w=newInstance(typeId);list.push(w);S.openInst.add(w.id);if(areaId)S.open.add(areaId);S.picker=null;render();},
  openPicker(areaId){S.picker=S.picker===areaId?null:areaId;render();},
  upd(id,key,val){const f=findInst(id);if(!f)return;if(!S._typing){commit();S._typing=true;clearTimeout(S._tt);}clearTimeout(S._tt);S._tt=setTimeout(()=>S._typing=false,1200);const w=f.list[f.i];if(key==='title')w.title=val;else w.settings[key]=val;updateDirty();},
  updVis(id,key,val){const f=findInst(id);if(!f)return;commit();const w=f.list[f.i];w.visibility=w.visibility||{};w.visibility[key]=val;updateDirty();},
  move(id,dir){const f=findInst(id);if(!f)return;const j=f.i+dir;if(j<0||j>=f.list.length)return;commit();const [w]=f.list.splice(f.i,1);f.list.splice(j,0,w);render();},
  moveTo(id,areaId){const f=findInst(id);if(!f)return;const list=listFor(areaId);if(!list||list===f.list)return;commit();const [w]=f.list.splice(f.i,1);list.push(w);S.open.add(areaId);render();},
  remove(id){const f=findInst(id);if(!f)return;if(!confirm('Widget wirklich löschen?'))return;commit();f.list.splice(f.i,1);S.openInst.delete(id);render();},
  duplicate(id){const f=findInst(id);if(!f)return;commit();const c=JSON.parse(JSON.stringify(f.list[f.i]));c.id=uid('wi');f.list.splice(f.i+1,0,c);render();},
  preview(id){window.open(location.origin+'/?cms_widget_preview='+encodeURIComponent(id)+'&embed=1','_blank','noopener');},
  addArea(){commit();areas().push({id:uid('area'),name:'Neuer Bereich',kind:'sidebar',scope:'global',page_id:'',position:'right',enabled:true,widgets:[]});S.open.add(areas()[areas().length-1].id);render();},
  updArea(id,key,val){const a=areas().find(x=>x.id===id);if(!a)return;commit();a[key]=val;if(key==='kind'||key==='scope')render();else updateDirty();},
  removeArea(id){const a=areas().find(x=>x.id===id);if(!a)return;if(!confirm('Bereich „'+a.name+'“ löschen? Enthaltene Widgets wandern zu „Inaktive Widgets“.'))return;commit();inactive().push(...(a.widgets||[]));CMS.widget_areas=areas().filter(x=>x.id!==id);render();},
  async reset(){await resetThemeAreas();S.baseline=snap();S.history=[];S.future=[];render();},
  async save(){
   const btn=document.getElementById('wmSaveBtn');if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Speichere…';}
   try{const a=await cmsApi('save',{section:'widget_areas',value:areas()});CMS.widget_areas=a.value;const i=await cmsApi('save',{section:'widget_inactive',value:inactive()});CMS.widget_inactive=i.value;S.baseline=snap();S.history=[];S.future=[];
    const live=await verifyPublicSection('widget_areas',a.value);cmsToast(live?'Widgets aktualisiert & live ✓':'Gespeichert, Live-Stand bitte prüfen',!live);}
   catch(e){cmsToast(e.message,true);}
   render();
  },
  // Drag & Drop: Bibliothek -> Bereich, Instanz -> Instanz/Bereich
  dragType(ev,typeId){S.drag={type:typeId};ev.dataTransfer.effectAllowed='copy';},
  dragInst(ev,id){S.drag={inst:id};ev.dataTransfer.effectAllowed='move';ev.stopPropagation();},
  dragOver(ev){ev.preventDefault();ev.currentTarget.classList.add('dragover');},
  dragLeave(ev){ev.currentTarget.classList.remove('dragover');},
  dropArea(ev,areaId){ev.preventDefault();ev.stopPropagation();ev.currentTarget.classList.remove('dragover');const d=S.drag;S.drag=null;if(!d)return;
   if(d.type)return api.addType(d.type,areaId);
   if(d.inst){const f=findInst(d.inst),list=listFor(areaId);if(!f||!list)return;commit();const [w]=f.list.splice(f.i,1);list.push(w);S.open.add(areaId);render();}},
  dropInst(ev,targetId){ev.preventDefault();ev.stopPropagation();ev.currentTarget.classList.remove('dragover');const d=S.drag;S.drag=null;if(!d)return;
   const tf=findInst(targetId);if(!tf)return;
   if(d.type){commit();const w=newInstance(d.type);tf.list.splice(tf.i,0,w);S.openInst.add(w.id);render();return;}
   if(d.inst&&d.inst!==targetId){const f=findInst(d.inst);if(!f)return;commit();const [w]=f.list.splice(f.i,1);const tf2=findInst(targetId);tf2.list.splice(tf2.i,0,w);render();}},
 };
 function firstAreaId(){const a=areas();return (a.find(x=>x.kind==='sidebar')||a[0])?.id||'';}
 function updateDirty(){const btn=document.getElementById('wmSaveBtn');if(btn){const d=dirty();btn.disabled=!d;btn.innerHTML=d?'<i class="fas fa-floppy-disk"></i> Aktualisieren':'<i class="fas fa-check"></i> Gespeichert';}
  const u=document.getElementById('wmUndoBtn'),r=document.getElementById('wmRedoBtn');if(u)u.disabled=!S.history.length;if(r)r.disabled=!S.future.length;}
 // ---- Rendering ----
 function fieldHtml(w,f){
  const v=w.settings?.[f.k];const on=`oninput="WidgetsManager.upd('${esc(w.id)}','${f.k}',this.value)"`;
  if(f.t==='bool')return `<label class="wm-check"><input type="checkbox" ${v!==false?'checked':''} onchange="WidgetsManager.upd('${esc(w.id)}','${f.k}',this.checked)"> ${esc(f.l)}</label>`;
  if(f.t==='int')return `<label class="news-lbl">${esc(f.l)}</label><input class="fc w-100" type="number" min="${f.min}" max="${f.max}" value="${esc(v??f.min)}" onchange="WidgetsManager.upd('${esc(w.id)}','${f.k}',parseInt(this.value,10)||${f.min})">`;
  if(f.t==='select')return `<label class="news-lbl">${esc(f.l)}</label><select class="fc w-100" onchange="WidgetsManager.upd('${esc(w.id)}','${f.k}',this.value)">${f.o.map(([val,lab])=>'<option value="'+esc(val)+'" '+(val===v?'selected':'')+'>'+esc(lab)+'</option>').join('')}</select>`;
  if(f.t==='textarea'||f.t==='html')return `<label class="news-lbl">${esc(f.l)}</label><textarea class="fc w-100" rows="${f.t==='html'?6:4}" ${f.t==='html'?'style="font-family:monospace;font-size:.78rem"':''} ${on}>${esc(v||'')}</textarea>`;
  if(f.t==='poll'){const list=window.__cmsPolls||[];const opts=[['','Neueste offene Umfrage'],...list.map(p=>[p.id,p.question]),...(v&&!list.some(p=>p.id===v)?[[v,'(Umfrage '+v+')']]:[])];return `<label class="news-lbl">${esc(f.l)}</label><select class="fc w-100" onchange="WidgetsManager.upd('${esc(w.id)}','${f.k}',this.value)">${opts.map(([val,lab])=>'<option value="'+esc(val)+'" '+(val===(v||'')?'selected':'')+'>'+esc(lab)+'</option>').join('')}</select>`}
  if(f.t==='station')return `<label class="news-lbl">${esc(f.l)}</label><input class="fc w-100" list="wmStations" value="${esc(v||'')}" ${on}>`;
  if(f.t==='media')return `<label class="news-lbl">${esc(f.l)}</label><div style="display:flex;gap:6px"><input class="fc w-100" value="${esc(v||'')}" placeholder="https://… oder /cms/media/…" ${on}>${window.MediaManager?.pick?'<button class="btn-g" type="button" onclick="MediaManager.pick(u=>{WidgetsManager.upd(\''+esc(w.id)+'\',\''+f.k+'\',u);WidgetsManager.render()})"><i class="fas fa-photo-film"></i></button>':''}</div>`;
  return `<label class="news-lbl">${esc(f.l)}</label><input class="fc w-100" value="${esc(v||'')}" ${on}>`;
 }
 function instHtml(w,areaId){
  const t=typeOf(w.type),open=S.openInst.has(w.id),vis=w.visibility||{},hidden=['desktop','tablet','mobile'].filter(d=>vis[d]);
  const visNote=hidden.length?((vis.mode==='show'?'nur auf ':'nicht auf ')+hidden.map(d=>({desktop:'Desktop',tablet:'Tablet',mobile:'Mobil'})[d]).join(', ')):'';
  const others=[...areas().filter(a=>a.id!==areaId).map(a=>[a.id,a.name]),...(areaId!=='__inactive'?[['__inactive','Inaktive Widgets']]:[])];
  return `<div class="wm-inst ${open?'open':''}" draggable="true" ondragstart="WidgetsManager.dragInst(event,'${esc(w.id)}')" ondragover="WidgetsManager.dragOver(event)" ondragleave="WidgetsManager.dragLeave(event)" ondrop="WidgetsManager.dropInst(event,'${esc(w.id)}')">
   <div class="wm-inst-head" onclick="WidgetsManager.toggleInst('${esc(w.id)}')"><i class="fas fa-grip-vertical wm-grip"></i><i class="fas ${esc(t.icon)} wm-ico"></i><div class="grow"><b>${esc(t.name)}${w.title&&w.title!==t.name?': '+esc(w.title):''}</b>${visNote?'<small><i class="fas fa-eye-slash"></i> '+esc(visNote)+'</small>':''}</div><i class="fas fa-chevron-${open?'up':'down'} wm-chev"></i></div>
   ${open?`<div class="wm-inst-body">
    <label class="news-lbl">Titel</label><input class="fc w-100" value="${esc(w.title||'')}" placeholder="${esc(t.name)}" oninput="WidgetsManager.upd('${esc(w.id)}','title',this.value)">
    ${t.fields.map(f=>'<div class="wm-field">'+fieldHtml(w,f)+'</div>').join('')}
    <div class="wm-vis"><div class="news-lbl">Sichtbarkeit</div><select class="fc" onchange="WidgetsManager.updVis('${esc(w.id)}','mode',this.value)"><option value="hide" ${vis.mode!=='show'?'selected':''}>Ausblenden auf markierten Geräten</option><option value="show" ${vis.mode==='show'?'selected':''}>Nur anzeigen auf markierten Geräten</option></select>
     <div class="wm-devices">${[['desktop','fa-desktop','Desktop'],['tablet','fa-tablet-screen-button','Tablet'],['mobile','fa-mobile-screen','Mobil']].map(([d,ic,l])=>'<label class="wm-check"><input type="checkbox" '+(vis[d]?'checked':'')+' onchange="WidgetsManager.updVis(\''+esc(w.id)+'\',\''+d+'\',this.checked)"> <i class="fas '+ic+'"></i> '+l+'</label>').join('')}</div></div>
    <div class="wm-inst-actions">
     <button class="btn-g" onclick="WidgetsManager.move('${esc(w.id)}',-1)" title="Nach oben"><i class="fas fa-arrow-up"></i></button><button class="btn-g" onclick="WidgetsManager.move('${esc(w.id)}',1)" title="Nach unten"><i class="fas fa-arrow-down"></i></button>
     <select class="fc" onchange="if(this.value){WidgetsManager.moveTo('${esc(w.id)}',this.value)}"><option value="">Verschieben nach …</option>${others.map(([id,n])=>'<option value="'+esc(id)+'">'+esc(n)+'</option>').join('')}</select>
     <button class="btn-g" onclick="WidgetsManager.duplicate('${esc(w.id)}')"><i class="fas fa-clone"></i></button><button class="btn-g" onclick="WidgetsManager.preview('${esc(w.id)}')" title="Vorschau (gespeicherter Stand)"><i class="fas fa-eye"></i></button>
     <span class="grow"></span><button class="btn-d" onclick="WidgetsManager.remove('${esc(w.id)}')"><i class="fas fa-trash"></i> Löschen</button><button class="btn-g" onclick="WidgetsManager.toggleInst('${esc(w.id)}')">Fertig</button>
    </div>
   </div>`:''}
  </div>`;
 }
 function pickerHtml(areaId){
  const cats=[...new Set(avail().map(t=>t.cat))];
  return `<div class="wm-picker"><div class="th" style="margin-bottom:8px"><b>Widget hinzufügen</b><button class="btn-g" onclick="WidgetsManager.openPicker('${esc(areaId)}')"><i class="fas fa-xmark"></i></button></div>${cats.map(c=>'<div class="widget-category-title">'+esc(c)+'</div><div class="wm-lib-grid small">'+T.filter(t=>t.cat===c).map(t=>'<button class="wm-type" onclick="WidgetsManager.addType(\''+t.id+'\',\''+esc(areaId)+'\')"><i class="fas '+t.icon+'"></i><span>'+esc(t.name)+'</span></button>').join('')+'</div>').join('')}</div>`;
 }
 function areaHtml(a){
  const open=S.open.has(a.id),pages=CMS.pages||[],n=(a.widgets||[]).length,layout=CMS.theme?.layout||{};
  const meta=[KIND_LABEL[a.kind]||a.kind,a.kind==='sidebar'?(a.position==='left'?'links':'rechts'):'',a.scope==='global'?'alle Seiten':'Seite: '+(pages.find(p=>p.id===a.page_id)?.title||a.page_id||'?')].filter(Boolean).join(' · ');
  const warn=a.kind==='sidebar'&&layout.sidebar==='none'?'<small class="wm-warn"><i class="fas fa-triangle-exclamation"></i> Theme ohne Sidebar</small>':'';
  return `<section class="wm-area ${open?'open':''} ${a.enabled?'':'off'}">
   <div class="wm-area-head" onclick="WidgetsManager.toggleArea('${esc(a.id)}')"><span class="wm-dot ${a.enabled?'on':''}"></span><div class="grow"><b>${esc(a.name)}</b><small>${esc(meta)} · ${n} Widget${n===1?'':'s'}</small>${warn}</div><i class="fas fa-chevron-${open?'up':'down'} wm-chev"></i></div>
   ${open?`<div class="wm-area-body" ondragover="WidgetsManager.dragOver(event)" ondragleave="WidgetsManager.dragLeave(event)" ondrop="WidgetsManager.dropArea(event,'${esc(a.id)}')">
     ${(a.widgets||[]).map(w=>instHtml(w,a.id)).join('')||'<div class="wm-empty">Noch kein Widget. Füge eines über „+“ hinzu oder ziehe es aus der Bibliothek hierher.</div>'}
     <button class="wm-add" onclick="WidgetsManager.openPicker('${esc(a.id)}')"><i class="fas fa-plus"></i></button>
     ${S.picker===a.id?pickerHtml(a.id):''}
     <details class="wm-area-settings"><summary><i class="fas fa-sliders"></i> Bereich-Einstellungen</summary>
      <div class="section-grid" style="margin-top:8px">
       <div><label class="news-lbl">Name</label><input class="fc w-100" value="${esc(a.name)}" oninput="WidgetsManager.updArea('${esc(a.id)}','name',this.value)"></div>
       <div><label class="news-lbl">Art</label><select class="fc w-100" onchange="WidgetsManager.updArea('${esc(a.id)}','kind',this.value)"><option value="sidebar" ${a.kind==='sidebar'?'selected':''}>Sidebar</option><option value="content" ${a.kind==='content'?'selected':''}>Unter dem Inhalt</option><option value="footer" ${a.kind==='footer'?'selected':''}>Footer</option></select></div>
       <div><label class="news-lbl">Gültigkeit</label><select class="fc w-100" onchange="WidgetsManager.updArea('${esc(a.id)}','scope',this.value)"><option value="global" ${a.scope==='global'?'selected':''}>Auf allen Seiten</option><option value="page" ${a.scope==='page'?'selected':''}>Nur auf einer Seite</option></select></div>
       ${a.scope==='page'?'<div><label class="news-lbl">Seite</label><select class="fc w-100" onchange="WidgetsManager.updArea(\''+esc(a.id)+'\',\'page_id\',this.value)">'+pages.map(p=>'<option value="'+esc(p.id)+'" '+(p.id===a.page_id?'selected':'')+'>'+esc(p.title)+'</option>').join('')+'</select></div>':''}
       ${a.kind==='sidebar'?'<div><label class="news-lbl">Position</label><select class="fc w-100" onchange="WidgetsManager.updArea(\''+esc(a.id)+'\',\'position\',this.value)"><option value="right" '+(a.position!=='left'?'selected':'')+'>Rechts</option><option value="left" '+(a.position==='left'?'selected':'')+'>Links</option></select><div class="hint" style="margin-top:4px">Die Seite legt das Theme-Layout fest, hier gilt die Position für dieses Theme.</div></div>':''}
      </div>
      <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap"><label class="wm-check"><input type="checkbox" ${a.enabled?'checked':''} onchange="WidgetsManager.updArea('${esc(a.id)}','enabled',this.checked)"> Bereich aktiv</label><span class="grow"></span><button class="btn-d" onclick="WidgetsManager.removeArea('${esc(a.id)}')"><i class="fas fa-trash"></i> Bereich löschen</button></div>
     </details>
   </div>`:''}
  </section>`;
 }
 function renderLibrary(){
  const host=document.getElementById('wmLibrary');if(!host)return;
  const q=S.search.trim().toLowerCase(),list=avail().filter(t=>!q||t.name.toLowerCase().includes(q)||t.desc.toLowerCase().includes(q)||t.cat.toLowerCase().includes(q));
  const cats=[...new Set(list.map(t=>t.cat))];
  host.innerHTML=cats.map(c=>'<div class="widget-category-title">'+esc(c)+'</div><div class="wm-lib-grid">'+list.filter(t=>t.cat===c).map(t=>'<button class="wm-type" draggable="true" ondragstart="WidgetsManager.dragType(event,\''+t.id+'\')" onclick="WidgetsManager.addType(\''+t.id+'\')" title="'+esc(t.desc)+'"><i class="fas '+t.icon+'"></i><span>'+esc(t.name)+'</span></button>').join('')+'</div>').join('')||'<div class="hint">Keine Widgets gefunden.</div>';
 }
 function render(){
  const host=document.getElementById('widgetsManager');if(!host)return;
  if(!S.inited){S.inited=true;S.baseline=snap();if(areas().length)S.open.add(firstAreaId());}
  if(!S.target||!(areas().some(a=>a.id===S.target)||S.target==='__inactive'))S.target=firstAreaId();
  const themeName=window.ThemeManager?.activeName?.()||CMS.theme?.active||'Theme',layout=CMS.theme?.layout||{};
  const groups=[['sidebar','Sidebars'],['content','Inhaltsbereiche'],['footer','Footer-Bereiche']];
  host.innerHTML=`<div class="wm-toolbar">
    <div><b><i class="fas fa-puzzle-piece" style="color:var(--accent);margin-right:6px"></i>Widgets</b><div class="hint">Theme „${esc(themeName)}“${layout.sidebar?' · Sidebar '+(layout.sidebar==='none'?'aus':layout.sidebar==='left'?'links':'rechts'):''} · Anordnung wird pro Theme gespeichert.</div></div>
    <div class="wm-toolbar-actions"><button id="wmUndoBtn" class="btn-g" onclick="WidgetsManager.undo()" title="Rückgängig"><i class="fas fa-rotate-left"></i></button><button id="wmRedoBtn" class="btn-g" onclick="WidgetsManager.redo()" title="Wiederholen"><i class="fas fa-rotate-right"></i></button><button class="btn-g" onclick="WidgetsManager.addArea()"><i class="fas fa-plus"></i> Bereich</button><button class="btn-g" onclick="WidgetsManager.reset()" title="Widget-Anordnung des Themes wiederherstellen"><i class="fas fa-brush"></i> Theme-Standard</button><button id="wmSaveBtn" class="btn-a" onclick="WidgetsManager.save()"><i class="fas fa-check"></i> Gespeichert</button></div>
   </div>
   <div class="wm-shell">
    <aside class="wm-library">
     <div class="wm-lib-head"><b>Verfügbare Widgets</b><div class="hint">Klick fügt zum Zielbereich hinzu, oder per Drag & Drop in einen Bereich ziehen.</div>
      <input class="fc w-100" placeholder="Widget suchen …" value="${esc(S.search)}" oninput="WidgetsManager.search(this.value)">
      <select class="fc w-100" style="margin-top:6px" onchange="WidgetsManager.setTarget(this.value)">${areas().map(a=>'<option value="'+esc(a.id)+'" '+(a.id===S.target?'selected':'')+'>Ziel: '+esc(a.name)+'</option>').join('')}<option value="__inactive" ${S.target==='__inactive'?'selected':''}>Ziel: Inaktive Widgets</option></select>
     </div>
     <div id="wmLibrary"></div>
     <datalist id="wmStations">${STATIONS.map(s=>'<option value="'+s+'">').join('')}</datalist>
    </aside>
    <div class="wm-main">
     ${groups.map(([kind,title])=>{const list=areas().filter(a=>a.kind===kind);return list.length?'<div class="widget-category-title">'+title+'</div>'+list.map(areaHtml).join(''):'';}).join('')}
     ${areas().length?'':'<div class="empty">Noch keine Widget-Bereiche. Lege einen Bereich an oder stelle den Theme-Standard wieder her.</div>'}
     <div class="widget-category-title" style="margin-top:14px">Inaktive Widgets</div>
     <section class="wm-area ${S.open.has('__inactive')?'open':''} off"><div class="wm-area-head" onclick="WidgetsManager.toggleArea('__inactive')"><span class="wm-dot"></span><div class="grow"><b>Inaktive Widgets</b><small>Parkplatz: Widgets samt Einstellungen aufbewahren, ohne sie anzuzeigen · ${inactive().length}</small></div><i class="fas fa-chevron-${S.open.has('__inactive')?'up':'down'} wm-chev"></i></div>
      ${S.open.has('__inactive')?'<div class="wm-area-body" ondragover="WidgetsManager.dragOver(event)" ondragleave="WidgetsManager.dragLeave(event)" ondrop="WidgetsManager.dropArea(event,\'__inactive\')">'+(inactive().map(w=>instHtml(w,'__inactive')).join('')||'<div class="wm-empty">Keine inaktiven Widgets.</div>')+'</div>':''}
     </section>
    </div>
   </div>`;
  renderLibrary();updateDirty();
 }
 window.addEventListener('beforeunload',e=>{if(S.inited&&dirty()){e.preventDefault();e.returnValue='';}});
 return api;
})();
