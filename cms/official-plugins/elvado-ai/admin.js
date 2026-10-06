'use strict';
// Elvado AI – Oberfläche: Buttons im Beitragseditor und ein globales window.ElvadoAi für den App-Bereich. Sendet nur, was du auslöst.
(function(){
  if(window.ElvadoAi)return;
  var tok=function(){return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';};
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
  var st=null;
  function call(name,args){
    return fetch('/cms/api.php?action=np_call',{method:'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':tok()},body:JSON.stringify({id:'elvado-ai',call:name,args:args||{}})})
      .then(function(r){return r.json().catch(function(){return{status:'error',message:'Unerwartete Antwort'};});}).then(function(d){if(d.status!=='ok'&&!d.message)d.message='Fehler';return d;});
  }
  function status(){   // das Promise (nicht erst das Ergebnis) merken: sonst startet jede DOM-Änderung bis zur Antwort eine neue Anfrage
    if(!st)st=call('status').then(function(d){if(d.status!=='ok')st=null;return d;},function(e){st=null;throw e;});
    return st;
  }
  function toast(m,err){(window.cmsToast||function(x){alert(x);})(m,!!err);}
  window.ElvadoAi={call:call,status:status,toast:toast};
  function busy(btn,on){btn.disabled=on;btn.dataset.t=btn.dataset.t||btn.innerHTML;btn.innerHTML=on?'<i class="fas fa-spinner fa-spin"></i> KI arbeitet …':btn.dataset.t;}
  function who(d){return d&&d.provider?' (Anbieter: '+d.provider+' · externer Dienst)':'';}
  function guard(){return status().then(function(s){if(!s.usable){toast('Noch kein KI-Anbieter eingerichtet. Bitte in der KI-Zentrale einen Schlüssel eintragen.',true);return false;}return true;});}
  function btn(label,title,fn){var b=document.createElement('button');b.type='button';b.className='btn-g ai-mini';b.title=title;b.innerHTML='<i class="fas fa-wand-magic-sparkles"></i> '+label;b.style.cssText='padding:3px 9px;font-size:.74rem;margin-left:6px';b.onclick=function(e){e.preventDefault();guard().then(function(ok){if(ok)fn(b);});};return b;}
  function bodyText(){try{var b=window.NewsMagazine&&NewsMagazine.bodyApi&&NewsMagazine.bodyApi();var h=b&&b.getHTML?b.getHTML():'';var d=document.createElement('div');d.innerHTML=String(h).replace(/<!--[\s\S]*?-->/g,' ');return d.textContent.replace(/\s+/g,' ').trim();}catch(e){return '';}}
  function val(id){var e=document.getElementById(id);return e?String(e.value||'').trim():'';}
  function setv(id,v){var e=document.getElementById(id);if(e){e.value=v;e.dispatchEvent(new Event('input',{bubbles:true}));}}
  function label(id){var e=document.getElementById(id);return e&&e.previousElementSibling&&e.previousElementSibling.tagName==='LABEL'?e.previousElementSibling:null;}
  function build(){
    status().then(function(s){
      if(!s.editor_tools)return;
      var ex=document.getElementById('newsExcerpt'),t=document.getElementById('newsSeoTitle'),d=document.getElementById('newsSeoDescription');
      if(ex&&!document.getElementById('aiExBtn')){var l=label('newsExcerpt');var b=btn('Zusammenfassen','Teaser aus dem Beitragstext erzeugen',function(bt){busy(bt,true);call('text',{action:'summarize',text:bodyText()||val('newsTitle')}).then(function(r){busy(bt,false);if(!r.ok)return toast(r.message||'Fehler',true);setv('newsExcerpt',r.text);toast('Teaser eingefügt'+who(r));});});b.id='aiExBtn';(l||ex.parentNode).appendChild(b);}
      if(t&&!document.getElementById('aiSeoBtn')){var lt=label('newsSeoTitle');var b2=btn('SEO-Vorschlag','SEO-Titel und Beschreibung vorschlagen',function(bt){busy(bt,true);call('seo',{title:val('newsTitle'),text:bodyText()||val('newsExcerpt')}).then(function(r){busy(bt,false);if(!r.ok)return toast(r.message||'Fehler',true);if(r.title)setv('newsSeoTitle',r.title);if(r.description)setv('newsSeoDescription',r.description);toast('SEO-Vorschlag eingefügt – bitte prüfen'+who(r));});});b2.id='aiSeoBtn';(lt||t.parentNode).appendChild(b2);}
      var host=document.querySelector('#newsSeoTitle');var det=host&&host.closest('details');
      if(det&&!document.getElementById('aiTools')){
        var box=document.createElement('details');box.id='aiTools';box.className='card editor-box np-panel';
        box.innerHTML='<summary><i class="fas fa-wand-magic-sparkles"></i> KI-Textwerkzeuge</summary><p class="hint" style="margin:6px 0">Wirkt auf den Teaser-Text. Die KI ist ein externer Dienst – gesendet wird nur der Text, den du hier auslöst.</p>'
          +'<textarea id="aiToolsOut" class="fc w-100" rows="4" placeholder="Ergebnis erscheint hier – prüfe es, bevor du es übernimmst" readonly></textarea><div class="ap-add" id="aiToolsBtns"></div><div class="ap-add"><button class="btn-g" type="button" id="aiToolsApply" disabled>In Teaser übernehmen</button></div>';
        det.parentNode.insertBefore(box,det.nextSibling);
        var bt=box.querySelector('#aiToolsBtns');
        [['improve','Verbessern'],['shorten','Kürzen'],['expand','Ausbauen'],['proofread','Korrigieren'],['translate','Übersetzen'],['titles','Titel-Ideen']].forEach(function(a){
          var x=document.createElement('button');x.type='button';x.className='btn-g';x.textContent=a[1];x.onclick=function(){guard().then(function(ok){if(!ok)return;var src=a[0]==='titles'?(bodyText()||val('newsExcerpt')):(val('newsExcerpt')||bodyText());busy(x,true);call('text',{action:a[0],text:src}).then(function(r){x.disabled=false;x.textContent=a[1];if(!r.ok)return toast(r.message||'Fehler',true);document.getElementById('aiToolsOut').value=r.text;document.getElementById('aiToolsApply').disabled=a[0]==='titles';document.getElementById('aiToolsOut').readOnly=false;});});};bt.appendChild(x);});
        box.querySelector('#aiToolsApply').onclick=function(){var v=document.getElementById('aiToolsOut').value;if(v){setv('newsExcerpt',v);toast('Teaser übernommen');}};
      }
    });
  }
  var tm=0;new MutationObserver(function(){if(tm)return;tm=setTimeout(function(){tm=0;build();},150);}).observe(document.body,{childList:true,subtree:true});   // gebündelt, damit eigene Änderungen keine Schleife auslösen
  build();
})();
