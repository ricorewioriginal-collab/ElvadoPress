package app.elvadopress.client;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.Context;
import android.content.res.ColorStateList;
import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Outline;
import android.graphics.Paint;
import android.graphics.RectF;
import android.graphics.Typeface;
import android.graphics.drawable.Drawable;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.RippleDrawable;
import android.text.TextUtils;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.ViewOutlineProvider;
import android.view.animation.LinearInterpolator;
import android.widget.FrameLayout;
import android.widget.HorizontalScrollView;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.Switch;
import android.widget.TextView;

/**
 * Design-System der Android-App ("Aurora"): Farben, Schrift und wiederverwendbare Bausteine.
 * Alle Ansichten der App bauen sich aus diesen Teilen, damit Abstände, Rundungen und Farben überall gleich sind.
 */
final class Ui {
    private Ui() {}

    // ---------- Farben ----------
    static final int BG = Color.rgb(7, 10, 28);
    static final int BG_DEEP = Color.rgb(4, 6, 20);
    static final int SURFACE = Color.rgb(15, 21, 56);
    static final int SURFACE_2 = Color.rgb(22, 29, 74);
    static final int LINE = Color.rgb(38, 46, 102);
    static final int TEXT = Color.rgb(241, 243, 255);
    static final int TEXT_2 = Color.rgb(184, 190, 224);
    static final int MUTED = Color.rgb(133, 141, 184);
    static final int CYAN = Color.rgb(0, 242, 234);
    static final int LIVE = Color.rgb(255, 45, 111);
    static final int ON_ACCENT = Color.rgb(22, 11, 46);
    static final int DEFAULT_ACCENT = Color.rgb(181, 124, 255);

    /** Akzent- und Verlaufsfarben (aus dem App-Builder im CMS). */
    static int accent = DEFAULT_ACCENT;
    static int heroFrom = Color.rgb(18, 13, 63);
    static int heroTo = Color.rgb(91, 28, 132);
    /** Bewegung reduzieren (Einstellungen): keine Dauer-Animationen. */
    static boolean reduceMotion = false;

    private static float density = 3f;

    static void init(Context c) {
        density = c.getResources().getDisplayMetrics().density;
    }

    static int dp(float v) {
        return Math.round(v * density);
    }

    static int alpha(int color, int a) {
        return Color.argb(a, Color.red(color), Color.green(color), Color.blue(color));
    }

    static int mix(int a, int b, float t) {
        return Color.rgb(
                Math.round(Color.red(a) + (Color.red(b) - Color.red(a)) * t),
                Math.round(Color.green(a) + (Color.green(b) - Color.green(a)) * t),
                Math.round(Color.blue(a) + (Color.blue(b) - Color.blue(a)) * t));
    }

