package app.elvadopress.client;

import android.Manifest;
import android.app.Activity;
import android.app.Dialog;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.ColorDrawable;
import android.graphics.drawable.GradientDrawable;
import android.media.MediaRecorder;
import android.os.Build;
import android.os.Handler;
import android.text.InputType;
import android.text.SpannableStringBuilder;
import android.text.Spanned;
import android.text.style.StyleSpan;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.inputmethod.EditorInfo;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.DataOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * KI-Assistent als eigene Ansicht im Inhaltsbereich der App (kein Overlay): Mini-Player und untere
 * Navigation bleiben sichtbar und bedienbar. Spricht dieselbe CMS-API wie das Portal
 * (assistant_chat / assistant_send / assistant_voice) und nutzt Sender und Favoriten der App.
 */
final class AssistantPanel {
    interface Labels { String of(String stationId); }

    /** Startet einen Treffer aus der Karte "Aus dem Radioverzeichnis" im Player der App (items = alle Treffer der Karte, index = gewaehlter). */
    interface StationPlayer { void play(List<JSONObject> items, int index); }

    private static final int BG = Color.rgb(7, 10, 28);
    private static final int CARD = Color.rgb(15, 21, 56);
    private static final int CARD_2 = Color.rgb(21, 27, 66);
    private static final int LINE = Color.rgb(43, 51, 112);
    private static final int TEXT = Color.rgb(238, 240, 250);
    private static final int MUTED = Color.rgb(158, 166, 199);
    private static final int PRIMARY = Color.rgb(181, 124, 255);
    private static final int CYAN = Color.rgb(0, 242, 234);
    static final int REQ_MIC = 77;

    private final Activity act;
    private final String base;
    private final ExecutorService io;
    private final Handler main;
    private final ScrollView scroll;
    private final LinearLayout content;
    private final LinearLayout barHost;
    private final float density;

    private String name = "Radio-Assistent";
    private String greeting = "Hi! Ich bin der Assistent. Frag mich, was gerade läuft, nach dem Sendeplan oder unseren Sendern – oder schick dem Studio eine Nachricht.";
    private boolean allowMail = true;
    private boolean allowVoice = true;

    private String stationId = "";
    private List<String> stations = new ArrayList<>();
    private Set<String> favorites = new java.util.HashSet<>();
    private Labels labels = id -> id;

    private LinearLayout page;
    private LinearLayout messages;
    private EditText input;
    private Button sendBtn;
    private boolean busy = false;
    private final List<String[]> history = new ArrayList<>();

    // Sprachaufnahme
    private MediaRecorder recorder;
    private File recordFile;
    private long recordStart;
    private boolean recording;

    private StationPlayer stationPlayer;

    void setStationPlayer(StationPlayer p) { this.stationPlayer = p; }

    AssistantPanel(Activity act, String base, ExecutorService io, Handler main, ScrollView scroll, LinearLayout content, LinearLayout barHost) {
        this.act = act;
        this.base = base;
        this.io = io;
        this.main = main;
        this.scroll = scroll;
        this.content = content;
        this.barHost = barHost;
        this.density = act.getResources().getDisplayMetrics().density;
    }

    void configure(String name, String greeting, boolean allowMail, boolean allowVoice) {
        if (name != null && !name.trim().isEmpty()) this.name = name.trim();
        if (greeting != null && !greeting.trim().isEmpty()) this.greeting = greeting.trim();
        this.allowMail = allowMail;
        this.allowVoice = allowVoice;
    }

    // ------------------------------------------------------------------ Anzeige

