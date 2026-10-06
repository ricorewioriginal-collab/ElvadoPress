package app.elvadopress.client;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertNotNull;
import static org.junit.Assert.assertTrue;

import org.json.JSONObject;
import org.junit.Test;

/** Prüft, dass die Funktionen der Radio-App aus android/brands.json ("radio") als gültiges JSON in der App ankommen (Anführungszeichen, Umlaute, Pfade). */
public class RadioConfigTest {
    @Test
    public void radioConfigIstGueltigesJson() throws Exception {
        JSONObject c = new JSONObject(BuildConfig.RADIO_CONFIG);
        assertNotNull(c);
        if (c.optBoolean("podcast", false)) {
            // Beispiel-App "demoradio" aus scripts/verify-app-template.sh
            assertEquals("https://example.org/community/", c.getString("communityBase"));
            JSONObject shop = c.getJSONArray("shops").getJSONObject(0);
            assertEquals("Shop \"A\" – Größe", shop.getString("title"));
            assertTrue(shop.getString("url").startsWith("https://"));
        }
    }
}
