using System.IO;
using System.Windows;
using System.Windows.Interop;
using System.Windows.Media;
using System.Windows.Threading;

namespace ElvadoPress.App.Windows;

public partial class App : System.Windows.Application
{
    public App()
    {
        // CI screenshot runners have no real GPU; WPF's hardware (D3D) render
        // path can crash the process natively once the visual tree gets more
        // complex (e.g. the stations list with many Image elements) - a crash
        // that bypasses every managed exception handler below. Software
        // rendering avoids that GPU/driver dependency entirely.
        if (Environment.GetEnvironmentVariable("APP_SCREENSHOT_MODE") == "1")
            RenderOptions.ProcessRenderMode = RenderMode.SoftwareOnly;

        DispatcherUnhandledException += (_, e) =>
        {
            LogCrash("DispatcherUnhandledException", e.Exception);
            e.Handled = true;
        };
        AppDomain.CurrentDomain.UnhandledException += (_, e) =>
            LogCrash("AppDomain.UnhandledException", e.ExceptionObject as Exception);
        System.Threading.Tasks.TaskScheduler.UnobservedTaskException += (_, e) =>
        {
            LogCrash("UnobservedTaskException", e.Exception);
            e.SetObserved();
        };
    }

    // Website- bzw. Baukasten-App (WebView2-Vollbildfenster)
    protected override void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);
        Window w = new WebShellWindow();
        MainWindow = w;
        w.Show();
    }

    private static void LogCrash(string source, Exception? ex)
    {
        ErrorReporter.SaveCrash(ex);
        try
        {
            var outDir = Environment.GetEnvironmentVariable("APP_SCREENSHOT_DIR");
            if (string.IsNullOrWhiteSpace(outDir)) outDir = AppContext.BaseDirectory;
            Directory.CreateDirectory(outDir);
            var logPath = Path.Combine(outDir, "windows-screenshot.log");
            File.AppendAllText(logPath, $"{DateTime.Now:O} {source}: {ex}\n");
        }
        catch
        {
            // Logging must never itself crash the process.
        }
    }
}
