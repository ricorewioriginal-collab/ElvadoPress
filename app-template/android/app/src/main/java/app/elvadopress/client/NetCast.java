package app.elvadopress.client;

import android.content.Context;
import android.net.nsd.NsdManager;
import android.net.nsd.NsdServiceInfo;
import android.net.wifi.WifiManager;
import android.os.Handler;
import android.os.Looper;

import java.nio.charset.StandardCharsets;
import java.util.ArrayDeque;
import java.util.HashSet;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * Übertragen auf Geräte im WLAN ohne Google-Dienste: Chromecast (CASTV2, Suche per mDNS) und DLNA/UPnP-Renderer
 * (Suche per SSDP). Hält höchstens eine aktive Verbindung; die lokale Wiedergabe wird von der Activity pausiert.
 */
final class NetCast {
    static final class Media {
        String url = "", mime = "audio/mpeg", title = "", artist = "", album = "", image = "";
        boolean live = true;
    }

    static final class Found {
        String kind = "";     // "chromecast" | "dlna"
        String id = "", name = "", host = "";
        int port = 8009;
        DlnaClient.Device dlna;
        String label() { return "chromecast".equals(kind) ? "Chromecast" : "DLNA"; }
    }

    interface ScanListener { void onFound(Found f); void onDone(); }
    interface ConnectListener { void onResult(boolean ok, String message); }

    private static NetCast inst;
    private final Context app;
    private final Handler main = new Handler(Looper.getMainLooper());
    private final ExecutorService io = Executors.newCachedThreadPool();

    // Suche
    private NsdManager.DiscoveryListener disc;
    private final ArrayDeque<NsdServiceInfo> resolveQ = new ArrayDeque<>();
    private boolean resolving;
    private volatile boolean scanning;
    private WifiManager.MulticastLock lock;
    private final Set<String> seen = new HashSet<>();

    // Sitzung
    private volatile Found current;
    private CastV2Client chromecast;
    private DlnaClient dlna;
    private volatile boolean playing;
    private Runnable stateListener;

    static synchronized NetCast get(Context c) {
        if (inst == null) inst = new NetCast(c.getApplicationContext());
        return inst;
    }

    private NetCast(Context app) { this.app = app; }

    void setStateListener(Runnable r) { stateListener = r; }
    private void changed() { Runnable r = stateListener; if (r != null) main.post(r); }

    boolean isActive() { return current != null; }
    boolean isPlaying() { return current != null && playing; }
    String name() { Found f = current; return f == null ? "" : f.name; }
    String kind() { Found f = current; return f == null ? "" : f.label(); }

    // ---------- Suche ----------

    void scan(ScanListener l) {
        stopScan();
        scanning = true;
        seen.clear();
        try {
            WifiManager wm = (WifiManager) app.getSystemService(Context.WIFI_SERVICE);
            if (wm != null) { lock = wm.createMulticastLock("app-netcast"); lock.setReferenceCounted(false); lock.acquire(); }
        } catch (Exception ignored) { }
        // Chromecast per mDNS
        try {
            final NsdManager nsd = (NsdManager) app.getSystemService(Context.NSD_SERVICE);
            disc = new NsdManager.DiscoveryListener() {
                @Override public void onStartDiscoveryFailed(String t, int e) { }
                @Override public void onStopDiscoveryFailed(String t, int e) { }
                @Override public void onDiscoveryStarted(String t) { }
                @Override public void onDiscoveryStopped(String t) { }
                @Override public void onServiceLost(NsdServiceInfo i) { }
                @Override public void onServiceFound(NsdServiceInfo i) {
                    synchronized (resolveQ) { resolveQ.add(i); }
                    pump(nsd, l);
                }
            };
            nsd.discoverServices("_googlecast._tcp", NsdManager.PROTOCOL_DNS_SD, disc);
        } catch (Exception ignored) { disc = null; }
        // DLNA per SSDP
        io.execute(() -> {
            DlnaClient.discover(6000, d -> {
                if (!scanning) return;
                Found f = new Found();
                f.kind = "dlna"; f.id = "dlna:" + d.location; f.name = d.name; f.host = d.host; f.port = d.port; f.dlna = d;
                deliver(f, l);
            });
        });
        main.postDelayed(() -> { if (scanning) { stopScan(); l.onDone(); } }, 10000);
    }

