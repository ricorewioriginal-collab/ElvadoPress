using System.IO;
using System.Net.Http;
using System.Text.Json;
using System.Xml.Linq;

namespace ElvadoPress.App.Windows;

public sealed record ApiResult<T>(T Data, bool FromCache, DateTime SavedAt);

public sealed class RadioApi
{
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(12) };
    private readonly JsonSerializerOptions _json = new() { PropertyNameCaseInsensitive = true };

    public RadioApi()
    {
        _http.DefaultRequestHeaders.UserAgent.ParseAdd("ElvadoPressApp-Windows/3.0");
    }

    public async Task<Station> GetStationAsync(string id)
    {
        using var doc = JsonDocument.Parse(await _http.GetStringAsync($"https://api.laut.fm/station/{Uri.EscapeDataString(id)}"));
        var root = doc.RootElement;
        var s = new Station
        {
            Id = id,
            Name = root.TryGetProperty("display_name", out var dn) ? dn.GetString() ?? id : id,
            Description = root.TryGetProperty("description", out var desc) ? desc.GetString() ?? "" : ""
        };
        if (root.TryGetProperty("images", out var images))
        {
            foreach (var key in new[]{"station_640x640","station_600x600","station_120x120"})
            {
                if (images.TryGetProperty(key, out var p) && !string.IsNullOrWhiteSpace(p.GetString()))
                {
                    s.Cover = p.GetString()!;
                    break;
                }
            }
        }
        if (root.TryGetProperty("genres", out var genres) && genres.ValueKind == JsonValueKind.Array)
        {
            foreach (var g in genres.EnumerateArray())
                if (!string.IsNullOrWhiteSpace(g.GetString())) s.Genres.Add(g.GetString()!);
        }
        return s;
    }

    public async Task<List<string>> GetAllStationNamesAsync()
    {
        using var doc = JsonDocument.Parse(await _http.GetStringAsync("https://api.laut.fm/station_names"));
        var result = new List<string>();
        if (doc.RootElement.ValueKind == JsonValueKind.Array)
        {
            foreach (var e in doc.RootElement.EnumerateArray())
            {
                if (e.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(e.GetString()))
                    result.Add(e.GetString()!);
                else if (e.ValueKind == JsonValueKind.Object)
                {
                    if (e.TryGetProperty("name", out var n) && !string.IsNullOrWhiteSpace(n.GetString())) result.Add(n.GetString()!);
                    else if (e.TryGetProperty("station", out var st) && !string.IsNullOrWhiteSpace(st.GetString())) result.Add(st.GetString()!);
                }
            }
        }
        return result.OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToList();
    }

    // ---------------------------------------------------------------- Lokaler Speicher (Offline-Stand)

    private static string CacheFile(string key)
    {
        var safe = new string(key.Select(c => char.IsLetterOrDigit(c) || c is '-' or '_' or '.' ? c : '_').ToArray());
        var brand = new string(Brand.Name.Select(c => char.IsLetterOrDigit(c) ? c : '_').ToArray());
        return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), brand, "cache", safe + ".txt");
    }

    /// <summary>
    /// Holt eine URL; gueltige Antworten werden gespeichert. Faellt das Netz aus, kommt der zuletzt gespeicherte Stand
    /// zurueck (FromCache = true). Gibt es keinen, wird eine Ausnahme geworfen.
    /// </summary>
    private async Task<(string Text, bool FromCache, DateTime SavedAt)> FetchCachedAsync(string key, string url, Func<string, bool> valid)
    {
        var file = CacheFile(key);
        try
        {
            var text = await _http.GetStringAsync(url);
            if (valid(text))
            {
                try { Directory.CreateDirectory(Path.GetDirectoryName(file)!); File.WriteAllText(file, text); } catch { }
                return (text, false, DateTime.Now);
            }
        }
        catch { }
        if (File.Exists(file))
        {
            var cached = File.ReadAllText(file);
            if (valid(cached)) return (cached, true, File.GetLastWriteTime(file));
        }
        throw new InvalidOperationException("Keine Daten verfügbar");
    }

    private static string JStr(JsonElement e, string prop) =>
        e.TryGetProperty(prop, out var v) ? (v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : v.ToString()) : "";

    private static int JInt(JsonElement e, string prop) => int.TryParse(JStr(e, prop), out var i) ? i : 0;

    private static bool IsJsonArray(string text)
    {
        try { using var d = JsonDocument.Parse(text); return d.RootElement.ValueKind == JsonValueKind.Array; }
        catch { return false; }
    }

    // ---------------------------------------------------------------- Sendeplan (laut.fm-API, ganze Woche)

    public async Task<ApiResult<List<ScheduleSlot>>> GetWeekScheduleAsync(string stationId)
    {
        var days = new[] { "mon", "tue", "wed", "thu", "fri", "sat", "sun" };
        var list = new List<ScheduleSlot>();
        void Add(string dayName, int start, int end, string title, string desc)
        {
            var day = Array.IndexOf(days, dayName);
            if (day < 0) return;
            list.Add(new ScheduleSlot { Day = day, Start = start, End = end, Title = title.Length > 0 ? title : "Sendung", Description = desc });
        }

        // Zuerst der Sendeplan-Dienst des Portals (gemeinsamer Zwischenspeicher, nur eigene Sender); sonst direkt laut.fm
        (string Text, bool FromCache, DateTime SavedAt) r;
        bool own = true;
        try
        {
            r = await FetchCachedAsync("schedule_own_" + stationId,
                $"{Brand.SiteBase}/cms/api.php?action=schedule&station={Uri.EscapeDataString(stationId)}", t => OwnPlaylists(t, stationId));
        }
        catch
        {
            own = false;
            r = await FetchCachedAsync("schedule_" + stationId,
                $"https://api.laut.fm/station/{Uri.EscapeDataString(stationId)}/schedule", IsJsonArray);
        }
        using var doc = JsonDocument.Parse(r.Text);
        if (own)
        {
            foreach (var p in doc.RootElement.GetProperty("stations").GetProperty(stationId).GetProperty("playlists").EnumerateArray())
            {
                if (!p.TryGetProperty("airtimes", out var air) || air.ValueKind != JsonValueKind.Array) continue;
                foreach (var a in air.EnumerateArray())
                    Add(JStr(a, "day"), JInt(a, "hour"), JInt(a, "end_time"), JStr(p, "name"), JStr(p, "description"));
            }
        }
        else
        {
            foreach (var el in doc.RootElement.EnumerateArray())
                Add(JStr(el, "day"), JInt(el, "hour"), JInt(el, "end_time"), JStr(el, "name"), JStr(el, "description"));
        }
        return new ApiResult<List<ScheduleSlot>>(list.OrderBy(x => x.Day).ThenBy(x => x.Start).ToList(), r.FromCache, r.SavedAt);
    }

    /// <summary>Antwort des Portal-Sendeplandienstes: status ok und Playlists des Senders vorhanden.</summary>
    private static bool OwnPlaylists(string text, string stationId)
    {
        try
        {
            using var d = JsonDocument.Parse(text);
            return JStr(d.RootElement, "status") == "ok"
                && d.RootElement.GetProperty("stations").GetProperty(stationId).GetProperty("playlists").ValueKind == JsonValueKind.Array;
        }
        catch { return false; }
    }

    // ---------------------------------------------------------------- Podcast

    public async Task<ApiResult<List<PodcastEpisode>>> GetPodcastsAsync()
    {
        var r = await FetchCachedAsync("podcast", Brand.SiteBase + "/podcast.php", t =>
        {
            try { using var d = JsonDocument.Parse(t); return d.RootElement.TryGetProperty("episodes", out var e) && e.ValueKind == JsonValueKind.Array; }
            catch { return false; }
        });
        using var doc = JsonDocument.Parse(r.Text);
        var list = new List<PodcastEpisode>();
        if (!doc.RootElement.TryGetProperty("episodes", out var eps) || eps.ValueKind != JsonValueKind.Array)
            return new ApiResult<List<PodcastEpisode>>(list, r.FromCache, r.SavedAt);
        foreach (var ep in eps.EnumerateArray())
        {
            var title = JStr(ep, "title");
            list.Add(new PodcastEpisode
            {
                Title = title.Length > 0 ? title : "Podcast-Folge",
                Date = JStr(ep, "pubDate"),
                AudioUrl = JStr(ep, "audioUrl"),
                Image = JStr(ep, "image"),
                Description = JStr(ep, "description")
            });
        }
        return new ApiResult<List<PodcastEpisode>>(list, r.FromCache, r.SavedAt);
    }

    // ---------------------------------------------------------------- News (RSS-Feed der Marke, Fallback News-API)

    public async Task<ApiResult<List<NewsArticle>>> GetNewsAsync(int limit = 30)
    {
        try
        {
            var rss = await FetchCachedAsync("news_rss", Brand.SiteBase + "/rss.xml", t =>
            {
                try { XDocument.Parse(t); return true; } catch { return false; }
            });
            var fromFeed = ParseRss(rss.Text);
            if (fromFeed.Count > 0) return new ApiResult<List<NewsArticle>>(fromFeed.Take(limit).ToList(), rss.FromCache, rss.SavedAt);
        }
        catch { }

        var api = await FetchCachedAsync("news_api", $"{Brand.SiteBase}/cms/api.php?action=news_public&limit={limit}", t =>
        {
            try { using var d = JsonDocument.Parse(t); return d.RootElement.TryGetProperty("articles", out var a) && a.ValueKind == JsonValueKind.Array; }
            catch { return false; }
        });
        return new ApiResult<List<NewsArticle>>(ParseNewsJson(api.Text), api.FromCache, api.SavedAt);
    }

    private static List<NewsArticle> ParseNewsJson(string json)
    {
        using var doc = JsonDocument.Parse(json);
        var list = new List<NewsArticle>();
        if (!doc.RootElement.TryGetProperty("articles", out var arr) || arr.ValueKind != JsonValueKind.Array) return list;
        foreach (var a in arr.EnumerateArray())
        {
            var cat = JStr(a, "category");
            var mode = JStr(a, "image_mode");
            list.Add(new NewsArticle
            {
                Id = a.TryGetProperty("id", out var id) && id.ValueKind == JsonValueKind.Number ? id.GetInt32() : 0,
                Slug = JStr(a, "slug"),
                Title = JStr(a, "title"),
                Category = cat.Length > 0 ? cat : "News",
                Excerpt = JStr(a, "excerpt"),
                ImageUrl = JStr(a, "image_url"),
                ImageMode = mode.Length > 0 ? mode : "thumbnail",
                ExternalUrl = JStr(a, "external_url"),
                VideoUrl = JStr(a, "video_url"),
                EmbedHtml = JStr(a, "embed_html"),
                BodyHtml = JStr(a, "body_html"),
                Author = JStr(a, "author"),
                PublishedAt = JStr(a, "published_at"),
                CreatedAt = JStr(a, "created_at")
            });
        }
        return list;
    }

    private static List<NewsArticle> ParseRss(string xml)
    {
        var doc = XDocument.Parse(xml);
        XNamespace content = "http://purl.org/rss/1.0/modules/content/";
        XNamespace media = "http://search.yahoo.com/mrss/";
        var siteHost = Uri.TryCreate(Brand.SiteBase, UriKind.Absolute, out var su) ? su.Host.Replace("www.", "") : "";
        var list = new List<NewsArticle>();
        foreach (var it in doc.Descendants("item"))
        {
            var title = ((string?)it.Element("title") ?? "").Trim();
            if (title.Length == 0) continue;
            var link = ((string?)it.Element("link") ?? "").Trim();
            var body = (string?)it.Element(content + "encoded") ?? "";
            var excerpt = StripTags((string?)it.Element("description") ?? body);
            var image = (string?)it.Element(media + "content")?.Attribute("url")
                        ?? (string?)it.Element(media + "thumbnail")?.Attribute("url") ?? "";
            var published = "";
            if (DateTimeOffset.TryParse((string?)it.Element("pubDate"), System.Globalization.CultureInfo.InvariantCulture,
                    System.Globalization.DateTimeStyles.None, out var dt))
                published = dt.LocalDateTime.ToString("yyyy-MM-dd HH:mm:ss");
            var external = "";
            if (Uri.TryCreate(link, UriKind.Absolute, out var lu))
            {
                var host = lu.Host.Replace("www.", "");
                var own = host.Equals(siteHost, StringComparison.OrdinalIgnoreCase) || host.EndsWith("." + siteHost, StringComparison.OrdinalIgnoreCase);
                if (!own) external = link;
            }
            var category = ((string?)it.Element("category") ?? "").Trim();
            list.Add(new NewsArticle
            {
                Title = title,
                Category = category.Length > 0 ? category : "News",
                Excerpt = excerpt,
                ImageUrl = image,
                ImageMode = "thumbnail",
                ExternalUrl = external,
                BodyHtml = body,
                Author = ((string?)it.Element("author") ?? "").Trim(),
                PublishedAt = published
            });
        }
        return list;
    }

    private static string StripTags(string s)
    {
        if (string.IsNullOrEmpty(s)) return "";
        var t = System.Text.RegularExpressions.Regex.Replace(s, "<[^>]+>", " ");
        t = System.Net.WebUtility.HtmlDecode(t);
        t = System.Text.RegularExpressions.Regex.Replace(t, @"\s+", " ").Trim();
        return t.Length > 260 ? t[..257] + "…" : t;
    }

    public async Task<(string artist,string title)> GetNowPlayingAsync(string stationId)
    {
        using var doc = JsonDocument.Parse(await _http.GetStringAsync($"https://api.laut.fm/station/{Uri.EscapeDataString(stationId)}/current_song"));
        var root = doc.RootElement;
        var title = root.TryGetProperty("title", out var t) ? t.GetString() ?? "Live-Stream" : "Live-Stream";
        var artist = "";
        if (root.TryGetProperty("artist", out var a))
        {
            if (a.ValueKind == JsonValueKind.Object && a.TryGetProperty("name", out var n)) artist = n.GetString() ?? "";
            else if (a.ValueKind == JsonValueKind.String) artist = a.GetString() ?? "";
        }
        return (artist,title);
    }
}
