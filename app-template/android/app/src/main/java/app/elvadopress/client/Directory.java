package app.elvadopress.client;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * Radioverzeichnis (Marken mit Verzeichnis-Funktion): Suche, Zufall, Meldungen und Metadaten laufen
 * ueber die CMS-API der Marke (directory_*). Der Server wendet Sperrliste und Einstellungen aus dem CMS an; die App
 * zeigt nur, was er liefert. Netzwerk-Aufrufe gehoeren in einen Hintergrund-Thread.
 */
final class Directory {
    private Directory() {}

    /** Ein Sender aus dem Verzeichnis: source "laut" (laut.fm) oder "world" (radio-browser.info, Fremd-Stream). */
    static final class Item {
        final String source, id, name, description, cover, link, country, stream, codec;
        final List<String> genres = new ArrayList<>();
        final boolean own;
        final int bitrate;

        Item(JSONObject o) {
            source = o.optString("source", "laut");
            id = o.optString("id", "");
            name = o.optString("name", id);
            description = o.optString("description", "");
            cover = o.optString("cover", "");
            link = o.optString("link", "");
            country = o.optString("country", "");
            stream = o.optString("stream", "");
            codec = o.optString("codec", "");
            own = o.optBoolean("own", false);
            bitrate = o.optInt("bitrate", 0);
            JSONArray g = o.optJSONArray("genres");
            if (g != null) for (int i = 0; i < g.length(); i++) {
                String s = g.optString(i, "").trim();
                if (!s.isEmpty()) genres.add(s);
            }
        }

        boolean isWorld() { return "world".equals(source); }

        /** Eindeutiger Schluessel, auch fuer Favoriten ("laut:<name>" bzw. "world:<uuid>"). */
        String key() { return source + ":" + id; }

        /** Nur mit https-Stream abspielbar (der Server liefert "" bei http oder wenn Fremd-Streams abgeschaltet sind). */
        boolean playable() { return !isWorld() || !stream.isEmpty(); }

        /** Zweite Zeile in Listen: Genres, Land, Bitrate. */
        String sub() {
            List<String> bits = new ArrayList<>();
            if (!genres.isEmpty()) bits.add(join(genres, 3));
            if (!country.isEmpty()) bits.add(country);
            if (isWorld() && bitrate > 0) bits.add(bitrate + " kbit/s");
            return join(bits, 4);
        }

        JSONObject toJson() {
            try {
                JSONObject o = new JSONObject();
                o.put("source", source).put("id", id).put("name", name).put("description", description)
                        .put("cover", cover).put("link", link).put("country", country).put("stream", stream)
                        .put("codec", codec).put("own", own).put("bitrate", bitrate);
                JSONArray g = new JSONArray();
                for (String s : genres) g.put(s);
                o.put("genres", g);
                return o;
            } catch (Exception e) {
                return new JSONObject();
            }
        }

        private static String join(List<String> parts, int max) {
            StringBuilder b = new StringBuilder();
            for (int i = 0; i < parts.size() && i < max; i++) {
                if (i > 0) b.append(" · ");
                b.append(parts.get(i));
            }
            return b.toString();
        }
    }

    /** Ergebnis einer Suche bzw. Zufallsauswahl. */
    static final class Page {
        final List<Item> items = new ArrayList<>();
        boolean hasMore;
        boolean reportOn = true;
    }

    static List<Item> parse(JSONArray arr) {
        List<Item> out = new ArrayList<>();
        if (arr == null) return out;
        for (int i = 0; i < arr.length(); i++) {
            JSONObject o = arr.optJSONObject(i);
            if (o == null) continue;
            Item it = new Item(o);
            if (!it.id.isEmpty()) out.add(it);
        }
        return out;
    }

    private static String api(String site, String action) { return site + "/cms/api.php?action=" + action; }

    private static String enc(String s) {
        try { return URLEncoder.encode(s, "UTF-8"); } catch (Exception e) { return ""; }
    }

    private static String get(String address) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(address).openConnection();
        c.setConnectTimeout(9000);
        c.setReadTimeout(15000);
        c.setRequestProperty("Accept", "application/json");
        c.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try (BufferedReader r = new BufferedReader(new InputStreamReader(c.getInputStream(), StandardCharsets.UTF_8))) {
            StringBuilder b = new StringBuilder();
            String line;
            while ((line = r.readLine()) != null) b.append(line);
            return b.toString();
        } finally {
            c.disconnect();
        }
    }

    /** scope: "all", "laut" oder "world". */
    static Page search(String site, String q, String scope, int offset, int limit) throws Exception {
        JSONObject d = new JSONObject(get(api(site, "directory_search") + "&q=" + enc(q) + "&scope=" + enc(scope)
                + "&offset=" + offset + "&limit=" + limit));
        Page p = new Page();
        p.items.addAll(parse(d.optJSONArray("results")));
        p.hasMore = d.optBoolean("has_more", false);
        p.reportOn = d.optBoolean("report", true);
        return p;
    }

    /** kind: "mix", "laut" oder "world". */
    static Page random(String site, String kind, int n) throws Exception {
        JSONObject d = new JSONObject(get(api(site, "directory_random") + "&kind=" + enc(kind) + "&n=" + n));
        Page p = new Page();
        p.items.addAll(parse(d.optJSONArray("results")));
        p.reportOn = d.optBoolean("report", true);
        return p;
    }

    /** Titel/Bild aus den ICY-Metadaten des Streams (Server-seitig mit Zwischenspeicher). Leere Felder, wenn nichts mitgesendet wird. */
    static String[] meta(String site, String streamUrl) {
        try {
            JSONObject d = new JSONObject(get(api(site, "directory_meta") + "&url=" + enc(streamUrl)));
            return new String[]{d.optString("title", ""), d.optString("image", "")};
        } catch (Exception e) {
            return new String[]{"", ""};
        }
    }

    /** Meldung an die Redaktion (landet im CMS unter Radioverzeichnis → Meldungen). */
    static String report(String site, Item it, String reason, String text) {
        try {
            JSONObject b = new JSONObject();
            b.put("source", it.source).put("id", it.id).put("name", it.name).put("link", it.link)
                    .put("reason", reason).put("text", text == null ? "" : text).put("website", "");
            byte[] body = b.toString().getBytes(StandardCharsets.UTF_8);
            HttpURLConnection c = (HttpURLConnection) new URL(api(site, "directory_report")).openConnection();
            c.setRequestMethod("POST");
            c.setConnectTimeout(9000);
            c.setReadTimeout(15000);
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            c.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
            try (OutputStream os = c.getOutputStream()) { os.write(body); }
            int code = c.getResponseCode();
            c.disconnect();
            return code == 200 ? "" : (code == 429 ? "Zu viele Meldungen – bitte später erneut versuchen." : "Meldung momentan nicht möglich.");
        } catch (Exception e) {
            return "Meldung momentan nicht möglich.";
        }
    }

    /** Gründe wie im Portal (Schluessel = Server-Werte). */
    static final String[][] REASONS = {
            {"rights", "Rechtsverletzung / Urheberrecht"},
            {"offline", "Nicht erreichbar / defekt"},
            {"content", "Unpassender Inhalt"},
            {"wrong", "Falsche Angaben"},
            {"other", "Sonstiges"}};

    static String lower(String s) { return s == null ? "" : s.toLowerCase(Locale.ROOT); }
}
