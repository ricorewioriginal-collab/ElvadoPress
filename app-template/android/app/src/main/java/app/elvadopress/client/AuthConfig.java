package app.elvadopress.client;

import android.content.Context;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

public final class AuthConfig {
    public String mode = "local-first";
    public boolean brokerEnabled = false;
    public String issuer = "";
    public String clientId = "";
    public String redirectUri = "app.elvadopress.client.dev://oauth/callback";
    public final List<String> enabledProviders = new ArrayList<>();

    public static AuthConfig load(Context context) {
        AuthConfig cfg = new AuthConfig();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(
                context.getAssets().open("config/auth-config.json"), StandardCharsets.UTF_8))) {
            StringBuilder b = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) b.append(line);
            JSONObject json = new JSONObject(b.toString());
            cfg.mode = json.optString("mode", cfg.mode);

            JSONObject broker = json.optJSONObject("broker");
            if (broker != null) {
                cfg.brokerEnabled = broker.optBoolean("enabled", false);
                cfg.issuer = broker.optString("issuer", "");
                cfg.clientId = broker.optString("clientId", "");
                cfg.redirectUri = broker.optString("redirectUri", cfg.redirectUri);
            }

            JSONArray providers = json.optJSONArray("providers");
            if (providers != null) {
                for (int i = 0; i < providers.length(); i++) {
                    JSONObject p = providers.optJSONObject(i);
                    if (p != null && p.optBoolean("enabled", false)) {
                        String id = p.optString("id", "").trim();
                        if (!id.isEmpty()) cfg.enabledProviders.add(id);
                    }
                }
            }
        } catch (Exception ignored) {
        }
        return cfg;
    }

    public boolean isReady() {
        return brokerEnabled && !issuer.trim().isEmpty() && !clientId.trim().isEmpty();
    }
}
