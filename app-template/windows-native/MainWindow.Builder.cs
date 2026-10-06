using System.IO;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Brush = System.Windows.Media.Brush;
using Button = System.Windows.Controls.Button;
using Brushes = System.Windows.Media.Brushes;
using Color = System.Windows.Media.Color;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using Orientation = System.Windows.Controls.Orientation;

namespace ElvadoPress.App.Windows;

// App-Builder (CMS → Apps): Farben, Startseiten-Blöcke, Senderreihenfolge, Wartungsmodus und Fehlerberichte.
// Alles kommt aus app_config; ohne aktiven Builder bleibt die Standard-Oberfläche unverändert.
public partial class MainWindow
{
    private JsonElement _layout;                 // layout-Objekt aus app_config (ValueKind.Undefined = Standard)
    private List<string> _ownedDefault = new();  // Core-Sender vor Anwendung von Reihenfolge/Ausblenden
    private Window? _maintWin;

    // Hörstatistik (nur wenn im CMS eingeschaltet): Sender + Dauer je Hörsitzung, anonym
    private DateTime _listenStart = DateTime.MinValue;
    private string _listenKey = "";

    private string ListenStationKey()
    {
        if (_currentWorld != null)
        {
            var slug = System.Text.RegularExpressions.Regex.Replace(_currentWorld.Name.ToLowerInvariant(), "[^a-z0-9]+", "-").Trim('-');
            return slug.Length == 0 ? "fremd-stream" : "world:" + (slug.Length > 50 ? slug[..50] : slug);
        }
        return (_currentStation?.Id ?? "").ToLowerInvariant();
    }

    private void ListenChanged(bool playing)
    {
        if (playing)
        {
            if (_listenStart == DateTime.MinValue) { _listenStart = DateTime.UtcNow; _listenKey = ListenStationKey(); }
            return;
        }
        if (_listenStart == DateTime.MinValue) return;
        var sec = (int)Math.Min((DateTime.UtcNow - _listenStart).TotalSeconds, 21600);
        var key = _listenKey;
        _listenStart = DateTime.MinValue;
        if (sec >= 10 && key.Length > 0 && ErrorReporter.ListenEnabled) _ = ErrorReporter.ListenAsync(_cmsHttp, InstallId(), key, sec);
    }

    // Zufällige, anonyme Installations-Kennung (nur für Nutzungszahlen/Ausrollen; der Server speichert nur einen Hash)
    private string InstallId()
    {
        var id = ReadSeen("install-id.txt");
        if (id.Length < 16)
        {
            id = Guid.NewGuid().ToString();
            try { Directory.CreateDirectory(DataDir); } catch { }
            WriteSeen("install-id.txt", id);
        }
        return id;
    }

    private static Color? ParseColor(string s)
    {
        try { return s.Length == 7 && s[0] == '#' ? (Color)System.Windows.Media.ColorConverter.ConvertFromString(s) : null; } catch { return null; }
    }

    private static string Str(JsonElement o, string k) =>
        o.ValueKind == JsonValueKind.Object && o.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";

    private void ApplyBuilder(JsonElement root)
    {
        if (root.TryGetProperty("telemetry", out var tm) && tm.ValueKind == JsonValueKind.Object)
            ErrorReporter.Enabled = tm.TryGetProperty("errors", out var eo) && eo.ValueKind == JsonValueKind.True;
        else ErrorReporter.Enabled = false;
        ErrorReporter.ListenEnabled = root.TryGetProperty("telemetry", out var tl) && tl.ValueKind == JsonValueKind.Object && tl.TryGetProperty("listen", out var lo) && lo.ValueKind == JsonValueKind.True;
        _ = ErrorReporter.FlushPendingAsync(_cmsHttp);

        _layout = root.TryGetProperty("layout", out var lay) && lay.ValueKind == JsonValueKind.Object ? lay.Clone() : default;
        JsonElement th = default;
        if (_layout.ValueKind == JsonValueKind.Object) _layout.TryGetProperty("theme", out th);
        var accent = ParseColor(Str(th, "accent")) ?? Color.FromRgb(0xB5, 0x7C, 0xFF);
        System.Windows.Application.Current.Resources["PrimaryBrush"] = new SolidColorBrush(accent);

        ApplyStationLayout();
    }

