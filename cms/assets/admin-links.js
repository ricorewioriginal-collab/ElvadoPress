/* Eigene Links im Verwaltungsmenü (unten in der Seitenleiste). Server: admin_links_get / admin_links_save (cms/lib/admin-links.php).
   Anzeige für alle Benutzer, Bearbeitung unter Einstellungen → Eigene Links (nur Administratoren). */
(function(){
  'use strict';
  var links=[],draft=[],cur='';
  var ICONS={link:'Link',globe:'Globus','chart-line':'Statistik',envelope:'E-Mail',folder:'Ordner',book:'Buch / Doku','cart-shopping':'Shop',users:'Team',headset:'Support','file-lines':'Dokument',gear:'Zahnrad',star:'Stern',calendar:'Kalender',bell:'Glocke',cloud:'Cloud',code:'Code',database:'Datenbank','shield-halved':'Sicherheit',image:'Bild',music:'Musik',video:'Video'};
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function safeUrl(u){return /^(https?:\/\/[^\s\\]+|\/(?!\/)[^\s\\]*)$/i.test(String(u||''))}
  function iconOf(l){return /^[a-z0-9-]{2,40}$/.test(l.icon||'')?l.icon:'link'}
  function note(t,bad){var e=$('alMsg');if(!e)return;e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}
  function find(id){for(var i=0;i<links.length;i++)if(links[i].id===id)return links[i];return null}

  /* Menü unten in der Seitenleiste */
  function renderMenu(){
    var host=$('cmsCustomLinks');if(!host)return;
    var ok=links.filter(function(l){return safeUrl(l.url)});
    host.hidden=!ok.length;
    host.innerHTML=ok.map(function(l){
      var ic='<i class="fas fa-'+esc(iconOf(l))+'"></i>';
      if(l.mode==='frame')return '<button type="button" class="tab cl-link'+(cur===l.id&&$('panel-customlink').classList.contains('on')?' on':'')+'" data-tab="customlink" data-cl="'+esc(l.id)+'" onclick="cmsTab(\'customlink\',this);AdminLinks.open(this.dataset.cl)">'+ic+'<span>'+esc(l.label)+'</span></button>';
      return '<a class="tab cl-link" href="'+esc(l.url)+'" target="_blank" rel="noopener noreferrer">'+ic+'<span>'+esc(l.label)+'</span><i class="fas fa-arrow-up-right-from-square cl-ext" aria-hidden="true"></i></a>';
    }).join('');
  }
  async function load(){
    try{var d=await cmsApi('admin_links_get');links=d.links||[];renderMenu();if(!$('panel-adminlinks').classList.contains('on')){draft=links.map(function(l){return Object.assign({},l)});drawEditor()}}
    catch(e){/* nicht angemeldet oder keine Verbindung: Menü bleibt ohne eigene Links */}
  }

  /* Rahmenansicht */
  function sameOrigin(u){try{return new URL(u,location.href).origin===location.origin}catch(e){return false}}
  function open(id){
    var l=find(id),fr=$('clFrame');if(!l||!fr||!safeUrl(l.url))return;
    cur=id;$('clTitle').textContent=l.label;$('clIcon').className='fas fa-'+iconOf(l);$('clNew').href=l.url;
    /* eigene Seite: ohne allow-same-origin, damit eingebettete Inhalte nie auf die Sitzung der Verwaltung zugreifen können */
    fr.setAttribute('sandbox','allow-scripts allow-forms allow-popups allow-downloads'+(sameOrigin(l.url)?'':' allow-same-origin'));
    fr.src=l.url;renderMenu();
  }
  function reload(){var l=find(cur),fr=$('clFrame');if(l&&fr)fr.src=l.url}

  /* Bearbeiten */
  function row(l,i){
    var opts=Object.keys(ICONS).map(function(k){return '<option value="'+k+'"'+(iconOf(l)===k?' selected':'')+'>'+esc(ICONS[k])+'</option>'}).join('');
    return '<div class="al-row" data-i="'+i+'">'
      +'<input class="fc al-label" maxlength="40" placeholder="Name" value="'+esc(l.label)+'" aria-label="Name">'
      +'<input class="fc al-url" maxlength="500" placeholder="https://… oder /pfad/" value="'+esc(l.url)+'" aria-label="Adresse">'
      +'<select class="fc al-icon" aria-label="Symbol">'+opts+'</select>'
      +'<select class="fc al-mode" aria-label="Öffnen"><option value="new"'+(l.mode!=='frame'?' selected':'')+'>Neuer Tab</option><option value="frame"'+(l.mode==='frame'?' selected':'')+'>Im Rahmen öffnen</option></select>'
      +'<span class="al-btns"><button type="button" class="btn-g" onclick="AdminLinks.move('+i+',-1)" title="Nach oben" aria-label="Nach oben"><i class="fas fa-arrow-up"></i></button>'
      +'<button type="button" class="btn-g" onclick="AdminLinks.move('+i+',1)" title="Nach unten" aria-label="Nach unten"><i class="fas fa-arrow-down"></i></button>'
      +'<button type="button" class="btn-g" onclick="AdminLinks.remove('+i+')" title="Entfernen" aria-label="Entfernen"><i class="fas fa-trash"></i></button></span></div>';
  }
  function drawEditor(){var h=$('alList');if(h)h.innerHTML=draft.length?draft.map(row).join(''):'<div class="hint">Noch keine eigenen Links.</div>'}
  function collect(){
    var rows=document.querySelectorAll('#alList .al-row');
    draft=Array.prototype.map.call(rows,function(r,i){var o=draft[+r.dataset.i]||{};return {id:o.id||'',label:r.querySelector('.al-label').value.trim(),url:r.querySelector('.al-url').value.trim(),icon:r.querySelector('.al-icon').value,mode:r.querySelector('.al-mode').value}});
  }
  function add(){collect();if(draft.length>=20)return note('Höchstens 20 eigene Links',true);draft.push({id:'',label:'',url:'',icon:'link',mode:'new'});drawEditor();note('');var l=document.querySelectorAll('#alList .al-label');if(l.length)l[l.length-1].focus()}
  function remove(i){collect();draft.splice(i,1);drawEditor();note('Nicht gespeichert')}
  function move(i,d){collect();var j=i+d;if(j<0||j>=draft.length)return;var t=draft[i];draft[i]=draft[j];draft[j]=t;drawEditor();note('Nicht gespeichert')}
  async function save(){
    collect();
    for(var i=0;i<draft.length;i++){if(!draft[i].label)return note('Zeile '+(i+1)+': Name fehlt',true);if(!safeUrl(draft[i].url))return note('Zeile '+(i+1)+': Adresse ungültig – https://…, http://… oder /pfad/',true)}
    try{
      var d=await cmsApi('admin_links_save',{links:draft});links=d.links||[];draft=links.map(function(l){return Object.assign({},l)});drawEditor();
      if(cur&&!find(cur)){cur='';var p=$('panel-customlink');if(p&&p.classList.contains('on'))cmsTab('overview',document.querySelector('.tab[data-tab="overview"]'))}
      renderMenu();note('Gespeichert – das Menü ist aktualisiert.');
    }catch(e){note(e.message||'Speichern fehlgeschlagen',true)}
  }
  function edit(){draft=links.map(function(l){return Object.assign({},l)});drawEditor();note('')}

  window.AdminLinks={load:load,open:open,reload:reload,edit:edit,add:add,remove:remove,move:move,save:save};
  var app=$('cmsApp');if(app&&app.style.display==='')load();
})();
