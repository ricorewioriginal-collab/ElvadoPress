using System.IO;
using System.Text.Json;

namespace ElvadoPress.App.Windows;

// Multi-Brand: Name und Website der App kommen aus assets/config/brand.json (wird im Build je
// Marke geschrieben, siehe .github/workflows/windows-custom-brand.yml, Werte aus android/brands.json).
// Fehlt die Datei (lokaler Entwicklungslauf), gelten die neutralen Standardwerte.
public static class Brand
{
    // Marken-ID aus brand.json ("id"): so erkennt das CMS, welche App (Apps → Apps verwalten) anfragt; leer im lokalen Entwicklungslauf
    public static string Id { get; private set; } = "";
    public static string Name { get; private set; } = "ElvadoPress App";
    public static string Website { get; private set; } = "https://example.org/";
    // Domain der Marke ohne Slash am Ende: Basis fuer die CMS-API
    public static string SiteBase => Website.TrimEnd('/');
    // App-Typ aus brand.json: "web" (Standard, die Website als eigene App) oder "content" (Baukasten-App mit Tab-Leiste)
    public static string Type { get; private set; } = "web";
    public static bool IsContent => Type == "content";
    public static string ThemeColor { get; private set; } = "#070A1C";
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
            if (doc.RootElement.TryGetProperty("id", out var bid) && bid.ValueKind == JsonValueKind.String && System.Text.RegularExpressions.Regex.IsMatch(bid.GetString() ?? "", "^[a-z][a-z0-9]{2,19}$")) Id = bid.GetString()!;
            if (doc.RootElement.TryGetProperty("name", out var n) && n.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(n.GetString())) Name = n.GetString()!;
            if (doc.RootElement.TryGetProperty("type", out var t) && t.ValueKind == JsonValueKind.String && (string.Equals(t.GetString(), "web", StringComparison.OrdinalIgnoreCase) || string.Equals(t.GetString(), "content", StringComparison.OrdinalIgnoreCase))) Type = t.GetString()!.ToLowerInvariant();
            if (doc.RootElement.TryGetProperty("themeColor", out var tc) && tc.ValueKind == JsonValueKind.String && System.Text.RegularExpressions.Regex.IsMatch(tc.GetString() ?? "", "^#[0-9a-fA-F]{6}$")) ThemeColor = tc.GetString()!;
            if (doc.RootElement.TryGetProperty("website", out var w) && w.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(w.GetString())) Website = w.GetString()!;
        }
        catch { }
    }
}
