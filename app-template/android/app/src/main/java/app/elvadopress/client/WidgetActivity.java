package app.elvadopress.client;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.WindowInsets;
import android.webkit.PermissionRequest;
import android.webkit.WebResourceRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

public class WidgetActivity extends Activity {
    private WebView webView;
    private PermissionRequest pendingWebPermission;
    private String pendingUrl;
    private String pendingHtml;
    private boolean requiresMicrophone;
    private boolean restrictToRadioSite;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Ui.init(this);

        String title = getIntent().getStringExtra("title");
        pendingUrl = getIntent().getStringExtra("url");
        pendingHtml = getIntent().getStringExtra("html");
        requiresMicrophone = getIntent().getBooleanExtra("requiresMicrophone", false);
        restrictToRadioSite = pendingUrl != null && pendingUrl.contains("/social-wall.html");

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(Color.rgb(6, 9, 28));
        applySystemInsets(root);

        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.HORIZONTAL);
        header.setGravity(Gravity.CENTER_VERTICAL);
        header.setPadding(dp(12), dp(8), dp(12), dp(8));
        header.setBackgroundColor(Color.rgb(10, 14, 40));

        android.widget.ImageButton back = Ui.iconButton(this, R.drawable.ic_back, Ui.TEXT, Ui.SURFACE, 42, "Zurück");
        back.setOnClickListener(v -> goBackNative());
        header.addView(back, new LinearLayout.LayoutParams(dp(42), dp(42)));

        TextView headerTitle = Ui.text(this, title == null ? "Radio-Funktion" : title, 17, Ui.TEXT, Ui.BOLD);
        headerTitle.setSingleLine(true);
        headerTitle.setEllipsize(android.text.TextUtils.TruncateAt.END);
        headerTitle.setPadding(dp(12), 0, dp(8), 0);
        header.addView(headerTitle, new LinearLayout.LayoutParams(
                0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));

        android.widget.ImageButton forward = Ui.iconButton(this, R.drawable.ic_chevron, Ui.TEXT_2, Ui.SURFACE, 42, "Vor");
        forward.setOnClickListener(v -> {
            if (webView != null && webView.canGoForward()) webView.goForward();
        });
        header.addView(forward, Ui.lp(dp(42), dp(42), 0, 0, 8, 0));

        android.widget.ImageButton close = Ui.iconButton(this, R.drawable.ic_close, Ui.TEXT, Ui.SURFACE, 42, "Schließen");
        close.setOnClickListener(v -> finish());
        header.addView(close, new LinearLayout.LayoutParams(dp(42), dp(42)));

        root.addView(header, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT));

        webView = new WebView(this);
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setJavaScriptCanOpenWindowsAutomatically(!restrictToRadioSite);
        settings.setAllowContentAccess(true);
        settings.setAllowFileAccess(true);

        webView.setBackgroundColor(Color.rgb(6, 9, 28));
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (!restrictToRadioSite) return false;
                String host = request.getUrl().getHost();
                return host == null || !(isRadioHost(host));
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (!restrictToRadioSite) return false;
                try {
                    String host = android.net.Uri.parse(url).getHost();
                    return host == null || !(isRadioHost(host));
                } catch (Exception ignored) {
                    return true;
                }
            }
        });
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(PermissionRequest request) {
                runOnUiThread(() -> handleWebPermissionRequest(request));
            }
        });

        root.addView(webView, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));

        setContentView(root);

        if (requiresMicrophone &&
                Build.VERSION.SDK_INT >= 23 &&
                checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 77);
        } else {
            loadPendingUrl();
        }
    }

    private void handleWebPermissionRequest(PermissionRequest request) {
        boolean asksForAudio = false;
        for (String resource : request.getResources()) {
            if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource)) {
                asksForAudio = true;
                break;
            }
        }

        if (!asksForAudio) {
            request.deny();
            return;
        }

        if (Build.VERSION.SDK_INT < 23 ||
                checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
            request.grant(new String[]{PermissionRequest.RESOURCE_AUDIO_CAPTURE});
        } else {
            pendingWebPermission = request;
            requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, 78);
        }
    }

    private void loadPendingUrl() {
        if (webView == null) return;
        if (pendingHtml != null && !pendingHtml.trim().isEmpty()) {
            webView.loadDataWithBaseURL(getString(R.string.site_base) + "/", pendingHtml, "text/html", "utf-8", null);
        } else if (pendingUrl != null && !pendingUrl.trim().isEmpty()) {
            webView.loadUrl(pendingUrl);
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        boolean granted = grantResults.length > 0 &&
                grantResults[0] == PackageManager.PERMISSION_GRANTED;

        if (requestCode == 77) {
            if (granted) {
                loadPendingUrl();
            } else {
                Toast.makeText(this,
                        "Für Voicemail wird Mikrofonzugriff benötigt.",
                        Toast.LENGTH_LONG).show();
            }
            return;
        }

        if (requestCode == 78 && pendingWebPermission != null) {
            if (granted) {
                pendingWebPermission.grant(
                        new String[]{PermissionRequest.RESOURCE_AUDIO_CAPTURE});
            } else {
                pendingWebPermission.deny();
            }
            pendingWebPermission = null;
        }
    }

    private void goBackNative() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            finish();
        }
    }

    @Override
    public void onBackPressed() {
        goBackNative();
    }

    private Button navButton(String value) {
        Button b = new Button(this);
        b.setText(value);
        b.setTextColor(Color.WHITE);
        b.setTextSize(18);
        b.setAllCaps(false);
        b.setBackgroundColor(Color.TRANSPARENT);
        return b;
    }

    private void applySystemInsets(View root) {
        root.setOnApplyWindowInsetsListener((v, insets) -> {
            v.setPadding(
                    insets.getSystemWindowInsetLeft(),
                    insets.getSystemWindowInsetTop(),
                    insets.getSystemWindowInsetRight(),
                    insets.getSystemWindowInsetBottom()
            );
            return insets;
        });
        root.requestApplyInsets();
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    @Override
    protected void onDestroy() {
        if (webView != null) {
            webView.stopLoading();
            webView.setWebChromeClient(null);
            webView.setWebViewClient(null);
            webView.destroy();
            webView = null;
        }
        super.onDestroy();
    }

    /** Gehört die Adresse zur Website der Marke (Host oder Unterdomain)? */
    private boolean isRadioHost(String host) {
        if (host == null) return false;
        String h = host.toLowerCase(java.util.Locale.ROOT);
        String site = android.net.Uri.parse(getString(R.string.site_base)).getHost();
        if (site == null || site.isEmpty()) return false;
        site = site.toLowerCase(java.util.Locale.ROOT).replaceFirst("^www\\.", "");
        return h.equals(site) || h.endsWith("." + site) || h.equals("www." + site);
    }
}
