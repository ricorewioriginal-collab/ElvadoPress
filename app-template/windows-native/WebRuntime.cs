using System.IO;
using System.Net.Http;
using System.Text.Json;

namespace ElvadoPress.App.Windows;

/// <summary>Laufzeit-Konfiguration der Website-App aus dem CMS (Apps → Apps verwalten): Wartungsmodus, Pflicht-Update, Hinweis an alle Nutzer.</summary>
public sealed class WebRuntimeConfig
{
    public bool Maintenance { get; set; }
    public string MaintenanceTitle { get; set; } = "";
    public string MaintenanceText { get; set; } = "";
    public bool UpdateRequired { get; set; }
    public string UpdateUrl { get; set; } = "";
    public string NoticeId { get; set; } = "";
    public string NoticeLevel { get; set; } = "info";
    public string NoticeTitle { get; set; } = "";
    public string NoticeText { get; set; } = "";
    public string NoticeUrl { get; set; } = "";
    public string NoticeLabel { get; set; } = "";
    public bool HasNotice => NoticeId.Length > 0 && (NoticeTitle.Length > 0 || NoticeText.Length > 0);
}

/// <summary>
/// Abruf von {Website}/cms/api.php?action=app_config&amp;brand=&lt;Marke&gt; beim Start. Bei jedem Fehler startet die App normal (nie aussperren).
/// Die Abfrage übermittelt eine zufällige Installations-Kennung, die der Server gesalzen hasht; gezählt wird nur, wenn der Betreiber es im CMS eingeschaltet hat.
/// </summary>
public static class WebRuntime
{
    private static string Dir => Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), Brand.Name);

    public static string InstallId()
    {
        try
        {
            var f = Path.Combine(Dir, "install-id.txt");
            if (File.Exists(f)) { var v = File.ReadAllText(f).Trim(); if (v.Length >= 16) return v; }
            Directory.CreateDirectory(Dir);
            var id = Guid.NewGuid().ToString();
            File.WriteAllText(f, id);
            return id;
        }
        catch { return Guid.NewGuid().ToString(); }
    }

    public static string NoticeSeen
    {
        get { try { var f = Path.Combine(Dir, "notice-seen.txt"); return File.Exists(f) ? File.ReadAllText(f).Trim() : ""; } catch { return ""; } }
        set { try { Directory.CreateDirectory(Dir); File.WriteAllText(Path.Combine(Dir, "notice-seen.txt"), value); } catch { } }
    }

    public static async Task<WebRuntimeConfig> FetchAsync(HttpClient http)
    {
        try
        {
            var ver = System.Reflection.Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "";
            var url = Brand.SiteBase + "/cms/api.php?action=app_config&platform=windows" + (Brand.Id.Length > 0 ? "&brand=" + Uri.EscapeDataString(Brand.Id) : "")
                + "&version=" + Uri.EscapeDataString(ver) + "&did=" + InstallId();
            return Parse(await http.GetStringAsync(url));
        }
        catch { return new WebRuntimeConfig(); }
    }

    private static string Https(string? u) => u != null && u.StartsWith("https://", StringComparison.Ordinal) ? u : "";

    /// <summary>Antwort von app_config auswerten (getrennt vom Netz, damit sie prüfbar bleibt).</summary>
    public static WebRuntimeConfig Parse(string json)
    {
        var c = new WebRuntimeConfig();
        try
        {
            using var doc = JsonDocument.Parse(json);
            var r = doc.RootElement;
            string S(JsonElement e, string k) => e.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
            if (!r.TryGetProperty("status", out var st) || st.GetString() != "ok") return c;
            if (r.TryGetProperty("maintenance", out var m) && m.ValueKind == JsonValueKind.Object)
            {
                c.Maintenance = true;
                c.MaintenanceTitle = S(m, "title").Length > 0 ? S(m, "title") : "Wartungsarbeiten";
                c.MaintenanceText = S(m, "text");
            }
            if (r.TryGetProperty("update", out var u) && u.ValueKind == JsonValueKind.Object && u.TryGetProperty("required", out var rq) && rq.ValueKind == JsonValueKind.True)
            {
                c.UpdateRequired = true;
                c.UpdateUrl = Https(S(u, "page")).Length > 0 ? Https(S(u, "page")) : Https(S(u, "url"));
            }
            if (r.TryGetProperty("notice", out var n) && n.ValueKind == JsonValueKind.Object)
            {
                c.NoticeId = S(n, "id");
                c.NoticeLevel = S(n, "level") == "warn" ? "warn" : "info";
                c.NoticeTitle = S(n, "title");
                c.NoticeText = S(n, "text");
                c.NoticeUrl = Https(S(n, "url"));
                c.NoticeLabel = S(n, "url_label");
            }
        }
        catch { return new WebRuntimeConfig(); }
        return c;
    }
}
