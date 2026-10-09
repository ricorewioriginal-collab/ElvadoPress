(function(){
  // Sitzungstoken aus dem dauerhaften Speicher in die Sitzung übernehmen; ohne Token zeigt cms-app.js das Login-Formular.
  try{
    var k='elvadopress_session_token',v=localStorage.getItem(k);
    if(v&&!sessionStorage.getItem(k))sessionStorage.setItem(k,v);
  }catch(e){}
})();
