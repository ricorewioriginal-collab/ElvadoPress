package app.elvadopress.client;

import android.app.Activity;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.WindowInsets;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

public class AccountActivity extends Activity {
    private static final int BG = Color.rgb(7, 10, 28);
    private static final int CARD = Color.rgb(15, 21, 56);
    private static final int LINE = Color.rgb(43, 51, 112);
    private static final int TEXT = Color.rgb(238, 240, 250);
    private static final int MUTED = Color.rgb(158, 166, 199);
    private static final int PRIMARY = Color.rgb(181, 124, 255);

    private AccountStore store;
    private AuthConfig authConfig;
    private LinearLayout root;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Ui.init(this);
        store = new AccountStore(this);
        authConfig = AuthConfig.load(this);
        setContentView(buildUi());
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (root != null) render();
    }

    private android.view.View buildUi() {
        LinearLayout page = new LinearLayout(this);
        page.setOrientation(LinearLayout.VERTICAL);
        page.setBackgroundColor(BG);
        applySystemInsets(page);

        LinearLayout top = new LinearLayout(this);
        top.setOrientation(LinearLayout.HORIZONTAL);
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.setPadding(dp(12), dp(12), dp(12), dp(12));

        android.widget.ImageButton back = Ui.iconButton(this, R.drawable.ic_back, Ui.TEXT, Ui.SURFACE, 42, "Zurück");
        back.setOnClickListener(v -> finish());
        top.addView(back, new LinearLayout.LayoutParams(dp(42), dp(42)));

        TextView title = text("Konto & Synchronisierung", 20, TEXT, true);
        title.setPadding(dp(14), 0, 0, 0);
        top.addView(title, new LinearLayout.LayoutParams(
                0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        page.addView(top);

        ScrollView scroll = new ScrollView(this);
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(16), dp(10), dp(16), dp(30));
        scroll.addView(root);
        page.addView(scroll, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));

        render();
        return page;
    }

    private void render() {
        root.removeAllViews();

        LinearLayout status = card();
        status.addView(text(store.isSignedIn() ? "Verbundenes Konto" : "Lokales Profil", 11, PRIMARY, true));
        status.addView(text(store.getDisplayName(), 20, TEXT, true));

        if (!store.getEmail().isEmpty()) {
            TextView email = text(store.getEmail(), 13, MUTED, false);
            email.setPadding(0, dp(4), 0, 0);
            status.addView(email);
        }

        TextView localId = text("Lokale Profil-ID: " + store.getLocalId(), 10, MUTED, false);
        localId.setPadding(0, dp(8), 0, 0);
        status.addView(localId);
        add(status);

        LinearLayout sync = card();
        sync.addView(text("Favoriten & Community", 16, TEXT, true));
        TextView body = text(
                "Favoriten funktionieren weiterhin local-first und offline. " +
                "Der Account-Layer ist für spätere serverseitige Synchronisierung vorbereitet, " +
                "ohne die lokale Nutzung davon abhängig zu machen.",
                12, MUTED, false);
        body.setPadding(0, dp(6), 0, dp(12));
        sync.addView(body);

        if (store.isSignedIn()) {
            Button logout = button("Konto trennen", CARD, TEXT);
            logout.setOnClickListener(v -> {
                store.signOut();
                render();
            });
            sync.addView(logout, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, dp(48)));
        } else {
            Button login = button(
                    authConfig.isReady() ? "Mit Konto verbinden" : "OAuth-Broker noch nicht konfiguriert",
                    PRIMARY,
                    Color.rgb(18, 10, 39)
            );
            login.setEnabled(authConfig.isReady());
            login.setOnClickListener(v -> OidcManager.startLogin(this));
            sync.addView(login, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, dp(48)));

            TextView note = text(
                    authConfig.isReady()
                            ? "Die Provider-Auswahl erfolgt auf der Login-Seite des konfigurierten Identity-Brokers."
                            : "Google, Microsoft, GitHub und Apple sind vorbereitet. Für Produktion braucht der gewählte Broker dennoch gültige Provider-Credentials.",
                    11,
                    MUTED,
                    false
            );
            note.setPadding(0, dp(9), 0, 0);
            sync.addView(note);
        }
        add(sync);
    }

    private LinearLayout card() {
        LinearLayout c = new LinearLayout(this);
        c.setOrientation(LinearLayout.VERTICAL);
        c.setPadding(dp(16), dp(15), dp(16), dp(15));
        c.setBackground(Ui.rect(Ui.SURFACE, 20, Ui.LINE, 1));
        return c;
    }

    private void add(android.view.View v) {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
        );
        lp.bottomMargin = dp(12);
        root.addView(v, lp);
    }

    private TextView text(String value, int sp, int color, boolean bold) {
        return Ui.text(this, value, sp, color, bold ? Ui.BOLD : Ui.REGULAR);
    }

    private Button button(String value, int bg, int fg) {
        Button b = new Button(this);
        b.setText(value);
        b.setTextColor(fg);
        b.setTextSize(13);
        b.setAllCaps(false);
        b.setTypeface(Typeface.create("sans-serif-medium", Typeface.NORMAL));
        b.setBackground(Ui.ripple(Ui.rect(bg, 24, bg == Ui.SURFACE ? Ui.LINE : Color.TRANSPARENT, bg == Ui.SURFACE ? 1 : 0), 24));
        return b;
    }

    private GradientDrawable roundRect(int fill, int radiusDp, int stroke, int strokeDp) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(fill);
        d.setCornerRadius(dp(radiusDp));
        if (strokeDp > 0) d.setStroke(dp(strokeDp), stroke);
        return d;
    }

    private void applySystemInsets(View root) {
        root.setOnApplyWindowInsetsListener((v, insets) -> {
            v.setPadding(
                    insets.getSystemWindowInsetLeft(),
                    insets.getSystemWindowInsetTop(),
                    insets.getSystemWindowInsetRight(),
                    insets.getSystemWindowInsetBottom()
            );
            return insets;
        });
        root.requestApplyInsets();
    }

    private int dp(int v) {
        return Math.round(v * getResources().getDisplayMetrics().density);
    }
}
