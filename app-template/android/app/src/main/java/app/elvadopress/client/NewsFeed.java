package app.elvadopress.client;

import android.util.Xml;

import org.json.JSONArray;
import org.json.JSONObject;
import org.xmlpull.v1.XmlPullParser;

import java.io.StringReader;
import java.net.URI;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

/** Liest den RSS-Feed des Portals (rss.xml) und liefert dieselben Felder wie die News-API. */
final class NewsFeed {
    private NewsFeed() {}

    static JSONArray parseRss(String xml, String siteBase) throws Exception {
        XmlPullParser p = Xml.newPullParser();
        p.setInput(new StringReader(xml));
        JSONArray out = new JSONArray();
        JSONObject item = null;
        String siteHost = hostOf(siteBase);
        int event = p.getEventType();
        while (event != XmlPullParser.END_DOCUMENT) {
            if (event == XmlPullParser.START_TAG) {
                String name = p.getName();
                if ("item".equals(name)) {
                    item = new JSONObject();
                } else if (item != null) {
                    switch (name) {
                        case "title": item.put("title", clean(p.nextText())); break;
                        case "link": item.put("link", p.nextText().trim()); break;
                        case "description": item.put("excerpt", stripTags(p.nextText())); break;
                        case "content:encoded": item.put("body_html", p.nextText()); break;
                        case "category": if (!item.has("category")) item.put("category", clean(p.nextText())); break;
                        case "dc:creator":
                        case "author": item.put("author", clean(p.nextText())); break;
                        case "pubDate": item.put("published_at", isoDate(p.nextText())); break;
                        case "media:content":
                        case "media:thumbnail": {
                            String url = p.getAttributeValue(null, "url");
                            if (url != null && !url.isEmpty() && !item.has("image_url")) item.put("image_url", url);
                            break;
                        }
                        case "enclosure": {
                            String type = p.getAttributeValue(null, "type");
                            String url = p.getAttributeValue(null, "url");
                            if (url != null && type != null && type.startsWith("image/") && !item.has("image_url")) item.put("image_url", url);
                            break;
                        }
                        default: break;
                    }
                }
            } else if (event == XmlPullParser.END_TAG && "item".equals(p.getName()) && item != null) {
                String link = item.optString("link", "");
                String linkHost = hostOf(link);
                // Eigene Portal-Links (#news/...) oeffnen wir in der App; fremde Links bekommen den "Mehr erfahren"-Button
                if (!link.isEmpty() && !linkHost.isEmpty() && !sameSite(linkHost, siteHost)) item.put("external_url", link);
                if (!item.has("category")) item.put("category", "News");
                if (!item.has("excerpt") || item.optString("excerpt").isEmpty()) {
                    item.put("excerpt", stripTags(item.optString("body_html", "")));
                }
                item.put("image_mode", "thumbnail");
                if (!item.optString("title", "").isEmpty()) out.put(item);
                item = null;
            }
            event = p.next();
        }
        return out;
    }

    private static boolean sameSite(String a, String b) {
        a = a.replaceFirst("^www\\.", "");
        b = b.replaceFirst("^www\\.", "");
        return a.equalsIgnoreCase(b) || a.endsWith("." + b);
    }

    private static String hostOf(String url) {
        try {
            String h = new URI(url).getHost();
            return h == null ? "" : h;
        } catch (Exception e) {
            return "";
        }
    }

    private static String clean(String s) {
        return s == null ? "" : s.replaceAll("\\s+", " ").trim();
    }

    private static String stripTags(String s) {
        if (s == null) return "";
        String t = s.replaceAll("(?is)<(script|style).*?</\\1>", " ").replaceAll("<[^>]+>", " ");
        t = t.replace("&nbsp;", " ").replace("&amp;", "&").replace("&lt;", "<").replace("&gt;", ">")
                .replace("&quot;", "\"").replace("&#039;", "'");
        t = clean(t);
        return t.length() > 260 ? t.substring(0, 257) + "…" : t;
    }

    private static String isoDate(String raw) {
        try {
            Date d = new SimpleDateFormat("EEE, dd MMM yyyy HH:mm:ss Z", Locale.US).parse(raw.trim());
            return new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(d);
        } catch (Exception e) {
            return "";
        }
    }
}
