/* Link-Struktur (CMS → Design → WordPress-Themes → Link-Struktur). Server: wp_permalinks / wp_permalinks_save in cms/api.php, cms/wp/links-api.php. */
(function(){
  'use strict';
  var st=null;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||''}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  async function call(action,body){
    var o={headers:{'X-ElvadoPress-Token':token()}};if(body!==undefined){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
    var r=await fetch('api.php?action='+action+'&_='+Date.now(),o),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
  }
  function draw(){
    var box=$('wlForm');if(!box||!st)return;
    var h='<div class="wl-list">';
    Object.keys(st.presets).forEach(function(k){var p=st.presets[k];h+='<label class="wl-opt"><input type="radio" name="wlPreset" value="'+esc(k)+'"'+(st.preset===k?' checked':'')+'><span><b>'+esc(p.label)+'</b><code>'+esc(location.origin+p.example)+'</code></span></label>'});
    h+='<label class="wl-opt"><input type="radio" name="wlPreset" value="custom"'+(st.preset==='custom'?' checked':'')+'><span><b>Eigene Struktur</b><input class="fc" id="wlStruct" value="'+esc(st.structure)+'" placeholder="/blog/%category%/%postname%/" '+(st.preset==='custom'?'':'disabled')+'></span></label></div>';
    h+='<div class="hint" id="wlTokens" style="margin:6px 0">Platzhalter: '+st.tokens.map(function(t){return '<code>'+esc(t)+'</code>'}).join(' ')+'. Ein Verzeichnis davor (z. B. <code>/blog/%postname%/</code>) ergibt Adressen wie <code>/blog/mein-beitrag/</code>.</div>';
    h+='<div class="wl-grid"><label>Kategorie-Basis<input class="fc" id="wlCat" value="'+esc(st.category_base)+'" placeholder="category"></label><label>Schlagwort-Basis<input class="fc" id="wlTag" value="'+esc(st.tag_base)+'" placeholder="tag"></label></div>';
    h+='<label class="wl-hash"><input type="checkbox" id="wlHash"'+(st.hash?' checked':'')+'><span>Hash-Form <code>/#/seite</code> – Links erscheinen mit <code>#</code>; der Browser leitet beim Aufruf der Startseite auf die echte Adresse weiter. Nur nötig, wenn du diese Form ausdrücklich brauchst.</span></label>';
    h+='<div class="hint" style="margin:8px 0">Beispiel mit deinem neuesten Beitrag: <code>'+esc(st.sample||'–')+'</code></div>';
    h+='<button class="btn-a" type="button" onclick="WpLinks.save()"><i class="fas fa-floppy-disk"></i> Link-Struktur speichern</button>';
    box.innerHTML=h;
    box.onchange=function(e){if(e.target.name==='wlPreset'){var c=$('wlStruct');c.disabled=e.target.value!=='custom';if(e.target.value==='custom')c.focus()}};
  }
  async function load(){
    if(st&&$('wlForm').dataset.ok)return;
    try{st=await call('wp_permalinks');draw();$('wlForm').dataset.ok='1'}catch(e){$('wlForm').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  async function save(){
    var sel=document.querySelector('input[name="wlPreset"]:checked'),v=sel?sel.value:'post',body={category_base:$('wlCat').value.trim(),tag_base:$('wlTag').value.trim(),hash:$('wlHash').checked};
    if(v==='custom')body.structure=$('wlStruct').value.trim();else body.preset=v;
    if(st&&st.structure!==''&&(v==='plain')&&!confirm('Mit der einfachen Form (?p=123) sehen die Adressen nicht mehr so schön aus. Trotzdem speichern?'))return;
    try{st=await call('wp_permalinks_save',body);draw();toast('Link-Struktur gespeichert ✓')}catch(e){toast(e.message,true)}
  }
  /* Startseite und Beitragsseite */
  var rd=null;
  function pageSel(id,val,none){return '<select class="fc" id="'+id+'">'+(none?'<option value="0">'+esc(none)+'</option>':'<option value="0">— Seite wählen —</option>')+rd.pages.map(function(p){return '<option value="'+p.id+'"'+(p.id===val?' selected':'')+'>'+esc(p.title)+'</option>'}).join('')+'</select>'}
  function drawReading(){
    var box=$('wrForm');if(!box||!rd)return;var st=rd.show_on_front==='page';
    box.innerHTML='<div class="wl-list"><label class="wl-opt"><input type="radio" name="wrMode" value="posts"'+(st?'':' checked')+'><span><b>Deine neuesten Beiträge</b><span class="hint">Die Startseite ist die Beitragsübersicht.</span></span></label>'
      +'<label class="wl-opt"><input type="radio" name="wrMode" value="page"'+(st?' checked':'')+'><span><b>Eine statische Seite</b><span class="wl-grid"><label>Startseite'+pageSel('wrFront',rd.page_on_front)+'</label><label>Beitragsseite (Blog)'+pageSel('wrPosts',rd.page_for_posts,'— keine —')+'</label></span></span></label></div>'
      +(rd.pages.length?'':'<div class="hint" style="margin:8px 0">Es gibt noch keine Seiten. Lege welche im CMS unter „Seiten“ an.</div>')
      +'<div style="margin-top:10px"><button class="btn-a" type="button" onclick="WpLinks.saveReading()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>';
    box.onchange=function(e){if(e.target.name==='wrMode'){var on=e.target.value==='page';['wrFront','wrPosts'].forEach(function(i){$(i).disabled=!on})}};
    ['wrFront','wrPosts'].forEach(function(i){$(i).disabled=!st});
  }
  async function loadReading(){
    if(rd&&$('wrForm').dataset.ok)return;
    try{rd=await call('wp_reading');drawReading();$('wrForm').dataset.ok='1'}catch(e){$('wrForm').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  async function saveReading(){
    var m=document.querySelector('input[name="wrMode"]:checked'),mode=m?m.value:'posts';
    try{rd=await call('wp_reading_save',{show_on_front:mode,page_on_front:+$('wrFront').value,page_for_posts:+$('wrPosts').value});drawReading();toast('Startseite gespeichert ✓')}catch(e){toast(e.message,true)}
  }
  window.WpLinks={load:load,save:save,loadReading:loadReading,saveReading:saveReading};
})();
