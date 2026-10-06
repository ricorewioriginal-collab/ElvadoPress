package app.elvadopress.client;

import android.content.Context;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

public final class AppConfig {
    public final Set<String> ownedStations = new HashSet<>();
    private final List<String> ownedStationOrder = new ArrayList<>();
    public String website = "";
    public String communityBase = "";
    public boolean podcast = false;
    public final List<String[]> shops = new ArrayList<>();   // {Titel, Beschreibung, https-Adresse}
    public String imprint = "";
    public String privacy = "";
    public String lautfmTerms = "https://laut.fm/pages/terms_and_conditions";

    public boolean hasCommunity() { return !communityBase.isEmpty(); }
    public boolean hasShops() { return !shops.isEmpty(); }

    public static AppConfig load(Context context) {
        AppConfig cfg = new AppConfig();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(
                context.getAssets().open("config/app-config.json"), StandardCharsets.UTF_8))) {
            StringBuilder b = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) b.append(line);
            JSONObject json = new JSONObject(b.toString());

            JSONArray stations = json.optJSONArray("ownedStations");
            if (stations != null) {
                for (int i = 0; i < stations.length(); i++) {
                    String id = stations.optString(i, "").trim();
                    if (!id.isEmpty()) {
                        cfg.ownedStations.add(id);
                        cfg.ownedStationOrder.add(id);
                    }
                }
            }

            JSONObject links = json.optJSONObject("links");
            if (links != null) {
                cfg.website = links.optString("website", cfg.website);
                cfg.imprint = links.optString("imprint", cfg.imprint);
                cfg.privacy = links.optString("privacy", cfg.privacy);
                cfg.lautfmTerms = links.optString("lautfmTerms", cfg.lautfmTerms);
                JSONArray shops = links.optJSONArray("shops");
                if (shops != null) for (int i = 0; i < shops.length(); i++) {
                    JSONObject sh = shops.optJSONObject(i);
                    if (sh == null || !sh.optString("url").startsWith("https://")) continue;
                    cfg.shops.add(new String[]{sh.optString("title", sh.optString("url")), sh.optString("desc", ""), sh.optString("url")});
                }
            }
            String cb = json.optString("communityBase", "");
            if (cb.startsWith("https://")) cfg.communityBase = cb.endsWith("/") ? cb : cb + "/";
            cfg.podcast = json.optBoolean("podcast", false);
        } catch (Exception ignored) {
        }
        // Funktionen je Marke aus android/brands.json ("radio": podcast, communityBase, shops) – überschreiben die Datei app-config.json
        try {
            JSONObject radio = new JSONObject(BuildConfig.RADIO_CONFIG);
            if (radio.has("podcast")) cfg.podcast = radio.optBoolean("podcast", false);
            String cb = radio.optString("communityBase", "");
            if (cb.startsWith("https://")) cfg.communityBase = cb.endsWith("/") ? cb : cb + "/";
            JSONArray shops = radio.optJSONArray("shops");
            if (shops != null) {
                cfg.shops.clear();
                for (int i = 0; i < shops.length(); i++) {
                    JSONObject sh = shops.optJSONObject(i);
                    if (sh == null || !sh.optString("url").startsWith("https://")) continue;
                    cfg.shops.add(new String[]{sh.optString("title", sh.optString("url")), sh.optString("desc", ""), sh.optString("url")});
                }
            }
        } catch (Exception ignored) {
        }
        // Das Portal der App ist die Website der Marke (android/brands.json → site); Impressum und Datenschutz liegen dort, sofern nichts anderes konfiguriert ist
        try {
            String site = context.getString(R.string.site_base).trim();
            if (!site.isEmpty()) {
                cfg.website = site.replaceAll("/+$", "") + "/";
                if (cfg.imprint.isEmpty()) cfg.imprint = cfg.website + "#impressum";
                if (cfg.privacy.isEmpty()) cfg.privacy = cfg.website + "#datenschutz";
            }
        } catch (Exception ignored) {
        }
        return cfg;
    }

    public synchronized void replaceOwnedStations(List<String> stations) {
        ownedStations.clear();
        ownedStationOrder.clear();
        if (stations == null) return;
        for (String raw : stations) {
            String id = raw == null ? "" : raw.trim().toLowerCase();
            if (id.isEmpty() || ownedStations.contains(id)) continue;
            ownedStations.add(id);
            ownedStationOrder.add(id);
        }
    }

    public boolean isOwned(String stationId) {
        return stationId != null && ownedStations.contains(stationId);
    }

    public List<String> ownedStationsList() {
        return new ArrayList<>(ownedStationOrder);
    }
}
