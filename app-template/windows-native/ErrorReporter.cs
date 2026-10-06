using System.IO;
using System.Net.Http;
using System.Text;
using System.Text.Json;

namespace ElvadoPress.App.Windows;

// Fehlerberichte an das CMS (Bereich Apps → Datenschutz & Statistik). Gesendet wird nur, wenn der Betreiber es im CMS
// eingeschaltet hat (app_config.telemetry.errors); enthalten sind Fehlertext, Version und Windows-Version, keine Nutzerdaten.
// Abstürze werden lokal vermerkt und beim nächsten Start gesendet (oder verworfen, wenn die Funktion aus ist).
public static class ErrorReporter
{
    public static bool Enabled { get; set; }
    private static DateTime _last = DateTime.MinValue;
    private static string PendingFile => Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), Brand.Name, "pending-crash.json");
    public static string Version => System.Reflection.Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "";

    private static string Build(string kind, string message, string where, string stack) =>
        JsonSerializer.Serialize(new Dictionary<string, string>
        {
            ["platform"] = "windows", ["brand"] = Brand.Id, ["kind"] = kind, ["message"] = message, ["where"] = where, ["version"] = Version,
            ["os"] = "Windows " + Environment.OSVersion.Version.Major + "." + Environment.OSVersion.Version.Build, ["stack"] = stack
        });

    /// <summary>Merkt einen Absturz für den nächsten Start (kein Netzwerk im Absturz-Handler).</summary>
    public static void SaveCrash(Exception? ex)
    {
        try
        {
            if (ex == null) return;
            var frames = (ex.StackTrace ?? "").Split('\n').Take(12).Select(l => l.Trim());
            Directory.CreateDirectory(Path.GetDirectoryName(PendingFile)!);
            File.WriteAllText(PendingFile, Build("crash", ex.GetType().Name + ": " + ex.Message, frames.FirstOrDefault() ?? "", string.Join("\n", frames)));
        }
        catch { }
    }

    private static async Task PostAsync(HttpClient http, string json)
    {
        using var content = new StringContent(json, Encoding.UTF8, "application/json");
        using var res = await http.PostAsync(Brand.SiteBase + "/cms/api.php?action=app_error", content);
    }

    public static bool ListenEnabled { get; set; }

    /// <summary>Hörsitzung (nur Sender + Sekunden) an das CMS, wenn die Hörstatistik dort eingeschaltet ist.</summary>
    public static async Task ListenAsync(HttpClient http, string did, string station, int seconds)
    {
        try
        {
            var json = JsonSerializer.Serialize(new Dictionary<string, object> { ["platform"] = "windows", ["brand"] = Brand.Id, ["did"] = did, ["station"] = station, ["seconds"] = seconds, ["version"] = Version });
            using var content = new StringContent(json, Encoding.UTF8, "application/json");
            using var res = await http.PostAsync(Brand.SiteBase + "/cms/api.php?action=app_listen", content);
        }
        catch { }
    }

    /// <summary>Absturz vom letzten Lauf senden (wenn erlaubt) und in jedem Fall löschen.</summary>
    public static async Task FlushPendingAsync(HttpClient http)
    {
        try
        {
            if (!File.Exists(PendingFile)) return;
            var json = File.ReadAllText(PendingFile);
            File.Delete(PendingFile);
            if (Enabled) await PostAsync(http, json);
        }
        catch { }
    }

    /// <summary>Laufzeitfehler melden (höchstens einer pro 30 s).</summary>
    public static async Task ReportAsync(HttpClient http, string kind, string message, string where)
    {
        try
        {
            if (!Enabled || (DateTime.UtcNow - _last).TotalSeconds < 30) return;
            _last = DateTime.UtcNow;
            await PostAsync(http, Build(kind, message, where, ""));
        }
        catch { }
    }
}
