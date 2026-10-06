# Admin auth cookie secret

PSFS encrypts the credentials in the admin auth cookie with AES-256-GCM. New
cookies use the per-installation setting `auth.cookie.secret`; the cookie name
remains unchanged for compatibility.

## Configure a secret

Generate 32 random bytes as a 64-character hexadecimal string:

```sh
openssl rand -hex 32
```

Add the generated value to the deployment's ignored `config/config.json`:

```json
{
  "auth.cookie.secret": "<64 hexadecimal characters>"
}
```

Use a different value for each installation. All PHP instances serving the
same installation must use the same value. This value is plaintext in the JSON
file; encrypting it with a fixed key shipped in PSFS would not protect it. Keep
the file readable only by the account that runs PHP:

```sh
chmod 600 config/config.json
```

PSFS also creates or tightens the file to mode `0600` whenever it saves the
configuration. Apply the command above to an existing file before relying on
the new secret, since an untouched existing file keeps its current permissions.

If the setting is missing or is not exactly 64 hexadecimal characters, PSFS
does not issue a persistent admin auth cookie and logs a warning. The current
admin session is still established.

## Rotate the secret

Replace the value with a newly generated one and restart PHP workers so they
reload the configuration. Cookies encrypted with the previous configured
secret will no longer decrypt and users will need to authenticate again.

For compatibility, PSFS still accepts cookies encrypted with the historical
source key, and the older `ADMIN_ID_TOKEN` key, as read-only fallbacks. Those
fallbacks do not write new cookies. While they remain enabled, rotating
`auth.cookie.secret` alone cannot invalidate a legacy cookie; removing the
fallback requires a separate compatibility decision.
