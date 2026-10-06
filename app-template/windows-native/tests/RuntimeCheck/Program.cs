using ElvadoPress.App.Windows;
int fails = 0; void T(string n, bool ok) { if (!ok) { fails++; Console.WriteLine("FEHLER: " + n); } }
var a = WebRuntime.Parse("{\"status\":\"ok\",\"maintenance\":null,\"notice\":null,\"update\":{\"required\":false}}");
T("leer", !a.Maintenance && !a.UpdateRequired && !a.HasNotice);
var b = WebRuntime.Parse("{\"status\":\"ok\",\"maintenance\":{\"title\":\"\",\"text\":\"Bis 18 Uhr\"}}");
T("wartung", b.Maintenance && b.MaintenanceTitle == "Wartungsarbeiten" && b.MaintenanceText == "Bis 18 Uhr");
var c = WebRuntime.Parse("{\"status\":\"ok\",\"update\":{\"required\":true,\"page\":\"http://x/\",\"url\":\"https://e.org/a.exe\"}}");
T("update https", c.UpdateRequired && c.UpdateUrl == "https://e.org/a.exe");
var d = WebRuntime.Parse("{\"status\":\"ok\",\"notice\":{\"id\":\"i\",\"level\":\"boese\",\"title\":\"T\",\"url\":\"ftp://x\",\"url_label\":\"L\"}}");
T("hinweis", d.HasNotice && d.NoticeLevel == "info" && d.NoticeUrl == "" && d.NoticeLabel == "L");
T("fehlerstatus", !WebRuntime.Parse("{\"status\":\"error\",\"maintenance\":{}}").Maintenance);
T("kaputtes json", !WebRuntime.Parse("{nein").Maintenance);
Console.WriteLine(fails == 0 ? "C#: 6 Prüfungen bestanden" : fails + " fehlgeschlagen"); return fails;
