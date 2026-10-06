using System.Diagnostics;
using System.IO;
using System.Net;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.Wpf;

namespace ElvadoPress.App.Windows;

/// <summary>
/// App-Typ "web": die Website als eigene Windows-App (Vollbild-WebView2) für beliebige Seiten, nicht nur Radio.
/// Interne Links bleiben im Fenster, fremde Adressen und mailto:/tel: öffnen im Standard-Browser.
/// </summary>
public class WebShellWindow : Window
{
    private readonly WebView2 _web = new();
    private const string RetryUrl = "https://retry.invalid/";

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
        Content = _web;
        Loaded += async (_, _) => await InitAsync();
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
