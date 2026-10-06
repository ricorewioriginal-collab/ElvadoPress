package app.elvadopress.client;

import android.app.Activity;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;

import net.openid.appauth.AuthorizationRequest;
import net.openid.appauth.AuthorizationService;
import net.openid.appauth.AuthorizationServiceConfiguration;
import net.openid.appauth.ResponseTypeValues;

public final class OidcManager {
    private OidcManager() {}

    public static void startLogin(Activity activity) {
        AuthConfig cfg = AuthConfig.load(activity);
        if (!cfg.isReady()) {
            android.widget.Toast.makeText(
                    activity,
                    "OAuth-Broker ist noch nicht konfiguriert. Lokales Profil bleibt aktiv.",
                    android.widget.Toast.LENGTH_LONG
            ).show();
            return;
        }

        Uri issuer = Uri.parse(cfg.issuer);
        AuthorizationServiceConfiguration.fetchFromIssuer(
                issuer,
                (serviceConfig, ex) -> {
                    if (serviceConfig == null) {
                        activity.runOnUiThread(() ->
                                android.widget.Toast.makeText(
                                        activity,
                                        "OIDC-Konfiguration konnte nicht geladen werden.",
                                        android.widget.Toast.LENGTH_LONG
                                ).show()
                        );
                        return;
                    }

                    AuthorizationRequest request = new AuthorizationRequest.Builder(
                            serviceConfig,
                            cfg.clientId,
                            ResponseTypeValues.CODE,
                            Uri.parse(cfg.redirectUri)
                    )
                            .setScope("openid profile email")
                            .build();

                    Intent completeIntent = new Intent(activity, AuthCallbackActivity.class);
                    PendingIntent completePending = PendingIntent.getActivity(
                            activity,
                            1001,
                            completeIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
                    );

                    Intent cancelIntent = new Intent(activity, AccountActivity.class);
                    PendingIntent cancelPending = PendingIntent.getActivity(
                            activity,
                            1002,
                            cancelIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
                    );

                    AuthorizationService authService = new AuthorizationService(activity);
                    authService.performAuthorizationRequest(request, completePending, cancelPending);
                }
        );
    }
}
