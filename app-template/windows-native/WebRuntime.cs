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
    /// <summary>Tab-Leiste der Baukasten-App (Titel, Symbol, vollständige https-Adresse).</summary>
    public List<(string Title, string Icon, string Url)> Tabs { get; set; } = new();
    public string TabsJson { get; set; } = "";
    public bool Ok { get; set; }
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

    /// <summary>Tab-Leiste lesen: Pfade (/seite/) werden auf die Website der App aufgelöst, unsichere oder leere Einträge verworfen (höchstens 5).</summary>
    public static List<(string Title, string Icon, string Url)> ParseTabs(string json, string siteBase)
    {
        var list = new List<(string, string, string)>();
        if (string.IsNullOrEmpty(json)) return list;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.ValueKind != JsonValueKind.Array) return list;
            var baseUrl = (siteBase ?? "").TrimEnd('/');
            foreach (var t in doc.RootElement.EnumerateArray())
            {
                if (list.Count >= 5 || t.ValueKind != JsonValueKind.Object) continue;
                var title = t.TryGetProperty("title", out var tt) && tt.ValueKind == JsonValueKind.String ? (tt.GetString() ?? "").Trim() : "";
                var url = t.TryGetProperty("url", out var uu) && uu.ValueKind == JsonValueKind.String ? (uu.GetString() ?? "").Trim() : "";
                var icon = t.TryGetProperty("icon", out var ii) && ii.ValueKind == JsonValueKind.String ? ii.GetString() ?? "star" : "star";
                if (url.StartsWith("/") && !url.StartsWith("//") && baseUrl.Length > 0) url = baseUrl + url;
                if (title.Length == 0 || !url.StartsWith("https://", StringComparison.Ordinal)) continue;
                list.Add((title, icon, url));
            }
        }
        catch { }
        return list;
    }

    public static string TabGlyph(string icon) => icon switch
    {
        "home" => "\u2302", "news" => "\u25A4", "info" => "\u24D8", "shop" => "\U0001F6D2", "calendar" => "\U0001F4C5", "phone" => "\u260E",
        "map" => "\U0001F4CD", "mail" => "\u2709", "user" => "\u263A", "play" => "\u25B6", "menu" => "\u2630", _ => "\u2605"
    };

    /// <summary>Zuletzt bekannte Tab-Leiste (damit sie auch ohne Netz sofort erscheint).</summary>
    public static string TabsCache
    {
        get { try { var f = Path.Combine(Dir, "tabs.json"); return File.Exists(f) ? File.ReadAllText(f) : ""; } catch { return ""; } }
        set { try { Directory.CreateDirectory(Dir); File.WriteAllText(Path.Combine(Dir, "tabs.json"), value); } catch { } }
    }

    /// <summary>Antwort von app_config auswerten (getrennt vom Netz, damit sie prüfbar bleibt).</summary>
    public static WebRuntimeConfig Parse(string json, string? siteBase = null)
    {
        var c = new WebRuntimeConfig();
        try
        {
            using var doc = JsonDocument.Parse(json);
            var r = doc.RootElement;
            string S(JsonElement e, string k) => e.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
            if (!r.TryGetProperty("status", out var st) || st.GetString() != "ok") return c;
            c.Ok = true;
            if (r.TryGetProperty("tabs", out var tb) && tb.ValueKind == JsonValueKind.Array)
            {
                c.TabsJson = tb.GetRawText();
                c.Tabs = ParseTabs(c.TabsJson, siteBase ?? Brand.SiteBase);
            }
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
