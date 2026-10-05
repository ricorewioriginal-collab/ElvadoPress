/* Globale CMS-Suche (Strg+K oder „/“): Bereiche, Beiträge und Seiten finden und direkt öffnen – wie die Suche in der WordPress-Adminleiste. */
(function(){
  'use strict';
  var box=null,input=null,list=null,items=[],sel=0,newsCache=null,newsAt=0,newsP=null,seq=0;
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function norm(s){return String(s==null?'':s).toLowerCase()}
  function visible(el){return el&&!el.hidden&&el.style.display!=='none'}

  function labelOf(t){var s='';t.childNodes.forEach(function(n){if(n.nodeType===3)s+=n.textContent});return (s||t.textContent).trim()}
  function sections(){
    var out=[];
    document.querySelectorAll('.tabs .tab-group').forEach(function(g){
      if(g.style.display==='none')return;
      var gt=g.querySelector('.tab-group-title span'),group=gt?gt.textContent:'';
      g.querySelectorAll('.tab').forEach(function(t){
        if(!visible(t))return;
        out.push({kind:'Bereich',icon:(t.querySelector('i')||{}).className||'fas fa-gear',title:labelOf(t),sub:group,key:t.dataset.tab,go:function(){t.click()}});
      });
    });
    return out;
  }
  function pages(){
    var p=(typeof CMS!=='undefined'&&CMS&&Array.isArray(CMS.pages))?CMS.pages:[];
    return p.map(function(x){return {kind:'Seite',icon:'fas fa-file-lines',title:String(x.title||x.slug||''),sub:'/'+(x.slug||''),key:x.slug||'',go:function(){cmsGoto('pages');if(typeof selectPage==='function')selectPage(x.id)}}});
  }
  function loadNews(){
    if(newsCache&&Date.now()-newsAt<60000)return Promise.resolve(newsCache);
    if(newsP)return newsP;                                  // laufende Abfrage teilen statt pro Tastendruck neu zu laden
    newsP=cmsApi('news_list').then(function(d){
      newsCache=(d.articles||[]).filter(function(a){return !a.deleted_at}).map(function(a){
        return {kind:'Beitrag',icon:'fas fa-newspaper',title:String(a.title||'(ohne Titel)'),sub:[a.category,a.status==='published'?'':'Entwurf'].filter(Boolean).join(' · '),key:[a.slug,a.tags,a.category].join(' '),go:function(){cmsGoto('news');setTimeout(function(){if(window.NewsMagazine&&NewsMagazine.edit)NewsMagazine.edit(a.id)},350)}};
      });newsAt=Date.now();return newsCache;
    }).catch(function(){return newsCache||[]}).then(function(r){newsP=null;return r});
    return newsP;
  }

  function build(){
    box=document.createElement('div');box.id='cmsSearch';box.hidden=true;
    box.innerHTML='<div class="cs-back"></div><div class="cs-dlg" role="dialog" aria-modal="true" aria-label="CMS durchsuchen"><div class="cs-in"><i class="fas fa-magnifying-glass"></i><input type="text" id="csInput" placeholder="Bereiche, Beiträge, Seiten suchen…" autocomplete="off" spellcheck="false"><kbd>Esc</kbd></div><div class="cs-list" id="csList" role="listbox"></div><div class="cs-foot">↑↓ wählen · Enter öffnen · Strg+K</div></div>';
    document.body.appendChild(box);
    input=box.querySelector('#csInput');list=box.querySelector('#csList');
    box.querySelector('.cs-back').addEventListener('click',close);
    input.addEventListener('input',run);
    input.addEventListener('keydown',function(e){
      if(e.key==='ArrowDown'){e.preventDefault();move(1)}else if(e.key==='ArrowUp'){e.preventDefault();move(-1)}
      else if(e.key==='Enter'){e.preventDefault();open(sel)}else if(e.key==='Escape'){e.preventDefault();close()}
    });
    list.addEventListener('click',function(e){var r=e.target.closest('[data-i]');if(r)open(parseInt(r.dataset.i,10))});
  }
  function score(it,q){
    var t=norm(it.title),k=norm(it.key+' '+it.sub);
    if(t===q)return 100;if(t.indexOf(q)===0)return 80;if(t.indexOf(' '+q)>=0)return 60;if(t.indexOf(q)>=0)return 50;if(k.indexOf(q)>=0)return 20;return 0;
  }
  function rank(list,q){return list.map(function(it){return {it:it,s:score(it,q)}}).filter(function(x){return x.s>0}).sort(function(a,b){return b.s-a.s}).map(function(x){return x.it})}
  async function run(){
    var q=norm(input.value).trim(),my=++seq;
    var base=sections().concat(pages());
    if(!q){items=base.slice(0,12);paint(q);return}
    items=rank(base,q).slice(0,30);paint(q);              // Bereiche und Seiten sofort
    var news=await loadNews();if(my!==seq)return;          // Beiträge kommen nach, sobald geladen
    items=rank(base.concat(news),q).slice(0,30);paint(q);
  }
  function paint(q){
    sel=0;
    list.innerHTML=items.length?items.map(function(it,i){return '<div class="cs-row'+(i===0?' on':'')+'" role="option" data-i="'+i+'"><i class="'+esc(it.icon)+'"></i><span class="cs-t">'+esc(it.title)+'</span><span class="cs-s">'+esc(it.sub)+'</span><span class="cs-k">'+esc(it.kind)+'</span></div>'}).join(''):'<div class="cs-empty">'+(q?'Nichts gefunden.':'Tippe, um zu suchen.')+'</div>';
  }
  function move(d){
    if(!items.length)return;sel=(sel+d+items.length)%items.length;
    [].forEach.call(list.children,function(r,i){r.classList.toggle('on',i===sel);if(i===sel&&r.scrollIntoView)r.scrollIntoView({block:'nearest'})});
  }
  function open(i){var it=items[i];if(!it)return;close();try{it.go()}catch(e){}}
  function show(){
    var app=document.getElementById('cmsApp');if(!app||app.style.display==='none')return;
    if(!box)build();box.hidden=false;input.value='';input.focus();run();loadNews();
  }
  function close(){if(box)box.hidden=true}
  function mountButton(){
    var bar=document.querySelector('.cms-navbar-actions');if(!bar||document.getElementById('cmsSearchBtn'))return;
    var b=document.createElement('button');b.type='button';b.id='cmsSearchBtn';b.className='btn btn-sm btn-outline-light';b.title='CMS durchsuchen (Strg+K)';b.innerHTML='<i class="fas fa-magnifying-glass"></i>';
    b.addEventListener('click',show);
    var nm=document.getElementById('cmsNewMenu');bar.insertBefore(b,nm?nm.nextSibling:bar.firstChild);
  }
  document.addEventListener('keydown',function(e){
    var tag=(e.target&&e.target.tagName||'').toLowerCase(),typing=tag==='input'||tag==='textarea'||tag==='select'||(e.target&&e.target.isContentEditable);
    if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();show();return}
    if(e.key==='/'&&!typing&&!e.ctrlKey&&!e.metaKey&&!e.altKey){e.preventDefault();show()}
  });
  function boot(){mountButton();setTimeout(mountButton,600)}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  window.CmsSearch={open:show,close:close};
})();
