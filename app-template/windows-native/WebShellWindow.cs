using System.Diagnostics;
using System.IO;
using System.Net;
using System.Net.Http;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.Wpf;
using Button = System.Windows.Controls.Button;
using Orientation = System.Windows.Controls.Orientation;
using Brushes = System.Windows.Media.Brushes;

namespace ElvadoPress.App.Windows;

/// <summary>
/// App-Typ "web": die Website als eigene Windows-App (Vollbild-WebView2) für beliebige Seiten, nicht nur Radio.
/// Interne Links bleiben im Fenster, fremde Adressen und mailto:/tel: öffnen im Standard-Browser.
/// Laufzeit-Einstellungen aus dem CMS (Apps → Apps verwalten): Wartungsmodus, Pflicht-Update, Hinweis an alle Nutzer – siehe WebRuntime.
/// </summary>
public class WebShellWindow : Window
{
    private readonly WebView2 _web = new();
    private const string RetryUrl = "https://retry.invalid/";
    private readonly Grid _root = new();
    private readonly StackPanel _notice = new() { VerticalAlignment = System.Windows.VerticalAlignment.Top, Visibility = Visibility.Collapsed };
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(6) };
    private Border? _block;
    private readonly StackPanel _tabBar = new() { Orientation = Orientation.Horizontal, Visibility = Visibility.Collapsed };
    private List<(string Title, string Icon, string Url)> _tabs = new();
    private int _activeTab;

    public WebShellWindow()
    {
        Title = Brand.Name;
        Width = 1280; Height = 860; MinWidth = 640; MinHeight = 480;
        WindowStartupLocation = WindowStartupLocation.CenterScreen;
        try { Background = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(Brand.ThemeColor)); } catch { }
        try
        {
            var icon = Path.Combine(AppContext.BaseDirectory, "assets", "config", "app_icon.png");
            if (File.Exists(icon)) Icon = BitmapFrame.Create(new Uri(icon));
        }
        catch { }
        // Zeilen: Website (füllt aus) und – nur bei Baukasten-Apps mit mindestens zwei Tabs – die Tab-Leiste unten
        _root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
        _root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        Grid.SetRow(_web, 0);
        Grid.SetRow(_tabBar, 1);
        Grid.SetRowSpan(_notice, 2);
        _root.Children.Add(_web);
        _root.Children.Add(_tabBar);
        _root.Children.Add(_notice);
        Content = _root;
        if (Brand.IsContent) SetTabs(WebRuntime.ParseTabs(WebRuntime.TabsCache, Brand.SiteBase));
        Loaded += async (_, _) => { await InitAsync(); await ApplyRuntimeAsync(); };
    }

    // ---- Laufzeit-Einstellungen aus dem CMS (nie blockierend: bei jedem Fehler läuft die App normal)

    private async Task ApplyRuntimeAsync()
    {
        var c = await WebRuntime.FetchAsync(_http);
        if (Brand.IsContent && c.Ok && c.TabsJson != WebRuntime.TabsCache) { WebRuntime.TabsCache = c.TabsJson; SetTabs(c.Tabs); }
        if (c.Maintenance) ShowBlock(c.MaintenanceTitle, c.MaintenanceText.Length > 0 ? c.MaintenanceText : "Die App ist vorübergehend nicht verfügbar. Bitte versuche es später erneut.", "");
        else if (c.UpdateRequired) ShowBlock("Update erforderlich", "Diese Version der App wird nicht mehr unterstützt. Bitte installiere die neueste Version.", c.UpdateUrl);
        else HideBlock();
        if (!c.Maintenance && !c.UpdateRequired && c.HasNotice && c.NoticeId != WebRuntime.NoticeSeen) ShowNotice(c);
        else _notice.Visibility = Visibility.Collapsed;
    }

    /// <summary>Tab-Leiste unten (Baukasten-App): nur ab zwei Einträgen; jeder Tab lädt seine Seite der Website.</summary>
    private void SetTabs(List<(string Title, string Icon, string Url)> list)
    {
        _tabs = list;
        _tabBar.Children.Clear();
        if (list.Count < 2) { _tabBar.Visibility = Visibility.Collapsed; return; }
        var bg = System.Windows.Media.Color.FromRgb(7, 10, 28);
        try { bg = (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(Brand.ThemeColor); } catch { }
        _tabBar.Background = new SolidColorBrush(bg);
        for (var i = 0; i < list.Count; i++)
        {
            var idx = i;
            var on = i == _activeTab;
            var cell = new StackPanel { Margin = new Thickness(6, 4, 6, 4), Opacity = on ? 1 : 0.7, Cursor = System.Windows.Input.Cursors.Hand, Background = Brushes.Transparent, ToolTip = list[i].Title };
            cell.Children.Add(new TextBlock { Text = WebRuntime.TabGlyph(list[i].Icon), FontSize = 20, Foreground = Brushes.White, TextAlignment = TextAlignment.Center });
            cell.Children.Add(new TextBlock { Text = list[i].Title, FontSize = 11, Foreground = Brushes.White, FontWeight = on ? FontWeights.Bold : FontWeights.Normal, TextAlignment = TextAlignment.Center, TextTrimming = TextTrimming.CharacterEllipsis });
            cell.MouseLeftButtonUp += (_, _) => { _activeTab = idx; _web.CoreWebView2?.Navigate(_tabs[idx].Url); SetTabs(_tabs); };
            cell.Width = Math.Max(80, 560.0 / list.Count);
            _tabBar.Children.Add(cell);
        }
        _tabBar.HorizontalAlignment = System.Windows.HorizontalAlignment.Center;
        _tabBar.Visibility = Visibility.Visible;
    }

    private void ShowNotice(WebRuntimeConfig c)
    {
        _notice.Children.Clear();
        var warn = c.NoticeLevel == "warn";
        var box = new Border
        {
            Background = new SolidColorBrush(warn ? System.Windows.Media.Color.FromRgb(255, 236, 179) : System.Windows.Media.Color.FromRgb(225, 238, 255)),
            Padding = new Thickness(16, 10, 16, 10)
        };
        var stack = new StackPanel();
        var dark = new SolidColorBrush(System.Windows.Media.Color.FromRgb(20, 20, 30));
        if (c.NoticeTitle.Length > 0) stack.Children.Add(new TextBlock { Text = c.NoticeTitle, FontWeight = FontWeights.Bold, FontSize = 15, Foreground = dark, TextWrapping = TextWrapping.Wrap });
        if (c.NoticeText.Length > 0) stack.Children.Add(new TextBlock { Text = c.NoticeText, FontSize = 13, Foreground = dark, TextWrapping = TextWrapping.Wrap, Margin = new Thickness(0, 2, 0, 6) });
        var row = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = System.Windows.HorizontalAlignment.Right };
        if (c.NoticeUrl.Length > 0)
        {
            var more = new Button { Content = c.NoticeLabel.Length > 0 ? c.NoticeLabel : "Mehr erfahren", Margin = new Thickness(0, 0, 8, 0), Padding = new Thickness(12, 4, 12, 4) };
            more.Click += (_, _) => OpenExternal(c.NoticeUrl);
            row.Children.Add(more);
        }
        var ok = new Button { Content = "OK", Padding = new Thickness(18, 4, 18, 4) };
        ok.Click += (_, _) => { WebRuntime.NoticeSeen = c.NoticeId; _notice.Visibility = Visibility.Collapsed; };
        row.Children.Add(ok);
        stack.Children.Add(row);
        box.Child = stack;
        _notice.Children.Add(box);
        _notice.Visibility = Visibility.Visible;
    }

    /// <summary>Vollbild-Sperre (Wartungsmodus oder Pflicht-Update) über der Website.</summary>
    private void ShowBlock(string title, string message, string actionUrl)
    {
        HideBlock();
        var stack = new StackPanel { VerticalAlignment = System.Windows.VerticalAlignment.Center, HorizontalAlignment = System.Windows.HorizontalAlignment.Center, MaxWidth = 520, Margin = new Thickness(24) };
        stack.Children.Add(new TextBlock { Text = title, FontSize = 24, FontWeight = FontWeights.Bold, Foreground = Brushes.White, TextAlignment = TextAlignment.Center, TextWrapping = TextWrapping.Wrap });
        stack.Children.Add(new TextBlock { Text = message, FontSize = 15, Foreground = new SolidColorBrush(System.Windows.Media.Color.FromRgb(220, 224, 240)), TextAlignment = TextAlignment.Center, TextWrapping = TextWrapping.Wrap, Margin = new Thickness(0, 12, 0, 20) });
        if (actionUrl.Length > 0)
        {
            var go = new Button { Content = "Update laden", Padding = new Thickness(18, 8, 18, 8), Margin = new Thickness(0, 0, 0, 8) };
            go.Click += (_, _) => OpenExternal(actionUrl);
            stack.Children.Add(go);
        }
        var again = new Button { Content = "Erneut prüfen", Padding = new Thickness(18, 8, 18, 8) };
        again.Click += async (_, _) => await ApplyRuntimeAsync();
        stack.Children.Add(again);
        var bg = System.Windows.Media.Color.FromRgb(7, 10, 28);
        try { bg = (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(Brand.ThemeColor); } catch { }
        _block = new Border { Background = new SolidColorBrush(bg), Child = stack };
        _root.Children.Add(_block);
    }

    private void HideBlock()
    {
        if (_block != null) { _root.Children.Remove(_block); _block = null; }
    }

    private static bool IsInternal(Uri uri)
    {
        if (uri.Scheme != Uri.UriSchemeHttps) return false;
        var own = new Uri(Brand.Website).Host.ToLowerInvariant();
        if (own.StartsWith("www.")) own = own[4..];
        var host = uri.Host.ToLowerInvariant();
        return host == own || host == "www." + own || host.EndsWith("." + own);
    }

    private static void OpenExternal(string url)
    {
        try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); } catch { }
    }

    private async Task InitAsync()
    {
        try
        {
            var safe = new string(Brand.Name.Where(char.IsLetterOrDigit).ToArray());
            var folder = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), safe.Length > 0 ? safe : "WebApp", "WebView2");
            var env = await CoreWebView2Environment.CreateAsync(null, folder);
            await _web.EnsureCoreWebView2Async(env);
            var core = _web.CoreWebView2;
            core.Settings.AreDevToolsEnabled = false;
            core.Settings.IsStatusBarEnabled = false;
            core.NavigationStarting += (_, e) =>
            {
                if (e.Uri == RetryUrl) { e.Cancel = true; core.Navigate(Brand.Website); return; }
                if (e.Uri.StartsWith("about:") || e.Uri.StartsWith("data:")) return;
                if (!Uri.TryCreate(e.Uri, UriKind.Absolute, out var uri) || !IsInternal(uri)) { e.Cancel = true; OpenExternal(e.Uri); }
            };
            core.NewWindowRequested += (_, e) =>
            {
                e.Handled = true;
                if (Uri.TryCreate(e.Uri, UriKind.Absolute, out var uri) && IsInternal(uri)) core.Navigate(e.Uri); else OpenExternal(e.Uri);
            };
            core.NavigationCompleted += (_, e) =>
            {
                if (!e.IsSuccess && e.WebErrorStatus != CoreWebView2WebErrorStatus.OperationCanceled && e.WebErrorStatus != CoreWebView2WebErrorStatus.ConnectionAborted)
                {
                    var name = WebUtility.HtmlEncode(Brand.Name);
                    core.NavigateToString("<!doctype html><meta charset=utf-8><body style='font-family:Segoe UI,sans-serif;text-align:center;padding:18vh 24px;color:#333'><h2>" + name
                        + "</h2><p>Keine Verbindung. Bitte prüfe dein Internet.</p><p><a href='" + RetryUrl + "' style='display:inline-block;padding:12px 22px;border-radius:10px;background:#111;color:#fff;text-decoration:none'>Erneut versuchen</a></p></body>");
                }
            };
            core.Navigate(Brand.Website);
        }
        catch (Exception ex)
        {
            ErrorReporter.SaveCrash(ex);
            System.Windows.MessageBox.Show("WebView2 konnte nicht gestartet werden. Bitte installiere die \"Microsoft Edge WebView2 Runtime\".\n\n" + ex.Message, Brand.Name);
        }
    }
}
