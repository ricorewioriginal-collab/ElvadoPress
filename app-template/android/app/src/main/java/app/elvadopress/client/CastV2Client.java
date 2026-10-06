package app.elvadopress.client;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.DataInputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.security.cert.X509Certificate;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;

/**
 * Chromecast ohne Google Play Services: CASTV2 direkt (TLS, Port 8009, längenpräfixierte Protobuf-Nachrichten).
 * Startet den Standard-Media-Receiver, lädt den Stream per LOAD und steuert Pause/Lautstärke. Nur im lokalen Netz.
 * Das Gerätezertifikat ist selbstsigniert, daher wird hier (nur für diese LAN-Verbindung) kein Zertifikat geprüft.
 * Reines Java (nur org.json), ohne Android-Klassen – testbar auf der JVM.
 */
final class CastV2Client {
    interface Events {
        /** Zustand: "connecting", "ready", "PLAYING", "PAUSED", "BUFFERING", "IDLE" */
        void onState(String state);
        void onClosed(String reason);
    }

    static final String APP_ID = "CC1AD845"; // Default Media Receiver
    private static final String NS_CONN = "urn:x-cast:com.google.cast.tp.connection";
    private static final String NS_HB = "urn:x-cast:com.google.cast.tp.heartbeat";
    private static final String NS_RECV = "urn:x-cast:com.google.cast.receiver";
    private static final String NS_MEDIA = "urn:x-cast:com.google.cast.media";

    private final String host;
    private final int port;
    private final Events events;
    private Socket socket;
    private OutputStream out;
    private final Object writeLock = new Object();
    private ScheduledExecutorService beat;
    private volatile boolean closed;
    private int requestId = 1;
    private String transportId, sessionId;
    private long mediaSessionId = -1;
    private JSONObject pendingLoad;
    private boolean launching;
    private volatile long lastRx;

    CastV2Client(String host, int port, Events events) {
        this.host = host;
        this.port = port <= 0 ? 8009 : port;
        this.events = events;
    }

    // ---------- Verbindung ----------

    void connect() throws Exception {
        SSLContext ctx = SSLContext.getInstance("TLS");
        ctx.init(null, new TrustManager[]{new X509TrustManager() {
            @Override public void checkClientTrusted(X509Certificate[] c, String a) { }
            @Override public void checkServerTrusted(X509Certificate[] c, String a) { }
            @Override public X509Certificate[] getAcceptedIssuers() { return new X509Certificate[0]; }
        }}, new SecureRandom());
        Socket plain = new Socket();
        plain.connect(new InetSocketAddress(host, port), 5000);
        socket = ctx.getSocketFactory().createSocket(plain, host, port, true);
        socket.setSoTimeout(0);
        out = socket.getOutputStream();
        lastRx = System.currentTimeMillis();
        events.onState("connecting");
        send(NS_CONN, "receiver-0", new JSONObject().put("type", "CONNECT"));
        Thread reader = new Thread(this::readLoop, "castv2-reader");
        reader.setDaemon(true);
        reader.start();
        beat = Executors.newSingleThreadScheduledExecutor(r -> { Thread t = new Thread(r, "castv2-beat"); t.setDaemon(true); return t; });
        beat.scheduleAtFixedRate(() -> {
            try {
                if (System.currentTimeMillis() - lastRx > 20000) { close("Zeitüberschreitung"); return; }
                send(NS_HB, "receiver-0", new JSONObject().put("type", "PING"));
            } catch (Exception e) { close("Verbindung getrennt"); }
        }, 5, 5, TimeUnit.SECONDS);
    }

    // ---------- Steuerung ----------

    /** Stream laden (startet den Receiver beim ersten Mal). */
    synchronized void load(String url, String mime, boolean live, String title, String artist, String album, String image) throws Exception {
        JSONObject meta = new JSONObject().put("metadataType", 3).put("title", title == null ? "" : title).put("artist", artist == null ? "" : artist);
        if (album != null && !album.isEmpty()) meta.put("albumName", album);
        if (image != null && !image.isEmpty()) meta.put("images", new JSONArray().put(new JSONObject().put("url", image)));
        pendingLoad = new JSONObject().put("type", "LOAD").put("autoplay", true).put("currentTime", 0)
                .put("media", new JSONObject().put("contentId", url).put("streamType", live ? "LIVE" : "BUFFERED").put("contentType", mime).put("metadata", meta));
        if (transportId != null) {
            sendLoad();
        } else if (!launching) {
            launching = true;
            send(NS_RECV, "receiver-0", new JSONObject().put("type", "LAUNCH").put("appId", APP_ID).put("requestId", next()));
        }
    }

    void pause() throws Exception { mediaCommand("PAUSE"); }
    void play() throws Exception { mediaCommand("PLAY"); }

    void setVolume(double level) throws Exception {
        send(NS_RECV, "receiver-0", new JSONObject().put("type", "SET_VOLUME").put("requestId", next())
                .put("volume", new JSONObject().put("level", Math.max(0, Math.min(1, level)))));
    }

    /** App am Gerät beenden und trennen. */
    void stop() {
        try {
            if (sessionId != null) send(NS_RECV, "receiver-0", new JSONObject().put("type", "STOP").put("sessionId", sessionId).put("requestId", next()));
            if (transportId != null) send(NS_CONN, transportId, new JSONObject().put("type", "CLOSE"));
            send(NS_CONN, "receiver-0", new JSONObject().put("type", "CLOSE"));
        } catch (Exception ignored) { }
        close(null);
    }

