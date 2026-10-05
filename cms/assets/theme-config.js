/* Theme-Einstellungen (CMS → Menü des aktiven Themes, z. B. „Band“): Editor, der sich aus dem Schema des Themes aufbaut.
   Server: themeconf_state / themeconf_get / themeconf_save in cms/api.php, Schema und Bereinigung in cms/lib/themeconf.php (+ cms/lib/<theme>.php). */
(function(){
  'use strict';
  var cur=null,schema=null,cfg=null,sec='',open={},dirty=false;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function msg(t,bad){var e=$('tcMsg');if(e){e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}}
  function mark(){dirty=true;msg('Ungespeicherte Änderungen')}
  /* Menüpunkte der aktiven Themes (z. B. „Band“) aus dem Serverzustand aufbauen; bei jedem Laden der Konfiguration und nach Theme-Wechseln */
  async function refresh(){
    try{var d=await cmsApi('themeconf_state'),cf=d.configs||{},host=$('tcMenuHost');
      if(host)host.innerHTML=Object.keys(cf).filter(function(id){return cf[id].active}).map(function(id){var c=cf[id];
        return '<div class="tab-group single"><div class="tab-group-title" role="button" tabindex="0"><i class="fas '+esc(c.icon||'fa-sliders')+'"></i><span>'+esc(c.menu||c.title)+'</span><i class="fas fa-chevron-down tg-chev"></i></div>'
          +'<div class="tab-group-body"><button class="tab'+(cur===id&&$('panel-themeconf').classList.contains('on')?' on':'')+'" data-tab="themeconf" onclick="cmsTab(\'themeconf\',this);ThemeConf.open(\''+esc(id)+'\')"><i class="fas fa-sliders"></i>'+esc(c.title)+'</button></div></div>'}).join('');
      var p=$('panel-themeconf');if(p&&p.classList.contains('on')&&cur&&!(cf[cur]&&cf[cur].active))cmsTab('overview',document.querySelector('.tab[data-tab="overview"]'));
    }catch(e){}
  }
  function secOf(id){for(var i=0;i<schema.sections.length;i++)if(schema.sections[i].id===id)return schema.sections[i];return null}
  function fieldHtml(f,val,at){
    var h='<div'+(f.wide||f.type==='textarea'?' class="wide"':'')+'><label class="news-lbl">'+esc(f.label)+'</label>';
    if(f.type==='textarea')h+='<textarea class="fc w-100" rows="4" '+at+'>'+esc(val)+'</textarea>';
    else if(f.type==='checkbox')h+='<label style="display:flex;gap:8px;align-items:center"><input class="switch" type="checkbox" '+(val?'checked ':'')+at+'> Ja</label>';
    else if(f.type==='select')h+='<select class="fc w-100" '+at+'>'+Object.keys(f.options).map(function(k){return '<option value="'+esc(k)+'"'+(String(val)===k?' selected':'')+'>'+esc(f.options[k])+'</option>'}).join('')+'</select>';
    else if(f.type==='image')h+='<div class="tc-img">'+(val?'<img src="'+esc(val)+'" alt="">':'')+'<input class="fc" placeholder="Bild-Adresse" value="'+esc(val)+'" '+at+'><button type="button" class="btn-g" data-pick="1" '+at.replace(/data-k=/,'data-pk=')+'><i class="fas fa-images"></i></button></div>';
    else{var t={date:'date',time:'time',email:'email',number:'number'}[f.type]||'text';h+='<input class="fc w-100" type="'+t+'"'+(f.type==='number'?' min="'+(f.min||0)+'" max="'+(f.max||100000)+'"':'')+(f.max&&f.type==='text'?' maxlength="'+f.max+'"':'')+' value="'+esc(val)+'" '+at+'>'}
    return h+'</div>';
  }
  function draw(){
    var s=secOf(sec);if(!s){return}
    $('tcTabs').innerHTML=schema.sections.map(function(x){return '<button type="button" class="btn-g'+(x.id===sec?' on':'')+'" data-tab-sec="'+x.id+'"><i class="fas '+esc(x.icon||'fa-circle')+'"></i> '+esc(x.title)+'</button>'}).join('');
    var h=s.hint?'<p class="hint" style="margin:0 0 10px">'+esc(s.hint)+'</p>':'';
    if((s.kind||'form')==='list'){
      var items=cfg[sec]||[];
      h+=items.map(function(it,i){
        var key=it[s.item_label]||it[s.required]||'',k=sec+':'+i;
        return '<div class="tc-item'+(open[k]?' open':'')+'"><div class="tc-item-head" data-toggle="'+i+'"><i class="fas fa-grip-lines"></i><b>'+esc(key||'(neu)')+'</b><span class="hint">'+esc(it.date||it.city||'')+'</span><span class="tools">'
          +'<button type="button" class="btn-g" data-act="up" data-i="'+i+'"'+(i===0?' disabled':'')+' aria-label="Nach oben"><i class="fas fa-arrow-up"></i></button><button type="button" class="btn-g" data-act="down" data-i="'+i+'"'+(i===items.length-1?' disabled':'')+' aria-label="Nach unten"><i class="fas fa-arrow-down"></i></button>'
          +'<button type="button" class="btn-g" data-act="dup" data-i="'+i+'" aria-label="Duplizieren"><i class="fas fa-copy"></i></button><button type="button" class="btn-g" data-act="del" data-i="'+i+'" aria-label="Löschen"><i class="fas fa-trash"></i></button></span></div>'
          +'<div class="tc-item-body">'+(open[k]?'<div class="tc-grid">'+s.fields.map(function(f){return fieldHtml(f,it[f.k],'data-i="'+i+'" data-k="'+f.k+'"')}).join('')+'</div>':'')+'</div></div>';
      }).join('')||'<div class="empty"><i class="fas fa-plus"></i>Noch keine Einträge.</div>';
      if(items.length<(s.max||50))h+='<div style="margin-top:8px"><button type="button" class="btn-g" data-act="add"><i class="fas fa-plus"></i> Hinzufügen</button></div>';
    }else{
      h+='<div class="tc-grid">'+s.fields.map(function(f){return fieldHtml(f,(cfg[sec]||{})[f.k],'data-k="'+f.k+'"')}).join('')+'</div>';
    }
    $('tcBody').innerHTML=h;
  }
  function defaults(s){var o={};s.fields.forEach(function(f){o[f.k]=f.default!==undefined?f.default:(f.type==='checkbox'?false:f.type==='number'?(f.min||0):f.type==='select'?Object.keys(f.options)[0]:'')});return o}
  async function load(id){
    cur=id;dirty=false;
    try{
      var d=await cmsApi('themeconf_get',{id:id});schema=d.schema;cfg=d.config;sec=schema.sections[0].id;open={};
      $('tcTitle').innerHTML='<i class="fas '+esc(schema.icon||'fa-sliders')+'"></i>'+esc(schema.title);$('tcHint').textContent=schema.hint||'';
      var n=$('tcNotice');n.style.display=d.active?'none':'';if(!d.active)n.innerHTML='Das zugehörige Theme ist noch nicht aktiv. Du kannst hier schon einrichten; aktiviere es unter <a href="#" onclick="cmsTab(\'themes\',document.querySelector(\'.tab[data-tab=themes]\'));return false">Design → Themes</a>.';
      draw();msg('');
    }catch(e){msg(e.message||'Laden fehlgeschlagen',true)}
  }
  async function save(){
    try{msg('Speichere…');var d=await cmsApi('themeconf_save',{id:cur,config:cfg});cfg=d.config;dirty=false;draw();msg('Gespeichert.')}catch(e){msg(e.message||'Speichern fehlgeschlagen',true)}
  }
  function pick(cb){if(window.HomeBuilder&&HomeBuilder.pickImage)HomeBuilder.pickImage(cb)}
  function onClick(e){
    var t=e.target,tb=t.closest('[data-tab-sec]');if(tb){sec=tb.getAttribute('data-tab-sec');open={};draw();return}
    var p=t.closest('[data-pick]');if(p){var i=p.getAttribute('data-i'),k=p.getAttribute('data-pk');pick(function(u){if(i!==null&&i!=='')cfg[sec][+i][k]=u;else cfg[sec][k]=u;mark();draw()});return}
    var a=t.closest('[data-act]');
    if(a){var s=secOf(sec),items=cfg[sec],i2=+a.dataset.i,act=a.dataset.act;
      if(act==='add'){items.push(defaults(s));open[sec+':'+(items.length-1)]=true}
      else if(act==='del'){if(!confirm('Eintrag löschen?'))return;items.splice(i2,1);open={}}
      else if(act==='dup'){items.splice(i2+1,0,JSON.parse(JSON.stringify(items[i2])));open={}}
      else if(act==='up'&&i2>0){items.splice(i2-1,0,items.splice(i2,1)[0]);open={}}
      else if(act==='down'&&i2<items.length-1){items.splice(i2+1,0,items.splice(i2,1)[0]);open={}}
      mark();draw();return}
    var h=t.closest('[data-toggle]');if(h&&!t.closest('.tools')){var k2=sec+':'+h.dataset.toggle;open[k2]=!open[k2];draw()}
  }
  function onInput(e){
    var el=e.target,k=el.dataset&&el.dataset.k;if(!k)return;var s=secOf(sec),f=s.fields.filter(function(x){return x.k===k})[0];if(!f)return;
    var v=el.type==='checkbox'?el.checked:f.type==='number'?parseInt(el.value||'0',10):el.value;
    if(el.dataset.i!==undefined)cfg[sec][+el.dataset.i][k]=v;else cfg[sec][k]=v;
    mark();
  }
  function bind(){var r=$('panel-themeconf');if(!r||r.dataset.bound)return;r.dataset.bound='1';r.addEventListener('click',onClick);r.addEventListener('input',onInput);r.addEventListener('change',onInput);
    window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue=''}})}
  window.ThemeConf={refresh:refresh,save:save,open:function(id){bind();load(id)}};
})();