    // Reihenfolge und Sichtbarkeit der Core-Sender (Builder → Sender)
    private void ApplyStationLayout()
    {
        if (_ownedDefault.Count == 0) _ownedDefault = new List<string>(_owned);
        if (_layout.ValueKind != JsonValueKind.Object || !_layout.TryGetProperty("stations", out var st) || st.ValueKind != JsonValueKind.Object) return;
        var all = new List<string>(_ownedDefault);
        var hide = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        if (st.TryGetProperty("hidden", out var h) && h.ValueKind == JsonValueKind.Array) foreach (var x in h.EnumerateArray()) hide.Add(x.GetString() ?? "");
        var order = new List<string>();
        if (st.TryGetProperty("order", out var o) && o.ValueKind == JsonValueKind.Array)
            foreach (var x in o.EnumerateArray()) { var id = x.GetString() ?? ""; if (all.Contains(id, StringComparer.OrdinalIgnoreCase) && !order.Contains(id, StringComparer.OrdinalIgnoreCase)) order.Add(id); }
        foreach (var id in all) if (!order.Contains(id, StringComparer.OrdinalIgnoreCase)) order.Add(id);
        order.RemoveAll(hide.Contains);
        if (order.Count == 0) return;
        _owned.Clear();
        _owned.AddRange(order);
    }