    void close(String reason) {
        if (closed) return;
        closed = true;
        if (beat != null) beat.shutdownNow();
        try { if (socket != null) socket.close(); } catch (IOException ignored) { }
        events.onClosed(reason);
    }

    // ---------- intern ----------

    private synchronized int next() { return requestId++; }

    private void mediaCommand(String type) throws Exception {
        if (transportId == null || mediaSessionId < 0) return;
        send(NS_MEDIA, transportId, new JSONObject().put("type", type).put("mediaSessionId", mediaSessionId).put("sessionId", sessionId).put("requestId", next()));
    }

    private void sendLoad() throws Exception {
        JSONObject l = pendingLoad;
        if (l == null || transportId == null) return;
        pendingLoad = null;
        l.put("requestId", next()).put("sessionId", sessionId);
        send(NS_MEDIA, transportId, l);
    }

    private void readLoop() {
        try {
            DataInputStream in = new DataInputStream(socket.getInputStream());
            while (!closed) {
                int len = in.readInt();
                if (len < 0 || len > 1 << 20) throw new IOException("Ungültige Nachricht");
                byte[] buf = new byte[len];
                in.readFully(buf);
                lastRx = System.currentTimeMillis();
                String[] m = decode(buf); // namespace, payload
                if (m == null || m[1] == null) continue;
                handle(m[0], new JSONObject(m[1]));
            }
        } catch (Exception e) {
            close(closed ? null : "Verbindung getrennt");
        }
    }

    private void handle(String ns, JSONObject j) throws Exception {
        String type = j.optString("type");
        if (NS_HB.equals(ns)) {
            if ("PING".equals(type)) send(NS_HB, "receiver-0", new JSONObject().put("type", "PONG"));
        } else if (NS_RECV.equals(ns) && "RECEIVER_STATUS".equals(type)) {
            JSONObject st = j.optJSONObject("status");
            JSONArray apps = st == null ? null : st.optJSONArray("applications");
            String tid = null, sid = null;
            if (apps != null) for (int i = 0; i < apps.length(); i++) {
                JSONObject a = apps.optJSONObject(i);
                if (a != null && APP_ID.equals(a.optString("appId"))) { tid = a.optString("transportId", null); sid = a.optString("sessionId", null); }
            }
            if (tid != null && !tid.equals(transportId)) {
                synchronized (this) {
                    transportId = tid;
                    sessionId = sid;
                    launching = false;
                    send(NS_CONN, transportId, new JSONObject().put("type", "CONNECT"));
                    events.onState("ready");
                    sendLoad();
                }
            } else if (tid == null && transportId != null) {
                close("Wiedergabe am Gerät beendet");
            }
        } else if (NS_MEDIA.equals(ns) && "MEDIA_STATUS".equals(type)) {
            JSONArray s = j.optJSONArray("status");
            if (s != null && s.length() > 0) {
                JSONObject first = s.optJSONObject(0);
                mediaSessionId = first.optLong("mediaSessionId", mediaSessionId);
                events.onState(first.optString("playerState", "IDLE"));
            }
        } else if (NS_CONN.equals(ns) && "CLOSE".equals(type)) {
            close("Gerät hat die Verbindung beendet");
        } else if ("LOAD_FAILED".equals(type) || "INVALID_REQUEST".equals(type)) {
            events.onState("IDLE");
        }
    }

    private void send(String ns, String dest, JSONObject payload) throws Exception {
        byte[] body = encode("sender-0", dest, ns, payload.toString());
        synchronized (writeLock) {
            if (out == null) throw new IOException("nicht verbunden");
            out.write(new byte[]{(byte) (body.length >>> 24), (byte) (body.length >>> 16), (byte) (body.length >>> 8), (byte) body.length});
            out.write(body);
            out.flush();
        }
    }

    // ---------- Protobuf (CastMessage) von Hand: 1 version=0, 2 source, 3 dest, 4 namespace, 5 type=0 (STRING), 6 payload_utf8 ----------

    static byte[] encode(String src, String dest, String ns, String payload) {
        ByteArrayOutputStream b = new ByteArrayOutputStream();
        b.write(0x08); b.write(0);
        str(b, 2, src); str(b, 3, dest); str(b, 4, ns);
        b.write(0x28); b.write(0);
        str(b, 6, payload);
        return b.toByteArray();
    }

    private static void str(ByteArrayOutputStream b, int field, String s) {
        byte[] d = s.getBytes(StandardCharsets.UTF_8);
        b.write((field << 3) | 2);
        long v = d.length;
        while (v > 0x7F) { b.write((int) ((v & 0x7F) | 0x80)); v >>>= 7; }
        b.write((int) v);
        b.write(d, 0, d.length);
    }

    /** @return {namespace, payload} oder null */
    static String[] decode(byte[] buf) {
        String ns = null, payload = null;
        int i = 0;
        while (i < buf.length) {
            int tag = buf[i++] & 0xFF;
            int field = tag >> 3, wire = tag & 7;
            if (wire == 0) { while (i < buf.length && (buf[i++] & 0x80) != 0) { } }
            else if (wire == 2) {
                long len = 0; int shift = 0;
                while (i < buf.length) { int c = buf[i++] & 0xFF; len |= (long) (c & 0x7F) << shift; if ((c & 0x80) == 0) break; shift += 7; }
                if (len < 0 || i + len > buf.length) return null;
                String v = new String(buf, i, (int) len, StandardCharsets.UTF_8);
                if (field == 4) ns = v; else if (field == 6) payload = v;
                i += (int) len;
            } else return null;
        }
        return new String[]{ns, payload};
    }
}
