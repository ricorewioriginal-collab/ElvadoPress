package app.elvadopress.client;

import android.Manifest;
import android.app.Activity;
import android.content.ComponentName;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.media.AudioManager;
import android.net.Uri;
import android.text.Html;
import android.text.TextUtils;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.LruCache;
import android.view.Gravity;
import android.view.HapticFeedbackConstants;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;
import android.view.animation.DecelerateInterpolator;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.HorizontalScrollView;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.SeekBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.media3.common.MediaItem;
import androidx.media3.common.MediaMetadata;
import androidx.media3.common.Player;
import androidx.media3.session.MediaController;
import androidx.media3.session.SessionToken;

import com.google.common.util.concurrent.ListenableFuture;

import org.json.JSONArray;
import org.json.JSONObject;
import org.json.JSONTokener;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Collections;
import java.util.HashMap;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.Random;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MainActivity extends Activity {

    // Farben kommen aus dem Design-System (Ui); die Kurzformen halten den älteren Code lesbar
    private static final int BG = Ui.BG;
    private static final int CARD = Ui.SURFACE;
    private static final int CARD_2 = Ui.SURFACE_2;
    private static final int LINE = Ui.LINE;
    private static final int TEXT = Ui.TEXT;
    private static final int MUTED = Ui.MUTED;
    private static final int CYAN = Ui.CYAN;
    private static int PRIMARY = Ui.DEFAULT_ACCENT;

    private final ExecutorService io = Executors.newFixedThreadPool(6);
    private final Handler main = new Handler(Looper.getMainLooper());
    private final Map<String, StationInfo> stationCache = new HashMap<>();

    private SharedPreferences prefs;
    private AppConfig appConfig;

    // Gerüst der Oberfläche
    private FrameLayout rootFrame;
    private LinearLayout appColumn;
    private FrameLayout overlayHost;
    private final List<View> overlays = new ArrayList<>();
    private int insetLeft, insetTop, insetRight, insetBottom;

    private LinearLayout content;
    private TextView screenTitle;
    private ImageView headerLogo;
    private ImageButton backButton;
    private View topSpacer;
    private ImageButton assistantTile;
    private LinearLayout bottomNav;
    private ScrollView mainScroll;
    private LinearLayout assistantBar;
    private AssistantPanel assistant;
    private int scheduleDay = -1;
    private String scheduleStationId = null;
    private Runnable screenParent = null;

    // Mini-Player
    private LinearLayout miniPlayer;
    private ImageView miniCover;
    private TextView miniStation;
    private TextView miniTitle;
    private ImageButton miniPlay;
    private Ui.Equalizer miniEq;
    private TextView miniSleep;
    private final Random random = new Random();

    // Vollbild-Player
    private View fullPlayerView;
    private View fullPlayerBg;
    private ImageView fullCover;
    private TextView fullStation;
    private TextView fullSource;
    private View fullHome;
    private TextView fullArtist;
    private TextView fullTitle;
    private ImageButton fullPlay;
    private ImageButton fullFavorite;
    private ImageButton fullSave;
    private View fullSchedule;
    private View fullReport;
    private LinearLayout fullCommunityWrap;
    private LinearLayout fullNextWrap;
    private LinearLayout fullHistoryWrap;
    private Ui.Equalizer fullEq;
    private TextView sleepTimerLabel;
    private Runnable sleepTimerRunnable;
    private Runnable sleepTickRunnable;
    private long sleepEndsAt = 0;
    private int fullBgColor = 0;

    private ListenableFuture<MediaController> controllerFuture;
    private MediaController controller;
    private boolean pendingAutoplay = false;

    private String currentStationId = "";
    private String currentStationName = "";
    private String currentStationCover = "";
    private String currentArtist = "";
    private String currentTitle = "";
    private String currentArtwork = "";
    private boolean podcastMode = false;
    private String currentScreen = "home";
    private final List<String> activeStationQueue = new ArrayList<>();


    // Radioverzeichnis (nur Marken mit Verzeichnis)
    private Directory.Item currentWorld;                       // laufender Fremd-Stream (World Radio), sonst null
    private final List<Directory.Item> dirQueue = new ArrayList<>(); // Reihenfolge fuer Vor/Zurueck aus Suche/Zufall/Favoriten
    private final List<Directory.Item> dirShown = new ArrayList<>();
    private boolean dirReportOn = true;
    private String dirScope = "all";
    private String dirQuery = "";
    private int dirOffset = 0;

    private final Runnable metadataTick = new Runnable() {
        @Override public void run() {
            if (!podcastMode && currentStationId != null) {
                if (currentWorld != null) loadWorldMeta();
                else loadNowPlaying(currentStationId);
            }
            main.postDelayed(this, 20000);
        }
    };

    private static class StationInfo {
        final String id;
        final String name;
        final String description;
        final String cover;
        final List<String> genres;

        StationInfo(String id, String name, String description, String cover, List<String> genres) {
            this.id = id;
            this.name = name;
            this.description = description;
            this.cover = cover;
            this.genres = genres;
        }
    }

    /** Eine Kachel (Schnellzugriff, App-Builder-Kacheln, Community …). */
    private static class Tile {
        final int icon;
        final String title;
        final String sub;
        final Runnable action;

        Tile(int icon, String title, String sub, Runnable action) {
            this.icon = icon;
            this.title = title;
            this.sub = sub;
            this.action = action;
        }
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        Ui.init(this);
        prefs = getSharedPreferences("app_prefs", MODE_PRIVATE);
        appConfig = AppConfig.load(this);
        Ui.reduceMotion = prefs.getBoolean("set_reduce_motion", false);
        applyTheme();
        ErrorReporter.install(prefs, appVersion());
        NetCast.get(this).setStateListener(() -> updatePlayButtons());

        if (Build.VERSION.SDK_INT >= 33 &&
                checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 42);
        }

        configureSystemBars();
        View rootView = buildUi();
        setContentView(rootView);
        refreshCmsRuntimeConfig();
        refreshAppConfig();
        connectPlaybackService();
        showHome();

        // Letzten Sender wiederherstellen (Einstellung "Letzten Sender merken"); im Screenshot-Modus immer der Standard
        boolean shot = getIntent().getStringExtra("screenshot_screen") != null;
        String lastId = prefs.getString("last_station_id", "");
        String lastName = prefs.getString("last_station_name", "");
        if (!shot && prefs.getBoolean("set_remember", true) && !lastId.isEmpty() && !lastId.startsWith("world:")) {
            selectStation(lastId, lastName.isEmpty() ? prettyName(lastId) : lastName, false);
            pendingAutoplay = prefs.getBoolean("set_autoplay", false);
        } else {
            selectDefaultStationIfNone();
        }
        main.post(metadataTick);
        main.postDelayed(this::applyScreenshotDestination, 1200);
    }

    // Screenshot-Modus (CI): die App zeichnet ihre Ansichten selbst in PNG-Dateien
    // (files/screenshots/), statt dass der Emulator per screencap abfotografiert wird.
    // Das ist unabhängig von Splash-Timing, Emulator-Rendering und ANR-Dialogen.
    private static final String[] SCREENSHOT_SEQUENCE = {"home", "stations", "schedule", "community", "podcast", "news", "search"};
    /** Erweiterte Folge für die Entwicklungs-Vorschau (screenshot_screen=preview). */
    private static final String[] PREVIEW_SEQUENCE = {"home", "stations", "detail", "schedule", "community", "podcast", "news", "search",
            "directory", "search2", "directory2", "player", "player2", "more", "settings", "saved", "favorites", "info", "home2", "assistant", "sleep"};

    private void applyScreenshotDestination() {
        String screen = getIntent().getStringExtra("screenshot_screen");
        if (screen == null || screen.trim().isEmpty()) return;
        // Absturz im Screenshot-Modus nachvollziehbar machen (crash.txt wird vom CI-Skript ausgegeben)
        final Thread.UncaughtExceptionHandler previous = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler((t, e) -> {
            java.io.StringWriter sw = new java.io.StringWriter();
            e.printStackTrace(new java.io.PrintWriter(sw));
            writeScreenshotFile("crash.txt", "Thread " + t.getName() + ": " + sw);
            if (previous != null) previous.uncaughtException(t, e);
        });
        List<String> queue = new ArrayList<>();
        if ("all".equals(screen.trim())) {
            Collections.addAll(queue, SCREENSHOT_SEQUENCE);
        } else if ("preview".equals(screen.trim())) {
            Collections.addAll(queue, PREVIEW_SEQUENCE);
        } else if ("tablet".equals(screen.trim())) {
            Collections.addAll(queue, "home", "stations", "player", "news");
        } else {
            queue.add(screen.trim());
        }
        runScreenshotQueue(queue, 0);
    }

    private void runScreenshotQueue(List<String> queue, int index) {
        if (index >= queue.size()) {
            writeScreenshotFile("done.marker", null);
            return;
        }
        String screen = queue.get(index);
        showScreenshotScreen(screen);
        long settle = screenshotSettleMs(screen);
        // Netzwerkansichten: ist nach der halben Wartezeit nur der Fehlerhinweis da, einmal neu laden
        main.postDelayed(() -> {
            if (screenshotViewShowsError()) showScreenshotScreen(screen);
        }, settle / 2);
        main.postDelayed(() -> {
            captureScreenshot(screen);
            runScreenshotQueue(queue, index + 1);
        }, settle);
    }

    private boolean screenshotViewShowsError() {
        return viewTreeContainsText(content, "konnte gerade nicht");
    }

    private static boolean viewTreeContainsText(View v, String needle) {
        if (v instanceof TextView) {
            CharSequence t = ((TextView) v).getText();
            return t != null && t.toString().contains(needle);
        }
        if (v instanceof ViewGroup) {
            ViewGroup g = (ViewGroup) v;
            for (int i = 0; i < g.getChildCount(); i++) {
                if (viewTreeContainsText(g.getChildAt(i), needle)) return true;
            }
        }
        return false;
    }

    private static long screenshotSettleMs(String screen) {
        switch (screen) {
            case "schedule":
            case "podcast":
            case "news":
            case "player":
                return 7000;
            case "stations":
            case "detail":
            case "directory":
                return 5000;
            case "search2":
            case "directory2":
            case "player2":
                return 8000;
            default:
                return 3500;
        }
    }

    // ---------------------------------------------------------------- KI-Assistent

    private void showAssistant() {
        closeAllOverlays();
        currentScreen = "assistant";
        screenParent = this::showHome;
        updateBottomNavActive("assistant");
        setScreenTitle("KI-Assistent");
        applyAssistantConfig();
        assistant.show(currentStationId, ownedStations(), getFavorites(), this::prettyName);
    }

    /** Name, Begruessung und erlaubte Funktionen kommen aus dem CMS (Bereich KI-Assistent) und werden lokal gemerkt. */
    private void applyAssistantConfig() {
        if (assistant == null) return;
        assistant.configure(prefs.getString("assistant_name", ""), prefs.getString("assistant_greeting", ""),
                prefs.getBoolean("assistant_mail", true), prefs.getBoolean("assistant_voice", true));
        if (assistantTile != null) {
            assistantTile.setVisibility(prefs.getBoolean("assistant_enabled", true) && prefs.getBoolean("app_f_assistant", true) ? View.VISIBLE : View.GONE);
        }
    }

    private void showScreenshotScreen(String screen) {
        closeAllOverlays();
        switch (screen) {
            case "home":
                showHome();
                break;
            case "stations":
                showOwnedStations();
                break;
            case "detail":
                showStationDetail(defaultStationId());
                break;
            case "schedule":
                showSchedule(currentStationId);
                break;
            case "community":
                showCommunity();
                break;
            case "podcast":
                showPodcast();
                break;
            case "search":
                showStationSearch();
                break;
            case "directory":
                showDirectory();
                break;
            case "news":
                showNews();
                break;
            case "player":
                showFullPlayer();
                break;
            case "search2":
                showStationSearch();
                main.postDelayed(() -> typeIntoFirstField(content, "rock"), 900);
                break;
            case "directory2":
                showDirectory();
                main.postDelayed(() -> typeIntoFirstField(content, "jazz"), 900);
                break;
            case "player2":
                // Songs merken, Sleep-Timer starten, Player öffnen und nach unten scrollen
                toggleSaveSong(currentArtist, currentTitle, currentStationName);
                setSleepTimer(30);
                showFullPlayer();
                main.postDelayed(() -> {
                    if (fullPlayerView instanceof ViewGroup && ((ViewGroup) fullPlayerView).getChildCount() > 0
                            && ((ViewGroup) fullPlayerView).getChildAt(0) instanceof ScrollView) {
                        ((ScrollView) ((ViewGroup) fullPlayerView).getChildAt(0)).fullScroll(View.FOCUS_DOWN);
                    }
                }, 3500);
                break;
            case "home2":
                showHome();
                main.postDelayed(() -> { if (mainScroll != null) mainScroll.scrollTo(0, dp(560)); }, 2500);
                break;
            case "assistant":
                showAssistant();
                break;
            case "sleep":
                showSleepTimerMenu();
                break;
            case "more":
                showMainMenu();
                break;
            case "settings":
                showSettings();
                break;
            case "saved":
                if (savedSongs().isEmpty()) {
                    // Vorschau: Beispiel-Einträge, damit die Ansicht nicht leer erscheint (wird nicht gespeichert)
                    previewSavedSongs = true;
                }
                showSavedSongs();
                break;
            case "favorites":
                showFavorites();
                break;
            case "info":
                showInfo();
                break;
            case "social":
                openInternalWeb("News & Social", appConfig.website + "index.html#news");
                break;
            default:
                showHome();
                break;
        }
    }

    private boolean previewSavedSongs = false;

    /** Screenshot-Modus: Text in das erste Eingabefeld schreiben und die Suche auslösen. */
    private void typeIntoFirstField(View root, String text) {
        EditText et = findEdit(root);
        if (et == null) return;
        et.setText(text);
        et.setSelection(text.length());
        et.onEditorAction(android.view.inputmethod.EditorInfo.IME_ACTION_SEARCH);
    }

    private EditText findEdit(View v) {
        if (v instanceof EditText) return (EditText) v;
        if (v instanceof ViewGroup) {
            ViewGroup g = (ViewGroup) v;
            for (int i = 0; i < g.getChildCount(); i++) {
                EditText r = findEdit(g.getChildAt(i));
                if (r != null) return r;
            }
        }
        return null;
    }

    private void captureScreenshot(String screen) {
        String fileName = "android-" + ("news".equals(screen) ? "news-social" : screen) + ".png";
        try {
            View decor = getWindow().getDecorView();
            int w = decor.getWidth(), h = decor.getHeight();
            if (w <= 0 || h <= 0) {
                writeScreenshotFile(fileName + ".error", "decor view has no size");
                return;
            }
            // 60 % Groesse: reicht fuer die Apps-Seite und spart Speicher (volle 1080x2400 ARGB = 10 MB je Bild)
            final float scale = 0.6f;
            Bitmap bmp = Bitmap.createBitmap(Math.round(w * scale), Math.round(h * scale), Bitmap.Config.ARGB_8888);
            android.graphics.Canvas canvas = new android.graphics.Canvas(bmp);
            canvas.drawColor(BG);
            canvas.scale(scale, scale);
            decor.draw(canvas);
            java.io.File out = screenshotFile(fileName);
            java.io.File tmp = screenshotFile(fileName + ".tmp");
            try (java.io.FileOutputStream fos = new java.io.FileOutputStream(tmp)) {
                bmp.compress(Bitmap.CompressFormat.PNG, 90, fos);
                fos.flush();
            }
            bmp.recycle();
            if (!tmp.renameTo(out)) writeScreenshotFile(fileName + ".error", "rename failed");
        } catch (Exception | OutOfMemoryError e) {
            writeScreenshotFile(fileName + ".error", String.valueOf(e));
        }
    }

    private java.io.File screenshotFile(String name) {
        java.io.File dir = new java.io.File(getFilesDir(), "screenshots");
        //noinspection ResultOfMethodCallIgnored
        dir.mkdirs();
        return new java.io.File(dir, name);
    }

    private void writeScreenshotFile(String name, String text) {
        try (java.io.FileOutputStream fos = new java.io.FileOutputStream(screenshotFile(name))) {
            fos.write((text == null ? "ok" : text).getBytes(StandardCharsets.UTF_8));
        } catch (Exception ignored) {
        }
    }

    // ================================================================ Gerüst: Kopfleiste, Inhalt, Mini-Player, Navigation

    private GradientDrawable pageBackground() {
        return new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM,
                new int[]{Color.rgb(12, 15, 44), Ui.BG, Ui.BG_DEEP});
    }

    private int contentMaxWidthPx() {
        int w = getResources().getDisplayMetrics().widthPixels;
        int max = dp(720);
        return w > max + dp(64) ? max : 0;
    }

    private View buildUi() {
        rootFrame = new FrameLayout(this);
        rootFrame.setBackground(pageBackground());

        appColumn = Ui.vbox(this);
        appColumn.addView(buildTopBar());

        mainScroll = new ScrollView(this);
        mainScroll.setFillViewport(true);
        mainScroll.setVerticalScrollBarEnabled(false);
        mainScroll.setOverScrollMode(View.OVER_SCROLL_NEVER);
        FrameLayout holder = new FrameLayout(this);
        content = Ui.vbox(this);
        content.setPadding(dp(16), dp(6), dp(16), dp(28));
        int maxW = contentMaxWidthPx();
        holder.addView(content, new FrameLayout.LayoutParams(maxW > 0 ? maxW : ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.CENTER_HORIZONTAL | Gravity.TOP));
        mainScroll.addView(holder, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        appColumn.addView(mainScroll, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));

        // Eingabeleiste des KI-Assistenten: nur in dessen Ansicht sichtbar, Mini-Player und Navigation bleiben frei
        assistantBar = new LinearLayout(this);
        assistantBar.setOrientation(LinearLayout.VERTICAL);
        assistantBar.setVisibility(View.GONE);
        appColumn.addView(assistantBar, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        assistant = new AssistantPanel(this, siteBase(), io, main, mainScroll, content, assistantBar);
        assistant.setStationPlayer((items, index) -> {
            if (items == null || index < 0 || index >= items.size()) return;
            List<Directory.Item> list = new ArrayList<>();
            for (org.json.JSONObject o : items) list.add(new Directory.Item(o));
            playDirectoryItem(list.get(index), list);
        });
        applyAssistantConfig();

        miniPlayer = buildMiniPlayer();
        LinearLayout.LayoutParams mlp = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(68));
        mlp.setMargins(dp(10), dp(4), dp(10), dp(6));
        appColumn.addView(miniPlayer, mlp);

        appColumn.addView(buildBottomNav());

        rootFrame.addView(appColumn, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        overlayHost = new FrameLayout(this);
        overlayHost.setVisibility(View.GONE);
        rootFrame.addView(overlayHost, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        rootFrame.setOnApplyWindowInsetsListener((v, insets) -> {
            insetLeft = insets.getSystemWindowInsetLeft();
            insetTop = insets.getSystemWindowInsetTop();
            insetRight = insets.getSystemWindowInsetRight();
            insetBottom = insets.getSystemWindowInsetBottom();
            appColumn.setPadding(insetLeft, insetTop, insetRight, insetBottom);
            for (View ov : overlays) {
                if (ov.getTag() != null && "fullscreen".equals(ov.getTag())) ov.setPadding(insetLeft, insetTop, insetRight, insetBottom);
            }
            return insets;
        });
        rootFrame.requestApplyInsets();
        return rootFrame;
    }

    private View buildTopBar() {
        LinearLayout bar = Ui.hbox(this);
        bar.setPadding(dp(14), dp(8), dp(12), dp(8));

        backButton = Ui.iconButton(this, R.drawable.ic_back, TEXT, Ui.SURFACE, 42, "Zurück");
        backButton.setVisibility(View.GONE);
        backButton.setOnClickListener(v -> onBackPressed());
        bar.addView(backButton, Ui.lp(dp(42), dp(42), 0, 0, 10, 0));

        headerLogo = new ImageView(this);
        headerLogo.setScaleType(ImageView.ScaleType.FIT_START);
        headerLogo.setAdjustViewBounds(true);
        loadBrandAsset("config/logo-lockup.png", headerLogo, R.drawable.app_logo);
        bar.addView(headerLogo, Ui.lp(dp(150), dp(38)));

        screenTitle = Ui.text(this, "", 21, TEXT, Ui.BOLD);
        screenTitle.setSingleLine(true);
        screenTitle.setEllipsize(TextUtils.TruncateAt.END);
        screenTitle.setVisibility(View.GONE);
        bar.addView(screenTitle, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        topSpacer = new View(this);
        bar.addView(topSpacer, new LinearLayout.LayoutParams(0, 1, 1f));

        ImageButton search = Ui.iconButton(this, R.drawable.ic_search, TEXT, Ui.SURFACE, 42, "Sender suchen");
        search.setOnClickListener(v -> showStationSearch());
        bar.addView(search, Ui.lp(dp(42), dp(42), 0, 0, 8, 0));

        // KI-Assistent: runde Schaltfläche rechts in der Kopfleiste
        assistantTile = new ImageButton(this);
        assistantTile.setImageResource(R.drawable.ic_sparkle);
        assistantTile.setColorFilter(Ui.ON_ACCENT);
        assistantTile.setScaleType(ImageView.ScaleType.CENTER);
        assistantTile.setPadding(dp(10), dp(10), dp(10), dp(10));
        assistantTile.setBackground(Ui.ripple(accentOval(), 42));
        assistantTile.setContentDescription("KI-Assistent öffnen");
        assistantTile.setOnClickListener(v -> showAssistant());
        bar.addView(assistantTile, new LinearLayout.LayoutParams(dp(42), dp(42)));
        return bar;
    }

    private GradientDrawable accentOval() {
        GradientDrawable g = new GradientDrawable(GradientDrawable.Orientation.TL_BR,
                new int[]{Ui.accent, Ui.mix(Ui.accent, Ui.CYAN, 0.55f)});
        g.setShape(GradientDrawable.OVAL);
        return g;
    }

    private LinearLayout buildBottomNav() {
        LinearLayout wrap = Ui.vbox(this);
        wrap.setBackgroundColor(Color.rgb(8, 11, 32));
        View divider = new View(this);
        divider.setBackgroundColor(Ui.alpha(Ui.LINE, 150));
        wrap.addView(divider, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 1));

        LinearLayout nav = new LinearLayout(this);
        nav.setOrientation(LinearLayout.HORIZONTAL);
        nav.setGravity(Gravity.CENTER);
        nav.setPadding(dp(6), dp(6), dp(6), dp(4));
        bottomNav = nav;

        addBottomNavItem(nav, R.drawable.ic_home, "Start", "home", this::showHome);
        addBottomNavItem(nav, R.drawable.ic_radio, "Sender", "owned", this::showOwnedStations);
        addBottomNavItem(nav, R.drawable.ic_calendar, "Sendeplan", "schedule", () -> showSchedule(currentStationId));
        addBottomNavItem(nav, R.drawable.ic_equalizer, "Mitmachen", "community", this::showCommunity);
        addBottomNavItem(nav, R.drawable.ic_more, "Mehr", "more", this::showMainMenu);
        wrap.addView(nav, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(62)));
        return wrap;
    }

    private void addBottomNavItem(LinearLayout nav, int iconRes, String label, String key, Runnable action) {
        LinearLayout item = new LinearLayout(this);
        item.setOrientation(LinearLayout.VERTICAL);
        item.setGravity(Gravity.CENTER);
        item.setTag(key);
        item.setContentDescription(label);
        item.setClickable(true);
        item.setFocusable(true);

        FrameLayout pill = new FrameLayout(this);
        pill.setTag("pill");
        ImageView icon = Ui.icon(this, iconRes, MUTED, 22);
        icon.setTag("icon");
        pill.addView(icon, new FrameLayout.LayoutParams(dp(22), dp(22), Gravity.CENTER));
        item.addView(pill, new LinearLayout.LayoutParams(dp(58), dp(30)));

        TextView title = Ui.text(this, label, 11, MUTED, Ui.MEDIUM);
        title.setGravity(Gravity.CENTER);
        title.setTag("label");
        title.setSingleLine(true);
        item.addView(title, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 2, 0, 0));

        item.setOnClickListener(v -> {
            haptic(v);
            action.run();
        });
        nav.addView(item, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, 1f));
    }

    private void updateBottomNavActive(String key) {
        if (bottomNav == null) return;
        for (int n = 0; n < bottomNav.getChildCount(); n++) {
            View child = bottomNav.getChildAt(n);
            if (!(child instanceof LinearLayout)) continue;
            boolean active = key.equals(String.valueOf(child.getTag()));
            int color = active ? Ui.accent : MUTED;
            View pill = child.findViewWithTag("pill");
            ImageView icon = child.findViewWithTag("icon");
            TextView label = child.findViewWithTag("label");
            if (pill != null) pill.setBackground(active ? Ui.rect(Ui.alpha(Ui.accent, 48), 15, 0, 0) : null);
            if (icon != null) icon.setColorFilter(active ? Ui.accent : MUTED);
            if (label != null) {
                label.setTextColor(active ? Ui.TEXT : color);
                label.setTypeface(Typeface.create(active ? "sans-serif" : "sans-serif-medium", active ? Typeface.BOLD : Typeface.NORMAL));
            }
        }
    }

    private void haptic(View v) {
        if (v != null && prefs != null && prefs.getBoolean("set_haptics", true)) {
            v.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY);
        }
    }

    private LinearLayout buildMiniPlayer() {
        LinearLayout bar = new LinearLayout(this);
        bar.setOrientation(LinearLayout.HORIZONTAL);
        bar.setGravity(Gravity.CENTER_VERTICAL);
        bar.setPadding(dp(9), dp(8), dp(8), dp(8));
        bar.setBackground(Ui.ripple(Ui.rect(Color.rgb(20, 27, 70), 22, Ui.LINE, 1), 22));
        bar.setElevation(dp(6));
        bar.setClickable(true);
        bar.setOnClickListener(v -> showFullPlayer());

        miniCover = Ui.cover(this, 14);
        miniCover.setImageResource(R.drawable.app_logo);
        bar.addView(miniCover, Ui.lp(dp(50), dp(50), 0, 0, 11, 0));

        LinearLayout copy = Ui.vbox(this);
        copy.setGravity(Gravity.CENTER_VERTICAL);
        LinearLayout head = Ui.hbox(this);
        miniStation = Ui.text(this, getString(R.string.app_name).toUpperCase(java.util.Locale.ROOT), 10, Ui.CYAN, Ui.BOLD);
        miniStation.setAllCaps(true);
        miniStation.setLetterSpacing(0.06f);
        miniStation.setSingleLine(true);
        miniStation.setEllipsize(TextUtils.TruncateAt.END);
        head.addView(miniStation, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        miniSleep = Ui.text(this, "", 10, Ui.accent, Ui.BOLD);
        miniSleep.setVisibility(View.GONE);
        miniSleep.setPadding(dp(6), 0, 0, 0);
        head.addView(miniSleep);
        miniEq = new Ui.Equalizer(this);
        miniEq.setColor(Ui.CYAN);
        head.addView(miniEq, Ui.lp(dp(14), dp(11), 6, 0, 0, 0));
        copy.addView(head);

        miniTitle = Ui.text(this, "Sender wählen", 15, TEXT, Ui.BOLD);
        Ui.marquee(miniTitle);
        copy.addView(miniTitle, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        bar.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        ImageButton prev = Ui.iconButton(this, R.drawable.ic_prev, TEXT, Color.TRANSPARENT, 38, "Voriger Sender");
        prev.setOnClickListener(v -> { haptic(v); switchStation(-1); });
        bar.addView(prev, Ui.lp(dp(38), dp(38), 4, 0, 0, 0));

        miniPlay = new ImageButton(this);
        miniPlay.setImageResource(R.drawable.ic_play);
        miniPlay.setColorFilter(Ui.ON_ACCENT);
        miniPlay.setScaleType(ImageView.ScaleType.CENTER);
        miniPlay.setPadding(dp(11), dp(11), dp(11), dp(11));
        miniPlay.setBackground(Ui.ripple(accentOval(), 46));
        miniPlay.setContentDescription("Wiedergabe");
        miniPlay.setOnClickListener(v -> { haptic(v); togglePlayback(); });
        bar.addView(miniPlay, Ui.lp(dp(46), dp(46), 2, 0, 2, 0));

        ImageButton next = Ui.iconButton(this, R.drawable.ic_next, TEXT, Color.TRANSPARENT, 38, "Nächster Sender");
        next.setOnClickListener(v -> { haptic(v); switchStation(1); });
        bar.addView(next, new LinearLayout.LayoutParams(dp(38), dp(38)));
        return bar;
    }

    private void configureMarquee(TextView view) {
        Ui.marquee(view);
    }

    // ---------------------------------------------------------------- Überlagerungen (Vollbild-Player, Menüs)

    private void openOverlay(View ov, boolean fullscreen, boolean slideUp) {
        if (fullscreen) {
            ov.setTag("fullscreen");
            ov.setPadding(insetLeft, insetTop, insetRight, insetBottom);
        }
        overlays.add(ov);
        overlayHost.addView(ov, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        overlayHost.setVisibility(View.VISIBLE);
        if (Ui.reduceMotion) return;
        ov.setAlpha(0f);
        if (slideUp) ov.setTranslationY(dp(70));
        ov.animate().alpha(1f).translationY(0).setDuration(240).setInterpolator(new DecelerateInterpolator()).start();
    }

    private void removeOverlayNow(View ov) {
        overlays.remove(ov);
        overlayHost.removeView(ov);
        if (overlays.isEmpty()) overlayHost.setVisibility(View.GONE);
        if (ov == fullPlayerView) clearFullPlayerRefs();
    }

    private void closeOverlay(View ov) {
        if (!overlays.contains(ov)) return;
        if (Ui.reduceMotion) {
            removeOverlayNow(ov);
            return;
        }
        overlays.remove(ov); // schon jetzt als geschlossen behandeln (Zurück-Taste, Mehrfachtippen)
        if (ov == fullPlayerView) clearFullPlayerRefs();
        ov.animate().alpha(0f).translationY(dp(50)).setDuration(170).withEndAction(() -> {
            overlayHost.removeView(ov);
            if (overlays.isEmpty()) overlayHost.setVisibility(View.GONE);
        }).start();
    }

    private void closeAllOverlays() {
        for (View ov : new ArrayList<>(overlays)) removeOverlayNow(ov);
    }

    private boolean isFullPlayerOpen() {
        return fullPlayerView != null && overlays.contains(fullPlayerView);
    }

    private void clearFullPlayerRefs() {
        fullPlayerView = null;
        fullPlayerBg = null;
        fullCover = null;
        fullStation = null;
        fullSource = null;
        fullHome = null;
        fullArtist = null;
        fullTitle = null;
        fullPlay = null;
        fullFavorite = null;
        fullSave = null;
        fullSchedule = null;
        fullReport = null;
        fullCommunityWrap = null;
        fullNextWrap = null;
        fullHistoryWrap = null;
        fullEq = null;
        sleepTimerLabel = null;
    }

    /** Begrenzt die Höhe eines Blattes auf einen Anteil des Bildschirms. */
    private static class CappedColumn extends LinearLayout {
        private final int maxHeight;

        CappedColumn(android.content.Context c, int maxHeight) {
            super(c);
            this.maxHeight = maxHeight;
        }

        @Override protected void onMeasure(int widthSpec, int heightSpec) {
            int mode = MeasureSpec.getMode(heightSpec);
            int size = MeasureSpec.getSize(heightSpec);
            if (mode == MeasureSpec.UNSPECIFIED || size > maxHeight) {
                heightSpec = MeasureSpec.makeMeasureSpec(maxHeight, MeasureSpec.AT_MOST);
            }
            super.onMeasure(widthSpec, heightSpec);
        }
    }

    /** Menü-Blatt von unten (Mehr-Menü, Sleep-Timer, Aktionen). */
    private class Sheet {
        final FrameLayout overlay = new FrameLayout(MainActivity.this);
        final LinearLayout body = Ui.vbox(MainActivity.this);
        final LinearLayout panel;

        Sheet(String title) {
            overlay.setBackgroundColor(Color.argb(175, 2, 4, 16));
            overlay.setClickable(true);
            overlay.setOnClickListener(v -> close());
            int maxH = (int) (getResources().getDisplayMetrics().heightPixels * 0.86f);
            panel = new CappedColumn(MainActivity.this, maxH);
            panel.setOrientation(LinearLayout.VERTICAL);
            GradientDrawable bg = new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM,
                    new int[]{Color.rgb(24, 31, 78), Color.rgb(14, 19, 52)});
            float r = dp(28);
            bg.setCornerRadii(new float[]{r, r, r, r, 0, 0, 0, 0});
            bg.setStroke(1, Ui.LINE);
            panel.setBackground(bg);
            panel.setClickable(true);
            panel.setPadding(dp(18), dp(10), dp(18), dp(14) + insetBottom);

            View grab = new View(MainActivity.this);
            grab.setBackground(Ui.rect(Ui.alpha(Ui.TEXT, 70), 3, 0, 0));
            LinearLayout.LayoutParams glp = new LinearLayout.LayoutParams(dp(40), dp(4));
            glp.gravity = Gravity.CENTER_HORIZONTAL;
            glp.bottomMargin = dp(12);
            panel.addView(grab, glp);

            LinearLayout head = Ui.hbox(MainActivity.this);
            head.addView(Ui.text(MainActivity.this, title, 20, TEXT, Ui.BOLD), new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            ImageButton x = Ui.iconButton(MainActivity.this, R.drawable.ic_close, TEXT, Ui.alpha(Ui.TEXT, 25), 38, "Schließen");
            x.setOnClickListener(v -> close());
            head.addView(x);
            panel.addView(head, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 8));

            ScrollView sv = new ScrollView(MainActivity.this);
            sv.setVerticalScrollBarEnabled(false);
            sv.setOverScrollMode(View.OVER_SCROLL_NEVER);
            sv.addView(body, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            panel.addView(sv, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

            FrameLayout.LayoutParams plp = new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.BOTTOM);
            int maxW = contentMaxWidthPx();
            if (maxW > 0) {
                plp = new FrameLayout.LayoutParams(maxW, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.BOTTOM | Gravity.CENTER_HORIZONTAL);
            }
            overlay.addView(panel, plp);
        }

        void show() {
            overlays.add(overlay);
            overlayHost.addView(overlay, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
            overlayHost.setVisibility(View.VISIBLE);
            if (Ui.reduceMotion) return;
            overlay.setAlpha(0f);
            overlay.animate().alpha(1f).setDuration(180).start();
            panel.setTranslationY(dp(500));
            panel.animate().translationY(0).setDuration(260).setInterpolator(new DecelerateInterpolator()).start();
        }

        void close() {
            if (!overlays.contains(overlay)) return;
            if (Ui.reduceMotion) {
                removeOverlayNow(overlay);
                return;
            }
            overlays.remove(overlay);
            panel.animate().translationY(panel.getHeight()).setDuration(190).start();
            overlay.animate().alpha(0f).setDuration(190).withEndAction(() -> {
                overlayHost.removeView(overlay);
                if (overlays.isEmpty()) overlayHost.setVisibility(View.GONE);
            }).start();
        }
    }

    // ---------------------------------------------------------------- Gemeinsame Bausteine für Ansichten

    /** Beginnt eine neue Ansicht: Navigation, Titel, Rücksprung, leerer Inhalt. */
    private void beginScreen(String screen, String navKey, String title, Runnable parent) {
        closeAllOverlays();
        currentScreen = screen;
        screenParent = parent;
        updateBottomNavActive(navKey);
        setScreenTitle(title);
        content.removeAllViews();
        if (mainScroll != null) mainScroll.scrollTo(0, 0);
    }

    private void setScreenTitle(String title) {
        if (assistant != null && !"KI-Assistent".equals(title)) assistant.hide();
        // Die Marke steht in der Kopfleiste; nur der KI-Assistent hat dort statt dessen einen Titel
        boolean showTitle = "assistant".equals(currentScreen);
        if (screenTitle != null) {
            screenTitle.setText(title);
            screenTitle.setVisibility(showTitle ? View.VISIBLE : View.GONE);
        }
        if (topSpacer != null) topSpacer.setVisibility(showTitle ? View.GONE : View.VISIBLE);
        if (headerLogo != null) headerLogo.setVisibility(showTitle ? View.GONE : View.VISIBLE);
        if (backButton != null) backButton.setVisibility(screenParent != null ? View.VISIBLE : View.GONE);
    }

    private void addToContent(View v, float topDp, float bottomDp) {
        content.addView(v, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, topDp, 0, bottomDp));
    }

    /** Abschnittsüberschrift mit Abstand nach oben. */
    private void addSection(String title, String actionLabel, Runnable action) {
        addToContent(Ui.sectionHeader(this, title, actionLabel, action), 22, 0);
    }

    /** Überschrift einer Ansicht (groß, mit kleiner Zeile darunter). */
    private void addPageHeading(String title, String subtitle) {
        LinearLayout wrap = Ui.vbox(this);
        if (subtitle != null && !subtitle.isEmpty()) wrap.addView(Ui.eyebrow(this, subtitle, Ui.accent));
        TextView t = Ui.text(this, title, 26, TEXT, Ui.BOLD);
        t.setPadding(0, dp(subtitle == null || subtitle.isEmpty() ? 0 : 4), 0, 0);
        wrap.addView(t);
        addToContent(wrap, 6, 12);
    }

    private void addEmpty(String message) {
        LinearLayout box = Ui.vbox(this);
        box.setGravity(Gravity.CENTER_HORIZONTAL);
        box.setPadding(dp(22), dp(34), dp(22), dp(34));
        box.setBackground(Ui.rect(CARD, 20, LINE, 1));
        ImageView ic = Ui.icon(this, R.drawable.ic_radio, Ui.alpha(MUTED, 190), 34);
        box.addView(ic, Ui.lp(dp(34), dp(34), 0, 0, 0, 10));
        TextView t = Ui.text(this, message, 14, Ui.TEXT_2, Ui.REGULAR);
        t.setGravity(Gravity.CENTER);
        box.addView(t);
        addToContent(box, 8, 0);
    }

    /** Platzhalter-Zeilen, bis Daten geladen sind. */
    private LinearLayout addLoading(int rows) {
        LinearLayout box = Ui.vbox(this);
        for (int i = 0; i < rows; i++) {
            LinearLayout r = Ui.hbox(this);
            r.setPadding(dp(10), dp(10), dp(10), dp(10));
            r.setBackground(Ui.rect(CARD, 18, LINE, 1));
            r.addView(Ui.skeleton(this, 56, 56, 14));
            LinearLayout col = Ui.vbox(this);
            col.setPadding(dp(12), 0, 0, 0);
            col.addView(Ui.skeleton(this, 150, 14, 7));
            View second = Ui.skeleton(this, 220, 11, 6);
            ((ViewGroup.LayoutParams) second.getLayoutParams()).width = dp(220);
            col.addView(second, Ui.lp(dp(220), dp(11), 0, 8, 0, 0));
            r.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            box.addView(r, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 9));
        }
        addToContent(box, 4, 0);
        return box;
    }

    /** Entfernt den Platzhalter; kam die Antwort aus dem lokalen Speicher, steht dort ein Offline-Hinweis mit Stand. */
    private void replaceLoading(View loading, DataCache.Result res) {
        int pos = content.indexOfChild(loading);
        content.removeView(loading);
        if (res != null && res.fromCache) {
            TextView note = Ui.text(this, "Offline – zuletzt gespeicherter Stand vom " + stampText(res.savedAt)
                    + ". Sobald du wieder online bist, wird aktualisiert.", 12, Ui.TEXT_2, Ui.REGULAR);
            note.setPadding(dp(14), dp(10), dp(14), dp(10));
            note.setBackground(Ui.rect(CARD_2, 14, LINE, 1));
            LinearLayout.LayoutParams lp = Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 12);
            content.addView(note, pos < 0 ? content.getChildCount() : pos, lp);
        }
    }

    private LinearLayout simpleCard() {
        return Ui.card(this);
    }

    private void addCardToContent(View card) {
        addToContent(card, 0, 10);
    }

    /** Zeile mit Symbol, Titel und Untertitel (Menüs, Info, Hilfe). */
    private void addMenuRow(int iconRes, String title, String subtitle, Runnable action) {
        addToContent(Ui.row(this, iconRes, title, subtitle, action), 0, 9);
    }

    /** Alte Schreibweise (Info/Hilfe) bleibt erhalten. */
    private void addMenuCard(String title, String subtitle, Runnable action) {
        addMenuRow(R.drawable.ic_info, title, subtitle, action == null ? null : action);
    }

    /** Schnellzugriff-Kachel: Symbol-Kreis, Titel, Untertitel. */
    private View tileView(Tile t, boolean compact) {
        LinearLayout tile = Ui.vbox(this);
        tile.setPadding(dp(14), dp(14), dp(14), dp(13));
        tile.setBackground(Ui.surface(20));
        tile.setClickable(true);
        tile.setFocusable(true);
        FrameLayout bubble = new FrameLayout(this);
        bubble.setBackground(Ui.rect(Ui.alpha(Ui.accent, 42), 14, 0, 0));
        bubble.addView(Ui.icon(this, t.icon, Ui.accent, 22), new FrameLayout.LayoutParams(dp(22), dp(22), Gravity.CENTER));
        tile.addView(bubble, new LinearLayout.LayoutParams(dp(42), dp(42)));
        TextView title = Ui.text(this, t.title, 15, TEXT, Ui.BOLD);
        title.setSingleLine(true);
        title.setEllipsize(TextUtils.TruncateAt.END);
        tile.addView(title, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 12, 0, 0));
        if (t.sub != null && !t.sub.isEmpty()) {
            TextView sub = Ui.text(this, t.sub, 12, MUTED, Ui.REGULAR);
            sub.setSingleLine(true);
            sub.setEllipsize(TextUtils.TruncateAt.END);
            tile.addView(sub, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        }
        tile.setOnClickListener(v -> {
            haptic(v);
            t.action.run();
        });
        return tile;
    }

    /** Kacheln in Spalten anordnen (2 auf dem Handy, mehr auf breiten Bildschirmen). */
    private void addTileGrid(List<Tile> tiles, int columns, float topDp) {
        LinearLayout row = null;
        int n = 0;
        for (Tile t : tiles) {
            if (n % columns == 0) {
                row = new LinearLayout(this);
                row.setOrientation(LinearLayout.HORIZONTAL);
                addToContent(row, n == 0 ? topDp : 10, 0);
            }
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
            if (n % columns != 0) lp.leftMargin = dp(10);
            row.addView(tileView(t, false), lp);
            n++;
        }
        if (row != null && n % columns != 0) {
            for (int i = n % columns; i < columns; i++) {
                View gap = new View(this);
                LinearLayout.LayoutParams g = new LinearLayout.LayoutParams(0, 1, 1f);
                g.leftMargin = dp(10);
                row.addView(gap, g);
            }
        }
    }

    private int gridColumns() {
        int maxW = contentMaxWidthPx();
        int w = maxW > 0 ? maxW : getResources().getDisplayMetrics().widthPixels;
        return Math.max(2, Math.min(4, (int) (w / (float) dp(168))));
    }

    // ================================================================ Startseite

    private void showHome() {
        beginScreen("home", "home", "Start", null);
        activeStationQueue.clear();
        activeStationQueue.addAll(ownedStations());

        JSONObject lay = layout();
        JSONArray remote = lay == null ? null : lay.optJSONArray("home");
        if (remote != null && remote.length() > 0) {
            renderRemoteHome(remote);
            return;
        }

        addHero((hasDirectory()
                ? brandText("hero_eyebrow", "DEIN RADIOVERZEICHNIS")
                : "WILLKOMMEN BEI " + brandName()),
                brandText("hero_title", "Willkommen bei\n" + brandName()),
                brandText("hero_text",
                        hasDirectory()
                                ? "Finde deinen Lieblingssender in unserem Radioverzeichnis – mit Webradios von laut.fm und aus aller Welt. Im Fokus stehen unsere eigenen Sender. Suchen, reinhören, Favoriten speichern oder dich überraschen lassen."
                                : "Alle Streams von " + brandName() + " an einem Ort. Hör rein, entdecke Sender und nutze Favoriten und Sendeplan direkt in der App."));

        addQuickRail();
        addRecentRail();
        addStationRail("Unsere Sender", ownedStations(), "Alle", this::showOwnedStations);
        addFavoritesRail();
        if (hasDirectory()) addDiscoverRail();
        addNewsRail();
        addPodcastTeaser();
    }

    /** Willkommens-Karte der Startseite (Texte aus dem CMS bzw. Standard). */
    private void addHero(String eyebrow, String title, String text) {
        FrameLayout hero = new FrameLayout(this);
        hero.setBackground(Ui.gradient(Ui.heroFrom, Ui.heroTo, 26, GradientDrawable.Orientation.TL_BR));
        hero.setClipToOutline(true);
        hero.setOutlineProvider(new android.view.ViewOutlineProvider() {
            @Override public void getOutline(View view, android.graphics.Outline outline) {
                outline.setRoundRect(0, 0, view.getWidth(), view.getHeight(), dp(26));
            }
        });

        // Dekor: ein weicher Kreis in der Ecke
        View c1 = new View(this);
        c1.setBackground(Ui.oval(Ui.alpha(Ui.CYAN, 20)));
        FrameLayout.LayoutParams c1lp = new FrameLayout.LayoutParams(dp(230), dp(230), Gravity.TOP | Gravity.END);
        c1lp.setMargins(0, -dp(100), -dp(80), 0);
        hero.addView(c1, c1lp);

        LinearLayout inner = Ui.vbox(this);
        inner.setPadding(dp(20), dp(20), dp(20), dp(20));
        if (eyebrow != null && !eyebrow.trim().isEmpty()) inner.addView(Ui.eyebrow(this, eyebrow, Ui.CYAN));

        TextView h = Ui.text(this, title, 27, Color.WHITE, Ui.BOLD);
        h.setLineSpacing(0, 1.08f);
        inner.addView(h, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 8, 0, 0));

        if (text != null && !text.trim().isEmpty()) {
            TextView p = Ui.text(this, text, 13.5f, Ui.alpha(Color.WHITE, 205), Ui.REGULAR);
            p.setLineSpacing(0, 1.2f);
            Ui.ellipsize(p, 4);
            inner.addView(p, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 10, 0, 0));
        }

        LinearLayout actions = Ui.hbox(this);
        View listen = Ui.button(this, "Jetzt hören", R.drawable.ic_play, Ui.PRIMARY);
        listen.setOnClickListener(v -> {
            haptic(v);
            listenNow();
        });
        actions.addView(listen, new LinearLayout.LayoutParams(0, dp(48), 1f));
        View more = Ui.button(this, hasDirectory() ? "Verzeichnis" : "Entdecken", hasDirectory() ? R.drawable.ic_globe : R.drawable.ic_search, Ui.OUTLINE);
        more.setOnClickListener(v -> showStationSearch());
        LinearLayout.LayoutParams mlp = new LinearLayout.LayoutParams(0, dp(48), 1f);
        mlp.leftMargin = dp(10);
        actions.addView(more, mlp);
        inner.addView(actions, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 16, 0, 0));

        hero.addView(inner, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        addToContent(hero, 6, 0);
    }

    /** "Jetzt hören": den zuletzt gewählten Sender starten und den Player öffnen. */
    private void listenNow() {
        boolean playing = NetCast.get(this).isActive() ? NetCast.get(this).isPlaying() : (controller != null && controller.isPlaying());
        if (!playing) {
            if (currentWorld != null) playWorld(currentWorld);
            else if (podcastMode) togglePlayback();
            else selectStation(currentStationId, currentStationName, true);
        }
        showFullPlayer();
    }

    // ---------- App-Builder (CMS → Apps): Layout aus app_config ----------

    private JSONObject layout() {
        try {
            String raw = prefs.getString("app_layout", "");
            return raw.isEmpty() ? null : new JSONObject(raw);
        } catch (Exception e) {
            return null;
        }
    }

    private static int parseColor(String c, int def) {
        try { return c == null || c.isEmpty() ? def : Color.parseColor(c); } catch (Exception e) { return def; }
    }

    /** Farben aus dem Builder übernehmen (ohne Builder: die Standardfarben). */
    private void applyTheme() {
        JSONObject lay = layout();
        JSONObject th = lay == null ? null : lay.optJSONObject("theme");
        PRIMARY = parseColor(th == null ? "" : th.optString("accent"), Ui.DEFAULT_ACCENT);
        Ui.accent = PRIMARY;
        Ui.heroFrom = parseColor(th == null ? "" : th.optString("hero_from"), Color.rgb(18, 13, 63));
        Ui.heroTo = parseColor(th == null ? "" : th.optString("hero_to"), Color.rgb(91, 28, 132));
    }

    /** Core-Sender in der im Builder festgelegten Reihenfolge, ohne ausgeblendete. */
    private List<String> ownedStations() {
        return Stations.owned(this, prefs);
    }

    private boolean menuHidden(String key) {
        JSONObject lay = layout();
        JSONObject mm = lay == null ? null : lay.optJSONObject("more_menu");
        JSONArray hide = mm == null ? null : mm.optJSONArray("hide");
        if (hide != null) for (int i = 0; i < hide.length(); i++) if (key.equals(hide.optString(i))) return true;
        return false;
    }

    private void renderRemoteHome(JSONArray blocks) {
        for (int i = 0; i < blocks.length(); i++) {
            JSONObject b = blocks.optJSONObject(i);
            if (b == null) continue;
            String type = b.optString("type");
            if ("hero".equals(type)) {
                addHero(b.optString("eyebrow"), b.optString("title").isEmpty() ? "Willkommen bei\n" + brandName() : b.optString("title"), b.optString("text"));
            } else if ("tiles".equals(type)) {
                if (!b.optString("title").isEmpty()) addSection(b.optString("title"), null, null);
                JSONArray tiles = b.optJSONArray("tiles");
                List<Tile> list = new ArrayList<>();
                if (tiles != null) for (int t = 0; t < tiles.length(); t++) {
                    JSONObject tl = tiles.optJSONObject(t);
                    if (tl == null) continue;
                    Tile spec = tileSpec(tl);
                    if (spec != null) list.add(spec);
                }
                addTileGrid(list, gridColumns(), b.optString("title").isEmpty() ? 14 : 0);
            } else if ("stations".equals(type)) {
                List<String> stations = ownedStations();
                int limit = b.optInt("limit", 0);
                if (limit > 0 && stations.size() > limit) stations = new ArrayList<>(stations.subList(0, limit));
                addStationRail(b.optString("title").isEmpty() ? "Unsere Sender" : b.optString("title"), stations, "Alle", this::showOwnedStations);
            } else if ("text".equals(type) || "link".equals(type)) {
                LinearLayout card = Ui.card(this);
                if (!b.optString("title").isEmpty()) card.addView(Ui.text(this, b.optString("title"), 17, TEXT, Ui.BOLD));
                if (!b.optString("text").isEmpty()) {
                    TextView tx = Ui.text(this, b.optString("text"), 14, Ui.TEXT_2, Ui.REGULAR);
                    tx.setLineSpacing(0, 1.2f);
                    tx.setPadding(0, dp(6), 0, 0);
                    card.addView(tx);
                }
                final String url = b.optString("url");
                if ("link".equals(type) && !url.isEmpty()) {
                    String label = b.optString("label");
                    View go = Ui.button(this, label.isEmpty() ? "Öffnen" : label, R.drawable.ic_open, Ui.PRIMARY);
                    go.setOnClickListener(v -> openInternalWeb(b.optString("title").isEmpty() ? brandName() : b.optString("title"), url));
                    card.addView(go, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(46), 0, 12, 0, 0));
                }
                addToContent(card, 16, 0);
            }
        }
    }

    /** Kachel-Typ aus dem Builder → Kachel; null = in dieser App nicht verfügbar. */
    private Tile tileSpec(JSONObject tl) {
        String id = tl.optString("id");
        String custom = tl.optString("title");
        final String url = tl.optString("url");
        Tile r;
        switch (id) {
            case "favorites": r = new Tile(R.drawable.ic_heart, "Favoriten", "Deine Sender", this::showFavorites); break;
            case "schedule": r = new Tile(R.drawable.ic_calendar, "Sendeplan", "Heute", () -> showSchedule(currentStationId)); break;
            case "podcast":
                if (!appConfig.podcast) return null;
                r = new Tile(R.drawable.ic_podcast, "Podcast", "Hören", this::showPodcast); break;
            case "community":
                if (!appConfig.hasCommunity()) return null;
                r = new Tile(R.drawable.ic_chat, "Mitmachen", "Voting & Wünsche", this::showCommunity); break;
            case "news": r = new Tile(R.drawable.ic_news, "News", "Magazin", this::showNews); break;
            case "shops":
                if (!appConfig.hasShops()) return null;
                r = new Tile(R.drawable.ic_shop, "Shops", "Online-Shops", this::showFanshops); break;
            case "help": r = new Tile(R.drawable.ic_help, "Hilfe", "Bedienung", this::showHelp); break;
            case "assistant":
                if (!(prefs.getBoolean("assistant_enabled", true) && prefs.getBoolean("app_f_assistant", true))) return null;
                r = new Tile(R.drawable.ic_sparkle, "KI-Assistent", "Fragen & Sendeplan", this::showAssistant); break;
            case "directory":
                if (!hasDirectory()) return null;
                r = new Tile(R.drawable.ic_globe, "Verzeichnis", "Sender suchen", this::showStationSearch); break;
            case "link":
                if (url.isEmpty()) return null;
                r = new Tile(R.drawable.ic_open, "Link", "", () -> openInternalWeb(custom.isEmpty() ? brandName() : custom, url)); break;
            default: return null;
        }
        String title = custom.isEmpty() ? r.title : custom;
        String sub = tl.optString("sub").isEmpty() ? r.sub : tl.optString("sub");
        return new Tile(r.icon, title, sub, r.action);
    }

    // ---------- Bausteine der Startseite ----------

    /** Waagerechte Leiste mit Schnellzugriffen. */
    private void addQuickRail() {
        List<Tile> tiles = new ArrayList<>();
        tiles.add(new Tile(R.drawable.ic_shuffle, "Überrasch mich", "Zufälliger Sender", this::playSurprise));
        if (hasDirectory()) tiles.add(new Tile(R.drawable.ic_globe, "Verzeichnis", "Sender suchen", this::showStationSearch));
        int favCount = getFavorites().size() + dirFavs().size();
        tiles.add(new Tile(R.drawable.ic_heart, "Favoriten", favCount > 0 ? favCount + " gespeichert" : "Deine Sender", this::showFavorites));
        tiles.add(new Tile(R.drawable.ic_calendar, "Sendeplan", "Heute", () -> showSchedule(currentStationId)));
        if (appConfig.podcast) tiles.add(new Tile(R.drawable.ic_podcast, "Podcast", "Hören", this::showPodcast));
        if (appConfig.hasCommunity()) tiles.add(new Tile(R.drawable.ic_chat, "Mitmachen", "Voting & Wünsche", this::showCommunity));
        tiles.add(new Tile(R.drawable.ic_news, "News", "Magazin", this::showNews));
        int saved = savedSongs().size();
        tiles.add(new Tile(R.drawable.ic_bookmark, "Gemerkt", saved > 0 ? saved + " Songs" : "Songs merken", this::showSavedSongs));
        if (appConfig.hasShops() && !menuHidden("shops")) tiles.add(new Tile(R.drawable.ic_shop, "Shops", "Online-Shops", this::showFanshops));

        addSection("Schnellzugriff", null, null);
        HorizontalScrollView rail = railInContent();
        LinearLayout inner = Ui.railInner(rail);
        for (Tile t : tiles) {
            inner.addView(tileView(t, true), Ui.lp(dp(142), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 10, 0));
        }
    }

    /** Eine Leiste, die bis an den Bildschirmrand reicht (hebt den Seitenrand des Inhalts auf). */
    private HorizontalScrollView railInContent() {
        HorizontalScrollView rail = Ui.rail(this, 16);
        LinearLayout.LayoutParams lp = Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 0);
        lp.leftMargin = -dp(16);
        lp.rightMargin = -dp(16);
        content.addView(rail, lp);
        return rail;
    }

    private void addStationRail(String title, List<String> ids, String actionLabel, Runnable action) {
        if (ids == null || ids.isEmpty()) return;
        addSection(title, actionLabel, action);
        HorizontalScrollView rail = railInContent();
        LinearLayout inner = Ui.railInner(rail);
        for (String id : ids) {
            inner.addView(stationCard(id, 148), Ui.lp(dp(148), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
        }
    }

    private void addFavoritesRail() {
        List<String> favs = new ArrayList<>(getFavorites());
        List<Directory.Item> world = dirFavs();
        if (favs.isEmpty() && world.isEmpty()) return;
        Collections.sort(favs, String.CASE_INSENSITIVE_ORDER);
        addSection("Deine Favoriten", "Alle", this::showFavorites);
        HorizontalScrollView rail = railInContent();
        LinearLayout inner = Ui.railInner(rail);
        for (String id : favs) inner.addView(stationCard(id, 132), Ui.lp(dp(132), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
        for (Directory.Item it : world) inner.addView(worldCard(it, 132), Ui.lp(dp(132), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
    }

    private void addRecentRail() {
        JSONArray recents = recentList();
        if (recents.length() == 0) return;
        addSection("Zuletzt gehört", null, null);
        HorizontalScrollView rail = railInContent();
        LinearLayout inner = Ui.railInner(rail);
        for (int i = 0; i < recents.length() && i < 10; i++) {
            JSONObject o = recents.optJSONObject(i);
            if (o == null) continue;
            View card;
            if (o.has("item")) {
                try { card = worldCard(new Directory.Item(o.getJSONObject("item")), 112); } catch (Exception e) { continue; }
            } else {
                card = stationCard(o.optString("id"), 112);
            }
            inner.addView(card, Ui.lp(dp(112), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
        }
    }

    /** Entdecken-Leiste für Marken mit Radioverzeichnis (Vorschläge vom Server). */
    private void addDiscoverRail() {
        addSection("Zum Entdecken", "Verzeichnis", this::showStationSearch);
        HorizontalScrollView rail = railInContent();
        final LinearLayout inner = Ui.railInner(rail);
        for (int i = 0; i < 4; i++) {
            LinearLayout sk = Ui.vbox(this);
            sk.addView(Ui.skeleton(this, 132, 132, 20));
            sk.addView(Ui.skeleton(this, 90, 12, 6), Ui.lp(dp(90), dp(12), 0, 10, 0, 0));
            inner.addView(sk, Ui.lp(dp(132), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
        }
        io.execute(() -> {
            Directory.Page page = null;
            try { page = Directory.random(siteBase(), "mix", 8); } catch (Exception ignored) { }
            final Directory.Page p = page;
            main.post(() -> {
                if (!"home".equals(currentScreen) || !inner.isAttachedToWindow()) return;
                inner.removeAllViews();
                if (p == null || p.items.isEmpty()) return;
                dirReportOn = p.reportOn;
                dirShown.clear();
                dirShown.addAll(p.items);
                for (Directory.Item it : p.items) {
                    inner.addView(worldCard(it, 132), Ui.lp(dp(132), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
                }
            });
        });
    }

    /** Nächste Nachrichten (aus dem Zwischenspeicher oder dem Netz), als Leiste mit Bildern. */
    private void addNewsRail() {
        final LinearLayout slot = Ui.vbox(this);
        addToContent(slot, 0, 0);
        loadNews((list, res) -> {
            if (!"home".equals(currentScreen) || !slot.isAttachedToWindow() || list == null || list.length() == 0) return;
            slot.addView(Ui.sectionHeader(this, "News & Magazin", "Alle", this::showNews), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 22, 0, 0));
            HorizontalScrollView rail = Ui.rail(this, 16);
            LinearLayout.LayoutParams rlp = Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 0);
            rlp.leftMargin = -dp(16);
            rlp.rightMargin = -dp(16);
            slot.addView(rail, rlp);
            LinearLayout inner = Ui.railInner(rail);
            for (int i = 0; i < list.length() && i < 6; i++) {
                JSONObject a = list.optJSONObject(i);
                if (a == null) continue;
                inner.addView(newsCard(a), Ui.lp(dp(250), ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 12, 0));
            }
        });
    }

    private View newsCard(JSONObject a) {
        String title = a.optString("title", "");
        String category = a.optString("category", "News");
        String image = a.optString("image_url", "");
        String date = newsDate(a.optString("published_at", a.optString("created_at", "")));
        LinearLayout card = Ui.vbox(this);
        card.setBackground(Ui.surface(20));
        card.setClickable(true);
        card.setOnClickListener(v -> showNewsArticle(a));
        FrameLayout imgWrap = new FrameLayout(this);
        ImageView img = Ui.cover(this, 0);
        img.setImageResource(R.drawable.app_logo);
        img.setScaleType(image.trim().isEmpty() ? ImageView.ScaleType.CENTER_INSIDE : ImageView.ScaleType.CENTER_CROP);
        if (image.trim().isEmpty()) img.setPadding(dp(40), dp(24), dp(40), dp(24));
        else loadImage(image, img);
        imgWrap.addView(img, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(124)));
        card.addView(imgWrap, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(124)));
        // obere Ecken abrunden
        card.setClipToOutline(true);
        card.setOutlineProvider(new android.view.ViewOutlineProvider() {
            @Override public void getOutline(View view, android.graphics.Outline outline) {
                outline.setRoundRect(0, 0, view.getWidth(), view.getHeight(), dp(20));
            }
        });
        LinearLayout body = Ui.vbox(this);
        body.setPadding(dp(14), dp(12), dp(14), dp(14));
        body.addView(Ui.eyebrow(this, category + (date.isEmpty() ? "" : " · " + date), Ui.accent));
        TextView t = Ui.text(this, title, 15, TEXT, Ui.BOLD);
        Ui.ellipsize(t, 3);
        t.setPadding(0, dp(6), 0, 0);
        body.addView(t);
        card.addView(body);
        return card;
    }

    /** Neueste Podcast-Folge als große Karte mit Wiedergabeknopf. */
    private void addPodcastTeaser() {
        final LinearLayout slot = Ui.vbox(this);
        addToContent(slot, 0, 0);
        loadPodcast((episodes, res) -> {
            if (!"home".equals(currentScreen) || !slot.isAttachedToWindow() || episodes == null || episodes.length() == 0) return;
            JSONObject ep = episodes.optJSONObject(0);
            if (ep == null) return;
            slot.addView(Ui.sectionHeader(this, "Neu im Podcast", "Alle", this::showPodcast), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 22, 0, 0));
            slot.addView(podcastRow(ep, true), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 0));
        });
    }

    // ---------- Sender-Karten ----------

    private final Map<String, Object[]> nowCache = new HashMap<>();

    /** Aktueller Titel eines Senders ("Interpret – Titel"), 60 Sekunden zwischengespeichert. */
    private void loadNowLine(String stationId, TextView target) {
        Object[] c;
        synchronized (nowCache) { c = nowCache.get(stationId); }
        if (c != null && System.currentTimeMillis() - (Long) c[1] < 60000) {
            target.setText((String) c[0]);
            return;
        }
        io.execute(() -> {
            try {
                JSONObject song = getJson(lautApi(stationId) + "/current_song");
                String title = cleanDisplayText(song.optString("title", ""));
                String artist = cleanDisplayText(extractArtistName(song.opt("artist")));
                final String line = title.isEmpty() ? "Live-Stream" : (artist.isEmpty() ? title : artist + " – " + title);
                synchronized (nowCache) { nowCache.put(stationId, new Object[]{line, System.currentTimeMillis()}); }
                main.post(() -> target.setText(line));
            } catch (Exception ignored) {
                main.post(() -> target.setText("Live-Stream"));
            }
        });
    }

    private View stationCard(String stationId, int widthDp) {
        LinearLayout card = Ui.vbox(this);
        card.setClickable(true);
        card.setFocusable(true);

        FrameLayout wrap = new FrameLayout(this);
        ImageView cover = Ui.cover(this, 20);
        cover.setImageResource(R.drawable.app_logo);
        wrap.addView(cover, new FrameLayout.LayoutParams(dp(widthDp), dp(widthDp)));
        wrap.addView(coverLiveBadge(dp(widthDp)));
        ImageButton play = Ui.iconButton(this, R.drawable.ic_play, Color.WHITE, Ui.alpha(Color.BLACK, 150), 34, "Abspielen");
        FrameLayout.LayoutParams plp = new FrameLayout.LayoutParams(dp(34), dp(34), Gravity.TOP | Gravity.END);
        plp.setMargins(0, dp(6), dp(6), 0);
        wrap.addView(play, plp);
        card.addView(wrap, new LinearLayout.LayoutParams(dp(widthDp), dp(widthDp)));

        TextView name = Ui.text(this, prettyName(stationId), 14, TEXT, Ui.BOLD);
        name.setSingleLine(true);
        name.setEllipsize(TextUtils.TruncateAt.END);
        card.addView(name, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 2, 10, 2, 0));
        TextView now = Ui.text(this, "", 12, MUTED, Ui.REGULAR);
        now.setSingleLine(true);
        now.setEllipsize(TextUtils.TruncateAt.END);
        card.addView(now, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 2, 3, 2, 0));

        card.setOnClickListener(v -> showStationDetail(stationId));
        play.setOnClickListener(v -> {
            haptic(v);
            selectStation(stationId, name.getText().toString(), true);
        });
        loadStationInfo(stationId, info -> {
            name.setText(info.name);
            if (!info.cover.isEmpty()) loadImage(info.cover, cover);
        });
        loadNowLine(stationId, now);
        return card;
    }

    /** Karte für einen Sender aus dem Radioverzeichnis (laut.fm oder Fremd-Stream). */
    private View worldCard(Directory.Item it, int widthDp) {
        LinearLayout card = Ui.vbox(this);
        card.setClickable(true);
        FrameLayout wrap = new FrameLayout(this);
        ImageView cover = Ui.cover(this, 20);
        cover.setImageResource(R.drawable.app_logo);
        if (!it.cover.isEmpty()) loadImage(it.cover, cover);
        wrap.addView(cover, new FrameLayout.LayoutParams(dp(widthDp), dp(widthDp)));
        ImageButton play = Ui.iconButton(this, it.playable() ? R.drawable.ic_play : R.drawable.ic_open, Color.WHITE, Ui.alpha(Color.BLACK, 150), 34, "Abspielen");
        FrameLayout.LayoutParams plp = new FrameLayout.LayoutParams(dp(34), dp(34), Gravity.TOP | Gravity.END);
        plp.setMargins(0, dp(6), dp(6), 0);
        wrap.addView(play, plp);
        card.addView(wrap, new LinearLayout.LayoutParams(dp(widthDp), dp(widthDp)));
        TextView name = Ui.text(this, it.name, 14, TEXT, Ui.BOLD);
        name.setSingleLine(true);
        name.setEllipsize(TextUtils.TruncateAt.END);
        card.addView(name, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 2, 10, 2, 0));
        String sub = it.sub();
        if (sub.isEmpty()) sub = it.isWorld() ? "Fremd-Stream" : "laut.fm";
        TextView s = Ui.text(this, sub, 12, MUTED, Ui.REGULAR);
        s.setSingleLine(true);
        s.setEllipsize(TextUtils.TruncateAt.END);
        card.addView(s, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 2, 3, 2, 0));
        View.OnClickListener go = v -> {
            haptic(v);
            playDirectoryItem(it, dirShown.isEmpty() ? java.util.Collections.singletonList(it) : dirShown);
        };
        play.setOnClickListener(go);
        card.setOnClickListener(go);
        return card;
    }

    // ---------- Zuletzt gehört ----------

    private JSONArray recentList() {
        try {
            return new JSONArray(prefs.getString("recent_list", "[]"));
        } catch (Exception e) {
            return new JSONArray();
        }
    }

    private void addRecent(String id, String name, Directory.Item world) {
        try {
            JSONArray old = recentList();
            JSONArray out = new JSONArray();
            JSONObject mine = new JSONObject().put("id", id).put("name", name);
            if (world != null) mine.put("item", world.toJson());
            out.put(mine);
            for (int i = 0; i < old.length() && out.length() < 12; i++) {
                JSONObject o = old.optJSONObject(i);
                if (o != null && !id.equals(o.optString("id"))) out.put(o);
            }
            prefs.edit().putString("recent_list", out.toString()).apply();
        } catch (Exception ignored) {
        }
    }

    // ================================================================ Sender, Favoriten, Senderdetail, Suche, Verzeichnis

    private int screenToken = 0;

    private int contentWidthPx() {
        int maxW = contentMaxWidthPx();
        int w = maxW > 0 ? maxW : getResources().getDisplayMetrics().widthPixels - insetLeft - insetRight;
        return w - dp(32);
    }

    private void showOwnedStations() {
        beginScreen("owned", "owned", "Sender", null);
        final int token = ++screenToken;
        activeStationQueue.clear();
        activeStationQueue.addAll(ownedStations());
        addPageHeading(brandName(), "Sender");

        final HorizontalScrollView chipScroll = new HorizontalScrollView(this);
        chipScroll.setHorizontalScrollBarEnabled(false);
        chipScroll.setVisibility(View.GONE);
        final LinearLayout chipRow = Ui.hbox(this);
        chipScroll.addView(chipRow);
        addToContent(chipScroll, 0, 12);

        final LinearLayout grid = Ui.vbox(this);
        addToContent(grid, 0, 0);
        final List<String> ids = ownedStations();
        renderStationGrid(grid, ids);

        // Genre-Filter, sobald die Sender-Infos geladen sind
        final int[] loaded = {0};
        final Map<String, Integer> genreCount = new HashMap<>();
        final Map<String, List<String>> byGenre = new HashMap<>();
        for (String id : ids) {
            loadStationInfo(id, info -> {
                loaded[0]++;
                for (String g : info.genres) {
                    String key = g.trim();
                    if (key.isEmpty()) continue;
                    genreCount.put(key, genreCount.containsKey(key) ? genreCount.get(key) + 1 : 1);
                    if (!byGenre.containsKey(key)) byGenre.put(key, new ArrayList<>());
                    byGenre.get(key).add(id);
                }
                if (loaded[0] < ids.size() || token != screenToken) return;
                List<String> genres = new ArrayList<>(genreCount.keySet());
                Collections.sort(genres, (a, b) -> {
                    int c = Integer.compare(genreCount.get(b), genreCount.get(a));
                    return c != 0 ? c : a.compareToIgnoreCase(b);
                });
                if (genres.size() < 2) return;
                final List<TextView> chips = new ArrayList<>();
                final List<String> keys = new ArrayList<>();
                keys.add("");
                TextView all = Ui.chip(this, "Alle", true);
                chips.add(all);
                chipRow.addView(all, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(34), 0, 0, 8, 0));
                for (int i = 0; i < genres.size() && i < 9; i++) {
                    TextView c = Ui.chip(this, genres.get(i), false);
                    chips.add(c);
                    keys.add(genres.get(i));
                    chipRow.addView(c, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(34), 0, 0, 8, 0));
                }
                for (int i = 0; i < chips.size(); i++) {
                    final int idx = i;
                    chips.get(i).setOnClickListener(v -> {
                        for (int n = 0; n < chips.size(); n++) Ui.setChipSelected(chips.get(n), n == idx);
                        String key = keys.get(idx);
                        List<String> subset = key.isEmpty() ? ids : byGenre.get(key);
                        activeStationQueue.clear();
                        activeStationQueue.addAll(subset);
                        renderStationGrid(grid, subset);
                    });
                }
                chipScroll.setVisibility(View.VISIBLE);
            });
        }
    }

    /** Sender als Kacheln in Spalten (2 auf dem Handy, mehr auf Tablets). */
    private void renderStationGrid(LinearLayout host, List<String> ids) {
        host.removeAllViews();
        int cols = gridColumns();
        int gap = dp(12);
        int cardW = (contentWidthPx() - gap * (cols - 1)) / cols;
        LinearLayout row = null;
        for (int i = 0; i < ids.size(); i++) {
            if (i % cols == 0) {
                row = new LinearLayout(this);
                row.setOrientation(LinearLayout.HORIZONTAL);
                host.addView(row, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, i == 0 ? 0 : 16, 0, 0));
            }
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(cardW, ViewGroup.LayoutParams.WRAP_CONTENT);
            if (i % cols != 0) lp.leftMargin = gap;
            row.addView(gridStationCard(ids.get(i), cardW), lp);
        }
    }

    /** Kleines LIVE-Zeichen oben links im Senderbild. */
    private View coverLiveBadge(int coverPx) {
        TextView live = Ui.liveBadge(this, true);
        FrameLayout.LayoutParams lp = new FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.TOP | Gravity.START);
        lp.leftMargin = Math.round(coverPx * 0.27f);
        lp.topMargin = Math.max(dp(3), Math.round(coverPx * 0.02f));
        live.setLayoutParams(lp);
        return live;
    }

    private View gridStationCard(String stationId, int cardW) {
        LinearLayout card = Ui.vbox(this);
        card.setClickable(true);
        card.setFocusable(true);
        FrameLayout wrap = new FrameLayout(this);
        ImageView cover = Ui.cover(this, 22);
        cover.setImageResource(R.drawable.app_logo);
        wrap.addView(cover, new FrameLayout.LayoutParams(cardW, cardW));
        wrap.addView(coverLiveBadge(cardW));

        final ImageButton heart = Ui.iconButton(this, getFavorites().contains(stationId) ? R.drawable.ic_heart : R.drawable.ic_heart_outline,
                getFavorites().contains(stationId) ? Ui.accent : Color.WHITE, Ui.alpha(Color.BLACK, 120), 36, "Favorit");
        FrameLayout.LayoutParams hlp = new FrameLayout.LayoutParams(dp(36), dp(36), Gravity.TOP | Gravity.END);
        hlp.setMargins(0, dp(8), dp(8), 0);
        wrap.addView(heart, hlp);
        heart.setOnClickListener(v -> {
            haptic(v);
            toggleFavorite(stationId);
            boolean fav = getFavorites().contains(stationId);
            heart.setImageResource(fav ? R.drawable.ic_heart : R.drawable.ic_heart_outline);
            heart.setColorFilter(fav ? Ui.accent : Color.WHITE);
        });

        card.addView(wrap, new LinearLayout.LayoutParams(cardW, cardW));

        // Text links, Wiedergabe-Knopf rechts daneben (verdeckt so nichts im Titelbild)
        LinearLayout infoRow = Ui.hbox(this);
        LinearLayout texts = Ui.vbox(this);
        final TextView name = Ui.text(this, prettyName(stationId), 15, TEXT, Ui.BOLD);
        name.setSingleLine(true);
        name.setEllipsize(TextUtils.TruncateAt.END);
        texts.addView(name);
        final TextView genre = Ui.text(this, "", 11.5f, Ui.accent, Ui.MEDIUM);
        genre.setSingleLine(true);
        genre.setEllipsize(TextUtils.TruncateAt.END);
        texts.addView(genre, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        final TextView now = Ui.text(this, "", 12, MUTED, Ui.REGULAR);
        now.setSingleLine(true);
        now.setEllipsize(TextUtils.TruncateAt.END);
        texts.addView(now, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        infoRow.addView(texts, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        ImageButton play = Ui.iconButton(this, R.drawable.ic_play, Ui.ON_ACCENT, Ui.accent, 40, "Abspielen");
        play.setBackground(Ui.ripple(accentOval(), 40));
        infoRow.addView(play, Ui.lp(dp(40), dp(40), 6, 0, 0, 0));
        card.addView(infoRow, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 2, 10, 0, 0));

        card.setOnClickListener(v -> showStationDetail(stationId));
        play.setOnClickListener(v -> {
            haptic(v);
            selectStation(stationId, name.getText().toString(), true);
        });
        loadStationInfo(stationId, info -> {
            name.setText(info.name);
            if (!info.genres.isEmpty()) genre.setText(info.genres.get(0));
            if (!info.cover.isEmpty()) loadImage(info.cover, cover);
        });
        loadNowLine(stationId, now);
        return card;
    }

    private void showFavorites() {
        beginScreen("favorites", "owned", "Favoriten", this::showOwnedStations);
        Set<String> favSet = getFavorites();
        List<String> favs = new ArrayList<>(favSet);
        Collections.sort(favs, String.CASE_INSENSITIVE_ORDER);
        activeStationQueue.clear();
        activeStationQueue.addAll(favs);
        List<Directory.Item> worldFavs = dirFavs();
        addPageHeading("Deine Favoriten", "Gespeicherte Sender");
        if (favs.isEmpty() && worldFavs.isEmpty()) {
            addEmpty("Noch keine Sender als Favorit gespeichert. Tippe bei einem Sender auf das Herz.");
            View go = Ui.button(this, "Sender entdecken", R.drawable.ic_radio, Ui.PRIMARY);
            go.setOnClickListener(v -> showOwnedStations());
            addToContent(go, 14, 0);
            return;
        }
        if (!favs.isEmpty()) {
            final LinearLayout grid = Ui.vbox(this);
            addToContent(grid, 0, 0);
            renderStationGrid(grid, favs);
        }
        if (!worldFavs.isEmpty()) {
            addSection("Aus dem Radioverzeichnis", null, null);
            dirQueue.clear();
            dirQueue.addAll(worldFavs);
            for (Directory.Item it : worldFavs) addDirectoryRow(content, it, worldFavs);
        }
    }

    private void showStationDetail(String stationId) {
        beginScreen("station-detail", "owned", "", this::showOwnedStations);
        final int token = ++screenToken;
        activeStationQueue.clear();
        activeStationQueue.addAll(ownedStations());
        final LinearLayout slot = Ui.vbox(this);
        addToContent(slot, 0, 0);

        loadStationInfo(stationId, info -> {
            if (token != screenToken) return;
            screenTitle.setText(info.name);
            slot.removeAllViews();

            // Kopf mit Farbverlauf aus dem Titelbild
            final FrameLayout hero = new FrameLayout(this);
            hero.setBackground(Ui.gradient(Ui.mix(Ui.heroFrom, Ui.heroTo, 0.4f), Ui.BG, 28, GradientDrawable.Orientation.TOP_BOTTOM));
            LinearLayout inner = Ui.vbox(this);
            inner.setGravity(Gravity.CENTER_HORIZONTAL);
            inner.setPadding(dp(18), dp(22), dp(18), dp(18));
            int artW = Math.min(contentWidthPx() - dp(90), dp(250));
            FrameLayout artWrap = new FrameLayout(this);
            ImageView art = Ui.cover(this, 28);
            art.setImageResource(R.drawable.app_logo);
            art.setElevation(dp(14));
            artWrap.addView(art, new FrameLayout.LayoutParams(artW, artW));
            if (!info.cover.isEmpty()) {
                loadImage(info.cover, art, bmp -> {
                    if (token == screenToken) hero.setBackground(Ui.gradient(Ui.dominantColor(bmp, Ui.heroFrom), Ui.BG, 28, GradientDrawable.Orientation.TOP_BOTTOM));
                });
            }
            inner.addView(artWrap, new LinearLayout.LayoutParams(artW, artW));

            TextView title = Ui.text(this, info.name, 26, Color.WHITE, Ui.BOLD);
            title.setGravity(Gravity.CENTER);
            inner.addView(title, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 18, 0, 0));

            LinearLayout meta = Ui.hbox(this);
            meta.setGravity(Gravity.CENTER);
            meta.addView(Ui.liveBadge(this), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 8, 0));
            for (int i = 0; i < info.genres.size() && i < 3; i++) {
                TextView g = Ui.text(this, info.genres.get(i), 12, Ui.TEXT_2, Ui.MEDIUM);
                g.setPadding(dp(10), dp(4), dp(10), dp(4));
                g.setBackground(Ui.rect(Ui.alpha(Color.WHITE, 22), 12, 0, 0));
                meta.addView(g, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 6, 0));
            }
            inner.addView(meta, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 10, 0, 0));

            LinearLayout actions = Ui.hbox(this);
            actions.setGravity(Gravity.CENTER);
            View listen = Ui.button(this, "Jetzt hören", R.drawable.ic_play, Ui.PRIMARY);
            listen.setOnClickListener(v -> {
                haptic(v);
                selectStation(stationId, info.name, true);
            });
            actions.addView(listen, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(48), 0, 0, 10, 0));
            final boolean fav = getFavorites().contains(stationId);
            final ImageButton heart = Ui.iconButton(this, fav ? R.drawable.ic_heart : R.drawable.ic_heart_outline, fav ? Ui.accent : TEXT, Ui.alpha(Color.WHITE, 28), 48, "Favorit");
            heart.setOnClickListener(v -> {
                haptic(v);
                toggleFavorite(stationId);
                boolean now = getFavorites().contains(stationId);
                heart.setImageResource(now ? R.drawable.ic_heart : R.drawable.ic_heart_outline);
                heart.setColorFilter(now ? Ui.accent : TEXT);
            });
            actions.addView(heart, Ui.lp(dp(48), dp(48), 0, 0, 10, 0));
            ImageButton plan = Ui.iconButton(this, R.drawable.ic_calendar, TEXT, Ui.alpha(Color.WHITE, 28), 48, "Sendeplan");
            plan.setOnClickListener(v -> {
                currentStationId = stationId;
                currentStationName = info.name;
                showSchedule(stationId);
            });
            actions.addView(plan);
            inner.addView(actions, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 18, 0, 0));
            hero.addView(inner, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            slot.addView(hero, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

            // Läuft gerade
            LinearLayout now = Ui.card(this);
            now.addView(Ui.eyebrow(this, "Läuft gerade", Ui.CYAN));
            TextView line = Ui.text(this, "Wird geladen …", 16, TEXT, Ui.BOLD);
            Ui.ellipsize(line, 2);
            now.addView(line, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 6, 0, 0));
            slot.addView(now, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 14, 0, 0));
            loadNowLine(stationId, line);

            // Mitmachen (nur eigene Sender, nur wenn die Website Community-Seiten bereitstellt)
            if (appConfig.hasCommunity() && appConfig.isOwned(stationId)) {
                TextView h = Ui.text(this, "Mitmachen", 18, TEXT, Ui.BOLD);
                slot.addView(h, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 22, 0, 10));
                String base = appConfig.communityBase;
                List<Tile> tiles = new ArrayList<>();
                tiles.add(new Tile(R.drawable.ic_equalizer, "Song-Voting", "Für diesen Sender", () -> openInternalWeb("Sender-Song-Voting",
                        base + "voting.html?mode=station&station=" + Uri.encode(stationId))));
                tiles.add(new Tile(R.drawable.ic_music, "Musikwunsch", "Song wünschen", () -> openInternalWeb("Musikwunsch",
                        base + "wunsch.html?station=" + Uri.encode(stationId))));
                tiles.add(new Tile(R.drawable.ic_chat, "Studiomail", "Gruß & Feedback", () ->
                        openInternalWeb("Studiomail", base + "studiomail/widget-form.html")));
                tiles.add(new Tile(R.drawable.ic_mic, "Voicemail", "Sprachnachricht", () ->
                        openInternalWeb("Voicemail", base + "voicemsg.html?station=" + Uri.encode(stationId), true)));
                LinearLayout grid = Ui.vbox(this);
                slot.addView(grid, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
                for (int i = 0; i < tiles.size(); i += 2) {
                    LinearLayout r = new LinearLayout(this);
                    r.setOrientation(LinearLayout.HORIZONTAL);
                    LinearLayout.LayoutParams a = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
                    r.addView(tileView(tiles.get(i), false), a);
                    LinearLayout.LayoutParams b = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
                    b.leftMargin = dp(10);
                    if (i + 1 < tiles.size()) r.addView(tileView(tiles.get(i + 1), false), b);
                    grid.addView(r, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, i == 0 ? 0 : 10, 0, 0));
                }
            }

            // Über den Sender
            TextView h2 = Ui.text(this, "Über den Sender", 18, TEXT, Ui.BOLD);
            slot.addView(h2, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 22, 0, 10));
            LinearLayout about = Ui.card(this);
            TextView desc = Ui.text(this, info.description, 14, Ui.TEXT_2, Ui.REGULAR);
            desc.setLineSpacing(0, 1.25f);
            about.addView(desc);
            slot.addView(about, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

            // Zuletzt gespielt
            final LinearLayout hist = Ui.vbox(this);
            slot.addView(hist, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            io.execute(() -> {
                try {
                    JSONArray history = getJsonArray(lautApi(stationId) + "/last_songs");
                    final List<String[]> rows = new ArrayList<>();
                    for (int i = 0; i < history.length() && rows.size() < 6; i++) {
                        JSONObject song = history.optJSONObject(i);
                        if (song == null) continue;
                        String t = cleanDisplayText(song.optString("title", ""));
                        String a = cleanDisplayText(extractArtistName(song.opt("artist")));
                        if (!t.isEmpty() || !a.isEmpty()) rows.add(new String[]{a, t});
                    }
                    main.post(() -> {
                        if (token != screenToken || rows.isEmpty()) return;
                        hist.addView(Ui.text(this, "Zuletzt gespielt", 18, TEXT, Ui.BOLD), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 22, 0, 10));
                        LinearLayout list = Ui.card(this);
                        for (String[] r : rows) list.addView(songLine(r[0], r[1], info.name, false));
                        hist.addView(list);
                    });
                } catch (Exception ignored) {
                }
            });
        });
    }

    // ---------- Sendersuche ----------

    private void showStationSearch() {
        if (hasDirectory()) {
            showDirectory();
            return;
        }
        showSearchOnly();
    }

    /** Suchfeld im neuen Stil: Lupe, Eingabe, Löschen. Gibt das Eingabefeld zurück. */
    private EditText addSearchField(String hint, Runnable onSubmit, java.util.function.Consumer<String> onText) {
        LinearLayout shell = Ui.hbox(this);
        shell.setPadding(dp(16), 0, dp(6), 0);
        shell.setBackground(Ui.rect(CARD, 28, LINE, 1));
        shell.addView(Ui.icon(this, R.drawable.ic_search, MUTED, 22));
        final EditText input = new EditText(this);
        input.setHint(hint);
        input.setHintTextColor(MUTED);
        input.setTextColor(TEXT);
        input.setTextSize(16);
        input.setSingleLine(true);
        input.setImeOptions(android.view.inputmethod.EditorInfo.IME_ACTION_SEARCH);
        input.setBackgroundColor(Color.TRANSPARENT);
        input.setPadding(dp(12), 0, dp(8), 0);
        shell.addView(input, new LinearLayout.LayoutParams(0, dp(54), 1f));
        final ImageButton clear = Ui.iconButton(this, R.drawable.ic_close, MUTED, Color.TRANSPARENT, 40, "Löschen");
        clear.setVisibility(View.GONE);
        clear.setOnClickListener(v -> input.setText(""));
        shell.addView(clear);
        input.addTextChangedListener(new android.text.TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void onTextChanged(CharSequence s, int a, int b, int c) {
                clear.setVisibility(s.length() > 0 ? View.VISIBLE : View.GONE);
                if (onText != null) onText.accept(s.toString());
            }
            @Override public void afterTextChanged(android.text.Editable s) { }
        });
        input.setOnEditorActionListener((v, actionId, event) -> {
            onSubmit.run();
            return true;
        });
        addToContent(shell, 4, 0);
        return input;
    }

    private void showSearchOnly() {
        beginScreen("search", "owned", "Sender suchen", null);
        final int token = ++screenToken;
        addPageHeading("Sender suchen", "Unsere Sender");

        final LinearLayout results = Ui.vbox(this);
        final EditText[] inputRef = new EditText[1];
        final Runnable perform = () -> {
            String q = inputRef[0].getText().toString().trim().toLowerCase(Locale.ROOT);
            if (q.length() < 2) {
                Toast.makeText(this, "Mindestens 2 Zeichen eingeben.", Toast.LENGTH_SHORT).show();
                return;
            }
            results.removeAllViews();
            results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
            results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
            main.post(() -> {
                if (token != screenToken) return;
                List<String> names = Stations.owned(this, prefs);
                results.removeAllViews();
                int shown = 0;
                List<String> matched = new ArrayList<>();
                for (String id : names) {
                    if (!id.toLowerCase(Locale.ROOT).contains(q) && !prettyName(id).toLowerCase(Locale.ROOT).contains(q)) continue;
                    matched.add(id);
                    addSearchStationResult(results, id, shown < 24);
                    shown++;
                    if (shown >= 40) break;
                }
                activeStationQueue.clear();
                activeStationQueue.addAll(matched);
                if (shown == 0) {
                    TextView none = Ui.text(this, "Kein Sender gefunden.", 14, MUTED, Ui.REGULAR);
                    none.setGravity(Gravity.CENTER);
                    none.setPadding(0, dp(24), 0, dp(24));
                    results.addView(none);
                }
            });
        };
        inputRef[0] = addSearchField("Sendername …", perform, null);

        HorizontalScrollView sug = new HorizontalScrollView(this);
        sug.setHorizontalScrollBarEnabled(false);
        LinearLayout sugRow = Ui.hbox(this);
        sug.addView(sugRow);
        for (String id : ownedStations()) {
            final String s = prettyName(id);
            TextView c = Ui.chip(this, s, false);
            c.setOnClickListener(v -> {
                inputRef[0].setText(s);
                inputRef[0].setSelection(s.length());
                perform.run();
            });
            sugRow.addView(c, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(34), 0, 0, 8, 0));
        }
        addToContent(sug, 12, 0);

        addToContent(results, 16, 0);
        TextView tip = Ui.text(this, "Tipp: Suche nach dem Namen eines unserer Sender.", 12.5f, MUTED, Ui.REGULAR);
        tip.setLineSpacing(0, 1.25f);
        results.addView(tip);
    }

    private void addSearchStationResult(LinearLayout host, String id, boolean withInfo) {
        LinearLayout row = Ui.hbox(this);
        row.setPadding(dp(10), dp(9), dp(10), dp(9));
        row.setBackground(Ui.surface(18));
        row.setClickable(true);

        final ImageView cover = Ui.cover(this, 14);
        cover.setImageResource(R.drawable.app_logo);
        row.addView(cover, Ui.lp(dp(48), dp(48), 0, 0, 12, 0));

        LinearLayout col = Ui.vbox(this);
        final TextView name = Ui.text(this, prettyName(id), 15, TEXT, Ui.BOLD);
        name.setSingleLine(true);
        name.setEllipsize(TextUtils.TruncateAt.END);
        col.addView(name);
        final TextView sub = Ui.text(this, appConfig.isOwned(id) ? "Unser Sender" : "laut.fm", 12, appConfig.isOwned(id) ? Ui.CYAN : MUTED, Ui.MEDIUM);
        col.addView(sub, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        row.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        ImageButton play = Ui.iconButton(this, R.drawable.ic_play, Ui.ON_ACCENT, Ui.accent, 42, "Abspielen");
        play.setBackground(Ui.ripple(accentOval(), 42));
        play.setOnClickListener(v -> {
            haptic(v);
            selectStation(id, name.getText().toString(), true);
        });
        row.addView(play);
        row.setOnClickListener(v -> {
            selectStation(id, name.getText().toString(), false);
            showFullPlayer();
        });
        host.addView(row, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 9));
        if (withInfo) {
            loadStationInfo(id, info -> {
                name.setText(info.name);
                if (!info.genres.isEmpty()) sub.setText(sub.getText() + " · " + info.genres.get(0));
                if (!info.cover.isEmpty()) loadImage(info.cover, cover);
            });
        }
    }

    // ---------- Radioverzeichnis (nur Marken mit Verzeichnis) ----------

    private void showDirectory() {
        beginScreen("directory", "owned", "Radioverzeichnis", null);
        final int token = ++screenToken;
        dirShown.clear();
        dirOffset = 0;
        dirQuery = "";
        addPageHeading("Radioverzeichnis", "Entdecken");

        TextView info = Ui.text(this, "Unsere eigenen Sender, öffentliche laut.fm-Sender und Webradios aus aller Welt (World Radio). "
                + "Fremde Sender gehören ihren Betreibern und werden direkt von dort gestreamt – nicht von " + brandName() + ".",
                12.5f, MUTED, Ui.REGULAR);
        info.setLineSpacing(0, 1.25f);
        addToContent(info, 0, 12);

        final LinearLayout results = Ui.vbox(this);
        final View more = Ui.button(this, "Mehr laden", R.drawable.ic_chevron, Ui.TONAL);
        more.setVisibility(View.GONE);
        final EditText[] inputRef = new EditText[1];
        final Runnable perform = () -> {
            String q = inputRef[0].getText().toString().trim();
            if (q.length() < 2) {
                Toast.makeText(this, "Mindestens 2 Zeichen eingeben.", Toast.LENGTH_SHORT).show();
                return;
            }
            dirQuery = q;
            runDirectorySearch(results, more, false, token);
        };
        inputRef[0] = addSearchField("Lieblingssender suchen …", perform, null);

        final String[][] scopes = {{"all", "Alle"}, {"laut", "laut.fm"}, {"world", "World Radio"}};
        int sel = 0;
        for (int i = 0; i < scopes.length; i++) if (scopes[i][0].equals(dirScope)) sel = i;
        LinearLayout seg = Ui.segmented(this, new String[]{scopes[0][1], scopes[1][1], scopes[2][1]}, sel, idx -> {
            dirScope = scopes[idx][0];
            if (dirQuery.length() >= 2) runDirectorySearch(results, more, false, token);
        });
        addToContent(seg, 12, 0);

        View surprise = Ui.button(this, "Überrasch mich", R.drawable.ic_shuffle, Ui.TONAL);
        surprise.setOnClickListener(v -> {
            haptic(v);
            playSurprise();
        });
        addToContent(surprise, 12, 0);

        addToContent(results, 18, 0);
        addToContent(more, 6, 0);
        more.setOnClickListener(v -> runDirectorySearch(results, more, true, token));

        loadDirectorySuggestions(results, token);
    }

    /** Vorschlaege zum Entdecken, solange noch nichts gesucht wurde. */
    private void loadDirectorySuggestions(LinearLayout results, int token) {
        results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
        results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
        io.execute(() -> {
            Directory.Page page = null;
            try {
                page = Directory.random(siteBase(), "mix", 8);
            } catch (Exception ignored) {
            }
            final Directory.Page p = page;
            main.post(() -> {
                if (token != screenToken || !results.isAttachedToWindow() || !dirQuery.isEmpty()) return;
                results.removeAllViews();
                if (p == null || p.items.isEmpty()) {
                    results.addView(Ui.text(this, "Tipp: z. B. rock, jazz, 80er oder einen Sendernamen eingeben.", 13, MUTED, Ui.REGULAR));
                    return;
                }
                dirReportOn = p.reportOn;
                TextView h = Ui.text(this, "Vorschläge zum Entdecken", 17, TEXT, Ui.BOLD);
                h.setPadding(0, 0, 0, dp(10));
                results.addView(h);
                dirShown.clear();
                dirShown.addAll(p.items);
                for (Directory.Item it : p.items) addDirectoryRow(results, it, dirShown);
            });
        });
    }

    private void runDirectorySearch(LinearLayout results, View more, boolean append, int token) {
        final String q = dirQuery;
        final String scope = dirScope;
        if (!append) {
            dirOffset = 0;
            dirShown.clear();
            results.removeAllViews();
            results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
            results.addView(Ui.skeleton(this, -1, 64, 18), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(64), 0, 0, 0, 9));
        }
        final int off = dirOffset;
        more.setEnabled(false);
        io.execute(() -> {
            Directory.Page page = null;
            try {
                page = Directory.search(siteBase(), q, scope, off, 12);
            } catch (Exception ignored) {
            }
            final Directory.Page p = page;
            main.post(() -> {
                if (token != screenToken || !results.isAttachedToWindow()
                        || !q.equals(dirQuery) || !scope.equals(dirScope)) return;
                more.setEnabled(true);
                if (!append) results.removeAllViews();
                if (p == null) {
                    results.addView(Ui.text(this, "Die Suche ist gerade nicht erreichbar. Bitte später erneut versuchen.", 13, MUTED, Ui.REGULAR));
                    more.setVisibility(View.GONE);
                    return;
                }
                dirReportOn = p.reportOn;
                for (Directory.Item it : p.items) {
                    dirShown.add(it);
                    addDirectoryRow(results, it, dirShown);
                }
                dirOffset = off + 12;
                if (dirShown.isEmpty()) {
                    TextView none = Ui.text(this, "Kein Sender gefunden.", 14, MUTED, Ui.REGULAR);
                    none.setGravity(Gravity.CENTER);
                    none.setPadding(0, dp(24), 0, dp(24));
                    results.addView(none);
                }
                more.setVisibility(p.hasMore ? View.VISIBLE : View.GONE);
            });
        });
    }

    private void addDirectoryRow(LinearLayout host, Directory.Item it, List<Directory.Item> queue) {
        LinearLayout row = Ui.hbox(this);
        row.setPadding(dp(10), dp(9), dp(8), dp(9));
        row.setBackground(Ui.surface(18));
        row.setClickable(true);

        ImageView cover = Ui.cover(this, 14);
        cover.setImageResource(R.drawable.app_logo);
        row.addView(cover, Ui.lp(dp(52), dp(52), 0, 0, 12, 0));
        if (!it.cover.isEmpty()) loadImage(it.cover, cover);

        LinearLayout col = Ui.vbox(this);
        TextView name = Ui.text(this, it.name, 15, TEXT, Ui.BOLD);
        name.setSingleLine(true);
        name.setEllipsize(TextUtils.TruncateAt.END);
        col.addView(name);
        String sub = it.sub();
        if (!sub.isEmpty()) {
            TextView s = Ui.text(this, sub, 12, MUTED, Ui.REGULAR);
            s.setSingleLine(true);
            s.setEllipsize(TextUtils.TruncateAt.END);
            col.addView(s, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 2, 0, 0));
        }
        col.addView(Ui.eyebrow(this, it.own ? "Unser Sender" : (it.isWorld() ? "Fremd-Stream" : "laut.fm"), it.own ? Ui.CYAN : Ui.accent),
                Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));
        row.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        final boolean fav0 = isDirFav(it);
        final ImageButton heart = Ui.iconButton(this, fav0 ? R.drawable.ic_heart : R.drawable.ic_heart_outline, fav0 ? Ui.accent : MUTED, Color.TRANSPARENT, 38, "Favorit");
        heart.setOnClickListener(v -> {
            haptic(v);
            toggleDirFav(it);
            boolean now = isDirFav(it);
            heart.setImageResource(now ? R.drawable.ic_heart : R.drawable.ic_heart_outline);
            heart.setColorFilter(now ? Ui.accent : MUTED);
        });
        if (reportAllowed() && !it.own) {
            ImageButton flag = Ui.iconButton(this, R.drawable.ic_flag, MUTED, Color.TRANSPARENT, 34, "Sender melden");
            flag.setOnClickListener(v -> showReportDialog(it));
            row.addView(flag);
        }
        row.addView(heart);

        ImageButton play = Ui.iconButton(this, it.playable() ? R.drawable.ic_play : R.drawable.ic_open, Ui.ON_ACCENT, Ui.accent, 42, "Abspielen");
        play.setBackground(Ui.ripple(accentOval(), 42));
        play.setOnClickListener(v -> {
            haptic(v);
            playDirectoryItem(it, queue);
        });
        row.addView(play, Ui.lp(dp(42), dp(42), 4, 0, 0, 0));

        row.setOnClickListener(v -> {
            playDirectoryItem(it, queue);
            if (it.playable()) showFullPlayer();
        });
        host.addView(row, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 9));
    }

    // ================================================================ Sendeplan

    private static final String[] DAY_KEYS = {"mon", "tue", "wed", "thu", "fri", "sat", "sun"};
    private static final String[] DAY_SHORT = {"Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"};

    private int todayIndex() {
        // Calendar: SUNDAY=1 ... SATURDAY=7  ->  Montag=0 ... Sonntag=6
        return (java.util.Calendar.getInstance().get(java.util.Calendar.DAY_OF_WEEK) + 5) % 7;
    }

    private void showSchedule(String stationId) {
        beginScreen("schedule", "schedule", "Sendeplan", null);
        final int token = ++screenToken;
        String sid = (stationId == null || stationId.isEmpty() || stationId.startsWith("world:")) ? defaultStationId() : stationId;
        final String targetStation = sid;
        scheduleStationId = targetStation;
        scheduleDay = todayIndex();
        addPageHeading("Sendeplan", "Programm der Woche");

        // Sender wählen
        HorizontalScrollView stScroll = new HorizontalScrollView(this);
        stScroll.setHorizontalScrollBarEnabled(false);
        LinearLayout stRow = Ui.hbox(this);
        stScroll.addView(stRow);
        List<String> choices = new ArrayList<>(ownedStations());
        if (!choices.contains(targetStation)) choices.add(0, targetStation);
        for (String id : choices) {
            TextView c = Ui.chip(this, prettyName(id), id.equals(targetStation));
            c.setOnClickListener(v -> {
                if (!id.equals(targetStation)) showSchedule(id);
            });
            stRow.addView(c, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(34), 0, 0, 8, 0));
        }
        addToContent(stScroll, 0, 14);

        // Wochentage
        final LinearLayout dayRow = Ui.hbox(this);
        addToContent(dayRow, 0, 14);
        final LinearLayout list = Ui.vbox(this);
        final JSONArray[] data = {null};
        java.util.Calendar cal = java.util.Calendar.getInstance();
        cal.add(java.util.Calendar.DAY_OF_MONTH, -todayIndex());
        for (int i = 0; i < 7; i++) {
            final int day = i;
            LinearLayout tile = Ui.vbox(this);
            tile.setGravity(Gravity.CENTER);
            tile.setClickable(true);
            tile.setTag(i);
            TextView dn = Ui.text(this, DAY_SHORT[i], 12, MUTED, Ui.MEDIUM);
            dn.setGravity(Gravity.CENTER);
            TextView dd = Ui.text(this, String.valueOf(cal.get(java.util.Calendar.DAY_OF_MONTH)), 17, TEXT, Ui.BOLD);
            dd.setGravity(Gravity.CENTER);
            tile.addView(dn);
            tile.addView(dd, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));
            tile.setOnClickListener(v -> {
                scheduleDay = day;
                styleDayTiles(dayRow);
                if (data[0] != null) renderScheduleDay(list, data[0], day);
            });
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, dp(62), 1f);
            if (i > 0) lp.leftMargin = dp(6);
            dayRow.addView(tile, lp);
            cal.add(java.util.Calendar.DAY_OF_MONTH, 1);
        }
        styleDayTiles(dayRow);
        addToContent(list, 0, 0);

        final LinearLayout loading = addLoading(3);
        io.execute(() -> {
            try {
                // Wochenplan direkt aus der laut.fm-API; jede erfolgreiche Antwort wird lokal gespeichert
                // Zuerst der Sendeplan-Dienst des Portals (gemeinsamer Zwischenspeicher, Berliner Zeit, nur eigene Sender);
                // für alle anderen Sender oder bei Ausfall direkt laut.fm.
                DataCache.Result res;
                JSONArray all;
                try {
                    res = DataCache.fetch(this, "schedule_own_" + targetStation,
                            siteBase() + "/cms/api.php?action=schedule&station=" + java.net.URLEncoder.encode(targetStation, "UTF-8"),
                            "application/json", t -> flattenSchedule(t, targetStation) != null);
                    all = flattenSchedule(res.text, targetStation);
                    if (all == null) throw new IllegalStateException("kein Sendeplan");
                } catch (Exception own) {
                    res = DataCache.fetch(this, "schedule_" + targetStation,
                            lautApi(targetStation) + "/schedule", "application/json",
                            t -> { try { new JSONArray(t); return true; } catch (Exception e) { return false; } });
                    all = new JSONArray(res.text);
                }
                final DataCache.Result fres = res;
                final JSONArray fall = all;
                main.post(() -> {
                    if (token != screenToken) return;
                    replaceLoading(loading, fres);
                    data[0] = fall;
                    renderScheduleDay(list, fall, scheduleDay);
                });
            } catch (Exception e) {
                main.post(() -> {
                    if (token != screenToken) return;
                    content.removeView(loading);
                    addEmpty("Sendeplan konnte gerade nicht geladen werden und es gibt noch keinen gespeicherten Stand.");
                });
            }
        });
    }

    /** Antwort des Portal-Sendeplandienstes in die flache Liste {name,day,hour,end_time,description} wandeln; null, wenn unbrauchbar. */
    static JSONArray flattenSchedule(String text, String station) {
        try {
            JSONObject o = new JSONObject(text);
            if (!"ok".equals(o.optString("status"))) return null;
            JSONObject st = o.getJSONObject("stations").getJSONObject(station);
            JSONArray pls = st.getJSONArray("playlists");
            JSONArray out = new JSONArray();
            for (int i = 0; i < pls.length(); i++) {
                JSONObject p = pls.getJSONObject(i);
                JSONArray air = p.optJSONArray("airtimes");
                if (air == null) continue;
                for (int j = 0; j < air.length(); j++) {
                    JSONObject a = air.getJSONObject(j);
                    out.put(new JSONObject().put("name", p.optString("name")).put("description", p.optString("description"))
                            .put("day", a.getString("day")).put("hour", a.getInt("hour")).put("end_time", a.getInt("end_time")));
                }
            }
            return out;
        } catch (Exception e) {
            return null;
        }
    }

    private void styleDayTiles(LinearLayout dayRow) {
        int today = todayIndex();
        for (int i = 0; i < dayRow.getChildCount(); i++) {
            LinearLayout t = (LinearLayout) dayRow.getChildAt(i);
            boolean sel = i == scheduleDay;
            t.setBackground(Ui.ripple(sel ? Ui.rect(Ui.accent, 18, 0, 0) : Ui.rect(CARD, 18, i == today ? Ui.accent : LINE, i == today ? 1.5f : 1), 18));
            ((TextView) t.getChildAt(0)).setTextColor(sel ? Ui.ON_ACCENT : MUTED);
            ((TextView) t.getChildAt(1)).setTextColor(sel ? Ui.ON_ACCENT : TEXT);
        }
    }

    private void renderScheduleDay(LinearLayout list, JSONArray all, int day) {
        list.removeAllViews();
        List<JSONObject> rows = new ArrayList<>();
        for (int i = 0; i < all.length(); i++) {
            JSONObject o = all.optJSONObject(i);
            if (o != null && DAY_KEYS[day].equals(o.optString("day"))) rows.add(o);
        }
        Collections.sort(rows, (a, b) -> Integer.compare(a.optInt("hour"), b.optInt("hour")));
        java.util.Calendar now = java.util.Calendar.getInstance();
        double nowH = now.get(java.util.Calendar.HOUR_OF_DAY) + now.get(java.util.Calendar.MINUTE) / 60.0;
        boolean today = day == todayIndex();
        if (rows.isEmpty()) {
            LinearLayout box = Ui.card(this);
            box.setGravity(Gravity.CENTER_HORIZONTAL);
            box.setPadding(dp(20), dp(30), dp(20), dp(30));
            box.addView(Ui.icon(this, R.drawable.ic_equalizer, Ui.accent, 32), Ui.lp(dp(32), dp(32), 0, 0, 0, 10));
            TextView empty = Ui.text(this, "An diesem Tag läuft der Nonstop-Mix ohne feste Sendungen.", 14, Ui.TEXT_2, Ui.REGULAR);
            empty.setGravity(Gravity.CENTER);
            box.addView(empty);
            list.addView(box);
            return;
        }
        int nextIdx = -1;
        for (int i = 0; i < rows.size(); i++) {
            JSONObject o = rows.get(i);
            int start = o.optInt("hour"), end = o.optInt("end_time");
            boolean live = today && (start < end ? (nowH >= start && nowH < end)
                    : (end == 0 ? nowH >= start : (nowH >= start || nowH < end)));
            if (today && nextIdx < 0 && !live && start > nowH) nextIdx = i;
        }
        for (int i = 0; i < rows.size(); i++) {
            JSONObject o = rows.get(i);
            int start = o.optInt("hour");
            int end = o.optInt("end_time");
            boolean live = today && (start < end ? (nowH >= start && nowH < end)
                    : (end == 0 ? nowH >= start : (nowH >= start || nowH < end)));
            int endH = end == 0 ? 24 : end;
            float progress = 0f;
            if (live) {
                double span = endH > start ? endH - start : (endH + 24 - start);
                double elapsed = nowH >= start ? nowH - start : nowH + 24 - start;
                progress = (float) Math.max(0, Math.min(1, elapsed / span));
            }
            LinearLayout line = Ui.hbox(this);
            line.setGravity(Gravity.TOP);
            LinearLayout timeCol = Ui.vbox(this);
            timeCol.setGravity(Gravity.CENTER_HORIZONTAL);
            TextView ts = Ui.text(this, String.format(Locale.GERMANY, "%02d:00", start), 15, live ? Ui.accent : TEXT, Ui.BOLD);
            TextView te = Ui.text(this, String.format(Locale.GERMANY, "%02d:00", endH == 24 ? 0 : endH), 12, MUTED, Ui.REGULAR);
            timeCol.addView(ts);
            timeCol.addView(te, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 2, 0, 0));
            line.addView(timeCol, new LinearLayout.LayoutParams(dp(54), ViewGroup.LayoutParams.WRAP_CONTENT));

            LinearLayout card = Ui.vbox(this);
            card.setPadding(dp(14), dp(12), dp(14), dp(12));
            card.setBackground(live ? Ui.rect(Ui.alpha(Ui.accent, 34), 18, Ui.accent, 1.5f) : Ui.rect(CARD, 18, LINE, 1));
            if (live || i == nextIdx) {
                LinearLayout badgeRow = Ui.hbox(this);
                if (live) {
                    Ui.Equalizer eq = new Ui.Equalizer(this);
                    eq.setColor(Ui.accent);
                    eq.setPlaying(true);
                    badgeRow.addView(eq, Ui.lp(dp(13), dp(10), 0, 0, 6, 0));
                }
                badgeRow.addView(Ui.eyebrow(this, live ? "Läuft jetzt" : "Gleich", live ? Ui.accent : Ui.CYAN));
                card.addView(badgeRow, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 5));
            }
            card.addView(Ui.text(this, o.optString("name", "Sendung"), 16, TEXT, Ui.BOLD));
            String d = o.optString("description", "");
            if (d != null && !d.trim().isEmpty()) {
                TextView dt = Ui.text(this, d, 13, Ui.TEXT_2, Ui.REGULAR);
                dt.setLineSpacing(0, 1.2f);
                dt.setPadding(0, dp(5), 0, 0);
                card.addView(dt);
            }
            if (live) {
                final float p = progress;
                FrameLayout bar = new FrameLayout(this);
                bar.setBackground(Ui.rect(Ui.alpha(Color.WHITE, 30), 3, 0, 0));
                View fill = new View(this);
                fill.setBackground(Ui.rect(Ui.accent, 3, 0, 0));
                bar.addView(fill, new FrameLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT));
                bar.post(() -> {
                    ViewGroup.LayoutParams flp = fill.getLayoutParams();
                    flp.width = Math.round(bar.getWidth() * p);
                    fill.setLayoutParams(flp);
                });
                card.addView(bar, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(5), 0, 12, 0, 0));
            }
            line.addView(card, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            list.addView(line, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 10));
        }
    }

    // ================================================================ Podcast

    interface Loaded {
        void done(JSONArray list, DataCache.Result res);
    }

    private void loadPodcast(Loaded cb) {
        io.execute(() -> {
            try {
                DataCache.Result res = DataCache.fetch(this, "podcast", siteBase() + "/podcast.php", "application/json",
                        t -> { try { return new JSONObject(t).optJSONArray("episodes") != null; } catch (Exception e) { return false; } });
                JSONArray episodes = new JSONObject(res.text).optJSONArray("episodes");
                main.post(() -> cb.done(episodes, res));
            } catch (Exception e) {
                main.post(() -> cb.done(null, null));
            }
        });
    }

    private void showPodcast() {
        beginScreen("podcast", "more", "Podcast", this::showHome);
        final int token = ++screenToken;
        addPageHeading("Podcast", "Direkt in der App hören");
        final LinearLayout loading = addLoading(3);
        loadPodcast((episodes, res) -> {
            if (token != screenToken) return;
            if (res == null) {
                content.removeView(loading);
                addEmpty("Podcast konnte gerade nicht geladen werden und es gibt noch keinen gespeicherten Stand.");
                return;
            }
            replaceLoading(loading, res);
            if (episodes == null || episodes.length() == 0) {
                addEmpty("Aktuell sind keine Folgen verfügbar.");
                return;
            }
            for (int i = 0; i < episodes.length(); i++) {
                JSONObject ep = episodes.optJSONObject(i);
                if (ep == null) continue;
                addToContent(podcastRow(ep, i == 0), 0, 10);
            }
        });
    }

    /** "Sat, 07 Mar 2026 21:20:00 +0100" → "07.03.2026"; unbekannte Formate bleiben unverändert. */
    private String podcastDate(String raw) {
        if (raw == null || raw.isEmpty()) return "";
        try {
            java.util.Date d = new java.text.SimpleDateFormat("EEE, dd MMM yyyy HH:mm:ss Z", Locale.ENGLISH).parse(raw.trim());
            return new java.text.SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY).format(d);
        } catch (Exception e) {
            return raw;
        }
    }

    private View podcastRow(JSONObject ep, boolean featured) {
        final String title = ep.optString("title", "Podcast-Folge");
        final String date = podcastDate(ep.optString("pubDate", ""));
        final String audio = ep.optString("audioUrl", "");
        final String image = ep.optString("image", "");
        final String description = ep.optString("description", "");
        boolean playing = podcastMode && title.equals(currentTitle);

        LinearLayout card = Ui.hbox(this);
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(12), dp(12), dp(12), dp(12));
        card.setBackground(featured ? Ui.gradient(Ui.mix(Ui.heroFrom, Ui.SURFACE, 0.35f), Ui.mix(Ui.heroTo, Ui.SURFACE, 0.55f), 22, GradientDrawable.Orientation.TL_BR)
                : Ui.rect(CARD, 20, playing ? Ui.accent : LINE, playing ? 1.5f : 1));

        int cs = featured ? 92 : 68;
        ImageView cover = Ui.cover(this, featured ? 18 : 14);
        cover.setImageResource(R.drawable.app_logo);
        if (!image.trim().isEmpty()) loadImage(image, cover);
        card.addView(cover, Ui.lp(dp(cs), dp(cs), 0, 0, 14, 0));

        LinearLayout copy = Ui.vbox(this);
        if (featured) copy.addView(Ui.eyebrow(this, "Neueste Folge", Ui.CYAN));
        TextView t = Ui.text(this, title, featured ? 17 : 15, TEXT, Ui.BOLD);
        Ui.ellipsize(t, 2);
        copy.addView(t, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, featured ? 5 : 0, 0, 0));
        if (!date.isEmpty()) copy.addView(Ui.text(this, date, 12, Ui.accent, Ui.MEDIUM), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));
        if (!description.trim().isEmpty()) {
            String shortDesc = description.length() > 130 ? description.substring(0, 127) + "…" : description;
            TextView desc = Ui.text(this, shortDesc, 12.5f, Ui.TEXT_2, Ui.REGULAR);
            Ui.ellipsize(desc, featured ? 3 : 2);
            copy.addView(desc, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 5, 0, 0));
        }
        card.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        ImageButton play = Ui.iconButton(this, playing ? R.drawable.ic_pause : R.drawable.ic_play, Ui.ON_ACCENT, Ui.accent, 46, "Folge abspielen");
        play.setBackground(Ui.ripple(accentOval(), 46));
        play.setOnClickListener(v -> {
            haptic(v);
            if (podcastMode && title.equals(currentTitle)) togglePlayback();
            else playPodcast(title, audio, image);
        });
        card.addView(play, Ui.lp(dp(46), dp(46), 10, 0, 0, 0));
        return card;
    }

    // ================================================================ News

    private void loadNews(Loaded cb) {
        io.execute(() -> {
            try {
                // News kommen aus dem RSS-Feed der Marke; ist er leer oder nicht erreichbar, hilft die News-API.
                // Beides wird lokal gespeichert und bei Netzausfall offline gezeigt.
                DataCache.Result res = null;
                JSONArray articles = null;
                try {
                    res = DataCache.fetch(this, "news_rss", siteBase() + "/rss.xml", "application/rss+xml, application/xml, text/xml",
                            xml -> { try { NewsFeed.parseRss(xml, siteBase()); return true; } catch (Exception e) { return false; } });
                    articles = NewsFeed.parseRss(res.text, siteBase());
                } catch (Exception rssFailed) {
                    articles = null;
                }
                if (articles == null || articles.length() == 0) {
                    DataCache.Result api = DataCache.fetch(this, "news_api", siteBase() + "/cms/api.php?action=news_public&limit=30", "application/json",
                            t -> { try { return new JSONObject(t).optJSONArray("articles") != null; } catch (Exception e) { return false; } });
                    articles = new JSONObject(api.text).optJSONArray("articles");
                    res = api;
                }
                final DataCache.Result shown = res;
                final JSONArray list = articles;
                main.post(() -> cb.done(list, shown));
            } catch (Exception e) {
                main.post(() -> cb.done(null, null));
            }
        });
    }

    private void showNews() {
        beginScreen("news", "more", "News & Magazin", this::showHome);
        final int token = ++screenToken;
        addPageHeading("News & Magazin", brandText("news_title", "Aktuelles von " + brandName()));

        View socialBtn = Ui.button(this, "TikTok & Instagram ansehen", R.drawable.ic_open, Ui.TONAL);
        socialBtn.setOnClickListener(v -> openInternalWeb("Social Wall", siteBase() + "/social-wall.html"));
        addToContent(socialBtn, 0, 14);

        final LinearLayout loading = addLoading(3);
        loadNews((list, res) -> {
            if (token != screenToken) return;
            if (res == null) {
                content.removeView(loading);
                addEmpty("News konnten gerade nicht geladen werden und es gibt noch keinen gespeicherten Stand.");
                return;
            }
            replaceLoading(loading, res);
            if (list == null || list.length() == 0) {
                addEmpty("Aktuell gibt es keine veröffentlichten News.");
                return;
            }
            for (int i = 0; i < list.length(); i++) {
                JSONObject a = list.optJSONObject(i);
                if (a == null) continue;
                addToContent(i == 0 ? featuredNews(a) : newsRow(a), 0, 10);
            }
        });
    }

    private View featuredNews(JSONObject a) {
        String title = a.optString("title", "");
        String category = a.optString("category", "News");
        String image = a.optString("image_url", "");
        String date = newsDate(a.optString("published_at", a.optString("created_at", "")));
        FrameLayout card = new FrameLayout(this);
        card.setBackground(Ui.rect(CARD, 24, LINE, 1));
        card.setClickable(true);
        card.setOnClickListener(v -> showNewsArticle(a));
        card.setClipToOutline(true);
        card.setOutlineProvider(new android.view.ViewOutlineProvider() {
            @Override public void getOutline(View view, android.graphics.Outline outline) {
                outline.setRoundRect(0, 0, view.getWidth(), view.getHeight(), dp(24));
            }
        });
        ImageView img = new ImageView(this);
        img.setScaleType(image.trim().isEmpty() ? ImageView.ScaleType.CENTER_INSIDE : ImageView.ScaleType.CENTER_CROP);
        img.setImageResource(R.drawable.app_logo);
        if (image.trim().isEmpty()) img.setPadding(dp(80), dp(40), dp(80), dp(40));
        else loadImage(image, img);
        card.addView(img, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(220)));
        View shade = new View(this);
        shade.setBackground(new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM, new int[]{Color.TRANSPARENT, Ui.alpha(Ui.BG_DEEP, 235)}));
        card.addView(shade, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(220)));
        LinearLayout txt = Ui.vbox(this);
        txt.setPadding(dp(16), dp(0), dp(16), dp(16));
        txt.addView(Ui.eyebrow(this, category + (date.isEmpty() ? "" : " · " + date), Ui.CYAN));
        TextView t = Ui.text(this, title, 20, Color.WHITE, Ui.BOLD);
        Ui.ellipsize(t, 3);
        txt.addView(t, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 6, 0, 0));
        card.addView(txt, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.BOTTOM));
        return card;
    }

    private View newsRow(JSONObject a) {
        String title = a.optString("title", "");
        String category = a.optString("category", "News");
        String excerpt = a.optString("excerpt", "");
        String image = a.optString("image_url", "");
        String imageMode = a.optString("image_mode", "thumbnail");
        boolean showThumb = image.trim().length() > 0 &&
                ("thumbnail".equals(imageMode) || "both".equals(imageMode) || imageMode.isEmpty());
        String date = newsDate(a.optString("published_at", a.optString("created_at", "")));

        LinearLayout card = Ui.hbox(this);
        card.setPadding(dp(10), dp(10), dp(12), dp(10));
        card.setBackground(Ui.surface(20));
        card.setClickable(true);
        card.setFocusable(true);
        card.setOnClickListener(v -> showNewsArticle(a));

        if (showThumb) {
            ImageView cover = Ui.cover(this, 14);
            cover.setImageResource(R.drawable.app_logo);
            loadImage(image, cover);
            card.addView(cover, Ui.lp(dp(84), dp(84), 0, 0, 12, 0));
        }
        LinearLayout copy = Ui.vbox(this);
        copy.addView(Ui.eyebrow(this, category + (date.isEmpty() ? "" : " · " + date), Ui.accent));
        TextView t = Ui.text(this, title, 15, TEXT, Ui.BOLD);
        Ui.ellipsize(t, 3);
        copy.addView(t, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));
        if (!excerpt.trim().isEmpty() && !showThumb) {
            TextView desc = Ui.text(this, excerpt.length() > 120 ? excerpt.substring(0, 117) + "…" : excerpt, 12.5f, Ui.TEXT_2, Ui.REGULAR);
            Ui.ellipsize(desc, 2);
            copy.addView(desc, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 5, 0, 0));
        }
        card.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        return card;
    }

    private String newsDate(String raw) {
        if (raw == null || raw.length() < 10) return "";
        String datePart = raw.substring(0, 10);
        String[] parts = datePart.split("-");
        if (parts.length != 3) return datePart;
        return parts[2] + "." + parts[1] + "." + parts[0];
    }

    // ================================================================ Mitmachen, Shops

    private void showCommunity() {
        beginScreen("community", "community", "Mitmachen", null);
        addPageHeading("Mitmachen", "Voting, Wünsche & Grüße");

        // Sender für Voting und Wunsch wählen
        HorizontalScrollView stScroll = new HorizontalScrollView(this);
        stScroll.setHorizontalScrollBarEnabled(false);
        LinearLayout stRow = Ui.hbox(this);
        stScroll.addView(stRow);
        for (String id : ownedStations()) {
            TextView c = Ui.chip(this, prettyName(id), id.equals(currentStationId));
            c.setOnClickListener(v -> {
                if (!id.equals(currentStationId)) {
                    selectStation(id, prettyName(id), false);
                    showCommunity();
                }
            });
            stRow.addView(c, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, dp(34), 0, 0, 8, 0));
        }

        if (!appConfig.isOwned(currentStationId)) {
            addEmpty("Community-Funktionen gibt es nur für die Sender dieser App. Wähle dafür einen der Sender.");
            addToContent(stScroll, 14, 0);
            View ours = Ui.button(this, "Sender öffnen", R.drawable.ic_radio, Ui.PRIMARY);
            ours.setOnClickListener(v -> showOwnedStations());
            addToContent(ours, 14, 0);
            return;
        }

        addToContent(stScroll, 0, 14);
        String base = appConfig.communityBase;
        final String sid = currentStationId;
        final String sname = currentStationName;
        addMenuRow(R.drawable.ic_equalizer, "Netzwerk-Song-Voting", "Songs aus dem gesamten Netzwerk bewerten",
                () -> openInternalWeb("Netzwerk-Song-Voting", base + "voting.html?mode=network"));
        addMenuRow(R.drawable.ic_equalizer, "Sender-Song-Voting", "Songs von " + sname + " bewerten",
                () -> openInternalWeb("Sender-Song-Voting", base + "voting.html?mode=station&station=" + Uri.encode(sid)));
        addMenuRow(R.drawable.ic_music, "Musikwunsch", "Deinen Songwunsch an " + sname + " senden",
                () -> openInternalWeb("Musikwunsch", base + "wunsch.html?station=" + Uri.encode(sid)));
        addMenuRow(R.drawable.ic_chat, "Studiomail", "Gruß oder Feedback ans Studio",
                () -> openInternalWeb("Studiomail", base + "studiomail/widget-form.html"));
        addMenuRow(R.drawable.ic_mic, "Voicemail", "Sprachnachricht aufnehmen",
                () -> openInternalWeb("Voicemail", base + "voicemsg.html?station=" + Uri.encode(sid), true));
    }

    private void showFanshops() {
        beginScreen("fanshops", "more", "Shops", this::showHome);
        addPageHeading("Shops", "Direkt in der App öffnen");
        for (String[] shop : appConfig.shops) {
            final String title = shop[0], url = shop[2];
            addMenuRow(R.drawable.ic_shop, title, shop[1], () -> openInternalWeb(title, url));
        }
    }

    // ================================================================ Mehr-Menü, Einstellungen, Info, Hilfe

    private void showMainMenu() {
        updateBottomNavActive("more");
        final Sheet sheet = new Sheet("Mehr");
        sheet.panel.setOnClickListener(v -> { });
        LinearLayout list = sheet.body;

        addGroupTitle(list, "Entdecken");
        if (appConfig.podcast && !menuHidden("podcast")) addSheetRow(sheet, R.drawable.ic_podcast, "Podcast", "Der Podcast von " + brandName(), this::showPodcast);
        if (!menuHidden("news")) addSheetRow(sheet, R.drawable.ic_news, "News & Magazin", "Aktuelles direkt in der App lesen", this::showNews);
        if (appConfig.hasShops() && !menuHidden("shops")) addSheetRow(sheet, R.drawable.ic_shop, "Shops", "Online-Shops von " + brandName(), this::showFanshops);
        addSheetRow(sheet, R.drawable.ic_bookmark, "Gemerkte Songs", savedSongs().size() + " gespeichert", this::showSavedSongs);

        addGroupTitle(list, "Wiedergabe");
        if (prefs.getBoolean("app_f_cast", true)) addSheetRow(sheet, R.drawable.ic_cast, "Übertragen (Cast)",
                NetCast.get(this).isActive() ? "Verbunden mit " + NetCast.get(this).name() : "Chromecast, DLNA & Google Cast", this::showCastPicker);
        addSheetRow(sheet, R.drawable.ic_moon, "Sleep-Timer", sleepEndsAt > System.currentTimeMillis() ? "Läuft: " + sleepRemainingText() : "Wiedergabe automatisch beenden", this::showSleepTimerMenu);
        addSheetRow(sheet, R.drawable.ic_settings, "Einstellungen", "Darstellung, Wiedergabe & Speicher", this::showSettings);

        addGroupTitle(list, "Konto & Hilfe");
        addSheetRow(sheet, R.drawable.ic_account, "Favoriten & Konto", "Favoriten, Profil & Sync", () -> startActivity(new Intent(this, AccountActivity.class)));
        if (prefs.getBoolean("assistant_enabled", true) && prefs.getBoolean("app_f_assistant", true) && !menuHidden("assistant")) {
            addSheetRow(sheet, R.drawable.ic_sparkle, "KI-Assistent", "Fragen, Sendeplan, Nachricht & Sprachnachricht", this::showAssistant);
        }
        if (!menuHidden("help")) addSheetRow(sheet, R.drawable.ic_help, "Hilfe & Bedienung", "Navigation & Player", this::showHelp);
        addSheetRow(sheet, R.drawable.ic_info, "App-Info", "Version, Funktionen & Rechtliches", this::showInfo);

        addGroupTitle(list, "Mehr von uns");
        if (!menuHidden("portal")) addSheetRow(sheet, R.drawable.ic_globe, "Website", siteHost(), () -> openInternalWeb(brandName(), appConfig.website));
        JSONObject menuLay = layout();
        JSONObject menuCfg = menuLay == null ? null : menuLay.optJSONObject("more_menu");
        JSONArray customMenu = menuCfg == null ? null : menuCfg.optJSONArray("custom");
        if (customMenu != null) for (int ci = 0; ci < customMenu.length(); ci++) {
            JSONObject ce = customMenu.optJSONObject(ci);
            if (ce == null || ce.optString("url").isEmpty()) continue;
            final String ctitle = ce.optString("title");
            final String curl = ce.optString("url");
            addSheetRow(sheet, R.drawable.ic_open, ctitle, ce.optString("sub").isEmpty() ? "Im Browser öffnen" : ce.optString("sub"), () -> openInternalWeb(ctitle, curl));
        }

        addGroupTitle(list, "Rechtliches");
        addSheetRow(sheet, R.drawable.ic_shield, "Datenschutz", "Datenschutzerklärung", () -> openInternalWeb("Datenschutz", appConfig.privacy));
        addSheetRow(sheet, R.drawable.ic_balance, "Impressum", "Rechtliche Anbieterinformationen", () -> openInternalWeb("Impressum", appConfig.imprint));

        LinearLayout quit = Ui.row(this, R.drawable.ic_power, "App beenden", "Wiedergabe stoppen", () -> {
            if (controller != null) controller.stop();
            sheet.close();
            finishAndRemoveTask();
        });
        list.addView(quit, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 18, 0, 0));
        sheet.show();
        sheet.overlay.setOnClickListener(v -> {
            sheet.close();
            updateBottomNavActive(currentScreen);
        });
    }

    private void addGroupTitle(LinearLayout host, String title) {
        TextView t = Ui.eyebrow(this, title, MUTED);
        t.setPadding(dp(4), 0, 0, 0);
        host.addView(t, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 16, 0, 8));
    }

    private void addSheetRow(Sheet sheet, int iconRes, String title, String sub, Runnable action) {
        LinearLayout row = Ui.row(this, iconRes, title, sub, () -> {
            sheet.close();
            updateBottomNavActive(currentScreen);
            action.run();
        });
        sheet.body.addView(row, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 0, 8));
    }

    private void showSettings() {
        beginScreen("settings", "more", "Einstellungen", this::showHome);
        addPageHeading("Einstellungen", "Deine App");

        addSection("Wiedergabe", null, null);
        addToContent(Ui.switchRow(this, "Letzten Sender merken", "Beim Start ist der zuletzt gehörte Sender vorgewählt",
                prefs.getBoolean("set_remember", true), on -> prefs.edit().putBoolean("set_remember", on).apply()), 0, 9);
        addToContent(Ui.switchRow(this, "Beim Start automatisch abspielen", "Der gemerkte Sender startet sofort, wenn du die App öffnest",
                prefs.getBoolean("set_autoplay", false), on -> prefs.edit().putBoolean("set_autoplay", on).apply()), 0, 9);
        if (prefs.getBoolean("app_f_cast", true)) addToContent(Ui.switchRow(this, "Titel auf dem Cast-Gerät live anzeigen", "Zeigt den laufenden Titel am Fernseher – der Stream startet dort bei jedem Titelwechsel kurz neu",
                prefs.getBoolean("cast_live_info", false), on -> prefs.edit().putBoolean("cast_live_info", on).apply()), 0, 9);
        addToContent(Ui.switchRow(this, "Sleep-Timer sanft ausblenden", "Die Lautstärke sinkt in den letzten 20 Sekunden",
                prefs.getBoolean("set_fade", true), on -> prefs.edit().putBoolean("set_fade", on).apply()), 0, 9);

        addSection("Darstellung", null, null);
        addToContent(Ui.switchRow(this, "Bewegung reduzieren", "Keine Dauer-Animationen (Equalizer, Platzhalter, Übergänge)",
                Ui.reduceMotion, on -> {
                    Ui.reduceMotion = on;
                    prefs.edit().putBoolean("set_reduce_motion", on).apply();
                }), 0, 9);
        addToContent(Ui.switchRow(this, "Haptisches Feedback", "Leichtes Vibrieren bei Tasten und Navigation",
                prefs.getBoolean("set_haptics", true), on -> prefs.edit().putBoolean("set_haptics", on).apply()), 0, 9);

        addSection("Daten & Speicher", null, null);
        addMenuRow(R.drawable.ic_history, "Zuletzt gehört löschen", recentList().length() + " Einträge", () -> {
            prefs.edit().remove("recent_list").apply();
            Toast.makeText(this, "Verlauf gelöscht", Toast.LENGTH_SHORT).show();
            showSettings();
        });
        addMenuRow(R.drawable.ic_delete, "Zwischenspeicher leeren", "Gespeicherte News, Sendepläne und Bilder", () -> {
            clearCaches();
            Toast.makeText(this, "Zwischenspeicher geleert", Toast.LENGTH_SHORT).show();
        });

        addSection("Über die App", null, null);
        addMenuRow(R.drawable.ic_info, brandName(), "Version " + appVersion(), this::showInfo);
        addMenuRow(R.drawable.ic_help, "Hilfe & Bedienung", "So funktioniert die App", this::showHelp);
    }

    private void clearCaches() {
        try {
            java.io.File dir = new java.io.File(getFilesDir(), "datacache");
            java.io.File[] files = dir.listFiles();
            if (files != null) for (java.io.File f : files) //noinspection ResultOfMethodCallIgnored
                f.delete();
        } catch (Exception ignored) {
        }
        imageCache.evictAll();
        synchronized (nowCache) { nowCache.clear(); }
    }

    private void showInfo() {
        beginScreen("info", "more", "App-Info", this::showHome);
        String version = appVersion().isEmpty() ? "unbekannt" : appVersion();

        LinearLayout head = Ui.vbox(this);
        head.setGravity(Gravity.CENTER_HORIZONTAL);
        head.setPadding(dp(18), dp(24), dp(18), dp(22));
        head.setBackground(Ui.gradient(Ui.mix(Ui.heroFrom, Ui.SURFACE, 0.3f), Ui.mix(Ui.heroTo, Ui.SURFACE, 0.5f), 26, GradientDrawable.Orientation.TL_BR));
        ImageView logo = Ui.cover(this, 22);
        logo.setImageResource(R.drawable.app_logo);
        head.addView(logo, new LinearLayout.LayoutParams(dp(76), dp(76)));
        TextView name = Ui.text(this, getString(R.string.app_name), 22, Color.WHITE, Ui.BOLD);
        name.setGravity(Gravity.CENTER);
        head.addView(name, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 14, 0, 0));
        TextView ver = Ui.text(this, "Version " + version, 13, Ui.TEXT_2, Ui.MEDIUM);
        head.addView(ver, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));
        addToContent(head, 6, 14);

        addMenuRow(R.drawable.ic_account, "Anbieter", brandName() + " · " + siteHost(), null);
        addMenuRow(R.drawable.ic_radio, "Funktionen", "Live-Radio, Hintergrundwiedergabe, Vollbildplayer, Favoriten, Sendeplan, News, Sendersuche, Cast und Konto/Sync.", null);
        addMenuRow(R.drawable.ic_info, "Streaming & Lizenzen", "Die eingebundenen laut.fm-Streams werden von laut.fm bereitgestellt. Laut laut.fm übernimmt der Anbieter Streamingkosten sowie die erforderlichen GEMA-/GVL-Lizenzen bzw. Gebühren.",
                () -> openInternalWeb("laut.fm Nutzungsbedingungen", appConfig.lautfmTerms));
        addMenuRow(R.drawable.ic_balance, "Impressum", "Rechtliche Anbieterinformationen öffnen", () -> openInternalWeb("Impressum", appConfig.imprint));
        addMenuRow(R.drawable.ic_shield, "Datenschutz", "Datenschutzerklärung öffnen", () -> openInternalWeb("Datenschutz", appConfig.privacy));
        addMenuRow(R.drawable.ic_globe, "Website", appConfig.website.replaceAll("^https?://", "").replaceAll("/$", "") + " öffnen",
                () -> openInternalWeb(brandName(), appConfig.website));
    }

    private void showHelp() {
        beginScreen("help", "more", "Hilfe", this::showHome);
        addPageHeading("Bedienung", "So funktioniert die App");
        addMenuRow(R.drawable.ic_play, "Player unten", "Der Player bleibt unten sichtbar. Antippen öffnet den Vollbildplayer; dort wechselst du Sender mit Wischen oder den Pfeiltasten.", null);
        addMenuRow(R.drawable.ic_search, "Sendersuche", "Über die Lupe oben suchst du eigene und weitere öffentlich gelistete laut.fm-Sender gemeinsam.", null);
        addMenuRow(R.drawable.ic_heart, "Favoriten", "Mit dem Herz speicherst du Sender. Sie erscheinen auf der Startseite und unter „Favoriten“.", null);
        addMenuRow(R.drawable.ic_bookmark, "Songs merken", "Im Vollbildplayer und im Verlauf speicherst du Titel mit dem Lesezeichen. Du findest sie unter Mehr › Gemerkte Songs.", null);
        addMenuRow(R.drawable.ic_moon, "Sleep-Timer", "Im Vollbildplayer pausiert die Wiedergabe nach 15, 30, 45, 60 oder 90 Minuten automatisch – auf Wunsch mit sanftem Ausblenden.", null);
        addMenuRow(R.drawable.ic_radio, "Hintergrundwiedergabe", "Radio läuft im Hintergrund weiter. Titel, Interpret und Cover werden an die Android-Mediensteuerung übergeben.", null);
        if (appConfig.hasCommunity()) addMenuRow(R.drawable.ic_equalizer, "Community", "Voting, Musikwunsch, Studiomail und Voicemail gibt es bei den Sendern dieser App.", null);
        addMenuRow(R.drawable.ic_news, "News", "Beiträge der Website sind im News-Bereich integriert.", null);
        addMenuRow(R.drawable.ic_mic, "Voicemail", "Beim ersten Öffnen Mikrofonzugriff erlauben. Die App öffnet die Aufnahme erst nach erteilter Android-Berechtigung.", null);
        addMenuRow(R.drawable.ic_back, "Android Zurück", "Schließt zuerst Vollbildansichten und Menüs. Auf Unterseiten führt Zurück eine Ebene höher.", null);
    }

    // ================================================================ Gemerkte Songs

    private List<JSONObject> savedSongs() {
        List<JSONObject> out = new ArrayList<>();
        try {
            JSONArray arr = new JSONArray(prefs.getString("saved_songs", "[]"));
            for (int i = 0; i < arr.length(); i++) {
                JSONObject o = arr.optJSONObject(i);
                if (o != null) out.add(o);
            }
        } catch (Exception ignored) {
        }
        return out;
    }

    private void writeSavedSongs(List<JSONObject> list) {
        JSONArray arr = new JSONArray();
        for (int i = 0; i < list.size() && i < 300; i++) arr.put(list.get(i));
        prefs.edit().putString("saved_songs", arr.toString()).apply();
    }

    private boolean isSongSaved(String artist, String title) {
        for (JSONObject o : savedSongs()) {
            if (o.optString("artist").equalsIgnoreCase(artist == null ? "" : artist)
                    && o.optString("title").equalsIgnoreCase(title == null ? "" : title)) return true;
        }
        return false;
    }

    /** Merkt einen Titel oder entfernt ihn wieder; gibt zurück, ob er danach gemerkt ist. */
    private boolean toggleSaveSong(String artist, String title, String station) {
        if (title == null || title.trim().isEmpty() || "Live-Stream".equals(title) || "Titel wird geladen …".equals(title)) {
            Toast.makeText(this, "Gerade ist kein Titel bekannt.", Toast.LENGTH_SHORT).show();
            return false;
        }
        List<JSONObject> list = savedSongs();
        for (int i = list.size() - 1; i >= 0; i--) {
            JSONObject o = list.get(i);
            if (o.optString("artist").equalsIgnoreCase(artist == null ? "" : artist) && o.optString("title").equalsIgnoreCase(title)) {
                list.remove(i);
                writeSavedSongs(list);
                Toast.makeText(this, "Song entfernt", Toast.LENGTH_SHORT).show();
                return false;
            }
        }
        try {
            list.add(0, new JSONObject().put("artist", artist == null ? "" : artist).put("title", title)
                    .put("station", station == null ? "" : station).put("t", System.currentTimeMillis()));
            writeSavedSongs(list);
            Toast.makeText(this, "Song gemerkt", Toast.LENGTH_SHORT).show();
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    private void openSongSearch(String artist, String title) {
        String q = ((artist == null ? "" : artist) + " " + (title == null ? "" : title)).trim();
        if (q.isEmpty()) return;
        openExternal("https://www.youtube.com/results?search_query=" + Uri.encode(q));
    }

    private void shareText(String text) {
        Intent send = new Intent(Intent.ACTION_SEND);
        send.setType("text/plain");
        send.putExtra(Intent.EXTRA_TEXT, text);
        startActivity(Intent.createChooser(send, "Teilen"));
    }

    /** Zeile mit Titel, Interpret, Merken- und Such-Knopf (Verlauf im Player, Senderdetail). */
    private View songLine(String artist, String title, String station, boolean showStation) {
        LinearLayout row = Ui.hbox(this);
        row.setPadding(0, dp(7), 0, dp(7));
        LinearLayout col = Ui.vbox(this);
        TextView t = Ui.text(this, title.isEmpty() ? artist : title, 14.5f, TEXT, Ui.MEDIUM);
        Ui.ellipsize(t, 2);
        col.addView(t);
        String sub = title.isEmpty() ? "" : artist;
        if (showStation && station != null && !station.isEmpty()) sub = sub.isEmpty() ? station : sub + " · " + station;
        if (!sub.isEmpty()) {
            TextView s = Ui.text(this, sub, 12.5f, MUTED, Ui.REGULAR);
            Ui.ellipsize(s, 1);
            col.addView(s, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 2, 0, 0));
        }
        row.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        final boolean saved0 = isSongSaved(artist, title);
        final ImageButton save = Ui.iconButton(this, saved0 ? R.drawable.ic_bookmark : R.drawable.ic_bookmark_outline, saved0 ? Ui.accent : MUTED, Color.TRANSPARENT, 40, "Song merken");
        save.setOnClickListener(v -> {
            haptic(v);
            boolean now = toggleSaveSong(artist, title, station);
            save.setImageResource(now ? R.drawable.ic_bookmark : R.drawable.ic_bookmark_outline);
            save.setColorFilter(now ? Ui.accent : MUTED);
            refreshFullPlayerUi();
        });
        row.addView(save);
        ImageButton find = Ui.iconButton(this, R.drawable.ic_search, MUTED, Color.TRANSPARENT, 40, "Song suchen");
        find.setOnClickListener(v -> openSongSearch(artist, title));
        row.addView(find);
        return row;
    }

    private void showSavedSongs() {
        beginScreen("saved", "more", "Gemerkte Songs", this::showHome);
        List<JSONObject> list = savedSongs();
        boolean sample = false;
        if (list.isEmpty() && previewSavedSongs) {
            sample = true;
            try {
                list.add(new JSONObject().put("artist", "Beispiel-Interpret").put("title", "So sieht ein gemerkter Song aus").put("station", brandName()).put("t", System.currentTimeMillis()));
                list.add(new JSONObject().put("artist", "Zweiter Interpret").put("title", "Noch ein Lieblingstitel").put("station", "Beispiel-Sender").put("t", System.currentTimeMillis() - 86400000L));
            } catch (Exception ignored) {
            }
            previewSavedSongs = false;
        }
        addPageHeading("Gemerkte Songs", "Deine Merkliste");
        if (list.isEmpty()) {
            addEmpty("Noch keine Songs gemerkt. Tippe im Player auf das Lesezeichen, wenn dir ein Titel gefällt.");
            return;
        }
        View share = Ui.button(this, "Liste teilen", R.drawable.ic_share, Ui.TONAL);
        final List<JSONObject> shareList = list;
        share.setOnClickListener(v -> {
            StringBuilder b = new StringBuilder("Meine gemerkten Songs (" + brandName() + "):\n");
            for (JSONObject o : shareList) {
                String a = o.optString("artist");
                b.append("• ").append(a.isEmpty() ? "" : a + " – ").append(o.optString("title")).append('\n');
            }
            shareText(b.toString());
        });
        addToContent(share, 0, 14);
        final boolean isSample = sample;
        for (JSONObject o : list) {
            final String artist = o.optString("artist"), title = o.optString("title"), station = o.optString("station");
            LinearLayout row = Ui.hbox(this);
            row.setPadding(dp(12), dp(10), dp(6), dp(10));
            row.setBackground(Ui.rect(CARD, 18, LINE, 1));
            FrameLayout bubble = new FrameLayout(this);
            bubble.setBackground(Ui.rect(Ui.alpha(Ui.accent, 40), 14, 0, 0));
            bubble.addView(Ui.icon(this, R.drawable.ic_music, Ui.accent, 22), new FrameLayout.LayoutParams(dp(22), dp(22), Gravity.CENTER));
            row.addView(bubble, Ui.lp(dp(44), dp(44), 0, 0, 12, 0));
            LinearLayout col = Ui.vbox(this);
            TextView t = Ui.text(this, title, 15, TEXT, Ui.BOLD);
            Ui.ellipsize(t, 2);
            col.addView(t);
            String when = o.optLong("t") > 0 ? new java.text.SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY).format(new java.util.Date(o.optLong("t"))) : "";
            String sub = (artist.isEmpty() ? "" : artist) + (station.isEmpty() ? "" : (artist.isEmpty() ? "" : " · ") + station) + (when.isEmpty() ? "" : " · " + when);
            if (!sub.isEmpty()) col.addView(Ui.text(this, sub, 12.5f, MUTED, Ui.REGULAR), Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
            row.addView(col, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            ImageButton find = Ui.iconButton(this, R.drawable.ic_search, MUTED, Color.TRANSPARENT, 40, "Song suchen");
            find.setOnClickListener(v -> openSongSearch(artist, title));
            row.addView(find);
            ImageButton shareOne = Ui.iconButton(this, R.drawable.ic_share, MUTED, Color.TRANSPARENT, 40, "Song teilen");
            shareOne.setOnClickListener(v -> shareText((artist.isEmpty() ? "" : artist + " – ") + title + "\n" + brandName()));
            row.addView(shareOne);
            ImageButton del = Ui.iconButton(this, R.drawable.ic_delete, MUTED, Color.TRANSPARENT, 40, "Entfernen");
            del.setOnClickListener(v -> {
                if (isSample) return;
                toggleSaveSong(artist, title, station);
                showSavedSongs();
            });
            row.addView(del);
            addToContent(row, 0, 9);
        }
    }

    // ================================================================ Vollbild-Player

    private void showFullPlayer() {
        if (isFullPlayerOpen()) {
            refreshFullPlayerUi();
            return;
        }
        final FrameLayout ov = new FrameLayout(this);
        fullPlayerView = ov;
        fullPlayerBg = ov;
        applyFullBackground(Ui.mix(Ui.heroFrom, Ui.heroTo, 0.5f));

        ScrollView scroll = new ScrollView(this);
        scroll.setVerticalScrollBarEnabled(false);
        scroll.setOverScrollMode(View.OVER_SCROLL_NEVER);
        scroll.setFillViewport(true);
        FrameLayout holder = new FrameLayout(this);
        final LinearLayout root = Ui.vbox(this);
        root.setPadding(dp(20), dp(8), dp(20), dp(28));
        final int availW = getResources().getDisplayMetrics().widthPixels - insetLeft - insetRight;
        final int panelW = Math.min(availW, dp(520));
        holder.addView(root, new FrameLayout.LayoutParams(panelW, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.CENTER_HORIZONTAL));
        scroll.addView(holder, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        ov.addView(scroll, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        // Kopf: einklappen, „Jetzt läuft“, Cast
        LinearLayout top = Ui.hbox(this);
        ImageButton down = Ui.iconButton(this, R.drawable.ic_back, Color.WHITE, Ui.alpha(Color.WHITE, 28), 42, "Player schließen");
        down.setRotation(-90);
        down.setOnClickListener(v -> closeOverlay(ov));
        top.addView(down);
        LinearLayout mid = Ui.vbox(this);
        mid.setGravity(Gravity.CENTER_HORIZONTAL);
        TextView nowLabel = Ui.eyebrow(this, "Jetzt läuft", Ui.alpha(Color.WHITE, 190));
        nowLabel.setGravity(Gravity.CENTER);
        mid.addView(nowLabel, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        fullStation = Ui.text(this, currentStationName, 14, Color.WHITE, Ui.BOLD);
        fullStation.setSingleLine(true);
        fullStation.setEllipsize(TextUtils.TruncateAt.END);
        fullStation.setGravity(Gravity.CENTER);
        mid.addView(fullStation, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 3, 0, 0));
        fullSource = Ui.text(this, sourceLabel(), 12, Ui.alpha(Color.WHITE, 190), Ui.REGULAR);
        fullSource.setSingleLine(true);
        fullSource.setEllipsize(TextUtils.TruncateAt.END);
        fullSource.setGravity(Gravity.CENTER);
        fullSource.setVisibility(sourceLabel().isEmpty() ? View.GONE : View.VISIBLE);
        mid.addView(fullSource, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 1, 0, 0));
        top.addView(mid, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        ImageButton castBtn = Ui.iconButton(this, R.drawable.ic_cast, Color.WHITE, Ui.alpha(Color.WHITE, 28), 42, "Übertragen (Cast)");
        castBtn.setOnClickListener(v -> showCastPicker());
        castBtn.setVisibility(prefs.getBoolean("app_f_cast", true) ? View.VISIBLE : View.INVISIBLE);
        top.addView(castBtn);
        root.addView(top, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 0));

        // Titelbild: nach links/rechts wischen = Sender wechseln
        final int availH = getResources().getDisplayMetrics().heightPixels - insetTop - insetBottom;
        final int coverSize = Math.min(Math.min(panelW - dp(40), dp(360)), (int) (availH * 0.36f));
        FrameLayout coverWrap = new FrameLayout(this);
        fullCover = Ui.cover(this, 28);
        fullCover.setImageResource(R.drawable.app_logo);
        fullCover.setElevation(dp(18));
        coverWrap.addView(fullCover, new FrameLayout.LayoutParams(coverSize, coverSize, Gravity.CENTER));
        attachCoverSwipe(coverWrap, fullCover, scroll);
        LinearLayout.LayoutParams cwlp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, coverSize + dp(8));
        cwlp.topMargin = dp(22);
        root.addView(coverWrap, cwlp);

        // Titel, Interpret, Merken
        LinearLayout titleRow = Ui.hbox(this);
        LinearLayout titleCol = Ui.vbox(this);
        fullTitle = Ui.text(this, currentTitle.isEmpty() ? "Live-Stream" : currentTitle, 24, Color.WHITE, Ui.BOLD);
        Ui.ellipsize(fullTitle, 2);
        titleCol.addView(fullTitle);
        fullArtist = Ui.text(this, currentArtist.isEmpty() ? currentStationName : currentArtist, 16, Ui.alpha(Color.WHITE, 205), Ui.MEDIUM);
        Ui.ellipsize(fullArtist, 1);
        titleCol.addView(fullArtist, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 5, 0, 0));
        titleRow.addView(titleCol, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        fullSave = Ui.iconButton(this, R.drawable.ic_bookmark_outline, Color.WHITE, Ui.alpha(Color.WHITE, 28), 46, "Song merken");
        fullSave.setOnClickListener(v -> {
            haptic(v);
            toggleSaveSong(currentArtist, currentTitle, currentStationName);
            refreshFullPlayerUi();
        });
        titleRow.addView(fullSave, Ui.lp(dp(46), dp(46), 10, 0, 0, 0));
        root.addView(titleRow, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 4, 22, 4, 0));

        LinearLayout liveRow = Ui.hbox(this);
        liveRow.addView(Ui.liveBadge(this), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 0, 8, 0));
        fullEq = new Ui.Equalizer(this);
        fullEq.setColor(Color.WHITE);
        liveRow.addView(fullEq, Ui.lp(dp(16), dp(12), 0, 0, 0, 0));
        root.addView(liveRow, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 4, 12, 4, 0));

        // Bedienung: Favorit · zurück · Play · weiter · Sleep-Timer
        LinearLayout controls = Ui.hbox(this);
        controls.setGravity(Gravity.CENTER);
        fullFavorite = Ui.iconButton(this, R.drawable.ic_heart_outline, Color.WHITE, Color.TRANSPARENT, 48, "Favorit");
        fullFavorite.setOnClickListener(v -> {
            haptic(v);
            if (currentWorld != null) {
                dirFavToggle(currentWorld);
                refreshFullPlayerUi();
            } else {
                toggleFavorite(currentStationId);
            }
        });
        controls.addView(fullFavorite, centerWeight());
        ImageButton prev = Ui.iconButton(this, R.drawable.ic_prev, Color.WHITE, Color.TRANSPARENT, 58, "Voriger Sender");
        prev.setPadding(dp(14), dp(14), dp(14), dp(14));
        prev.setOnClickListener(v -> { haptic(v); switchStation(-1); });
        controls.addView(prev, centerWeight());
        fullPlay = new ImageButton(this);
        fullPlay.setImageResource(R.drawable.ic_play);
        fullPlay.setColorFilter(Ui.ON_ACCENT);
        fullPlay.setScaleType(ImageView.ScaleType.CENTER);
        fullPlay.setPadding(dp(20), dp(20), dp(20), dp(20));
        GradientDrawable playBg = new GradientDrawable(GradientDrawable.Orientation.TL_BR, new int[]{Color.WHITE, Ui.mix(Color.WHITE, Ui.accent, 0.35f)});
        playBg.setShape(GradientDrawable.OVAL);
        fullPlay.setBackground(Ui.ripple(playBg, 80));
        fullPlay.setElevation(dp(8));
        fullPlay.setContentDescription("Wiedergabe");
        fullPlay.setOnClickListener(v -> { haptic(v); togglePlayback(); });
        LinearLayout.LayoutParams plp = new LinearLayout.LayoutParams(dp(78), dp(78));
        controls.addView(fullPlay, plp);
        ImageButton next = Ui.iconButton(this, R.drawable.ic_next, Color.WHITE, Color.TRANSPARENT, 58, "Nächster Sender");
        next.setPadding(dp(14), dp(14), dp(14), dp(14));
        next.setOnClickListener(v -> { haptic(v); switchStation(1); });
        controls.addView(next, centerWeight());
        ImageButton sleep = Ui.iconButton(this, R.drawable.ic_moon, Color.WHITE, Color.TRANSPARENT, 48, "Sleep-Timer");
        sleep.setOnClickListener(v -> showSleepTimerMenu());
        controls.addView(sleep, centerWeight());
        root.addView(controls, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 18, 0, 0));

        sleepTimerLabel = Ui.text(this, "", 12.5f, Color.WHITE, Ui.MEDIUM);
        sleepTimerLabel.setGravity(Gravity.CENTER);
        sleepTimerLabel.setPadding(dp(14), dp(7), dp(14), dp(7));
        sleepTimerLabel.setBackground(Ui.rect(Ui.alpha(Color.WHITE, 30), 16, 0, 0));
        sleepTimerLabel.setVisibility(View.GONE);
        sleepTimerLabel.setOnClickListener(v -> showSleepTimerMenu());
        root.addView(sleepTimerLabel, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 14, 0, 0));
        ((LinearLayout.LayoutParams) sleepTimerLabel.getLayoutParams()).gravity = Gravity.CENTER_HORIZONTAL;

        // Lautstärke
        LinearLayout vol = Ui.hbox(this);
        vol.addView(Ui.icon(this, R.drawable.ic_volume, Ui.alpha(Color.WHITE, 170), 20));
        SeekBar volume = new SeekBar(this);
        final AudioManager audioManager = (AudioManager) getSystemService(AUDIO_SERVICE);
        volume.setMax(audioManager.getStreamMaxVolume(AudioManager.STREAM_MUSIC));
        volume.setProgress(audioManager.getStreamVolume(AudioManager.STREAM_MUSIC));
        volume.setProgressTintList(android.content.res.ColorStateList.valueOf(Color.WHITE));
        volume.setThumbTintList(android.content.res.ColorStateList.valueOf(Color.WHITE));
        volume.setProgressBackgroundTintList(android.content.res.ColorStateList.valueOf(Ui.alpha(Color.WHITE, 70)));
        volume.setOnSeekBarChangeListener(new SeekBar.OnSeekBarChangeListener() {
            @Override public void onProgressChanged(SeekBar seekBar, int progress, boolean fromUser) {
                if (fromUser) audioManager.setStreamVolume(AudioManager.STREAM_MUSIC, progress, 0);
            }
            @Override public void onStartTrackingTouch(SeekBar seekBar) {}
            @Override public void onStopTrackingTouch(SeekBar seekBar) {}
        });
        vol.addView(volume, Ui.lp(0, dp(36), 8, 0, 4, 0));
        ((LinearLayout.LayoutParams) volume.getLayoutParams()).weight = 1f;
        root.addView(vol, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 4, 18, 4, 0));

        // Weitere Aktionen
        LinearLayout actions = Ui.hbox(this);
        actions.setGravity(Gravity.CENTER);
        actions.addView(actionButton(R.drawable.ic_share, "Teilen", v -> shareCurrent()), centerWeight());
        fullSchedule = actionButton(R.drawable.ic_calendar, "Sendeplan", v -> {
            closeOverlay(ov);
            showSchedule(currentStationId);
        });
        actions.addView(fullSchedule, centerWeight());
        fullReport = actionButton(R.drawable.ic_flag, "Melden", v -> {
            Directory.Item cur = currentWorld != null ? currentWorld : directoryItemForStation(currentStationId);
            if (cur != null) showReportDialog(cur);
        });
        actions.addView(fullReport, centerWeight());
        fullHome = actionButton(R.drawable.ic_globe, hasDirectory() ? "Homepage" : "Portal", v -> openStationHome());
        actions.addView(fullHome, centerWeight());
        root.addView(actions, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 16, 0, 0));

        // Mitmachen (nur eigene Sender)
        fullCommunityWrap = Ui.vbox(this);
        fullCommunityWrap.setPadding(dp(16), dp(14), dp(16), dp(14));
        fullCommunityWrap.setBackground(Ui.rect(Ui.alpha(Color.BLACK, 70), 22, Ui.alpha(Color.WHITE, 30), 1));
        fullCommunityWrap.addView(Ui.text(this, "Mitmachen", 16, Color.WHITE, Ui.BOLD));
        LinearLayout comRow = Ui.hbox(this);
        String base = appConfig.communityBase;
        comRow.addView(actionButton(R.drawable.ic_equalizer, "Voting", v -> openInternalWeb("Sender-Song-Voting",
                base + "voting.html?mode=station&station=" + Uri.encode(currentStationId))), centerWeight());
        comRow.addView(actionButton(R.drawable.ic_music, "Wunsch", v -> openInternalWeb("Musikwunsch",
                base + "wunsch.html?station=" + Uri.encode(currentStationId))), centerWeight());
        comRow.addView(actionButton(R.drawable.ic_chat, "Studiomail", v -> openInternalWeb("Studiomail", base + "studiomail/widget-form.html")), centerWeight());
        comRow.addView(actionButton(R.drawable.ic_mic, "Voicemail", v -> openInternalWeb("Voicemail",
                base + "voicemsg.html?station=" + Uri.encode(currentStationId), true)), centerWeight());
        fullCommunityWrap.addView(comRow, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 10, 0, 0));
        root.addView(fullCommunityWrap, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 20, 0, 0));

        // Gleich · Zuletzt gespielt
        fullNextWrap = playerCard();
        fullNextWrap.addView(Ui.text(this, "Gleich", 16, Color.WHITE, Ui.BOLD));
        fullNextWrap.addView(Ui.text(this, "Wird geladen …", 13, Ui.alpha(Color.WHITE, 170), Ui.REGULAR), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 8, 0, 0));
        root.addView(fullNextWrap, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 14, 0, 0));

        fullHistoryWrap = playerCard();
        fullHistoryWrap.addView(Ui.text(this, "Zuletzt gespielt", 16, Color.WHITE, Ui.BOLD));
        fullHistoryWrap.addView(Ui.text(this, "Wird geladen …", 13, Ui.alpha(Color.WHITE, 170), Ui.REGULAR), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 8, 0, 0));
        root.addView(fullHistoryWrap, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 14, 0, 0));

        openOverlay(ov, true, true);
        refreshFullPlayerUi();
        loadFullPlayerExtras();
        updateSleepUi();
    }

    private LinearLayout.LayoutParams centerWeight() {
        return new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
    }

    private LinearLayout playerCard() {
        LinearLayout c = Ui.vbox(this);
        c.setPadding(dp(16), dp(14), dp(16), dp(10));
        c.setBackground(Ui.rect(Ui.alpha(Color.BLACK, 70), 22, Ui.alpha(Color.WHITE, 30), 1));
        return c;
    }

    /** Kleine Aktion: Symbol über Beschriftung. */
    private LinearLayout actionButton(int iconRes, String label, View.OnClickListener click) {
        LinearLayout b = Ui.vbox(this);
        b.setGravity(Gravity.CENTER_HORIZONTAL);
        b.setPadding(dp(4), dp(8), dp(4), dp(8));
        b.setClickable(true);
        b.setFocusable(true);
        b.setBackground(Ui.ripple(Ui.rect(Color.TRANSPARENT, 16, 0, 0), 16));
        b.addView(Ui.icon(this, iconRes, Color.WHITE, 22), new LinearLayout.LayoutParams(dp(22), dp(22)));
        TextView t = Ui.text(this, label, 11.5f, Ui.alpha(Color.WHITE, 205), Ui.MEDIUM);
        t.setGravity(Gravity.CENTER);
        t.setSingleLine(true);
        b.addView(t, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 5, 0, 0));
        b.setOnClickListener(v -> {
            haptic(v);
            click.onClick(v);
        });
        b.setContentDescription(label);
        return b;
    }

    /** Wischen auf dem Titelbild wechselt den Sender (links = nächster, rechts = voriger). */
    private void attachCoverSwipe(View touchArea, final View moving, final ScrollView scroll) {
        final float[] down = new float[2];
        final boolean[] tracking = {false};
        touchArea.setOnTouchListener((v, e) -> {
            switch (e.getAction()) {
                case MotionEvent.ACTION_DOWN:
                    down[0] = e.getRawX();
                    down[1] = e.getRawY();
                    tracking[0] = false;
                    return true;
                case MotionEvent.ACTION_MOVE: {
                    float dx = e.getRawX() - down[0], dy = e.getRawY() - down[1];
                    if (!tracking[0] && Math.abs(dx) > dp(12) && Math.abs(dx) > Math.abs(dy) * 1.4f) {
                        tracking[0] = true;
                        scroll.requestDisallowInterceptTouchEvent(true);
                    }
                    if (tracking[0]) {
                        moving.setTranslationX(dx * 0.45f);
                        moving.setAlpha(1f - Math.min(0.45f, Math.abs(dx) / (dp(420))));
                    }
                    return true;
                }
                case MotionEvent.ACTION_UP:
                case MotionEvent.ACTION_CANCEL: {
                    float dx = e.getRawX() - down[0];
                    boolean fire = tracking[0] && e.getAction() == MotionEvent.ACTION_UP && Math.abs(dx) > dp(70);
                    moving.animate().translationX(0).alpha(1f).setDuration(Ui.reduceMotion ? 0 : 200).start();
                    scroll.requestDisallowInterceptTouchEvent(false);
                    if (fire) {
                        haptic(v);
                        switchStation(dx < 0 ? 1 : -1);
                    } else if (!tracking[0] && e.getAction() == MotionEvent.ACTION_UP) {
                        v.performClick();
                    }
                    tracking[0] = false;
                    return true;
                }
                default:
                    return true;
            }
        });
    }

    private void applyFullBackground(int color) {
        fullBgColor = color;
        if (fullPlayerBg == null) return;
        fullPlayerBg.setBackground(new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM,
                new int[]{color, Ui.mix(color, Ui.BG, 0.72f), Ui.BG_DEEP}));
    }

    private String fullArtShown = "";

    private void refreshFullPlayerUi() {
        if (!isFullPlayerOpen()) return;

        if (fullStation != null) fullStation.setText(podcastMode ? "Podcast" : currentStationName);
        if (fullSource != null) {
            String src = sourceLabel();
            fullSource.setText(src);
            fullSource.setVisibility(src.isEmpty() ? View.GONE : View.VISIBLE);
        }
        if (fullHome != null) {
            boolean noHome = hasDirectory() && !podcastMode && currentWorld != null && currentWorld.link.isEmpty();
            fullHome.setVisibility(noHome ? View.GONE : View.VISIBLE);
        }
        if (fullArtist != null) fullArtist.setText(
                podcastMode ? "Podcast" :
                        (currentArtist.isEmpty() ? currentStationName : currentArtist));
        if (fullTitle != null) fullTitle.setText(currentTitle.isEmpty() ? "Live-Stream" : currentTitle);

        String art = !currentArtwork.isEmpty() ? currentArtwork : currentStationCover;
        if (fullCover != null && !art.equals(fullArtShown)) {
            fullArtShown = art;
            if (!art.isEmpty()) {
                final String shown = art;
                loadImage(art, fullCover, bmp -> {
                    if (shown.equals(fullArtShown)) applyFullBackground(Ui.dominantColor(bmp, Ui.mix(Ui.heroFrom, Ui.heroTo, 0.5f)));
                });
            } else {
                fullCover.setImageResource(R.drawable.app_logo);
                applyFullBackground(Ui.mix(Ui.heroFrom, Ui.heroTo, 0.5f));
            }
        }

        updatePlayButtons();
        if (fullFavorite != null) {
            fullFavorite.setEnabled(!podcastMode);
            fullFavorite.setAlpha(podcastMode ? 0.35f : 1f);
            boolean fav = currentWorld != null ? dirFavHas(currentWorld) : getFavorites().contains(currentStationId);
            fullFavorite.setImageResource(fav ? R.drawable.ic_heart : R.drawable.ic_heart_outline);
            fullFavorite.setColorFilter(fav ? Ui.accent : Color.WHITE);
        }
        if (fullSave != null) {
            boolean saved = isSongSaved(currentArtist, currentTitle);
            fullSave.setImageResource(saved ? R.drawable.ic_bookmark : R.drawable.ic_bookmark_outline);
            fullSave.setColorFilter(saved ? Ui.accent : Color.WHITE);
            fullSave.setVisibility(podcastMode ? View.INVISIBLE : View.VISIBLE);
        }
        if (fullSchedule != null) fullSchedule.setVisibility(currentWorld != null || podcastMode ? View.GONE : View.VISIBLE);
        if (fullReport != null) {
            Directory.Item cur = currentWorld != null ? currentWorld : directoryItemForStation(currentStationId);
            fullReport.setVisibility(hasDirectory() && reportAllowed() && !podcastMode && cur != null && !cur.own ? View.VISIBLE : View.GONE);
        }
        if (fullCommunityWrap != null) {
            fullCommunityWrap.setVisibility(!podcastMode && appConfig.hasCommunity() && appConfig.isOwned(currentStationId)
                    ? View.VISIBLE : View.GONE);
        }
        if (fullNextWrap != null) fullNextWrap.setVisibility(podcastMode || currentWorld != null ? View.GONE : View.VISIBLE);
        if (fullHistoryWrap != null) fullHistoryWrap.setVisibility(podcastMode || currentWorld != null ? View.GONE : View.VISIBLE);
    }

    /** Quelle des laufenden Senders (nur Marken mit Radioverzeichnis): laut.fm bzw. Radio Browser bei Fremdsendern. */
    private String sourceLabel() {
        if (!hasDirectory() || podcastMode) return "";
        if (currentWorld != null) return "Quelle: Radio Browser";
        return Stations.custom(prefs, currentStationId) != null ? "Quelle: " + brandName() : "Quelle: laut.fm";
    }

    /**
     * Homepage-Knopf im Player. Marken mit Radioverzeichnis: Fremdsender -> Homepage des Senders, eigene Sender -> Website der Marke.
     * Andere Marken: die Website der Marke.
     */
    private void openStationHome() {
        if (!hasDirectory() || podcastMode) {
            openInternalWeb(brandName(), appConfig.website);
            return;
        }
        if (currentWorld != null) {
            if (currentWorld.link.isEmpty()) {
                Toast.makeText(this, "Dieser Sender hat keine Homepage angegeben.", Toast.LENGTH_SHORT).show();
                return;
            }
            openInternalWeb(currentWorld.name, currentWorld.link);
        } else if (appConfig.isOwned(currentStationId)) {
            openInternalWeb(brandName(), appConfig.website);
        } else {
            openInternalWeb(currentStationName, "https://laut.fm/" + Uri.encode(currentStationId));
        }
    }

    private void shareCurrent() {
        String label = podcastMode
                ? "Podcast – " + currentTitle
                : currentStationName + " – " +
                  (currentArtist.isEmpty() ? "" : currentArtist + " – ") + currentTitle;
        String url = podcastMode
                ? appConfig.website
                : (currentWorld != null
                        ? (currentWorld.link.isEmpty() ? currentWorld.stream : currentWorld.link)
                        : "https://laut.fm/" + currentStationId);

        Intent send = new Intent(Intent.ACTION_SEND);
        send.setType("text/plain");
        send.putExtra(Intent.EXTRA_SUBJECT, label);
        send.putExtra(Intent.EXTRA_TEXT, label + "\n" + url);
        startActivity(Intent.createChooser(send, "Teilen"));
    }

    // ---------- Sleep-Timer ----------

    private String sleepRemainingText() {
        long ms = Math.max(0, sleepEndsAt - System.currentTimeMillis());
        long s = (ms + 999) / 1000;
        return String.format(Locale.GERMANY, "%d:%02d", s / 60, s % 60);
    }

    private void showSleepTimerMenu() {
        final Sheet sheet = new Sheet("Sleep-Timer");
        LinearLayout body = sheet.body;
        boolean active = sleepEndsAt > System.currentTimeMillis();
        if (active) {
            TextView st = Ui.text(this, "Läuft noch " + sleepRemainingText() + " Minuten", 15, Ui.accent, Ui.BOLD);
            body.addView(st, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 4, 0, 10));
        } else {
            TextView hint = Ui.text(this, "Die Wiedergabe pausiert automatisch.", 13, Ui.TEXT_2, Ui.REGULAR);
            body.addView(hint, Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 2, 0, 12));
        }
        int[] minutes = {15, 30, 45, 60, 90, 120};
        LinearLayout row = null;
        for (int i = 0; i < minutes.length; i++) {
            if (i % 3 == 0) {
                row = new LinearLayout(this);
                row.setOrientation(LinearLayout.HORIZONTAL);
                body.addView(row, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, i == 0 ? 0 : 10, 0, 0));
            }
            final int m = minutes[i];
            LinearLayout cell = Ui.vbox(this);
            cell.setGravity(Gravity.CENTER);
            cell.setPadding(0, dp(14), 0, dp(14));
            cell.setBackground(Ui.surface(18));
            cell.setClickable(true);
            TextView mv = Ui.text(this, String.valueOf(m), 22, TEXT, Ui.BOLD);
            mv.setGravity(Gravity.CENTER);
            TextView ml = Ui.text(this, "Minuten", 11.5f, MUTED, Ui.MEDIUM);
            ml.setGravity(Gravity.CENTER);
            cell.addView(mv, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            cell.addView(ml, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            cell.setOnClickListener(v -> {
                haptic(v);
                setSleepTimer(m);
                sheet.close();
            });
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
            if (i % 3 != 0) lp.leftMargin = dp(10);
            row.addView(cell, lp);
        }
        if (active) {
            View off = Ui.button(this, "Timer ausschalten", R.drawable.ic_close, Ui.DANGER);
            off.setOnClickListener(v -> {
                cancelSleepTimer();
                sheet.close();
            });
            body.addView(off, Ui.lp(ViewGroup.LayoutParams.MATCH_PARENT, dp(48), 0, 16, 0, 0));
        }
        sheet.show();
    }

    private void setSleepTimer(int minutes) {
        cancelSleepTimer();
        sleepEndsAt = System.currentTimeMillis() + minutes * 60L * 1000L;
        sleepTimerRunnable = () -> {
            if (controller != null) {
                controller.pause();
                controller.setVolume(1f);
            }
            NetCast nc = NetCast.get(this);
            if (nc.isActive() && nc.isPlaying()) nc.togglePause();
            sleepTimerRunnable = null;
            sleepEndsAt = 0;
            updateSleepUi();
            Toast.makeText(this, "Sleep-Timer beendet – gute Nacht!", Toast.LENGTH_LONG).show();
        };
        main.postDelayed(sleepTimerRunnable, minutes * 60L * 1000L);
        sleepTickRunnable = new Runnable() {
            @Override public void run() {
                long left = sleepEndsAt - System.currentTimeMillis();
                if (sleepEndsAt == 0 || left <= 0) return;
                if (left <= 20000 && prefs.getBoolean("set_fade", true) && controller != null
                        && !NetCast.get(MainActivity.this).isActive()) {
                    controller.setVolume(Math.max(0.05f, left / 20000f));
                }
                updateSleepUi();
                main.postDelayed(this, 1000);
            }
        };
        main.post(sleepTickRunnable);
        Toast.makeText(this, "Sleep-Timer auf " + minutes + " Minuten gesetzt.", Toast.LENGTH_SHORT).show();
        updateSleepUi();
    }

    private void cancelSleepTimer() {
        if (sleepTimerRunnable != null) {
            main.removeCallbacks(sleepTimerRunnable);
            sleepTimerRunnable = null;
        }
        if (sleepTickRunnable != null) {
            main.removeCallbacks(sleepTickRunnable);
            sleepTickRunnable = null;
        }
        boolean wasActive = sleepEndsAt != 0;
        sleepEndsAt = 0;
        if (wasActive && controller != null) controller.setVolume(1f);
        updateSleepUi();
    }

    private void updateSleepUi() {
        boolean active = sleepEndsAt > System.currentTimeMillis();
        if (miniSleep != null) {
            miniSleep.setVisibility(active ? View.VISIBLE : View.GONE);
            if (active) miniSleep.setText("☾ " + sleepRemainingText());
        }
        if (sleepTimerLabel != null) {
            sleepTimerLabel.setVisibility(active ? View.VISIBLE : View.GONE);
            if (active) sleepTimerLabel.setText("☾  Sleep-Timer · noch " + sleepRemainingText());
        }
    }

    // ---------- Gleich / Zuletzt gespielt ----------

    private void renderNextArtists(String stationId, List<String> names) {
        if (fullNextWrap == null || !stationId.equals(currentStationId)) return;
        fullNextWrap.removeAllViews();
        fullNextWrap.addView(Ui.text(this, "Gleich", 16, Color.WHITE, Ui.BOLD));

        if (names.isEmpty()) {
            fullNextWrap.addView(Ui.text(this, "Keine Vorschau verfügbar.", 13, Ui.alpha(Color.WHITE, 170), Ui.REGULAR), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 8, 0, 4));
            return;
        }
        for (String name : names) {
            LinearLayout line = Ui.hbox(this);
            line.setPadding(0, dp(8), 0, dp(4));
            line.addView(Ui.icon(this, R.drawable.ic_music, Ui.alpha(Color.WHITE, 150), 16), Ui.lp(dp(16), dp(16), 0, 0, 10, 0));
            TextView artist = Ui.text(this, name, 14, Color.WHITE, Ui.MEDIUM);
            artist.setSingleLine(true);
            artist.setEllipsize(TextUtils.TruncateAt.END);
            line.addView(artist, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            fullNextWrap.addView(line);
        }
    }

    private void renderHistory(String stationId, List<String[]> rows) {
        if (fullHistoryWrap == null || !stationId.equals(currentStationId)) return;
        fullHistoryWrap.removeAllViews();
        fullHistoryWrap.addView(Ui.text(this, "Zuletzt gespielt", 16, Color.WHITE, Ui.BOLD));

        if (rows.isEmpty()) {
            fullHistoryWrap.addView(Ui.text(this, "Noch kein Verlauf verfügbar.", 13, Ui.alpha(Color.WHITE, 170), Ui.REGULAR), Ui.lp(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, 0, 8, 0, 4));
            return;
        }
        for (String[] row : rows) {
            String artist = row[0] == null ? "" : row[0].trim();
            String title = row[1] == null ? "" : row[1].trim();
            fullHistoryWrap.addView(songLine(artist, title, currentStationName, false));
        }
    }

    private void showNewsArticle(JSONObject a) {
        String title = a.optString("title", "News");
        openInternalWebHtml(title, newsArticleHtml(a));
    }

    private String newsArticleHtml(JSONObject a) {
        String category = a.optString("category", "News");
        String title = a.optString("title", "");
        String date = newsDate(a.optString("published_at", a.optString("created_at", "")));
        String author = a.optString("author", "");
        String meta = date + (author.isEmpty() ? "" : " · " + author);
        String image = a.optString("image_url", "");
        String imageMode = a.optString("image_mode", "thumbnail");
        boolean showArticleImage = image.trim().length() > 0 &&
                ("article".equals(imageMode) || "both".equals(imageMode));
        String video = a.optString("video_url", "");
        String embed = a.optString("embed_html", "");
        String body = a.optString("body_html", "");
        String externalUrl = a.optString("external_url", "");

        StringBuilder media = new StringBuilder();
        String youtubeId = youtubeIdFromUrl(video);
        if (!youtubeId.isEmpty()) {
            media.append("<div class=\"media\"><iframe src=\"https://www.youtube-nocookie.com/embed/")
                    .append(youtubeId)
                    .append("\" allow=\"autoplay; encrypted-media; picture-in-picture; fullscreen\" allowfullscreen></iframe></div>");
        }
        if (!embed.trim().isEmpty()) media.append("<div class=\"media\">").append(embed).append("</div>");

        StringBuilder html = new StringBuilder();
        html.append("<!doctype html><html><head><meta charset=\"utf-8\">")
                .append("<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">")
                .append("<style>")
                .append("body{margin:0;padding:18px;background:#07091c;color:#eef0fa;font-family:sans-serif;line-height:1.68;font-size:15px}")
                .append("h1,h2,h3{color:#fff}a{color:#00f2ea}")
                .append(".kicker{color:#b57cff;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px}")
                .append("h1.title{font-size:1.7rem;line-height:1.2;margin:8px 0 6px;color:#fff}")
                .append(".meta{color:#9ea6c7;font-size:.78rem;margin-bottom:14px}")
                .append("img{max-width:100%;height:auto;border-radius:14px}")
                .append(".hero{width:100%;border-radius:16px;object-fit:cover;max-height:260px;margin-bottom:14px}")
                .append("blockquote{margin:1.2em 0;padding:10px 14px;border-left:3px solid #b57cff;background:rgba(181,124,255,.08);border-radius:0 10px 10px 0}")
                .append(".media{margin:16px 0;border-radius:14px;overflow:hidden;background:#000}")
                .append(".media iframe{display:block;width:100%;aspect-ratio:16/9;border:0;min-height:200px}")
                .append(".more{display:inline-block;margin-top:20px;padding:12px 18px;border-radius:12px;background:#b57cff;color:#120a27;font-weight:800;text-decoration:none}")
                .append("</style></head><body>")
                .append("<div class=\"kicker\">").append(escapeHtml(category)).append("</div>")
                .append("<h1 class=\"title\">").append(escapeHtml(title)).append("</h1>");
        if (!meta.trim().isEmpty()) html.append("<div class=\"meta\">").append(escapeHtml(meta)).append("</div>");
        if (showArticleImage) html.append("<img class=\"hero\" src=\"").append(escapeHtml(image)).append("\">");
        html.append(media).append(body);
        if (!externalUrl.trim().isEmpty()) {
            html.append("<a class=\"more\" href=\"").append(escapeHtml(externalUrl)).append("\">Mehr erfahren ↗</a>");
        }
        html.append("</body></html>");
        return html.toString();
    }

    private String escapeHtml(String s) {
        if (s == null) return "";
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("\"", "&quot;");
    }

    private String youtubeIdFromUrl(String url) {
        if (url == null) return "";
        java.util.regex.Matcher m = java.util.regex.Pattern
                .compile("(?:youtube\\.com/(?:watch\\?v=|shorts/|embed/)|youtu\\.be/)([A-Za-z0-9_-]{6,20})", java.util.regex.Pattern.CASE_INSENSITIVE)
                .matcher(url);
        return m.find() ? m.group(1) : "";
    }

    private void openInternalWebHtml(String title, String html) {
        Intent intent = new Intent(this, WidgetActivity.class);
        intent.putExtra("title", title);
        intent.putExtra("html", html);
        startActivity(intent);
    }

    private void loadFullPlayerExtras() {
        if (podcastMode || currentStationId == null) return;
        if (currentWorld != null) {
            if (fullNextWrap != null) fullNextWrap.removeAllViews();
            if (fullHistoryWrap != null) fullHistoryWrap.removeAllViews();
            return;
        }
        final String stationId = currentStationId;

        io.execute(() -> {
            try {
                JSONArray next = getJsonArray(lautApi(stationId) + "/next_artists");
                List<String> names = new ArrayList<>();
                for (int i = 0; i < next.length() && names.size() < 6; i++) {
                    String name = extractArtistName(next.opt(i));
                    name = cleanDisplayText(name);
                    if (!name.isEmpty() && !names.contains(name)) names.add(name);
                }
                main.post(() -> renderNextArtists(stationId, names));
            } catch (Exception ignored) {
                main.post(() -> renderNextArtists(stationId, new ArrayList<>()));
            }
        });

        io.execute(() -> {
            try {
                JSONArray history = getJsonArray(lautApi(stationId) + "/last_songs");
                List<String[]> rows = new ArrayList<>();
                for (int i = 0; i < history.length() && rows.size() < 6; i++) {
                    JSONObject song = history.optJSONObject(i);
                    if (song == null) continue;
                    String title = cleanDisplayText(song.optString("title", ""));
                    String artist = cleanDisplayText(extractArtistName(song.opt("artist")));
                    if (title.isEmpty() && artist.isEmpty()) continue;
                    rows.add(new String[]{artist, title});
                }
                main.post(() -> renderHistory(stationId, rows));
            } catch (Exception ignored) {
                main.post(() -> renderHistory(stationId, new ArrayList<>()));
            }
        });
    }

    private String extractArtistName(Object value) {
        if (value == null || value == JSONObject.NULL) return "";
        if (value instanceof String) return (String) value;
        if (value instanceof JSONObject) {
            JSONObject o = (JSONObject) value;
            String name = o.optString("name", "");
            if (name.isEmpty()) name = o.optString("artist_name", "");
            if (name.isEmpty()) {
                Object nested = o.opt("artist");
                if (nested != null && nested != value) name = extractArtistName(nested);
            }
            return name;
        }
        if (value instanceof JSONArray) {
            JSONArray a = (JSONArray) value;
            return a.length() > 0 ? extractArtistName(a.opt(0)) : "";
        }
        return String.valueOf(value);
    }

    private String cleanDisplayText(String value) {
        if (value == null) return "";
        String cleaned;
        if (Build.VERSION.SDK_INT >= 24) {
            cleaned = Html.fromHtml(value, Html.FROM_HTML_MODE_LEGACY).toString();
        } else {
            cleaned = Html.fromHtml(value).toString();
        }
        cleaned = cleaned
                .replace('\uFFFD', ' ')
                .replace('\u0000', ' ')
                .replaceAll("[\\p{Cntrl}&&[^\r\n\t]]", " ")
                .replaceAll("\\s+", " ")
                .trim();
        return cleaned;
    }

    private void playRadioStation(StationInfo info) {
        if (controller == null) {
            Toast.makeText(this, "Player startet noch …", Toast.LENGTH_SHORT).show();
            return;
        }

        MediaMetadata.Builder meta = new MediaMetadata.Builder()
                .setTitle(info.name)
                .setArtist(brandName());
        if (!info.cover.isEmpty()) meta.setArtworkUri(Uri.parse(info.cover));

        MediaItem item = new MediaItem.Builder()
                .setMediaId("station:" + info.id)
                .setUri(Stations.streamUrl(prefs, info.id))
                .setMimeType(Stations.mime(Stations.streamUrl(prefs, info.id), ""))
                .setMediaMetadata(meta.build())
                .build();

        if (netCastPlay(item)) return;
        controller.setMediaItem(item);
        controller.prepare();
        controller.play();
    }

    private void playPodcast(String title, String audioUrl, String image) {
        if (audioUrl == null || audioUrl.trim().isEmpty()) return;
        if (controller == null) {
            Toast.makeText(this, "Player startet noch …", Toast.LENGTH_SHORT).show();
            return;
        }

        podcastMode = true;
        currentArtist = "Podcast";
        currentTitle = title;
        currentArtwork = image == null ? "" : image;

        miniStation.setText("Podcast");
        miniTitle.setText(title);
        if (!currentArtwork.isEmpty()) loadImage(currentArtwork, miniCover);

        MediaMetadata.Builder meta = new MediaMetadata.Builder()
                .setTitle(title)
                .setArtist("Podcast");
        if (!currentArtwork.isEmpty()) meta.setArtworkUri(Uri.parse(currentArtwork));

        MediaItem item = new MediaItem.Builder()
                .setMediaId("podcast:" + title)
                .setUri(audioUrl)
                .setMimeType(Stations.mime(audioUrl, ""))
                .setMediaMetadata(meta.build())
                .build();

        if (netCastPlay(item)) {
            refreshFullPlayerUi();
            return;
        }
        controller.setMediaItem(item);
        controller.prepare();
        controller.play();
        refreshFullPlayerUi();
    }

    private void togglePlayback() {
        if (NetCast.get(this).isActive()) {
            NetCast.get(this).togglePause();
            return;
        }
        if (controller == null) {
            Toast.makeText(this, "Player startet noch …", Toast.LENGTH_SHORT).show();
            return;
        }

        if (controller.getMediaItemCount() == 0) {
            if (currentWorld != null) {
                playWorld(currentWorld);
                return;
            }
            StationInfo info = stationCache.get(currentStationId);
            if (info != null) playRadioStation(info);
            else loadStationInfo(currentStationId, this::playRadioStation);
            return;
        }

        if (controller.isPlaying()) controller.pause();
        else controller.play();
    }

    private void loadNowPlaying(String stationId) {
        io.execute(() -> {
            try {
                JSONObject song = getJson(lautApi(stationId) + "/current_song");
                String title = song.optString("title", "");
                String artist = "";
                Object artistObj = song.opt("artist");
                if (artistObj instanceof JSONObject) {
                    artist = ((JSONObject) artistObj).optString("name", "");
                } else if (artistObj != null) {
                    artist = String.valueOf(artistObj);
                }

                final String fArtist = artist;
                final String fTitle = title.trim().isEmpty() ? "Live-Stream" : title;

                main.post(() -> {
                    if (!stationId.equals(currentStationId) || podcastMode) return;
                    currentArtist = fArtist;
                    currentTitle = fTitle;
                    miniTitle.setText(
                            fArtist.trim().isEmpty() ? fTitle : fArtist + " – " + fTitle);
                    refreshFullPlayerUi();
                    updatePlaybackMetadata();
                });

                String artwork = findArtwork(artist, title);
                if (!artwork.isEmpty()) {
                    main.post(() -> {
                        if (!stationId.equals(currentStationId) || podcastMode) return;
                        currentArtwork = artwork;
                        loadImage(artwork, miniCover);
                        refreshFullPlayerUi();
                        updatePlaybackMetadata();
                    });
                }
                main.post(() -> {
                    if (stationId.equals(currentStationId)) castPushInfo();
                });
            } catch (Exception ignored) {
            }
        });
    }

    private String findArtwork(String artist, String title) {
        if (artist == null || title == null || artist.trim().isEmpty() || title.trim().isEmpty()) return "";
        try {
            String q = URLEncoder.encode(artist + " " + title, "UTF-8");
            JSONObject data = getJson("https://itunes.apple.com/search?term=" + q + "&entity=song&limit=1");
            JSONArray results = data.optJSONArray("results");
            if (results == null || results.length() == 0) return "";
            String url = results.optJSONObject(0).optString("artworkUrl100", "");
            if (url.isEmpty()) return "";
            return url.replace("100x100bb.jpg", "600x600bb.jpg");
        } catch (Exception ignored) {
            return "";
        }
    }

    private void loadStationInfo(String stationId, java.util.function.Consumer<StationInfo> callback) {
        StationInfo cached = stationCache.get(stationId);
        if (cached != null) {
            callback.accept(cached);
            return;
        }
        final JSONObject own = Stations.custom(prefs, stationId);
        if (own != null && own.optString("laut").isEmpty()) {   // eigener Sender mit beliebigem Stream: Angaben stehen in der Konfiguration, kein laut.fm-Abruf
            StationInfo info = new StationInfo(stationId, own.optString("title", stationId), "Live-Stream", own.optString("logo", ""), new ArrayList<>());
            synchronized (stationCache) { stationCache.put(stationId, info); }
            callback.accept(info);
            return;
        }

        io.execute(() -> {
            try {
                JSONObject station = getJson(lautApi(stationId));
                String name = station.optString("display_name", prettyName(stationId));
                String description = station.optString("description", "");
                if (description.length() > 110) description = description.substring(0, 107) + "…";

                JSONObject images = station.optJSONObject("images");
                String cover = "";
                if (images != null) {
                    cover = images.optString("station_640x640",
                            images.optString("station_600x600",
                                    images.optString("station_120x120", "")));
                }

                List<String> genres = new ArrayList<>();
                JSONArray g = station.optJSONArray("genres");
                if (g != null) {
                    for (int i = 0; i < g.length(); i++) genres.add(g.optString(i, ""));
                }

                StationInfo info = new StationInfo(
                        stationId, name,
                        description.trim().isEmpty() ? "laut.fm Live-Stream" : description,
                        cover, genres);
                synchronized (stationCache) { stationCache.put(stationId, info); }
                if (!cover.isEmpty()) prefs.edit().putString("cover_" + stationId, cover).apply(); // für Android Auto
                main.post(() -> callback.accept(info));
            } catch (Exception e) {
                StationInfo fallback = new StationInfo(
                        stationId, prettyName(stationId), "laut.fm Live-Stream", "",
                        new ArrayList<>());
                synchronized (stationCache) { stationCache.put(stationId, fallback); }
                main.post(() -> callback.accept(fallback));
            }
        });
    }

    // ------------------------------------------------------------------ Radioverzeichnis (nur Marken mit Verzeichnis)

    /** Radioverzeichnis: im Build der Marke vorgesehen und im CMS (Bereich Apps) nicht abgeschaltet. */
    private boolean hasDirectory() {
        return getResources().getBoolean(R.bool.has_directory) && prefs.getBoolean("app_f_directory", true);
    }

    private final java.util.concurrent.atomic.AtomicBoolean updateCancelled = new java.util.concurrent.atomic.AtomicBoolean(false);

    /** Update laden (mit Fortschritt), prüfen und dem Android-Installer übergeben. Ohne direkten Download-Link: Seite öffnen. */
    private void startUpdate(JSONObject up) {
        final AppUpdater.Info info = new AppUpdater.Info(up);
        final String updPkg = up.optString("package", "");
        if (!BuildConfig.SELF_UPDATE || !info.usable() || (!updPkg.isEmpty() && !updPkg.equals(getPackageName()))) {   // Store-Pakete: Update über den Store bzw. die Seite
            String page = up.optString("page");
            openExternal(page.isEmpty() ? up.optString("url") : page);
            return;
        }
        updateCancelled.set(false);
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(22), dp(14), dp(22), dp(6));
        final TextView label = text("Update wird geladen …", 13, TEXT, false);
        final ProgressBar bar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        bar.setMax(100);
        box.addView(label);
        box.addView(bar, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        final android.app.AlertDialog dlg = dlg()
                .setTitle(brandName() + " " + info.latest)
                .setView(box)
                .setCancelable(false)
                .setNegativeButton("Abbrechen", (d, w) -> updateCancelled.set(true))
                .create();
        dlg.show();
        io.execute(() -> {
            try {
                final java.io.File f = AppUpdater.download(this, info, pc -> {
                    main.post(() -> {
                        bar.setProgress(pc);
                        label.setText("Update wird geladen … " + pc + " %");
                    });
                    return !updateCancelled.get();
                });
                main.post(() -> {
                    dlg.dismiss();
                    afterUpdateDownload(f, up);
                });
            } catch (Exception e) {
                final boolean cancelled = updateCancelled.get();
                main.post(() -> {
                    dlg.dismiss();
                    if (cancelled) return;
                    dlg()
                            .setTitle("Update nicht möglich")
                            .setMessage("Das Update konnte nicht geladen werden (" + (e.getMessage() == null ? "Fehler" : e.getMessage())
                                    + "). Du kannst es auch über die Download-Seite holen.")
                            .setPositiveButton("Download-Seite", (d, w) -> openExternal(up.optString("page")))
                            .setNegativeButton("Schließen", null)
                            .show();
                });
            }
        });
    }

    private void afterUpdateDownload(java.io.File f, JSONObject up) {
        if (!AppUpdater.sameSigner(this, f)) {
            dlg()
                    .setTitle("Update nicht direkt installierbar")
                    .setMessage("Diese Version ist mit einem anderen Schlüssel signiert als die installierte. Android erlaubt deshalb kein Update "
                            + "über die vorhandene App. Bitte die App einmal deinstallieren und die neue Version von der Download-Seite installieren "
                            + "(Favoriten im Konto sichern). Spätere Updates laufen dann automatisch.")
                    .setPositiveButton("Download-Seite", (d, w) -> openExternal(up.optString("page")))
                    .setNegativeButton("Schließen", null)
                    .show();
            return;
        }
        if (!AppUpdater.canInstall(this)) {
            dlg()
                    .setTitle("Installation erlauben")
                    .setMessage("Damit sich " + brandName() + " selbst aktualisieren kann, muss Android Installationen aus dieser App erlauben. "
                            + "Aktiviere die Freigabe in den Einstellungen und tippe danach noch einmal auf „Jetzt aktualisieren“.")
                    .setPositiveButton("Einstellungen öffnen", (d, w) -> AppUpdater.openInstallSettings(this))
                    .setNegativeButton("Abbrechen", null)
                    .show();
            return;
        }
        try {
            AppUpdater.install(this, f);
        } catch (Exception e) {
            openExternal(up.optString("page"));
        }
    }

    private long lastAppConfigCheck = 0;

    @Override
    protected void onResume() {
        super.onResume();
        // regelmäßig auf neue Versionen/Hinweise prüfen, auch wenn die App lange im Hintergrund lief
        if (lastAppConfigCheck != 0 && System.currentTimeMillis() - lastAppConfigCheck > 6L * 3600 * 1000) refreshAppConfig();
    }

    private boolean reportAllowed() {
        return dirReportOn && prefs.getBoolean("app_f_report", true);
    }

    /** Build-Nummer (versionCode): erlaubt dem Server, neuere Builds mit gleichem Versionsnamen zu erkennen. */
    private int appVersionCode() {
        try {
            android.content.pm.PackageInfo pi = getPackageManager().getPackageInfo(getPackageName(), 0);
            //noinspection deprecation
            return android.os.Build.VERSION.SDK_INT >= 28 ? (int) pi.getLongVersionCode() : pi.versionCode;
        } catch (Exception e) {
            return 0;
        }
    }

    private String appVersion() {
        try {
            String v = getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
            return v == null ? "" : v;
        } catch (Exception e) {
            return "";
        }
    }

    private android.app.AlertDialog requiredUpdateDialog;

    /** Einstellungen aus dem CMS (Bereich Apps): Funktionen ein/aus, Hinweis an alle Nutzer, Update-Hinweis. */
    /** Zufällige, anonyme Installations-Kennung (nur für Nutzungszahlen/Ausrollen; der Server speichert nur einen Hash). */
    private String installId() {
        String id = prefs.getString("install_id", "");
        if (id.isEmpty()) {
            id = UUID.randomUUID().toString();
            prefs.edit().putString("install_id", id).apply();
        }
        return id;
    }

    private android.app.AlertDialog maintenanceDialog;

    // ---------- Hörstatistik (nur wenn im CMS eingeschaltet): Sender + Dauer je Hörsitzung, anonym ----------
    private long listenStart = 0;
    private String listenKey = "";

    private String listenStationKey() {
        if (podcastMode) return "podcast";
        if (currentWorld != null) {
            String slug = currentWorld.name.toLowerCase(Locale.ROOT).replaceAll("[^a-z0-9]+", "-").replaceAll("^-+|-+$", "");
            return slug.isEmpty() ? "fremd-stream" : "world:" + (slug.length() > 50 ? slug.substring(0, 50) : slug);
        }
        return currentStationId == null ? "" : currentStationId.toLowerCase(Locale.ROOT);
    }

    private void listenTick(boolean playing) {
        if (playing) {
            if (listenStart == 0) {
                listenStart = android.os.SystemClock.elapsedRealtime();
                listenKey = listenStationKey();
            }
        } else {
            listenFlush();
        }
    }

    private void listenFlush() {
        if (listenStart == 0) return;
        final long sec = (android.os.SystemClock.elapsedRealtime() - listenStart) / 1000;
        final String key = listenKey;
        listenStart = 0;
        if (sec < 10 || key.isEmpty() || !prefs.getBoolean("app_t_listen", false)) return;
        final String did = installId();
        final String site = siteBase();
        final String ver = appVersion();
        new Thread(() -> ErrorReporter.listen(site, did, key, (int) Math.min(sec, 21600), ver)).start();
    }

    private void refreshAppConfig() {
        lastAppConfigCheck = System.currentTimeMillis();
        io.execute(() -> {
            try {
                JSONObject d = getJson(siteBase() + "/cms/api.php?action=app_config&platform=android&brand=" + BuildConfig.FLAVOR + "&version="
                        + URLEncoder.encode(appVersion(), "UTF-8") + "&code=" + appVersionCode() + "&did=" + installId());
                if (!"ok".equals(d.optString("status"))) return;
                JSONObject tm = d.optJSONObject("telemetry");
                boolean errorsOn = tm != null && tm.optBoolean("errors", false);
                prefs.edit().putBoolean("app_t_errors", errorsOn)
                        .putBoolean("app_t_listen", tm != null && tm.optBoolean("listen", false)).apply();
                ErrorReporter.flushPending(prefs, siteBase(), errorsOn);
                JSONObject lay = d.optJSONObject("layout");
                String layBefore = prefs.getString("app_layout", "");
                prefs.edit().putString("app_layout", lay == null ? "" : lay.toString()).apply();
                main.post(this::selectDefaultStationIfNone);
                final boolean layChanged = !layBefore.equals(lay == null ? "" : lay.toString());
                JSONObject f = d.optJSONObject("features");
                String before = prefs.getBoolean("app_f_directory", true) + "|" + prefs.getBoolean("app_f_assistant", true)
                        + "|" + prefs.getBoolean("app_f_report", true);
                prefs.edit()
                        .putBoolean("app_f_directory", f == null || f.optBoolean("directory", true))
                        .putBoolean("app_f_assistant", f == null || f.optBoolean("assistant", true))
                        .putBoolean("app_f_report", f == null || f.optBoolean("report", true))
                        .putBoolean("app_f_cast", f == null || f.optBoolean("cast", true))
                        .apply();
                String after = prefs.getBoolean("app_f_directory", true) + "|" + prefs.getBoolean("app_f_assistant", true)
                        + "|" + prefs.getBoolean("app_f_report", true);
                main.post(() -> {
                    applyAssistantConfig();
                    if (layChanged) applyTheme();
                    if ((!before.equals(after) || layChanged) && "home".equals(currentScreen)) showHome();
                    showAppNotices(d);
                });
            } catch (Exception ignored) {
            }
        });
    }

    /** Pflicht-Update (blockiert), sonst Hinweis des Studios (einmal je Text), sonst Update-Hinweis (einmal je Version). */
    private void showAppNotices(JSONObject d) {
        if (isFinishing()) return;
        String shot = getIntent().getStringExtra("screenshot_screen");
        if (shot != null && !shot.trim().isEmpty()) return;
        JSONObject mt = d.optJSONObject("maintenance");
        if (mt == null) {
            if (maintenanceDialog != null) {
                try { maintenanceDialog.dismiss(); } catch (Exception ignored) { }
                maintenanceDialog = null;
            }
        } else {
            if (maintenanceDialog != null && maintenanceDialog.isShowing()) return;
            maintenanceDialog = dlg()
                    .setTitle(mt.optString("title"))
                    .setMessage(mt.optString("text"))
                    .setCancelable(false)
                    .setPositiveButton("Erneut prüfen", null)
                    .create();
            maintenanceDialog.show();
            // Knopf schließt nicht: erst wenn die Wartung im CMS beendet ist, verschwindet die Sperre
            maintenanceDialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> refreshAppConfig());
            return;
        }
        JSONObject up = d.optJSONObject("update");
        JSONObject n = d.optJSONObject("notice");
        final String target = up == null ? "" : (!up.optString("url").isEmpty() ? up.optString("url") : up.optString("page"));
        if (up != null && up.optBoolean("required")) {
            if (requiredUpdateDialog != null && requiredUpdateDialog.isShowing()) return;
            requiredUpdateDialog = dlg()
                    .setTitle("Update erforderlich")
                    .setMessage("Diese Version von " + brandName() + " wird nicht mehr unterstützt. Bitte aktualisiere auf Version "
                            + up.optString("min") + " oder neuer.")
                    .setCancelable(false)
                    .setPositiveButton("Jetzt aktualisieren", null)
                    .create();
            requiredUpdateDialog.show();
            // Knopf schließt den Dialog nicht: die App bleibt gesperrt, bis sie aktualisiert ist
            requiredUpdateDialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> startUpdate(up));
            return;
        }
        if (n != null && !n.optString("id").isEmpty() && !n.optString("id").equals(prefs.getString("notice_seen", ""))) {
            prefs.edit().putString("notice_seen", n.optString("id")).apply();
            android.app.AlertDialog.Builder b = dlg()
                    .setTitle(n.optString("title").isEmpty() ? brandName() : n.optString("title"))
                    .setMessage(n.optString("text"))
                    .setPositiveButton("OK", null);
            final String url = n.optString("url");
            if (!url.isEmpty()) {
                String label = n.optString("url_label");
                b.setNeutralButton(label.isEmpty() ? "Mehr erfahren" : label, (dd, w) -> openExternal(url));
            }
            b.show();
            return;
        }
        // Nur Updates für genau dieses Paket anbieten (nie die Developer-APK einer anderen Variante über eine Store-/Release-App)
        final boolean forThisApp = up == null || up.optString("package", "").isEmpty() || up.optString("package").equals(getPackageName());
        final String seenKey = up == null ? "" : up.optString("latest") + "#" + up.optInt("latest_code", 0);
        if (BuildConfig.SELF_UPDATE && forThisApp && up != null && up.optBoolean("available") && !seenKey.equals(prefs.getString("update_seen", ""))) {
            prefs.edit().putString("update_seen", seenKey).apply();
            final String notes = up.optString("notes", "").trim();
            dlg()
                    .setTitle("Neue Version verfügbar")
                    .setMessage("Es gibt " + brandName() + " " + up.optString("latest") + ". Jetzt laden und installieren?" + (notes.isEmpty() ? "" : "\n\nNeu:\n" + notes))
                    .setPositiveButton("Jetzt aktualisieren", (dd, w) -> startUpdate(up))
                    .setNegativeButton("Später", null)
                    .show();
        }
    }

    // ---------- Cast (Chromecast, Google Cast Lautsprecher/TV): Umschalten übernimmt PlaybackService ----------
    private boolean castAvailable() {
        if (!prefs.getBoolean("app_f_cast", true)) return false;
        try {
            return com.google.android.gms.common.GoogleApiAvailability.getInstance()
                    .isGooglePlayServicesAvailable(this) == com.google.android.gms.common.ConnectionResult.SUCCESS;
        } catch (Throwable t) {
            return false;
        }
    }

    /** Gemeinsame Geräteauswahl: Chromecast und DLNA ohne Google-Dienste, dazu (falls vorhanden) Google Cast. */
    private NetCast.Media castMediaFrom(MediaItem it) {
        if (it == null || it.localConfiguration == null) return null;
        NetCast.Media m = new NetCast.Media();
        m.url = it.localConfiguration.uri.toString();
        m.mime = it.localConfiguration.mimeType != null ? it.localConfiguration.mimeType : Stations.mime(m.url, "");
        m.title = it.mediaMetadata.title == null ? "" : it.mediaMetadata.title.toString();
        m.artist = it.mediaMetadata.artist == null ? "" : it.mediaMetadata.artist.toString();
        m.image = it.mediaMetadata.artworkUri == null ? "" : it.mediaMetadata.artworkUri.toString();
        m.album = it.mediaMetadata.albumTitle == null ? "" : it.mediaMetadata.albumTitle.toString();
        m.live = it.mediaId == null || !it.mediaId.startsWith("podcast:");
        enrichCastInfo(m, it);
        return m;
    }

    /**
     * Mehr Infos auf dem Fernseher: Sender, Marke, Genres; mit der Option "Titel live" auch der laufende Titel samt Cover.
     * Der Standard-Empfänger zeigt Titel, Künstler und Album/Untertitel an.
     */
    private void enrichCastInfo(NetCast.Media m, MediaItem it) {
        if (!m.live || podcastMode || currentStationId == null || it.mediaId == null) return;
        String mid = it.mediaId;
        boolean world = mid.startsWith("world:") && currentWorld != null && mid.equals("world:" + currentWorld.id);
        boolean station = mid.equals("station:" + currentStationId);
        if (!world && !station) return;
        List<String> genreList = world ? currentWorld.genres : (stationCache.containsKey(currentStationId) ? stationCache.get(currentStationId).genres : null);
        StringBuilder genres = new StringBuilder();
        if (genreList != null) for (int i = 0; i < genreList.size() && i < 3; i++) genres.append(i > 0 ? " · " : "").append(genreList.get(i));
        String brand = brandName();
        String stationName = currentStationName.isEmpty() ? m.title : currentStationName;
        boolean songKnown = !currentTitle.isEmpty() && !currentTitle.equals("Live-Stream") && !currentTitle.startsWith("Titel wird");
        if (prefs.getBoolean("cast_live_info", false) && songKnown) {
            m.title = currentTitle;
            m.artist = currentArtist.isEmpty() ? stationName : currentArtist;
            m.album = genres.length() > 0 ? stationName + " · " + genres : stationName;
            if (!currentArtwork.isEmpty()) m.image = currentArtwork;
        } else {
            m.title = stationName;
            m.artist = world ? "Radio Browser" : brand;
            m.album = genres.toString();
        }
    }

    private String castKey = "";

    private String castKeyOf(NetCast.Media m) {
        return currentStationId + "|" + m.artist + "|" + m.title + "|" + m.image;
    }

    /** Titelwechsel an das Gerät melden (nur mit der Option "Titel live"; der Empfänger lädt dazu den Stream kurz neu). */
    private void castPushInfo() {
        NetCast nc = NetCast.get(this);
        if (!nc.isActive() || podcastMode || controller == null || !prefs.getBoolean("cast_live_info", false)) return;
        NetCast.Media m = castMediaFrom(controller.getCurrentMediaItem());
        if (m == null) return;
        String key = castKeyOf(m);
        if (key.equals(castKey)) return;
        castKey = key;
        nc.load(m);
    }

    /** Neuen Sender/Podcast auf das verbundene Gerät schicken statt lokal zu spielen. */
    private boolean netCastPlay(MediaItem item) {
        NetCast nc = NetCast.get(this);
        if (!nc.isActive()) return false;
        NetCast.Media m = castMediaFrom(item);
        if (m == null) return false;
        castKey = castKeyOf(m);
        nc.load(m);
        return true;
    }

    private void showCastPicker() {
        final NetCast nc = NetCast.get(this);
        final LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(18), dp(14), dp(18), dp(10));
        final android.app.AlertDialog.Builder b = dlg().setTitle("Übertragen (Cast)").setView(box)
                .setNegativeButton("Schließen", null);
        if (nc.isActive()) {
            box.addView(text("Verbunden mit " + nc.name() + " (" + nc.kind() + ")", 14, TEXT, true));
            TextView hint = text("Die Wiedergabe läuft auf dem Gerät. Neue Sender starten dort.", 12, MUTED, false);
            hint.setPadding(0, dp(6), 0, dp(10));
            box.addView(hint);
            Button pp = button(nc.isPlaying() ? "❚❚  Pause" : "▶  Weiter", PRIMARY, Color.rgb(18, 10, 39));
            pp.setOnClickListener(v -> { nc.togglePause(); Toast.makeText(this, "Gesendet", Toast.LENGTH_SHORT).show(); });
            box.addView(pp, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(46)));
            android.widget.SeekBar vol = new android.widget.SeekBar(this);
            vol.setMax(100);
            vol.setProgress(50);
            vol.setOnSeekBarChangeListener(new android.widget.SeekBar.OnSeekBarChangeListener() {
                @Override public void onProgressChanged(android.widget.SeekBar sb, int p, boolean user) { if (user) nc.setVolume(p); }
                @Override public void onStartTrackingTouch(android.widget.SeekBar sb) { }
                @Override public void onStopTrackingTouch(android.widget.SeekBar sb) { }
            });
            LinearLayout.LayoutParams vlp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
            vlp.topMargin = dp(12);
            box.addView(text("Lautstärke am Gerät", 12, MUTED, false));
            box.addView(vol, vlp);
            b.setPositiveButton("Trennen", (d, w) -> {
                nc.disconnect();
                Toast.makeText(this, "Getrennt – die Wiedergabe läuft wieder nur auf dem Handy, sobald du Play drückst.", Toast.LENGTH_LONG).show();
            });
            b.show();
            return;
        }
        final TextView status = text("Suche im WLAN … (Handy und Gerät müssen im selben Netz sein)", 12, MUTED, false);
        status.setPadding(0, 0, 0, dp(10));
        box.addView(status);
        final android.app.AlertDialog dlg = b.create();
        if (castAvailable()) {
            Button g = button("Google Cast (Play-Dienste) …", CARD_2, TEXT);
            g.setOnClickListener(v -> { dlg.dismiss(); showCast(); });
            LinearLayout.LayoutParams glp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(44));
            glp.bottomMargin = dp(8);
            box.addView(g, glp);
        }
        final int[] count = {0};
        dlg.setOnDismissListener(d -> nc.stopScan());
        dlg.show();
        nc.scan(new NetCast.ScanListener() {
            @Override public void onFound(NetCast.Found f) {
                count[0]++;
                Button row = button(f.name + "  ·  " + f.label(), CARD_2, TEXT);
                row.setOnClickListener(v -> {
                    NetCast.Media m = castMediaFrom(controller == null ? null : controller.getCurrentMediaItem());
                    if (m == null) {
                        Toast.makeText(MainActivity.this, "Starte zuerst einen Sender.", Toast.LENGTH_LONG).show();
                        return;
                    }
                    status.setText("Verbinde mit " + f.name + " …");
                    castKey = castKeyOf(m);
                    nc.connect(f, m, (ok, msg) -> {
                        if (ok) {
                            if (controller != null) controller.pause();
                            dlg.dismiss();
                            Toast.makeText(MainActivity.this, "Läuft auf " + f.name, Toast.LENGTH_SHORT).show();
                        } else {
                            status.setText(msg + (f.kind.equals("dlna") ? " Manche DLNA-Geräte können nur http-Streams." : ""));
                        }
                    });
                });
                LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(46));
                lp.bottomMargin = dp(6);
                box.addView(row, lp);
                status.setText(count[0] + " Gerät" + (count[0] == 1 ? "" : "e") + " gefunden – tippe zum Übertragen.");
            }

            @Override public void onDone() {
                if (count[0] == 0) status.setText("Keine Geräte gefunden. Ist das Gerät an und im selben WLAN?");
            }
        });
    }

    private void showCast() {
        try {
            androidx.mediarouter.media.MediaRouter router = androidx.mediarouter.media.MediaRouter.getInstance(this);
            androidx.mediarouter.media.MediaRouteSelector selector = new androidx.mediarouter.media.MediaRouteSelector.Builder()
                    .addControlCategory(com.google.android.gms.cast.CastMediaControlIntent.categoryForCast(
                            com.google.android.gms.cast.CastMediaControlIntent.DEFAULT_MEDIA_RECEIVER_APPLICATION_ID))
                    .build();
            if (!router.getSelectedRoute().isDefault()) {
                new androidx.mediarouter.app.MediaRouteControllerDialog(this).show();
            } else {
                androidx.mediarouter.app.MediaRouteChooserDialog dlg = new androidx.mediarouter.app.MediaRouteChooserDialog(this);
                dlg.setRouteSelector(selector);
                dlg.show();
            }
        } catch (Throwable t) {
            Toast.makeText(this, "Cast ist auf diesem Gerät nicht verfügbar.", Toast.LENGTH_LONG).show();
        }
    }

    private void openExternal(String url) {
        if (url == null || !url.startsWith("http")) return;
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
        } catch (Exception ignored) {
        }
    }

    private void playDirectoryItem(Directory.Item it, List<Directory.Item> queue) {
        if (it == null) return;
        if (!it.playable()) {
            Toast.makeText(this, "Dieser Sender lässt sich hier nicht direkt abspielen – die Webseite wird geöffnet.", Toast.LENGTH_LONG).show();
            openExternal(it.link);
            return;
        }
        List<Directory.Item> q = queue == null ? new ArrayList<>() : new ArrayList<>(queue);
        if (it.isWorld()) playWorld(it);
        else selectStation(it.id, it.name, true);
        dirQueue.clear();
        dirQueue.addAll(q);
    }

    private void playWorld(Directory.Item it) {
        if (controller == null) {
            Toast.makeText(this, "Player startet noch …", Toast.LENGTH_SHORT).show();
            return;
        }
        podcastMode = false;
        currentWorld = it;
        addRecent("world:" + it.id, it.name, it);
        currentStationId = "world:" + it.id;
        currentStationName = it.name;
        currentStationCover = it.cover;
        currentArtist = "";
        currentTitle = "Fremd-Stream";
        currentArtwork = "";

        miniStation.setText(it.name);
        miniTitle.setText("Fremd-Stream");
        miniCover.setImageResource(R.drawable.app_logo);
        if (!it.cover.isEmpty()) loadImage(it.cover, miniCover);

        MediaMetadata.Builder meta = new MediaMetadata.Builder().setTitle(it.name).setArtist("Fremd-Stream");
        if (!it.cover.isEmpty()) meta.setArtworkUri(Uri.parse(it.cover));
        MediaItem item = new MediaItem.Builder()
                .setMediaId("world:" + it.id)
                .setUri(it.stream)
                .setMimeType(Stations.mime(it.stream, it.codec))
                .setMediaMetadata(meta.build())
                .build();
        if (netCastPlay(item)) {
            refreshFullPlayerUi();
            loadWorldMeta();
            return;
        }
        controller.setMediaItem(item);
        controller.prepare();
        controller.play();
        refreshFullPlayerUi();
        loadWorldMeta();
    }

    /** Titel (und Bild), falls der Stream sie per ICY mitsendet; sonst bleibt es bei "Fremd-Stream". Cover wie bei laut.fm per Titelsuche. */
    private void loadWorldMeta() {
        final Directory.Item w = currentWorld;
        if (w == null) return;
        io.execute(() -> {
            String[] m = Directory.meta(siteBase(), w.stream);
            String title = m[0].trim();
            String artist = "";
            String song = title;
            int dash = title.indexOf(" - ");
            if (dash > 0) {
                artist = title.substring(0, dash).trim();
                song = title.substring(dash + 3).trim();
            }
            String artwork = !m[1].isEmpty() ? m[1] : (title.isEmpty() ? "" : findArtwork(artist, song));
            final String fArtist = artist, fSong = song, fArt = artwork, fTitle = title;
            main.post(() -> {
                if (currentWorld != w || podcastMode) return;
                currentArtist = fArtist;
                currentTitle = fTitle.isEmpty() ? "Fremd-Stream" : fSong;
                miniTitle.setText(fTitle.isEmpty() ? "Fremd-Stream" : fTitle);
                if (!fArt.isEmpty()) {
                    currentArtwork = fArt;
                    loadImage(fArt, miniCover);
                }
                refreshFullPlayerUi();
                updatePlaybackMetadata();
            });
        });
    }

    /** Vor/Zurueck innerhalb der zuletzt angezeigten Verzeichnis-Liste; false, wenn der aktuelle Sender nicht darin steht. */
    private boolean stepDirectoryQueue(int delta) {
        if (dirQueue.isEmpty()) return false;
        String ck = currentWorld != null ? currentWorld.key() : "laut:" + currentStationId;
        int idx = -1;
        for (int i = 0; i < dirQueue.size(); i++) {
            if (dirQueue.get(i).key().equals(ck)) {
                idx = i;
                break;
            }
        }
        if (idx < 0) return false;
        int n = dirQueue.size();
        List<Directory.Item> q = new ArrayList<>(dirQueue);
        for (int step = 1; step <= n; step++) {
            Directory.Item cand = q.get((((idx + delta * step) % n) + n) % n);
            if (cand.playable()) {
                playDirectoryItem(cand, q);
                loadFullPlayerExtras();
                return true;
            }
        }
        return false;
    }

    /** "Ueberrasch mich": bei Verzeichnis-Marken im Wechsel eigener Sender und Fremdsender. */
    private void playSurprise() {
        if (!hasDirectory()) {
            playRandomOwnedStation();
            return;
        }
        boolean own = !prefs.getBoolean("surprise_foreign_next", false);
        prefs.edit().putBoolean("surprise_foreign_next", own).apply();
        if (own) {
            playRandomOwnedStation();
            return;
        }
        Toast.makeText(this, "Suche etwas Neues …", Toast.LENGTH_SHORT).show();
        io.execute(() -> {
            Directory.Page page = null;
            try {
                page = Directory.random(siteBase(), "mix", 6);
            } catch (Exception ignored) {
            }
            final Directory.Page p = page;
            main.post(() -> {
                Directory.Item pick = null;
                if (p != null) {
                    for (Directory.Item it : p.items) {
                        if (!it.own && it.playable()) {
                            pick = it;
                            break;
                        }
                    }
                }
                if (pick == null) {
                    playRandomOwnedStation();
                    return;
                }
                dirReportOn = p.reportOn;
                playDirectoryItem(pick, p.items);
                Toast.makeText(this, "Überraschung: " + pick.name, Toast.LENGTH_SHORT).show();
            });
        });
    }

    private List<Directory.Item> dirFavs() {
        try {
            return Directory.parse(new JSONArray(prefs.getString("dir_favs", "[]")));
        } catch (Exception e) {
            return new ArrayList<>();
        }
    }

    private boolean dirFavHas(Directory.Item it) {
        for (Directory.Item x : dirFavs()) if (x.key().equals(it.key())) return true;
        return false;
    }

    private void dirFavToggle(Directory.Item it) {
        List<Directory.Item> list = dirFavs();
        boolean removed = false;
        for (int i = list.size() - 1; i >= 0; i--) {
            if (list.get(i).key().equals(it.key())) {
                list.remove(i);
                removed = true;
            }
        }
        if (!removed) list.add(0, it);
        JSONArray arr = new JSONArray();
        for (int i = 0; i < list.size() && i < 100; i++) arr.put(list.get(i).toJson());
        prefs.edit().putString("dir_favs", arr.toString()).apply();
        Toast.makeText(this, removed ? "Favorit entfernt" : "Als Favorit gespeichert", Toast.LENGTH_SHORT).show();
    }

    private boolean isDirFav(Directory.Item it) {
        return it.isWorld() ? dirFavHas(it) : getFavorites().contains(it.id);
    }

    /** laut.fm-Sender liegen wie bisher in "favorites", Fremd-Streams (World Radio) brauchen die vollen Daten. */
    private void toggleDirFav(Directory.Item it) {
        if (it.isWorld()) {
            dirFavToggle(it);
            return;
        }
        Set<String> favs = getFavorites();
        boolean removed = favs.remove(it.id);
        if (!removed) favs.add(it.id);
        prefs.edit().putStringSet("favorites", favs).apply();
        Toast.makeText(this, removed ? "Favorit entfernt" : "Als Favorit gespeichert", Toast.LENGTH_SHORT).show();
    }

    /** Der laufende laut.fm-Sender als Verzeichnis-Eintrag (fuer "Melden"); null bei Podcast/Fremd-Stream. */
    private Directory.Item directoryItemForStation(String id) {
        if (id == null || id.startsWith("world:") || podcastMode) return null;
        try {
            JSONObject o = new JSONObject().put("source", "laut").put("id", id).put("name", currentStationName)
                    .put("link", "https://laut.fm/" + id).put("own", appConfig.isOwned(id));
            return new Directory.Item(o);
        } catch (Exception e) {
            return null;
        }
    }

    private void showReportDialog(Directory.Item it) {
        String[] labels = new String[Directory.REASONS.length];
        for (int i = 0; i < labels.length; i++) labels[i] = Directory.REASONS[i][1];
        final int[] sel = {1};
        dlg()
                .setTitle("Sender melden: " + it.name)
                .setSingleChoiceItems(labels, sel[0], (d, which) -> sel[0] = which)
                .setPositiveButton("Melden", (d, which) -> io.execute(() -> {
                    String err = Directory.report(siteBase(), it, Directory.REASONS[sel[0]][0], "");
                    main.post(() -> Toast.makeText(this,
                            err.isEmpty() ? "Danke – wir prüfen den Sender." : err, Toast.LENGTH_LONG).show());
                }))
                .setNegativeButton("Abbrechen", null)
                .show();
    }

    private Set<String> getFavorites() {
        return new HashSet<>(prefs.getStringSet("favorites", new HashSet<>()));
    }

    private void toggleFavorite(String stationId) {
        if (stationId == null || podcastMode) return;
        Set<String> favs = getFavorites();
        if (favs.contains(stationId)) favs.remove(stationId);
        else favs.add(stationId);
        prefs.edit().putStringSet("favorites", favs).apply();

        Toast.makeText(this,
                favs.contains(stationId) ? "Als Favorit gespeichert" : "Favorit entfernt",
                Toast.LENGTH_SHORT).show();
        refreshFullPlayerUi();
    }

    private void openInternalWeb(String title, String url) {
        openInternalWeb(title, url, false);
    }

    private void openInternalWeb(String title, String url, boolean requiresMicrophone) {
        Intent intent = new Intent(this, WidgetActivity.class);
        intent.putExtra("title", title);
        intent.putExtra("url", url);
        intent.putExtra("requiresMicrophone", requiresMicrophone);
        startActivity(intent);
    }

    private String cmsAssetUrl(String value) {
        if (value == null) return "";
        String v = value.trim();
        if (v.isEmpty()) return "";
        if (v.startsWith("http://") || v.startsWith("https://")) return v;
        if (v.startsWith("/")) return siteBase() + v;
        return siteBase() + "/" + v;
    }

    private void refreshCmsRuntimeConfig() {
        io.execute(() -> {
            try {
                // Eigene Texte der Marke (Startseite, News-Titel) aus dem CMS; Bereich Marken > Texte
                JSONObject b = getJson(siteBase() + "/cms/api.php?action=brand");
                JSONObject portalTexts = b.optJSONObject("overrides") == null ? null : b.optJSONObject("overrides").optJSONObject("portal");
                if (portalTexts != null) {
                    String before = prefs.getString("brand_hero_title", "") + "|" + prefs.getString("brand_hero_text", "");
                    prefs.edit()
                            .putString("brand_hero_title", portalTexts.optString("hero_title", ""))
                            .putString("brand_hero_text", portalTexts.optString("hero_text", ""))
                            .putString("brand_hero_eyebrow", portalTexts.optString("hero_eyebrow", ""))
                            .putString("brand_news_title", portalTexts.optString("news_title", ""))
                            .apply();
                    String after = prefs.getString("brand_hero_title", "") + "|" + prefs.getString("brand_hero_text", "");
                    if (!before.equals(after)) main.post(() -> { if ("home".equals(currentScreen)) showHome(); });
                }
            } catch (Exception ignored) {
            }
            try {
                JSONObject root = getJson(siteBase() + "/cms/api.php?action=public");
                JSONObject cfg = root.optJSONObject("config");
                if (cfg == null) return;

                JSONObject core = cfg.optJSONObject("core_network");
                JSONArray arr = core == null ? null : core.optJSONArray("stations");
                if (arr != null && arr.length() > 0) {
                    List<String> ids = new ArrayList<>();
                    for (int i = 0; i < arr.length(); i++) {
                        String id = arr.optString(i, "").trim().toLowerCase(Locale.ROOT);
                        if (!id.isEmpty() && !ids.contains(id)) ids.add(id);
                    }
                    appConfig.replaceOwnedStations(ids);
                    main.post(this::selectDefaultStationIfNone);
                }

                JSONObject ai = cfg.optJSONObject("assistant");
                if (ai != null) {
                    JSONObject feat = ai.optJSONObject("features");
                    prefs.edit()
                            .putBoolean("assistant_enabled", ai.optBoolean("enabled", true))
                            .putString("assistant_name", ai.optString("name", ""))
                            .putString("assistant_greeting", ai.optString("greeting", ""))
                            .putBoolean("assistant_mail", feat == null || feat.optBoolean("studiomail", true))
                            .putBoolean("assistant_voice", feat == null || feat.optBoolean("voicemail", true))
                            .apply();
                    main.post(this::applyAssistantConfig);
                }

                // CMS-Branding der Website (android_inapp_logo, android_startscreen, android_app_icon)
                JSONObject branding = cfg.optJSONObject("branding");
                if (branding != null) {
                    String logo = cmsAssetUrl(branding.optString("android_inapp_logo", ""));
                    String splash = cmsAssetUrl(branding.optString("android_startscreen", ""));
                    String appIcon = cmsAssetUrl(branding.optString("android_app_icon", ""));
                    prefs.edit()
                            .putString("branding_logo", logo)
                            .putString("branding_startscreen", splash)
                            .putString("branding_app_icon", appIcon)
                            .apply();
                    if (!logo.isEmpty() && headerLogo != null) {
                        main.post(() -> loadImage(logo, headerLogo));
                    }
                }
            } catch (Exception ignored) {
            }
        });
    }

    private void loadBrandAsset(String path, ImageView target, int fallbackRes) {
        io.execute(() -> {
            Bitmap bitmap = null;
            try (InputStream in = getAssets().open(path)) {
                bitmap = BitmapFactory.decodeStream(in);
            } catch (Exception ignored) {
            }
            Bitmap finalBitmap = bitmap;
            main.post(() -> {
                if (finalBitmap != null) target.setImageBitmap(finalBitmap);
                else target.setImageResource(fallbackRes);
            });
        });
    }

    /** Domain der Marke dieser App (aus android/brands.json je Flavor), ohne Slash am Ende. */
    private String siteBase() {
        String b = getString(R.string.site_base).trim();
        return b.isEmpty() ? "https://example.org" : b.replaceAll("/+$", "");
    }

    private String brandName() { return getString(R.string.app_name); }

    /** Marken-Text aus dem CMS (Bereich Marken > Texte), lokal gemerkt; ohne Wert gilt der Standardtext der Marke. */
    /** Text aus dem CMS (Apps → Layout → Texte), sonst der neutrale Standard. */
    private String brandText(String key, String defaultText) {
        String v = prefs.getString("brand_" + key, "").trim();
        return v.isEmpty() ? defaultText : v;
    }

    private String siteHost() {
        try {
            String h = new java.net.URI(siteBase()).getHost();
            return h == null ? siteBase() : h.replaceFirst("^www\\.", "");
        } catch (Exception e) {
            return siteBase();
        }
    }

    private static String stampText(long ms) {
        return new java.text.SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY).format(new java.util.Date(ms));
    }

    private JSONObject getJson(String address) throws Exception {
        return new JSONObject(getText(address));
    }

    private JSONArray getJsonArray(String address) throws Exception {
        return new JSONArray(getText(address));
    }

    private String getText(String address) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(address).openConnection();
        connection.setConnectTimeout(9000);
        connection.setReadTimeout(9000);
        connection.setRequestProperty("Accept", "application/json");
        connection.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(
                connection.getInputStream(), StandardCharsets.UTF_8))) {
            StringBuilder b = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) b.append(line);
            return b.toString();
        } finally {
            connection.disconnect();
        }
    }

    private void switchStation(int delta) {
        if (podcastMode) podcastMode = false;
        if (stepDirectoryQueue(delta)) return;
        if (activeStationQueue.isEmpty()) {
            activeStationQueue.addAll(ownedStations());
        }
        if (activeStationQueue.isEmpty()) return;

        int index = activeStationQueue.indexOf(currentStationId);
        if (index < 0) index = 0;
        int nextIndex = (index + delta) % activeStationQueue.size();
        if (nextIndex < 0) nextIndex += activeStationQueue.size();
        String nextId = activeStationQueue.get(nextIndex);
        selectStation(nextId, prettyName(nextId), true);
        loadFullPlayerExtras();
    }

    private void updatePlaybackMetadata() {
        if (controller == null || podcastMode || controller.getMediaItemCount() == 0) return;
        // Beim Cast würde das Ersetzen des Items den Stream am Lautsprecher/TV neu starten
        if (controller.getDeviceInfo().playbackType == androidx.media3.common.DeviceInfo.PLAYBACK_TYPE_REMOTE || NetCast.get(this).isActive()) return;
        int index = controller.getCurrentMediaItemIndex();
        MediaItem existing = controller.getCurrentMediaItem();
        if (existing == null) return;

        String artwork = !currentArtwork.isEmpty() ? currentArtwork : currentStationCover;
        MediaMetadata.Builder meta = new MediaMetadata.Builder()
                .setTitle(currentTitle.isEmpty() ? currentStationName : currentTitle)
                .setArtist(currentArtist.isEmpty() ? currentStationName : currentArtist)
                .setAlbumTitle(currentStationName);
        if (!artwork.isEmpty()) meta.setArtworkUri(Uri.parse(artwork));

        MediaItem updated = new MediaItem.Builder()
                .setMediaId(currentWorld != null ? "world:" + currentWorld.id : "station:" + currentStationId)
                .setUri(currentWorld != null ? currentWorld.stream : Stations.streamUrl(prefs, currentStationId))
                .setMimeType(currentWorld != null ? Stations.mime(currentWorld.stream, currentWorld.codec) : Stations.mime(Stations.streamUrl(prefs, currentStationId), ""))
                .setMediaMetadata(meta.build())
                .build();

        controller.replaceMediaItem(index, updated);
    }

    // ================================================================ Wiedergabe-Steuerung (Logik)

    private void selectStation(String stationId, String displayName, boolean autoplay) {
        podcastMode = false;
        currentWorld = null;
        dirQueue.clear();
        currentStationId = stationId;
        currentStationName = displayName;
        currentArtist = "";
        currentTitle = "Titel wird geladen …";
        currentArtwork = "";
        currentStationCover = "";
        prefs.edit().putString("last_station_id", stationId).putString("last_station_name", displayName).apply();
        if (autoplay) addRecent(stationId, displayName, null);

        miniStation.setText(displayName);
        miniTitle.setText(currentTitle);
        miniCover.setImageResource(R.drawable.app_logo);

        loadStationInfo(stationId, info -> {
            if (!stationId.equals(currentStationId)) return;
            currentStationName = info.name;
            currentStationCover = info.cover;
            miniStation.setText(info.name);
            if (!info.cover.isEmpty()) loadImage(info.cover, miniCover);
            refreshFullPlayerUi();

            if (autoplay) playRadioStation(info);
        });

        loadNowPlaying(stationId);

        if (autoplay && stationCache.containsKey(stationId)) {
            playRadioStation(stationCache.get(stationId));
        }
    }

    private void connectPlaybackService() {
        SessionToken token = new SessionToken(this, new ComponentName(this, PlaybackService.class));
        controllerFuture = new MediaController.Builder(this, token).buildAsync();
        controllerFuture.addListener(() -> {
            try {
                controller = controllerFuture.get();
                controller.addListener(new Player.Listener() {
                    @Override public void onIsPlayingChanged(boolean isPlaying) {
                        runOnUiThread(() -> {
                            listenTick(isPlaying);
                            updatePlayButtons();
                        });
                    }

                    @Override public void onMediaItemTransition(MediaItem mediaItem, int reason) {
                        runOnUiThread(() -> {
                            listenFlush();
                            if (controller != null && controller.isPlaying()) listenTick(true);
                            updatePlayButtons();
                        });
                    }

                    @Override public void onPlayerError(androidx.media3.common.PlaybackException error) {
                        if (!prefs.getBoolean("app_t_errors", false)) return;
                        final String where = currentWorld != null ? "world" : String.valueOf(currentStationId);
                        final String code = error.getErrorCodeName();
                        io.execute(() -> {
                            try {
                                ErrorReporter.send(siteBase(), ErrorReporter.build("player", code, where, appVersion(), ""), false);
                            } catch (Exception ignored) {
                            }
                        });
                    }
                });
                updatePlayButtons();
                if (pendingAutoplay) {
                    pendingAutoplay = false;
                    if (controller.getMediaItemCount() == 0) togglePlayback();
                }
            } catch (Exception e) {
                runOnUiThread(() -> Toast.makeText(this,
                        "Player konnte nicht gestartet werden.", Toast.LENGTH_LONG).show());
            }
        }, command -> runOnUiThread(command));
    }

    private void updatePlayButtons() {
        NetCast nc = NetCast.get(this);
        boolean playing = nc.isActive() ? nc.isPlaying() : (controller != null && controller.isPlaying());
        if (miniPlay != null) {
            miniPlay.setImageResource(playing ? R.drawable.ic_pause : R.drawable.ic_play);
            miniPlay.setContentDescription(playing ? "Pause" : "Wiedergabe");
        }
        if (fullPlay != null) fullPlay.setImageResource(playing ? R.drawable.ic_pause : R.drawable.ic_play);
        if (miniEq != null) {
            miniEq.setPlaying(playing);
            miniEq.setVisibility(playing ? View.VISIBLE : View.GONE);
        }
        if (fullEq != null) {
            fullEq.setPlaying(playing);
            fullEq.setVisibility(playing ? View.VISIBLE : View.GONE);
        }
    }

    // ================================================================ Bilder, Hilfsfunktionen

    private final LruCache<String, Bitmap> imageCache = new LruCache<String, Bitmap>(24 * 1024 * 1024) {
        @Override protected int sizeOf(String key, Bitmap value) {
            return value.getByteCount();
        }
    };
    private final java.util.WeakHashMap<ImageView, String> imageTargets = new java.util.WeakHashMap<>();

    private void loadImage(String address, ImageView target) {
        loadImage(address, target, null);
    }

    /** Lädt ein Bild (mit Zwischenspeicher im Arbeitsspeicher, verkleinert) und meldet es optional zurück. */
    private void loadImage(String address, ImageView target, java.util.function.Consumer<Bitmap> done) {
        if (address == null || address.trim().isEmpty() || target == null) return;
        Bitmap hit = imageCache.get(address);
        if (hit != null) {
            imageTargets.put(target, address);
            target.setImageBitmap(hit);
            if (done != null) done.accept(hit);
            return;
        }
        imageTargets.put(target, address);
        io.execute(() -> {
            try {
                HttpURLConnection connection = (HttpURLConnection) new URL(address).openConnection();
                connection.setConnectTimeout(7000);
                connection.setReadTimeout(7000);
                connection.setDoInput(true);
                connection.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
                connection.connect();
                java.io.ByteArrayOutputStream buf = new java.io.ByteArrayOutputStream();
                try (InputStream in = connection.getInputStream()) {
                    byte[] chunk = new byte[16384];
                    int n;
                    while ((n = in.read(chunk)) > 0 && buf.size() < 8 * 1024 * 1024) buf.write(chunk, 0, n);
                } finally {
                    connection.disconnect();
                }
                byte[] bytes = buf.toByteArray();
                BitmapFactory.Options bounds = new BitmapFactory.Options();
                bounds.inJustDecodeBounds = true;
                BitmapFactory.decodeByteArray(bytes, 0, bytes.length, bounds);
                int sample = 1;
                while (Math.max(bounds.outWidth, bounds.outHeight) / (sample * 2) >= 900) sample *= 2;
                BitmapFactory.Options opts = new BitmapFactory.Options();
                opts.inSampleSize = sample;
                final Bitmap bitmap = BitmapFactory.decodeByteArray(bytes, 0, bytes.length, opts);
                if (bitmap == null) return;
                imageCache.put(address, bitmap);
                main.post(() -> {
                    if (address.equals(imageTargets.get(target))) {
                        target.setImageBitmap(bitmap);
                        if (done != null) done.accept(bitmap);
                    }
                });
            } catch (Exception ignored) {
            }
        });
    }

    private android.app.AlertDialog.Builder dlg() {
        return new android.app.AlertDialog.Builder(this, android.R.style.Theme_Material_Dialog_Alert);
    }

    private int dp(float value) {
        return Ui.dp(value);
    }

    /** laut.fm-API-Adresse einer Station; eigene Sender nutzen ihre laut.fm-Kennung (falls vorhanden), sonst führt der Abruf ins Leere und die App zeigt "Live-Stream". */
    private String lautApi(String stationId) {
        return "https://api.laut.fm/station/" + Uri.encode(Stations.lautId(prefs, stationId));
    }

    private String prettyName(String id) {
        return Stations.displayName(prefs, id);
    }

    /** Für Dialoge: Text im Stil der App. */
    private TextView text(String value, int sp, int color, boolean bold) {
        return Ui.text(this, value, sp, color, bold ? Ui.BOLD : Ui.REGULAR);
    }

    /** Für Dialoge: Schaltfläche im Stil der App. */
    private Button button(String value, int background, int foreground) {
        Button b = new Button(this);
        b.setText(value);
        b.setTextColor(foreground);
        b.setTextSize(14);
        b.setAllCaps(false);
        b.setTypeface(Typeface.create("sans-serif-medium", Typeface.NORMAL));
        b.setGravity(Gravity.CENTER);
        b.setPadding(dp(14), 0, dp(14), 0);
        b.setBackground(Ui.ripple(Ui.rect(background, 14, background == Color.TRANSPARENT ? Color.TRANSPARENT : LINE,
                background == Color.TRANSPARENT ? 0 : 1), 14));
        return b;
    }

    private void configureSystemBars() {
        Window window = getWindow();
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_NAVIGATION);
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.setStatusBarColor(Color.TRANSPARENT);
        window.setNavigationBarColor(Color.rgb(8, 11, 32));
        if (Build.VERSION.SDK_INT >= 29) {
            window.setNavigationBarContrastEnforced(false);
            window.setStatusBarContrastEnforced(false);
        }
        if (Build.VERSION.SDK_INT >= 30) {
            window.setDecorFitsSystemWindows(false);
        } else {
            window.getDecorView().setSystemUiVisibility(
                    View.SYSTEM_UI_FLAG_LAYOUT_STABLE |
                    View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION |
                    View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
            );
        }
    }

    // ================================================================ Zurück, Beenden

    /** Erster Sender der Liste (Core-Netzwerk der Website, dann eigene Sender); leer, solange die Konfiguration noch nicht geladen ist. */
    private String defaultStationId() {
        List<String> o = ownedStations();
        return o.isEmpty() ? "" : o.get(0);
    }

    /** Beim ersten Start (oder wenn die Senderliste erst nach dem Laden der Konfiguration da ist) den Standardsender vorwählen. */
    private void selectDefaultStationIfNone() {
        if (!currentStationId.isEmpty() || currentWorld != null) return;
        String d = defaultStationId();
        if (!d.isEmpty()) selectStation(d, prettyName(d), false);
    }

    private void playRandomOwnedStation() {
        List<String> stations = ownedStations();
        if (stations.isEmpty()) return;
        String stationId = stations.get(random.nextInt(stations.size()));
        activeStationQueue.clear();
        activeStationQueue.addAll(stations);
        selectStation(stationId, prettyName(stationId), true);
        Toast.makeText(this, "Überraschung: " + prettyName(stationId), Toast.LENGTH_SHORT).show();
    }

    @Override
    public void onBackPressed() {
        if (!overlays.isEmpty()) {
            View top = overlays.get(overlays.size() - 1);
            closeOverlay(top);
            updateBottomNavActive(currentScreen);
            return;
        }
        if (!"home".equals(currentScreen)) {
            if (screenParent != null) screenParent.run();
            else showHome();
            return;
        }
        super.onBackPressed();
    }

    @Override
    protected void onDestroy() {
        listenFlush();
        main.removeCallbacks(metadataTick);
        cancelSleepTimer();
        io.shutdownNow();
        if (controllerFuture != null) MediaController.releaseFuture(controllerFuture);
        controller = null;
        super.onDestroy();
    }
}
