package app.elvadopress.client;

import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.widget.Toast;

import net.openid.appauth.AuthorizationException;
import net.openid.appauth.AuthorizationResponse;
import net.openid.appauth.AuthorizationService;
import net.openid.appauth.TokenRequest;

public class AuthCallbackActivity extends Activity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        AuthorizationResponse response = AuthorizationResponse.fromIntent(getIntent());
        AuthorizationException exception = AuthorizationException.fromIntent(getIntent());

        if (response == null) {
            Toast.makeText(this, "Anmeldung wurde abgebrochen.", Toast.LENGTH_LONG).show();
            finishToAccount();
            return;
        }

        AuthorizationService service = new AuthorizationService(this);
        TokenRequest tokenRequest = response.createTokenExchangeRequest();

        service.performTokenRequest(tokenRequest, (tokenResponse, ex) -> {
            runOnUiThread(() -> {
                try {
                    if (tokenResponse == null || tokenResponse.idToken == null) {
                        Toast.makeText(
                                this,
                                "Anmeldung abgeschlossen, aber kein ID-Token erhalten.",
                                Toast.LENGTH_LONG
                        ).show();
                    } else {
                        new AccountStore(this).saveIdToken(tokenResponse.idToken);
                        Toast.makeText(this, "Konto verbunden.", Toast.LENGTH_SHORT).show();
                    }
                } finally {
                    service.dispose();
                    finishToAccount();
                }
            });
        });
    }

    private void finishToAccount() {
        Intent i = new Intent(this, AccountActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        startActivity(i);
        finish();
    }
}