    void show(String stationId, List<String> stations, Set<String> favorites, Labels labels) {
        this.stationId = stationId == null ? "" : stationId;
        this.stations = stations == null ? new ArrayList<>() : stations;
        this.favorites = favorites == null ? new java.util.HashSet<>() : favorites;
        this.labels = labels == null ? id -> id : labels;
        if (page == null) buildPage();
        content.removeAllViews();
        if (page.getParent() instanceof ViewGroup) ((ViewGroup) page.getParent()).removeView(page);
        content.addView(page, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        buildBar();
        barHost.setVisibility(View.VISIBLE);
        scrollToEnd();
    }

    void hide() {
        barHost.setVisibility(View.GONE);
        stopRecordingSilently();
    }

    private void buildPage() {
        page = new LinearLayout(act);
        page.setOrientation(LinearLayout.VERTICAL);

        // Kopf: Badge + Name + Hinweis + Neustart
        LinearLayout head = new LinearLayout(act);
        head.setOrientation(LinearLayout.HORIZONTAL);
        head.setGravity(Gravity.CENTER_VERTICAL);
        head.setPadding(dp(14), dp(12), dp(14), dp(12));
        head.setBackground(round(CARD, 18, LINE, 1));
        TextView badge = tv("KI", 13, Color.rgb(18, 10, 39), true);
        badge.setGravity(Gravity.CENTER);
        badge.setBackground(gradient());
        head.addView(badge, lp(dp(42), dp(42), 0, 0, dp(12), 0));
        LinearLayout titles = new LinearLayout(act);
        titles.setOrientation(LinearLayout.VERTICAL);
        titles.addView(tv(name, 16, TEXT, true));
        TextView sub = tv("KI-Antworten können Fehler enthalten", 11, MUTED, false);
        titles.addView(sub);
        head.addView(titles, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        TextView reset = tv("Neu", 12, CYAN, true);
        reset.setPadding(dp(10), dp(8), dp(6), dp(8));
        reset.setOnClickListener(v -> resetChat());
        head.addView(reset);
        page.addView(head, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(10)));

        // Schnellfragen
        HorizontalScrollView chipScroll = new HorizontalScrollView(act);
        chipScroll.setHorizontalScrollBarEnabled(false);
        LinearLayout chips = new LinearLayout(act);
        chips.setOrientation(LinearLayout.HORIZONTAL);
        addChip(chips, "Was läuft gerade?", () -> ask("Was läuft gerade?"));
        addChip(chips, "Sendeplan heute", () -> ask("Wie sieht der Sendeplan heute aus?"));
        addChip(chips, "Nächste Sendungen", () -> ask("Welche Sendungen kommen als Nächstes?"));
        addChip(chips, "Podcast", () -> ask("Was gibt es Neues im Podcast?"));
        addChip(chips, "News & Events", () -> ask("Welche News und Events gibt es aktuell?"));
        addChip(chips, "Sender empfehlen", () -> ask("Welchen Sender empfiehlst du mir?"));
        if (allowMail) addChip(chips, "Nachricht ans Studio", () -> openMailDialog(stationId));
        if (allowVoice) addChip(chips, "Sprachnachricht", () -> openVoiceDialog(stationId));
        chipScroll.addView(chips);
        page.addView(chipScroll, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(12)));

        messages = new LinearLayout(act);
        messages.setOrientation(LinearLayout.VERTICAL);
        page.addView(messages, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        addBubble(greeting, false);
    }

    private void resetChat() {
        history.clear();
        busy = false;
        if (messages != null) {
            messages.removeAllViews();
            addBubble(greeting, false);
        }
    }

    private void buildBar() {
        barHost.removeAllViews();
        LinearLayout row = new LinearLayout(act);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.setPadding(dp(12), dp(8), dp(12), dp(8));
        row.setBackgroundColor(Color.rgb(6, 9, 25));

        input = new EditText(act);
        input.setHint("Frag mich etwas …");
        input.setHintTextColor(Color.rgb(120, 124, 160));
        input.setTextColor(TEXT);
        input.setTextSize(14);
        input.setMaxLines(3);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
        input.setImeOptions(EditorInfo.IME_ACTION_SEND);
        input.setPadding(dp(16), dp(10), dp(16), dp(10));
        input.setBackground(round(Color.rgb(22, 24, 54), 24, Color.rgb(61, 61, 104), 1));
        input.setOnEditorActionListener((v, actionId, e) -> {
            if (actionId == EditorInfo.IME_ACTION_SEND) { submitInput(); return true; }
            return false;
        });
        row.addView(input, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        sendBtn = new Button(act);
        sendBtn.setText("➤");
        sendBtn.setTextColor(Color.rgb(18, 10, 39));
        sendBtn.setTextSize(16);
        sendBtn.setAllCaps(false);
        sendBtn.setPadding(0, 0, 0, 0);
        sendBtn.setBackground(gradient());
        sendBtn.setOnClickListener(v -> submitInput());
        row.addView(sendBtn, lp(dp(46), dp(46), dp(8), 0, 0, 0));
        barHost.addView(row, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
    }

    // ------------------------------------------------------------------ Chat

    private void submitInput() {
        if (input == null) return;
        String q = input.getText().toString().trim();
        if (q.isEmpty()) return;
        input.setText("");
        ask(q);
    }

    void ask(String question) {
        if (busy || question == null || question.trim().isEmpty()) return;
        busy = true;
        addBubble(question, true);
        history.add(new String[]{"user", question});
        final TextView typing = addBubble("…", false);
        final String station = stationId;
        io.execute(() -> {
            String reply;
            JSONArray actions = null;
            JSONArray cards = null;
            try {
                JSONArray msgs = new JSONArray();
                int from = Math.max(0, history.size() - 8);
                for (int i = from; i < history.size(); i++) {
                    JSONObject m = new JSONObject();
                    m.put("role", history.get(i)[0]);
                    m.put("content", history.get(i)[1]);
                    msgs.put(m);
                }
                JSONObject body = new JSONObject();
                body.put("messages", msgs);
                body.put("station", station);
                JSONArray favs = new JSONArray();
                for (String f : favorites) favs.put(f);
                body.put("favorites", favs);
                String[] res = postJson(base + "/cms/api.php?action=assistant_chat", body.toString());
                JSONObject d = new JSONObject(res[1]);
                if ("ok".equals(d.optString("status")) && !d.optString("reply").trim().isEmpty()) {
                    reply = d.optString("reply").trim();
                    actions = d.optJSONArray("actions");
                    cards = d.optJSONArray("cards");
                } else {
                    reply = d.optString("message", "Ich konnte gerade nicht antworten. Bitte versuch es gleich noch einmal.");
                }
            } catch (IOException e) {
                reply = "Ich erreiche gerade keine Verbindung. Sendeplan, News und Podcast findest du auch offline mit dem zuletzt geladenen Stand in der App.";
            } catch (Exception e) {
                reply = "Das hat leider nicht geklappt. Bitte versuch es gleich noch einmal.";
            }
            final String text = reply;
            final JSONArray act2 = actions;
            final JSONArray cards2 = cards;
            main.post(() -> {
                busy = false;
                messages.removeView(typing.getParent() instanceof View ? (View) typing.getParent() : typing);
                history.add(new String[]{"assistant", text});
                addBubble(text, false);
                if (cards2 != null) for (int i = 0; i < cards2.length(); i++) addCard(cards2.optJSONObject(i));
                if (act2 != null) for (int i = 0; i < act2.length(); i++) addActionButton(act2.optJSONObject(i));
                scrollToEnd();
            });
        });
    }

    private void addCard(JSONObject c) {
        if (c == null) return;
        String type = c.optString("type");
        if ("stations".equals(type)) {
            addStationsCard(c);
            return;
        }
        String line = null;
        if ("nowplaying".equals(type)) {
            line = "♪  " + c.optString("label") + " · " + c.optString("text");
        } else if ("schedule".equals(type)) {
            StringBuilder b = new StringBuilder();
            JSONObject now = c.optJSONObject("now");
            if (now != null) b.append("Jetzt: ").append(now.optString("name")).append(" (").append(hour(now.optInt("start"))).append("–").append(hour(now.optInt("end"))).append(")");
            JSONArray next = c.optJSONArray("next");
            if (next != null) for (int i = 0; i < next.length() && i < 3; i++) {
                JSONObject n = next.optJSONObject(i);
                if (n == null) continue;
                if (b.length() > 0) b.append("\n");
                b.append("Danach: ").append(n.optString("name")).append(" (").append(hour(n.optInt("start"))).append("–").append(hour(n.optInt("end"))).append(")");
            }
            if (b.length() > 0) line = c.optString("label") + "\n" + b;
        }
        if (line == null) return;
        TextView t = tv(line, 12, MUTED, false);
        t.setPadding(dp(12), dp(8), dp(12), dp(8));
        t.setBackground(round(CARD_2, 12, LINE, 1));
        messages.addView(t, lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, dp(4), 0, 0, dp(8)));
    }

    private void addStationsCard(JSONObject c) {
        JSONArray arr = c.optJSONArray("items");
        if (arr == null || stationPlayer == null) return;
        final List<JSONObject> items = new ArrayList<>();
        for (int i = 0; i < arr.length(); i++) {
            JSONObject o = arr.optJSONObject(i);
            if (o != null && !o.optString("id").isEmpty()) items.add(o);
        }
        if (items.isEmpty()) return;
        LinearLayout card = new LinearLayout(act);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(12), dp(10), dp(12), dp(6));
        card.setBackground(round(CARD_2, 12, LINE, 1));
        card.addView(tv("Aus dem Radioverzeichnis", 12, PRIMARY, true));
        for (int i = 0; i < items.size(); i++) {
            final int idx = i;
            JSONObject o = items.get(i);
            LinearLayout row = new LinearLayout(act);
            row.setOrientation(LinearLayout.HORIZONTAL);
            row.setGravity(Gravity.CENTER_VERTICAL);
            row.setPadding(0, dp(8), 0, dp(4));
            LinearLayout col = new LinearLayout(act);
            col.setOrientation(LinearLayout.VERTICAL);
            TextView name = tv(o.optString("name", o.optString("id")), 13, TEXT, true);
            name.setSingleLine(true);
            name.setEllipsize(android.text.TextUtils.TruncateAt.END);
            col.addView(name);
            List<String> bits = new ArrayList<>();
            JSONArray g = o.optJSONArray("genres");
            if (g != null) for (int k = 0; k < g.length() && k < 2; k++) if (!g.optString(k).isEmpty()) bits.add(g.optString(k));
            if (!o.optString("country").isEmpty()) bits.add(o.optString("country"));
            bits.add("world".equals(o.optString("source")) ? "Fremd-Stream" : "laut.fm");
            TextView sub = tv(android.text.TextUtils.join(" · ", bits), 11, MUTED, false);
            sub.setSingleLine(true);
            sub.setEllipsize(android.text.TextUtils.TruncateAt.END);
            col.addView(sub);
            row.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            TextView play = tv("▶ Hören", 12, Color.rgb(18, 10, 39), true);
            play.setBackground(round(PRIMARY, 16, 0, 0));
            play.setPadding(dp(12), dp(8), dp(12), dp(8));
            play.setOnClickListener(v -> stationPlayer.play(items, idx));
            row.addView(play, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            card.addView(row);
        }
        messages.addView(card, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, dp(4), 0, 0, dp(8)));
    }

    private String hour(int h) {
        return String.format(Locale.GERMANY, "%02d:00", h == 0 ? 24 : h);
    }

    private void addActionButton(JSONObject a) {
        if (a == null) return;
        final String station = a.optString("station", stationId);
        if ("studiomail".equals(a.optString("type")) && allowMail) {
            addChipTo(messages, "✉  Nachricht ans Studio schreiben", () -> openMailDialog(station));
        } else if ("voicemail".equals(a.optString("type")) && allowVoice) {
            addChipTo(messages, "🎙  Sprachnachricht aufnehmen", () -> openVoiceDialog(station));
        }
    }

    private TextView addBubble(String text, boolean user) {
        LinearLayout wrap = new LinearLayout(act);
        wrap.setGravity(user ? Gravity.END : Gravity.START);
        TextView t = tv("", 14, user ? Color.rgb(18, 10, 39) : TEXT, false);
        t.setText(md(text));
        t.setLineSpacing(0, 1.18f);
        t.setPadding(dp(14), dp(10), dp(14), dp(10));
        t.setBackground(user ? gradient() : round(CARD, 16, LINE, 1));
        t.setMaxWidth((int) (act.getResources().getDisplayMetrics().widthPixels * 0.84f));
        t.setTextIsSelectable(true);
        wrap.addView(t, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        messages.addView(wrap, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        scrollToEnd();
        return t;
    }

    private CharSequence md(String s) {
        String t = s == null ? "" : s.replace("\r", "").replaceAll("(?m)^\\s*[-*]\\s+", "• ").replaceAll("(?m)^#+\\s*", "");
        SpannableStringBuilder sb = new SpannableStringBuilder();
        Matcher m = Pattern.compile("\\*\\*(.+?)\\*\\*").matcher(t);
        int last = 0;
        while (m.find()) {
            sb.append(t, last, m.start());
            int st = sb.length();
            sb.append(m.group(1));
            sb.setSpan(new StyleSpan(Typeface.BOLD), st, sb.length(), Spanned.SPAN_EXCLUSIVE_EXCLUSIVE);
            last = m.end();
        }
        sb.append(t, last, t.length());
        return sb;
    }

    private void scrollToEnd() {
        scroll.postDelayed(() -> scroll.fullScroll(View.FOCUS_DOWN), 60);
    }

    // ------------------------------------------------------------------ Nachricht ans Studio

    private List<String> targetIds() {
        List<String> ids = new ArrayList<>();
        ids.add("netzwerk");
        ids.add("podcast");
        for (String s : stations) if (!ids.contains(s)) ids.add(s);
        return ids;
    }

    private String targetLabel(String id) {
        if ("netzwerk".equals(id)) return "Alle Sender";
        if ("podcast".equals(id)) return "Podcast";
        return labels.of(id);
    }

    private Spinner stationSpinner(String preselect) {
        List<String> ids = targetIds();
        List<String> names = new ArrayList<>();
        for (String id : ids) names.add(targetLabel(id));
        ArrayAdapter<String> ad = new ArrayAdapter<String>(act, android.R.layout.simple_spinner_item, names) {
            @Override public View getView(int p, View v, ViewGroup parent) {
                TextView t = (TextView) super.getView(p, v, parent);
                t.setTextColor(TEXT);
                return t;
            }
            @Override public View getDropDownView(int p, View v, ViewGroup parent) {
                TextView t = (TextView) super.getDropDownView(p, v, parent);
                t.setTextColor(TEXT);
                t.setBackgroundColor(CARD_2);
                t.setPadding(dp(14), dp(12), dp(14), dp(12));
                return t;
            }
        };
        ad.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        Spinner sp = new Spinner(act);
        sp.setAdapter(ad);
        sp.setBackground(round(Color.rgb(22, 24, 54), 12, Color.rgb(61, 61, 104), 1));
        int idx = ids.indexOf(preselect);
        sp.setSelection(idx >= 0 ? idx : Math.max(0, ids.indexOf(stationId)));
        sp.setTag(ids);
        return sp;
    }

    @SuppressWarnings("unchecked")
    private String selectedTarget(Spinner sp) {
        List<String> ids = (List<String>) sp.getTag();
        int p = sp.getSelectedItemPosition();
        return p >= 0 && p < ids.size() ? ids.get(p) : "netzwerk";
    }

    private Dialog baseDialog(String title, String subtitle, LinearLayout body) {
        Dialog d = new Dialog(act);
        d.requestWindowFeature(Window.FEATURE_NO_TITLE);
        LinearLayout panel = new LinearLayout(act);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setPadding(dp(18), dp(16), dp(18), dp(16));
        panel.setBackground(round(BG, 20, LINE, 1));
        panel.addView(tv(title, 17, TEXT, true));
        TextView s = tv(subtitle, 12, MUTED, false);
        s.setPadding(0, dp(3), 0, dp(12));
        panel.addView(s);
        panel.addView(body);
        ScrollView sv = new ScrollView(act);
        sv.addView(panel);
        d.setContentView(sv);
        if (d.getWindow() != null) {
            d.getWindow().setBackgroundDrawable(new ColorDrawable(Color.TRANSPARENT));
            d.getWindow().setLayout((int) (act.getResources().getDisplayMetrics().widthPixels * 0.94f), ViewGroup.LayoutParams.WRAP_CONTENT);
            d.getWindow().setSoftInputMode(android.view.WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);
        }
        return d;
    }

    private EditText field(String hint, boolean multi) {
        EditText e = new EditText(act);
        e.setHint(hint);
        e.setHintTextColor(Color.rgb(120, 124, 160));
        e.setTextColor(TEXT);
        e.setTextSize(14);
        e.setPadding(dp(12), dp(10), dp(12), dp(10));
        e.setBackground(round(Color.rgb(22, 24, 54), 12, Color.rgb(61, 61, 104), 1));
        if (multi) {
            e.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES);
            e.setMinLines(3);
            e.setGravity(Gravity.TOP);
        }
        return e;
    }

    private void openMailDialog(String preselect) {
        LinearLayout body = new LinearLayout(act);
        body.setOrientation(LinearLayout.VERTICAL);
        Spinner target = stationSpinner(preselect);
        body.addView(target, lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(46), 0, 0, 0, dp(8)));
        EditText nameF = field("Dein Name", false);
        nameF.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_WORDS);
        body.addView(nameF, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        EditText mailF = field("E-Mail für Rückfragen (optional)", false);
        mailF.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS);
        body.addView(mailF, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        EditText msgF = field("Deine Nachricht, dein Gruß oder Musikwunsch …", true);
        body.addView(msgF, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        TextView status = tv("", 12, Color.rgb(255, 150, 150), false);
        body.addView(status);
        final Dialog d = baseDialog("Nachricht ans Studio", "Wird direkt an das Studio der Website gesendet.", body);
        LinearLayout buttons = new LinearLayout(act);
        buttons.setGravity(Gravity.END);
        Button cancel = flatButton("Abbrechen", CARD_2, TEXT);
        Button send = flatButton("Senden", PRIMARY, Color.rgb(18, 10, 39));
        buttons.addView(cancel, lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(44), 0, 0, dp(8), 0));
        buttons.addView(send, lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(44), 0, 0, 0, 0));
        body.addView(buttons, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, dp(8), 0, 0));
        cancel.setOnClickListener(v -> d.dismiss());
        send.setOnClickListener(v -> {
            String n = nameF.getText().toString().trim();
            String m = msgF.getText().toString().trim();
            if (n.isEmpty()) { status.setText("Bitte einen Namen angeben."); return; }
            if (m.length() < 3) { status.setText("Bitte eine Nachricht eingeben."); return; }
            send.setEnabled(false);
            status.setTextColor(MUTED);
            status.setText("Wird gesendet …");
            final String tgt = selectedTarget(target);
            io.execute(() -> {
                String error = null;
                try {
                    JSONObject b = new JSONObject();
                    b.put("name", n);
                    b.put("email", mailF.getText().toString().trim());
                    b.put("station", tgt);
                    b.put("message", m);
                    b.put("hp", "");
                    String[] res = postJson(base + "/cms/api.php?action=assistant_send", b.toString());
                    JSONObject r = new JSONObject(res[1]);
                    if (!"ok".equals(r.optString("status"))) error = r.optString("message", "Senden fehlgeschlagen.");
                } catch (IOException e) {
                    error = "Keine Verbindung – bitte später erneut versuchen.";
                } catch (Exception e) {
                    error = "Senden fehlgeschlagen – bitte später erneut versuchen.";
                }
                final String err = error;
                main.post(() -> {
                    if (err != null) {
                        status.setTextColor(Color.rgb(255, 150, 150));
                        status.setText(err);
                        send.setEnabled(true);
                    } else {
                        d.dismiss();
                        addBubble("Deine Nachricht an " + targetLabel(tgt) + " ist im Studio angekommen – danke dir!", false);
                    }
                });
            });
        });
        d.show();
    }

    // ------------------------------------------------------------------ Sprachnachricht

    private void openVoiceDialog(String preselect) {
        LinearLayout body = new LinearLayout(act);
        body.setOrientation(LinearLayout.VERTICAL);
        Spinner target = stationSpinner(preselect);
        body.addView(target, lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(46), 0, 0, 0, dp(8)));
        EditText nameF = field("Dein Name (optional)", false);
        body.addView(nameF, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        EditText noteF = field("Kurze Notiz (optional)", false);
        body.addView(noteF, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(10)));
        final TextView timer = tv("Bereit – bis zu 60 Sekunden", 13, MUTED, false);
        timer.setGravity(Gravity.CENTER);
        body.addView(timer, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        final Button rec = flatButton("🎙  Aufnahme starten", PRIMARY, Color.rgb(18, 10, 39));
        body.addView(rec, lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(48), 0, 0, 0, dp(8)));
        final TextView status = tv("", 12, MUTED, false);
        body.addView(status);
        final Dialog d = baseDialog("Sprachnachricht", "Wird als Sprachnachricht an das Studio der Website gesendet.", body);
        LinearLayout buttons = new LinearLayout(act);
        buttons.setGravity(Gravity.END);
        Button cancel = flatButton("Schließen", CARD_2, TEXT);
        final Button send = flatButton("Senden", CYAN, Color.rgb(5, 20, 30));
        send.setEnabled(false);
        send.setAlpha(0.45f);
        buttons.addView(cancel, lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(44), 0, 0, dp(8), 0));
        buttons.addView(send, lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(44), 0, 0, 0, 0));
        body.addView(buttons, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, dp(8), 0, 0));

        final Runnable tick = new Runnable() {
            @Override public void run() {
                if (!recording) return;
                long s = (System.currentTimeMillis() - recordStart) / 1000;
                timer.setText(String.format(Locale.GERMANY, "● Aufnahme läuft  %d:%02d", s / 60, s % 60));
                main.postDelayed(this, 400);
            }
        };
        final Runnable finish = () -> {
            long sec = (System.currentTimeMillis() - recordStart) / 1000;
            boolean ok = stopRecorder();
            rec.setText("🎙  Neu aufnehmen");
            if (ok && recordFile != null && recordFile.length() > 800) {
                timer.setText(String.format(Locale.GERMANY, "Aufnahme fertig  %d:%02d", sec / 60, sec % 60));
                send.setEnabled(true);
                send.setAlpha(1f);
            } else {
                timer.setText("Die Aufnahme war zu kurz – bitte noch einmal versuchen.");
            }
        };
        rec.setOnClickListener(v -> {
            if (recording) { finish.run(); return; }
            if (Build.VERSION.SDK_INT >= 23 && act.checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
                act.requestPermissions(new String[]{Manifest.permission.RECORD_AUDIO}, REQ_MIC);
                status.setText("Bitte das Mikrofon erlauben und dann erneut auf „Aufnahme starten“ tippen.");
                return;
            }
            status.setText("");
            send.setEnabled(false);
            send.setAlpha(0.45f);
            if (startRecorder(() -> main.post(finish))) {
                rec.setText("■  Aufnahme beenden");
                main.post(tick);
            } else {
                status.setText("Die Aufnahme konnte nicht gestartet werden.");
            }
        });
        cancel.setOnClickListener(v -> d.dismiss());
        d.setOnDismissListener(x -> stopRecordingSilently());
        send.setOnClickListener(v -> {
            if (recordFile == null || !recordFile.isFile()) return;
            send.setEnabled(false);
            status.setTextColor(MUTED);
            status.setText("Wird gesendet …");
            final String tgt = selectedTarget(target);
            final File file = recordFile;
            final long dur = Math.max(1, (System.currentTimeMillis() - recordStart) / 1000);
            io.execute(() -> {
                String error = null;
                try {
                    String res = postVoice(file, tgt, nameF.getText().toString().trim(), noteF.getText().toString().trim(), dur);
                    JSONObject r = new JSONObject(res);
                    if (!"ok".equals(r.optString("status"))) error = r.optString("message", "Senden fehlgeschlagen.");
                } catch (IOException e) {
                    error = "Keine Verbindung – bitte später erneut versuchen.";
                } catch (Exception e) {
                    error = "Senden fehlgeschlagen – bitte später erneut versuchen.";
                }
                final String err = error;
                main.post(() -> {
                    if (err != null) {
                        status.setTextColor(Color.rgb(255, 150, 150));
                        status.setText(err);
                        send.setEnabled(true);
                    } else {
                        d.dismiss();
                        //noinspection ResultOfMethodCallIgnored
                        file.delete();
                        addBubble("Deine Sprachnachricht für " + targetLabel(tgt) + " ist angekommen – danke dir!", false);
                    }
                });
            });
        });
        d.show();
    }

    private boolean startRecorder(Runnable onMax) {
        try {
            recordFile = new File(act.getCacheDir(), "voice-" + System.currentTimeMillis() + ".m4a");
            recorder = Build.VERSION.SDK_INT >= 31 ? new MediaRecorder(act) : new MediaRecorder();
            recorder.setAudioSource(MediaRecorder.AudioSource.MIC);
            recorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4);
            recorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC);
            recorder.setAudioEncodingBitRate(64000);
            recorder.setAudioSamplingRate(44100);
            recorder.setMaxDuration(60000);
            recorder.setOutputFile(recordFile.getAbsolutePath());
            recorder.setOnInfoListener((mr, what, extra) -> {
                if (what == MediaRecorder.MEDIA_RECORDER_INFO_MAX_DURATION_REACHED) onMax.run();
            });
            recorder.prepare();
            recorder.start();
            recordStart = System.currentTimeMillis();
            recording = true;
            return true;
        } catch (Exception e) {
            stopRecordingSilently();
            return false;
        }
    }

    private boolean stopRecorder() {
        boolean ok = true;
        if (recorder != null) {
            try { recorder.stop(); } catch (Exception e) { ok = false; }
            try { recorder.release(); } catch (Exception ignored) { }
            recorder = null;
        }
        recording = false;
        return ok;
    }

    private void stopRecordingSilently() {
        stopRecorder();
    }

    // ------------------------------------------------------------------ Netzwerk

    private String[] postJson(String address, String json) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(address).openConnection();
        c.setConnectTimeout(9000);
        c.setReadTimeout(45000);
        c.setRequestMethod("POST");
        c.setDoOutput(true);
        c.setRequestProperty("Content-Type", "application/json; charset=utf-8");
        c.setRequestProperty("Accept", "application/json");
        c.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try {
            c.getOutputStream().write(json.getBytes(StandardCharsets.UTF_8));
            int code = c.getResponseCode();
            InputStream in = code >= 400 ? c.getErrorStream() : c.getInputStream();
            String body = in == null ? "{}" : readAll(in);
            return new String[]{String.valueOf(code), body.isEmpty() ? "{}" : body};
        } finally {
            c.disconnect();
        }
    }

    private String postVoice(File file, String station, String name, String note, long duration) throws Exception {
        String boundary = "----rrw" + System.currentTimeMillis();
        HttpURLConnection c = (HttpURLConnection) new URL(base + "/cms/api.php?action=assistant_voice").openConnection();
        c.setConnectTimeout(9000);
        c.setReadTimeout(60000);
        c.setRequestMethod("POST");
        c.setDoOutput(true);
        c.setChunkedStreamingMode(16384);
        c.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
        c.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try {
            DataOutputStream out = new DataOutputStream(c.getOutputStream());
            String[][] fields = {{"station", station}, {"name", name}, {"note", note}, {"email", ""}, {"duration", String.valueOf(duration)}, {"hp", ""}};
            for (String[] f : fields) {
                out.writeBytes("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + f[0] + "\"\r\n\r\n");
                out.write(f[1].getBytes(StandardCharsets.UTF_8));
                out.writeBytes("\r\n");
            }
            out.writeBytes("--" + boundary + "\r\nContent-Disposition: form-data; name=\"audio\"; filename=\"aufnahme.m4a\"\r\nContent-Type: audio/mp4\r\n\r\n");
            try (FileInputStream fin = new FileInputStream(file)) {
                byte[] buf = new byte[8192];
                int n;
                while ((n = fin.read(buf)) > 0) out.write(buf, 0, n);
            }
            out.writeBytes("\r\n--" + boundary + "--\r\n");
            out.flush();
            int code = c.getResponseCode();
            InputStream in = code >= 400 ? c.getErrorStream() : c.getInputStream();
            String body = in == null ? "{}" : readAll(in);
            return body.isEmpty() ? "{}" : body;
        } finally {
            c.disconnect();
        }
    }

    private static String readAll(InputStream in) throws IOException {
        try (BufferedReader r = new BufferedReader(new InputStreamReader(in, StandardCharsets.UTF_8))) {
            StringBuilder b = new StringBuilder();
            String line;
            while ((line = r.readLine()) != null) b.append(line);
            return b.toString();
        }
    }

    // ------------------------------------------------------------------ UI-Helfer

    private void addChip(LinearLayout row, String label, Runnable action) {
        addChipTo(row, label, action);
    }

    private void addChipTo(LinearLayout parent, String label, Runnable action) {
        TextView chip = tv(label, 12, TEXT, true);
        chip.setPadding(dp(14), dp(9), dp(14), dp(9));
        chip.setBackground(round(CARD_2, 20, LINE, 1));
        chip.setOnClickListener(v -> action.run());
        if (parent == messages) {
            LinearLayout wrap = new LinearLayout(act);
            wrap.addView(chip, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            parent.addView(wrap, lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, dp(8)));
        } else {
            parent.addView(chip, lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, dp(8), 0));
        }
    }

    private Button flatButton(String label, int bg, int fg) {
        Button b = new Button(act);
        b.setText(label);
        b.setAllCaps(false);
        b.setTextColor(fg);
        b.setTextSize(13);
        b.setTypeface(Typeface.DEFAULT_BOLD);
        b.setPadding(dp(16), 0, dp(16), 0);
        b.setBackground(round(bg, 14, Color.TRANSPARENT, 0));
        return b;
    }

    private TextView tv(String value, int sp, int color, boolean bold) {
        TextView t = new TextView(act);
        t.setText(value);
        t.setTextSize(sp);
        t.setTextColor(color);
        if (bold) t.setTypeface(Typeface.DEFAULT_BOLD);
        return t;
    }

    private GradientDrawable round(int fill, int radiusDp, int stroke, int strokeDp) {
        GradientDrawable g = new GradientDrawable();
        g.setColor(fill);
        g.setCornerRadius(dp(radiusDp));
        if (strokeDp > 0) g.setStroke(dp(strokeDp), stroke);
        return g;
    }

    private GradientDrawable gradient() {
        GradientDrawable g = new GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT, new int[]{PRIMARY, CYAN});
        g.setCornerRadius(dp(18));
        return g;
    }

    private LinearLayout.LayoutParams lp(int w, int h, int l, int t, int r, int b) {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(w, h);
        p.setMargins(l, t, r, b);
        return p;
    }

    private int dp(int v) {
        return Math.round(v * density);
    }
}
