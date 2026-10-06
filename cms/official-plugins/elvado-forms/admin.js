'use strict';
// Elvado Forms – Formular-Builder in der Plugin-Verwaltung. Wird von der Plugin-Seite geladen und mit (Container, API) aufgerufen.
(function(){
  window.ElvadoPluginPages=window.ElvadoPluginPages||{pages:{},register:function(id,fn){this.pages[id]=fn;}};
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
  ElvadoPluginPages.register('elvado-forms',function(root,api){
    var st={forms:[],types:{},uploads:false,edit:null,subs:null};
    function slug(s){return String(s||'').toLowerCase().replace(/[äöüß]/g,function(c){return {'ä':'ae','ö':'oe','ü':'ue','ß':'ss'}[c];}).replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,38);}
    function fid(s){return String(s||'').toLowerCase().replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'').replace(/^([0-9])/,'f$1').slice(0,30);}
    async function load(){var r=await api.call('list_forms');st.forms=r.forms||[];st.types=r.types||{};st.uploads=!!r.uploads;draw();}
    function blank(){return {id:'',title:'',fields:[{id:'name',type:'text',label:'Name',required:true},{id:'email',type:'email',label:'E-Mail',required:true},{id:'nachricht',type:'textarea',label:'Nachricht',required:true}],submit_label:'Absenden',success_message:'Danke! Deine Nachricht wurde gesendet.',redirect_url:'',store:true,retention_days:90,notify:{enabled:true,to:'',subject:'Neue Nachricht: {title}',reply_to_field:'email'},consent:{enabled:true,text:'',link:''},spam:{honeypot:true,min_seconds:3,rate_limit:5,captcha:false},webhook:{url:'',secret:''},_new:true};}
    function list(){
      var h='<div class="ap-add" style="margin-bottom:10px"><button class="btn-a" type="button" data-a="new"><i class="fas fa-plus"></i> Neues Formular</button></div>';
      if(!st.forms.length)return h+'<p class="hint">Noch kein Formular. Lege eins an und füge es mit dem Shortcode in eine Seite oder einen Beitrag ein.</p>';
      h+='<div class="dm-list">'+st.forms.map(function(f){return '<div class="dm-row"><div class="dm-head"><b>'+esc(f.title)+'</b><span class="dm-pill grey">'+f.fields.length+' Felder</span>'+(f.store?'<span class="dm-pill">'+f.count+' Einsendungen</span>':'<span class="dm-pill grey">keine Speicherung</span>')+'</div>'
        +'<div class="dm-meta">Shortcode: <code>'+esc(f.shortcode)+'</code> <button class="btn-g" type="button" data-a="copy" data-i="'+esc(f.id)+'"><i class="fas fa-copy"></i> Kopieren</button></div>'
        +'<div class="ap-add"><button class="btn-g" type="button" data-a="edit" data-i="'+esc(f.id)+'">Bearbeiten</button>'+(f.store?'<button class="btn-g" type="button" data-a="subs" data-i="'+esc(f.id)+'">Einsendungen</button>':'')+'<button class="btn-g" type="button" data-a="del" data-i="'+esc(f.id)+'"><i class="fas fa-trash"></i></button></div></div>';}).join('')+'</div>';
      return h;
    }
    function fieldRow(f,i,n){
      var opts=(f.type==='select'||f.type==='radio'||f.type==='checkbox');
      var types=Object.keys(st.types).filter(function(t){return t!=='file'||st.uploads;}).map(function(t){return '<option value="'+t+'"'+(f.type===t?' selected':'')+'>'+esc(st.types[t])+'</option>';}).join('');
      return '<div class="dm-row" data-f="'+i+'"><div class="ap-tab"><select class="fc" data-k="type">'+types+'</select><input class="fc" data-k="label" placeholder="Beschriftung" value="'+esc(f.label||'')+'"><input class="fc" data-k="id" placeholder="Kennung" value="'+esc(f.id||'')+'" style="max-width:150px">'
        +'<label class="ap-check"><input type="checkbox" data-k="required"'+(f.required?' checked':'')+'> Pflicht</label>'
        +'<button class="btn-g" type="button" data-a="up" '+(i?'':'disabled')+'>↑</button><button class="btn-g" type="button" data-a="dn" '+(i<n-1?'':'disabled')+'>↓</button><button class="btn-g" type="button" data-a="rm"><i class="fas fa-trash"></i></button></div>'
        +(opts?'<textarea class="fc w-100" rows="3" data-k="options" placeholder="Eine Option pro Zeile'+(f.type==='checkbox'?' (leer = einzelne Checkbox)':'')+'">'+esc((f.options||[]).join('\n'))+'</textarea>':'')
        +(f.type!=='checkbox'&&f.type!=='file'&&f.type!=='select'&&f.type!=='radio'?'<input class="fc w-100" data-k="placeholder" placeholder="Platzhalter (optional)" value="'+esc(f.placeholder||'')+'">':'')
        +'<input class="fc w-100" data-k="help" placeholder="Hilfetext unter dem Feld (optional)" value="'+esc(f.help||'')+'"></div>';
    }
    function editor(){
      var f=st.edit,n=f.fields.length;
      return '<div class="card" style="margin:0"><div class="ap-sub">'+(f._new?'Neues Formular':'Formular bearbeiten')+'</div><div class="ap-grid">'
        +'<label class="news-lbl">Titel<input class="fc w-100" data-g="title" value="'+esc(f.title)+'"></label>'
        +'<label class="news-lbl">Kennung (für den Shortcode)<input class="fc w-100" data-g="id" value="'+esc(f.id)+'" '+(f._new?'':'readonly')+' placeholder="kontakt"></label></div>'
        +'<div class="ap-sub">Felder</div>'+f.fields.map(function(x,i){return fieldRow(x,i,n);}).join('')
        +'<div class="ap-add"><button class="btn-g" type="button" data-a="addf"><i class="fas fa-plus"></i> Feld hinzufügen</button></div>'
        +'<div class="ap-sub">Nach dem Absenden</div><div class="ap-grid"><label class="news-lbl">Beschriftung des Knopfs<input class="fc w-100" data-g="submit_label" value="'+esc(f.submit_label)+'"></label>'
        +'<label class="news-lbl">Erfolgsmeldung<input class="fc w-100" data-g="success_message" value="'+esc(f.success_message)+'"></label>'
        +'<label class="news-lbl">Danach weiterleiten (optional)<input class="fc w-100" data-g="redirect_url" value="'+esc(f.redirect_url)+'" placeholder="/danke/"></label></div>'
        +'<div class="ap-sub">E-Mail und Speicherung</div>'
        +'<label class="ap-check"><input type="checkbox" data-g="notify.enabled"'+(f.notify.enabled?' checked':'')+'> Benachrichtigung per E-Mail senden</label>'
        +'<div class="ap-grid"><label class="news-lbl">Empfänger (leer = Standard-Empfänger der Plugin-Einstellungen)<input class="fc w-100" data-g="notify.to" value="'+esc(f.notify.to)+'"></label>'
        +'<label class="news-lbl">Betreff ({title} = Formulartitel)<input class="fc w-100" data-g="notify.subject" value="'+esc(f.notify.subject)+'"></label>'
        +'<label class="news-lbl">Antworten an das Feld<select class="fc w-100" data-g="notify.reply_to_field"><option value="">– keines –</option>'+f.fields.filter(function(x){return x.type==='email';}).map(function(x){return '<option value="'+esc(x.id)+'"'+(f.notify.reply_to_field===x.id?' selected':'')+'>'+esc(x.label)+'</option>';}).join('')+'</select></label></div>'
        +'<label class="ap-check"><input type="checkbox" data-g="store"'+(f.store?' checked':'')+'> Einsendungen im CMS speichern</label>'
        +'<label class="news-lbl">Aufbewahrung (Tage, danach automatisch gelöscht)<input class="fc" type="number" min="1" max="3650" data-g="retention_days" value="'+esc(f.retention_days)+'"></label>'
        +'<div class="ap-sub">Datenschutz</div><label class="ap-check"><input type="checkbox" data-g="consent.enabled"'+(f.consent.enabled?' checked':'')+'> Einwilligungs-Checkbox anzeigen (Pflicht)</label>'
        +'<div class="ap-grid"><label class="news-lbl">Text der Einwilligung<input class="fc w-100" data-g="consent.text" value="'+esc(f.consent.text)+'" placeholder="Standardtext"></label><label class="news-lbl">Link zur Datenschutzerklärung<input class="fc w-100" data-g="consent.link" value="'+esc(f.consent.link)+'" placeholder="/datenschutz/"></label></div>'
        +'<div class="ap-sub">Spam-Schutz</div><label class="ap-check"><input type="checkbox" data-g="spam.honeypot"'+(f.spam.honeypot?' checked':'')+'> Honeypot-Feld</label>'
        +'<label class="ap-check"><input type="checkbox" data-g="spam.captcha"'+(f.spam.captcha?' checked':'')+'> Rechenfrage</label>'
        +'<div class="ap-grid"><label class="news-lbl">Mindestzeit bis zum Absenden (Sekunden)<input class="fc" type="number" min="0" max="60" data-g="spam.min_seconds" value="'+esc(f.spam.min_seconds)+'"></label><label class="news-lbl">Einsendungen je Besucher in 10 Minuten<input class="fc" type="number" min="1" max="100" data-g="spam.rate_limit" value="'+esc(f.spam.rate_limit)+'"></label></div>'
        +'<div class="ap-sub">Webhook (optional)</div><div class="ap-grid"><label class="news-lbl">https-Adresse<input class="fc w-100" data-g="webhook.url" value="'+esc(f.webhook.url)+'" placeholder="https://…"></label>'
        +'<label class="news-lbl">Geheimnis für die Signatur'+(f.webhook.secret_set?' (gesetzt)':'')+'<input class="fc w-100" type="password" data-g="webhook.secret" value="" placeholder="'+(f.webhook.secret_set?'leer lassen = unverändert':'optional')+'" autocomplete="new-password"></label></div>'
        +'<p class="hint">Die Daten gehen als JSON per POST; mit Geheimnis steht die Signatur HMAC-SHA256 im Header <code>X-Elvado-Signature</code>. Interne Adressen sind gesperrt.</p>'
        +'<div class="ap-add"><button class="btn-a" type="button" data-a="save"><i class="fas fa-floppy-disk"></i> Speichern</button><button class="btn-g" type="button" data-a="cancel">Abbrechen</button></div></div>';
    }
    function subs(){
      var s=st.subs,f=st.forms.find(function(x){return x.id===s.form;})||{fields:[]};
      var h='<div class="ap-add" style="margin-bottom:10px"><button class="btn-g" type="button" data-a="back">‹ Zurück</button><button class="btn-g" type="button" data-a="csv"><i class="fas fa-file-csv"></i> CSV-Export</button></div><div class="ap-sub">Einsendungen: '+esc(f.title||s.form)+'</div>';
      if(!s.items.length)return h+'<p class="hint">Noch keine Einsendungen.</p>';
      return h+s.items.map(function(e){return '<div class="dm-row"><div class="dm-meta">'+esc(e.t)+(e.consent?' · Einwilligung erteilt':'')+'</div>'+Object.keys(e.values||{}).map(function(k){return '<div><b>'+esc(k)+':</b> '+esc(e.values[k]).replace(/\n/g,'<br>')+'</div>';}).join('')
        +(e.files||[]).map(function(x){return '<div><i class="fas fa-paperclip"></i> <a href="#" data-a="file" data-st="'+esc(x.stored)+'">'+esc(x.name)+'</a></div>';}).join('')
        +'<div class="ap-add"><button class="btn-g" type="button" data-a="delsub" data-i="'+esc(e.id)+'"><i class="fas fa-trash"></i> Löschen</button></div></div>';}).join('');
    }
    function draw(){root.innerHTML=st.edit?editor():(st.subs?subs():list());}
    function setPath(o,path,v){var p=path.split('.');for(var i=0;i<p.length-1;i++)o=o[p[i]];o[p[p.length-1]]=v;}
    root.addEventListener('input',function(e){var t=e.target;if(!st.edit)return;
      if(t.dataset.g){var v=t.type==='checkbox'?t.checked:(t.type==='number'?+t.value:t.value);setPath(st.edit,t.dataset.g,v);if(t.dataset.g==='title'&&st.edit._new&&!st.edit._idTouched){st.edit.id=slug(t.value);var i=root.querySelector('[data-g="id"]');if(i)i.value=st.edit.id;}if(t.dataset.g==='id')st.edit._idTouched=true;}
      else if(t.dataset.k){var row=t.closest('[data-f]');if(!row)return;var f=st.edit.fields[+row.dataset.f];
        if(t.dataset.k==='required')f.required=t.checked;else if(t.dataset.k==='options')f.options=t.value.split(/\n/).map(function(x){return x.trim();}).filter(Boolean);else f[t.dataset.k]=t.value;
        if(t.dataset.k==='label'&&!f._idTouched&&!f._saved){f.id=fid(t.value);var idi=row.querySelector('[data-k="id"]');if(idi)idi.value=f.id;}if(t.dataset.k==='id')f._idTouched=true;}
    });
    root.addEventListener('change',function(e){var t=e.target;if(st.edit&&t.dataset&&t.dataset.k==='type'){var row=t.closest('[data-f]');st.edit.fields[+row.dataset.f].type=t.value;draw();}else if(st.edit&&t.dataset&&t.dataset.g){setPath(st.edit,t.dataset.g,t.type==='checkbox'?t.checked:t.value);if(t.dataset.g==='notify.reply_to_field'){}}});
    root.addEventListener('click',async function(e){
      var b=e.target.closest('[data-a]');if(!b)return;e.preventDefault();var a=b.dataset.a,i=b.dataset.i;
      try{
        if(a==='new'){st.edit=blank();draw();}
        else if(a==='edit'){st.edit=JSON.parse(JSON.stringify(st.forms.find(function(f){return f.id===i;})));st.edit.fields.forEach(function(f){f._saved=true;});draw();}
        else if(a==='cancel'){st.edit=null;draw();}
        else if(a==='copy'){var f=st.forms.find(function(x){return x.id===i;});try{await navigator.clipboard.writeText(f.shortcode);api.toast('Shortcode kopiert');}catch(x){api.toast(f.shortcode);}}
        else if(a==='del'){if(!confirm('Dieses Formular löschen? Die gespeicherten Einsendungen bleiben erhalten, bis du sie entfernst.'))return;var wd=confirm('Auch alle gespeicherten Einsendungen und Dateien dieses Formulars endgültig löschen?');await api.call('delete_form',{id:i,with_data:wd});api.toast('Gelöscht');await load();}
        else if(a==='addf'){st.edit.fields.push({id:'',type:'text',label:'',required:false});draw();}
        else if(a==='rm'){var r=b.closest('[data-f]');st.edit.fields.splice(+r.dataset.f,1);draw();}
        else if(a==='up'||a==='dn'){var k=+b.closest('[data-f]').dataset.f,j=a==='up'?k-1:k+1,fl=st.edit.fields;var t=fl[k];fl[k]=fl[j];fl[j]=t;draw();}
        else if(a==='save'){var payload=JSON.parse(JSON.stringify(st.edit));delete payload._new;delete payload._idTouched;payload.fields.forEach(function(f){delete f._saved;delete f._idTouched;});var r2=await api.call('save_form',{form:payload});if(!r2.ok){api.toast(r2.message||'Fehler',true);return;}api.toast('Formular gespeichert');st.edit=null;await load();}
        else if(a==='subs'){var rs=await api.call('submissions',{form:i});st.subs={form:i,items:rs.items||[]};draw();}
        else if(a==='back'){st.subs=null;draw();}
        else if(a==='delsub'){if(!confirm('Diese Einsendung endgültig löschen?'))return;await api.call('delete_submission',{form:st.subs.form,id:i});var rs2=await api.call('submissions',{form:st.subs.form});st.subs.items=rs2.items||[];draw();}
        else if(a==='csv'){api.download('download_csv',{form:st.subs.form});}
        else if(a==='file'){api.download('download_upload',{form:st.subs.form,stored:b.dataset.st});}
      }catch(err){api.toast(err.message||'Fehler',true);}
    });
    load().catch(function(err){root.innerHTML='<p class="hint">'+esc(err.message)+'</p>';});
  });
})();
