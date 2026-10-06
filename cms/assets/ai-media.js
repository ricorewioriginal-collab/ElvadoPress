/* KI-Bilder und -Videos (EvoLink, fal.ai, OpenAI): Beschreibung → Auftrag → Ergebnis → in die Mediathek übernehmen (API: ai_media_providers/start/status/save). Schlüssel stehen in der KI-Zentrale. */
(function(){
  'use strict';
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
  var api=function(a,b){return window.cmsApi(a,b)};
  var toast=function(m,bad){try{window.cmsToast(m,!!bad)}catch(e){}};
  var st={data:null,provider:'',kind:'image',model:'',ratio:'16:9',prompt:'',ref:'',job:null,res:null,saved:null,msg:'',progress:0},timer=null;
  function host(){return document.getElementById('aiMedia')}
  function prov(){return (st.data.providers||[]).filter(function(p){return p.id===st.provider})[0]}
  function models(){var p=prov();return p?(p[st.kind]||[]):[]}
  function modelOpts(){
    var ms=models(),own=st.ownModel||(st.model!==''&&ms.indexOf(st.model)<0);st.ownModel=own;
    return ms.map(function(m){return '<option value="'+esc(m)+'"'+(!own&&m===st.model?' selected':'')+'>'+esc(m)+'</option>'}).join('')+'<option value="__custom__"'+(own?' selected':'')+'>Eigener Modellname …</option>';
  }
  function pick(v){if(v==='__custom__'){st.ownModel=true}else{st.ownModel=false;st.model=v}draw()}
  function load(){
    api('ai_media_providers').then(function(d){
      st.data=d;if(!prov()&&d.providers.length)st.provider=d.providers[0].id;
      if(prov()&&!(prov()[st.kind]||[]).length)st.kind=(prov().image||[]).length?'image':'video';
      if(!st.model||models().indexOf(st.model)<0)st.model=models()[0]||'';draw();
    }).catch(function(e){var h=host();if(h)h.innerHTML='<div class="empty">'+esc(e.message||'Fehler')+'</div>'});
  }
  function draw(){
    var h=host();if(!h||!st.data)return;var ps=st.data.providers||[];
    if(!ps.length){h.innerHTML='<div class="card"><div class="th"><div class="tt"><i class="fas fa-image"></i>Bilder &amp; Videos mit KI</div></div><p class="hint">Dafür braucht es einen Schlüssel für <b>EvoLink</b>, <b>fal.ai</b> oder <b>OpenAI</b>. Trage ihn im Reiter „Zentrale“ ein und schalte den Anbieter ein.</p><button class="btn-a" onclick="AiNav.go(\'aicenter\')"><i class="fas fa-brain"></i> Zur Zentrale</button></div>';return}
    var p=prov(),busy=!!st.job&&!st.res;
    h.innerHTML='<div class="card"><div class="th"><div class="tt"><i class="fas fa-image"></i>Bilder &amp; Videos mit KI</div></div>'
      +'<div class="aim-grid"><label>Anbieter<select class="fc" onchange="AiMedia.set(\'provider\',this.value)">'+ps.map(function(x){return '<option value="'+esc(x.id)+'"'+(x.id===st.provider?' selected':'')+'>'+esc(x.label)+'</option>'}).join('')+'</select></label>'
      +'<label>Art<select class="fc" onchange="AiMedia.set(\'kind\',this.value)"><option value="image"'+(st.kind==='image'?' selected':'')+'>Bild</option><option value="video"'+(st.kind==='video'?' selected':'')+(p&&!(p.video||[]).length?' disabled':'')+'>Video</option></select></label>'
      +'<label>Modell<select class="fc" onchange="AiMedia.pick(this.value)">'+modelOpts()+'</select>'+(st.ownModel?'<input class="fc" style="margin-top:6px" placeholder="Modellname" value="'+esc(st.model)+'" oninput="AiMedia.model=this.value">':'')+'</label>'
      +'<label>Format<select class="fc" onchange="AiMedia.set(\'ratio\',this.value)">'+(st.data.ratios||[]).map(function(r){return '<option'+(r===st.ratio?' selected':'')+'>'+esc(r)+'</option>'}).join('')+'</select></label></div>'
      +'<label class="news-lbl">Beschreibung</label><textarea class="aib-text" rows="4" placeholder="z. B. Moderner Studioraum mit Mikrofon, warmes Licht, fotorealistisch" oninput="AiMedia.prompt(this.value)">'+esc(st.prompt)+'</textarea>'
      +(st.kind==='video'?'<label class="news-lbl" style="margin-top:10px">Startbild-Adresse (optional, https – für „image-to-video“-Modelle)</label><input class="fc" style="width:100%" value="'+esc(st.ref)+'" oninput="AiMedia.ref(this.value)">':'')
      +'<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button class="btn-a" '+(busy?'disabled':'')+' onclick="AiMedia.start()"><i class="fas '+(busy?'fa-spinner fa-spin':'fa-wand-magic-sparkles')+'"></i> '+(busy?'Wird erzeugt …':'Erzeugen')+'</button>'
      +'<span class="hint">Videos dauern oft mehrere Minuten. Die Anbieter berechnen jeden Auftrag nach ihrer Preisliste.</span></div>'
      +(busy?'<div class="aim-bar" style="margin-top:12px"><i style="width:'+Math.max(5,st.progress)+'%"></i></div>':'')
      +(st.msg?'<p class="hint" style="margin-top:10px">'+esc(st.msg)+'</p>':'')
      +(st.res?'<div class="aim-out">'+(st.res.kind==='video'?'<video controls src="'+esc(st.res.url)+'"></video>':'<img alt="" src="'+esc(st.res.url||('data:image/png;base64,'+st.res.b64))+'">')
        +'<div style="display:flex;gap:8px;flex-wrap:wrap">'+(st.saved?'<span class="hint"><i class="fas fa-check"></i> Gespeichert: '+(st.saved.kind==='video'?'<code>'+esc(st.saved.url)+'</code>':'in der Mediathek (Alt-Text dort ergänzen)')+'</span>':'<button class="btn-a" onclick="AiMedia.save()"><i class="fas fa-download"></i> '+(st.res.kind==='video'?'Video speichern':'In die Mediathek übernehmen')+'</button>')+'</div></div>':'')
      +'</div>';
  }
  function set(k,v){st[k]=v;if(k==='provider'||k==='kind'){st.ownModel=false;var p=prov();if(k==='provider'&&p&&!(p[st.kind]||[]).length)st.kind=(p.image||[]).length?'image':'video';st.model=models()[0]||''}draw()}
  function poll(){
    clearTimeout(timer);
    timer=setTimeout(function(){
      if(!st.job)return;
      api('ai_media_status',{job:st.job}).then(function(d){
        if(d.status==='completed'){st.res={kind:st.kind,url:d.url,b64:''};st.msg='';draw();return}
        if(d.status==='failed'){st.job=null;st.msg=d.error||'Der Auftrag ist fehlgeschlagen.';draw();return}
        st.progress=d.progress||Math.min(90,st.progress+4);draw();poll();
      }).catch(function(e){st.job=null;st.msg=e.message;draw()});
    },4000);
  }
  function start(){
    if(!st.prompt.trim()){toast('Bitte eine Beschreibung eingeben.',true);return}
    st.res=null;st.saved=null;st.msg='';st.progress=0;st.job='…';draw();
    api('ai_media_start',{provider:st.provider,kind:st.kind,model:st.model,prompt:st.prompt,ratio:st.ratio,image_url:st.ref}).then(function(d){
      if(d.status==='completed'){st.job=d.job;st.res={kind:st.kind,url:d.url||'',b64:d.b64||''};draw();return}
      st.job=d.job;draw();poll();
    }).catch(function(e){st.job=null;st.msg=e.message;draw()});
  }
  function save(){
    var r=st.res;if(!r)return;
    api('ai_media_save',{kind:r.kind,url:r.url||'',b64:r.b64||'',prompt:st.prompt}).then(function(d){st.saved=d;draw();toast(r.kind==='video'?'Video gespeichert ✓':'In der Mediathek ✓')}).catch(function(e){toast(e.message,true)});
  }
  window.AiMedia={open:load,pick:pick,set:set,start:start,save:save,prompt:function(v){st.prompt=v},ref:function(v){st.ref=v},set model(v){st.model=v},get model(){return st.model}};
})();
