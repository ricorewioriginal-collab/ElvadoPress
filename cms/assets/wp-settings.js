/* Einstellungen wie in WordPress (CMS → Einstellungen): Allgemein, Schreiben, Lesen, Diskussion, Medien, Permalinks.
   Server: wp_settings, wp_settings_save (cms/wp/settings-api.php), wp_reading(_save) und wp_permalinks(_save) (cms/wp/links-api.php). */
(function(){
  'use strict';
  var HINTS={general:'Titel, Sprache, Zeitzone und Formate der Website.',writing:'Vorgaben für neue Beiträge.',reading:'Startseite, Beitragsanzahl und Sichtbarkeit für Suchmaschinen.',discussion:'Kommentare auf deiner Website.',media:'Größen für automatisch erzeugte Bilder.',permalinks:'Form der Adressen deiner Beiträge.'};
  var TITLES={general:'Allgemeine Einstellungen',writing:'Schreibeinstellungen',reading:'Leseeinstellungen',discussion:'Diskussionseinstellungen',media:'Medieneinstellungen',permalinks:'Permalink-Einstellungen'};
  var cur='general',data=null,rd=null,pl=null;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  function field(f){
    var id='wps_'+f.name,v=f.value,h='';
    if(f.type==='bool')h='<label class="wps-chk"><input type="checkbox" id="'+id+'"'+(v?' checked':'')+'> '+esc(f.check||f.label)+'</label>';
    else if(f.type==='enum')h='<select class="fc" id="'+id+'">'+f.options.map(function(o){return '<option value="'+esc(o.value)+'"'+(String(v)===o.value?' selected':'')+'>'+esc(o.label)+'</option>'}).join('')+'</select>';
    else if(f.type==='int')h='<input class="fc" type="number" id="'+id+'" value="'+esc(v)+'" min="'+f.min+'" max="'+f.max+'" step="1" style="max-width:140px">';
    else h='<input class="fc" type="'+(f.type==='email'?'email':'text')+'" id="'+id+'" value="'+esc(v)+'" maxlength="'+(f.max||200)+'">';
    return '<div class="wps-row"><label class="wps-lbl" for="'+id+'">'+esc(f.label)+'</label><div class="wps-in">'+h+(f.help?'<div class="hint">'+esc(f.help)+'</div>':'')+'</div></div>';
  }
  function pageSel(id,val){return '<select class="fc" id="'+id+'"><option value="0">— Seite wählen —</option>'+rd.pages.map(function(p){return '<option value="'+p.id+'"'+(p.id===val?' selected':'')+'>'+esc(p.title)+'</option>'}).join('')+'</select>'}
  function drawReadingTop(){
    var st=rd.show_on_front==='page';
    return '<div class="wps-row"><div class="wps-lbl">Deine Startseite zeigt</div><div class="wps-in">'
      +'<label class="wps-chk"><input type="radio" name="wpsMode" value="posts"'+(st?'':' checked')+'> Deine neuesten Beiträge</label>'
      +'<label class="wps-chk"><input type="radio" name="wpsMode" value="page"'+(st?' checked':'')+'> Eine statische Seite</label>'
      +'<div class="wps-sub"><label>Startseite '+pageSel('wpsFront',rd.page_on_front)+'</label> <label>Beitragsseite '+pageSel('wpsPosts',rd.page_for_posts)+'</label></div>'
      +(rd.pages.length?'':'<div class="hint">Es gibt noch keine Seiten. Lege welche unter „Seiten“ an.</div>')+'</div></div>';
  }
  function drawPermalinks(){
    var h='<div class="wps-row"><div class="wps-lbl">Allgemeine Einstellungen</div><div class="wps-in">';
    Object.keys(pl.presets).forEach(function(k){var p=pl.presets[k];h+='<label class="wps-chk"><input type="radio" name="wpsPl" value="'+esc(k)+'"'+(pl.preset===k?' checked':'')+'> <b>'+esc(p.label)+'</b> <code>'+esc(p.example)+'</code></label>'});
    h+='<label class="wps-chk"><input type="radio" name="wpsPl" value="custom"'+(pl.preset==='custom'?' checked':'')+'> <b>Eigene Struktur</b> <input class="fc" id="wpsPlStruct" value="'+esc(pl.structure)+'"'+(pl.preset==='custom'?'':' disabled')+' style="max-width:320px"></label>';
    h+='<div class="hint">Platzhalter: '+pl.tokens.map(function(t){return '<code>'+esc(t)+'</code>'}).join(' ')+'</div></div></div>';
    h+='<div class="wps-row"><div class="wps-lbl">Optional</div><div class="wps-in"><label>Kategorie-Basis <input class="fc" id="wpsPlCat" value="'+esc(pl.category_base)+'" placeholder="category" style="max-width:200px"></label> <label>Schlagwort-Basis <input class="fc" id="wpsPlTag" value="'+esc(pl.tag_base)+'" placeholder="tag" style="max-width:200px"></label>'
      +'<label class="wps-chk"><input type="checkbox" id="wpsPlHash"'+(pl.hash?' checked':'')+'> Hash-Form <code>/#/seite</code></label>'
      +'<div class="hint">Beispiel mit deinem neuesten Beitrag: <code>'+esc(pl.sample||'–')+'</code></div></div></div>';
    return h;
  }
  function draw(){
    var box=$('wpsBody');if(!box)return;
    $('wpsTitle').textContent=TITLES[cur];$('wpsHint').textContent=HINTS[cur];
    document.querySelectorAll('#wpsTabs [data-g]').forEach(function(b){b.classList.toggle('on',b.dataset.g===cur)});
    var h='';
    if(cur==='permalinks'){if(!pl){box.innerHTML='<div class="hint">Lädt …</div>';return}h=drawPermalinks()}
    else{
      if(!data){box.innerHTML='<div class="hint">Lädt …</div>';return}
      if(cur==='reading'&&rd)h+=drawReadingTop();
      h+=data.fields.map(field).join('');
    }
    box.innerHTML=h+'<div style="margin-top:14px"><button class="btn-a" type="button" onclick="WpSettings.save()"><i class="fas fa-floppy-disk"></i> Änderungen speichern</button></div>';
    box.onchange=function(e){
      if(e.target.name==='wpsMode'){var on=e.target.value==='page';['wpsFront','wpsPosts'].forEach(function(i){$(i).disabled=!on})}
      if(e.target.name==='wpsPl'){var c=$('wpsPlStruct');c.disabled=e.target.value!=='custom';if(!c.disabled)c.focus()}
    };
    if(cur==='reading'&&rd&&rd.show_on_front!=='page')['wpsFront','wpsPosts'].forEach(function(i){$(i).disabled=true});
  }
  async function open(g){
    cur=TITLES[g]?g:'general';data=null;draw();
    try{
      if(cur==='permalinks'){pl=await cmsApi('wp_permalinks',{})}
      else{
        data=await cmsApi('wp_settings',{group:cur});
        if(cur==='reading')rd=await cmsApi('wp_reading',{});
      }
    }catch(e){var b=$('wpsBody');if(b)b.innerHTML='<div class="hint" style="color:var(--bad)">'+esc(e.message||'Laden fehlgeschlagen')+'</div>';return}
    draw();
  }
  function collect(){
    var v={};data.fields.forEach(function(f){var el=$('wps_'+f.name);if(!el)return;v[f.name]=f.type==='bool'?el.checked:el.value});return v;
  }
  async function save(){
    try{
      if(cur==='permalinks'){
        var sel=document.querySelector('input[name="wpsPl"]:checked'),k=sel?sel.value:'post',body={category_base:$('wpsPlCat').value.trim(),tag_base:$('wpsPlTag').value.trim(),hash:$('wpsPlHash').checked};
        if(k==='custom')body.structure=$('wpsPlStruct').value.trim();else body.preset=k;
        if(k==='plain'&&pl.structure!==''&&!confirm('Mit der einfachen Form (?p=123) sehen die Adressen nicht mehr so schön aus. Trotzdem speichern?'))return;
        pl=await cmsApi('wp_permalinks_save',body);
      }else{
        if(cur==='reading'&&rd){
          var m=document.querySelector('input[name="wpsMode"]:checked');
          rd=await cmsApi('wp_reading_save',{show_on_front:m?m.value:'posts',page_on_front:+$('wpsFront').value,page_for_posts:+$('wpsPosts').value});
        }
        data=await cmsApi('wp_settings_save',{group:cur,values:collect()});
      }
      draw();toast('Einstellungen gespeichert ✓');
    }catch(e){toast(e.message||'Speichern fehlgeschlagen',true)}
  }
  window.WpSettings={open:open,save:save};
})();