    // ---------- Formen ----------
    static GradientDrawable rect(int fill, float radiusDp, int stroke, float strokeDp) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(fill);
        d.setCornerRadius(dp(radiusDp));
        if (strokeDp > 0) d.setStroke(Math.max(1, dp(strokeDp)), stroke);
        return d;
    }

    static GradientDrawable gradient(int from, int to, float radiusDp, GradientDrawable.Orientation o) {
        GradientDrawable d = new GradientDrawable(o, new int[]{from, to});
        d.setCornerRadius(dp(radiusDp));
        return d;
    }

    static GradientDrawable oval(int fill) {
        GradientDrawable d = new GradientDrawable();
        d.setShape(GradientDrawable.OVAL);
        d.setColor(fill);
        return d;
    }

    /** Berührungseffekt (Ripple) über beliebigem Hintergrund. */
    static Drawable ripple(Drawable content, float radiusDp) {
        GradientDrawable mask = new GradientDrawable();
        mask.setColor(Color.WHITE);
        mask.setCornerRadius(dp(radiusDp));
        return new RippleDrawable(ColorStateList.valueOf(alpha(Color.WHITE, 38)), content, mask);
    }

    static Drawable surface(float radiusDp) {
        return ripple(rect(SURFACE, radiusDp, LINE, 1), radiusDp);
    }

    // ---------- Text ----------
    static final int REGULAR = 0, MEDIUM = 1, BOLD = 2;

    static TextView text(Context c, CharSequence value, float sp, int color, int weight) {
        TextView v = new TextView(c);
        v.setText(value);
        v.setTextSize(sp);
        v.setTextColor(color);
        v.setIncludeFontPadding(false);
        v.setLineSpacing(0, 1.14f);
        if (weight == BOLD) v.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        else if (weight == MEDIUM) v.setTypeface(Typeface.create("sans-serif-medium", Typeface.NORMAL));
        return v;
    }

    static TextView eyebrow(Context c, CharSequence value, int color) {
        TextView v = text(c, value.toString().toUpperCase(java.util.Locale.ROOT), 10.5f, color, BOLD);
        v.setLetterSpacing(0.08f);
        return v;
    }

    static void ellipsize(TextView v, int lines) {
        v.setMaxLines(lines);
        v.setEllipsize(TextUtils.TruncateAt.END);
    }

    static void marquee(TextView v) {
        v.setSingleLine(true);
        v.setEllipsize(TextUtils.TruncateAt.MARQUEE);
        v.setMarqueeRepeatLimit(-1);
        v.setSelected(true);
        v.setHorizontallyScrolling(true);
        v.setFadingEdgeLength(dp(16));
    }

    // ---------- Layout-Helfer ----------
    static LinearLayout.LayoutParams lp(int w, int h) {
        return new LinearLayout.LayoutParams(w, h);
    }

    static LinearLayout.LayoutParams lp(int w, int h, float l, float t, float r, float b) {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(w, h);
        p.setMargins(dp(l), dp(t), dp(r), dp(b));
        return p;
    }

    static LinearLayout.LayoutParams lpW(float weight) {
        return new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, weight);
    }

    static final int MATCH = ViewGroup.LayoutParams.MATCH_PARENT;
    static final int WRAP = ViewGroup.LayoutParams.WRAP_CONTENT;

    static LinearLayout vbox(Context c) {
        LinearLayout l = new LinearLayout(c);
        l.setOrientation(LinearLayout.VERTICAL);
        return l;
    }

    static LinearLayout hbox(Context c) {
        LinearLayout l = new LinearLayout(c);
        l.setOrientation(LinearLayout.HORIZONTAL);
        l.setGravity(Gravity.CENTER_VERTICAL);
        return l;
    }

    static LinearLayout card(Context c) {
        LinearLayout l = vbox(c);
        l.setPadding(dp(16), dp(14), dp(16), dp(14));
        l.setBackground(rect(SURFACE, 20, LINE, 1));
        return l;
    }

    // ---------- Symbole & Knöpfe ----------
    static ImageView icon(Context c, int res, int tint, float sizeDp) {
        ImageView v = new ImageView(c);
        v.setImageResource(res);
        v.setColorFilter(tint);
        v.setLayoutParams(new ViewGroup.LayoutParams(dp(sizeDp), dp(sizeDp)));
        return v;
    }

    /** Runde Symbol-Schaltfläche (Standard 44 dp). */
    static ImageButton iconButton(Context c, int res, int tint, int bg, float sizeDp, String description) {
        ImageButton b = new ImageButton(c);
        b.setImageResource(res);
        b.setColorFilter(tint);
        b.setScaleType(ImageView.ScaleType.CENTER);
        int pad = dp(sizeDp * 0.26f);
        b.setPadding(pad, pad, pad, pad);
        b.setBackground(ripple(oval(bg), sizeDp));
        b.setContentDescription(description);
        b.setLayoutParams(new ViewGroup.LayoutParams(dp(sizeDp), dp(sizeDp)));
        return b;
    }

    static final int PRIMARY = 0, TONAL = 1, OUTLINE = 2, GHOST = 3, DANGER = 4;

    /** Schaltfläche (Pille) mit optionalem Symbol; Inhalt immer mittig, auch bei voller Breite. */
    static LinearLayout button(Context c, CharSequence label, int iconRes, int style) {
        LinearLayout b = new LinearLayout(c);
        b.setOrientation(LinearLayout.HORIZONTAL);
        b.setGravity(Gravity.CENTER);
        b.setClickable(true);
        b.setFocusable(true);
        b.setPadding(dp(18), 0, dp(18), 0);
        b.setMinimumHeight(dp(46));
        int fg;
        Drawable bg;
        switch (style) {
            case PRIMARY:
                fg = ON_ACCENT;
                bg = ripple(gradient(accent, mix(accent, CYAN, 0.35f), 23, GradientDrawable.Orientation.LEFT_RIGHT), 23);
                break;
            case OUTLINE:
                fg = TEXT;
                bg = ripple(rect(Color.TRANSPARENT, 23, alpha(TEXT, 70), 1), 23);
                break;
            case GHOST:
                fg = accent;
                bg = ripple(rect(Color.TRANSPARENT, 23, 0, 0), 23);
                break;
            case DANGER:
                fg = Color.WHITE;
                bg = ripple(rect(LIVE, 23, 0, 0), 23);
                break;
            default:
                fg = TEXT;
                bg = ripple(rect(SURFACE_2, 23, LINE, 1), 23);
        }
        b.setBackground(bg);
        if (iconRes != 0) {
            ImageView ic = icon(c, iconRes, fg, 20);
            b.addView(ic, lp(dp(20), dp(20), 0, 0, 8, 0));
        }
        TextView t = text(c, label, 14, fg, MEDIUM);
        t.setSingleLine(true);
        t.setEllipsize(TextUtils.TruncateAt.END);
        b.addView(t);
        b.setContentDescription(label);
        return b;
    }

    static TextView chip(Context c, CharSequence label, boolean selected) {
        TextView v = text(c, label, 13, selected ? ON_ACCENT : TEXT_2, MEDIUM);
        v.setGravity(Gravity.CENTER);
        v.setPadding(dp(14), 0, dp(14), 0);
        v.setMinHeight(dp(34));
        v.setSingleLine(true);
        v.setClickable(true);
        v.setBackground(ripple(selected ? rect(accent, 17, 0, 0) : rect(SURFACE, 17, LINE, 1), 17));
        return v;
    }

    static void setChipSelected(TextView v, boolean selected) {
        v.setTextColor(selected ? ON_ACCENT : TEXT_2);
        v.setBackground(ripple(selected ? rect(accent, 17, 0, 0) : rect(SURFACE, 17, LINE, 1), 17));
    }

    /** Quadratisches, abgerundetes Bild mit Platzhalter-Hintergrund. */
    static ImageView cover(Context c, float radiusDp) {
        ImageView v = new ImageView(c);
        v.setScaleType(ImageView.ScaleType.CENTER_CROP);
        v.setBackgroundColor(SURFACE_2);
        final float r = dp(radiusDp);
        v.setOutlineProvider(new ViewOutlineProvider() {
            @Override public void getOutline(View view, Outline outline) {
                outline.setRoundRect(0, 0, view.getWidth(), view.getHeight(), r);
            }
        });
        v.setClipToOutline(true);
        return v;
    }

    static TextView liveBadge(Context c) {
        return liveBadge(c, false);
    }

    static TextView liveBadge(Context c, boolean small) {
        TextView v = text(c, "LIVE", small ? 8 : 9, Color.WHITE, BOLD);
        v.setLetterSpacing(0.05f);
        v.setGravity(Gravity.CENTER);
        v.setPadding(dp(small ? 5 : 7), dp(small ? 2 : 3), dp(small ? 5 : 7), dp(small ? 2 : 3));
        v.setBackground(rect(LIVE, small ? 5 : 7, 0, 0));
        return v;
    }

    /** Überschrift eines Abschnitts mit optionalem Link rechts ("Alle"). */
    static LinearLayout sectionHeader(Context c, String title, String actionLabel, Runnable action) {
        LinearLayout row = hbox(c);
        row.setPadding(0, 0, 0, dp(10));
        TextView t = text(c, title, 18, TEXT, BOLD);
        row.addView(t, lpW(1f));
        if (actionLabel != null && action != null) {
            TextView a = text(c, actionLabel, 13, accent, MEDIUM);
            a.setPadding(dp(8), dp(6), 0, dp(6));
            a.setOnClickListener(v -> action.run());
            row.addView(a);
        }
        return row;
    }

    /** Waagerechte Leiste; der innere Container ist das einzige Kind. */
    static HorizontalScrollView rail(Context c, float sidePaddingDp) {
        HorizontalScrollView s = new HorizontalScrollView(c);
        s.setHorizontalScrollBarEnabled(false);
        s.setOverScrollMode(View.OVER_SCROLL_NEVER);
        s.setClipToPadding(false);
        LinearLayout inner = new LinearLayout(c);
        inner.setOrientation(LinearLayout.HORIZONTAL);
        s.addView(inner, new FrameLayout.LayoutParams(WRAP, WRAP));
        s.setPadding(dp(sidePaddingDp), 0, dp(sidePaddingDp), 0);
        return s;
    }

    static LinearLayout railInner(HorizontalScrollView rail) {
        return (LinearLayout) rail.getChildAt(0);
    }

    /** Zeile für Menüs und Einstellungen: Symbol-Kreis, Titel, Untertitel, Pfeil. */
    static LinearLayout row(Context c, int iconRes, String title, String sub, Runnable onClick) {
        LinearLayout r = hbox(c);
        r.setPadding(dp(14), dp(12), dp(12), dp(12));
        r.setBackground(onClick != null ? surface(18) : rect(SURFACE, 18, LINE, 1));
        r.setClickable(onClick != null);
        r.setFocusable(onClick != null);
        FrameLayout bubble = new FrameLayout(c);
        bubble.setBackground(rect(alpha(accent, 40), 14, 0, 0));
        ImageView ic = icon(c, iconRes, accent, 22);
        bubble.addView(ic, new FrameLayout.LayoutParams(dp(22), dp(22), Gravity.CENTER));
        r.addView(bubble, lp(dp(44), dp(44)));
        LinearLayout copy = vbox(c);
        copy.setPadding(dp(14), 0, dp(8), 0);
        copy.addView(text(c, title, 15, TEXT, MEDIUM));
        if (sub != null && !sub.isEmpty()) {
            TextView s = text(c, sub, 12, MUTED, REGULAR);
            s.setPadding(0, dp(3), 0, 0);
            ellipsize(s, 2);
            copy.addView(s);
        }
        r.addView(copy, lpW(1f));
        if (onClick != null) {
            r.addView(icon(c, R.drawable.ic_chevron, MUTED, 22));
            r.setOnClickListener(v -> onClick.run());
        }
        return r;
    }

    static LinearLayout switchRow(Context c, String title, String sub, boolean checked, java.util.function.Consumer<Boolean> change) {
        LinearLayout r = hbox(c);
        r.setPadding(dp(16), dp(12), dp(12), dp(12));
        r.setBackground(rect(SURFACE, 18, LINE, 1));
        LinearLayout copy = vbox(c);
        copy.addView(text(c, title, 15, TEXT, MEDIUM));
        if (sub != null && !sub.isEmpty()) {
            TextView s = text(c, sub, 12, MUTED, REGULAR);
            s.setPadding(0, dp(3), 0, 0);
            copy.addView(s);
        }
        r.addView(copy, lpW(1f));
        Switch sw = new Switch(c);
        sw.setChecked(checked);
        sw.setThumbTintList(new ColorStateList(new int[][]{{android.R.attr.state_checked}, {}}, new int[]{accent, MUTED}));
        sw.setTrackTintList(new ColorStateList(new int[][]{{android.R.attr.state_checked}, {}}, new int[]{alpha(accent, 110), alpha(MUTED, 70)}));
        sw.setOnCheckedChangeListener((b, on) -> change.accept(on));
        r.addView(sw);
        r.setOnClickListener(v -> sw.toggle());
        return r;
    }

    /** Umschalter mit mehreren Segmenten (z. B. Alle | laut.fm | World Radio). */
    static LinearLayout segmented(Context c, String[] labels, int selected, java.util.function.IntConsumer onSelect) {
        LinearLayout box = hbox(c);
        box.setPadding(dp(3), dp(3), dp(3), dp(3));
        box.setBackground(rect(SURFACE, 22, LINE, 1));
        final TextView[] items = new TextView[labels.length];
        for (int i = 0; i < labels.length; i++) {
            final int idx = i;
            TextView t = text(c, labels[i], 13, i == selected ? ON_ACCENT : TEXT_2, MEDIUM);
            t.setGravity(Gravity.CENTER);
            t.setMinHeight(dp(38));
            t.setBackground(i == selected ? rect(accent, 19, 0, 0) : null);
            t.setOnClickListener(v -> {
                for (int n = 0; n < items.length; n++) {
                    items[n].setTextColor(n == idx ? ON_ACCENT : TEXT_2);
                    items[n].setBackground(n == idx ? rect(accent, 19, 0, 0) : null);
                }
                onSelect.accept(idx);
            });
            items[i] = t;
            box.addView(t, lpW(1f));
        }
        return box;
    }

    // ---------- Animierte Bausteine ----------

    /** Drei springende Balken: zeigt, dass gerade Musik läuft. */
    static final class Equalizer extends View {
        private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        private final RectF r = new RectF();
        private ValueAnimator anim;
        private float t = 0f;
        private boolean playing = false;

        Equalizer(Context c) {
            super(c);
            paint.setColor(CYAN);
        }

        void setColor(int color) {
            paint.setColor(color);
            invalidate();
        }

        void setPlaying(boolean on) {
            playing = on;
            if (on && !reduceMotion) {
                if (anim == null) {
                    anim = ValueAnimator.ofFloat(0f, (float) (Math.PI * 2));
                    anim.setDuration(900);
                    anim.setRepeatCount(ValueAnimator.INFINITE);
                    anim.setInterpolator(new LinearInterpolator());
                    anim.addUpdateListener(a -> {
                        t = (float) a.getAnimatedValue();
                        invalidate();
                    });
                }
                if (!anim.isRunning()) anim.start();
            } else if (anim != null) {
                anim.cancel();
            }
            invalidate();
        }

        @Override protected void onDetachedFromWindow() {
            super.onDetachedFromWindow();
            if (anim != null) anim.cancel();
        }

        @Override protected void onAttachedToWindow() {
            super.onAttachedToWindow();
            if (playing && anim != null && !anim.isRunning() && !reduceMotion) anim.start();
        }

        @Override protected void onDraw(Canvas canvas) {
            int w = getWidth(), h = getHeight();
            if (w <= 0 || h <= 0) return;
            int n = 4;
            float gap = w * 0.10f;
            float bw = (w - gap * (n - 1)) / n;
            for (int i = 0; i < n; i++) {
                float f;
                if (!playing) f = 0.28f;
                else if (reduceMotion) f = 0.3f + 0.15f * i;
                else f = 0.35f + 0.65f * (0.5f + 0.5f * (float) Math.sin(t * (1.0 + i * 0.35) + i * 1.7));
                float bh = h * Math.min(1f, f);
                float left = i * (bw + gap);
                r.set(left, h - bh, left + bw, h);
                canvas.drawRoundRect(r, bw / 2, bw / 2, paint);
            }
        }
    }

    /** Grauer, leise pulsierender Platzhalter, bis Inhalte geladen sind. */
    static View skeleton(Context c, float wDp, float hDp, float radiusDp) {
        View v = new View(c);
        v.setBackground(rect(SURFACE_2, radiusDp, 0, 0));
        v.setLayoutParams(new ViewGroup.LayoutParams(wDp < 0 ? (int) wDp : dp(wDp), dp(hDp)));
        if (!reduceMotion) {
            ObjectAnimator a = ObjectAnimator.ofFloat(v, "alpha", 0.45f, 1f);
            a.setDuration(900);
            a.setRepeatMode(ValueAnimator.REVERSE);
            a.setRepeatCount(ValueAnimator.INFINITE);
            v.addOnAttachStateChangeListener(new View.OnAttachStateChangeListener() {
                @Override public void onViewAttachedToWindow(View view) {
                    a.start();
                }

                @Override public void onViewDetachedFromWindow(View view) {
                    a.cancel();
                }
            });
        }
        return v;
    }

    // ---------- Farben aus Bildern ----------

    /** Kräftige, aber dunkle Hintergrundfarbe aus dem Mittelwert eines Bildes (für den Vollbild-Player). */
    static int dominantColor(Bitmap src, int fallback) {
        if (src == null || src.isRecycled() || src.getWidth() < 2) return fallback;
        try {
            Bitmap s = Bitmap.createScaledBitmap(src, 12, 12, true);
            float rs = 0, gs = 0, bs = 0, ws = 0;
            for (int y = 0; y < 12; y++) {
                for (int x = 0; x < 12; x++) {
                    int p = s.getPixel(x, y);
                    float[] hsv = new float[3];
                    Color.colorToHSV(p, hsv);
                    float w = 0.15f + hsv[1] * hsv[2];
                    rs += Color.red(p) * w;
                    gs += Color.green(p) * w;
                    bs += Color.blue(p) * w;
                    ws += w;
                }
            }
            if (s != src) s.recycle();
            if (ws <= 0) return fallback;
            int avg = Color.rgb(Math.round(rs / ws), Math.round(gs / ws), Math.round(bs / ws));
            float[] hsv = new float[3];
            Color.colorToHSV(avg, hsv);
            hsv[1] = Math.min(1f, Math.max(0.35f, hsv[1]));
            hsv[2] = Math.min(0.62f, Math.max(0.28f, hsv[2] * 0.75f));
            return Color.HSVToColor(hsv);
        } catch (Exception e) {
            return fallback;
        }
    }

    /** Ob die Breite (dp) für mehrspaltige Anordnung (Tablet/Querformat) reicht. */
    static int columns(Context c, int minCardDp, int max) {
        float w = c.getResources().getDisplayMetrics().widthPixels / density;
        return Math.max(2, Math.min(max, (int) (w / minCardDp)));
    }
}
