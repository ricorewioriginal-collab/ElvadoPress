'use strict';
/* Einstellungen → Mehrsprachige Website (API ml_get/ml_save, lib/multilang.php) */
window.MlSettings=(()=>{
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let S=null;
 async function load(){const b=document.getElementById('mlBody');if(!b)return;try{S=await cmsApi('ml_get');draw()}catch(e){b.innerHTML='<div class="danger-note">'+esc(e.message)+'</div>'}}
 function draw(){
  const b=document.getElementById('mlBody');if(!b||!S)return;const c=S.config,av=S.available||{};
  b.innerHTML='<label style="display:flex;gap:8px;align-items:center;cursor:pointer;margin:6px 0"><input type="checkbox" id="mlOn"'+(c.enabled?' checked':'')+'> Mehrsprachigkeit einschalten</label>'
   +'<div class="news-lbl" style="margin-top:8px">Sprachen der Website</div><div style="display:flex;flex-wrap:wrap;gap:10px;margin:4px 0 8px">'
   +Object.keys(av).map(l=>'<label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" data-ml-lang="'+l+'"'+(c.languages.includes(l)?' checked':'')+(l===c.default?' disabled':'')+'> '+esc(av[l])+(l===c.default?' <span class="hint">(Standard)</span>':'')+'</label>').join('')+'</div>'
   +'<label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="checkbox" id="mlSwitch"'+(c.switcher?' checked':'')+'> Sprachumschalter unten rechts auf der Website anzeigen</label>'
   +'<p class="hint" style="margin-top:8px">Die Standardsprache ist die Sprache aus der Einrichtung. Texte des Themes (z. B. Menübeschriftungen) übersetzt du in den Theme-Einstellungen; liefert echtes WordPress die Website aus, gelten dort nur die Beiträge der Standardsprache.</p>'
   +'<div class="ap-add"><button class="btn-a" type="button" onclick="MlSettings.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>';
 }
 async function save(){
  const langs=[...document.querySelectorAll('[data-ml-lang]')].filter(i=>i.checked).map(i=>i.dataset.mlLang);
  try{S=await cmsApi('ml_save',{enabled:document.getElementById('mlOn').checked,languages:langs,switcher:document.getElementById('mlSwitch').checked});draw();(window.cmsToast||alert)(S.config.enabled?'Mehrsprachigkeit gespeichert ✓':'Gespeichert – Mehrsprachigkeit ist aus');if(window.NewsMagazine&&NewsMagazine.reload)NewsMagazine.reload()}catch(e){(window.cmsToast||alert)(e.message,true)}
 }
 document.addEventListener('click',e=>{const t=e.target.closest&&e.target.closest('.tab[data-tab="settings"]');if(t)setTimeout(load,50)});
 return {load,save};
})();
