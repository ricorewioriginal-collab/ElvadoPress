package app.elvadopress.client;

import android.content.SharedPreferences;
import android.os.Build;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Fehlerberichte an das CMS (Bereich Apps → Datenschutz &amp; Statistik). Gesendet wird nur, wenn der Betreiber es im CMS
 * eingeschaltet hat (app_config.telemetry.errors); enthalten sind Fehlertext, Version und Android-Version, keine Nutzerdaten.
 * Abstürze werden lokal vermerkt und beim nächsten Start gesendet (oder verworfen, wenn die Funktion aus ist).
 */
final class ErrorReporter {
    private ErrorReporter() {}

    private static long lastSent = 0;

    /** Merkt sich unbehandelte Abstürze für den nächsten Start; reicht danach an den Standard-Handler weiter. */
    static void install(final SharedPreferences prefs, final String version) {
        final Thread.UncaughtExceptionHandler old = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler((t, e) -> {
            try {
                StackTraceElement[] st = e.getStackTrace();
                StringBuilder s = new StringBuilder();
                for (int i = 0; i < st.length && i < 12; i++) s.append("at ").append(st[i]).append('\n');
                JSONObject o = build("crash", String.valueOf(e), st.length > 0 ? st[0].toString() : "", version, s.toString());
                prefs.edit().putString("pending_crash", o.toString()).commit();
            } catch (Throwable ignored) {
            }
            if (old != null) old.uncaughtException(t, e);
        });
    }

    static JSONObject build(String kind, String message, String where, String version, String stack) throws Exception {
        return new JSONObject().put("platform", "android").put("brand", BuildConfig.FLAVOR).put("kind", kind).put("message", message).put("where", where)
                .put("version", version).put("os", "Android " + Build.VERSION.RELEASE).put("stack", stack == null ? "" : stack);
    }

    /** Sendet einen Bericht (blockierend, in einen Hintergrund-Thread legen). Höchstens einer pro 30 s. */
    static boolean send(String site, JSONObject report, boolean force) {
        long now = System.currentTimeMillis();
        if (!force && now - lastSent < 30000) return false;
        lastSent = now;
        try {
            byte[] body = report.toString().getBytes(StandardCharsets.UTF_8);
            HttpURLConnection c = (HttpURLConnection) new URL(site + "/cms/api.php?action=app_error").openConnection();
            c.setRequestMethod("POST");
            c.setConnectTimeout(8000);
            c.setReadTimeout(10000);
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            try (OutputStream os = c.getOutputStream()) { os.write(body); }
            int code = c.getResponseCode();
            c.disconnect();
            return code == 200;
        } catch (Exception e) {
            return false;
        }
    }

    /** Hörsitzung (nur Sender + Sekunden) an das CMS, wenn die Hörstatistik dort eingeschaltet ist. Blockierend. */
    static void listen(String site, String did, String station, int seconds, String version) {
        try {
            byte[] body = new JSONObject().put("platform", "android").put("brand", BuildConfig.FLAVOR).put("did", did).put("station", station)
                    .put("seconds", seconds).put("version", version).toString().getBytes(StandardCharsets.UTF_8);
            HttpURLConnection c = (HttpURLConnection) new URL(site + "/cms/api.php?action=app_listen").openConnection();
            c.setRequestMethod("POST");
            c.setConnectTimeout(8000);
            c.setReadTimeout(10000);
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            try (OutputStream os = c.getOutputStream()) { os.write(body); }
            c.getResponseCode();
            c.disconnect();
        } catch (Exception ignored) {
        }
    }

    /** Liegt ein Absturz vom letzten Lauf vor: senden (wenn erlaubt) und in jedem Fall löschen. */
    static void flushPending(SharedPreferences prefs, String site, boolean allowed) {
        String raw = prefs.getString("pending_crash", "");
        if (raw.isEmpty()) return;
        prefs.edit().remove("pending_crash").apply();
        if (!allowed) return;
        try { send(site, new JSONObject(raw), true); } catch (Exception ignored) { }
    }
}
