package app.elvadopress.client;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.Signature;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;

import androidx.core.content.FileProvider;

import org.json.JSONObject;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;

/**
 * Selbst-Update: Die neue APK kommt von der eigenen Seite der Marke (Angaben aus app_config: url, sha256, size), wird
 * geladen, auf Größe/Prüfsumme und denselben Signaturschlüssel geprüft und dann dem Android-Installer übergeben.
 * Die Bestätigung gibt der Nutzer (erst im App-Dialog, dann im System-Installer).
 */
final class AppUpdater {
    private AppUpdater() {}

    /** Rückmeldung beim Laden; false = abbrechen. */
    interface Progress { boolean tick(int percent); }

    static final class Info {
        final String url, sha256, latest;
        final long size;

        Info(JSONObject update) {
            url = update.optString("url", "");
            sha256 = update.optString("sha256", "").toLowerCase(Locale.ROOT);
            size = update.optLong("size", 0);
            latest = update.optString("latest", "");
        }

        boolean usable() { return url.startsWith("https://"); }
    }

    static File target(Context c) {
        File d = new File(c.getCacheDir(), "updates");
        //noinspection ResultOfMethodCallIgnored
        d.mkdirs();
        return new File(d, "app-update.apk");
    }

    static File download(Context c, Info info, Progress p) throws Exception {
        File f = target(c);
        if (f.isFile() && verify(f, info)) return f; // schon geladen (z. B. nach der Freigabe in den Einstellungen)
        File tmp = new File(f.getParentFile(), "app-update.part");
        HttpURLConnection con = (HttpURLConnection) new URL(info.url).openConnection();
        con.setConnectTimeout(12000);
        con.setReadTimeout(30000);
        con.setInstanceFollowRedirects(true);
        con.setRequestProperty("User-Agent", "ElvadoPressApp/1.0");
        try {
            if (con.getResponseCode() != 200) throw new IOException("HTTP " + con.getResponseCode());
            long total = con.getContentLengthLong();
            if (total <= 0) total = info.size;
            try (InputStream in = con.getInputStream(); FileOutputStream out = new FileOutputStream(tmp)) {
                byte[] buf = new byte[65536];
                long done = 0;
                int n, last = -1;
                while ((n = in.read(buf)) > 0) {
                    out.write(buf, 0, n);
                    done += n;
                    if (total > 0 && p != null) {
                        int pc = (int) Math.min(100, done * 100 / total);
                        if (pc != last) {
                            last = pc;
                            if (!p.tick(pc)) {
                                //noinspection ResultOfMethodCallIgnored
                                tmp.delete();
                                throw new IOException("abgebrochen");
                            }
                        }
                    }
                }
            }
        } finally {
            con.disconnect();
        }
        if (!verify(tmp, info)) {
            //noinspection ResultOfMethodCallIgnored
            tmp.delete();
            throw new IOException("Prüfsumme oder Größe stimmt nicht");
        }
        //noinspection ResultOfMethodCallIgnored
        f.delete();
        if (!tmp.renameTo(f)) throw new IOException("Datei konnte nicht abgelegt werden");
        return f;
    }

    static boolean verify(File f, Info info) {
        try {
            if (info.size > 0 && f.length() != info.size) return false;
            if (info.sha256.length() == 64) return sha256(f).equals(info.sha256);
            return f.length() > 1_000_000; // ohne Prüfsumme wenigstens eine plausible Größe verlangen
        } catch (Exception e) {
            return false;
        }
    }

    static String sha256(File f) throws Exception {
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        try (InputStream in = new FileInputStream(f)) {
            byte[] buf = new byte[65536];
            int n;
            while ((n = in.read(buf)) > 0) md.update(buf, 0, n);
        }
        StringBuilder b = new StringBuilder();
        for (byte x : md.digest()) b.append(String.format(Locale.ROOT, "%02x", x));
        return b.toString();
    }

    /** Android installiert ein Update nur über eine App mit demselben Signaturschlüssel. Im Zweifel true (dann entscheidet das System). */
    @SuppressWarnings("deprecation")
    static boolean sameSigner(Context c, File apk) {
        try {
            PackageManager pm = c.getPackageManager();
            Set<String> mine = new HashSet<>(), theirs = new HashSet<>();
            if (Build.VERSION.SDK_INT >= 28) {
                PackageInfo a = pm.getPackageInfo(c.getPackageName(), PackageManager.GET_SIGNING_CERTIFICATES);
                PackageInfo b = pm.getPackageArchiveInfo(apk.getAbsolutePath(), PackageManager.GET_SIGNING_CERTIFICATES);
                if (a == null || b == null || a.signingInfo == null || b.signingInfo == null) return true;
                for (Signature s : a.signingInfo.getApkContentsSigners()) mine.add(s.toCharsString());
                for (Signature s : b.signingInfo.getApkContentsSigners()) theirs.add(s.toCharsString());
            } else {
                PackageInfo a = pm.getPackageInfo(c.getPackageName(), PackageManager.GET_SIGNATURES);
                PackageInfo b = pm.getPackageArchiveInfo(apk.getAbsolutePath(), PackageManager.GET_SIGNATURES);
                if (a == null || b == null || a.signatures == null || b.signatures == null) return true;
                for (Signature s : a.signatures) mine.add(s.toCharsString());
                for (Signature s : b.signatures) theirs.add(s.toCharsString());
            }
            if (mine.isEmpty() || theirs.isEmpty()) return true;
            return mine.equals(theirs);
        } catch (Exception e) {
            return true;
        }
    }

    static boolean canInstall(Context c) {
        return Build.VERSION.SDK_INT < 26 || c.getPackageManager().canRequestPackageInstalls();
    }

    static void openInstallSettings(Activity a) {
        a.startActivity(new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + a.getPackageName())));
    }

    static void install(Activity a, File apk) {
        Uri uri = FileProvider.getUriForFile(a, a.getPackageName() + ".updates", apk);
        Intent i = new Intent(Intent.ACTION_VIEW)
                .setDataAndType(uri, "application/vnd.android.package-archive")
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
        a.startActivity(i);
    }
}