    // Wartungsmodus: blockierendes Fenster, bis der Betreiber die Wartung im CMS beendet
    private bool ShowMaintenance(JsonElement root)
    {
        var has = root.TryGetProperty("maintenance", out var mt) && mt.ValueKind == JsonValueKind.Object;
        if (!has)
        {
            if (_maintWin != null) { var w = _maintWin; _maintWin = null; try { w.Close(); } catch { } }
            return false;
        }
        if (_maintWin != null) return true;
        var stack = new StackPanel { Margin = new Thickness(24) };
        stack.Children.Add(Txt(Str(mt, "title"), 20, null, FontWeights.Bold));
        var text = Txt(Str(mt, "text"), 14, (Brush)FindResource("MutedBrush"));
        text.Margin = new Thickness(0, 10, 0, 18);
        stack.Children.Add(text);
        var row = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right };
        var again = new Button { Content = "Erneut prüfen", Width = 130, Height = 36, Margin = new Thickness(0, 0, 8, 0) };
        var quit = new Button { Content = "Beenden", Width = 100, Height = 36 };
        row.Children.Add(again);
        row.Children.Add(quit);
        stack.Children.Add(row);
        var win = new Window
        {
            Title = Brand.Name,
            Width = 460,
            SizeToContent = SizeToContent.Height,
            ResizeMode = ResizeMode.NoResize,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner,
            Content = stack
        };
        _maintWin = win;
        again.Click += async (_, _) => await RefreshAppConfigAsync();
        quit.Click += (_, _) => { _maintWin = null; _allowClose = true; Close(); };
        win.Closing += (_, e) => { if (_maintWin != null) e.Cancel = true; }; // nur über "Beenden" oder wenn die Wartung endet
        win.Show();
        return true;
    }

    // Startseite aus Builder-Blöcken; false = Standard-Startseite
    private async Task<bool> TryRenderRemoteHomeAsync()
    {
        if (_layout.ValueKind != JsonValueKind.Object || !_layout.TryGetProperty("theme", out _) ||
            !_layout.TryGetProperty("home", out var home) || home.ValueKind != JsonValueKind.Array || home.GetArrayLength() == 0) return false;
        JsonElement th = default;
        _layout.TryGetProperty("theme", out th);
        var from = ParseColor(Str(th, "hero_from")) ?? Color.FromRgb(0x12, 0x0D, 0x3F);
        var to = ParseColor(Str(th, "hero_to")) ?? Color.FromRgb(0x5B, 0x1C, 0x84);
        Page(Brand.Name.ToUpperInvariant(), "Start");
        foreach (var b in home.EnumerateArray())
        {
            var type = Str(b, "type");
            if (type == "hero")
            {
                var hero = Card();
                hero.Background = new LinearGradientBrush(from, to, 45);
                var s = new StackPanel();
                s.Children.Add(Txt(Str(b, "eyebrow").ToUpperInvariant(), 11, (Brush)FindResource("CyanBrush"), FontWeights.Bold));
                s.Children.Add(Txt(Str(b, "title").Length > 0 ? Str(b, "title") : "Willkommen bei " + Brand.Name, 30, null, FontWeights.Bold));
                var t = Txt(Str(b, "text"), 15, (Brush)FindResource("MutedBrush"));
                t.Margin = new Thickness(0, 10, 0, 18);
                s.Children.Add(t);
                var listen = new Button { Content = "▶ Jetzt hören", Background = (Brush)FindResource("PrimaryBrush"), Foreground = Brushes.Black };
                listen.Click += async (_, _) => await ShowStationsAsync();
                s.Children.Add(listen);
                hero.Child = s;
                ContentHost.Children.Add(hero);
            }
            else if (type == "tiles")
            {
                if (Str(b, "title").Length > 0) ContentHost.Children.Add(Txt(Str(b, "title"), 12, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
                var wrap = new WrapPanel { Margin = new Thickness(0, 6, 0, 14) };
                if (b.TryGetProperty("tiles", out var tiles) && tiles.ValueKind == JsonValueKind.Array)
                    foreach (var tl in tiles.EnumerateArray())
                    {
                        var spec = TileSpec(tl);
                        if (spec == null) continue;
                        var btn = new Button { Content = spec.Value.title, Margin = new Thickness(0, 0, 10, 10), Padding = new Thickness(16, 10, 16, 10), MinWidth = 150 };
                        var action = spec.Value.action;
                        btn.Click += (_, _) => action();
                        wrap.Children.Add(btn);
                    }
                ContentHost.Children.Add(wrap);
            }
            else if (type == "stations")
            {
                ContentHost.Children.Add(Txt(Str(b, "title").Length > 0 ? Str(b, "title") : "Unsere Sender", 12, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
                var limit = b.TryGetProperty("limit", out var l) && l.ValueKind == JsonValueKind.Number ? l.GetInt32() : 0;
                foreach (var id in limit > 0 ? _owned.Take(limit) : _owned)
                {
                    try { ContentHost.Children.Add(await BuildStationCardAsync(id)); } catch { }
                }
            }
            else if (type == "text" || type == "link")
            {
                var card = Card();
                var s = new StackPanel();
                if (Str(b, "title").Length > 0) s.Children.Add(Txt(Str(b, "title"), 17, null, FontWeights.Bold));
                if (Str(b, "text").Length > 0) { var t = Txt(Str(b, "text"), 14, (Brush)FindResource("MutedBrush")); t.Margin = new Thickness(0, 6, 0, 0); s.Children.Add(t); }
                var url = Str(b, "url");
                if (type == "link" && url.Length > 0)
                {
                    var go = new Button { Content = (Str(b, "label").Length > 0 ? Str(b, "label") : "Öffnen") + " ›", Margin = new Thickness(0, 12, 0, 0), HorizontalAlignment = HorizontalAlignment.Left, Background = (Brush)FindResource("PrimaryBrush"), Foreground = Brushes.Black };
                    go.Click += (_, _) => OpenExternal(url);
                    s.Children.Add(go);
                }
                card.Child = s;
                ContentHost.Children.Add(card);
            }
        }
        return true;
    }

    private (string title, Action action)? TileSpec(JsonElement tl)
    {
        var id = Str(tl, "id");
        var custom = Str(tl, "title");
        var url = Str(tl, "url");
        (string title, Action action)? r = id switch
        {
            "favorites" => ("★ Favoriten", () => ShowFavorites()),
            "schedule" => ("◷ Sendeplan", async () => await ShowScheduleAsync()),
            "podcast" => ("◉ Podcast", async () => await ShowPodcastAsync()),
            "community" => ("✦ Mitmachen", () => ShowCommunity()),
            "news" => ("▤ News", async () => await ShowNewsAsync()),
            "help" => ("? Hilfe", () => ShowHelp()),
            "assistant" => Brand.AssistantEnabled ? ("✧ KI-Assistent", async () => await ShowAssistantAsync()) : null,
            "directory" => Brand.HasDirectory ? ("📡 Verzeichnis", async () => await ShowDirectoryAsync()) : null,
            "link" => url.Length > 0 ? ("↗ " + (custom.Length > 0 ? custom : "Link"), () => OpenExternal(url)) : null,
            _ => null
        };
        if (r != null && custom.Length > 0 && id != "link") r = (custom, r.Value.action);
        return r;
    }
}
