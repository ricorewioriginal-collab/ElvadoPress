package app.elvadopress.client;

import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;

import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.UUID;

/**
 * Laufzeit-Konfiguration der Website-App aus dem CMS (Apps → Apps verwalten): Wartungsmodus, Pflicht-Update und Hinweis an alle Nutzer.
 * Abgerufen wird {site}/cms/api.php?action=app_config&brand=<Marke> beim Start; bei jedem Fehler startet die App normal (nie aussperren).
 * Die Abfrage übermittelt eine zufällige Installations-Kennung, die der Server gesalzen hasht; Nutzungszahlen zählt das CMS nur, wenn der Betreiber sie eingeschaltet hat.
 */
final class WebRuntime {
    static final class Config {
        String maintenanceTitle = "", maintenanceText = "";
        boolean maintenance;
        boolean updateRequired;
        String updateUrl = "";
        String noticeId = "", noticeLevel = "info", noticeTitle = "", noticeText = "", noticeUrl = "", noticeLabel = "";
        boolean hasNotice() { return !noticeId.isEmpty() && (!noticeTitle.isEmpty() || !noticeText.isEmpty()); }
    }

    interface Callback { void done(Config config); }

    private WebRuntime() {}

    /** Zufällige Kennung dieser Installation (bleibt bis zur Deinstallation gleich). */
    static String installId(SharedPreferences prefs) {
        String id = prefs.getString("install_id", "");
        if (id.isEmpty()) {
            id = UUID.randomUUID().toString();
            prefs.edit().putString("install_id", id).apply();
        }
        return id;
    }

    /** Lädt die Konfiguration im Hintergrund; ruft {@code cb} mit einer leeren Konfiguration auf, wenn nichts zu holen war. Nicht auf dem Hauptthread aufrufen. */
    static void fetch(Context ctx, SharedPreferences prefs, String site, Callback cb) {
        Config c = new Config();
        try {
            String version = "";
            int code = 0;
            try {
                PackageInfo pi = ctx.getPackageManager().getPackageInfo(ctx.getPackageName(), 0);
                version = pi.versionName == null ? "" : pi.versionName;
                code = android.os.Build.VERSION.SDK_INT >= 28 ? (int) pi.getLongVersionCode() : pi.versionCode;
            } catch (Exception ignored) { }
            String url = site.replaceAll("/+$", "") + "/cms/api.php?action=app_config&platform=android&brand=" + URLEncoder.encode(BuildConfig.FLAVOR, "UTF-8")
                    + "&version=" + URLEncoder.encode(version, "UTF-8") + "&code=" + code + "&did=" + installId(prefs);
            HttpURLConnection con = (HttpURLConnection) new URL(url).openConnection();
            con.setConnectTimeout(5000);
            con.setReadTimeout(5000);
            con.setRequestProperty("Accept", "application/json");
            con.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
            if (con.getResponseCode() == 200) {
                try (InputStream in = con.getInputStream()) {
                    ByteArrayOutputStream out = new ByteArrayOutputStream();
                    byte[] buf = new byte[4096];
                    int n, total = 0;
                    while ((n = in.read(buf)) > 0 && total < 200_000) { out.write(buf, 0, n); total += n; }
                    parse(new String(out.toByteArray(), StandardCharsets.UTF_8), c);
                }
            }
        } catch (Exception ignored) {
            c = new Config();
        }
        cb.done(c);
    }

    /** Antwort von app_config in die Konfiguration übernehmen (getrennt, damit sie ohne Netz prüfbar bleibt). */
    static void parse(String json, Config c) throws Exception {
        JSONObject d = new JSONObject(json);
        if (!"ok".equals(d.optString("status"))) return;
        JSONObject m = d.optJSONObject("maintenance");
        if (m != null) {
            c.maintenance = true;
            c.maintenanceTitle = m.optString("title", "Wartungsarbeiten");
            c.maintenanceText = m.optString("text", "");
        }
        JSONObject u = d.optJSONObject("update");
        if (u != null && u.optBoolean("required", false)) {
            c.updateRequired = true;
            c.updateUrl = httpsOrEmpty(u.optString("page", ""));
            if (c.updateUrl.isEmpty()) c.updateUrl = httpsOrEmpty(u.optString("url", ""));
        }
        JSONObject n = d.optJSONObject("notice");
        if (n != null) {
            c.noticeId = n.optString("id", "");
            c.noticeLevel = "warn".equals(n.optString("level")) ? "warn" : "info";
            c.noticeTitle = n.optString("title", "");
            c.noticeText = n.optString("text", "");
            c.noticeUrl = httpsOrEmpty(n.optString("url", ""));
            c.noticeLabel = n.optString("url_label", "");
        }
    }

    private static String httpsOrEmpty(String u) {
        return u != null && u.startsWith("https://") ? u : "";
    }
}
