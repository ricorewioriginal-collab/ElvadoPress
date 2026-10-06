package app.elvadopress.client;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONObject;

import java.nio.charset.StandardCharsets;
import android.util.Base64;
import java.util.UUID;

public final class AccountStore {
    private static final String PREF = "app_account";
    private static final String K_LOCAL_ID = "local_id";
    private static final String K_SUB = "sub";
    private static final String K_EMAIL = "email";
    private static final String K_NAME = "name";
    private static final String K_ID_TOKEN = "id_token";

    private final SharedPreferences prefs;

    public AccountStore(Context context) {
        prefs = context.getSharedPreferences(PREF, Context.MODE_PRIVATE);
        ensureLocalId();
    }

    private void ensureLocalId() {
        if (!prefs.contains(K_LOCAL_ID)) {
            prefs.edit().putString(K_LOCAL_ID, UUID.randomUUID().toString()).apply();
        }
    }

    public String getLocalId() {
        return prefs.getString(K_LOCAL_ID, "");
    }

    public boolean isSignedIn() {
        return !prefs.getString(K_SUB, "").isEmpty();
    }

    public String getDisplayName() {
        String name = prefs.getString(K_NAME, "");
        if (!name.isEmpty()) return name;
        String email = prefs.getString(K_EMAIL, "");
        if (!email.isEmpty()) return email;
        return "Lokales Profil";
    }

    public String getEmail() {
        return prefs.getString(K_EMAIL, "");
    }

    public String getSubject() {
        return prefs.getString(K_SUB, "");
    }

    public String getIdToken() {
        return prefs.getString(K_ID_TOKEN, "");
    }

    public void saveIdToken(String idToken) {
        String sub = "";
        String email = "";
        String name = "";
        try {
            String[] parts = idToken.split("\\.");
            if (parts.length >= 2) {
                byte[] decoded = Base64.decode(parts[1], Base64.URL_SAFE | Base64.NO_WRAP | Base64.NO_PADDING);
                JSONObject payload = new JSONObject(new String(decoded, StandardCharsets.UTF_8));
                sub = payload.optString("sub", "");
                email = payload.optString("email", "");
                name = payload.optString("name", payload.optString("preferred_username", ""));
            }
        } catch (Exception ignored) {
        }

        prefs.edit()
                .putString(K_SUB, sub)
                .putString(K_EMAIL, email)
                .putString(K_NAME, name)
                .putString(K_ID_TOKEN, idToken)
                .apply();
    }

    public void signOut() {
        prefs.edit()
                .remove(K_SUB)
                .remove(K_EMAIL)
                .remove(K_NAME)
                .remove(K_ID_TOKEN)
                .apply();
    }
}
