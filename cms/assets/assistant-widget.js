/* Chat-Fenster des KI-Assistenten für die öffentliche Website (eigenständiges CMS). Wird per wp_footer eingebunden (cms/wp/core/ext/assistant-widget.php).
   Einstellungen kommen als data-Attribute des Script-Tags; Fragen gehen an die öffentliche Aktion assistant_chat. Kein externes Skript, keine Cookies. */
(function(){
  'use strict';
  var s=document.currentScript;if(!s||document.getElementById('elvado-ai'))return;
  var endpoint=s.getAttribute('data-endpoint')||'/cms/api.php?action=assistant_chat',name=s.getAttribute('data-name')||'Assistent',
      greeting=s.getAttribute('data-greeting')||'',privacy=s.getAttribute('data-privacy')||'';
  var KEY='elvado_ai_chat',msgs=[],busy=false;
  function load(){try{var d=JSON.parse(sessionStorage.getItem(KEY)||'[]');if(Array.isArray(d))msgs=d.filter(function(m){return m&&(m.role==='user'||m.role==='assistant')&&typeof m.content==='string'}).slice(-30)}catch(e){}}
  function save(){try{sessionStorage.setItem(KEY,JSON.stringify(msgs.slice(-30)))}catch(e){}}
  function esc(t){return String(t).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function fmt(t){ // Text → sicheres HTML: **fett**, https-Links, Zeilenumbrüche
    return esc(t).replace(/\*\*([^*\n]+)\*\*/g,'<b>$1</b>').replace(/(https:\/\/[^\s<)]+[^\s<).,;:!?])/g,'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g,'<br>');
  }
  var css='#elvado-ai{--ai-bg:#fff;--ai-fg:#1d2230;--ai-mut:#6a7285;--ai-line:#dde1ea;--ai-acc:#2563eb;--ai-accfg:#fff;--ai-bub:#eef1f7;position:fixed;right:18px;bottom:18px;z-index:99999;font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--ai-fg)}'
   +'@media(prefers-color-scheme:dark){#elvado-ai{--ai-bg:#171b26;--ai-fg:#e8ebf3;--ai-mut:#98a1b5;--ai-line:#2c3345;--ai-acc:#5b8cff;--ai-accfg:#0c1020;--ai-bub:#232a3b}}'
   +'#elvado-ai *{box-sizing:border-box}#elvado-ai-btn{width:56px;height:56px;border-radius:50%;border:0;background:var(--ai-acc);color:var(--ai-accfg);cursor:pointer;box-shadow:0 6px 22px rgba(0,0,0,.28);display:flex;align-items:center;justify-content:center}'
   +'#elvado-ai-btn:focus-visible,#elvado-ai button:focus-visible,#elvado-ai textarea:focus-visible{outline:3px solid var(--ai-acc);outline-offset:2px}'
   +'#elvado-ai-btn svg{width:26px;height:26px}#elvado-ai-box{display:none;position:absolute;right:0;bottom:70px;width:min(380px,calc(100vw - 28px));height:min(540px,calc(100vh - 110px));background:var(--ai-bg);border:1px solid var(--ai-line);border-radius:16px;box-shadow:0 14px 44px rgba(0,0,0,.3);flex-direction:column;overflow:hidden}'
   +'#elvado-ai.open #elvado-ai-box{display:flex}#elvado-ai-head{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--ai-line);font-weight:700}#elvado-ai-head span{flex:1}'
   +'#elvado-ai-head button{border:0;background:none;color:var(--ai-mut);cursor:pointer;font-size:20px;line-height:1;padding:2px 6px}#elvado-ai-log{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:8px}'
   +'.elvado-ai-m{max-width:88%;padding:8px 12px;border-radius:14px;background:var(--ai-bub);word-wrap:break-word;overflow-wrap:anywhere}.elvado-ai-m.u{align-self:flex-end;background:var(--ai-acc);color:var(--ai-accfg)}.elvado-ai-m a{color:inherit;text-decoration:underline}'
   +'.elvado-ai-c{align-self:flex-start;max-width:88%;font-size:.86em;color:var(--ai-mut)}.elvado-ai-c a{color:var(--ai-acc)}#elvado-ai-priv{padding:0 14px 6px;font-size:.74em;color:var(--ai-mut)}'
   +'#elvado-ai-form{display:flex;gap:8px;padding:10px;border-top:1px solid var(--ai-line)}#elvado-ai-in{flex:1;resize:none;border:1px solid var(--ai-line);border-radius:10px;padding:8px 10px;font:inherit;background:transparent;color:inherit;max-height:96px}'
   +'#elvado-ai-send{border:0;border-radius:10px;background:var(--ai-acc);color:var(--ai-accfg);padding:0 14px;font-weight:700;cursor:pointer}#elvado-ai-send[disabled]{opacity:.5;cursor:default}'
   +'@media(prefers-reduced-motion:no-preference){#elvado-ai.open #elvado-ai-box{animation:elvadoai .16s ease-out}@keyframes elvadoai{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}}';
  var st=document.createElement('style');st.textContent=css;document.head.appendChild(st);
  var root=document.createElement('div');root.id='elvado-ai';
  root.innerHTML='<div id="elvado-ai-box" role="dialog" aria-label="'+esc(name)+'"><div id="elvado-ai-head"><span>'+esc(name)+'</span><button type="button" id="elvado-ai-x" aria-label="Chat schließen">×</button></div>'
   +'<div id="elvado-ai-log" role="log" aria-live="polite"></div>'+(privacy?'<div id="elvado-ai-priv">'+esc(privacy)+'</div>':'')
   +'<form id="elvado-ai-form"><textarea id="elvado-ai-in" rows="1" maxlength="1500" placeholder="Frage stellen …" aria-label="Deine Frage"></textarea><button id="elvado-ai-send" type="submit">Senden</button></form></div>'
   +'<button type="button" id="elvado-ai-btn" aria-label="'+esc(name)+' öffnen" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg></button>';
  document.body.appendChild(root);
  var log=root.querySelector('#elvado-ai-log'),inp=root.querySelector('#elvado-ai-in'),send=root.querySelector('#elvado-ai-send'),btn=root.querySelector('#elvado-ai-btn');
  function bubble(role,text){var d=document.createElement('div');d.className='elvado-ai-m'+(role==='user'?' u':'');d.innerHTML=fmt(text);log.appendChild(d);log.scrollTop=log.scrollHeight;return d}
  function cards(list){(list||[]).forEach(function(c){if(c.type==='pages'&&c.items&&c.items.length){var d=document.createElement('div');d.className='elvado-ai-c';
      d.innerHTML='Mehr dazu: '+c.items.filter(function(i){return /^https:\/\//.test(i.url||'')||/^\//.test(i.url||'')}).map(function(i){return '<a href="'+esc(i.url)+'">'+esc(i.title)+'</a>'}).join(' · ');if(d.querySelector('a'))log.appendChild(d)}});log.scrollTop=log.scrollHeight}
  function draw(){log.innerHTML='';if(greeting)bubble('assistant',greeting);msgs.forEach(function(m){bubble(m.role,m.content)})}
  function open(on){root.classList.toggle('open',on);btn.setAttribute('aria-expanded',on?'true':'false');if(on){draw();setTimeout(function(){inp.focus()},30)}else btn.focus()}
  btn.addEventListener('click',function(){open(!root.classList.contains('open'))});
  root.querySelector('#elvado-ai-x').addEventListener('click',function(){open(false)});
  root.addEventListener('keydown',function(e){if(e.key==='Escape'&&root.classList.contains('open'))open(false)});
  inp.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();root.querySelector('#elvado-ai-form').requestSubmit?root.querySelector('#elvado-ai-form').requestSubmit():send.click()}});
  root.querySelector('#elvado-ai-form').addEventListener('submit',function(e){
    e.preventDefault();var q=inp.value.trim();if(!q||busy)return;inp.value='';busy=true;send.disabled=true;
    msgs.push({role:'user',content:q});bubble('user',q);var wait=bubble('assistant','…');save();
    fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:msgs.slice(-8)})})
      .then(function(r){return r.json().catch(function(){return {}}).then(function(d){return {ok:r.ok,d:d}})})
      .then(function(x){var t=x.ok&&x.d&&x.d.reply?x.d.reply:(x.d&&x.d.message)||'Das hat leider nicht geklappt. Bitte versuche es später noch einmal.';
        wait.innerHTML=fmt(t);if(x.ok&&x.d&&x.d.reply){msgs.push({role:'assistant',content:t});save();cards(x.d.cards)}})
      .catch(function(){wait.innerHTML=fmt('Keine Verbindung. Bitte versuche es später noch einmal.')})
      .then(function(){busy=false;send.disabled=false;log.scrollTop=log.scrollHeight;inp.focus()});
  });
  load();
})();
