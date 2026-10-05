(function(){
  function value(k){return sessionStorage.getItem(k)||localStorage.getItem(k)||''}
  var user=value('anmacha_lautfm_user')||value('anmacha_station_name');
  var tok=value('anmacha_session_token');
  if(user&&tok){
    ['anmacha_station_name','anmacha_lautfm_user','anmacha_all_stations','anmacha_station_id','anmacha_session_token','anmacha_user_cache','anmacha_user_role'].forEach(function(k){
      var v=localStorage.getItem(k);if(v&&!sessionStorage.getItem(k))sessionStorage.setItem(k,v);
    });
    return;
  }
  // Kein AnMaCha-Login vorhanden: nicht mehr automatisch wegleiten. cms-app.js prüft
  // stattdessen einen eigenen lokalen CMS-Zugang und zeigt bei Bedarf ein Login-Formular.
})();
