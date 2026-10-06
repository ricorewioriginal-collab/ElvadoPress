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
var tb = WebRuntime.Parse("{\"status\":\"ok\",\"tabs\":[{\"title\":\"Start\",\"icon\":\"home\",\"url\":\"/\"},{\"title\":\"Shop\",\"icon\":\"shop\",\"url\":\"https://shop.example.org/\"},{\"title\":\"Böse\",\"icon\":\"star\",\"url\":\"javascript:x\"},{\"title\":\"\",\"icon\":\"star\",\"url\":\"/leer/\"}]}", "https://example.org");
T("tabs", tb.Ok && tb.Tabs.Count == 2 && tb.Tabs[0].Url == "https://example.org/" && tb.Tabs[0].Icon == "home" && tb.Tabs[1].Url == "https://shop.example.org/");
T("keine tabs", WebRuntime.Parse("{\"status\":\"ok\"}", "https://example.org").Tabs.Count == 0 && WebRuntime.ParseTabs("kaputt", "https://example.org").Count == 0);
Console.WriteLine(fails == 0 ? "C#: 8 Prüfungen bestanden" : fails + " fehlgeschlagen"); return fails;
