package app.elvadopress.client;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.DatagramPacket;
import java.net.DatagramSocket;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * DLNA/UPnP-Renderer ohne Google-Dienste: Suche per SSDP, Steuerung per SOAP (AVTransport, RenderingControl).
 * HTTP läuft über rohe Sockets im lokalen Netz (die App erlaubt sonst kein unverschlüsseltes HTTP).
 * Reines Java ohne Android-Klassen – testbar auf der JVM.
 */
final class DlnaClient {
    static final class Device {
        String name = "", location = "", base = "", avType = "", avControl = "", rcType = "", rcControl = "";
        String host = "";
        int port = 80;
    }

    private final Device d;

    DlnaClient(Device d) { this.d = d; }

    // ---------- Suche ----------

    /** Sendet M-SEARCH und sammelt Antworten für {@code ms} Millisekunden; {@code found} wird je neuem Gerät aufgerufen. */
    static void discover(int ms, java.util.function.Consumer<Device> found) {
        Set<String> seen = new HashSet<>();
        try (DatagramSocket s = new DatagramSocket()) {
            s.setSoTimeout(500);
            byte[] msg = ("M-SEARCH * HTTP/1.1\r\nHOST: 239.255.255.250:1900\r\nMAN: \"ssdp:discover\"\r\nMX: 2\r\n"
                    + "ST: urn:schemas-upnp-org:device:MediaRenderer:1\r\n\r\n").getBytes(StandardCharsets.UTF_8);
            InetAddress group = InetAddress.getByName("239.255.255.250");
            long end = System.currentTimeMillis() + ms;
            int sent = 0;
            byte[] buf = new byte[2048];
            while (System.currentTimeMillis() < end) {
                if (sent < 2 && System.currentTimeMillis() > end - ms + sent * 1000L) { s.send(new DatagramPacket(msg, msg.length, group, 1900)); sent++; }
                try {
                    DatagramPacket p = new DatagramPacket(buf, buf.length);
                    s.receive(p);
                    String r = new String(p.getData(), 0, p.getLength(), StandardCharsets.UTF_8);
                    String loc = header(r, "LOCATION");
                    if (loc.isEmpty() || !seen.add(loc)) continue;
                    Device dev = describe(loc);
                    if (dev != null) found.accept(dev);
                } catch (java.net.SocketTimeoutException ignored) { }
            }
        } catch (Exception ignored) { }
    }

    static String header(String response, String name) {
        Matcher m = Pattern.compile("(?im)^" + Pattern.quote(name) + ":\\s*(.+?)\\s*$").matcher(response);
        return m.find() ? m.group(1) : "";
    }

    /** Gerätebeschreibung laden; null, wenn es kein steuerbarer Renderer ist. */
    static Device describe(String location) {
        try {
            URI u = URI.create(location);
            String xml = new String(http("GET", u.getHost(), u.getPort() < 0 ? 80 : u.getPort(), pathOf(u), null, null, 4000).body, StandardCharsets.UTF_8);
            Device d = new Device();
            d.location = location;
            d.host = u.getHost();
            d.port = u.getPort() < 0 ? 80 : u.getPort();
            d.base = "http://" + d.host + ":" + d.port;
            Matcher bm = Pattern.compile("(?is)<URLBase>\\s*(.*?)\\s*</URLBase>").matcher(xml);
            if (bm.find() && bm.group(1).startsWith("http")) d.base = bm.group(1).replaceAll("/+$", "");
            Matcher nm = Pattern.compile("(?is)<friendlyName>\\s*(.*?)\\s*</friendlyName>").matcher(xml);
            if (nm.find()) d.name = unescape(nm.group(1));
            Matcher sm = Pattern.compile("(?is)<service>(.*?)</service>").matcher(xml);
            while (sm.find()) {
                String blk = sm.group(1);
                String type = tag(blk, "serviceType"), ctl = tag(blk, "controlURL");
                if (type.contains(":service:AVTransport:")) { d.avType = type; d.avControl = ctl; }
                else if (type.contains(":service:RenderingControl:")) { d.rcType = type; d.rcControl = ctl; }
            }
            if (d.avControl.isEmpty()) return null;
            if (d.name.isEmpty()) d.name = d.host;
            return d;
        } catch (Exception e) {
            return null;
        }
    }

    // ---------- Steuerung ----------

    void play(String url, String mime, String title) throws Exception {
        String didl = "<DIDL-Lite xmlns=\"urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:upnp=\"urn:schemas-upnp-org:metadata-1-0/upnp/\">"
                + "<item id=\"0\" parentID=\"-1\" restricted=\"1\"><dc:title>" + esc(title) + "</dc:title><upnp:class>object.item.audioItem.audioBroadcast</upnp:class>"
                + "<res protocolInfo=\"http-get:*:" + esc(mime) + ":*\">" + esc(url) + "</res></item></DIDL-Lite>";
        // Mancher Renderer will vor einem neuen Stream gestoppt werden
        try { soap(d.avType, d.avControl, "Stop", "<InstanceID>0</InstanceID>"); } catch (Exception ignored) { }
        soap(d.avType, d.avControl, "SetAVTransportURI", "<InstanceID>0</InstanceID><CurrentURI>" + esc(url) + "</CurrentURI><CurrentURIMetaData>" + esc(didl) + "</CurrentURIMetaData>");
        soap(d.avType, d.avControl, "Play", "<InstanceID>0</InstanceID><Speed>1</Speed>");
    }

