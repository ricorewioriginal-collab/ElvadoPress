using System.Diagnostics;
using System.IO;
using System.Net.Http;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using System.Runtime.InteropServices;
using System.Windows.Interop;
using Brush = System.Windows.Media.Brush;
using Image = System.Windows.Controls.Image;
using KeyEventArgs = System.Windows.Input.KeyEventArgs;
using MessageBox = System.Windows.MessageBox;
using Orientation = System.Windows.Controls.Orientation;
using Button = System.Windows.Controls.Button;
using Brushes = System.Windows.Media.Brushes;
using TextBox = System.Windows.Controls.TextBox;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using Microsoft.Web.WebView2.Wpf;

namespace ElvadoPress.App.Windows;

public partial class MainWindow : Window
{
    private static readonly HttpClient _cmsHttp = new() { Timeout = TimeSpan.FromSeconds(6) };
    private readonly RadioApi _api = new();
    private readonly NativePlayer _player = new();
    private readonly Random _random = new();
    private readonly DispatcherTimer _metadataTimer = new() { Interval = TimeSpan.FromSeconds(20) };
    private readonly DispatcherTimer _sleepTimer = new();
    private readonly System.Threading.Mutex _singleInstanceMutex = new(true, @"Local\" + new string(Brand.Name.Where(char.IsLetterOrDigit).ToArray()) + ".Windows.Native", out _);
    private System.Windows.Forms.NotifyIcon? _trayIcon;
    private bool _allowClose;
    private HwndSource? _hwndSource;

    private const int WM_HOTKEY = 0x0312;
    private const int HOTKEY_PLAY_PAUSE = 0x4101;
    private const int HOTKEY_PREVIOUS = 0x4102;
    private const int HOTKEY_NEXT = 0x4103;
    private const uint MOD_NOREPEAT = 0x4000;
    private const uint VK_MEDIA_PLAY_PAUSE = 0xB3;
    private const uint VK_MEDIA_PREV_TRACK = 0xB1;
    private const uint VK_MEDIA_NEXT_TRACK = 0xB0;

    [DllImport("user32.dll")] private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);
    [DllImport("user32.dll")] private static extern bool UnregisterHotKey(IntPtr hWnd, int id);

    private readonly HashSet<string> _favorites = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<string, Station> _stationCache = new(StringComparer.OrdinalIgnoreCase);

    // Sender (laut.fm-Kennungen) der Website: kommen zur Laufzeit aus dem CMS (Apps → Layout → Sender), nicht aus dem Programm
    private readonly List<string> _owned = new();
    private string DefaultStationId => _owned.Count > 0 ? _owned[0] : "";

    private int _stationIndex;
    private Station? _currentStation;
    private string _currentAudioUrl = "";
    private CancellationTokenSource? _sleepCts;

