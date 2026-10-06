package app.elvadopress.client;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;

import androidx.media3.common.MediaItem;
import androidx.media3.common.MediaMetadata;
import androidx.media3.common.MimeTypes;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/** Gemeinsame Sender-Helfer für App, Android Auto (Browse-Baum) und Cast. */
final class Stations {
    private Stations() {}

    /** Anzeigename eines Senders ohne Konfiguration: die Kennung (der echte Name kommt von laut.fm bzw. aus der Konfiguration der Website). */
    static String prettyName(String id) {
        return id == null ? "" : id;
    }

    /** Anzeigename mit eigenen Sendern der App (layout.stations.custom: title). */
    static String displayName(SharedPreferences prefs, String id) {
        JSONObject c = custom(prefs, id);
        return c != null && !c.optString("title").isEmpty() ? c.optString("title") : prettyName(id);
    }

    /** Eigene Sender aus dem App-Layout des CMS (Apps → Layout → Eigene Sender): id, title, stream, optional laut, logo. */
    static List<JSONObject> customList(SharedPreferences prefs) {
        List<JSONObject> out = new ArrayList<>();
        try {
            String raw = prefs.getString("app_layout", "");
            JSONObject st = raw.isEmpty() ? null : new JSONObject(raw).optJSONObject("stations");
            JSONArray arr = st == null ? null : st.optJSONArray("custom");
            if (arr != null) for (int i = 0; i < arr.length(); i++) {
                JSONObject c = arr.optJSONObject(i);
                if (c != null && !c.optString("id").isEmpty() && c.optString("stream").startsWith("https://")) out.add(c);
            }
        } catch (Exception ignored) {
        }
        return out;
    }

    static JSONObject custom(SharedPreferences prefs, String id) {
        if (id == null) return null;
        for (JSONObject c : customList(prefs)) if (id.equals(c.optString("id"))) return c;
        return null;
    }

    /** Stream-Adresse: eigener Sender mit https-Adresse, sonst der laut.fm-Stream der Kennung. */
    static String streamUrl(SharedPreferences prefs, String id) {
        JSONObject c = custom(prefs, id);
        return c != null ? c.optString("stream") : "https://stream.laut.fm/" + id;
    }

    /** laut.fm-Kennung für Titel- und Sendeplan-Abfragen: bei eigenen Sendern nur, wenn sie ein laut.fm-Sender sind, sonst leer. */
    static String lautId(SharedPreferences prefs, String id) {
        JSONObject c = custom(prefs, id);
        return c == null ? id : c.optString("laut");
    }

    /** Core-Sender in der im App-Builder festgelegten Reihenfolge, ohne ausgeblendete (wie auf der Startseite). */
    static List<String> owned(Context ctx, SharedPreferences prefs) {
        List<String> all = AppConfig.load(ctx).ownedStationsList();
        for (JSONObject c : customList(prefs)) if (!all.contains(c.optString("id"))) all.add(c.optString("id"));   // eigene Sender hinter dem Core-Netzwerk
        try {
            String raw = prefs.getString("app_layout", "");
            JSONObject st = raw.isEmpty() ? null : new JSONObject(raw).optJSONObject("stations");
            if (st == null) return all;
            List<String> out = new ArrayList<>();
            JSONArray order = st.optJSONArray("order");
            JSONArray hidden = st.optJSONArray("hidden");
            Set<String> hide = new HashSet<>();
            if (hidden != null) for (int i = 0; i < hidden.length(); i++) hide.add(hidden.optString(i));
            if (order != null) for (int i = 0; i < order.length(); i++) {
                String id = order.optString(i);
                if (all.contains(id) && !out.contains(id)) out.add(id);
            }
            for (String id : all) if (!out.contains(id)) out.add(id);
            out.removeAll(hide);
            return out.isEmpty() ? all : out;
        } catch (Exception e) {
            return all;
        }
    }

    /** MIME-Typ für Cast (CastPlayer verlangt ihn) und ExoPlayer: HLS/AAC erkennen, sonst MP3. */
    static String mime(String url, String codec) {
        String u = url == null ? "" : url.toLowerCase();
        if (u.contains(".m3u8")) return MimeTypes.APPLICATION_M3U8;
        String c = codec == null ? "" : codec.toLowerCase();
        if (c.contains("aac") || u.endsWith(".aac")) return MimeTypes.AUDIO_AAC;
        if (c.contains("ogg") || u.endsWith(".ogg")) return "audio/ogg";
        return MimeTypes.AUDIO_MPEG;
    }

    /** Spielbares Item für einen laut.fm-Sender ("station:<id>"), Titelbild aus dem Zwischenspeicher der App. */
    static MediaItem stationItem(SharedPreferences prefs, String id, String artist) {
        MediaMetadata.Builder meta = new MediaMetadata.Builder().setTitle(displayName(prefs, id)).setArtist(artist).setIsPlayable(true).setIsBrowsable(false)
                .setMediaType(MediaMetadata.MEDIA_TYPE_RADIO_STATION);
        JSONObject cu = custom(prefs, id);
        String cover = cu != null && !cu.optString("logo").isEmpty() ? cu.optString("logo") : prefs.getString("cover_" + id, "");
        if (!cover.isEmpty()) meta.setArtworkUri(Uri.parse(cover));
        String url = streamUrl(prefs, id);
        return new MediaItem.Builder().setMediaId("station:" + id).setUri(url).setMimeType(mime(url, ""))
                .setMediaMetadata(meta.build()).build();
    }
}
