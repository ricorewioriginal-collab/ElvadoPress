package app.elvadopress.client;

import android.content.SharedPreferences;
import android.os.Bundle;

import androidx.annotation.Nullable;
import androidx.media3.cast.CastPlayer;
import androidx.media3.cast.SessionAvailabilityListener;
import androidx.media3.common.MediaItem;
import androidx.media3.common.MediaMetadata;
import androidx.media3.common.Player;
import androidx.media3.exoplayer.ExoPlayer;
import androidx.media3.session.LibraryResult;
import androidx.media3.session.MediaLibraryService;
import androidx.media3.session.MediaSession;

import com.google.android.gms.cast.framework.CastContext;
import com.google.common.collect.ImmutableList;
import com.google.common.util.concurrent.Futures;
import com.google.common.util.concurrent.ListenableFuture;

import java.util.ArrayList;
import java.util.List;

/**
 * Wiedergabe-Dienst: ExoPlayer lokal, CastPlayer wenn eine Cast-Verbindung besteht (die Session schaltet dann um).
 * Als MediaLibraryService liefert er Android Auto / Assistenten einen Browse-Baum mit den Sendern und Favoriten.
 */
public class PlaybackService extends MediaLibraryService {
    private static final String ROOT = "root";
    private static final String STATIONS = "stations";
    private static final String FAVORITES = "favorites";

    private ExoPlayer exo;
    private CastPlayer cast;
    private MediaLibrarySession session;
    private SharedPreferences prefs;

    @Override
    public void onCreate() {
        super.onCreate();
        prefs = getSharedPreferences("app_prefs", MODE_PRIVATE);
        exo = new ExoPlayer.Builder(this).build();
        exo.setHandleAudioBecomingNoisy(true);
        session = new MediaLibrarySession.Builder(this, exo, new LibraryCallback()).build();
        initCast();
    }

    private void initCast() {
        // Ohne Google Play Services (oder bei abgeschaltetem Cast im CMS) läuft alles lokal weiter
        try {
            if (!prefs.getBoolean("app_f_cast", true)) return;
            CastContext ctx = CastContext.getSharedInstance(this);
            cast = new CastPlayer(ctx);
            cast.setSessionAvailabilityListener(new SessionAvailabilityListener() {
                @Override public void onCastSessionAvailable() { switchTo(cast); }
                @Override public void onCastSessionUnavailable() { switchTo(exo); }
            });
            if (cast.isCastSessionAvailable()) switchTo(cast);
        } catch (Throwable t) {
            cast = null;
        }
    }

    /** Aktuelles Item samt Wiedergabezustand auf den anderen Player übergeben (Live-Stream: Position 0). */
    private void switchTo(Player target) {
        if (session == null) return;
        Player old = session.getPlayer();
        if (old == target || target == null) return;
        MediaItem current = old.getCurrentMediaItem();
        boolean playing = old.getPlayWhenReady();
        old.stop();
        old.clearMediaItems();
        session.setPlayer(target);
        if (current != null) {
            target.setMediaItem(current);
            target.prepare();
            target.setPlayWhenReady(playing);
        }
    }

    @Nullable
    @Override
    public MediaLibrarySession onGetSession(MediaSession.ControllerInfo controllerInfo) {
        return session;
    }

    @Override
    public void onTaskRemoved(android.content.Intent rootIntent) {
        Player p = session == null ? null : session.getPlayer();
        if (p != null && !p.getPlayWhenReady()) stopSelf();
    }

    @Override
    public void onDestroy() {
        if (cast != null) {
            cast.setSessionAvailabilityListener(null);
            cast.release();
            cast = null;
        }
        if (session != null) {
            session.release();
            session = null;
        }
        if (exo != null) {
            exo.release();
            exo = null;
        }
        super.onDestroy();
    }

    private String brand() { return getString(R.string.app_name); }

    private MediaItem folder(String id, String title) {
        return new MediaItem.Builder().setMediaId(id)
                .setMediaMetadata(new MediaMetadata.Builder().setTitle(title).setIsBrowsable(true).setIsPlayable(false)
                        .setMediaType(MediaMetadata.MEDIA_TYPE_FOLDER_RADIO_STATIONS).build()).build();
    }

    private ImmutableList<MediaItem> children(String parent) {
        ImmutableList.Builder<MediaItem> b = ImmutableList.builder();
        if (ROOT.equals(parent)) {
            b.add(folder(STATIONS, "Sender"));
            b.add(folder(FAVORITES, "Favoriten"));
        } else if (STATIONS.equals(parent)) {
            for (String id : Stations.owned(this, prefs)) b.add(Stations.stationItem(prefs, id, brand()));
        } else if (FAVORITES.equals(parent)) {
            List<String> favs = new ArrayList<>(prefs.getStringSet("favorites", new java.util.HashSet<>()));
            java.util.Collections.sort(favs);
            for (String id : favs) if (!id.contains(":")) b.add(Stations.stationItem(prefs, id, brand()));
        }
        return b.build();
    }

    private final class LibraryCallback implements MediaLibrarySession.Callback {
        @Override
        public ListenableFuture<LibraryResult<MediaItem>> onGetLibraryRoot(MediaLibrarySession s, MediaSession.ControllerInfo browser, @Nullable LibraryParams params) {
            return Futures.immediateFuture(LibraryResult.ofItem(folder(ROOT, brand()), params));
        }

        @Override
        public ListenableFuture<LibraryResult<ImmutableList<MediaItem>>> onGetChildren(MediaLibrarySession s, MediaSession.ControllerInfo browser,
                                                                                         String parentId, int page, int pageSize, @Nullable LibraryParams params) {
            ImmutableList<MediaItem> all = children(parentId);
            int from = Math.min(page * pageSize, all.size());
            int to = Math.min(from + pageSize, all.size());
            return Futures.immediateFuture(LibraryResult.ofItemList(all.subList(from, to), params));
        }

        @Override
        public ListenableFuture<LibraryResult<MediaItem>> onGetItem(MediaLibrarySession s, MediaSession.ControllerInfo browser, String mediaId) {
            if (mediaId.startsWith("station:")) return Futures.immediateFuture(LibraryResult.ofItem(Stations.stationItem(prefs, mediaId.substring(8), brand()), null));
            if (ROOT.equals(mediaId)) return Futures.immediateFuture(LibraryResult.ofItem(folder(ROOT, brand()), null));
            return Futures.immediateFuture(LibraryResult.ofError(LibraryResult.RESULT_ERROR_BAD_VALUE));
        }

        // Android Auto schickt nur die mediaId; hier wird sie zum abspielbaren Item aufgelöst
        @Override
        public ListenableFuture<List<MediaItem>> onAddMediaItems(MediaSession s, MediaSession.ControllerInfo controller, List<MediaItem> items) {
            List<MediaItem> out = new ArrayList<>();
            for (MediaItem it : items) {
                if (it.localConfiguration != null) out.add(it);
                else if (it.mediaId.startsWith("station:")) out.add(Stations.stationItem(prefs, it.mediaId.substring(8), brand()));
            }
            return Futures.immediateFuture(out);
        }
    }
}