    private string DataDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        Brand.Name);

    public MainWindow()
    {
        InitializeComponent();
        Title = Brand.Name;
        BrandFooter.Text = Brand.Name;
        if (!Brand.HasPodcast) PodcastNav.Visibility = Visibility.Collapsed;
        if (!Brand.HasCommunity) CommunityNav.Visibility = Visibility.Collapsed;
        if (!Brand.HasShops) ShopsNav.Visibility = Visibility.Collapsed;
        Loaded += MainWindow_Loaded;
        SourceInitialized += MainWindow_SourceInitialized;
        StateChanged += MainWindow_StateChanged;
        Closing += MainWindow_Closing;
        Closed += (_,_) => {
            _player.Dispose();
            CleanupTrayAndHotkeys();
            _singleInstanceMutex.Dispose();
        };

        _player.PlayingChanged += playing =>
            Dispatcher.Invoke(() => { ListenChanged(playing); PlayButton.Content = playing ? "❚❚" : "▶"; });

        _metadataTimer.Tick += async (_,_) => await RefreshNowPlayingAsync();
    }

    private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
    {
        var args = Environment.GetCommandLineArgs();
        var screenshotMode = Environment.GetEnvironmentVariable("APP_SCREENSHOT_MODE") == "1"
            || args.Any(a => string.Equals(a, "--screenshots", StringComparison.OrdinalIgnoreCase));

        if (!_singleInstanceMutex.WaitOne(TimeSpan.Zero, true))
        {
            if (!screenshotMode) MessageBox.Show(Brand.Name + " läuft bereits.", Brand.Name);
            _allowClose = true;
            Close();
            return;
        }

        if (!screenshotMode) SetupTray();
        Directory.CreateDirectory(DataDir);
        LoadFavorites();
        LoadDirFavs();
        TryLoadBranding();

        if (screenshotMode)
        {
            var exitCode = 0;
            try
            {
                await RefreshCmsRuntimeAsync();
                await ShowHomeAsync();
                await SelectStationAsync(DefaultStationId, false);
                await CaptureScreenshotSetAsync();
            }
            catch (Exception ex)
            {
                LogScreenshotCrash(ex);
                exitCode = 1;
            }
            finally
            {
                _allowClose = true;
                Close();
                Environment.Exit(exitCode);
            }
            return;
        }

        await RefreshCmsRuntimeAsync();
        await RefreshAppConfigAsync();
        await ShowHomeAsync();
        await SelectStationAsync(DefaultStationId, false);
        _metadataTimer.Start();
        _updateTimer.Tick += async (_, _) => { if (!_updating) await RefreshAppConfigAsync(); };
        _updateTimer.Start();
    }

    private static void LogScreenshotCrash(Exception ex)
    {
        try
        {
            var outDir = Environment.GetEnvironmentVariable("APP_SCREENSHOT_DIR");
            if (string.IsNullOrWhiteSpace(outDir)) outDir = AppContext.BaseDirectory;
            Directory.CreateDirectory(outDir);
            File.AppendAllText(Path.Combine(outDir, "windows-screenshot.log"),
                $"{DateTime.Now:O} FATAL in screenshot capture: {ex}\n");
        }
        catch
        {
            // Logging must never itself crash the process.
        }
    }


    private void MainWindow_SourceInitialized(object? sender, EventArgs e)
    {
        var handle = new WindowInteropHelper(this).Handle;
        _hwndSource = HwndSource.FromHwnd(handle);
        _hwndSource?.AddHook(WndProc);
        RegisterHotKey(handle, HOTKEY_PLAY_PAUSE, MOD_NOREPEAT, VK_MEDIA_PLAY_PAUSE);
        RegisterHotKey(handle, HOTKEY_PREVIOUS, MOD_NOREPEAT, VK_MEDIA_PREV_TRACK);
        RegisterHotKey(handle, HOTKEY_NEXT, MOD_NOREPEAT, VK_MEDIA_NEXT_TRACK);
    }

    private IntPtr WndProc(IntPtr hwnd, int msg, IntPtr wParam, IntPtr lParam, ref bool handled)
    {
        if (msg == WM_HOTKEY)
        {
            switch (wParam.ToInt32())
            {
                case HOTKEY_PLAY_PAUSE:
                    Play_Click(this, new RoutedEventArgs());
                    handled = true;
                    break;
                case HOTKEY_PREVIOUS:
                    Prev_Click(this, new RoutedEventArgs());
                    handled = true;
                    break;
                case HOTKEY_NEXT:
                    Next_Click(this, new RoutedEventArgs());
                    handled = true;
                    break;
            }
        }
        return IntPtr.Zero;
    }

    private void SetupTray()
    {
        _trayIcon = new System.Windows.Forms.NotifyIcon
        {
            Visible = true,
            Text = Brand.Name
        };

        try
        {
            var iconPath = Path.Combine(AppContext.BaseDirectory, "assets", "config", "app_icon.ico");
            if (File.Exists(iconPath)) _trayIcon.Icon = new System.Drawing.Icon(iconPath);
            else _trayIcon.Icon = System.Drawing.SystemIcons.Application;
        }
        catch
        {
            _trayIcon.Icon = System.Drawing.SystemIcons.Application;
        }

        var menu = new System.Windows.Forms.ContextMenuStrip();
        menu.Items.Add(Brand.Name + " öffnen", null, (_,_) => Dispatcher.Invoke(ShowFromTray));
        menu.Items.Add("Play / Pause", null, (_,_) => Dispatcher.Invoke(() => Play_Click(this, new RoutedEventArgs())));
        menu.Items.Add("Vorheriger Sender", null, (_,_) => Dispatcher.Invoke(() => Prev_Click(this, new RoutedEventArgs())));
        menu.Items.Add("Nächster Sender", null, (_,_) => Dispatcher.Invoke(() => Next_Click(this, new RoutedEventArgs())));
        menu.Items.Add(new System.Windows.Forms.ToolStripSeparator());
        menu.Items.Add("Beenden", null, (_,_) => Dispatcher.Invoke(() => { _allowClose = true; Close(); }));
        _trayIcon.ContextMenuStrip = menu;
        _trayIcon.DoubleClick += (_,_) => Dispatcher.Invoke(ShowFromTray);
    }

    private void ShowFromTray()
    {
        Show();
        if (WindowState == WindowState.Minimized) WindowState = WindowState.Normal;
        Activate();
        Topmost = true;
        Topmost = false;
        Focus();
    }

    private void MainWindow_StateChanged(object? sender, EventArgs e)
    {
        if (WindowState == WindowState.Minimized)
        {
            Hide();
            if (_trayIcon != null)
            {
                _trayIcon.BalloonTipTitle = Brand.Name;
                _trayIcon.BalloonTipText = "Die Wiedergabe läuft im Hintergrund weiter.";
                _trayIcon.ShowBalloonTip(1500);
            }
        }
    }

    private void MainWindow_Closing(object? sender, System.ComponentModel.CancelEventArgs e)
    {
        if (_allowClose) return;
        e.Cancel = true;
        Hide();
        if (_trayIcon != null)
        {
            _trayIcon.BalloonTipTitle = Brand.Name;
            _trayIcon.BalloonTipText = "Die App läuft im Infobereich weiter.";
            _trayIcon.ShowBalloonTip(1500);
        }
    }

    private void CleanupTrayAndHotkeys()
    {
        try
        {
            var handle = new WindowInteropHelper(this).Handle;
            if (handle != IntPtr.Zero)
            {
                UnregisterHotKey(handle, HOTKEY_PLAY_PAUSE);
                UnregisterHotKey(handle, HOTKEY_PREVIOUS);
                UnregisterHotKey(handle, HOTKEY_NEXT);
            }
        }
        catch { }

        if (_hwndSource != null) _hwndSource.RemoveHook(WndProc);
        if (_trayIcon != null)
        {
            _trayIcon.Visible = false;
            _trayIcon.Dispose();
            _trayIcon = null;
        }
    }

    private async Task CaptureScreenshotSetAsync()
    {
        var args = Environment.GetCommandLineArgs();
        var outDir = Environment.GetEnvironmentVariable("APP_SCREENSHOT_DIR");
        var argIndex = Array.FindIndex(args, a => string.Equals(a, "--screenshot-dir", StringComparison.OrdinalIgnoreCase));
        if (argIndex >= 0 && argIndex + 1 < args.Length && !string.IsNullOrWhiteSpace(args[argIndex + 1]))
            outDir = args[argIndex + 1];
        if (string.IsNullOrWhiteSpace(outDir))
            outDir = Path.Combine(AppContext.BaseDirectory, "app-screenshots");

        outDir = Path.GetFullPath(outDir);
        Directory.CreateDirectory(outDir);
        var bootLog = Path.Combine(outDir, "windows-screenshot.log");
        File.AppendAllText(bootLog, $"{DateTime.Now:O} ENTER screenshot mode; base={AppContext.BaseDirectory}; cwd={Environment.CurrentDirectory}; out={outDir}\n");

        Width = 1280;
        Height = 820;
        WindowState = WindowState.Normal;
        Show();
        Activate();

        async Task CaptureAsync(string fileName, Func<Task> showView, int settleMs)
        {
            var logPath = Path.Combine(outDir!, "windows-screenshot.log");
            try
            {
                await showView();
            }
            catch (Exception ex)
            {
                File.AppendAllText(logPath, $"{DateTime.Now:O} VIEW {fileName}: {ex}\n");
            }

            await Task.Delay(settleMs);
            UpdateLayout();
            await Dispatcher.InvokeAsync(() => { }, DispatcherPriority.Render);

            var target = Path.Combine(outDir!, fileName);
            try
            {
                SaveWindowPng(target);
            }
            catch (Exception ex)
            {
                File.AppendAllText(logPath, $"{DateTime.Now:O} WINDOW {fileName}: {ex}\n");
                SaveVisualPng(target, Content as Visual ?? this);
            }

            if (!File.Exists(target) || new FileInfo(target).Length < 1024)
                throw new IOException($"Screenshot wurde nicht korrekt erzeugt: {target}");

            File.AppendAllText(logPath, $"{DateTime.Now:O} OK {fileName} {new FileInfo(target).Length} bytes\n");
        }

        await CaptureAsync("windows-home.png", ShowHomeAsync, 900);
        await CaptureAsync("windows-stations.png", ShowStationsAsync, 1100);
        await CaptureAsync("windows-schedule.png", ShowScheduleAsync, 1200);
        await CaptureAsync("windows-community.png", () => { ShowCommunity(); return Task.CompletedTask; }, 600);
        await CaptureAsync("windows-podcast.png", ShowPodcastAsync, 1300);
        await CaptureAsync("windows-help.png", () => { ShowHelp(); return Task.CompletedTask; }, 600);
    }

    private void SaveWindowPng(string path)
    {
        UpdateLayout();
        SaveVisualPng(path, this);
    }

    private static void SaveVisualPng(string path, Visual visual)
    {
        var fe = visual as FrameworkElement;
        var width = Math.Max(1, (int)Math.Ceiling(fe?.ActualWidth > 1 ? fe.ActualWidth : 1280));
        var height = Math.Max(1, (int)Math.Ceiling(fe?.ActualHeight > 1 ? fe.ActualHeight : 820));
        var bmp = new RenderTargetBitmap(width, height, 96, 96, PixelFormats.Pbgra32);
        bmp.Render(visual);
        var encoder = new PngBitmapEncoder();
        encoder.Frames.Add(BitmapFrame.Create(bmp));
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        using var stream = File.Create(path);
        encoder.Save(stream);
        stream.Flush();
    }

    private static string ResolveCmsAssetUrl(string raw)
    {
        if (string.IsNullOrWhiteSpace(raw)) return "";
        raw = raw.Trim();
        if (raw.StartsWith("http://", StringComparison.OrdinalIgnoreCase) || raw.StartsWith("https://", StringComparison.OrdinalIgnoreCase)) return raw;
        if (raw.StartsWith("/")) return Brand.SiteBase + raw;
        return Brand.SiteBase + "/" + raw;
    }

    private async Task<BitmapImage?> LoadRemoteBitmapAsync(string url)
    {
        if (string.IsNullOrWhiteSpace(url)) return null;
        try
        {
            var bytes = await _cmsHttp.GetByteArrayAsync(url);
            using var ms = new MemoryStream(bytes);
            var bmp = new BitmapImage();
            bmp.BeginInit();
            bmp.CacheOption = BitmapCacheOption.OnLoad;
            bmp.StreamSource = ms;
            bmp.EndInit();
            bmp.Freeze();
            return bmp;
        }
        catch { return null; }
    }

    // Eigene Texte der Marke (Startseite, News-Titel) aus dem CMS (Bereich Marken > Texte)
    private string _heroTitle = "", _heroText = "", _newsTitle = "", _heroEyebrow = "";

    private async Task RefreshCmsRuntimeAsync()
    {
        try
        {
            var brandJson = await _cmsHttp.GetStringAsync(Brand.SiteBase + "/cms/api.php?action=brand");
            using var brandDoc = JsonDocument.Parse(brandJson);
            if (brandDoc.RootElement.TryGetProperty("overrides", out var ov) && ov.ValueKind == JsonValueKind.Object &&
                ov.TryGetProperty("portal", out var texts) && texts.ValueKind == JsonValueKind.Object)
            {
                string T(string key) => texts.TryGetProperty(key, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
                _heroTitle = T("hero_title");
                _heroEyebrow = T("hero_eyebrow");
                _heroText = T("hero_text");
                _newsTitle = T("news_title");
            }
        }
        catch { }

        try
        {
            var json = await _cmsHttp.GetStringAsync(Brand.SiteBase + "/cms/api.php?action=public");
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("config", out var cfg)) return;

            if (cfg.TryGetProperty("core_network", out var core) &&
                core.TryGetProperty("stations", out var stations) &&
                stations.ValueKind == JsonValueKind.Array)
            {
                var fresh = new List<string>();
                foreach (var item in stations.EnumerateArray())
                {
                    var id = (item.GetString() ?? "").Trim().ToLowerInvariant();
                    if (id.Length > 1 && !fresh.Contains(id, StringComparer.OrdinalIgnoreCase)) fresh.Add(id);
                }
                if (fresh.Count > 0)
                {
                    _owned.Clear();
                    _owned.AddRange(fresh);
                    _ownedDefault = new List<string>(fresh);
                    ApplyStationLayout();
                }
            }

            if (cfg.TryGetProperty("assistant", out var ai) && ai.ValueKind == JsonValueKind.Object)
            {
                if (ai.TryGetProperty("enabled", out var aiOn) && aiOn.ValueKind == JsonValueKind.False) AssistantTile.Visibility = Visibility.Collapsed;
                if (ai.TryGetProperty("name", out var aiName) && !string.IsNullOrWhiteSpace(aiName.GetString())) _aiName = aiName.GetString()!.Trim();
                if (ai.TryGetProperty("greeting", out var aiGreet) && !string.IsNullOrWhiteSpace(aiGreet.GetString())) _aiGreeting = aiGreet.GetString()!.Trim();
                if (ai.TryGetProperty("features", out var aiFeat) && aiFeat.ValueKind == JsonValueKind.Object &&
                    aiFeat.TryGetProperty("studiomail", out var aiMail)) _aiMail = aiMail.ValueKind != JsonValueKind.False;
            }

            // CMS-Branding der Website (windows_logo, sonst android_inapp_logo)
            if (cfg.TryGetProperty("branding", out var branding))
            {
                var raw = branding.TryGetProperty("windows_logo", out var winLogo) ? winLogo.GetString() ?? "" : "";
                if (string.IsNullOrWhiteSpace(raw) && branding.TryGetProperty("android_inapp_logo", out var appLogo)) raw = appLogo.GetString() ?? "";
                var url = ResolveCmsAssetUrl(raw);
                var bmp = await LoadRemoteBitmapAsync(url);
                if (bmp != null)
                {
                    LogoImage.Source = bmp;
                    if (NowCover.Source == null) NowCover.Source = bmp;
                }
            }
        }
        catch { }
    }

    private void TryLoadBranding()
    {
        var candidates = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "assets", "config", "logo-lockup.png"),
            Path.Combine(AppContext.BaseDirectory, "assets", "config", "app_icon.png")
        };

        foreach (var path in candidates)
        {
            if (!File.Exists(path)) continue;
            try
            {
                LogoImage.Source = new BitmapImage(new Uri(path));
                NowCover.Source = new BitmapImage(new Uri(path));
                return;
            }
            catch { }
        }
    }

    private Border Card()
    {
        return new Border
        {
            Background = (Brush)FindResource("CardBrush"),
            BorderBrush = (Brush)FindResource("LineBrush"),
            BorderThickness = new Thickness(1),
            CornerRadius = new CornerRadius(18),
            Padding = new Thickness(18),
            Margin = new Thickness(0,0,0,14)
        };
    }

    private TextBlock Txt(string text, double size=14, Brush? brush=null, FontWeight? weight=null)
    {
        return new TextBlock
        {
            Text = text,
            FontSize = size,
            Foreground = brush ?? (Brush)FindResource("TextBrush"),
            FontWeight = weight ?? FontWeights.Normal,
            TextWrapping = TextWrapping.Wrap
        };
    }

    /// <summary>Zeigt einen Hinweis mit Datum, wenn die Daten aus dem lokalen Speicher stammen (kein Internet).</summary>
    private void AddOfflineNote<T>(ApiResult<T> result)
    {
        if (!result.FromCache) return;
        var note = Card();
        note.Padding = new Thickness(14, 10, 14, 10);
        note.Child = Txt($"Offline – zuletzt gespeicherter Stand vom {result.SavedAt:dd.MM.yyyy HH:mm}. Sobald du wieder online bist, wird aktualisiert.", 12, (Brush)FindResource("MutedBrush"));
        ContentHost.Children.Add(note);
    }

    private void Page(string eyebrow, string title)
    {
        PageEyebrow.Text = eyebrow;
        PageTitle.Text = title;
        ContentHost.Children.Clear();
    }

    private async Task ShowHomeAsync()
    {
        if (await TryRenderRemoteHomeAsync()) return;
        Page(Brand.Name.ToUpperInvariant(), _heroTitle.Length > 0 ? _heroTitle : "Willkommen bei " + Brand.Name);

        var hero = Card();
        var stack = new StackPanel();
        stack.Children.Add(Txt(_heroEyebrow.Length > 0 ? _heroEyebrow.ToUpperInvariant() : Brand.HasDirectory ? "DEIN RADIOVERZEICHNIS" : ("WILLKOMMEN BEI " + Brand.Name).ToUpperInvariant(), 11, (Brush)FindResource("CyanBrush"), FontWeights.Bold));
        stack.Children.Add(Txt("Alle Streams an einem Ort.", 30, null, FontWeights.Bold));
        stack.Children.Add(new TextBlock
        {
            Text = _heroText.Length > 0 ? _heroText : "Alle Streams von " + Brand.Name + " an einem Ort. Hör rein, entdecke Sender und nutze Favoriten und Sendeplan direkt in der Windows-App.",
            Foreground = (Brush)FindResource("MutedBrush"),
            FontSize = 15,
            TextWrapping = TextWrapping.Wrap,
            Margin = new Thickness(0,10,0,18)
        });

        var actions = new StackPanel { Orientation = Orientation.Horizontal };
        var listen = new Button { Content = "▶ Jetzt hören", Background = (Brush)FindResource("PrimaryBrush"), Foreground = Brushes.Black };
        listen.Click += async (_,_) => { await ShowStationsAsync(); };
        var surprise = new Button { Content = "Überrasch mich" };
        surprise.Click += async (_,_) => await SurpriseAsync();
        actions.Children.Add(listen);
        actions.Children.Add(surprise);
        stack.Children.Add(actions);
        hero.Child = stack;
        ContentHost.Children.Add(hero);

        ContentHost.Children.Add(Txt("VORGESCHLAGEN FÜR DICH", 12, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
        foreach (var id in _owned.Take(4))
        {
            try { ContentHost.Children.Add(await BuildStationCardAsync(id)); } catch { }
        }
    }

    private async Task ShowStationsAsync()
    {
        Page(Brand.Name.ToUpperInvariant(), "Sender");
        foreach (var id in _owned)
        {
            try { ContentHost.Children.Add(await BuildStationCardAsync(id)); }
            catch
            {
                var c = Card();
                c.Child = Txt(id, 16, null, FontWeights.Bold);
                ContentHost.Children.Add(c);
            }
        }
    }

    private async Task<Border> BuildStationCardAsync(string id)
    {
        var station = await GetStationAsync(id);
        var card = Card();
        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(82) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

        var cover = new Border { Width = 70, Height = 70, CornerRadius = new CornerRadius(12), ClipToBounds = true, Background=(Brush)FindResource("Card2Brush") };
        var img = new Image { Stretch = Stretch.UniformToFill };
        SetRemoteImage(img, station.Cover);
        cover.Child = img;
        grid.Children.Add(cover);

        var copy = new StackPanel { Margin = new Thickness(12,0,12,0), VerticalAlignment = VerticalAlignment.Center };
        copy.Children.Add(Txt(station.Name, 17, null, FontWeights.Bold));
        copy.Children.Add(Txt(station.GenreText, 12, (Brush)FindResource("PrimaryBrush"), FontWeights.SemiBold));
        if (!string.IsNullOrWhiteSpace(station.Description))
        {
            var d = Txt(station.Description.Length > 150 ? station.Description[..147] + "…" : station.Description, 12, (Brush)FindResource("MutedBrush"));
            d.Margin = new Thickness(0,4,0,0);
            copy.Children.Add(d);
        }
        Grid.SetColumn(copy,1);
        grid.Children.Add(copy);

        var buttons = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
        var play = new Button { Content = "▶ Hören" };
        play.Click += async (_,_) => await SelectStationAsync(id, true);
        var fav = new Button { Content = _favorites.Contains(id) ? "★" : "☆" };
        fav.Click += (_,_) => { ToggleFavorite(id); fav.Content = _favorites.Contains(id) ? "★" : "☆"; };
        buttons.Children.Add(play);
        buttons.Children.Add(fav);
        Grid.SetColumn(buttons,2);
        grid.Children.Add(buttons);

        card.Child = grid;
        return card;
    }

    private async Task<Station> GetStationAsync(string id)
    {
        if (_stationCache.TryGetValue(id, out var cached)) return cached;
        var s = await _api.GetStationAsync(id);
        _stationCache[id] = s;
        return s;
    }

    private bool _podcastMode;

    /// <summary>Quelle des laufenden Senders und Homepage-Knopf (nur Marken mit Verzeichnis).</summary>
    private void UpdateSourceUi()
    {
        if (!Brand.HasDirectory || _podcastMode || _currentStation == null)
        {
            NowSource.Visibility = Visibility.Collapsed;
            HomeButton.Visibility = Visibility.Collapsed;
            return;
        }
        var own = _currentWorld == null && _owned.Contains(_currentStation.Id);
        NowSource.Text = "Quelle: " + (_currentWorld != null ? "Radio Browser" : "laut.fm");
        NowSource.Visibility = Visibility.Visible;
        HomeButton.Visibility = _currentWorld != null && string.IsNullOrWhiteSpace(_currentWorld.Link) ? Visibility.Collapsed : Visibility.Visible;
    }

    /// <summary>Fremdsender: Homepage des Senders; eigene Sender: die Website der Marke.</summary>
    private void StationHome_Click(object sender, RoutedEventArgs e)
    {
        if (_currentStation == null) return;
        string url;
        if (_currentWorld != null) url = _currentWorld.Link;
        else if (_owned.Contains(_currentStation.Id))
            url = Brand.Website;
        else url = "https://laut.fm/" + Uri.EscapeDataString(_currentStation.Id);
        if (!Uri.TryCreate(url, UriKind.Absolute, out var u) || u.Scheme != Uri.UriSchemeHttps && u.Scheme != Uri.UriSchemeHttp) return;
        Process.Start(new ProcessStartInfo(u.AbsoluteUri) { UseShellExecute = true });
    }

    private async Task SelectStationAsync(string id, bool play)
    {
        _currentWorld = null;
        _podcastMode = false;
        _dirQueue.Clear();
        _stationIndex = Math.Max(0, _owned.IndexOf(id));
        _currentStation = await GetStationAsync(id);
        _currentAudioUrl = $"https://stream.laut.fm/{id}";
        NowStation.Text = _currentStation.Name;
        NowTitle.Text = "Live-Stream";
        SetRemoteImage(NowCover, _currentStation.Cover);
        UpdateSourceUi();
        await RefreshNowPlayingAsync();

        if (play)
        {
            try { _player.PlayUrl(_currentAudioUrl); }
            catch (Exception ex) { MessageBox.Show($"Stream konnte nicht gestartet werden:\n{ex.Message}", Brand.Name); }
        }
    }

    private async Task RefreshNowPlayingAsync()
    {
        if (_currentWorld != null)
        {
            await RefreshWorldMetaAsync();
            return;
        }
        if (_currentStation == null) return;
        try
        {
            var (artist,title) = await _api.GetNowPlayingAsync(_currentStation.Id);
            NowTitle.Text = string.IsNullOrWhiteSpace(artist) ? title : $"{artist} – {title}";
        }
        catch { }
    }

    private async Task ShowScheduleAsync()
    {
        if (_currentStation == null) await SelectStationAsync(DefaultStationId, false);
        Page("PROGRAMM", $"Sendeplan · {_currentStation!.Name}");

        ApiResult<List<ScheduleSlot>>? res = null;
        try { res = await _api.GetWeekScheduleAsync(_currentStation.Id); }
        catch { }

        if (res == null)
        {
            var failed = Card();
            failed.Child = Txt("Der Sendeplan konnte gerade nicht geladen werden und es gibt noch keinen gespeicherten Stand.", 15, (Brush)FindResource("MutedBrush"));
            ContentHost.Children.Add(failed);
            return;
        }
        AddOfflineNote(res);

        var all = res.Data;
        var today = ((int)DateTime.Now.DayOfWeek + 6) % 7; // Montag = 0 ... Sonntag = 6
        var shortNames = new[] { "Mo", "Di", "Mi", "Do", "Fr", "Sa", "So" };
        var chips = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 14) };
        var list = new StackPanel();
        var buttons = new List<Button>();

        void Render(int day)
        {
            for (var i = 0; i < buttons.Count; i++)
            {
                buttons[i].Background = i == day ? (Brush)FindResource("PrimaryBrush") : (Brush)FindResource("Card2Brush");
                buttons[i].Foreground = i == day ? Brushes.Black : (Brush)FindResource("TextBrush");
            }
            list.Children.Clear();
            var rows = all.Where(x => x.Day == day).ToList();
            if (rows.Count == 0)
            {
                var empty = Card();
                empty.Child = Txt("An diesem Tag läuft der Nonstop-Mix ohne feste Sendungen.", 15, (Brush)FindResource("MutedBrush"));
                list.Children.Add(empty);
                return;
            }
            var now = DateTime.Now;
            var nowH = now.Hour + now.Minute / 60.0;
            foreach (var item in rows)
            {
                var live = day == today && (item.Start < item.End
                    ? nowH >= item.Start && nowH < item.End
                    : (item.End == 0 ? nowH >= item.Start : nowH >= item.Start || nowH < item.End));
                var card = Card();
                if (live) card.BorderBrush = (Brush)FindResource("CyanBrush");
                var s = new StackPanel();
                s.Children.Add(Txt(live ? "● LÄUFT JETZT · " + item.Time : item.Time, 12, (Brush)FindResource(live ? "CyanBrush" : "PrimaryBrush"), FontWeights.Bold));
                s.Children.Add(Txt(item.Title, 17, null, FontWeights.Bold));
                if (!string.IsNullOrWhiteSpace(item.Description))
                    s.Children.Add(Txt(item.Description, 13, (Brush)FindResource("MutedBrush")));
                card.Child = s;
                list.Children.Add(card);
            }
        }

        for (var i = 0; i < 7; i++)
        {
            var day = i;
            var b = new Button { Content = shortNames[i], Width = 54, Height = 38, Margin = new Thickness(0, 0, 8, 0) };
            b.Click += (_, _) => Render(day);
            buttons.Add(b);
            chips.Children.Add(b);
        }
        ContentHost.Children.Add(chips);
        ContentHost.Children.Add(list);
        Render(today);
    }

    private async Task ShowPodcastAsync()
    {
        Page("PODCAST", "Der Podcast von " + Brand.Name);
        List<PodcastEpisode> episodes;
        try { var res = await _api.GetPodcastsAsync(); episodes = res.Data; AddOfflineNote(res); }
        catch { episodes = new(); }

        if (episodes.Count == 0)
        {
            var c = Card();
            c.Child = Txt("Aktuell sind keine Podcast-Folgen verfügbar.", 15, (Brush)FindResource("MutedBrush"));
            ContentHost.Children.Add(c);
            return;
        }

        foreach (var ep in episodes)
        {
            var card = Card();
            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(76) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var img = new Image { Width=64, Height=64, Stretch=Stretch.UniformToFill };
            SetRemoteImage(img, ep.Image);
            grid.Children.Add(img);

            var copy = new StackPanel { Margin = new Thickness(12,0,12,0) };
            copy.Children.Add(Txt(ep.Title, 16, null, FontWeights.Bold));
            copy.Children.Add(Txt(ep.Date, 11, (Brush)FindResource("PrimaryBrush")));
            if (!string.IsNullOrWhiteSpace(ep.Description))
                copy.Children.Add(Txt(ep.Description.Length > 160 ? ep.Description[..157] + "…" : ep.Description, 12, (Brush)FindResource("MutedBrush")));
            Grid.SetColumn(copy,1);
            grid.Children.Add(copy);

            var b = new Button { Content = "▶ Abspielen" };
            b.Click += (_,_) =>
            {
                if (string.IsNullOrWhiteSpace(ep.AudioUrl)) return;
                _currentAudioUrl = ep.AudioUrl;
                _podcastMode = true;
                UpdateSourceUi();
                NowStation.Text = "Podcast · " + Brand.Name;
                NowTitle.Text = ep.Title;
                SetRemoteImage(NowCover, ep.Image);
                try { _player.PlayUrl(ep.AudioUrl); } catch { }
            };
            Grid.SetColumn(b,2);
            grid.Children.Add(b);
            card.Child = grid;
            ContentHost.Children.Add(card);
        }
    }

    private async Task ShowSearchAsync(string? initial = null)
    {
        if (Brand.HasDirectory)
        {
            await ShowDirectoryAsync(initial);
            return;
        }
        Page("ENTDECKEN", "Unsere Sender");
        var input = new TextBox
        {
            Text = initial ?? "",
            Height = 46,
            FontSize = 15,
            Padding = new Thickness(12,0,12,0),
            Background = (Brush)FindResource("CardBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            BorderBrush = (Brush)FindResource("LineBrush")
        };
        ContentHost.Children.Add(input);

        var results = new StackPanel { Margin = new Thickness(0,14,0,0) };
        ContentHost.Children.Add(results);

        async Task Run()
        {
            results.Children.Clear();
            var q = input.Text.Trim();
            if (q.Length < 2) return;
            // Ohne Radioverzeichnis: nur die eigenen Sender durchsuchen
            var names = _owned.ToList();
            foreach (var id in names.Where(x => x.Contains(q, StringComparison.OrdinalIgnoreCase)
                || (_stationCache.TryGetValue(x, out var st) && st.Name.Contains(q, StringComparison.OrdinalIgnoreCase))).Take(40))
            {
                try { results.Children.Add(await BuildStationCardAsync(id)); } catch { }
            }
        }

        input.KeyDown += async (_,e) => { if (e.Key == Key.Enter) await Run(); };
        if (!string.IsNullOrWhiteSpace(initial)) await Run();
        input.Focus();
    }

    private void ShowFavorites()
    {
        Page("DEINE AUSWAHL", "Favoriten");
        if (_favorites.Count == 0)
        {
            var c = Card();
            c.Child = Txt("Noch keine Sender als Favorit gespeichert.", 15, (Brush)FindResource("MutedBrush"));
            ContentHost.Children.Add(c);
            return;
        }

        _ = Dispatcher.InvokeAsync(async () =>
        {
            var worldFavs = _favorites.Where(x => x.StartsWith("world:", StringComparison.Ordinal)).Select(x => _dirFavItems.TryGetValue(x, out var d) ? d : null).Where(d => d != null).Cast<DirItem>().ToList();
            foreach (var id in _favorites.Where(x => !x.StartsWith("world:", StringComparison.Ordinal)).ToList())
            {
                try { ContentHost.Children.Add(await BuildStationCardAsync(id)); } catch { }
            }
            foreach (var it in worldFavs) ContentHost.Children.Add(BuildDirectoryRow(it, worldFavs));
        });
    }

    private void ShowCommunity()
    {
        Page("MITMACHEN", "Voting & Community");
        if (!Brand.HasCommunity)
        {
            var none = Card();
            none.Child = Txt("Für diese App sind keine Community-Funktionen eingerichtet.", 15, (Brush)FindResource("MutedBrush"));
            ContentHost.Children.Add(none);
            return;
        }
        var cb = Brand.CommunityBase;
        var sid = Uri.EscapeDataString(_currentStation?.Id ?? DefaultStationId);
        var items = new[]
        {
            ("Netzwerk-Song-Voting","Songs aus dem gesamten Netzwerk bewerten",cb + "voting.html?mode=network"),
            ("Sender-Song-Voting","Songs des aktuellen Senders bewerten",$"{cb}voting.html?mode=station&station={sid}"),
            ("Musikwunsch","Deinen Songwunsch senden",$"{cb}wunsch.html?station={sid}"),
            ("Studiomail","Gruß oder Feedback ans Studio",cb + "studiomail/widget-form.html"),
            ("Voicemail","Sprachnachricht aufnehmen",cb + "voicemsg.html")
        };

        foreach (var (title,desc,url) in items)
        {
            var c = Card();
            var s = new StackPanel();
            s.Children.Add(Txt(title, 17, null, FontWeights.Bold));
            s.Children.Add(Txt(desc, 12, (Brush)FindResource("MutedBrush")));
            var b = new Button { Content = "Öffnen", HorizontalAlignment = HorizontalAlignment.Left, Margin = new Thickness(0,10,0,0) };
            b.Click += (_,_) => Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            s.Children.Add(b);
            c.Child = s;
            ContentHost.Children.Add(c);
        }
    }


    private async Task ShowNewsAsync()
    {
        Page("NEWS & MAGAZIN", _newsTitle.Length > 0 ? _newsTitle : "Aktuelles von " + Brand.Name);

        var socialBtn = new Button { Content = "TikTok & Instagram ansehen", HorizontalAlignment = HorizontalAlignment.Left, Margin = new Thickness(0,0,0,14) };
        socialBtn.Click += (_,_) => ShowWebPopup(Brand.Name + " · Social Wall", "SOCIAL WALL", new Uri(Brand.SiteBase + "/social-wall.html"));
        ContentHost.Children.Add(socialBtn);

        List<NewsArticle> articles;
        try { var res = await _api.GetNewsAsync(); articles = res.Data; AddOfflineNote(res); }
        catch { articles = new(); }

        if (articles.Count == 0)
        {
            var c = Card();
            c.Child = Txt("Aktuell gibt es keine veröffentlichten News.", 15, (Brush)FindResource("MutedBrush"));
            ContentHost.Children.Add(c);
            return;
        }

        foreach (var a in articles)
        {
            var card = Card();
            card.Cursor = System.Windows.Input.Cursors.Hand;
            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(76) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            bool showThumb = !string.IsNullOrWhiteSpace(a.ImageUrl) &&
                (a.ImageMode == "thumbnail" || a.ImageMode == "both" || string.IsNullOrEmpty(a.ImageMode));
            if (showThumb)
            {
                var img = new Image { Width = 64, Height = 64, Stretch = Stretch.UniformToFill };
                SetRemoteImage(img, a.ImageUrl);
                grid.Children.Add(img);
            }

            var copy = new StackPanel { Margin = new Thickness(showThumb ? 12 : 0,0,0,0) };
            Grid.SetColumn(copy,1);
            copy.Children.Add(Txt(a.Category.ToUpperInvariant(), 10, (Brush)FindResource("PrimaryBrush"), FontWeights.Bold));
            copy.Children.Add(Txt(a.Title, 16, null, FontWeights.Bold));
            if (!string.IsNullOrWhiteSpace(a.Excerpt))
                copy.Children.Add(Txt(a.Excerpt.Length > 140 ? a.Excerpt[..137] + "…" : a.Excerpt, 12, (Brush)FindResource("MutedBrush")));
            var date = NewsDate(string.IsNullOrEmpty(a.PublishedAt) ? a.CreatedAt : a.PublishedAt);
            if (!string.IsNullOrEmpty(date))
                copy.Children.Add(Txt(date, 11, (Brush)FindResource("MutedBrush")));
            grid.Children.Add(copy);

            card.Child = grid;
            card.MouseLeftButtonUp += (_,_) => ShowNewsArticle(a);
            ContentHost.Children.Add(card);
        }
    }

    private static string NewsDate(string raw)
    {
        if (string.IsNullOrEmpty(raw) || raw.Length < 10) return "";
        var datePart = raw[..10];
        var parts = datePart.Split('-');
        return parts.Length == 3 ? $"{parts[2]}.{parts[1]}.{parts[0]}" : datePart;
    }

    private static string EscapeHtml(string s) =>
        string.IsNullOrEmpty(s) ? "" : s.Replace("&","&amp;").Replace("<","&lt;").Replace(">","&gt;").Replace("\"","&quot;");

    private static string YoutubeIdFromUrl(string url)
    {
        if (string.IsNullOrEmpty(url)) return "";
        var m = System.Text.RegularExpressions.Regex.Match(url,
            @"(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,20})",
            System.Text.RegularExpressions.RegexOptions.IgnoreCase);
        return m.Success ? m.Groups[1].Value : "";
    }

    private string NewsArticleHtml(NewsArticle a)
    {
        var meta = NewsDate(string.IsNullOrEmpty(a.PublishedAt) ? a.CreatedAt : a.PublishedAt);
        if (!string.IsNullOrWhiteSpace(a.Author)) meta += (meta.Length > 0 ? " · " : "") + a.Author;
        var showArticleImage = !string.IsNullOrWhiteSpace(a.ImageUrl) && (a.ImageMode == "article" || a.ImageMode == "both");

        var media = new System.Text.StringBuilder();
        var yt = YoutubeIdFromUrl(a.VideoUrl);
        if (yt.Length > 0)
            media.Append("<div class=\"media\"><iframe src=\"https://www.youtube-nocookie.com/embed/").Append(yt)
                .Append("\" allow=\"autoplay; encrypted-media; picture-in-picture; fullscreen\" allowfullscreen></iframe></div>");
        if (!string.IsNullOrWhiteSpace(a.EmbedHtml)) media.Append("<div class=\"media\">").Append(a.EmbedHtml).Append("</div>");

        var html = new System.Text.StringBuilder();
        html.Append("<!doctype html><html><head><meta charset=\"utf-8\">")
            .Append("<style>")
            .Append("body{margin:0;padding:22px;background:#07091c;color:#eef0fa;font-family:Segoe UI,sans-serif;line-height:1.68;font-size:15px}")
            .Append("h1,h2,h3{color:#fff}a{color:#00f2ea}")
            .Append(".kicker{color:#b57cff;font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px}")
            .Append("h1.title{font-size:2rem;line-height:1.2;margin:8px 0 6px;color:#fff}")
            .Append(".meta{color:#9ea6c7;font-size:.82rem;margin-bottom:16px}")
            .Append("img{max-width:100%;height:auto;border-radius:14px}")
            .Append(".hero{width:100%;border-radius:16px;object-fit:cover;max-height:340px;margin-bottom:16px}")
            .Append("blockquote{margin:1.2em 0;padding:10px 14px;border-left:3px solid #b57cff;background:rgba(181,124,255,.08);border-radius:0 10px 10px 0}")
            .Append(".media{margin:18px 0;border-radius:14px;overflow:hidden;background:#000}")
            .Append(".media iframe{display:block;width:100%;aspect-ratio:16/9;border:0;min-height:280px}")
            .Append(".more{display:inline-block;margin-top:22px;padding:12px 20px;border-radius:12px;background:#b57cff;color:#120a27;font-weight:800;text-decoration:none}")
            .Append("</style></head><body>")
            .Append("<div class=\"kicker\">").Append(EscapeHtml(a.Category)).Append("</div>")
            .Append("<h1 class=\"title\">").Append(EscapeHtml(a.Title)).Append("</h1>");
        if (meta.Length > 0) html.Append("<div class=\"meta\">").Append(EscapeHtml(meta)).Append("</div>");
        if (showArticleImage) html.Append("<img class=\"hero\" src=\"").Append(EscapeHtml(a.ImageUrl)).Append("\">");
        html.Append(media).Append(a.BodyHtml);
        if (!string.IsNullOrWhiteSpace(a.ExternalUrl))
            html.Append("<a class=\"more\" href=\"").Append(EscapeHtml(a.ExternalUrl)).Append("\">Mehr erfahren ↗</a>");
        html.Append("</body></html>");
        return html.ToString();
    }

    private async void ShowNewsArticle(NewsArticle a)
    {
        var win = new Window
        {
            Title = Brand.Name + " · " + a.Title,
            Width = 1000,
            Height = 800,
            MinWidth = 640,
            MinHeight = 480,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner
        };

        var root = new Grid();
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

        var top = new Grid { Margin = new Thickness(16,12,16,10) };
        top.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        top.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
        top.Children.Add(Txt("NEWS & MAGAZIN", 11, (Brush)FindResource("CyanBrush"), FontWeights.Bold));
        var close = new Button { Content = "Schließen", Height = 40, MinWidth = 96 };
        close.Click += (_,_) => win.Close();
        Grid.SetColumn(close,1);
        top.Children.Add(close);

        var web = new WebView2 { Margin = new Thickness(12,0,12,12), DefaultBackgroundColor = System.Drawing.Color.FromArgb(7,10,28) };
        Grid.SetRow(web,1);
        root.Children.Add(top);
        root.Children.Add(web);
        win.Content = root;

        win.Loaded += async (_,_) =>
        {
            try
            {
                await web.EnsureCoreWebView2Async();
                web.CoreWebView2.Settings.AreDefaultContextMenusEnabled = false;
                web.CoreWebView2.Settings.AreDevToolsEnabled = false;
                web.CoreWebView2.NewWindowRequested += (_, args) => { args.Handled = true; };
                web.NavigateToString(NewsArticleHtml(a));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Der Beitrag konnte nicht geladen werden.\n\n" + ex.Message, Brand.Name + " · News & Magazin");
                win.Close();
            }
        };

        win.ShowDialog();
    }

    private async void ShowWebPopup(string windowTitle, string eyebrow, Uri url)
    {
        var win = new Window
        {
            Title = windowTitle,
            Width = 1180,
            Height = 820,
            MinWidth = 860,
            MinHeight = 620,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner
        };

        var root = new Grid();
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

        var top = new Grid { Margin = new Thickness(16,12,16,10) };
        top.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        top.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

        var title = new StackPanel();
        title.Children.Add(Txt(eyebrow, 11, (Brush)FindResource("CyanBrush"), FontWeights.Bold));
        title.Children.Add(Txt(windowTitle, 20, null, FontWeights.Bold));
        top.Children.Add(title);

        var close = new Button { Content = "Schließen", Height = 40, MinWidth = 96 };
        close.Click += (_,_) => win.Close();
        Grid.SetColumn(close,1);
        top.Children.Add(close);

        var web = new WebView2
        {
            Margin = new Thickness(12,0,12,12),
            DefaultBackgroundColor = System.Drawing.Color.FromArgb(7,10,28)
        };
        Grid.SetRow(web,1);
        root.Children.Add(top);
        root.Children.Add(web);
        win.Content = root;

        win.Loaded += async (_,_) =>
        {
            try
            {
                await web.EnsureCoreWebView2Async();
                web.CoreWebView2.Settings.AreDefaultContextMenusEnabled = false;
                web.CoreWebView2.Settings.AreDevToolsEnabled = false;
                web.CoreWebView2.NewWindowRequested += (_, args) => { args.Handled = true; };
                web.NavigationStarting += (_, args) =>
                {
                    if (Uri.TryCreate(args.Uri, UriKind.Absolute, out var uri) && !Brand.IsOwnHost(uri.Host))
                    {
                        args.Cancel = true;
                    }
                };
                web.Source = url;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Die Seite konnte nicht geladen werden.\n\n" + ex.Message, windowTitle);
                win.Close();
            }
        };

        win.ShowDialog();
    }

    private void ShowFanshops()
    {
        Page("SHOPS", "Shops");
        var shops = Brand.Shops.ToArray();

        foreach (var (title,desc,url) in shops)
        {
            var card = Card();
            var stack = new StackPanel();
            stack.Children.Add(Txt(title, 18, null, FontWeights.Bold));
            stack.Children.Add(Txt(desc, 13, (Brush)FindResource("MutedBrush")));
            var open = new Button { Content = "Shop öffnen", HorizontalAlignment = HorizontalAlignment.Left, Margin = new Thickness(0,12,0,0) };
            open.Click += (_,_) => Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            stack.Children.Add(open);
            card.Child = stack;
            ContentHost.Children.Add(card);
        }
    }

    private void ShowHelp()
    {
        Page("HILFE & INFO", Brand.Name + " für Windows");
        var items = new[]
        {
            ("Player","Sender unten auswählen oder über „Sender“ starten. Vor/Zurück wechselt zwischen den Sendern."),
            ("Überrasch mich","Wählt zufällig einen Sender und startet ihn direkt."),
            ("Favoriten","Favoriten werden lokal auf diesem Windows-Gerät gespeichert."),
            ("Sendeplan","Zeigt den heutigen laut.fm-Sendeplan des aktuell ausgewählten Senders."),
                                    ("News & Social","Magazin-News und Social-Inhalte der Website sind in einem gemeinsamen Bereich gebündelt."),
            ("Sleep-Timer","„Sleep 30“ stoppt die Wiedergabe nach 30 Minuten.")
        };

        foreach (var (title,desc) in items)
        {
            var card = Card();
            var stack = new StackPanel();
            stack.Children.Add(Txt(title, 16, null, FontWeights.Bold));
            stack.Children.Add(Txt(desc, 13, (Brush)FindResource("MutedBrush")));
            card.Child = stack;
            ContentHost.Children.Add(card);
        }

        var links = Card();
        var linkStack = new StackPanel();
        linkStack.Children.Add(Txt("Rechtliches & Web", 16, null, FontWeights.Bold));
        foreach (var (label,url) in new[]
        {
            ("Radioportal",Brand.Website),
            ("Impressum",Brand.SiteBase + "/#impressum"),
            ("Datenschutz",Brand.SiteBase + "/#datenschutz"),
            ("laut.fm Nutzungsbedingungen","https://laut.fm/pages/terms_and_conditions")
        })
        {
            var b = new Button { Content = label, HorizontalAlignment = HorizontalAlignment.Left };
            b.Click += (_,_) => Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            linkStack.Children.Add(b);
        }
        links.Child = linkStack;
        ContentHost.Children.Add(links);
    }

    private void ShowFullPlayer()
    {
        if (_currentStation == null) return;

        var win = new Window
        {
            Title = Brand.Name + " · Player",
            Width = 620,
            Height = 640,
            MinWidth = 520,
            MinHeight = 560,
            Background = (Brush)FindResource("BgBrush"),
            Foreground = (Brush)FindResource("TextBrush"),
            Owner = this,
            WindowStartupLocation = WindowStartupLocation.CenterOwner
        };

        var root = new Grid { Margin = new Thickness(26) };
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

        var artBorder = new Border
        {
            Width = 260,
            Height = 260,
            CornerRadius = new CornerRadius(24),
            ClipToBounds = true,
            Background = (Brush)FindResource("CardBrush"),
            HorizontalAlignment = HorizontalAlignment.Center
        };
        var art = new Image { Stretch = Stretch.UniformToFill };
        SetRemoteImage(art, _currentStation.Cover);
        artBorder.Child = art;
        Grid.SetRow(artBorder,0);
        root.Children.Add(artBorder);

        var station = Txt(_currentStation.Name, 24, null, FontWeights.Bold);
        station.HorizontalAlignment = HorizontalAlignment.Center;
        station.Margin = new Thickness(0,20,0,4);
        Grid.SetRow(station,1);
        root.Children.Add(station);

        var now = Txt(NowTitle.Text, 15, (Brush)FindResource("MutedBrush"));
        now.HorizontalAlignment = HorizontalAlignment.Center;
        now.TextAlignment = TextAlignment.Center;
        now.Margin = new Thickness(20,0,20,18);
        Grid.SetRow(now,2);
        root.Children.Add(now);

        var controls = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Center };
        var prev = new Button { Content = "◀", Width = 54, Height = 50 };
        prev.Click += async (sender,args) => { Prev_Click(sender, new RoutedEventArgs()); await Task.Delay(400); win.Close(); ShowFullPlayer(); };
        var play = new Button { Content = _player.IsPlaying ? "❚❚" : "▶", Width = 64, Height = 54, Background = (Brush)FindResource("Card2Brush"), BorderBrush = (Brush)FindResource("CyanBrush") };
        play.Click += (sender,args) => { Play_Click(sender, new RoutedEventArgs()); play.Content = _player.IsPlaying ? "❚❚" : "▶"; };
        var next = new Button { Content = "▶▶", Width = 58, Height = 50 };
        next.Click += async (sender,args) => { Next_Click(sender, new RoutedEventArgs()); await Task.Delay(400); win.Close(); ShowFullPlayer(); };
        var fav = new Button { Content = _favorites.Contains(_currentStation.Id) ? "★ Favorit" : "☆ Favorit", Height = 50 };
        fav.Click += (_,_) => { ToggleFavorite(_currentStation.Id); fav.Content = _favorites.Contains(_currentStation.Id) ? "★ Favorit" : "☆ Favorit"; };
        controls.Children.Add(prev);
        controls.Children.Add(play);
        controls.Children.Add(next);
        controls.Children.Add(fav);
        Grid.SetRow(controls,3);
        root.Children.Add(controls);

        win.Content = root;
        win.ShowDialog();
    }

    private async Task SurpriseAsync()
    {
        if (Brand.HasDirectory)
        {
            var foreign = _surpriseNextForeign;
            _surpriseNextForeign = !_surpriseNextForeign;
            if (foreign && await TryForeignSurpriseAsync()) return;
        }
        var id = _owned[_random.Next(_owned.Count)];
        await SelectStationAsync(id, true);
    }

    private async void Home_Click(object sender, RoutedEventArgs e) => await ShowHomeAsync();
    private async void Stations_Click(object sender, RoutedEventArgs e) => await ShowStationsAsync();
    private async void Schedule_Click(object sender, RoutedEventArgs e) => await ShowScheduleAsync();
    private void Favorites_Click(object sender, RoutedEventArgs e) => ShowFavorites();
    private async void Search_Click(object sender, RoutedEventArgs e) => await ShowSearchAsync();
    private async void Podcast_Click(object sender, RoutedEventArgs e) => await ShowPodcastAsync();
    private async void NewsSocial_Click(object sender, RoutedEventArgs e) => await ShowNewsAsync();
    private void Community_Click(object sender, RoutedEventArgs e) => ShowCommunity();
    private void Fanshops_Click(object sender, RoutedEventArgs e) => ShowFanshops();
    private void Help_Click(object sender, RoutedEventArgs e) => ShowHelp();
    private async void Assistant_Click(object sender, RoutedEventArgs e) => await ShowAssistantAsync();
    private void FullPlayer_Click(object sender, RoutedEventArgs e) => ShowFullPlayer();

    private async void Prev_Click(object sender, RoutedEventArgs e)
    {
        if (await StepDirectoryAsync(-1)) return;
        if (_owned.Count == 0) return;
        _stationIndex = (_stationIndex - 1 + _owned.Count) % _owned.Count;
        await SelectStationAsync(_owned[_stationIndex], true);
    }

    private async void Next_Click(object sender, RoutedEventArgs e)
    {
        if (await StepDirectoryAsync(1)) return;
        if (_owned.Count == 0) return;
        _stationIndex = (_stationIndex + 1) % _owned.Count;
        await SelectStationAsync(_owned[_stationIndex], true);
    }

    private void Play_Click(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(_currentAudioUrl)) return;
        try
        {
            if (_player.IsPlaying) _player.PauseResume();
            else if (PlayButton.Content?.ToString() == "▶") _player.PlayUrl(_currentAudioUrl);
            else _player.PauseResume();
        }
        catch { }
    }

    private async void Surprise_Click(object sender, RoutedEventArgs e) => await SurpriseAsync();

    private void Favorite_Click(object sender, RoutedEventArgs e)
    {
        if (_currentStation == null) return;
        ToggleFavorite(_currentStation.Id);
    }

    private async void Sleep_Click(object sender, RoutedEventArgs e)
    {
        _sleepCts?.Cancel();
        _sleepCts = new CancellationTokenSource();
        try
        {
            await Task.Delay(TimeSpan.FromMinutes(30), _sleepCts.Token);
            _player.Stop();
        }
        catch (TaskCanceledException) { }
    }

    private async void QuickSearch_GotFocus(object sender, RoutedEventArgs e)
    {
        if (QuickSearch.Text == "Sender suchen …") QuickSearch.Text = "";
        await ShowSearchAsync(QuickSearch.Text);
    }

    private async void QuickSearch_KeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key != Key.Enter) return;
        await ShowSearchAsync(QuickSearch.Text);
    }

    private void ToggleFavorite(string id)
    {
        if (id.StartsWith("world:", StringComparison.Ordinal))
        {
            if (_dirFavItems.TryGetValue(id, out var known)) ToggleDirFav(known);
            else if (_currentWorld != null && "world:" + _currentWorld.Id == id) ToggleDirFav(_currentWorld);
            return;
        }
        if (!_favorites.Add(id)) _favorites.Remove(id);
        SaveFavorites();
    }

    private void LoadFavorites()
    {
        try
        {
            var p = Path.Combine(DataDir, "favorites.json");
            if (!File.Exists(p)) return;
            var data = JsonSerializer.Deserialize<List<string>>(File.ReadAllText(p)) ?? new();
            foreach (var id in data) _favorites.Add(id);
        }
        catch { }
    }

    private void SaveFavorites()
    {
        try
        {
            File.WriteAllText(Path.Combine(DataDir, "favorites.json"),
                JsonSerializer.Serialize(_favorites.OrderBy(x => x).ToList()));
        }
        catch { }
    }

    private static void SetRemoteImage(Image target, string url)
    {
        if (string.IsNullOrWhiteSpace(url)) return;
        try
        {
            var bmp = new BitmapImage();
            bmp.BeginInit();
            bmp.UriSource = new Uri(url);
            bmp.CacheOption = BitmapCacheOption.OnLoad;
            bmp.EndInit();
            target.Source = bmp;
        }
        catch { }
    }
}
