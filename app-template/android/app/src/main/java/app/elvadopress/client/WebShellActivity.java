package app.elvadopress.client;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.ProgressBar;

import java.util.List;
import java.util.Locale;

/**
 * App-Typ "web": die Website als eigene App (Vollbild-WebView) für beliebige Seiten, nicht nur Radio.
 * Start-Adresse und Farbe kommen aus android/brands.json (launch_url, site_base, theme_color).
 * Interne Links bleiben in der App, fremde Adressen, mailto:/tel: und Downloads öffnen extern.
 * Laufzeit-Einstellungen aus dem CMS (Apps → Apps verwalten): Wartungsmodus, Pflicht-Update, Hinweis an alle Nutzer – siehe WebRuntime.
 */
public class WebShellActivity extends Activity {
    private WebView web;
    private ProgressBar progress;
    private String siteHost = "";
    private String launchUrl = "";
    private boolean failed = false;
    private SharedPreferences prefs;
    private FrameLayout root;
    private LinearLayout noticeBar;
    private LinearLayout blockView;
    private LinearLayout tabBar;
    private List<String[]> tabs = new java.util.ArrayList<>();
    private int activeTab = 0;
    private int bgColor = Color.rgb(7, 10, 28);

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences("app_prefs", MODE_PRIVATE);
        launchUrl = getString(R.string.launch_url);
        try { siteHost = Uri.parse(getString(R.string.site_base)).getHost(); } catch (Exception ignored) { }
        if (siteHost == null) siteHost = "";

        int bg = Color.rgb(7, 10, 28);
        try { bg = Color.parseColor(getString(R.string.theme_color)); } catch (Exception ignored) { }
        bgColor = bg;
        getWindow().setStatusBarColor(bg);
        getWindow().setNavigationBarColor(bg);

        root = new FrameLayout(this);
        root.setBackgroundColor(bg);
        LinearLayout column = new LinearLayout(this);
        column.setOrientation(LinearLayout.VERTICAL);
        noticeBar = new LinearLayout(this);
        noticeBar.setOrientation(LinearLayout.VERTICAL);
        noticeBar.setVisibility(View.GONE);

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

