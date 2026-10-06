using System.Diagnostics;
using System.IO;
using System.Net.Http;
using System.Security.Cryptography;

namespace ElvadoPress.App.Windows;

/// <summary>
/// Selbst-Update: Der Installer kommt von der eigenen Seite der Marke (Angaben aus app_config: url, sha256, size), wird geladen,
/// auf Größe/Prüfsumme geprüft und gestartet; die App beendet sich dabei, der Installer startet sie danach wieder.
/// </summary>
public static class AppUpdater
{
    private static readonly HttpClient Http = CreateHttp();

    private static HttpClient CreateHttp()
    {
        var c = new HttpClient { Timeout = TimeSpan.FromMinutes(10) };
        c.DefaultRequestHeaders.UserAgent.ParseAdd("ElvadoPressApp-Windows/3.0");
        return c;
    }

    /// <summary>Lädt den Installer in den Temp-Ordner. Rückgabe: Pfad der geprüften Datei. Wirft bei Fehler/Abbruch.</summary>
    public static async Task<string> DownloadAsync(string url, string sha256, long size, IProgress<int>? progress, CancellationToken ct)
    {
        if (!url.StartsWith("https://", StringComparison.OrdinalIgnoreCase)) throw new InvalidOperationException("Kein sicherer Download-Link");
        var dir = Path.Combine(Path.GetTempPath(), new string(Brand.Name.Where(char.IsLetterOrDigit).ToArray()) + "-Update");
        Directory.CreateDirectory(dir);
        var name = Path.GetFileName(new Uri(url).LocalPath);
        if (string.IsNullOrWhiteSpace(name) || !name.EndsWith(".exe", StringComparison.OrdinalIgnoreCase)) name = "Setup.exe";
        var path = Path.Combine(dir, name);
        var part = path + ".part";
        using (var resp = await Http.GetAsync(url, HttpCompletionOption.ResponseHeadersRead, ct))
        {
            resp.EnsureSuccessStatusCode();
            var total = resp.Content.Headers.ContentLength ?? size;
            await using var input = await resp.Content.ReadAsStreamAsync(ct);
            await using var output = File.Create(part);
            var buf = new byte[81920];
            long done = 0;
            var last = -1;
            int n;
            while ((n = await input.ReadAsync(buf, ct)) > 0)
            {
                await output.WriteAsync(buf.AsMemory(0, n), ct);
                done += n;
                if (total > 0)
                {
                    var pc = (int)Math.Min(100, done * 100 / total);
                    if (pc != last) { last = pc; progress?.Report(pc); }
                }
            }
        }
        var info = new FileInfo(part);
        if (size > 0 && info.Length != size) { TryDelete(part); throw new InvalidDataException("Größe stimmt nicht"); }
        if (sha256.Length == 64)
        {
            await using var fs = File.OpenRead(part);
            var hash = Convert.ToHexString(await SHA256.HashDataAsync(fs, ct)).ToLowerInvariant();
            if (hash != sha256.ToLowerInvariant()) { fs.Dispose(); TryDelete(part); throw new InvalidDataException("Prüfsumme stimmt nicht"); }
        }
        else if (info.Length < 1_000_000) { TryDelete(part); throw new InvalidDataException("Datei unvollständig"); }
        TryDelete(path);
        File.Move(part, path);
        return path;
    }

    /// <summary>Startet den Installer im stillen Modus mit Fortschrittsanzeige (kein Administrator nötig, Installation pro Benutzer).</summary>
    public static void RunInstaller(string path) =>
        Process.Start(new ProcessStartInfo(path, "/SILENT /SP- /NORESTART") { UseShellExecute = true });

    private static void TryDelete(string p) { try { if (File.Exists(p)) File.Delete(p); } catch { } }
}