    @SuppressWarnings("deprecation")
    private void pump(NsdManager nsd, ScanListener l) {
        NsdServiceInfo next;
        synchronized (resolveQ) {
            if (resolving || (next = resolveQ.poll()) == null) return;
            resolving = true;
        }
        try {
            nsd.resolveService(next, new NsdManager.ResolveListener() {
                @Override public void onResolveFailed(NsdServiceInfo i, int e) { done(); }
                @Override public void onServiceResolved(NsdServiceInfo i) {
                    try {
                        Found f = new Found();
                        f.kind = "chromecast";
                        Map<String, byte[]> a = i.getAttributes();
                        byte[] fn = a == null ? null : a.get("fn");
                        byte[] id = a == null ? null : a.get("id");
                        f.name = fn != null ? new String(fn, StandardCharsets.UTF_8) : i.getServiceName();
                        f.host = i.getHost() == null ? "" : i.getHost().getHostAddress();
                        f.port = i.getPort() > 0 ? i.getPort() : 8009;
                        f.id = "cc:" + (id != null ? new String(id, StandardCharsets.UTF_8) : f.host);
                        if (!f.host.isEmpty()) deliver(f, l);
                    } finally { done(); }
                }
                private void done() { synchronized (resolveQ) { resolving = false; } pump(nsd, l); }
            });
        } catch (Exception e) {
            synchronized (resolveQ) { resolving = false; }
        }
    }

    private void deliver(Found f, ScanListener l) {
        synchronized (seen) { if (!seen.add(f.id)) return; }
        main.post(() -> { if (scanning) l.onFound(f); });
    }

    void stopScan() {
        scanning = false;
        try {
            if (disc != null) ((NsdManager) app.getSystemService(Context.NSD_SERVICE)).stopServiceDiscovery(disc);
        } catch (Exception ignored) { }
        disc = null;
        synchronized (resolveQ) { resolveQ.clear(); resolving = false; }
        try { if (lock != null && lock.isHeld()) lock.release(); } catch (Exception ignored) { }
    }

    // ---------- Sitzung ----------

    void connect(Found f, Media m, ConnectListener cl) {
        stopScan();
        disconnectQuiet();
        io.execute(() -> {
            try {
                if ("chromecast".equals(f.kind)) {
                    CastV2Client c = new CastV2Client(f.host, f.port, new CastV2Client.Events() {
                        @Override public void onState(String s) {
                            if ("PLAYING".equals(s) || "BUFFERING".equals(s)) playing = true;
                            else if ("PAUSED".equals(s) || "IDLE".equals(s)) playing = false;
                            changed();
                        }
                        @Override public void onClosed(String reason) {
                            if (current == f) { current = null; chromecast = null; playing = false; changed(); }
                        }
                    });
                    c.connect();
                    chromecast = c;
                    current = f;
                    c.load(m.url, m.mime, m.live, m.title, m.artist, m.album, m.image);
                } else {
                    dlna = new DlnaClient(f.dlna);
                    current = f;
                    dlna.play(dlnaUrl(m.url), m.mime, castLabel(m));
                    playing = true;
                }
                changed();
                main.post(() -> cl.onResult(true, ""));
            } catch (Exception e) {
                current = null; chromecast = null; dlna = null; playing = false;
                main.post(() -> cl.onResult(false, "Verbindung zu " + f.name + " nicht möglich."));
            }
        });
    }

    /** Einzeiliger Titel für Geräte ohne Künstler-/Album-Felder (DLNA): "Titel – Künstler". */
    private static String castLabel(Media m) {
        return m.artist == null || m.artist.isEmpty() || m.artist.equals(m.title) ? m.title : m.title + " – " + m.artist;
    }

    // Viele DLNA-Geräte können kein https: laut.fm liefert dieselben Streams auch per http
    private static String dlnaUrl(String url) {
        return url.startsWith("https://stream.laut.fm/") ? "http://" + url.substring(8) : url;
    }

    /** Anderen Sender/Titel auf dem verbundenen Gerät abspielen. */
    void load(Media m) {
        final Found f = current;
        if (f == null) return;
        io.execute(() -> {
            try {
                if (chromecast != null) chromecast.load(m.url, m.mime, m.live, m.title, m.artist, m.album, m.image);
                else if (dlna != null) { dlna.play(dlnaUrl(m.url), m.mime, castLabel(m)); playing = true; changed(); }
            } catch (Exception ignored) { }
        });
    }

    void togglePause() {
        final boolean wasPlaying = playing;
        io.execute(() -> {
            try {
                if (chromecast != null) { if (wasPlaying) chromecast.pause(); else chromecast.play(); }
                else if (dlna != null) {
                    if (wasPlaying) { try { dlna.pause(); } catch (Exception e) { dlna.stop(); } } else dlna.resume();
                    playing = !wasPlaying;
                    changed();
                }
            } catch (Exception ignored) { }
        });
    }

    void setVolume(int percent) {
        io.execute(() -> {
            try {
                if (chromecast != null) chromecast.setVolume(percent / 100.0);
                else if (dlna != null) dlna.setVolume(percent);
            } catch (Exception ignored) { }
        });
    }

    void disconnect() {
        final CastV2Client c = chromecast;
        final DlnaClient d = dlna;
        disconnectQuiet();
        io.execute(() -> {
            try { if (c != null) c.stop(); else if (d != null) d.stop(); } catch (Exception ignored) { }
        });
    }

    private void disconnectQuiet() {
        current = null; chromecast = null; dlna = null; playing = false;
        changed();
    }
}