        column.addView(noticeBar, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        column.addView(web, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        tabBar = new LinearLayout(this);
        tabBar.setOrientation(LinearLayout.HORIZONTAL);
        tabBar.setBackgroundColor(bg);
        tabBar.setVisibility(View.GONE);
        column.addView(tabBar, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        root.addView(column, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        int h = Math.round(3 * getResources().getDisplayMetrics().density);
        root.addView(progress, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, h));
        setContentView(root);

        if (savedInstanceState != null) web.restoreState(savedInstanceState);
        else web.loadUrl(launchUrl);
        // Baukasten-App: zuletzt bekannte Tab-Leiste sofort zeigen (auch offline), danach aus dem CMS aktualisieren
        if (isContentApp()) setTabs(WebRuntime.parseTabs(prefs.getString("tabs_json", ""), getString(R.string.site_base)));
        loadRuntime();
    }

    private boolean isContentApp() { return "content".equals(BuildConfig.APP_TYPE); }

    /** Tab-Leiste unten (Baukasten-App): nur bei mindestens zwei Einträgen; jeder Tab lädt seine Seite der Website. */
    private void setTabs(List<String[]> list) {
        tabs = list;
        tabBar.removeAllViews();
        if (list.size() < 2) { tabBar.setVisibility(View.GONE); return; }
        int light = Color.rgb(235, 238, 250);
        int active = Color.WHITE;
        for (int i = 0; i < list.size(); i++) {
            final int idx = i;
            String[] t = list.get(i);
            LinearLayout cell = new LinearLayout(this);
            cell.setOrientation(LinearLayout.VERTICAL);
            cell.setGravity(Gravity.CENTER);
            cell.setPadding(dp(2), dp(6), dp(2), dp(6));
            TextView g = text(WebRuntime.tabGlyph(t[1]), 20, i == activeTab ? active : light, false);
            g.setGravity(Gravity.CENTER);
            TextView l = text(t[0], 11, i == activeTab ? active : light, i == activeTab);
            l.setGravity(Gravity.CENTER);
            l.setSingleLine(true);
            l.setEllipsize(android.text.TextUtils.TruncateAt.END);
            cell.addView(g);
            cell.addView(l);
            cell.setAlpha(i == activeTab ? 1f : 0.7f);
            cell.setContentDescription(t[0]);
            cell.setOnClickListener(v -> { activeTab = idx; web.loadUrl(tabs.get(idx)[2]); setTabs(tabs); });
            tabBar.addView(cell, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        }
        tabBar.setVisibility(View.VISIBLE);
    }

    // ---- Laufzeit-Einstellungen aus dem CMS (nie blockierend: bei jedem Fehler läuft die App normal)

    private void loadRuntime() {
        final String site = getString(R.string.site_base);
        new Thread(() -> WebRuntime.fetch(this, prefs, site, cfg -> runOnUiThread(() -> { if (!isFinishing()) applyRuntime(cfg); }))).start();
    }

    private void applyRuntime(WebRuntime.Config c) {
        if (isContentApp() && c.ok && !c.tabsJson.equals(prefs.getString("tabs_json", ""))) {
            prefs.edit().putString("tabs_json", c.tabsJson).apply();
            setTabs(c.tabs);
        }
        if (c.maintenance) {
            showBlock(c.maintenanceTitle.isEmpty() ? "Wartungsarbeiten" : c.maintenanceTitle,
                    c.maintenanceText.isEmpty() ? "Die App ist vorübergehend nicht verfügbar. Bitte versuche es später erneut." : c.maintenanceText, "", "Erneut prüfen");
        } else if (c.updateRequired) {
            showBlock("Update erforderlich", "Diese Version der App wird nicht mehr unterstützt. Bitte installiere die neueste Version.", c.updateUrl, "Erneut prüfen");
        } else {
            hideBlock();
        }
        if (!c.maintenance && !c.updateRequired && c.hasNotice() && !c.noticeId.equals(prefs.getString("notice_seen", ""))) showNotice(c);
        else noticeBar.setVisibility(View.GONE);
    }

    private int dp(int v) { return Math.round(v * getResources().getDisplayMetrics().density); }

    private TextView text(String t, int sp, int color, boolean bold) {
        TextView v = new TextView(this);
        v.setText(t);
        v.setTextSize(sp);
        v.setTextColor(color);
        if (bold) v.setTypeface(null, android.graphics.Typeface.BOLD);
        return v;
    }

    /** Hinweis an alle Nutzer: einmal pro Gerät (bis der Text im CMS geändert wird), mit optionalem Link. */
    private void showNotice(final WebRuntime.Config c) {
        noticeBar.removeAllViews();
        noticeBar.setBackgroundColor("warn".equals(c.noticeLevel) ? Color.rgb(255, 236, 179) : Color.rgb(225, 238, 255));
        noticeBar.setPadding(dp(14), dp(10), dp(14), dp(10));
        if (!c.noticeTitle.isEmpty()) noticeBar.addView(text(c.noticeTitle, 15, Color.rgb(20, 20, 30), true));
        if (!c.noticeText.isEmpty()) noticeBar.addView(text(c.noticeText, 14, Color.rgb(40, 40, 55), false));
        LinearLayout row = new LinearLayout(this);
        row.setGravity(Gravity.END);
        if (!c.noticeUrl.isEmpty()) {
            Button more = new Button(this);
            more.setText(c.noticeLabel.isEmpty() ? "Mehr erfahren" : c.noticeLabel);
            more.setOnClickListener(v -> openExternal(Uri.parse(c.noticeUrl)));
            row.addView(more);
        }
        Button ok = new Button(this);
        ok.setText("OK");
        ok.setOnClickListener(v -> { prefs.edit().putString("notice_seen", c.noticeId).apply(); noticeBar.setVisibility(View.GONE); });
        row.addView(ok);
        noticeBar.addView(row);
        noticeBar.setVisibility(View.VISIBLE);
    }

    /** Vollbild-Sperre (Wartungsmodus oder Pflicht-Update) über der Website. */
    private void showBlock(String title, String message, final String actionUrl, String retryLabel) {
        hideBlock();
        blockView = new LinearLayout(this);
        blockView.setOrientation(LinearLayout.VERTICAL);
        blockView.setGravity(Gravity.CENTER);
        blockView.setBackgroundColor(bgColor);
        blockView.setPadding(dp(28), dp(28), dp(28), dp(28));
        blockView.setClickable(true);
        TextView t = text(title, 22, Color.WHITE, true);
        t.setGravity(Gravity.CENTER);
        blockView.addView(t);
        TextView m = text(message, 16, Color.rgb(220, 224, 240), false);
        m.setGravity(Gravity.CENTER);
        m.setPadding(0, dp(12), 0, dp(20));
        blockView.addView(m);
        if (actionUrl != null && !actionUrl.isEmpty()) {
            Button go = new Button(this);
            go.setText("Update laden");
            go.setOnClickListener(v -> openExternal(Uri.parse(actionUrl)));
            blockView.addView(go);
        }
        Button retry = new Button(this);
        retry.setText(retryLabel);
        retry.setOnClickListener(v -> loadRuntime());
        blockView.addView(retry);
        root.addView(blockView, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
    }

    private void hideBlock() {
        if (blockView != null) { root.removeView(blockView); blockView = null; }
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
        if (keyCode == KeyEvent.KEYCODE_BACK && blockView != null) { finish(); return true; }
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
