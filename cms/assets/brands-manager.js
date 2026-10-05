'use strict';
// Domains & Branding: Verwaltung der Marken-Registry (CMS.brands). Jede Marke = eigene Domain(s)
// + eigene Assets/Texte; Inhalte bleiben gemeinsam. Assets kommen aus dem Medien-Hub
// (brand_asset_assign), Speichern über die normale save-API (Sektion "brands").
window.BrandsManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const ASSETS=[['logo','Logo','Kopfzeile und Footer der Website. Leer = globales Website-Logo (Branding).'],['logo_dark','Logo (dunkler Hintergrund)','Optional, für Themes mit dunklem Kopf.'],['logo_light','Logo (heller Hintergrund)','Optional, für helle Themes.'],['favicon','Favicon','Browser-Tab-Symbol. PNG/SVG/ICO.'],['touch_icon','App-/Touch-Icon','Homescreen-Symbol (PWA, iOS/Android), ideal 512×512 PNG.'],['og_image','OpenGraph-Bild','Vorschaubild beim Teilen von Links (1200×630 empfohlen).'],['social_image','Social-Media-Bild','Optional abweichendes Bild für X/Twitter; leer = OpenGraph-Bild.']];
 const S={editing:null,dirty:false};
 const reg=()=>{CMS.brands=CMS.brands&&Array.isArray(CMS.brands.items)?CMS.brands:{default:'ricorewi-radio',items:[]};return CMS.brands;};
 const find=id=>reg().items.find(b=>b.id===id);
 function statusBadge(b){const on=b.enabled!==false;return '<span class="brand-status '+(on?'on':'')+'"><i class="fas fa-circle"></i> '+(on?'Aktiv':'Inaktiv')+'</span>';}
 function listHtml(){
  const r=reg();
  return '<div class="brand-list">'+r.items.map(b=>`<div class="brand-card ${S.editing===b.id?'on':''}" onclick="BrandsManager.open('${esc(b.id)}')">
    <div class="brand-logo">${(b.logo||(b.id===r.default?(CMS.branding?.portal_logo||''):''))?'<img src="'+esc(b.logo||CMS.branding?.portal_logo)+'" alt="">':'<i class="fas fa-globe"></i>'}</div>
    <div class="grow"><b>${esc(b.name||b.id)}</b>${b.id===r.default?' <span class="brand-main">Hauptmarke</span>':''}<small>${esc(b.primary_domain||'– keine Domain –')}${(b.domains||[]).length?' · '+esc(b.domains.join(', ')):''}</small>${b.claim?'<small><i>'+esc(b.claim)+'</i></small>':''}</div>
    <div>${statusBadge(b)}</div>
    <div class="brand-card-actions"><a class="btn-g" href="/?rrw_brand=${encodeURIComponent(b.id)}" target="_blank" rel="noopener" onclick="event.stopPropagation()" title="Website mit dieser Marke ansehen"><i class="fas fa-eye"></i> Vorschau</a></div>
   </div>`).join('')+'</div>';
 }
 function field(b,path,label,opts={}){
  const v=path.split('.').reduce((o,k)=>o?.[k],b)??'';
  const on=`oninput="BrandsManager.set('${esc(path)}',this.value)"`;
  if(opts.type==='textarea')return `<div><label class="news-lbl">${esc(label)}</label><textarea class="fc w-100" rows="${opts.rows||3}" placeholder="${esc(opts.ph||'')}" ${on}>${esc(v)}</textarea></div>`;
  if(opts.type==='select')return `<div><label class="news-lbl">${esc(label)}</label><select class="fc w-100" onchange="BrandsManager.set('${esc(path)}',this.value)">${opts.options.map(([val,lab])=>'<option value="'+esc(val)+'" '+(val===v?'selected':'')+'>'+esc(lab)+'</option>').join('')}</select>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
  return `<div><label class="news-lbl">${esc(label)}</label><input class="fc w-100" value="${esc(v)}" placeholder="${esc(opts.ph||'')}" ${on}>${opts.hint?'<div class="hint" style="margin-top:4px">'+esc(opts.hint)+'</div>':''}</div>`;
 }
 function assetHtml(b,[k,label,desc]){
  const v=b[k]||'';const isDefaultFallback=!v&&b.id===reg().default&&(k==='logo'?CMS.branding?.portal_logo:k==='favicon'?CMS.branding?.favicon:k==='og_image'?(CMS.seo?.og_image||CMS.branding?.portal_icon):'');
  const shown=v||isDefaultFallback||'';
  return `<div class="asset-card brand-asset">
    <div class="asset-preview">${shown?'<img src="'+esc(shown)+'?_='+Date.now()+'" alt="">':'<span class="hint">Kein Asset – Standard</span>'}</div>
    <b>${esc(label)}</b><div class="hint" style="margin:4px 0 8px">${esc(desc)}</div>
    <div class="hint" style="margin-bottom:6px;word-break:break-all">${v?esc(v):(isDefaultFallback?'Erbt: '+esc(isDefaultFallback):'Platzhalter/Standard aktiv')}</div>
    <input id="brandAsset-${esc(b.id)}-${k}" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon,.ico" class="fc w-100" style="font-size:.72rem;margin-bottom:6px">
    <div style="display:flex;gap:5px;flex-wrap:wrap"><button class="btn-g" onclick="BrandsManager.upload('${esc(b.id)}','${k}')"><i class="fas fa-upload"></i> Hochladen</button><button class="btn-g" onclick="window.MediaHub?.beginBrandingPick('brand:${esc(b.id)}:${k}')"><i class="fas fa-photo-film"></i> Medien-Hub</button>${v?'<button class="btn-d" onclick="BrandsManager.clearAsset(\''+esc(b.id)+'\',\''+k+'\')"><i class="fas fa-xmark"></i></button>':''}</div>
  </div>`;
 }
 function editorHtml(b){
  const r=reg(),isMain=b.id===r.default;
  return `<div class="brand-editor">
   <div class="th"><div><div class="tt"><i class="fas fa-globe"></i>${esc(b.name||b.id)} <code style="font-size:.7rem;color:var(--muted)">${esc(b.id)}</code></div><div class="hint">${isMain?'Hauptmarke: leere Felder übernehmen die globalen Einstellungen (Branding, SEO, Website) – so bleibt alles wie bisher.':'Leere Felder übernehmen die gemeinsamen Einstellungen der Website.'}</div></div>
    <div style="display:flex;gap:6px;flex-wrap:wrap"><label class="wm-check" style="margin:0"><input type="checkbox" ${b.enabled!==false?'checked':''} onchange="BrandsManager.set('enabled',this.checked)"> Aktiv</label><a class="btn-g" href="/?rrw_brand=${encodeURIComponent(b.id)}" target="_blank" rel="noopener"><i class="fas fa-eye"></i> Vorschau</a>${!b.builtin?'<button class="btn-d" onclick="BrandsManager.remove(\''+esc(b.id)+'\')"><i class="fas fa-trash"></i></button>':''}<button id="brandSaveBtn" class="btn-a" onclick="BrandsManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div></div>
   <div class="widget-category-title">Marke</div>
   <div class="section-grid">${field(b,'name','Anzeigename')}${field(b,'short_name','Kurzname',{ph:'z.B. SenderWelt'})}${field(b,'claim','Claim',{ph:'Deine Streams. Deine Sender. Eine Welt.'})}${field(b,'title','Seitentitel',{ph:isMain?'leer = SEO-Seitentitel':'z.B. SenderWelt'})}${field(b,'title_suffix','Titel-Zusatz (Unterseiten)',{ph:'z.B. "Sendeplan – SenderWelt"'})}${field(b,'description','Meta Description',{type:'textarea',rows:2})}</div>
   <div class="widget-category-title" style="margin-top:14px">Domains</div>
   <div class="section-grid">${field(b,'primary_domain','Hauptdomain',{ph:'senderwelt.de',hint:'Ohne https://, ohne www. Die www-Variante wird automatisch erkannt.'})}<div><label class="news-lbl">Zusätzliche Domains</label><input class="fc w-100" value="${esc((b.domains||[]).join(', '))}" placeholder="www.senderwelt.de, senderwelt.eu" oninput="BrandsManager.setDomains(this.value)"><div class="hint" style="margin-top:4px">Kommagetrennt. Jede Domain muss serverseitig (KeyHelp) auf dieses Projekt zeigen, siehe Doku.</div></div></div>
   <div class="widget-category-title" style="margin-top:14px">Assets (Medien-Hub)</div>
   <div class="asset-grid">${ASSETS.map(a=>assetHtml(b,a)).join('')}</div>
   <div class="widget-category-title" style="margin-top:14px">SEO & Canonical</div>
   <div class="section-grid">${field(b,'canonical_mode','Canonical-Verhalten',{type:'select',options:[['own','Eigene Domain (Seiten gelten als eigenständig)'],['main','Hauptquelle = Canonical-Basis der Website (sicher gegen Duplicate Content)'],['custom','Eigene Canonical-Basis']],hint:'Da beide Domains dieselben Inhalte zeigen, ist "Hauptquelle" für zusätzliche Marken die sichere Voreinstellung.'})}${field(b,'canonical_base','Eigene Canonical-Basis',{ph:'https://senderwelt.de',hint:'Nur bei "Eigene Canonical-Basis".'})}${field(b,'manifest_name','App-Name (PWA)',{ph:'SenderWelt'})}${field(b,'manifest_short_name','App-Kurzname (PWA)',{ph:'SenderWelt'})}${field(b,'colors.theme','Theme-Farbe (Browserleiste)',{ph:'#070a1c'})}${field(b,'colors.accent','Markenfarbe (optional)',{ph:'#b57cff'})}</div>
   <div class="widget-category-title" style="margin-top:14px">Texte überschreiben (nur diese Marke)</div>
   <div class="hint" style="margin:-4px 0 8px">Eigene Texte für Startseite, News und Footer dieser Marke. Beispiel: Startseitentitel „Willkommen in der SenderWelt“.</div>
   <div class="section-grid">${field(b,'overrides.portal.site_name','Website-Name',{ph:isMain?String(CMS.portal?.site_name||''):String(b.name||'')})}${field(b,'overrides.portal.hero_eyebrow','Startseite: Kicker',{ph:String(CMS.portal?.hero_eyebrow||'Hallo!')})}${field(b,'overrides.portal.hero_title','Startseite: Titel',{ph:isMain?String(CMS.portal?.hero_title||''):'Willkommen bei '+String(b.name||'')})}${field(b,'overrides.portal.news_title','News: Titel',{ph:isMain?String(CMS.portal?.news_title||''):'Aktuelles von '+String(b.name||'')})}${field(b,'overrides.portal.hero_text','Startseite: Text',{type:'textarea',ph:isMain?String(CMS.portal?.hero_text||''):'Schön, dass du da bist! Hier findest du alle Streams von '+String(b.name||'')+' an einem Ort …'})}${field(b,'overrides.portal.news_intro','News: Einleitung',{type:'textarea',ph:String(CMS.portal?.news_intro||'')})}${field(b,'overrides.portal.footer_text','Footer-Text',{ph:isMain?String(CMS.portal?.footer_text||''):'© '+new Date().getFullYear()+' '+String(b.name||'')+' • Alle Rechte vorbehalten'})}</div>
   ${field(b,'overrides.portal.legal_notice','Footer: Rechtshinweis (laut.fm / Verzeichnis)',{type:'textarea',rows:6,ph:'leer = Standardtext; Link: [Text](https://…), Zeilenumbruch = neue Zeile',hint:'Ersetzt den laut.fm-Hinweis im Footer dieser Marke. Bei Marken mit Radioverzeichnis steht standardmäßig der erweiterte Hinweis zu Fremd-Sendern (laut.fm, radio-browser.info).'})}
   <div class="widget-category-title" style="margin-top:14px">Radioverzeichnis</div>
   <label class="wm-check" data-pack="ricorewi-radio" style="margin:0 0 6px"><input type="checkbox" ${b.directory?'checked':''} onchange="BrandsManager.set('directory',this.checked)"> Radioverzeichnis aktivieren (Suchfeld „Lieblingssender suchen“ im Header + Verzeichnis-Seite)</label>
   <div class="hint" style="margin-bottom:8px">Sucht in allen laut.fm-Sendern (öffentliche API) und in radio-browser.info. Eigene Sender laufen im Portal; Fremd-Sender werden nur gelistet und zum Betreiber bzw. zu laut.fm verlinkt (kein Stream-Einbetten, keine Logos fremder Sender außer laut.fm-Stationsbildern).</div>
   <div class="hint" style="margin-top:6px">Leer gelassene Felder zeigen den Platzhalter-Text: bei der Hauptmarke die gemeinsamen Portal-Texte, bei weiteren Marken automatisch markeneigene Standardtexte.</div>
   <div class="widget-category-title" style="margin-top:14px">Rechtliches</div>
   <div class="section-grid">${field(b,'legal.imprint_mode','Impressum',{type:'select',options:[['shared','Gemeinsamen Standard verwenden'],['custom','Eigener Inhalt / eigene URL']]})}${field(b,'legal.privacy_mode','Datenschutz',{type:'select',options:[['shared','Gemeinsamen Standard verwenden'],['custom','Eigener Inhalt / eigene URL']]})}
   ${b.legal?.imprint_mode==='custom'?field(b,'legal.imprint_url','Impressum: eigene URL (leer = Inhalt unten)',{ph:'https://…'})+field(b,'legal.imprint_content','Impressum: eigener Inhalt (HTML)',{type:'textarea',rows:5}):''}
   ${b.legal?.privacy_mode==='custom'?field(b,'legal.privacy_url','Datenschutz: eigene URL (leer = Inhalt unten)',{ph:'https://…'})+field(b,'legal.privacy_content','Datenschutz: eigener Inhalt (HTML)',{type:'textarea',rows:5}):''}
   </div>
   <div style="display:flex;justify-content:flex-end;margin-top:14px"><button class="btn-a" onclick="BrandsManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
  </div>`;
 }
 function render(){
  const host=document.getElementById('brandsManager');if(!host)return;
  const r=reg();if(S.editing&&!find(S.editing))S.editing=null;
  host.innerHTML=listHtml()+(S.editing?editorHtml(find(S.editing)):'<div class="hint" style="padding:10px 4px">Marke anklicken, um Domains, Logo, Favicon, Texte und SEO zu bearbeiten.</div>');
  const btn=document.getElementById('brandSaveBtn');if(btn)btn.innerHTML=S.dirty?'<i class="fas fa-floppy-disk"></i> Speichern':'<i class="fas fa-check"></i> Gespeichert';
 }
 const api={
  render,
  open(id){S.editing=id;render();},
  set(path,val){const b=find(S.editing);if(!b)return;const keys=path.split('.');let o=b;keys.slice(0,-1).forEach(k=>{o[k]=o[k]&&typeof o[k]==='object'?o[k]:{};o=o[k];});o[keys.at(-1)]=val;S.dirty=true;if(path==='legal.imprint_mode'||path==='legal.privacy_mode'||path==='enabled')render();else{const btn=document.getElementById('brandSaveBtn');if(btn)btn.innerHTML='<i class="fas fa-floppy-disk"></i> Speichern';}},
  setDomains(v){const b=find(S.editing);if(!b)return;b.domains=String(v).split(/[,\s]+/).map(x=>x.trim().toLowerCase()).filter(Boolean);S.dirty=true;},
  add(){const r=reg();let id=prompt('Technische Kennung der neuen Marke (nur Kleinbuchstaben, Ziffern, Bindestrich):','');if(!id)return;id=id.toLowerCase().replace(/[^a-z0-9-]+/g,'-').replace(/^-|-$/g,'');if(!id||find(id))return cmsToast('Kennung ungültig oder schon vergeben',true);
   r.items.push({id,name:id,short_name:'',claim:'',enabled:false,builtin:false,primary_domain:'',domains:[],logo:'',logo_dark:'',logo_light:'',favicon:'',touch_icon:'',og_image:'',social_image:'',title:'',title_suffix:'',description:'',manifest_name:'',manifest_short_name:'',colors:{theme:'',accent:''},canonical_mode:'main',canonical_base:'',legal:{imprint_mode:'shared',imprint_url:'',imprint_content:'',privacy_mode:'shared',privacy_url:'',privacy_content:''},overrides:{portal:{}}});
   S.editing=id;S.dirty=true;render();},
  remove(id){const b=find(id);if(!b||b.builtin)return;if(!confirm('Marke „'+b.name+'“ löschen? Domains dieser Marke zeigen danach die Hauptmarke.'))return;reg().items=reg().items.filter(x=>x.id!==id);S.editing=null;S.dirty=true;api.save();},
  async save(){
   const r=reg();const b=find(S.editing);
   if(b&&b.enabled!==false&&!b.primary_domain&&b.id!==r.default)cmsToast('Hinweis: Marke ohne Hauptdomain wird nur per Vorschau erreichbar sein',true);
   try{const d=await cmsApi('save',{section:'brands',value:r});CMS.brands=d.value;S.dirty=false;render();const live=await verifyPublicSection('brands',d.value);cmsToast(live?'Marken gespeichert & live ✓':'Gespeichert, Live-Stand bitte prüfen',!live);}
   catch(e){cmsToast(e.message,true);}
  },
  async upload(brandId,kind){
   const input=document.getElementById('brandAsset-'+brandId+'-'+kind),file=input?.files?.[0];if(!file)return cmsToast('Bitte zuerst eine Datei wählen',true);
   const fd=new FormData();fd.append('file',file);fd.append('sizes','64,128,192,256,512,1024,1600');fd.append('quality','90');
   try{
    setPublishState(true,'Upload läuft…');
    const up=await fetch(CRON+'?action=media_library_upload&_='+Date.now(),{method:'POST',headers:cmsHeaders(false),body:fd});
    const ud=await up.json();if(!up.ok||ud.status!=='ok')throw new Error(ud.message||'Upload fehlgeschlagen');
    const path=ud.item?.original?.path?String(ud.item.original.path).replace(/\/original\.[^.]+$/,''):('library/'+(ud.item?.id||''));
    await api.assign(brandId,kind,path);
    window.MediaHub?.load(true);
   }catch(e){setPublishState(false,'Upload fehlgeschlagen');cmsToast(e.message,true);}
  },
  async assign(brandId,kind,path,size='auto'){
   const d=await cmsApi('brand_asset_assign',{brand_id:brandId,kind,path,size});CMS.brands=d.brands;S.editing=brandId;render();
   setPublishState(true,'Live veröffentlicht');cmsToast('Asset gespeichert & live ✓');
  },
  async clearAsset(brandId,kind){try{const d=await cmsApi('brand_asset_assign',{brand_id:brandId,kind,url:''});CMS.brands=d.brands;render();cmsToast('Asset entfernt – Standard aktiv');}catch(e){cmsToast(e.message,true);}},
 };
 return api;
})();
