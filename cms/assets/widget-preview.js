'use strict';
const q=new URLSearchParams(location.search),id=q.get('id')||'';
const root=document.getElementById('preview');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let CFG={};
async function loadCfg(){const r=await fetch('/cms/api.php?action=public&_='+Date.now(),{cache:'no-store'});const d=await r.json();CFG=d.config||{};render();}
function widget(){
 try{
   const raw=sessionStorage.getItem('rrw_widget_preview_'+id);
   if(raw){const draft=JSON.parse(raw);if(draft&&draft.id===id&&draft.enabled!==false)return draft;}
 }catch(e){}
 return (CFG.widgets||[]).find(x=>x.id===id&&x.enabled!==false)
}
function profiles(){return CFG.social||{}}
function socialInfo(b){const m={'tiktok-ricorewi':['tiktok','ricorewi'],'tiktok-anmacha':['tiktok','anmacha'],'instagram-ricorewi':['instagram','ricorewi'],'instagram-anmacha':['instagram','anmacha']};return m[b]||null}
function handle(platform,creator){
 const s=profiles();
 if(creator==='ricorewi')return platform==='tiktok'?(s.ricorewi_tiktok||'ricorewi'):(s.ricorewi_instagram||'ricorewi_official');
 return platform==='tiktok'?(s.anmacha_tiktok||'anmacha.radioproduktion'):(s.anmacha_instagram||'dieanmacha');
}
function socialMarkup(platform,creator,title=''){
 const h=handle(platform,creator);
 const body=platform==='tiktok'
   ? '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@'+esc(h)+'" data-unique-id="'+esc(h)+'" data-embed-type="creator" style="max-width:720px;min-width:288px"><section></section></blockquote>'
   : '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/'+esc(h)+'/" data-instgrm-version="14" style="max-width:540px;width:100%;min-width:288px"></blockquote>';
 return '<section class="widget"><h3>'+esc(title||('@'+h))+'</h3><div class="social-card"><div class="social-head"><div class="social-icon">'+(platform==='tiktok'?'♪':'◎')+'</div><div><b>@'+esc(h)+'</b><div style="color:var(--dim);font-size:.78rem">'+(platform==='tiktok'?'TikTok':'Instagram')+'</div></div></div><div class="social-stage">'+body+'</div></div></section>';
}
function loadSocialScripts(){
 if(document.querySelector('.tiktok-embed')){const s=document.createElement('script');s.src='https://www.tiktok.com/embed.js';s.async=true;document.body.appendChild(s)}
 if(document.querySelector('.instagram-media')){const s=document.createElement('script');s.src='https://www.instagram.com/embed.js';s.async=true;document.body.appendChild(s)}
}
function render(){
 const w=widget();if(!w){root.innerHTML='<div class="empty">Widget nicht gefunden oder deaktiviert.</div>';return}
 const title=w.title||w.name||'Widget',single=socialInfo(w.builtin);
 if(single){root.innerHTML=socialMarkup(single[0],single[1],title);loadSocialScripts();return}
 if(w.builtin==='social-wall'){
   root.innerHTML='<div class="social-wall">'+socialMarkup('tiktok','ricorewi','TikTok · RicoReWi')+socialMarkup('tiktok','anmacha','TikTok · AnMaCha')+socialMarkup('instagram','ricorewi','Instagram · RicoReWi')+socialMarkup('instagram','anmacha','Instagram · AnMaCha')+'</div>';loadSocialScripts();return
 }
 if(w.type==='html'){root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3>'+String(w.html||'')+'</section>';return}
 if(w.type==='iframe'){root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3><iframe class="frame" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" src="'+esc(w.url||'about:blank')+'"></iframe></section>';return}
 const labels={
  'stations':['Senderübersicht','/#sender'],'schedule':['Sendeplan','/#sendeplan'],'podcast':['Podcast','/#podcast'],'news-latest':['Aktuelle News','/#news'],
  'random-station':['Überrasch mich','/'],'favorites':['Meine Favoriten','/'],'voting':['Netzwerk-Voting','/#voting'],'song-voting':['Song-Voting','/#voting'],
  'studiomail':['Studiomail','/'],'voicemail':['Voicemail','/'],'wunsch':['Musikwunsch','/'],'poll':['Umfrage','/']
 };
 const x=labels[w.builtin]||[title,'/'];root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3><p>Live-Vorschau für <b>'+esc(x[0])+'</b>. Die Funktion wird im Portal mit der bestehenden Radio-/AnMaCha-Logik ausgeführt.</p><a class="btn" href="'+esc(x[1])+'" target="_blank" rel="noopener">Im Portal öffnen</a></section>';
}
loadCfg().catch(e=>root.innerHTML='<div class="empty">Vorschau konnte nicht geladen werden.</div>');