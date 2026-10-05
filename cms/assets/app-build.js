/* Build-Assistent für eigene Android-Apps (CMS → Apps). Server: app_build_* in cms/api.php, cms/lib/appbuild.php. */
(function(){
  'use strict';
  var st=null,loaded=false,edit=null,timers={},media=null,shots=[],pickFor='icon';
  var TYPES={radio:'Radio-App',web:'Website-App'},PLAT={android:'Android',windows:'Windows'};
  function buildBtns(b){
    var pl=(b.platforms&&b.platforms.length)?b.platforms:['android'];
    return pl.map(function(p,i){return '<button class="'+(i===0?'btn-a':'btn-g')+'" type="button" onclick="AppBuild.start(\''+esc(b.id)+'\',\''+p+'\')"><i class="fas '+(p==='windows'?'fa-windows fab':'fa-android fab')+'"></i> '+(pl.length>1?esc(PLAT[p])+' bauen':'App bauen')+'</button>'}).join('');
  }
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||''}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  async function call(action,body,q){
    var o={headers:{'X-AnMaCha-Token':token()}};if(body!==undefined){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
    var r=await fetch('api.php?action='+action+(q||'')+'&_='+Date.now(),o),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
  }
  function slug(s){return String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'').replace(/^[^a-z]+/,'').slice(0,20)}
  function drawConn(chk){
    var box=$('abConn');if(!box||!st)return;
    box.innerHTML='<div class="hint">So geht\'s: <ol style="margin:6px 0 6px 18px;padding:0;display:grid;gap:3px"><li>Ein neues GitHub-Repository aus der <b>App-Vorlage</b> anlegen (<code>app-template.zip</code> im Release „app-template“ – Anleitung: <a href="docs/APP-BUILDER.md" target="_blank" rel="noopener">Doku</a>; privat ist möglich).</li><li>Unter GitHub → Settings → Developer settings ein <b>Fine-grained Token</b> für genau dieses Repository erzeugen mit „Contents: Read and write“, „Actions: Read and write“ und „Metadata: Read“.</li><li>Repository und Token hier eintragen, speichern und prüfen.</li></ol><span class="hint">Optional für fest signierte Updates: Signatur-Schlüssel als Repository-Secrets hinterlegen (siehe Doku).</span></div>'
      +'<div class="ab-grid"><label>Repository<input class="fc" id="abRepo" placeholder="besitzer/mein-radio-apps" value="'+esc(st.repo)+'"></label><label>Branch für App-Builds<input class="fc" id="abBranch" value="'+esc(st.branch||'app-builder')+'"></label>'
      +'<label style="grid-column:1/-1">GitHub-Token '+(st.has_token?'<span class="hint">(gespeichert: '+esc(st.token_hint)+' – leer lassen, um es zu behalten)</span>':'')+'<input class="fc" id="abToken" type="password" autocomplete="off" placeholder="'+(st.has_token?'unverändert':'github_pat_…')+'"></label></div>'
      +'<div class="ab-row"><button class="btn-a" type="button" onclick="AppBuild.saveConn()"><i class="fas fa-floppy-disk"></i> Speichern</button><button class="btn-g" type="button" onclick="AppBuild.check()"><i class="fas fa-circle-check"></i> Verbindung prüfen</button>'+(st.has_token?'<button class="btn-g" type="button" onclick="AppBuild.clearToken()">Token entfernen</button>':'')+'</div>'
      +(chk?'<ul class="ab-checks">'+chk.checks.map(function(c){return '<li class="'+(c[0]?'ok':'bad')+'"><i class="fas '+(c[0]?'fa-check':'fa-xmark')+'"></i> '+esc(c[1])+'</li>'}).join('')+'</ul>':'');
  }
  function brandForm(b){
    var n=b.id?false:true;
    return '<div class="ab-form"><div class="ab-grid">'
      +'<label>App-Typ<select class="fc" id="abfType" onchange="AppBuild.typeChange()"><option value="radio"'+((b.type||'radio')==='radio'?' selected':'')+'>Radio-App (Sender, Sendeplan, Player …)</option><option value="web"'+(b.type==='web'?' selected':'')+'>Website-App (deine Website als App – für jedes Thema: Shop, Verein, Magazin, Portfolio …)</option></select></label>'
      +'<div><div class="news-lbl">Plattformen</div><div class="ab-row"><label class="ab-chk"><input type="checkbox" id="abfAnd"'+((b.platforms||['android']).indexOf('android')>=0?' checked':'')+'> Android</label><label class="ab-chk"><input type="checkbox" id="abfWin"'+((b.platforms||[]).indexOf('windows')>=0?' checked':'')+'> Windows</label></div></div>'
      +'<label>App-Name<input class="fc" id="abfName" maxlength="30" value="'+esc(b.appName||'')+'" oninput="AppBuild.autoFill()"></label>'
      +'<label>Marken-ID <span class="hint">(intern, nicht änderbar)</span><input class="fc" id="abfId" maxlength="20" value="'+esc(b.id||'')+'" '+(n?'':'readonly')+'></label>'
      +'<label>Paketname<input class="fc" id="abfPkg" placeholder="de.meinefirma.app" value="'+esc(b.applicationId||'')+'"></label>'
      +'<label>Website<input class="fc" id="abfSite" placeholder="https://www.beispiel.de" value="'+esc(b.site||location.origin)+'"></label>'
      +'<label>Dateiname-Anfang<input class="fc" id="abfPrefix" maxlength="30" placeholder="MeineApp" value="'+esc(b.filePrefix||'')+'"></label>'
      +'<label class="ab-chk" id="abfDirRow" data-pack="ricorewi-radio"><input type="checkbox" id="abfDir"'+(b.directory?' checked':'')+'> Radioverzeichnis in der App</label>'
      +'<label id="abfColorRow">Farbe (Statusleiste/Fenster)<input type="color" id="abfColor" value="'+esc(b.themeColor||'#070a1c')+'" data-touched="'+(b.themeColor?1:'')+'" style="width:60px;height:36px;padding:2px" oninput="this.dataset.touched=1"></label>'
      +'<label>Icon-Hintergrund <span class="hint">(optional: Farbe hinter einem transparenten Icon)</span><input type="color" id="abfIconBg" value="'+esc(b.iconBg||'#ffffff')+'" data-set="'+(b.iconBg?1:'')+'" style="width:60px;height:36px;padding:2px" oninput="this.dataset.set=1"></label>'
      +'<div style="grid-column:1/-1"><div class="news-lbl">App-Icon (PNG/JPG/WebP, mindestens 96 px, besser 512 px)</div><div class="ab-icon"><div id="abfPrev">'+(b.icon?'<img src="'+esc(b.icon)+'" alt="">':'<span class="hint">kein Icon</span>')+'</div><button type="button" class="btn-g" onclick="AppBuild.pickIcon()"><i class="fas fa-images"></i> Aus Mediathek wählen</button><button type="button" class="btn-g" onclick="AppBuild.setIcon(\'\')">Entfernen</button></div><input type="hidden" id="abfIcon" value="'+esc(b.icon||'')+'"></div>'
      +'<div style="grid-column:1/-1"><div class="news-lbl">Startbild (optional, wird beim Öffnen der App gezeigt; Querformat oder Hochformat, mindestens 200 px)</div><div class="ab-icon"><div id="abfSplashPrev">'+(b.splash?'<img src="'+esc(b.splash)+'" alt="">':'<span class="hint">kein Startbild</span>')+'</div><button type="button" class="btn-g" onclick="AppBuild.pickImage(\'splash\')"><i class="fas fa-images"></i> Aus Mediathek wählen</button><button type="button" class="btn-g" onclick="AppBuild.setSplash(\'\')">Entfernen</button></div><input type="hidden" id="abfSplash" value="'+esc(b.splash||'')+'"></div>'
      +'<div style="grid-column:1/-1"><div class="news-lbl">Logo in der Kopfzeile der App (optional, z. B. Wortmarke; mindestens 64 px)</div><div class="ab-icon"><div id="abfLogoPrev">'+(b.headerLogo?'<img src="'+esc(b.headerLogo)+'" alt="">':'<span class="hint">kein Kopfzeilen-Logo – das App-Icon wird verwendet</span>')+'</div><button type="button" class="btn-g" onclick="AppBuild.pickImage(\'logo\')"><i class="fas fa-images"></i> Aus Mediathek wählen</button><button type="button" class="btn-g" onclick="AppBuild.setLogo(\'\')">Entfernen</button></div><input type="hidden" id="abfLogo" value="'+esc(b.headerLogo||'')+'"></div>'
      +'<div style="grid-column:1/-1"><div class="news-lbl">Store-Screenshots (optional, bis zu 8 – für Play Store und Microsoft Store)</div><div class="ab-icon"><div id="abfShots"></div><button type="button" class="btn-g" onclick="AppBuild.pickImage(\'shot\')"><i class="fas fa-plus"></i> Screenshot aus Mediathek</button></div></div>'
      +'<label style="grid-column:1/-1">Kurzbeschreibung für den Store <span class="hint">(max. 80 Zeichen)</span><input class="fc" id="abfShort" maxlength="80" value="'+esc(b.shortDescription||'')+'"></label>'
      +'<label style="grid-column:1/-1">Ausführliche Beschreibung für den Store<textarea class="fc" id="abfFull" rows="5" maxlength="4000">'+esc(b.fullDescription||'')+'</textarea></label>'
      +'<div style="grid-column:1/-1"><div id="abPick"></div></div>'
      +'</div><div class="ab-row"><button class="btn-a" type="button" onclick="AppBuild.saveBrand()"><i class="fas fa-floppy-disk"></i> App speichern</button><button class="btn-g" type="button" onclick="AppBuild.cancel()">Abbrechen</button></div></div>';
  }
  function draw(){
    var box=$('abBrands');if(!box||!st)return;
    var h='<div class="ab-head"><b>2 · Meine Apps</b><span class="hint">'+st.brands.length+' von '+st.max_brands+'</span></div>';
    h+=st.brands.map(function(b){return edit===b.id?'<div class="ab-app">'+brandForm(b)+'</div>':'<div class="ab-app" data-b="'+esc(b.id)+'"><div class="ab-top"><div class="ab-ic">'+(b.icon?'<img src="'+esc(b.icon)+'" alt="">':'<i class="fas fa-mobile-screen"></i>')+'</div><div style="flex:1;min-width:0"><b>'+esc(b.appName)+'</b><div class="hint">'+esc(TYPES[b.type||'radio'])+' · '+esc((b.platforms||['android']).map(function(x){return PLAT[x]||x}).join(' + '))+' · '+esc(b.applicationId)+' · '+esc(b.site)+'</div></div>'
      +'<div class="ab-row">'+buildBtns(b)+'<button class="btn-g" type="button" onclick="AppBuild.edit(\''+esc(b.id)+'\')">Bearbeiten</button><button class="btn-g" type="button" onclick="AppBuild.remove(\''+esc(b.id)+'\')" title="Entfernen"><i class="fas fa-trash"></i></button></div></div>'+((b.type||'radio')==='radio'?'<div class="hint"><i class="fas fa-microphone"></i> Sprachsteuerung per Alexa: Skill-Paket und Anleitung im Bereich <a href="#" onclick="cmsTab(\'alexa\',document.querySelector(\'.tab[data-tab=alexa]\'));return false">Alexa-Skill</a>.</div>':'')+'<div class="ab-status" id="abs-'+esc(b.id)+'"></div></div>'}).join('');
    if(edit==='new')h+='<div class="ab-app">'+brandForm({})+'</div>';
    else if(st.brands.length<st.max_brands)h+='<button class="btn-g" type="button" onclick="AppBuild.edit(\'new\')"><i class="fas fa-plus"></i> Neue App</button>';
    box.innerHTML=h;
    st.brands.forEach(function(b){refresh(b.id)});
  }
  function when(iso){var d=new Date(iso);return isNaN(d)?'':d.toLocaleString('de-DE',{dateStyle:'short',timeStyle:'short'})}
  function size(n){return n>1048576?(n/1048576).toFixed(1)+' MB':Math.round(n/1024)+' KB'}
  async function refresh(id){
    var box=$('abs-'+id);if(!box||!st.has_token||!st.repo)return;clearTimeout(timers[id]);
    try{
      var d=await call('app_build_status',undefined,'&brand='+encodeURIComponent(id)),r=d.runs[0],h='';box=$('abs-'+id);if(!box)return;
      d.runs.slice(0,6).forEach(function(r,ri){var run=r.status!=='completed';if(ri>=3&&!run)return;h+='<div class="ab-run '+(run?'run':r.conclusion==='success'?'ok':'bad')+'"><i class="fas '+(run?'fa-spinner fa-spin':r.conclusion==='success'?'fa-check':'fa-triangle-exclamation')+'"></i> '+esc(PLAT[r.platform]||'')+': '+(run?'Build läuft … ('+esc(when(r.created))+')':r.conclusion==='success'?'Build erfolgreich ('+esc(when(r.created))+')':'Build fehlgeschlagen ('+esc(when(r.created))+')')+' <a href="'+esc(r.url)+'" target="_blank" rel="noopener">Protokoll</a></div>';if(run){clearTimeout(timers[id]);timers[id]=setTimeout(function(){refresh(id)},15000)}});
      if(d.files.length)h+='<div class="ab-files">'+d.files.map(function(f){return '<a class="btn-g" href="api.php?action=app_build_download&brand='+encodeURIComponent(id)+'&asset='+f.asset+'&_tok='+encodeURIComponent(token())+'"><i class="fas fa-download"></i> '+esc(PLAT[f.platform]||'')+' · '+esc(f.name)+' <span class="hint">'+size(f.size)+' · '+esc(when(f.created))+'</span></a>'}).join('')+'</div><div class="hint">Android: zum Testen auf dem Handy installieren („Unbekannte Quellen“ erlauben); für den Play Store brauchst du eine Release-Signatur. Windows: der Installer ist nicht signiert, SmartScreen kann warnen. Mehr in der Anleitung.</div>';
      else if(!d.runs.length)h+='<div class="hint">Noch kein Build.</div>';
      box.innerHTML=h;
    }catch(e){if(box)box.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  async function load(force){
    if(loaded&&!force)return;loaded=true;
    try{st=await call('app_build_state');drawConn();draw()}catch(e){loaded=false;if($('abConn'))$('abConn').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  async function saveConn(){
    try{st=await call('app_build_save',{repo:$('abRepo').value.trim(),branch:$('abBranch').value.trim(),token:$('abToken').value.trim()});drawConn();draw();toast('Gespeichert ✓')}catch(e){toast(e.message,true)}
  }
  async function clearToken(){if(!confirm('Gespeichertes GitHub-Token entfernen?'))return;try{st=await call('app_build_save',{repo:st.repo,branch:st.branch,clear_token:true});drawConn();draw()}catch(e){toast(e.message,true)}}
  async function check(){
    var box=$('abConn');try{var c=await call('app_build_check',{});drawConn(c)}catch(e){toast(e.message,true)}
  }
  function autoFill(){
    var id=$('abfId');if(id&&!id.readOnly&&!id.dataset.touched){id.value=slug($('abfName').value)}
    var p=$('abfPrefix');if(p&&!p.dataset.touched)p.value=($('abfName').value||'').replace(/[^A-Za-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,30);
  }
  async function saveBrand(){
    var pl=[];if($('abfAnd').checked)pl.push('android');if($('abfWin').checked)pl.push('windows');
    var ty=$('abfType').value,body={id:$('abfId').value.trim(),appName:$('abfName').value.trim(),applicationId:$('abfPkg').value.trim(),site:$('abfSite').value.trim(),filePrefix:$('abfPrefix').value.trim(),directory:ty==='radio'&&$('abfDir').checked,icon:$('abfIcon').value,type:ty,platforms:pl,themeColor:(ty==='web'||$('abfColor').dataset.touched)?$('abfColor').value:'',
      splash:$('abfSplash').value,headerLogo:$('abfLogo').value,iconBg:$('abfIconBg').dataset.set?$('abfIconBg').value:'',screenshots:shots.slice(),shortDescription:$('abfShort').value.trim(),fullDescription:$('abfFull').value.trim()};
    try{st=await call('app_build_brand_save',body);edit=null;draw();toast('App gespeichert ✓')}catch(e){toast(e.message,true)}
  }
  async function start(id,platform){
    if(!st.has_token||!st.repo){toast('Bitte zuerst die Verbindung zu GitHub einrichten (Schritt 1).',true);return}
    if(!confirm('Build starten? Das CMS schreibt die App-Konfiguration in den Branch „'+st.branch+'“ deines Repositories und startet den Build.'))return;
    try{var r=await call('app_build_start',{id:id,platform:platform||'android'});toast(r.message);setTimeout(function(){refresh(id)},4000)}catch(e){toast(e.message,true)}
  }
  async function remove(id){
    if(!confirm('App „'+id+'“ aus dem CMS entfernen? (Bereits gebaute Pakete im Repository bleiben bestehen.)'))return;
    try{st=await call('app_build_brand_delete',{id:id});draw()}catch(e){toast(e.message,true)}
  }
  function setIcon(url){var i=$('abfIcon');if(!i)return;i.value=url;$('abfPrev').innerHTML=url?'<img src="'+esc(url)+'" alt="">':'<span class="hint">kein Icon</span>';var p=$('abPick');if(p)p.innerHTML=''}
  function setSplash(url){var i=$('abfSplash');if(!i)return;i.value=url;$('abfSplashPrev').innerHTML=url?'<img src="'+esc(url)+'" alt="">':'<span class="hint">kein Startbild</span>';var p=$('abPick');if(p)p.innerHTML=''}
  function setLogo(url){var i=$('abfLogo');if(!i)return;i.value=url;$('abfLogoPrev').innerHTML=url?'<img src="'+esc(url)+'" alt="">':'<span class="hint">kein Kopfzeilen-Logo – das App-Icon wird verwendet</span>';var p=$('abPick');if(p)p.innerHTML=''}
  function drawShots(){var h=$('abfShots');if(!h)return;h.innerHTML=shots.length?shots.map(function(u,i){return '<span class="ab-shot"><img src="'+esc(u)+'" alt="" style="height:64px"><button type="button" class="btn-g" title="Entfernen" onclick="AppBuild.delShot('+i+')"><i class="fas fa-xmark"></i></button></span>'}).join(' '):'<span class="hint">keine Screenshots</span>'}
  function addShot(url){if(shots.length>=8){toast('Höchstens 8 Screenshots',true);return}if(shots.indexOf(url)<0)shots.push(url);drawShots();var p=$('abPick');if(p)p.innerHTML=''}
  function choose(url){if(pickFor==='splash')setSplash(url);else if(pickFor==='logo')setLogo(url);else if(pickFor==='shot')addShot(url);else setIcon(url)}
  async function pickImage(what){
    pickFor=what||'icon';var box=$('abPick');box.innerHTML='<div class="hint">Lädt …</div>';
    try{
      if(!media){var l=await call('media_library_list');media=(l.items||[]).filter(function(i){return i.url&&/^\/cms\/media\//.test(i.url)&&(/^image\//.test(i.mime||'')||/\.(png|jpe?g|webp)$/i.test(i.url))})}
      box.innerHTML=media.length?'<div class="ab-pick">'+media.slice(0,60).map(function(i){return '<button type="button" onclick="AppBuild.choose(\''+esc(i.url).replace(/\\/g,'')+'\')" title="'+esc(i.name)+'"><img loading="lazy" src="'+esc(i.url)+'" alt=""></button>'}).join('')+'</div>':'<div class="hint">Keine Bilder in der Medienbibliothek.</div>';
    }catch(e){box.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  document.addEventListener('input',function(e){if(e.target&&(e.target.id==='abfId'||e.target.id==='abfPrefix'))e.target.dataset.touched='1'});
  window.AppBuild={load:load,saveConn:saveConn,clearToken:clearToken,check:check,saveBrand:saveBrand,start:start,remove:remove,autoFill:autoFill,setIcon:setIcon,pickIcon:function(){return pickImage('icon')},setSplash:setSplash,setLogo:setLogo,pickImage:pickImage,choose:choose,delShot:function(i){shots.splice(i,1);drawShots()},
    typeChange:function(){var web=$('abfType').value==='web';$('abfDirRow').hidden=web},
    edit:function(id){edit=id;var cur=(st.brands||[]).filter(function(x){return x.id===id})[0];shots=((cur&&cur.screenshots)||[]).slice();draw();if($('abfType')){AppBuild.typeChange();drawShots()}},cancel:function(){edit=null;draw()}};
})();
