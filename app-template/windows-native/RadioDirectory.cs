using System.Net.Http;
using System.Text;
using System.Text.Json;

namespace ElvadoPress.App.Windows;

/// <summary>Ein Sender aus dem Radioverzeichnis: Source "laut" (laut.fm) oder "world" (radio-browser.info, Fremd-Stream).</summary>
public sealed class DirItem
{
    public string Source { get; set; } = "laut";
    public string Id { get; set; } = "";
    public string Name { get; set; } = "";
    public string Description { get; set; } = "";
    public string Cover { get; set; } = "";
    public string Link { get; set; } = "";
    public string Country { get; set; } = "";
    public string Stream { get; set; } = "";
    public string Codec { get; set; } = "";
    public bool Own { get; set; }
    public int Bitrate { get; set; }
    public List<string> Genres { get; set; } = new();

    public bool IsWorld => Source == "world";
    public string Key => Source + ":" + Id;
    /// <summary>Fremd-Streams sind nur mit https-Stream abspielbar (der Server liefert "" bei http oder wenn sie im CMS abgeschaltet sind).</summary>
    public bool Playable => !IsWorld || !string.IsNullOrWhiteSpace(Stream);
    public string Sub
    {
        get
        {
            var bits = new List<string>();
            if (Genres.Count > 0) bits.Add(string.Join(" · ", Genres.Take(3)));
            if (!string.IsNullOrWhiteSpace(Country)) bits.Add(Country);
            if (IsWorld && Bitrate > 0) bits.Add(Bitrate + " kbit/s");
            return string.Join(" · ", bits);
        }
    }
    public string Tag => Own ? "Unser Sender" : IsWorld ? "Fremd-Stream" : "laut.fm";

    public static DirItem? From(JsonElement e)
    {
        if (e.ValueKind != JsonValueKind.Object) return null;
        string S(string k) => e.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
        var it = new DirItem
        {
            Source = S("source") == "world" ? "world" : "laut",
            Id = S("id"), Name = S("name"), Description = S("description"), Cover = S("cover"),
            Link = S("link"), Country = S("country"), Stream = S("stream"), Codec = S("codec"),
            Own = e.TryGetProperty("own", out var o) && o.ValueKind == JsonValueKind.True,
            Bitrate = e.TryGetProperty("bitrate", out var b) && b.ValueKind == JsonValueKind.Number ? b.GetInt32() : 0
        };
        if (e.TryGetProperty("genres", out var g) && g.ValueKind == JsonValueKind.Array)
            foreach (var x in g.EnumerateArray())
                if (x.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(x.GetString())) it.Genres.Add(x.GetString()!.Trim());
        if (string.IsNullOrWhiteSpace(it.Id)) return null;
        if (string.IsNullOrWhiteSpace(it.Name)) it.Name = it.Id;
        return it;
    }
}

public sealed class DirPage
{
    public List<DirItem> Items { get; } = new();
    public bool HasMore { get; set; }
    public bool ReportOn { get; set; } = true;
}

/// <summary>
/// Radioverzeichnis (Marken mit Verzeichnis-Funktion): Suche, Zufall, Meldungen und Metadaten laufen ueber die
/// CMS-API der Marke (directory_*). Der Server wendet Sperrliste und Einstellungen aus dem CMS an; die App zeigt nur, was er liefert.
/// </summary>
public static class RadioDirectory
{
    private static readonly HttpClient Http = CreateHttp();

    public static readonly (string Key, string Label)[] Reasons =
    {
        ("rights", "Rechtsverletzung / Urheberrecht"),
        ("offline", "Nicht erreichbar / defekt"),
        ("content", "Unpassender Inhalt"),
        ("wrong", "Falsche Angaben"),
        ("other", "Sonstiges")
    };

    private static HttpClient CreateHttp()
    {
        var c = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
        c.DefaultRequestHeaders.UserAgent.ParseAdd("ElvadoPressApp-Windows/3.0");
        return c;
    }

    private static string Api(string action) => Brand.SiteBase + "/cms/api.php?action=" + action;

    public static List<DirItem> ParseItems(JsonElement arr)
    {
        var list = new List<DirItem>();
        if (arr.ValueKind != JsonValueKind.Array) return list;
        foreach (var e in arr.EnumerateArray())
        {
            var it = DirItem.From(e);
            if (it != null) list.Add(it);
        }
        return list;
    }

    private static DirPage ToPage(JsonElement root)
    {
        var p = new DirPage();
        if (root.TryGetProperty("results", out var r)) p.Items.AddRange(ParseItems(r));
        p.HasMore = root.TryGetProperty("has_more", out var m) && m.ValueKind == JsonValueKind.True;
        p.ReportOn = !root.TryGetProperty("report", out var rp) || rp.ValueKind != JsonValueKind.False;
        return p;
    }

    /// <summary>scope: "all", "laut" oder "world".</summary>
    public static async Task<DirPage> SearchAsync(string q, string scope, int offset, int limit)
    {
        var url = Api("directory_search") + "&q=" + Uri.EscapeDataString(q) + "&scope=" + Uri.EscapeDataString(scope) + "&offset=" + offset + "&limit=" + limit;
        using var doc = JsonDocument.Parse(await Http.GetStringAsync(url));
        return ToPage(doc.RootElement);
    }

    /// <summary>kind: "mix", "laut" oder "world".</summary>
    public static async Task<DirPage> RandomAsync(string kind, int n)
    {
        using var doc = JsonDocument.Parse(await Http.GetStringAsync(Api("directory_random") + "&kind=" + Uri.EscapeDataString(kind) + "&n=" + n));
        return ToPage(doc.RootElement);
    }

    /// <summary>Titel/Bild aus den ICY-Metadaten des Streams (Server-seitig zwischengespeichert). Leer, wenn nichts mitgesendet wird.</summary>
    public static async Task<(string Title, string Image)> MetaAsync(string streamUrl)
    {
        try
        {
            using var doc = JsonDocument.Parse(await Http.GetStringAsync(Api("directory_meta") + "&url=" + Uri.EscapeDataString(streamUrl)));
            string S(string k) => doc.RootElement.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
            return (S("title").Trim(), S("image"));
        }
        catch { return ("", ""); }
    }

    /// <summary>Meldung an die Redaktion (CMS: Radioverzeichnis → Meldungen). Gibt "" bei Erfolg zurueck, sonst eine Fehlermeldung.</summary>
    public static async Task<string> ReportAsync(DirItem it, string reason, string text)
    {
        try
        {
            var body = JsonSerializer.Serialize(new { source = it.Source, id = it.Id, name = it.Name, link = it.Link, reason, text, website = "" });
            using var resp = await Http.PostAsync(Api("directory_report"), new StringContent(body, Encoding.UTF8, "application/json"));
            if (resp.IsSuccessStatusCode) return "";
            return (int)resp.StatusCode == 429 ? "Zu viele Meldungen – bitte später erneut versuchen." : "Meldung momentan nicht möglich.";
        }
        catch { return "Meldung momentan nicht möglich."; }
    }
}
