package app.elvadopress.client;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.View;
import android.widget.FrameLayout;
import android.widget.ImageView;

import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class SplashActivity extends Activity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(7, 10, 28));
        applySystemInsets(root);

        ImageView image = new ImageView(this);
        image.setScaleType(ImageView.ScaleType.CENTER_CROP);

        Bitmap bitmap = null;
        try (InputStream in = getAssets().open("config/startscreen.png")) {
            bitmap = BitmapFactory.decodeStream(in);
        } catch (Exception ignored) {
        }

        if (bitmap != null) {
            image.setImageBitmap(bitmap);
        } else {
            image.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
            image.setImageResource(R.drawable.app_logo);
            int p = Math.round(56 * getResources().getDisplayMetrics().density);
            image.setPadding(p, p, p, p);
        }

        root.addView(image, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT,
                Gravity.CENTER));

        setContentView(root);

        SharedPreferences prefs = getSharedPreferences("app_prefs", MODE_PRIVATE);
        String remoteSplash = prefs.getString("branding_startscreen", "");
        if (remoteSplash != null && !remoteSplash.trim().isEmpty()) {
            new Thread(() -> {
                HttpURLConnection connection = null;
                try {
                    connection = (HttpURLConnection) new URL(remoteSplash).openConnection();
                    connection.setConnectTimeout(1800);
                    connection.setReadTimeout(2500);
                    connection.setDoInput(true);
                    connection.connect();
                    Bitmap remote = BitmapFactory.decodeStream(connection.getInputStream());
                    if (remote != null) runOnUiThread(() -> image.setImageBitmap(remote));
                } catch (Exception ignored) {
                } finally {
                    if (connection != null) connection.disconnect();
                }
            }).start();
        }

        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            startActivity(new Intent(this, WebShellActivity.class));   // Website bzw. Baukasten-App (App-Typ siehe android/brands.json)
            finish();
        }, 950);
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
}
