package app.elvadopress.client;

import android.content.Context;

import java.io.BufferedReader;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.function.Predicate;

/**
 * Lokaler Zwischenspeicher fuer Sendeplan, News (RSS), Podcast und Co.: jede erfolgreiche Antwort wird
 * als Datei abgelegt; faellt das Netz aus, zeigt die App den zuletzt gespeicherten Stand.
 */
final class DataCache {
    private DataCache() {}

    static final class Result {
        final String text;
        final boolean fromCache;
        final long savedAt;

        Result(String text, boolean fromCache, long savedAt) {
            this.text = text;
            this.fromCache = fromCache;
            this.savedAt = savedAt;
        }
    }

    private static File file(Context c, String key) {
        File dir = new File(c.getFilesDir(), "datacache");
        if (!dir.isDirectory()) //noinspection ResultOfMethodCallIgnored
            dir.mkdirs();
        return new File(dir, key.replaceAll("[^A-Za-z0-9._-]", "_") + ".txt");
    }

    static void put(Context c, String key, String text) {
        if (text == null || text.trim().isEmpty()) return;
        try {
            File target = file(c, key);
            File tmp = new File(target.getPath() + ".tmp");
            try (FileOutputStream out = new FileOutputStream(tmp)) {
                out.write(text.getBytes(StandardCharsets.UTF_8));
            }
            //noinspection ResultOfMethodCallIgnored
            target.delete();
            //noinspection ResultOfMethodCallIgnored
            tmp.renameTo(target);
        } catch (Exception ignored) {
        }
    }

    static String get(Context c, String key) {
        try {
            File f = file(c, key);
            if (!f.isFile()) return null;
            try (BufferedReader reader = new BufferedReader(new InputStreamReader(
                    new java.io.FileInputStream(f), StandardCharsets.UTF_8))) {
                StringBuilder b = new StringBuilder();
                char[] buf = new char[8192];
                int n;
                while ((n = reader.read(buf)) > 0) b.append(buf, 0, n);
                return b.toString();
            }
        } catch (Exception e) {
            return null;
        }
    }

    static long savedAt(Context c, String key) {
        File f = file(c, key);
        return f.isFile() ? f.lastModified() : 0L;
    }

    /**
     * Holt eine URL. Gueltige Antworten (validator == null oder true) werden gespeichert und frisch zurueckgegeben.
     * Schlaegt Netz oder Pruefung fehl, kommt der gespeicherte Stand zurueck; gibt es keinen, wird der Fehler geworfen.
     */
    static Result fetch(Context c, String key, String address, String accept, Predicate<String> validator) throws Exception {
        Exception failure;
        try {
            String text = httpGet(address, accept);
            if (validator == null || validator.test(text)) {
                put(c, key, text);
                return new Result(text, false, System.currentTimeMillis());
            }
            failure = new IOException("Antwort ungueltig");
        } catch (Exception e) {
            failure = e;
        }
        String cached = get(c, key);
        if (cached != null && (validator == null || validator.test(cached))) {
            return new Result(cached, true, savedAt(c, key));
        }
        throw failure;
    }

    static String httpGet(String address, String accept) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(address).openConnection();
        connection.setConnectTimeout(9000);
        connection.setReadTimeout(12000);
        connection.setRequestProperty("Accept", accept == null ? "*/*" : accept);
        connection.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try {
            int code = connection.getResponseCode();
            if (code < 200 || code >= 300) throw new IOException("HTTP " + code);
            try (InputStream in = connection.getInputStream()) {
                ByteArrayOutputStream out = new ByteArrayOutputStream();
                byte[] buf = new byte[8192];
                int n;
                while ((n = in.read(buf)) > 0) out.write(buf, 0, n);
                return new String(out.toByteArray(), StandardCharsets.UTF_8);
            }
        } finally {
            connection.disconnect();
        }
    }
}
