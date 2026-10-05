(function(){
'use strict';
let mounted=false, articles=[], canEdit=false, currentId=0, showTrash=false;
let selectedIds=new Set();
let quickEditId=0;
let searchQuery='', statusFilter='all', sortKey='date_desc';
let currentPage=1, currentPageItems=[];
const PAGE_SIZE=20;
let commentSettings={enabled:false,require_approval:true}, commentsAdmin=[];
let categories=['News','Radio','Podcast','Shows','Musik','Community','Partner','Intern'];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const CMS_API='api.php';
const cmsNewsToken=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
const cmsNewsHeaders=(json=true)=>{const h={};const t=cmsNewsToken();if(t)h['X-AnMaCha-Token']=t;if(json)h['Content-Type']='application/json';return h};
const fmt=d=>{if(!d)return'–';try{return new Date(String(d).replace(' ','T')).toLocaleString('de-DE',{dateStyle:'medium',timeStyle:'short'});}catch(e){return d;}};

function api(action, body){
  const opts=body?{method:'POST',headers:cmsNewsHeaders(true),body:JSON.stringify(body)}:{headers:cmsNewsHeaders(false)};
  return fetch(CMS_API+'?action='+encodeURIComponent(action)+'&_='+Date.now(),opts).then(async r=>{
    const txt=await r.text(); let d={}; try{d=JSON.parse(txt);}catch(e){throw new Error(txt.slice(0,240)||'Ungültige Serverantwort');}
    if(r.status===401)window.cmsSessionExpired?.();
    if(!r.ok||d.status==='error'||d.error)throw new Error(d.message||d.error||'Fehler');
    return d;
  });
}
function val(id){return document.getElementById(id)?.value||'';}
function checked(id){return !!document.getElementById(id)?.checked;}
function toast(m,kind){window.cmsToast?.(m,kind==='err');}
function say(m,t){try{toast(m,t==='error'?'err':t==='success'?'ok':'inf');}catch(e){alert(m);}}

function toolbar(cmd,arg){
  const ed=document.getElementById('newsBody'); if(!ed)return; ed.focus();
  if(cmd==='createLink'){
    const u=prompt('Link-URL (https://…)'); if(!u)return;
    document.execCommand('createLink',false,u);
  }else document.execCommand(cmd,false,arg||null);
}
window.newsFmt=toolbar;

function renderCategoryOptions(){
  const sel=document.getElementById('newsCategory'); if(!sel)return;
  const current=sel.value;
  sel.innerHTML=categories.map(c=>'<option>'+esc(c)+'</option>').join('');
  if(categories.includes(current))sel.value=current;
}
function categoryManagerHtml(){
  return `<div class="card"><div class="th"><div class="tt"><i class="fas fa-tags" style="color:var(--accent)"></i>Kategorien</div></div>
    <div id="newsCategoryChips" style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px;"></div>
    <div style="display:flex;gap:7px;">
      <input id="newsNewCategory" class="fc" style="flex:1" maxlength="40" placeholder="Neue Kategorie…" onkeydown="if(event.key==='Enter'){event.preventDefault();NewsMagazine.addCategory();}">
      <button class="btn-g" onclick="NewsMagazine.addCategory()"><i class="fas fa-plus"></i> Hinzufügen</button>
    </div>
  </div>`;
}
function renderCategoryManager(){
  const host=document.getElementById('newsCategoryChips'); if(!host)return;
  const countOf=c=>articles.filter(a=>!a.deleted_at&&(a.category||'News')===c).length;
  host.innerHTML=categories.map(c=>`<span style="display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);background:var(--surface3);border-radius:999px;padding:5px 6px 5px 12px;font-size:.76rem;color:#fff;">${esc(c)}<span style="color:var(--muted);font-weight:800;">(${countOf(c)})</span><button onclick="NewsMagazine.renameCategory('${esc(c).replace(/'/g,"\\'")}')" style="background:none;border:0;color:var(--muted);cursor:pointer;padding:2px 4px;" title="Umbenennen"><i class="fas fa-pen"></i></button><button onclick="NewsMagazine.removeCategory('${esc(c).replace(/'/g,"\\'")}')" style="background:none;border:0;color:var(--muted);cursor:pointer;padding:2px 4px;" title="Entfernen"><i class="fas fa-xmark"></i></button></span>`).join('')||'<span style="font-size:.76rem;color:var(--muted)">Noch keine Kategorien.</span>';
}
async function renameCategory(oldName){
  const next=prompt('Neuer Name für die Kategorie „'+oldName+'“:',oldName);
  if(next===null)return;
  const trimmed=next.trim();
  if(!trimmed||trimmed===oldName)return;
  if(categories.some(c=>c.toLowerCase()===trimmed.toLowerCase()&&c!==oldName))return say('Kategorie existiert bereits','error');
  try{
    const d=await api('news_category_rename',{old:oldName,new:trimmed});
    categories=d.categories||categories;
    articles.forEach(a=>{if(a.category===oldName)a.category=trimmed;});
    renderCategoryOptions();renderCategoryManager();renderListBody();
    say('Kategorie umbenannt ('+(d.affected||0)+' Beitrag/Beiträge aktualisiert)','success');
  }catch(e){say(e.message,'error');}
}
async function saveCategories(next){
  try{
    await api('save',{section:'news_categories',value:next});
    categories=next;
    renderCategoryOptions();
    renderCategoryManager();
  }catch(e){say(e.message,'error');}
}
function addCategory(){
  const input=document.getElementById('newsNewCategory'); if(!input)return;
  const v=input.value.trim(); if(!v)return;
  if(categories.some(c=>c.toLowerCase()===v.toLowerCase()))return say('Kategorie existiert bereits','error');
  input.value='';
  saveCategories([...categories,v]);
}
function removeCategory(name){
  if(!confirm('Kategorie "'+name+'" entfernen? Bereits zugewiesene Beiträge behalten sie, sie steht nur nicht mehr zur Auswahl.'))return;
  saveCategories(categories.filter(c=>c!==name));
}
function renderTagSuggestions(){
  const host=document.getElementById('newsTagsList'); if(!host)return;
  const set=new Set();
  articles.forEach(a=>{const t=String(a.tags||'').trim(); if(t)set.add(t);});
  host.innerHTML=[...set].sort((a,b)=>a.localeCompare(b,'de')).map(t=>'<option value="'+esc(t)+'">').join('');
}
function setCategories(list){
  categories=Array.isArray(list)&&list.length?list:categories;
  renderCategoryOptions();
  renderCategoryManager();
}

function isScheduled(a){
  if(a.status!=='published'||!a.published_at)return false;
  try{return new Date(String(a.published_at).replace(' ','T'))>new Date();}catch(e){return false;}
}
function statusBadge(a){
  const scheduled=isScheduled(a);
  const pub=a.status==='published'&&!scheduled;
  const color=pub?'var(--good)':scheduled?'#7cc9ff':'var(--accent)';
  const border=pub?'rgba(0,230,118,.28)':scheduled?'rgba(124,201,255,.3)':'rgba(255,206,0,.25)';
  const bg=pub?'rgba(0,230,118,.08)':scheduled?'rgba(124,201,255,.08)':'rgba(255,206,0,.07)';
  const label=pub?'● Veröffentlicht':scheduled?'🕒 Geplant · '+fmt(a.published_at):'● Entwurf';
  return '<span style="display:inline-flex;align-items:center;gap:5px;border:1px solid '+border+';background:'+bg+';color:'+color+';border-radius:999px;padding:3px 8px;font-size:.66rem;font-weight:800;">'+label+'</span>';
}
function articleStatusKey(a){
  if(a.status!=='published')return 'draft';
  return isScheduled(a)?'scheduled':'published';
}
function filteredArticles(){
  let list=articles;
  if(!showTrash&&statusFilter!=='all')list=list.filter(a=>articleStatusKey(a)===statusFilter);
  const q=searchQuery.trim().toLowerCase();
  if(q)list=list.filter(a=>String(a.title||'').toLowerCase().includes(q)||String(a.excerpt||'').toLowerCase().includes(q));
  list=list.slice();
  const dateOf=a=>new Date(String(a.published_at||a.created_at||'').replace(' ','T')).getTime()||0;
  if(sortKey==='date_asc')list.sort((a,b)=>dateOf(a)-dateOf(b));
  else if(sortKey==='title_asc')list.sort((a,b)=>String(a.title||'').localeCompare(String(b.title||''),'de'));
  else if(sortKey==='title_desc')list.sort((a,b)=>String(b.title||'').localeCompare(String(a.title||''),'de'));
  else list.sort((a,b)=>dateOf(b)-dateOf(a));
  return list;
}
function statusCounts(){
  const c={all:articles.length,published:0,scheduled:0,draft:0};
  articles.forEach(a=>{c[articleStatusKey(a)]++;});
  return c;
}
function filterBarHtml(){
  const c=statusCounts();
  const tab=(key,label)=>`<button class="btn-g" style="${statusFilter===key?'background:var(--accent);color:#120a27;border-color:var(--accent);':''}" onclick="NewsMagazine.setStatusFilter('${key}')">${label} (${c[key]})</button>`;
  return `<div id="newsFilterBar" style="display:flex;align-items:center;gap:8px;padding:10px 15px;border-bottom:1px solid var(--border);flex-wrap:wrap;justify-content:space-between;">
    <div style="display:flex;gap:6px;flex-wrap:wrap;">${showTrash?'<span style="font-size:.74rem;color:var(--muted);"><i class="fas fa-circle-info"></i> Beiträge werden 30 Tage nach dem Löschen automatisch endgültig entfernt.</span>':tab('all','Alle')+tab('published','Veröffentlicht')+tab('scheduled','Geplant')+tab('draft','Entwurf')}</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <select id="newsSort" class="fc" style="width:auto;padding:6px 10px;" onchange="NewsMagazine.setSort(this.value)">
        <option value="date_desc"${sortKey==='date_desc'?' selected':''}>Neueste zuerst</option>
        <option value="date_asc"${sortKey==='date_asc'?' selected':''}>Älteste zuerst</option>
        <option value="title_asc"${sortKey==='title_asc'?' selected':''}>Titel A–Z</option>
        <option value="title_desc"${sortKey==='title_desc'?' selected':''}>Titel Z–A</option>
      </select>
      <input id="newsSearch" class="fc" style="width:220px;" placeholder="Beiträge durchsuchen…" value="${esc(searchQuery)}" oninput="NewsMagazine.setSearch(this.value)">
    </div>
  </div>`;
}
function renderList(){
  const bar=document.getElementById('newsFilterBar'); if(bar)bar.outerHTML=filterBarHtml();
  renderListBody();
}
function paginationHtml(page,totalPages,total){
  if(totalPages<=1)return'';
  const btn=(p,label,disabled)=>`<button class="btn-g" ${disabled?'disabled style="opacity:.4;cursor:default"':''} onclick="NewsMagazine.goToPage(${p})">${label}</button>`;
  return `<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 15px;border-top:1px solid var(--border);flex-wrap:wrap;">
    <span style="font-size:.72rem;color:var(--muted);">${total} Beitrag/Beiträge · Seite ${page} von ${totalPages}</span>
    <div style="display:flex;gap:6px;">${btn(page-1,'← Zurück',page<=1)}${btn(page+1,'Weiter →',page>=totalPages)}</div>
  </div>`;
}
function renderListBody(){
  const host=document.getElementById('newsList'); if(!host)return;
  selectedIds=new Set([...selectedIds].filter(id=>articles.some(a=>a.id===id)));
  const list=filteredArticles();
  const pager=document.getElementById('newsPagination');
  if(!articles.length){host.innerHTML='<div class="empty"><i class="fas '+(showTrash?'fa-trash':'fa-newspaper')+'"></i>'+(showTrash?'Papierkorb ist leer.':'Noch keine Beiträge. Erstelle den ersten Artikel.')+'</div>';if(pager)pager.innerHTML='';currentPageItems=[];updateBulkBar();return;}
  if(!list.length){host.innerHTML='<div class="empty"><i class="fas fa-magnifying-glass"></i>Keine Beiträge gefunden.</div>';if(pager)pager.innerHTML='';currentPageItems=[];updateBulkBar();return;}
  const totalPages=Math.max(1,Math.ceil(list.length/PAGE_SIZE));
  currentPage=Math.min(Math.max(1,currentPage),totalPages);
  currentPageItems=list.slice((currentPage-1)*PAGE_SIZE,currentPage*PAGE_SIZE);
  host.innerHTML=currentPageItems.map(a=>a.id===quickEditId?quickEditRowHtml(a):rowHtml(a)).join('');
  if(pager)pager.innerHTML=paginationHtml(currentPage,totalPages,list.length);
  updateBulkBar();
}
function goToPage(p){ currentPage=p; renderListBody(); }
function rowHtml(a){
  return `<div class="news-admin-row" style="display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;gap:12px;padding:12px 13px;border-bottom:1px solid var(--border);align-items:center;">
    <input type="checkbox" class="news-row-check" data-id="${a.id}" ${selectedIds.has(a.id)?'checked':''} onchange="NewsMagazine.toggleSelect(${a.id},this.checked)" style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;">
    ${a.image_url?'<img src="'+esc(a.image_url)+'" alt="" style="width:52px;height:52px;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:var(--surface2);" onerror="this.outerHTML=\'<div style=&quot;width:52px;height:52px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);&quot;><i class=&quot;fas fa-image&quot;></i></div>\'">':'<div style="width:52px;height:52px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);"><i class="fas fa-image"></i></div>'}
    <div style="min-width:0;">
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:4px;">${a.featured==1?'<span style="color:var(--accent)"><i class="fas fa-star"></i></span>':''}<b style="color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px;">${esc(a.title)}</b>${statusBadge(a)}</div>
      ${!showTrash&&a.slug?'<div style="font-size:.66rem;color:var(--muted);font-family:monospace;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">/#news/'+esc(a.slug)+'</div>':''}
      <div style="font-size:.7rem;color:var(--muted);">${esc(a.category||'News')} · ${showTrash?'Gelöscht: '+fmt(a.deleted_at):fmt(a.published_at||a.created_at)} · ${esc(a.author||'')}${a.views?' · <i class="fas fa-eye"></i> '+a.views:''}</div>
      ${a.excerpt?'<div style="font-size:.76rem;color:var(--dim);margin-top:5px;line-height:1.45;">'+esc(a.excerpt)+'</div>':''}
    </div>
    <div class="news-row-actions" style="display:flex;gap:6px;">
      ${showTrash?
        (canEdit&&a.can_edit!==false?'<button class="btn-g" onclick="NewsMagazine.restore('+a.id+')" title="Wiederherstellen"><i class="fas fa-rotate-left"></i></button><button class="btn-d" onclick="NewsMagazine.delPermanent('+a.id+')" title="Endgültig löschen"><i class="fas fa-trash-can"></i></button>':'<span style="font-size:.68rem;color:var(--muted)" title="Nur der Autor oder ein Admin darf diesen Beitrag verwalten"><i class="fas fa-lock"></i></span>')
        :
        (a.can_edit!==false?
          '<button class="btn-g" onclick="NewsMagazine.edit('+a.id+')" title="Bearbeiten"><i class="fas fa-pen"></i></button>'
          +'<button class="btn-g" onclick="NewsMagazine.startQuickEdit('+a.id+')" title="Schnellbearbeitung"><i class="fas fa-bolt"></i></button>'
          +(a.status==='published'?'<a class="btn-g" href="/#news/'+encodeURIComponent(a.slug||'')+'" target="_blank" rel="noopener" title="Ansehen" style="text-decoration:none"><i class="fas fa-eye"></i></a>':'')
          +'<button class="btn-g" onclick="NewsMagazine.duplicate('+a.id+')" title="Duplizieren"><i class="fas fa-clone"></i></button>'
          +(canEdit?'<button class="btn-d" onclick="NewsMagazine.del('+a.id+')" title="In den Papierkorb"><i class="fas fa-trash"></i></button>':'')
          :'<span style="font-size:.68rem;color:var(--muted)" title="Nur der Autor oder ein Admin darf diesen Beitrag verwalten"><i class="fas fa-lock"></i></span>')
      }
    </div>
  </div>`;
}
function quickEditRowHtml(a){
  return `<div class="news-admin-row" style="padding:14px 13px;border-bottom:1px solid var(--border);background:var(--surface3);">
    <div class="row g-2" style="align-items:end;">
      <div class="col-md-5"><label class="news-lbl">Titel</label><input id="qeTitle" class="fc w-100" value="${esc(a.title)}"></div>
      <div class="col-md-3"><label class="news-lbl">Kategorie</label><select id="qeCategory" class="fc w-100">${categories.map(c=>'<option value="'+esc(c)+'"'+(c===a.category?' selected':'')+'>'+esc(c)+'</option>').join('')}</select></div>
      <div class="col-md-2"><label class="news-lbl">Status</label><select id="qeStatus" class="fc w-100"><option value="draft"${a.status!=='published'?' selected':''}>Entwurf</option><option value="published"${a.status==='published'?' selected':''}>Veröffentlicht</option></select></div>
      <div class="col-md-2" style="padding-bottom:9px;"><label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="qeFeatured" ${a.featured==1?'checked':''} style="accent-color:var(--accent)"><span style="font-size:.78rem;color:#fff">Hervorheben</span></label></div>
      <div class="col-12" style="display:flex;gap:8px;margin-top:4px;">
        <button class="btn-a" onclick="NewsMagazine.saveQuickEdit(${a.id})"><i class="fas fa-check"></i> Aktualisieren</button>
        <button class="btn-g" onclick="NewsMagazine.cancelQuickEdit()"><i class="fas fa-times"></i> Abbrechen</button>
      </div>
    </div>
  </div>`;
}
function startQuickEdit(id){ quickEditId=id; renderListBody(); }
function cancelQuickEdit(){ quickEditId=0; renderListBody(); }
async function saveQuickEdit(id){
  const title=document.getElementById('qeTitle')?.value.trim();
  if(!title)return say('Titel darf nicht leer sein','error');
  try{
    await api('news_quick_edit',{id,title,category:document.getElementById('qeCategory')?.value,status:document.getElementById('qeStatus')?.value,featured:document.getElementById('qeFeatured')?.checked});
    quickEditId=0;
    say('Beitrag aktualisiert','success');
    load();
  }catch(e){say(e.message,'error');}
}
function bulkBarHtml(){
  const opts=showTrash
    ?'<option value="restore">Wiederherstellen</option><option value="delete_permanent">Endgültig löschen</option>'
    :'<option value="publish">Veröffentlichen</option><option value="draft">Als Entwurf markieren</option><option value="feature">Hervorheben</option><option value="unfeature">Hervorhebung entfernen</option><option value="set_category">Kategorie ändern…</option><option value="trash">In den Papierkorb verschieben</option>';
  return `<div id="newsBulkBar" style="display:none;align-items:center;gap:10px;padding:10px 15px;border-bottom:1px solid var(--border);background:var(--surface3);flex-wrap:wrap;">
    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;"><input type="checkbox" id="newsSelectAll" onchange="NewsMagazine.toggleSelectAll(this.checked)" style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;"><span style="font-size:.72rem;color:var(--muted);">Alle</span></label>
    <select id="newsBulkOp" class="fc" style="width:auto;padding:6px 10px;" onchange="NewsMagazine.onBulkOpChange(this.value)"><option value="">Massenaktion wählen…</option>${opts}</select>
    <select id="newsBulkCategoryValue" class="fc" style="width:auto;padding:6px 10px;display:none;">${categories.map(c=>'<option value="'+esc(c)+'">'+esc(c)+'</option>').join('')}</select>
    <button class="btn-g" onclick="NewsMagazine.applyBulk()">Anwenden</button>
    <span id="newsBulkCount" style="font-size:.72rem;color:var(--muted);"></span>
  </div>`;
}
function onBulkOpChange(op){
  const sel=document.getElementById('newsBulkCategoryValue'); if(sel)sel.style.display=op==='set_category'?'':'none';
}
function updateBulkBar(){
  const bar=document.getElementById('newsBulkBar'); if(!bar)return;
  const count=selectedIds.size;
  const visible=currentPageItems.length;
  bar.style.display=count>0?'flex':'none';
  const c=document.getElementById('newsBulkCount'); if(c)c.textContent=count>0?count+' ausgewählt':'';
  const all=document.getElementById('newsSelectAll'); if(all)all.checked=visible>0&&currentPageItems.every(a=>selectedIds.has(a.id));
}
function setSearch(v){ searchQuery=v; currentPage=1; renderListBody(); }
function setStatusFilter(key){ statusFilter=key; currentPage=1; renderList(); }
function setSort(key){ sortKey=key; currentPage=1; renderListBody(); }
function toggleSelect(id,checked){
  if(checked)selectedIds.add(id); else selectedIds.delete(id);
  updateBulkBar();
}
function toggleSelectAll(checked){
  if(checked)currentPageItems.forEach(a=>selectedIds.add(a.id));
  else currentPageItems.forEach(a=>selectedIds.delete(a.id));
  renderListBody();
}
async function applyBulk(){
  const op=document.getElementById('newsBulkOp')?.value;
  if(!op)return say('Bitte eine Massenaktion auswählen','error');
  if(!selectedIds.size)return;
  if(op==='delete_permanent'&&!confirm(selectedIds.size+' Beitrag/Beiträge endgültig löschen? Das kann nicht rückgängig gemacht werden.'))return;
  const value=op==='set_category'?(document.getElementById('newsBulkCategoryValue')?.value||''):undefined;
  try{
    const d=await api('news_bulk_action',{ids:[...selectedIds],op,value});
    say((d.affected||0)+' Beitrag/Beiträge aktualisiert','success');
    selectedIds=new Set();
    load();
  }catch(e){say(e.message,'error');}
}
async function load(){
  const h=document.getElementById('newsList'); if(h)h.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Lade Beiträge…</div>';
  currentPage=1;
  try{
    const d=await api(showTrash?'news_trash_list':'news_list'); articles=d.articles||[]; canEdit=!!d.can_edit;
    const b=document.getElementById('newsNewBtn'); if(b)b.style.display=(canEdit&&!showTrash)?'':'none';
    renderList();
    updateTrashToggle();
    renderTagSuggestions();
    renderCategoryManager();
  }catch(e){if(h)h.innerHTML='<div class="empty" style="color:#ff8080"><i class="fas fa-triangle-exclamation"></i>'+esc(e.message)+'</div>';}
}
function updateTrashToggle(){
  const t=document.getElementById('newsTrashToggle'); if(!t)return;
  t.innerHTML=showTrash?'<i class="fas fa-arrow-left"></i> Zurück zu Beiträgen':'<i class="fas fa-trash"></i> Papierkorb';
}
function toggleTrash(){ showTrash=!showTrash; selectedIds=new Set(); load(); }
function editorHtml(){
  return `<div class="card" id="newsEditorCard" style="display:none;border-color:rgba(255,206,0,.2)">
    <div class="th"><div class="tt"><i class="fas fa-pen-nib" style="color:var(--accent)"></i><span id="newsEditorTitle">Beitrag erstellen</span></div><div class="d-flex gap-2"><button id="newsRevisionsBtn" class="btn-g" style="display:none" onclick="NewsMagazine.toggleRevisions()"><i class="fas fa-clock-rotate-left"></i> Versionen</button><button class="btn-g" onclick="NewsMagazine.closeEditor()"><i class="fas fa-times"></i> Schließen</button></div></div>
    <div id="newsRevisionsPanel" style="display:none;padding:10px 0;border-bottom:1px solid var(--border);margin-bottom:14px;"></div>
    <div id="newsAutosaveBanner" style="display:none;align-items:center;justify-content:space-between;gap:10px;padding:9px 13px;border:1px solid rgba(255,206,0,.3);background:rgba(255,206,0,.07);border-radius:10px;margin-bottom:14px;font-size:.78rem;color:var(--accent);">
      <span id="newsAutosaveText"></span>
      <div class="d-flex gap-2"><button class="btn-g" onclick="NewsMagazine.restoreAutosave()">Wiederherstellen</button><button class="btn-g" onclick="NewsMagazine.dismissAutosave()">Verwerfen</button></div>
    </div>
    <div id="newsAutosaveStatus" style="font-size:.68rem;color:var(--muted);margin-bottom:8px;"></div>
    <div class="editor-shell">
      <div class="editor-main">
        <div class="row g-3">
          <div class="col-12"><label class="news-lbl">Titel</label><input id="newsTitle" class="fc w-100" maxlength="255" placeholder="Überschrift des Beitrags"></div>
          <div class="col-12"><label class="news-lbl">Teaser</label><textarea id="newsExcerpt" class="fc w-100" rows="2" maxlength="600" placeholder="Kurze Zusammenfassung für die Übersicht"></textarea></div>
          <div class="col-12">
            <label class="news-lbl">Artikel</label>
            <div style="display:flex;gap:5px;flex-wrap:wrap;padding:7px;background:var(--surface3);border:1px solid var(--border);border-bottom:0;border-radius:9px 9px 0 0;">
              <button class="sp-tool-btn" onclick="newsFmt('bold')"><b>B</b></button><button class="sp-tool-btn" onclick="newsFmt('italic')"><i>I</i></button><button class="sp-tool-btn" onclick="newsFmt('formatBlock','h2')">H2</button><button class="sp-tool-btn" onclick="newsFmt('formatBlock','h3')">H3</button><button class="sp-tool-btn" onclick="newsFmt('insertUnorderedList')"><i class="fas fa-list-ul"></i></button><button class="sp-tool-btn" onclick="newsFmt('insertOrderedList')"><i class="fas fa-list-ol"></i></button><button class="sp-tool-btn" onclick="NewsMagazine.insertImage()" title="Bild einfügen (Mediathek oder freie Bilder)"><i class="fas fa-image"></i></button><button class="sp-tool-btn" onclick="newsFmt('createLink')"><i class="fas fa-link"></i></button>
            </div>
            <div id="newsBody" contenteditable="true" style="min-height:260px;background:var(--surface2);color:var(--text);border:1px solid var(--border);border-radius:0 0 9px 9px;padding:14px;line-height:1.65;outline:none;"></div>
          </div>
          <div class="col-md-6"><label class="news-lbl">Optionaler externer Link</label><input id="newsExternal" class="fc w-100" placeholder="https://…"></div>
          <div class="col-md-6"><label class="news-lbl">Video URL</label><input id="newsVideo" class="fc w-100" placeholder="YouTube- oder TikTok-URL" oninput="NewsMagazine.videoPreview()"></div>
          <div class="col-12"><label class="news-lbl">Tags / Hashtags</label><input id="newsTags" class="fc w-100" placeholder="${(window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'#Radio #Podcast #AnMaCha':'#Radio #Podcast #News'}" list="newsTagsList"><datalist id="newsTagsList"></datalist></div>
          <div class="col-12">
            <label class="news-lbl">Embed-/HTML-Code</label>
            <textarea id="newsEmbedHtml" class="fc w-100" rows="4" placeholder="z. B. YouTube-, TikTok-, Instagram-, Spotify-, Vimeo- oder SoundCloud-Embed-Code"></textarea>
            <div style="font-size:.68rem;color:var(--muted);margin-top:5px;">Skripte werden entfernt; sichere Medien-Embeds bleiben erhalten.</div>
          </div>
          <div class="col-12"><div id="newsVideoPreview"></div></div>
          <div class="col-12" style="border-top:1px solid var(--border);padding-top:14px;margin-top:6px;">
            <label class="news-lbl"><i class="fas fa-magnifying-glass-chart" style="margin-right:5px"></i>SEO (optional, überschreibt Titel/Teaser nur in Suchergebnissen & Linkvorschauen)</label>
          </div>
          <div class="col-md-6"><label class="news-lbl">SEO-Titel</label><input id="newsSeoTitle" class="fc w-100" maxlength="70" placeholder="Standard: Beitragstitel"></div>
          <div class="col-md-6"><label class="news-lbl">SEO-Beschreibung</label><input id="newsSeoDescription" class="fc w-100" maxlength="200" placeholder="Standard: Teaser"></div>
        </div>
      </div>
      <aside class="editor-sidebar">
        <div class="card editor-box">
          <div class="tt" style="font-size:.85rem"><i class="fas fa-paper-plane"></i> Veröffentlichen</div>
          <label class="news-lbl" style="margin-top:12px">Status</label><select id="newsStatus" class="fc w-100"><option value="draft">Entwurf</option><option value="published">Veröffentlicht</option></select>
          <label class="news-lbl" style="margin-top:10px">Veröffentlichung</label><input id="newsPublishedAt" class="fc w-100" type="datetime-local">
          <label style="display:flex;align-items:center;gap:8px;padding:10px 0 4px;cursor:pointer;"><input id="newsFeatured" type="checkbox" style="accent-color:var(--accent)"><span style="font-weight:700;color:#fff"><i class="fas fa-star" style="color:var(--accent)"></i> Hervorheben</span></label>
          <div id="newsSaveHint" class="hint" style="margin:10px 0">Öffentlich erscheint nur, was den Status „Veröffentlicht“ hat. Liegt „Veröffentlichung“ in der Zukunft, wird der Beitrag erst dann automatisch live geschaltet.</div>
          <div class="d-flex gap-2"><button class="btn-g" style="flex:1" onclick="NewsMagazine.preview()"><i class="fas fa-eye"></i> Vorschau</button><button class="btn-a" style="flex:1" onclick="NewsMagazine.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        </div>
        <div class="card editor-box">
          <div class="tt" style="font-size:.85rem"><i class="fas fa-folder"></i> Kategorie</div>
          <select id="newsCategory" class="fc w-100" style="margin-top:10px"></select>
        </div>
        <div class="card editor-box">
          <div class="tt" style="font-size:.85rem"><i class="fas fa-image"></i> Beitragsbild</div>
          <div style="display:flex;gap:7px;align-items:center;margin-top:10px">
            <input id="newsImage" class="fc w-100" placeholder="Bild-URL oder Upload">
            <button type="button" class="btn-g" onclick="NewsMagazine.openMediaPicker()" title="Aus Mediathek wählen"><i class="fas fa-photo-film"></i></button><button type="button" class="btn-g" onclick="NewsMagazine.pickFeatured()" title="Freie Bilder (Pixabay, Pexels, Unsplash …)"><i class="fas fa-images"></i></button>
            <button type="button" class="btn-g" onclick="document.getElementById('newsThumbFile').click()" title="Thumbnail hochladen"><i class="fas fa-upload"></i></button>
            <input id="newsThumbFile" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="NewsMagazine.uploadThumb(this)">
          </div>
          <div id="newsThumbPreview" style="margin-top:8px"></div>
          <label class="news-lbl" style="margin-top:10px">Bild anzeigen</label>
          <select id="newsImageMode" class="fc w-100">
            <option value="thumbnail">Nur als Thumbnail</option>
            <option value="article">Nur im Beitrag</option>
            <option value="both">Thumbnail + Beitrag</option>
            <option value="none">Bild komplett ausblenden</option>
          </select>
        </div>
      </aside>
    </div>
  </div>`;
}
function mount(){
  if(mounted){load();return;} mounted=true;
  const host=document.getElementById('newsMagazineHost'); if(!host)return;
  host.innerHTML=`<div class="th"><div><div class="tt"><i class="fas fa-newspaper" style="color:var(--accent)"></i>News & Magazin</div><div style="font-size:.72rem;color:var(--muted);margin-top:3px">Beiträge werden direkt unter <code>/cms/data/news.json</code> gespeichert.</div></div><div class="d-flex gap-2"><a class="btn-g" href="/rss.php" target="_blank" rel="noopener" style="text-decoration:none"><i class="fas fa-rss"></i> RSS</a><a class="btn-g" href="/#news" target="_blank" rel="noopener" style="text-decoration:none"><i class="fas fa-eye"></i> News ansehen</a><a class="btn-g" href="api.php?action=news_export&_tok=${encodeURIComponent(cmsNewsToken())}" style="text-decoration:none"><i class="fas fa-file-export"></i> Exportieren</a><button id="newsImportBtn" class="btn-g" onclick="NewsMagazine.importPrompt()" title="Beiträge aus einer Exportdatei (JSON) übernehmen"><i class="fas fa-file-import"></i> Importieren</button><button id="newsTrashToggle" class="btn-g" onclick="NewsMagazine.toggleTrash()"><i class="fas fa-trash"></i> Papierkorb</button><button id="newsNewBtn" class="btn-a" onclick="NewsMagazine.newArticle()"><i class="fas fa-plus"></i> Neuer Beitrag</button></div></div>
  <div class="card"><div style="display:flex;align-items:flex-start;gap:12px;"><div style="width:38px;height:38px;border-radius:10px;background:rgba(255,206,0,.1);display:grid;place-items:center;color:var(--accent)"><i class="fas fa-bullhorn"></i></div><div><b style="color:#fff">${(window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'AnMaCha Redaktion':esc(RRW_P.name+' Redaktion')}</b><div style="color:var(--dim);font-size:.78rem;line-height:1.55;margin-top:3px;">Beiträge hier erstellen, als Entwurf vorbereiten oder veröffentlichen. Veröffentlichte Meldungen erscheinen automatisch ${(window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'im News-Bereich des Radioportals und im öffentlichen Widget':'auf deiner Website und im Feed'}.</div></div></div></div>
  ${categoryManagerHtml()}
  ${editorHtml()}
  <div class="card" style="padding:0;overflow:hidden"><div style="padding:13px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between"><b style="color:#fff"><i class="fas fa-layer-group" style="color:var(--accent);margin-right:6px"></i>Beiträge</b><button class="btn-g" onclick="NewsMagazine.reload()"><i class="fas fa-rotate"></i></button></div>${filterBarHtml()}${bulkBarHtml()}<div id="newsList"></div><div id="newsPagination"></div></div>
  <div class="card"><div class="th"><div class="tt"><i class="fas fa-comments" style="color:var(--accent)"></i>Kommentare</div></div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:12px;">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input id="commentsEnabled" type="checkbox" style="accent-color:var(--accent)" onchange="NewsMagazine.saveCommentSettings()"><span style="font-weight:700;color:#fff">Kommentare aktiviert</span></label>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input id="commentsRequireApproval" type="checkbox" style="accent-color:var(--accent)" onchange="NewsMagazine.saveCommentSettings()"><span style="font-weight:700;color:#fff">Freigabe vor Veröffentlichung nötig</span></label>
    </div>
    <div style="font-size:.7rem;color:var(--muted);margin-bottom:10px;">Kostenlose, selbst gehostete Kommentarfunktion – keine externen Dienste. Leser kommentieren veröffentlichte Beiträge direkt im Portal.</div>
    <div id="commentsAdminList"></div>
  </div>`;
  renderCategoryOptions();
  renderCategoryManager();
  load();
  loadCommentSettings();
  // Kategorien können schon vor dem ersten Öffnen dieses Tabs aktualisiert worden sein
  // (cmsReload() synchronisiert sie nur, wenn NewsMagazine bereits gemountet ist) - deshalb
  // hier einmalig sicherheitshalber den aktuellen Stand nachladen.
  api('get').then(d=>{const cats=d.config?.news_categories; if(Array.isArray(cats)&&cats.length){categories=cats;renderCategoryOptions();renderCategoryManager();}}).catch(()=>{});
  loadCommentsAdmin();
  setTimeout(()=>document.getElementById('newsImage')?.addEventListener('input',renderThumbPreview),0);
}
async function loadCommentSettings(){
  try{const d=await api('comments_settings');commentSettings={enabled:!!d.enabled,require_approval:!!d.require_approval};
    const e=document.getElementById('commentsEnabled'),r=document.getElementById('commentsRequireApproval');
    if(e)e.checked=commentSettings.enabled; if(r)r.checked=commentSettings.require_approval;
  }catch(e){}
}
async function saveCommentSettings(){
  if(!canEdit)return;
  const value={enabled:checked('commentsEnabled'),require_approval:checked('commentsRequireApproval')};
  try{await api('save',{section:'comments',value});commentSettings=value;say('Kommentar-Einstellungen gespeichert','success');}catch(e){say(e.message,'error');}
}
function commentStatusBadge(c){
  const pending=c.status==='pending';
  return '<span style="display:inline-flex;align-items:center;gap:5px;border:1px solid '+(pending?'rgba(255,206,0,.3)':'rgba(0,230,118,.28)')+';background:'+(pending?'rgba(255,206,0,.08)':'rgba(0,230,118,.08)')+';color:'+(pending?'var(--accent)':'var(--good)')+';border-radius:999px;padding:2px 7px;font-size:.62rem;font-weight:800;">'+(pending?'● Wartet auf Freigabe':'● Freigegeben')+'</span>';
}
function commentRow(c,indent){
  return `<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);align-items:start;${indent?'margin-left:28px;border-left:2px solid var(--border);padding-left:12px;':''}">
    <div style="min-width:0;">
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px;"><b style="color:#fff">${esc(c.name)}</b>${c.is_staff?'<span style="font-size:.62rem;font-weight:800;color:var(--accent);border:1px solid rgba(47,184,255,.3);background:rgba(47,184,255,.08);border-radius:999px;padding:2px 7px;">Team</span>':''}${commentStatusBadge(c)}<span style="font-size:.68rem;color:var(--muted)">${fmt(c.created_at)}${indent?'':' · zu „'+esc(c.article_title||'gelöschter Beitrag')+'"'}</span></div>
      <div style="font-size:.78rem;color:var(--dim);line-height:1.5;white-space:pre-wrap;">${esc(c.text)}</div>
      <div id="commentReplyBox-${c.id}" style="display:none;margin-top:8px;gap:6px;">
        <textarea id="commentReplyText-${c.id}" class="fc w-100" rows="2" placeholder="Antwort als Redaktion …" style="margin-bottom:6px;"></textarea>
        <button class="btn-a" onclick="NewsMagazine.sendCommentReply(${c.id},${c.article_id})"><i class="fas fa-reply"></i> Antworten</button>
      </div>
    </div>
    <div style="display:flex;gap:6px;">
      ${c.status==='pending'?'<button class="btn-g" onclick="NewsMagazine.approveComment('+c.id+')" title="Freigeben"><i class="fas fa-check"></i></button>':''}
      <button class="btn-g" onclick="NewsMagazine.toggleCommentReply(${c.id})" title="Antworten"><i class="fas fa-reply"></i></button>
      <button class="btn-d" onclick="NewsMagazine.deleteComment(${c.id})" title="Löschen"><i class="fas fa-trash"></i></button>
    </div>
  </div>`;
}
function renderCommentsAdmin(){
  const host=document.getElementById('commentsAdminList'); if(!host)return;
  if(!commentsAdmin.length){host.innerHTML='<div class="empty"><i class="fas fa-comment-slash"></i>Noch keine Kommentare.</div>';return;}
  const byParent=new Map();
  commentsAdmin.forEach(c=>{const p=c.parent_id||0;if(!byParent.has(p))byParent.set(p,[]);byParent.get(p).push(c);});
  const top=(byParent.get(0)||[]).slice().sort((a,b)=>new Date(b.created_at)-new Date(a.created_at));
  host.innerHTML=top.map(c=>commentRow(c,false)+(byParent.get(c.id)||[]).map(r=>commentRow(r,true)).join('')).join('');
}
async function loadCommentsAdmin(){
  try{const d=await api('comments_admin_list');commentsAdmin=d.comments||[];renderCommentsAdmin();}catch(e){}
}
async function approveComment(id){try{await api('comment_approve',{id});say('Kommentar freigegeben','success');loadCommentsAdmin();}catch(e){say(e.message,'error');}}
async function deleteComment(id){if(!confirm('Kommentar wirklich löschen?'))return;try{await api('comment_delete',{id});say('Kommentar gelöscht','success');loadCommentsAdmin();}catch(e){say(e.message,'error');}}
function toggleCommentReply(id){
  const box=document.getElementById('commentReplyBox-'+id); if(!box)return;
  box.style.display=box.style.display==='none'?'block':'none';
}
async function sendCommentReply(parentId,articleId){
  const ta=document.getElementById('commentReplyText-'+parentId); const text=ta?.value.trim();
  if(!text)return;
  try{await api('comment_reply',{article_id:articleId,parent_id:parentId,text});say('Antwort gesendet','success');loadCommentsAdmin();}catch(e){say(e.message,'error');}
}
let autosaveTimer=null;
function autosaveKey(){ return 'rrw_news_autosave_'+(currentId||'new'); }
function autosaveTick(){
  const data=collectFormData();
  if(!data.title.trim()&&!data.body_html.trim())return;
  try{localStorage.setItem(autosaveKey(),JSON.stringify({data,savedAt:Date.now()}));}catch(e){}
  const el=document.getElementById('newsAutosaveStatus'); if(el)el.textContent='Lokal zwischengespeichert · '+new Date().toLocaleTimeString('de-DE');
}
function startAutosave(){ stopAutosave(); autosaveTimer=setInterval(autosaveTick,20000); }
function stopAutosave(){ if(autosaveTimer){clearInterval(autosaveTimer);autosaveTimer=null;} }
function clearAutosave(){
  try{localStorage.removeItem(autosaveKey());}catch(e){}
  const b=document.getElementById('newsAutosaveBanner'); if(b)b.style.display='none';
  const el=document.getElementById('newsAutosaveStatus'); if(el)el.textContent='';
}
function checkAutosaveRecovery(loaded){
  let raw; try{raw=localStorage.getItem(autosaveKey());}catch(e){return;}
  if(!raw)return;
  let parsed; try{parsed=JSON.parse(raw);}catch(e){return;}
  if(!parsed||!parsed.data)return;
  const same=parsed.data.title===(loaded?.title||'')&&parsed.data.body_html===(loaded?.body_html||'');
  if(same){try{localStorage.removeItem(autosaveKey());}catch(e){}return;}
  const banner=document.getElementById('newsAutosaveBanner'), text=document.getElementById('newsAutosaveText');
  if(banner&&text){text.textContent='Nicht gespeicherter lokaler Stand vom '+new Date(parsed.savedAt).toLocaleString('de-DE')+' gefunden.';banner.style.display='flex';}
}
function restoreAutosave(){
  let raw; try{raw=localStorage.getItem(autosaveKey());}catch(e){return;}
  if(!raw)return;
  let parsed; try{parsed=JSON.parse(raw);}catch(e){return;}
  if(!parsed||!parsed.data)return;
  const d=parsed.data;
  document.getElementById('newsTitle').value=d.title||'';
  document.getElementById('newsCategory').value=d.category||'News';
  document.getElementById('newsExcerpt').value=d.excerpt||'';
  document.getElementById('newsImage').value=d.image_url||'';
  document.getElementById('newsExternal').value=d.external_url||'';
  document.getElementById('newsImageMode').value=d.image_mode||'thumbnail';
  document.getElementById('newsVideo').value=d.video_url||'';
  document.getElementById('newsTags').value=d.tags||'';
  document.getElementById('newsEmbedHtml').value=d.embed_html||'';
  document.getElementById('newsStatus').value=d.status||'draft';
  document.getElementById('newsFeatured').checked=!!d.featured;
  document.getElementById('newsPublishedAt').value=d.published_at||'';
  document.getElementById('newsSeoTitle').value=d.seo_title||'';
  document.getElementById('newsSeoDescription').value=d.seo_description||'';
  document.getElementById('newsBody').innerHTML=d.body_html||'';
  renderThumbPreview(); videoPreview(); dismissAutosave();
  say('Lokaler Stand wiederhergestellt','success');
}
function dismissAutosave(){ const b=document.getElementById('newsAutosaveBanner'); if(b)b.style.display='none'; }
function openEditor(a){
  currentId=a?.id||0;
  document.getElementById('newsEditorCard').style.display='';
  document.getElementById('newsEditorTitle').textContent=currentId?'Beitrag bearbeiten':'Beitrag erstellen';
  document.getElementById('newsTitle').value=a?.title||'';
  renderCategoryOptions();
  const catSel=document.getElementById('newsCategory'), cat=a?.category||'News';
  if(catSel&&![...catSel.options].some(o=>o.value===cat)){const opt=document.createElement('option');opt.textContent=cat;catSel.appendChild(opt);}
  if(catSel)catSel.value=cat;
  document.getElementById('newsExcerpt').value=a?.excerpt||'';
  document.getElementById('newsImage').value=a?.image_url||'';
  document.getElementById('newsExternal').value=a?.external_url||'';
  document.getElementById('newsImageMode').value=a?.image_mode||'thumbnail';
  document.getElementById('newsVideo').value=a?.video_url||'';
  document.getElementById('newsTags').value=a?.tags||'';
  document.getElementById('newsEmbedHtml').value=a?.embed_html||'';
  document.getElementById('newsStatus').value=a?.status||'draft';
  document.getElementById('newsFeatured').checked=String(a?.featured||0)==='1';
  const d=a?.published_at?String(a.published_at).replace(' ','T').slice(0,16):'';
  document.getElementById('newsPublishedAt').value=d;
  document.getElementById('newsSeoTitle').value=a?.seo_title||'';
  document.getElementById('newsSeoDescription').value=a?.seo_description||'';
  document.getElementById('newsBody').innerHTML=a?.body_html||'';
  renderThumbPreview();
  videoPreview();
  const revBtn=document.getElementById('newsRevisionsBtn'); if(revBtn)revBtn.style.display=currentId?'':'none';
  const revPanel=document.getElementById('newsRevisionsPanel'); if(revPanel)revPanel.style.display='none';
  dismissAutosave();
  checkAutosaveRecovery({title:a?.title||'',body_html:a?.body_html||''});
  startAutosave();
  document.getElementById('newsEditorCard').scrollIntoView({behavior:'smooth',block:'start'});
}
async function toggleRevisions(){
  const panel=document.getElementById('newsRevisionsPanel'); if(!panel||!currentId)return;
  if(panel.style.display!=='none'){panel.style.display='none';return;}
  panel.style.display='';
  panel.innerHTML='<div class="empty" style="border:0;padding:10px 0"><i class="fas fa-spinner fa-spin"></i> Lade Versionen…</div>';
  try{
    const r=await fetch(CMS_API+'?action=news_revisions_list&id='+encodeURIComponent(currentId)+'&_='+Date.now(),{headers:cmsNewsHeaders(false)});
    const d=await r.json();
    if(!r.ok||d.status==='error'||d.error)throw new Error(d.message||d.error||'Fehler');
    const revs=d.revisions||[];
    panel.innerHTML=revs.length?revs.map(r=>`<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);">
        <span style="font-size:.76rem;color:var(--dim)"><i class="fas fa-clock" style="color:var(--muted);margin-right:6px"></i>${fmt(r.saved_at)} · ${esc(r.title)}</span>
        <button class="btn-g" onclick="NewsMagazine.restoreRevision('${r.revision_id}')">Wiederherstellen</button>
      </div>`).join(''):'<div class="empty" style="border:0;padding:10px 0">Noch keine früheren Versionen gespeichert.</div>';
  }catch(e){panel.innerHTML='<div class="empty" style="border:0;padding:10px 0;color:#ff8080">'+esc(e.message)+'</div>';}
}
async function restoreRevision(revisionId){
  if(!currentId||!confirm('Diese Version wiederherstellen? Der aktuelle Stand wird vorher als Version gesichert.'))return;
  try{
    await api('news_revision_restore',{id:currentId,revision_id:revisionId});
    say('Version wiederhergestellt','success');
    await edit(currentId);
    load();
  }catch(e){say(e.message,'error');}
}
async function edit(id){try{const r=await fetch(CMS_API+'?action=news_get&id='+encodeURIComponent(id)+'&_='+Date.now(),{headers:cmsNewsHeaders(false)});const d=await r.json();if(!r.ok||d.status==='error'||d.error)throw new Error(d.message||d.error||'Fehler');openEditor(d.article);}catch(e){say(e.message,'error');}}
function collectFormData(){
  return {id:currentId,title:val('newsTitle').trim(),category:val('newsCategory'),excerpt:val('newsExcerpt').trim(),image_url:val('newsImage').trim(),image_mode:val('newsImageMode')||'thumbnail',external_url:val('newsExternal').trim(),video_url:val('newsVideo').trim(),tags:val('newsTags').trim(),embed_html:val('newsEmbedHtml').trim(),status:val('newsStatus'),featured:checked('newsFeatured'),published_at:val('newsPublishedAt'),seo_title:val('newsSeoTitle').trim(),seo_description:val('newsSeoDescription').trim(),body_html:document.getElementById('newsBody')?.innerHTML||''};
}
async function save(){
  if(!canEdit)return say('Keine Redaktionsrechte','error');
  const data=collectFormData();
  if(!data.title)return say('Bitte einen Titel eingeben','error');
  try{const d=await api('news_save',data);clearAutosave();currentId=d.id;say('Beitrag gespeichert','success');await load();closeEditor();}catch(e){say(e.message,'error');}
}
async function del(id){if(!canEdit||!confirm('Beitrag in den Papierkorb verschieben?'))return;try{await api('news_delete',{id});say('Beitrag in den Papierkorb verschoben','success');load();}catch(e){say(e.message,'error');}}
async function restore(id){if(!canEdit)return;try{await api('news_restore',{id});say('Beitrag wiederhergestellt','success');load();}catch(e){say(e.message,'error');}}
async function delPermanent(id){if(!canEdit||!confirm('Beitrag endgültig löschen? Das kann nicht rückgängig gemacht werden.'))return;try{await api('news_delete_permanent',{id});say('Beitrag endgültig gelöscht','success');load();}catch(e){say(e.message,'error');}}
async function duplicate(id){try{await api('news_duplicate',{id});say('Beitrag als Entwurf dupliziert','success');await load();}catch(e){say(e.message,'error');}}
function youtubeId(url){
  const m=String(url||'').match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{6,20})/i);
  return m?m[1]:'';
}
function renderThumbPreview(){
  const h=document.getElementById('newsThumbPreview'),u=val('newsImage').trim(); if(!h)return;
  h.innerHTML=u?'<img src="'+esc(u)+'" alt="" style="width:180px;max-width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:10px;border:1px solid var(--border)" onerror="this.parentElement.innerHTML=\'<span style=&quot;color:#ff8080;font-size:.72rem&quot;>Bild konnte nicht geladen werden</span>\'">':'';
}
async function uploadThumb(input){
  const file=input?.files?.[0]; if(!file)return;
  if(file.size>8*1024*1024){say('Bild darf maximal 8 MB groß sein','error');input.value='';return;}
  const fd=new FormData(); fd.append('file',file);
  try{
    const r=await fetch(CMS_API+'?action=news_thumbnail_upload',{method:'POST',headers:cmsNewsHeaders(false),body:fd});
    const d=await r.json();
    if(!r.ok||d.status!=='ok')throw new Error(d.message||d.error||'Upload fehlgeschlagen');
    document.getElementById('newsImage').value=d.url;
    renderThumbPreview();
    say('Thumbnail hochgeladen','success');
  }catch(e){say(e.message,'error');}
  input.value='';
}
async function openMediaPicker(){
  let modal=document.getElementById('newsMediaPicker');
  if(!modal){
    modal=document.createElement('div'); modal.id='newsMediaPicker';
    modal.style.cssText='position:fixed;inset:0;background:rgba(6,6,10,.7);z-index:3000;display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML='<div style="background:var(--surface,#101522);border:1px solid var(--border,#232b45);border-radius:16px;max-width:760px;width:100%;max-height:82vh;display:flex;flex-direction:column;overflow:hidden;">'
      +'<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border,#232b45);"><b style="color:#fff">Aus Mediathek wählen</b><button type="button" class="btn-g" onclick="NewsMagazine.closeMediaPicker()"><i class="fas fa-xmark"></i></button></div>'
      +'<div id="newsMediaPickerGrid" style="padding:16px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;"><div class="empty"><i class="fas fa-spinner fa-spin"></i>Lädt…</div></div>'
      +'</div>';
    modal.addEventListener('click',e=>{ if(e.target===modal)closeMediaPicker(); });
    document.body.appendChild(modal);
  }
  modal.style.display='flex';
  const grid=document.getElementById('newsMediaPickerGrid');
  grid.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Lädt…</div>';
  try{
    const d=await api('media_library_list');
    const items=(d.items||[]).filter(x=>String(x.mime||'').startsWith('image/'));
    grid.innerHTML=items.length?items.map((x,i)=>{
      const url=x.original?.url||x.url||'';
      return '<button type="button" onclick="NewsMagazine.pickMedia('+i+')" style="border:1px solid var(--border,#232b45);border-radius:10px;padding:6px;background:#0b0f26;cursor:pointer;display:flex;flex-direction:column;gap:5px;align-items:center;"><img src="'+esc(url)+'" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:7px" alt=""><span style="font-size:.64rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">'+esc(x.name||'')+'</span></button>';
    }).join(''):'<div class="empty" style="grid-column:1/-1">Noch keine Bilder in der Mediathek. Lade zuerst eines im Tab „Medien" hoch.</div>';
    openMediaPicker._items=items;
  }catch(e){ grid.innerHTML='<div class="empty" style="grid-column:1/-1;color:var(--bad)">'+esc(e.message)+'</div>'; }
}
function pickMedia(i){
  const item=(openMediaPicker._items||[])[i]; if(!item)return;
  document.getElementById('newsImage').value=item.original?.url||item.url||'';
  renderThumbPreview();
  closeMediaPicker();
}
function closeMediaPicker(){
  const modal=document.getElementById('newsMediaPicker'); if(modal)modal.style.display='none';
}
function videoPreview(){
  const h=document.getElementById('newsVideoPreview'),u=val('newsVideo').trim(); if(!h)return;
  if(!u){h.innerHTML='';return;}
  const yt=youtubeId(u);
  if(yt){
    if(!val('newsImage').trim()){document.getElementById('newsImage').value='https://i.ytimg.com/vi/'+yt+'/hqdefault.jpg';renderThumbPreview();}
    h.innerHTML='<div style="max-width:560px;aspect-ratio:16/9;border:1px solid var(--border);border-radius:12px;overflow:hidden;background:#000"><iframe src="https://www.youtube-nocookie.com/embed/'+yt+'" style="width:100%;height:100%;border:0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>';
    return;
  }
  if(/tiktok\.com\//i.test(u)){
    h.innerHTML='<div style="font-size:.74rem;color:var(--muted);padding:10px 12px;border:1px solid var(--border);border-radius:10px;"><i class="fab fa-tiktok" style="margin-right:6px;color:#fff"></i>TikTok-Video wird im veröffentlichten Artikel eingebettet.</div>';
    return;
  }
  h.innerHTML='<div style="font-size:.72rem;color:var(--muted)">Video-URL erkannt; Vorschau ist für YouTube direkt verfügbar.</div>';
}
function closeEditor(){stopAutosave();const el=document.getElementById('newsEditorCard');if(el)el.style.display='none';currentId=0;}
function preview(){
  const title=esc(val('newsTitle')||'Vorschau'),body=document.getElementById('newsBody')?.innerHTML||'',img=val('newsImage');
  const w=window.open('','_blank');if(!w)return;
  w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>'+title+'</title><style>body{margin:0;background:#07070b;color:#eee;font-family:Segoe UI,system-ui,sans-serif}.x{max-width:820px;margin:40px auto;padding:24px}.x img{width:100%;max-height:420px;object-fit:cover;border-radius:18px}.x h1{font-size:2.4rem}.x .b{line-height:1.7;font-size:1rem}.x a{color:#ffce00}</style></head><body><main class="x">'+(img?'<img src="'+esc(img)+'">':'')+'<h1>'+title+'</h1><div class="b">'+body+'</div></main></body></html>');w.document.close();
}

async function importPrompt(){
  const inp=document.createElement('input');inp.type='file';inp.accept='application/json,.json';
  inp.onchange=async()=>{
    const f=inp.files&&inp.files[0];if(!f)return;
    if(f.size>8*1024*1024){alert('Die Datei ist größer als 8 MB.');return}
    let data;try{data=JSON.parse(await f.text())}catch(e){alert('Die Datei ist kein gültiges JSON.');return}
    const articles=Array.isArray(data)?data:(Array.isArray(data&&data.articles)?data.articles:null);
    if(!articles||!articles.length){alert('In der Datei wurden keine Beiträge gefunden.');return}
    const draft=confirm(articles.length+' Beitrag/Beiträge gefunden.\n\nOK = als Entwurf importieren (empfohlen)\nAbbrechen = Veröffentlichungsstatus aus der Datei übernehmen');
    const dup=confirm('Wenn die Adresse (Slug) schon vorhanden ist:\n\nOK = überspringen\nAbbrechen = als Kopie mit neuer Adresse anlegen')?'skip':'copy';
    try{
      const r=await fetch(CMS_API+'?action=news_import&_='+Date.now(),{method:'POST',headers:cmsNewsHeaders(true),body:JSON.stringify({articles:articles.slice(0,200),as_draft:draft,on_duplicate:dup})});
      const d=await r.json();
      if(d.status!=='ok')throw new Error(d.message||'Import fehlgeschlagen');
      alert(d.imported+' importiert'+(d.skipped?', '+d.skipped+' übersprungen':'')+(d.invalid?', '+d.invalid+' ungültig':'')+(articles.length>200?'\n(Es werden höchstens 200 Beiträge pro Import übernommen.)':''));
      load();
    }catch(e){alert(e.message||'Import fehlgeschlagen')}
  };
  inp.click();
}

/* Bild in den Artikeltext einfügen (Mediathek oder freie Bilder); bei Namensnennungspflicht mit Bildunterschrift */
let savedRange=null;
function rememberRange(){const sel=window.getSelection();if(sel&&sel.rangeCount){const r=sel.getRangeAt(0);const ed=document.getElementById('newsBody');if(ed&&ed.contains(r.commonAncestorContainer))savedRange=r.cloneRange()}}
function insertImage(){
  if(!window.StockMedia){say('Bildauswahl nicht verfügbar','error');return}
  rememberRange();
  StockMedia.open({onPick:(item,info)=>{
    const ed=document.getElementById('newsBody');if(!ed)return;ed.focus();
    const fig=document.createElement('figure'),img=document.createElement('img');img.src=info.urlFor(1024);img.alt=info.alt||'';fig.appendChild(img);
    if(info.credit){const c=document.createElement('figcaption');c.textContent=info.credit;fig.appendChild(c)}
    // hinter den Block einfügen, in dem der Cursor stand (oder ans Ende); ein leerer Absatz danach erlaubt weiteres Schreiben
    let node=savedRange?savedRange.startContainer:null;while(node&&node.parentNode&&node.parentNode!==ed)node=node.parentNode;
    const after=document.createElement('p');after.innerHTML='<br>';
    if(node&&node.parentNode===ed){ed.insertBefore(fig,node.nextSibling)}else ed.appendChild(fig);
    fig.after(after);ed.dispatchEvent(new Event('input',{bubbles:true}));
    if(info.attribution&&!info.credit)say('Bitte den Bildnachweis ergänzen.','info');
  }});
}
function pickFeatured(){
  if(!window.StockMedia){say('Bildauswahl nicht verfügbar','error');return}
  StockMedia.open({tab:'stock',onPick:(item,info)=>{document.getElementById('newsImage').value=info.url;renderThumbPreview()}});
}
window.NewsMagazine={insertImage,pickFeatured,importPrompt,mount,reload:load,newArticle:()=>openEditor(null),edit,save,del,restore,delPermanent,toggleTrash,closeEditor,preview,duplicate,uploadThumb,openMediaPicker,pickMedia,closeMediaPicker,videoPreview,saveCommentSettings,approveComment,deleteComment,toggleCommentReply,sendCommentReply,toggleSelect,toggleSelectAll,applyBulk,onBulkOpChange,startQuickEdit,cancelQuickEdit,saveQuickEdit,toggleRevisions,restoreRevision,setSearch,setStatusFilter,setSort,goToPage,restoreAutosave,dismissAutosave,setCategories,addCategory,removeCategory,renameCategory};
})();