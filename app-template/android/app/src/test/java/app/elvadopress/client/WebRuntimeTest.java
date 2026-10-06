package app.elvadopress.client;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import org.junit.Test;

/** Prüft die Auswertung der CMS-Antwort (app_config) der Website-App – ohne Netz und ohne Gerät. */
public class WebRuntimeTest {
    private static WebRuntime.Config parse(String json) throws Exception {
        WebRuntime.Config c = new WebRuntime.Config();
        WebRuntime.parse(json, c, "https://example.org");
        return c;
    }

    @Test
    public void leereAntwortSperrtNichts() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"ok\",\"maintenance\":null,\"notice\":null,\"update\":{\"required\":false}}");
        assertFalse(c.maintenance);
        assertFalse(c.updateRequired);
        assertFalse(c.hasNotice());
    }

    @Test
    public void wartungsmodusMitTextundStandardtitel() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"ok\",\"maintenance\":{\"title\":\"Wartungsarbeiten\",\"text\":\"Bis 18 Uhr.\"}}");
        assertTrue(c.maintenance);
        assertEquals("Wartungsarbeiten", c.maintenanceTitle);
        assertEquals("Bis 18 Uhr.", c.maintenanceText);
    }

    @Test
    public void pflichtUpdateNimmtNurHttpsAdressen() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"ok\",\"update\":{\"required\":true,\"page\":\"http://unsicher.example/\",\"url\":\"https://example.org/app.apk\"}}");
        assertTrue(c.updateRequired);
        assertEquals("https://example.org/app.apk", c.updateUrl);
        WebRuntime.Config d = parse("{\"status\":\"ok\",\"update\":{\"required\":true,\"page\":\"javascript:alert(1)\"}}");
        assertTrue(d.updateRequired);
        assertEquals("", d.updateUrl);
    }

    @Test
    public void hinweisMitStufeLinkUndKennung() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"ok\",\"notice\":{\"id\":\"abc123\",\"level\":\"warn\",\"title\":\"Hallo\",\"text\":\"Neuer Katalog\",\"url\":\"https://shop.example/\",\"url_label\":\"Ansehen\"}}");
        assertTrue(c.hasNotice());
        assertEquals("warn", c.noticeLevel);
        assertEquals("abc123", c.noticeId);
        assertEquals("https://shop.example/", c.noticeUrl);
        assertEquals("Ansehen", c.noticeLabel);
        WebRuntime.Config d = parse("{\"status\":\"ok\",\"notice\":{\"id\":\"x\",\"level\":\"boese\",\"title\":\"T\",\"url\":\"ftp://x\"}}");
        assertEquals("info", d.noticeLevel);
        assertEquals("", d.noticeUrl);
    }

    @Test
    public void fehlerAntwortWirdIgnoriert() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"error\",\"maintenance\":{\"title\":\"x\"}}");
        assertFalse(c.maintenance);
    }

    @Test
    public void tabLeisteLoestPfadeAufUndVerwirftUnsicheres() throws Exception {
        WebRuntime.Config c = parse("{\"status\":\"ok\",\"tabs\":[{\"title\":\"Start\",\"icon\":\"home\",\"url\":\"/\"},"
                + "{\"title\":\"Shop\",\"icon\":\"shop\",\"url\":\"https://shop.example.org/\"},"
                + "{\"title\":\"Böse\",\"icon\":\"star\",\"url\":\"javascript:alert(1)\"},"
                + "{\"title\":\"\",\"icon\":\"star\",\"url\":\"/leer/\"}]}");
        assertEquals(2, c.tabs.size());
        assertEquals("https://example.org/", c.tabs.get(0)[2]);
        assertEquals("home", c.tabs.get(0)[1]);
        assertEquals("https://shop.example.org/", c.tabs.get(1)[2]);
    }

    @Test
    public void ohneTabsKeineLeiste() throws Exception {
        assertTrue(parse("{\"status\":\"ok\"}").tabs.isEmpty());
        assertTrue(WebRuntime.parseTabs("kaputt", "https://example.org").isEmpty());
    }
}
