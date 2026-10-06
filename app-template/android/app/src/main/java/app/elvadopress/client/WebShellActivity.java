package app.elvadopress.client;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ProgressBar;

import java.util.Locale;

/**
 * App-Typ "web": die Website als eigene App (Vollbild-WebView) für beliebige Seiten, nicht nur Radio.
 * Start-Adresse und Farbe kommen aus android/brands.json (launch_url, site_base, theme_color).
 * Interne Links bleiben in der App, fremde Adressen, mailto:/tel: und Downloads öffnen extern.
 */
public class WebShellActivity extends Activity {
    private WebView web;
    private ProgressBar progress;
    private String siteHost = "";
    private String launchUrl = "";
    private boolean failed = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        launchUrl = getString(R.string.launch_url);
        try { siteHost = Uri.parse(getString(R.string.site_base)).getHost(); } catch (Exception ignored) { }
        if (siteHost == null) siteHost = "";

        int bg = Color.rgb(7, 10, 28);
        try { bg = Color.parseColor(getString(R.string.theme_color)); } catch (Exception ignored) { }
        getWindow().setStatusBarColor(bg);
        getWindow().setNavigationBarColor(bg);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(bg);

        web = new WebView(this);
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        web.setBackgroundColor(Color.WHITE);

        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setMax(100);
        progress.setVisibility(View.GONE);

        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int p) {
                progress.setProgress(p);
                progress.setVisibility(p >= 100 ? View.GONE : View.VISIBLE);
            }
        });
        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handle(request.getUrl());
            }

            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
                failed = false;
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    failed = true;
                    view.loadDataWithBaseURL(launchUrl, offlineHtml(), "text/html", "UTF-8", null);
                }
            }
        });
        web.setDownloadListener((url, ua, cd, mime, len) -> openExternal(Uri.parse(url)));

        root.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        int h = Math.round(3 * getResources().getDisplayMetrics().density);
        root.addView(progress, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, h));
        setContentView(root);

        if (savedInstanceState != null) web.restoreState(savedInstanceState);
        else web.loadUrl(launchUrl);
    }

    /** true = die App behandelt die Adresse selbst (extern geöffnet), false = in der WebView laden. */
    private boolean handle(Uri uri) {
        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.ROOT);
        if (scheme.equals("retry")) { failed = false; web.loadUrl(launchUrl); return true; }
        if (!scheme.equals("http") && !scheme.equals("https")) { openExternal(uri); return true; }
        String host = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.ROOT);
        String own = siteHost.toLowerCase(Locale.ROOT).replaceFirst("^www\\.", "");
        boolean internal = !own.isEmpty() && (host.equals(own) || host.equals("www." + own) || host.endsWith("." + own));
        if (internal && scheme.equals("https")) return false;
        openExternal(uri);
        return true;
    }

    private void openExternal(Uri uri) {
        try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); } catch (ActivityNotFoundException ignored) { }
    }

    private String offlineHtml() {
        String name = getString(R.string.app_name).replace("<", "").replace(">", "").replace("&", "");
        return "<!doctype html><meta name=viewport content='width=device-width,initial-scale=1'>"
                + "<body style='font-family:sans-serif;text-align:center;padding:18vh 24px;color:#333'>"
                + "<h2>" + name + "</h2><p>Keine Verbindung. Bitte prüfe dein Internet.</p>"
                + "<p><a href='retry://now' style='display:inline-block;padding:12px 22px;border-radius:10px;background:#111;color:#fff;text-decoration:none'>Erneut versuchen</a></p></body>";
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK && web != null && web.canGoBack() && !failed) { web.goBack(); return true; }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        if (web != null) web.saveState(outState);
    }

    @Override
    protected void onDestroy() {
        if (web != null) { ((ViewGroup) web.getParent()).removeView(web); web.destroy(); }
        super.onDestroy();
    }
}
