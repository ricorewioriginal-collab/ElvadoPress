using System.IO;
using System.Text.Json;

namespace ElvadoPress.App.Windows;

// Multi-Brand: Name und Website der App kommen aus assets/config/brand.json (wird im Build je
// Marke geschrieben, siehe .github/workflows/windows-custom-brand.yml, Werte aus android/brands.json).
// Fehlt die Datei (lokaler Entwicklungslauf), gelten die neutralen Standardwerte.
public static class Brand
{
    public static string Name { get; private set; } = "ElvadoPress App";
    public static string Website { get; private set; } = "https://example.org/";
    // Domain der Marke ohne Slash am Ende: Basis fuer News (RSS), Podcast, CMS-API und KI-Assistent
    public static string SiteBase => Website.TrimEnd('/');
    // App-Typ aus brand.json: "radio" (Standard, Radio-App) oder "web" (die Website als eigene App für beliebige Seiten)
    public static string Type { get; private set; } = "radio";
    public static bool IsWeb => Type == "web";
    public static string ThemeColor { get; private set; } = "#070A1C";
    // Marken mit Radioverzeichnis: Verzeichnis-Suche, Fremd-Streams, Melden. Kommt aus brand.json ("directory": true).
    private static bool _buildDirectory;
    // Laufzeit-Schalter aus dem CMS (Bereich Apps, Aktion app_config); Standard: an
    public static bool DirectoryEnabled { get; set; } = true;
    public static bool AssistantEnabled { get; set; } = true;
    public static bool ReportEnabled { get; set; } = true;
    public static bool HasDirectory => _buildDirectory && DirectoryEnabled;
    // Optionale Zusatzfunktionen der Radio-App, die eine Website mitbringen muss (Voting-/Wunsch-Seiten, Podcast, Shops): aus brand.json,
    // sonst ausgeblendet. "communityBase": Basisadresse der Community-Seiten, "podcast": true, "shops": [{"title","desc","url"}]
    public static string CommunityBase { get; private set; } = "";
    public static bool HasPodcast { get; private set; }
    public static List<(string Title, string Desc, string Url)> Shops { get; } = new();
    public static bool HasCommunity => !string.IsNullOrWhiteSpace(CommunityBase);
    public static bool HasShops => Shops.Count > 0;
    public static string Host => Uri.TryCreate(Website, UriKind.Absolute, out var u) ? u.Host : "";
    /// <summary>Gehört die Adresse zur Website der Marke (Host oder Unterdomain)?</summary>
    public static bool IsOwnHost(string host) => Host.Length > 0 && (host.Equals(Host, StringComparison.OrdinalIgnoreCase) || host.EndsWith("." + Host, StringComparison.OrdinalIgnoreCase));

    static Brand()
    {
        try
        {
            var path = Path.Combine(AppContext.BaseDirectory, "assets", "config", "brand.json");
            if (!File.Exists(path)) return;
            using var doc = JsonDocument.Parse(File.ReadAllText(path));
            if (doc.RootElement.TryGetProperty("name", out var n) && n.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(n.GetString())) Name = n.GetString()!;
            if (doc.RootElement.TryGetProperty("type", out var t) && t.ValueKind == JsonValueKind.String && string.Equals(t.GetString(), "web", StringComparison.OrdinalIgnoreCase)) Type = "web";
            if (doc.RootElement.TryGetProperty("themeColor", out var tc) && tc.ValueKind == JsonValueKind.String && System.Text.RegularExpressions.Regex.IsMatch(tc.GetString() ?? "", "^#[0-9a-fA-F]{6}$")) ThemeColor = tc.GetString()!;
            if (doc.RootElement.TryGetProperty("directory", out var d) && d.ValueKind == JsonValueKind.True) _buildDirectory = true;
            if (doc.RootElement.TryGetProperty("communityBase", out var cb) && cb.ValueKind == JsonValueKind.String && Uri.TryCreate(cb.GetString(), UriKind.Absolute, out var cbu) && cbu.Scheme == Uri.UriSchemeHttps) CommunityBase = cb.GetString()!.TrimEnd('/') + "/";
            if (doc.RootElement.TryGetProperty("podcast", out var pc) && pc.ValueKind == JsonValueKind.True) HasPodcast = true;
            if (doc.RootElement.TryGetProperty("shops", out var sh) && sh.ValueKind == JsonValueKind.Array)
                foreach (var it in sh.EnumerateArray())
                {
                    var u = it.TryGetProperty("url", out var uu) ? uu.GetString() ?? "" : "";
                    if (!Uri.TryCreate(u, UriKind.Absolute, out var su) || su.Scheme != Uri.UriSchemeHttps) continue;
                    var shopTitle = it.TryGetProperty("title", out var tt) ? tt.GetString() ?? "" : "";
                    var shopDesc = it.TryGetProperty("desc", out var dd) ? dd.GetString() ?? "" : "";
                    Shops.Add((string.IsNullOrWhiteSpace(shopTitle) ? su.Host : shopTitle, shopDesc, su.AbsoluteUri));
                }
            if (doc.RootElement.TryGetProperty("website", out var w) && w.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(w.GetString())) Website = w.GetString()!;
        }
        catch { }
    }
}
