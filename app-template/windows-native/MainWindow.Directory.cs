using System.Diagnostics;
using System.IO;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Threading;
using Brush = System.Windows.Media.Brush;
using Button = System.Windows.Controls.Button;
using ComboBox = System.Windows.Controls.ComboBox;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using MessageBox = System.Windows.MessageBox;
using Orientation = System.Windows.Controls.Orientation;
using TextBox = System.Windows.Controls.TextBox;

namespace ElvadoPress.App.Windows;

// Radioverzeichnis (nur Marken mit Verzeichnis-Funktion): Suche in laut.fm und World Radio, Fremd-Streams
// direkt vom Betreiber, Favoriten, Melden, "Überrasch mich" im Wechsel und Treffer-Karte des KI-Assistenten.
public partial class MainWindow
{
    private DirItem? _currentWorld;                    // laufender Fremd-Stream (World Radio), sonst null
    private readonly List<DirItem> _dirQueue = new();  // Reihenfolge fuer Vor/Zurueck aus Suche, Zufall, Favoriten
    private readonly Dictionary<string, DirItem> _dirFavItems = new(StringComparer.OrdinalIgnoreCase); // "world:<uuid>" -> Daten
    private bool _dirReportOn = true;
    private bool _surpriseNextForeign;
    private List<DirItem> _aiStations = new();

    // Einstellungen aus dem CMS (Bereich Apps): Funktionen ein/aus, Hinweis an alle Nutzer, Update-Hinweis
    private async Task RefreshAppConfigAsync()
    {
        try
        {
            var ver = System.Reflection.Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "";
            var json = await _cmsHttp.GetStringAsync(Brand.SiteBase + "/cms/api.php?action=app_config&platform=windows&version=" + Uri.EscapeDataString(ver) + "&did=" + InstallId());
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (!root.TryGetProperty("status", out var st) || st.GetString() != "ok") return;
            if (root.TryGetProperty("features", out var f) && f.ValueKind == JsonValueKind.Object)
            {
                bool On(string k) => !f.TryGetProperty(k, out var v) || v.ValueKind != JsonValueKind.False;
                Brand.DirectoryEnabled = On("directory");
                Brand.AssistantEnabled = On("assistant");
                Brand.ReportEnabled = On("report");
            }
            if (!Brand.AssistantEnabled)
            {
                AssistantTile.Visibility = Visibility.Collapsed;
                AssistantNav.Visibility = Visibility.Collapsed;
            }
            ApplyBuilder(root);
            if (ShowMaintenance(root)) return;
            ShowAppNotices(root);
        }
        catch { }
    }

    private bool _updating;
    private readonly DispatcherTimer _updateTimer = new() { Interval = TimeSpan.FromHours(6) }; // regelmäßig auf neue Versionen prüfen

