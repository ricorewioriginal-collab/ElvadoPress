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
    if(!ps.length){h.innerHTML='<div class="card"><div class="th"><div class="tt"><i class="fas fa-image"></i>Bilder &amp; Videos mit KI</div></div><p class="hint">Dafür braucht es einen Schlüssel für <b>EvoLink</b>, <b>fal.ai</b> oder <b>OpenAI</b>. Trage ihn im Reiter „Zentrale“ ein (die Karten von EvoLink, fal.ai und OpenAI stehen ganz oben) und speichere.</p><button class="btn-a" onclick="AiNav.go(\'aicenter\')"><i class="fas fa-brain"></i> Zur Zentrale</button></div>';return}
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

  /* ───── Wiederverwendbar: Bild erzeugen und in die Mediathek übernehmen (Website-Generator, Beitragseditor) ───── */
  var provCache=null;
  function providers(){return provCache||(provCache=api('ai_media_providers').then(function(d){return d.providers||[]}).catch(function(){provCache=null;return []}))}
  /** Auswahl-Liste „Anbieter · Modell“ für Bilder. @returns Promise<[{provider,model,label}]> */
  function imageChoices(){return providers().then(function(ps){var o=[];ps.forEach(function(p){(p.image||[]).forEach(function(m){o.push({provider:p.id,model:m,label:p.label+' · '+m})})});return o})}
  function sleep(ms){return new Promise(function(r){setTimeout(r,ms)})}
  /** Ein Bild erzeugen und in die Mediathek legen. o:{prompt,ratio,provider,model,onProgress}. @returns Promise<{id,url,alt,item}> */
  function generate(o){
    var prompt=String(o.prompt||'').trim();
    return api('ai_media_start',{provider:o.provider,kind:'image',model:o.model||'',prompt:prompt,ratio:o.ratio||'16:9'}).then(function(d){
      if(d.status==='completed')return d;
      var n=0;
      var loop=function(job){return sleep(3500).then(function(){return api('ai_media_status',{job:job})}).then(function(r){
        if(r.status==='completed')return r;if(r.status==='failed')throw new Error(r.error||'Der Auftrag ist fehlgeschlagen.');
        if(++n>100)throw new Error('Das Bild wurde nicht rechtzeitig fertig.');if(o.onProgress)o.onProgress(r.progress||0);return loop(job)})};
      return loop(d.job);
    }).then(function(r){return api('ai_media_save',{kind:'image',url:r.url||'',b64:r.b64||'',prompt:prompt})}).then(function(d){
      var it=d.item||{},v=(it.variants||[]).filter(function(x){return x.width===1024})[0];
      var alt=prompt.replace(/\s+/g,' ').slice(0,125);
      return api('media_alt_save',{id:it.id,alt:alt}).catch(function(){}).then(function(){return {id:it.id,url:v?v.url:(it.original||{}).url,alt:alt,item:it}});
    });
  }
  /** Dialog „Bild mit KI erzeugen“. opts.onPick({url,alt,item}) */
  function dialog(opts){
    imageChoices().then(function(ch){
      if(!ch.length){toast('Dafür ist ein Schlüssel für EvoLink, fal.ai oder OpenAI nötig (Menü „KI“ → Zentrale).',true);return}
      var m=document.createElement('div');m.style.cssText='position:fixed;inset:0;background:rgba(6,6,10,.72);z-index:100001;display:flex;align-items:center;justify-content:center;padding:16px';
      m.innerHTML='<div class="card" style="max-width:560px;width:100%;max-height:92vh;overflow:auto;margin:0"><div class="th"><div class="tt"><i class="fas fa-wand-magic-sparkles"></i>Bild mit KI erzeugen</div><button type="button" class="btn-g" data-x><i class="fas fa-xmark"></i></button></div>'
        +'<label class="news-lbl">Beschreibung</label><textarea class="aib-text" rows="3" data-p placeholder="Was soll zu sehen sein?">'+esc(opts&&opts.prompt||'')+'</textarea>'
        +'<div class="aim-grid" style="margin-top:10px"><label>Modell<select class="fc" data-m>'+ch.map(function(c,i){return '<option value="'+i+'">'+esc(c.label)+'</option>'}).join('')+'</select></label>'
        +'<label>Format<select class="fc" data-r><option>16:9</option><option>1:1</option><option>9:16</option><option>4:3</option><option>3:4</option></select></label></div>'
        +'<div class="aim-bar" data-b hidden><i style="width:10%"></i></div><p class="hint" data-s>Die Anbieter berechnen jedes Bild nach ihrer Preisliste. Das Bild landet mit Alt-Text in der Mediathek.</p>'
        +'<div data-o></div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><button type="button" class="btn-a" data-go><i class="fas fa-wand-magic-sparkles"></i> Erzeugen</button><button type="button" class="btn-a" data-use hidden><i class="fas fa-check"></i> Verwenden</button></div></div>';
      document.body.appendChild(m);var q=function(a){return m.querySelector('['+a+']')},res=null,close=function(){m.remove()};
      q('data-x').onclick=close;m.addEventListener('click',function(e){if(e.target===m)close()});
      q('data-go').onclick=function(){
        var pr=q('data-p').value.trim();if(!pr){toast('Bitte eine Beschreibung eingeben.',true);return}
        var c=ch[+q('data-m').value],go=q('data-go');go.disabled=true;q('data-b').hidden=false;q('data-s').textContent='Wird erzeugt …';q('data-use').hidden=true;
        generate({prompt:pr,ratio:q('data-r').value,provider:c.provider,model:c.model,onProgress:function(p){q('data-b').firstChild.style.width=Math.max(10,p)+'%'}}).then(function(r){
          res=r;q('data-o').innerHTML='<div class="aim-out"><img alt="" src="'+esc(r.url)+'"></div>';q('data-s').textContent='Fertig – in der Mediathek gespeichert.';q('data-use').hidden=false;
        }).catch(function(e){q('data-s').textContent=e.message}).then(function(){go.disabled=false;q('data-b').hidden=true});
      };
      q('data-use').onclick=function(){if(res&&opts&&opts.onPick)opts.onPick(res);close()};
    });
  }
  window.AiMedia={dialog:dialog,generate:generate,imageChoices:imageChoices,open:load,pick:pick,set:set,start:start,save:save,prompt:function(v){st.prompt=v},ref:function(v){st.ref=v},set model(v){st.model=v},get model(){return st.model}};
})();