    void resume() throws Exception { soap(d.avType, d.avControl, "Play", "<InstanceID>0</InstanceID><Speed>1</Speed>"); }
    void pause() throws Exception { soap(d.avType, d.avControl, "Pause", "<InstanceID>0</InstanceID>"); }
    void stop() throws Exception { soap(d.avType, d.avControl, "Stop", "<InstanceID>0</InstanceID>"); }

    void setVolume(int percent) throws Exception {
        if (d.rcControl.isEmpty()) return;
        soap(d.rcType, d.rcControl, "SetVolume", "<InstanceID>0</InstanceID><Channel>Master</Channel><DesiredVolume>" + Math.max(0, Math.min(100, percent)) + "</DesiredVolume>");
    }

    private void soap(String type, String control, String action, String args) throws Exception {
        String body = "<?xml version=\"1.0\" encoding=\"utf-8\"?><s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" s:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\"><s:Body>"
                + "<u:" + action + " xmlns:u=\"" + type + "\">" + args + "</u:" + action + "></s:Body></s:Envelope>";
        URI u = URI.create(control.startsWith("http") ? control : d.base + (control.startsWith("/") ? "" : "/") + control);
        int port = u.getPort() < 0 ? 80 : u.getPort();
        Resp r = http("POST", u.getHost(), port, pathOf(u), body.getBytes(StandardCharsets.UTF_8),
                new String[]{"CONTENT-TYPE: text/xml; charset=\"utf-8\"", "SOAPACTION: \"" + type + "#" + action + "\""}, 6000);
        if (r.status < 200 || r.status >= 300) throw new Exception("DLNA " + action + " -> HTTP " + r.status);
    }

    // ---------- HTTP über Sockets ----------

    static final class Resp { int status; byte[] body = new byte[0]; }

    static Resp http(String method, String host, int port, String path, byte[] body, String[] headers, int timeout) throws Exception {
        try (Socket s = new Socket()) {
            s.connect(new InetSocketAddress(host, port), timeout);
            s.setSoTimeout(timeout);
            StringBuilder h = new StringBuilder(method + " " + path + " HTTP/1.1\r\nHOST: " + host + ":" + port + "\r\nConnection: close\r\nUser-Agent: ElvadoPressApp/1.0 UPnP/1.0\r\n");
            if (headers != null) for (String x : headers) h.append(x).append("\r\n");
            if (body != null) h.append("CONTENT-LENGTH: ").append(body.length).append("\r\n");
            h.append("\r\n");
            OutputStream o = s.getOutputStream();
            o.write(h.toString().getBytes(StandardCharsets.UTF_8));
            if (body != null) o.write(body);
            o.flush();
            InputStream in = s.getInputStream();
            ByteArrayOutputStream all = new ByteArrayOutputStream();
            byte[] buf = new byte[4096];
            int n;
            while ((n = in.read(buf)) > 0 && all.size() < 2_000_000) all.write(buf, 0, n);
            byte[] raw = all.toByteArray();
            String head = new String(raw, 0, Math.min(raw.length, 4096), StandardCharsets.ISO_8859_1);
            int sep = head.indexOf("\r\n\r\n");
            Resp r = new Resp();
            Matcher m = Pattern.compile("^HTTP/\\d\\.\\d\\s+(\\d{3})").matcher(head);
            r.status = m.find() ? Integer.parseInt(m.group(1)) : 0;
            if (sep >= 0) {
                byte[] b = java.util.Arrays.copyOfRange(raw, sep + 4, raw.length);
                r.body = head.toLowerCase(Locale.ROOT).contains("transfer-encoding: chunked") ? dechunk(b) : b;
            }
            return r;
        }
    }

    private static byte[] dechunk(byte[] b) {
        ByteArrayOutputStream o = new ByteArrayOutputStream();
        int i = 0;
        while (i < b.length) {
            int e = i;
            while (e + 1 < b.length && !(b[e] == '\r' && b[e + 1] == '\n')) e++;
            if (e + 1 >= b.length) break;
            int len;
            try { len = Integer.parseInt(new String(b, i, e - i, StandardCharsets.ISO_8859_1).trim().split(";")[0], 16); } catch (Exception ex) { break; }
            if (len == 0) break;
            i = e + 2;
            o.write(b, i, Math.min(len, b.length - i));
            i += len + 2;
        }
        return o.toByteArray();
    }

    // ---------- Hilfen ----------

    private static String pathOf(URI u) {
        String p = u.getRawPath() == null || u.getRawPath().isEmpty() ? "/" : u.getRawPath();
        return u.getRawQuery() == null ? p : p + "?" + u.getRawQuery();
    }

    private static String tag(String xml, String name) {
        Matcher m = Pattern.compile("(?is)<" + name + ">\\s*(.*?)\\s*</" + name + ">").matcher(xml);
        return m.find() ? unescape(m.group(1)) : "";
    }

    static String esc(String s) {
        return (s == null ? "" : s).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("\"", "&quot;").replace("'", "&apos;");
    }

    static String unescape(String s) {
        return s.replace("&lt;", "<").replace("&gt;", ">").replace("&quot;", "\"").replace("&apos;", "'").replace("&amp;", "&");
    }
}