    /// <summary>Installer laden (mit Fortschritt), prüfen und starten; danach beendet sich die App. Ohne direkten Link: Download-Seite öffnen.</summary>
    private async Task StartUpdateAsync(JsonElement up, bool required)
    {
        string S(string k) => up.ValueKind == JsonValueKind.Object && up.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
        var url = S("url");
        var page = S("page");
        long size = up.ValueKind == JsonValueKind.Object && up.TryGetProperty("size", out var sz) && sz.ValueKind == JsonValueKind.Number ? sz.GetInt64() : 0;
        if (_updating) return;
        if (!url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
        {
            OpenExternal(page);
            if (required) { _allowClose = true; Close(); }
            return;
        }
        _updating = true;
        var cts = new CancellationTokenSource();
        var bar = new System.Windows.Controls.ProgressBar { Minimum = 0, Maximum = 100, Height = 18, Margin = new Thickness(0, 12, 0, 12) };
        var label = Txt("Update wird geladen …", 13);
        var cancel = new Button { Content = "Abbrechen", Width = 110, Height = 34, HorizontalAlignment = HorizontalAlignment.Right };
        cancel.Click += (_, _) => cts.Cancel();
        var stack = new StackPanel { Margin = new Thickness(22) };
        stack.Children.Add(label);
        stack.Children.Add(bar);
        stack.Children.Add(cancel);
        var win = new Window
        {
            Title = Brand.Name + " " + S("latest"),
            Width = 420,
            Height = 190,
            ResizeMode = ResizeMode.NoResize,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner,
            Content = stack
        };
        win.Closing += (_, _) => cts.Cancel();
        win.Show();
        try
        {
            var progress = new Progress<int>(pc => { bar.Value = pc; label.Text = $"Update wird geladen … {pc} %"; });
            var path = await AppUpdater.DownloadAsync(url, S("sha256"), size, progress, cts.Token);
            label.Text = "Installer wird gestartet …";
            AppUpdater.RunInstaller(path);
            win.Close();
            _allowClose = true;
            Close();
        }
        catch (OperationCanceledException)
        {
            win.Close();
            if (required) { _allowClose = true; Close(); }
        }
        catch (Exception ex)
        {
            win.Close();
            if (MessageBox.Show($"Das Update konnte nicht geladen werden ({ex.Message}).\n\nDownload-Seite öffnen?", "Update nicht möglich", MessageBoxButton.YesNo) == MessageBoxResult.Yes) OpenExternal(page);
            if (required) { _allowClose = true; Close(); }
        }
        finally { _updating = false; }
    }

    private string SeenFile(string name) => Path.Combine(DataDir, name);
    private string ReadSeen(string name) { try { var p = SeenFile(name); return File.Exists(p) ? File.ReadAllText(p).Trim() : ""; } catch { return ""; } }
    private void WriteSeen(string name, string value) { try { File.WriteAllText(SeenFile(name), value); } catch { } }

    /// <summary>Pflicht-Update (beendet die App), sonst Hinweis des Studios (einmal je Text), sonst Update-Hinweis (einmal je Version).</summary>
    private void ShowAppNotices(JsonElement root)
    {
        string S(JsonElement o, string k) => o.ValueKind == JsonValueKind.Object && o.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
        bool B(JsonElement o, string k) => o.ValueKind == JsonValueKind.Object && o.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.True;
        root.TryGetProperty("update", out var up);
        root.TryGetProperty("notice", out var n);
        var target = !string.IsNullOrEmpty(S(up, "url")) ? S(up, "url") : S(up, "page");
        if (B(up, "required"))
        {
            MessageBox.Show($"Diese Version von {Brand.Name} wird nicht mehr unterstützt. Bitte aktualisiere auf Version {S(up, "min")} oder neuer.\n\nNach \"OK\" wird das Update geladen und installiert.", "Update erforderlich");
            _ = StartUpdateAsync(up, true);
            return;
        }
        var nid = S(n, "id");
        if (nid.Length > 0 && nid != ReadSeen("app-notice-seen.txt"))
        {
            WriteSeen("app-notice-seen.txt", nid);
            var title = S(n, "title").Length > 0 ? S(n, "title") : Brand.Name;
            var url = S(n, "url");
            if (url.Length > 0)
            {
                var label = S(n, "url_label").Length > 0 ? S(n, "url_label") : "Mehr erfahren";
                if (MessageBox.Show(S(n, "text") + "\n\n" + label + " öffnen?", title, MessageBoxButton.YesNo) == MessageBoxResult.Yes) OpenExternal(url);
            }
            else MessageBox.Show(S(n, "text"), title);
            return;
        }
        var latest = S(up, "latest");
        if (B(up, "available") && latest.Length > 0 && latest != ReadSeen("app-update-seen.txt"))
        {
            WriteSeen("app-update-seen.txt", latest);
            var notes = S(up, "notes").Trim();
            if (MessageBox.Show($"Es gibt {Brand.Name} {latest}. Jetzt laden und installieren?" + (notes.Length > 0 ? "\n\nNeu:\n" + notes : "") + "\n\nDie App wird dafür kurz beendet und startet danach neu.", "Neue Version verfügbar", MessageBoxButton.YesNo) == MessageBoxResult.Yes) _ = StartUpdateAsync(up, false);
        }
    }

    private static void OpenExternal(string url)
    {
        if (string.IsNullOrWhiteSpace(url) || !url.StartsWith("http", StringComparison.OrdinalIgnoreCase)) return;
        try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); } catch { }
    }

    private async Task ShowDirectoryAsync(string? initial = null)
    {
        Page("RADIOVERZEICHNIS", "Lieblingssender suchen");
        ContentHost.Children.Add(Txt("Unsere eigenen Sender, öffentliche laut.fm-Sender und Webradios aus aller Welt (World Radio). " +
            "Fremde Sender gehören ihren Betreibern und werden direkt von dort gestreamt – nicht von " + Brand.Name + ".", 12, (Brush)FindResource("MutedBrush")));

        var input = new TextBox
        {
            Text = initial ?? "",
            Height = 46,
            FontSize = 15,
            Margin = new Thickness(0, 12, 0, 0),
            Padding = new Thickness(12, 0, 12, 0),
            VerticalContentAlignment = VerticalAlignment.Center,
            Background = (Brush)FindResource("CardBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            BorderBrush = (Brush)FindResource("LineBrush")
        };
        ContentHost.Children.Add(input);

        var scope = "all";
        var tools = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 10, 0, 0) };
        var scopes = new (string Key, string Label)[] { ("all", "Alle"), ("laut", "laut.fm"), ("world", "World Radio") };
        var scopeButtons = new List<Button>();
        foreach (var (key, label) in scopes)
        {
            var b = new Button { Content = label, Margin = new Thickness(0, 0, 8, 0) };
            scopeButtons.Add(b);
            tools.Children.Add(b);
        }
        var surprise = new Button { Content = "⤨ Überrasch mich" };
        surprise.Click += async (_, _) => await SurpriseAsync();
        tools.Children.Add(surprise);
        ContentHost.Children.Add(tools);

        var results = new StackPanel { Margin = new Thickness(0, 14, 0, 0) };
        ContentHost.Children.Add(results);
        var more = new Button { Content = "Mehr laden", Visibility = Visibility.Collapsed, HorizontalAlignment = HorizontalAlignment.Left, Padding = new Thickness(18, 6, 18, 6) };
        ContentHost.Children.Add(more);

        var shown = new List<DirItem>();
        var query = "";
        var offset = 0;
        const int PageSize = 12;

        void PaintScope()
        {
            for (var i = 0; i < scopes.Length; i++)
            {
                var on = scopes[i].Key == scope;
                scopeButtons[i].Background = on ? (Brush)FindResource("PrimaryBrush") : (Brush)FindResource("Card2Brush");
                scopeButtons[i].Foreground = on ? System.Windows.Media.Brushes.Black : (Brush)FindResource("TextBrush");
            }
        }

        async Task Run(bool append)
        {
            var q = query;
            var sc = scope;
            if (!append)
            {
                offset = 0;
                shown.Clear();
                results.Children.Clear();
                results.Children.Add(Txt("Suche läuft …", 13, (Brush)FindResource("MutedBrush")));
            }
            more.IsEnabled = false;
            DirPage? page = null;
            try { page = await RadioDirectory.SearchAsync(q, sc, offset, PageSize); } catch { }
            if (q != query || sc != scope) return; // inzwischen neu gesucht
            more.IsEnabled = true;
            if (!append) results.Children.Clear();
            if (page == null)
            {
                results.Children.Add(Txt("Die Suche ist gerade nicht erreichbar. Bitte später erneut versuchen.", 13, (Brush)FindResource("MutedBrush")));
                more.Visibility = Visibility.Collapsed;
                return;
            }
            _dirReportOn = page.ReportOn;
            foreach (var it in page.Items)
            {
                shown.Add(it);
                results.Children.Add(BuildDirectoryRow(it, shown));
            }
            offset += PageSize;
            if (shown.Count == 0) results.Children.Add(Txt("Kein Sender gefunden.", 13, (Brush)FindResource("MutedBrush")));
            more.Visibility = page.HasMore ? Visibility.Visible : Visibility.Collapsed;
        }

        async Task Perform()
        {
            var q = input.Text.Trim();
            if (q.Length < 2) return;
            query = q;
            await Run(false);
        }

        for (var i = 0; i < scopes.Length; i++)
        {
            var key = scopes[i].Key;
            scopeButtons[i].Click += async (_, _) =>
            {
                scope = key;
                PaintScope();
                if (query.Length >= 2) await Run(false);
            };
        }
        PaintScope();
        more.Click += async (_, _) => await Run(true);
        input.KeyDown += async (_, e) => { if (e.Key == System.Windows.Input.Key.Enter) await Perform(); };

        if (!string.IsNullOrWhiteSpace(initial) && initial.Trim().Length >= 2 && initial != "Sender suchen …")
        {
            await Perform();
        }
        else
        {
            results.Children.Add(Txt("Vorschläge werden geladen …", 13, (Brush)FindResource("MutedBrush")));
            DirPage? sug = null;
            try { sug = await RadioDirectory.RandomAsync("mix", 8); } catch { }
            if (query.Length > 0) return; // der Nutzer hat inzwischen gesucht
            results.Children.Clear();
            if (sug == null || sug.Items.Count == 0)
            {
                results.Children.Add(Txt("Tipp: z. B. rock, jazz, 80er oder einen Sendernamen eingeben.", 12, (Brush)FindResource("MutedBrush")));
            }
            else
            {
                _dirReportOn = sug.ReportOn;
                results.Children.Add(Txt("VORSCHLÄGE ZUM ENTDECKEN", 12, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
                shown.AddRange(sug.Items);
                foreach (var it in sug.Items) results.Children.Add(BuildDirectoryRow(it, shown));
            }
        }
        input.Focus();
    }

    private Border BuildDirectoryRow(DirItem it, List<DirItem> queue)
    {
        var card = Card();
        card.Padding = new Thickness(12);
        card.Margin = new Thickness(0, 0, 0, 10);
        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(64) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

        var cover = new Border { Width = 52, Height = 52, CornerRadius = new CornerRadius(10), ClipToBounds = true, Background = (Brush)FindResource("Card2Brush") };
        var img = new System.Windows.Controls.Image { Stretch = Stretch.UniformToFill };
        SetRemoteImage(img, it.Cover);
        cover.Child = img;
        grid.Children.Add(cover);

        var copy = new StackPanel { Margin = new Thickness(8, 0, 10, 0), VerticalAlignment = VerticalAlignment.Center };
        var name = Txt(it.Name, 16, null, FontWeights.Bold);
        name.TextTrimming = TextTrimming.CharacterEllipsis;
        name.TextWrapping = TextWrapping.NoWrap;
        copy.Children.Add(name);
        if (!string.IsNullOrWhiteSpace(it.Sub))
        {
            var sub = Txt(it.Sub, 12, (Brush)FindResource("MutedBrush"));
            sub.TextTrimming = TextTrimming.CharacterEllipsis;
            sub.TextWrapping = TextWrapping.NoWrap;
            copy.Children.Add(sub);
        }
        copy.Children.Add(Txt(it.Tag, 11, (Brush)FindResource(it.Own ? "CyanBrush" : "PrimaryBrush"), FontWeights.SemiBold));
        Grid.SetColumn(copy, 1);
        grid.Children.Add(copy);

        var buttons = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
        var play = new Button { Content = it.Playable ? "▶ Hören" : "↗ Webseite" };
        play.Click += async (_, _) => await PlayDirectoryItemAsync(it, queue);
        var fav = new Button { Content = IsDirFav(it) ? "★" : "☆" };
        fav.Click += (_, _) => { ToggleDirFav(it); fav.Content = IsDirFav(it) ? "★" : "☆"; };
        buttons.Children.Add(play);
        buttons.Children.Add(fav);
        if (_dirReportOn && Brand.ReportEnabled && !it.Own)
        {
            var flag = new Button { Content = "⚑", ToolTip = "Sender melden" };
            flag.Click += (_, _) => ShowReportDialog(it);
            buttons.Children.Add(flag);
        }
        Grid.SetColumn(buttons, 2);
        grid.Children.Add(buttons);

        card.Child = grid;
        return card;
    }

    /// <summary>Sender aus dem Verzeichnis starten: laut.fm ueber den bekannten Weg, World Radio direkt vom Stream des Betreibers.</summary>
    private async Task PlayDirectoryItemAsync(DirItem it, List<DirItem>? queue)
    {
        var q = queue == null ? new List<DirItem>() : new List<DirItem>(queue);
        if (!it.Playable)
        {
            MessageBox.Show("Dieser Sender lässt sich hier nicht direkt abspielen – die Webseite wird geöffnet.", Brand.Name);
            OpenExternal(it.Link);
            return;
        }
        try
        {
            if (it.IsWorld) await PlayWorldAsync(it);
            else await SelectStationAsync(it.Id, true);
            _dirQueue.Clear();
            _dirQueue.AddRange(q);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Sender konnte nicht gestartet werden:\n{ex.Message}", Brand.Name);
        }
    }

    private async Task PlayWorldAsync(DirItem it)
    {
        _currentWorld = it;
        _podcastMode = false;
        _currentStation = new Station { Id = "world:" + it.Id, Name = it.Name, Cover = it.Cover, Description = "Fremd-Stream", Genres = it.Genres };
        _currentAudioUrl = it.Stream;
        NowStation.Text = it.Name;
        NowTitle.Text = "Fremd-Stream";
        SetRemoteImage(NowCover, it.Cover);
        UpdateSourceUi();
        _player.PlayUrl(it.Stream);
        await RefreshWorldMetaAsync();
    }

    /// <summary>Titel (und Bild), falls der Stream sie per ICY mitsendet; sonst bleibt es bei "Fremd-Stream".</summary>
    private async Task RefreshWorldMetaAsync()
    {
        var w = _currentWorld;
        if (w == null) return;
        var (title, image) = await RadioDirectory.MetaAsync(w.Stream);
        if (_currentWorld != w) return;
        NowTitle.Text = string.IsNullOrWhiteSpace(title) ? "Fremd-Stream" : title;
        if (!string.IsNullOrWhiteSpace(image)) SetRemoteImage(NowCover, image);
    }

    /// <summary>Vor/Zurueck innerhalb der zuletzt angezeigten Verzeichnis-Liste; false, wenn der aktuelle Sender nicht darin steht.</summary>
    private async Task<bool> StepDirectoryAsync(int delta)
    {
        if (_dirQueue.Count == 0 || _currentStation == null) return false;
        var key = _currentWorld != null ? _currentWorld.Key : "laut:" + _currentStation.Id;
        var idx = _dirQueue.FindIndex(x => x.Key == key);
        if (idx < 0) return false;
        var q = new List<DirItem>(_dirQueue);
        var n = q.Count;
        for (var step = 1; step <= n; step++)
        {
            var cand = q[(((idx + delta * step) % n) + n) % n];
            if (!cand.Playable) continue;
            await PlayDirectoryItemAsync(cand, q);
            return true;
        }
        return false;
    }

    /// <summary>"Überrasch mich" bei Verzeichnis-Marken im Wechsel: eigener Sender, dann Fremdsender. true, wenn ein Fremdsender gestartet wurde.</summary>
    private async Task<bool> TryForeignSurpriseAsync()
    {
        DirPage? page = null;
        try { page = await RadioDirectory.RandomAsync("mix", 6); } catch { }
        var pick = page?.Items.FirstOrDefault(x => !x.Own && x.Playable);
        if (page == null || pick == null) return false;
        _dirReportOn = page.ReportOn;
        await PlayDirectoryItemAsync(pick, page.Items);
        return true;
    }

    private bool IsDirFav(DirItem it) => _favorites.Contains(it.IsWorld ? "world:" + it.Id : it.Id);

    /// <summary>laut.fm-Sender liegen wie bisher nur als Kennung in den Favoriten, Fremd-Streams brauchen die vollen Daten.</summary>
    private void ToggleDirFav(DirItem it)
    {
        if (!it.IsWorld)
        {
            ToggleFavorite(it.Id);
            return;
        }
        var key = "world:" + it.Id;
        if (_favorites.Remove(key)) _dirFavItems.Remove(key);
        else
        {
            _favorites.Add(key);
            _dirFavItems[key] = it;
        }
        SaveFavorites();
        SaveDirFavs();
    }

    private void LoadDirFavs()
    {
        try
        {
            var p = Path.Combine(DataDir, "directory-favorites.json");
            if (!File.Exists(p)) return;
            using var doc = JsonDocument.Parse(File.ReadAllText(p));
            foreach (var it in RadioDirectory.ParseItems(doc.RootElement))
                if (it.IsWorld) _dirFavItems["world:" + it.Id] = it;
        }
        catch { }
    }

    private void SaveDirFavs()
    {
        try
        {
            File.WriteAllText(Path.Combine(DataDir, "directory-favorites.json"), JsonSerializer.Serialize(_dirFavItems.Values.ToList()));
        }
        catch { }
    }

    private void ShowReportDialog(DirItem it)
    {
        var win = new Window
        {
            Title = "Sender melden",
            Width = 440,
            Height = 380,
            ResizeMode = ResizeMode.NoResize,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner
        };
        var stack = new StackPanel { Margin = new Thickness(22) };
        stack.Children.Add(Txt("Sender melden: " + it.Name, 16, null, FontWeights.Bold));
        stack.Children.Add(Txt("Danke für deinen Hinweis – wir prüfen den Sender und nehmen ihn bei Bedarf aus dem Verzeichnis.", 12, (Brush)FindResource("MutedBrush")));
        var combo = new ComboBox { Margin = new Thickness(0, 14, 0, 8), Height = 38, DisplayMemberPath = "Label" };
        foreach (var r in RadioDirectory.Reasons) combo.Items.Add(new { r.Key, r.Label });
        combo.SelectedIndex = 1;
        stack.Children.Add(combo);
        var note = new TextBox
        {
            MinHeight = 80,
            AcceptsReturn = true,
            TextWrapping = TextWrapping.Wrap,
            Padding = new Thickness(10, 8, 10, 8),
            Background = (Brush)FindResource("Card2Brush"),
            Foreground = (Brush)FindResource("TextBrush"),
            BorderBrush = (Brush)FindResource("LineBrush"),
            ToolTip = "Optional: kurze Angabe zum Problem"
        };
        stack.Children.Add(note);
        var status = Txt("", 12, (Brush)FindResource("MutedBrush"));
        status.Margin = new Thickness(0, 8, 0, 0);
        var row = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 12, 0, 0) };
        var send = new Button { Content = "Melden", Width = 110, Height = 38 };
        var cancel = new Button { Content = "Abbrechen", Width = 110, Height = 38 };
        cancel.Click += (_, _) => win.Close();
        send.Click += async (_, _) =>
        {
            send.IsEnabled = false;
            status.Text = "Wird gesendet …";
            var key = RadioDirectory.Reasons[Math.Max(0, combo.SelectedIndex)].Key;
            var err = await RadioDirectory.ReportAsync(it, key, note.Text.Trim());
            if (err.Length == 0)
            {
                MessageBox.Show("Danke – wir prüfen den Sender.", Brand.Name);
                win.Close();
                return;
            }
            status.Text = err;
            send.IsEnabled = true;
        };
        row.Children.Add(send);
        row.Children.Add(cancel);
        stack.Children.Add(row);
        stack.Children.Add(status);
        win.Content = stack;
        win.ShowDialog();
    }

    /// <summary>Karte "Aus dem Radioverzeichnis" im KI-Assistenten: Treffer mit Hören-Knopf.</summary>
    private void AddStationsCard(StackPanel host, List<DirItem> items)
    {
        if (items.Count == 0) return;
        var card = Card();
        card.Margin = new Thickness(0, 0, 80, 10);
        card.Padding = new Thickness(14, 10, 14, 6);
        var stack = new StackPanel();
        stack.Children.Add(Txt("Aus dem Radioverzeichnis", 12, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
        foreach (var it in items)
        {
            var row = new Grid { Margin = new Thickness(0, 8, 0, 4) };
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            var copy = new StackPanel();
            var name = Txt(it.Name, 14, null, FontWeights.Bold);
            name.TextTrimming = TextTrimming.CharacterEllipsis;
            name.TextWrapping = TextWrapping.NoWrap;
            copy.Children.Add(name);
            var sub = Txt(string.Join(" · ", new[] { it.Sub, it.IsWorld ? "Fremd-Stream" : "laut.fm" }.Where(x => !string.IsNullOrWhiteSpace(x))), 11, (Brush)FindResource("MutedBrush"));
            sub.TextTrimming = TextTrimming.CharacterEllipsis;
            sub.TextWrapping = TextWrapping.NoWrap;
            copy.Children.Add(sub);
            row.Children.Add(copy);
            var play = new Button { Content = "▶ Hören", Margin = new Thickness(10, 0, 0, 0) };
            play.Click += async (_, _) => await PlayDirectoryItemAsync(it, items);
            Grid.SetColumn(play, 1);
            row.Children.Add(play);
            stack.Children.Add(row);
        }
        card.Child = stack;
        host.Children.Add(card);
    }
}
