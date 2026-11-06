<?php

namespace App\Support\Setup;

/**
 * Identity providers offered by the setup wizard. All of them are used
 * through the generic OIDC driver; they only differ in how the issuer URL
 * is formed and in the instructions shown.
 *
 * In steps, {redirect_uris} becomes "both redirect URIs" (web wizard) or
 * "the redirect URI" (terminal, which has no test sign-in). In snippets,
 * {public_callback} and {test_callback} are replaced; lines with the test
 * URI are dropped in the terminal. test_note is advice that only matters
 * for the test sign-in.
 *
 * @phpstan-type Tenant array{label: string, placeholder: string, hint: string}
 * @phpstan-type Preset array{
 *     label: string,
 *     kind: 'Self-hosted'|'Cloud'|'Any',
 *     summary: string,
 *     docs: string,
 *     issuer: ?string,
 *     issuer_placeholder: string,
 *     issuer_hint: string,
 *     tenant: ?Tenant,
 *     steps: list<string>,
 *     snippet: ?string,
 *     test_note: ?string,
 *     client_id_hint: string,
 *     client_secret_hint: string,
 * }
 */
class SsoPresets
{
    /**
     * What every provider must be told, whatever its screens call it.
     *
     * @return array<string, string>
     */
    public static function clientRequirements(): array
    {
        return [
            'Client type' => 'Confidential (web application, with a client secret)',
            'Grant / flow' => 'Authorization code',
            'Client authentication' => 'Client secret sent in the request body (client_secret_post)',
            'Scopes' => 'openid email profile',
            'PKCE' => 'S256 (allowed or required)',
        ];
    }

    /**
     * @return array<string, Preset>
     */
    public static function all(): array
    {
        return [
            'keycloak' => [
                'label' => 'Keycloak',
                'kind' => 'Self-hosted',
                'summary' => 'Open-source identity server with realms.',
                'docs' => 'https://www.keycloak.org/docs/latest/server_admin/index.html#_oidc_clients',
                'issuer' => null,
                'issuer_placeholder' => 'https://keycloak.example.com/realms/home',
                'issuer_hint' => 'Your Keycloak address plus /realms/ and the realm name (not the admin console URL).',
                'tenant' => null,
                'steps' => [
                    'Open the realm you want to use, then Clients → Create client.',
                    'Client type "OpenID Connect", Client ID for example "spools" → Next.',
                    'Turn on "Client authentication", keep only "Standard flow" checked → Next.',
                    'Put {redirect_uris} below in "Valid redirect URIs" → Save.',
                    'Open the Credentials tab and copy the client secret.',
                    'Users need "Email verified" turned on, otherwise their sign-in is refused.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'The Client ID you chose when creating the client.',
                'client_secret_hint' => 'Clients → your client → Credentials → Client secret.',
            ],
            'authentik' => [
                'label' => 'Authentik',
                'kind' => 'Self-hosted',
                'summary' => 'Open-source identity provider and SSO portal.',
                'docs' => 'https://docs.goauthentik.io/add-secure-apps/providers/oauth2/',
                'issuer' => null,
                'issuer_placeholder' => 'https://auth.example.com/application/o/spools/',
                'issuer_hint' => 'The "OpenID Configuration Issuer" shown on the provider page.',
                'tenant' => null,
                'steps' => [
                    'Admin interface → Applications → Applications → "Create with provider".',
                    'Name "Spool Tracker", slug "spools" → provider type "OAuth2/OpenID Connect".',
                    'Client type "Confidential". Add {redirect_uris} below with matching mode "Strict".',
                    'Finish the wizard, then open the provider to see the Client ID, Client Secret and issuer.',
                    'Optional: bind a group or users to the application to limit who can sign in there.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'Applications → Providers → your provider → Client ID.',
                'client_secret_hint' => 'Same page, Client Secret (click to reveal).',
            ],
            'authelia' => [
                'label' => 'Authelia',
                'kind' => 'Self-hosted',
                'summary' => 'Lightweight SSO configured in YAML.',
                'docs' => 'https://www.authelia.com/configuration/identity-providers/openid-connect/clients/',
                'issuer' => null,
                'issuer_placeholder' => 'https://auth.example.com',
                'issuer_hint' => 'The address of your Authelia portal.',
                'tenant' => null,
                'steps' => [
                    'Generate a secret: docker run --rm authelia/authelia:latest authelia crypto hash generate pbkdf2 --variant sha512 --random --random.length 72 --random.charset rfc3986',
                    'It prints a "Random Password" (paste that here as the client secret) and a "Digest" (goes in the config).',
                    'Add the client below to identity_providers.oidc.clients in configuration.yml, with the Digest as client_secret.',
                    'Restart Authelia.',
                ],
                'snippet' => <<<'YAML'
                    - client_id: 'spools'
                      client_name: 'Spool Tracker'
                      client_secret: '$pbkdf2-sha512$310000$...'  # the Digest
                      public: false
                      authorization_policy: 'two_factor'
                      require_pkce: true
                      pkce_challenge_method: 'S256'
                      redirect_uris:
                        - '{public_callback}'
                        - '{test_callback}'
                      scopes: ['openid', 'profile', 'email']
                      response_types: ['code']
                      grant_types: ['authorization_code']
                      token_endpoint_auth_method: 'client_secret_post'
                    YAML,
                'test_note' => null,
                'client_id_hint' => 'The client_id in the YAML, "spools" in the example.',
                'client_secret_hint' => 'The "Random Password" from the generate command, not the Digest.',
            ],
            'zitadel' => [
                'label' => 'Zitadel',
                'kind' => 'Any',
                'summary' => 'Self-hosted or Zitadel Cloud.',
                'docs' => 'https://zitadel.com/docs/guides/integrate/login/oidc/login-users',
                'issuer' => null,
                'issuer_placeholder' => 'https://myinstance-abc123.zitadel.cloud',
                'issuer_hint' => 'The address of your Zitadel instance.',
                'tenant' => null,
                'steps' => [
                    'Console → Projects → Create a project, then "New" application of type "Web".',
                    'Authentication method "POST" (this app sends the secret in the request body).',
                    'Add {redirect_uris} below.',
                    'Create, then copy the Client ID and Client Secret right away; the secret is shown once.',
                ],
                'snippet' => null,
                'test_note' => 'The test redirect URI uses http, so turn on "Development Mode" for the app; turn it off again after setup.',
                'client_id_hint' => 'Shown after creating the app, and on the app page.',
                'client_secret_hint' => 'Only shown once after creating; use "Regenerate secret" if you lost it.',
            ],
            'pocketid' => [
                'label' => 'Pocket ID',
                'kind' => 'Self-hosted',
                'summary' => 'Simple passkey-only provider.',
                'docs' => 'https://pocket-id.org/docs/client-examples',
                'issuer' => null,
                'issuer_placeholder' => 'https://id.example.com',
                'issuer_hint' => 'The address of your Pocket ID.',
                'tenant' => null,
                'steps' => [
                    'Settings → Administration → OIDC Clients → Add OIDC client.',
                    'Name "Spool Tracker", add {redirect_uris} below as Callback URLs.',
                    'Leave "Public client" off, turn PKCE on → Save.',
                    'Copy the Client ID and Client secret; the secret is shown once.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'Shown on the client page.',
                'client_secret_hint' => 'Only shown once after saving; create a new secret if you lost it.',
            ],
            'entra' => [
                'label' => 'Microsoft Entra ID',
                'kind' => 'Cloud',
                'summary' => 'Microsoft 365 / Azure work and school accounts.',
                'docs' => 'https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app',
                'issuer' => 'https://login.microsoftonline.com/{tenant}/v2.0',
                'issuer_placeholder' => '',
                'issuer_hint' => 'Filled in from the tenant ID.',
                'tenant' => [
                    'label' => 'Directory (tenant) ID',
                    'placeholder' => '00000000-0000-0000-0000-000000000000',
                    'hint' => 'Entra admin center → App registrations → your app → Overview → "Directory (tenant) ID".',
                ],
                'steps' => [
                    'entra.microsoft.com → App registrations → New registration.',
                    'Supported account types "Accounts in this organizational directory only".',
                    'Redirect URI platform "Web", paste the normal redirect URI below → Register.',
                    'Certificates & secrets → New client secret; copy the Value (not the Secret ID). Note the expiry date.',
                    'Users need an email address on their account; that is what the allow-list checks.',
                ],
                'snippet' => null,
                'test_note' => 'Authentication → add the test redirect URI as a second Web redirect URI.',
                'client_id_hint' => 'Overview → "Application (client) ID".',
                'client_secret_hint' => 'Certificates & secrets → the Value column, only visible right after creating.',
            ],
            'google' => [
                'label' => 'Google',
                'kind' => 'Cloud',
                'summary' => 'Gmail and Google Workspace accounts.',
                'docs' => 'https://developers.google.com/identity/openid-connect/openid-connect',
                'issuer' => 'https://accounts.google.com',
                'issuer_placeholder' => '',
                'issuer_hint' => 'Always the same for Google.',
                'tenant' => null,
                'steps' => [
                    'console.cloud.google.com → create or pick a project → Google Auth Platform.',
                    'Set up the consent screen. With Gmail accounts choose audience "External" and add yourself as a test user (or publish the app).',
                    'Clients → Create client → application type "Web application".',
                    'Add {redirect_uris} below under "Authorized redirect URIs" → Create.',
                    'Copy the Client ID and Client secret right away; the secret may not be shown again.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'Ends in .apps.googleusercontent.com.',
                'client_secret_hint' => 'Starts with GOCSPX-. Shown when the client is created.',
            ],
            'okta' => [
                'label' => 'Okta',
                'kind' => 'Cloud',
                'summary' => 'Okta Workforce Identity.',
                'docs' => 'https://developer.okta.com/docs/guides/sign-into-web-app-redirect/',
                'issuer' => 'https://{tenant}',
                'issuer_placeholder' => '',
                'issuer_hint' => 'Filled in from your Okta domain.',
                'tenant' => [
                    'label' => 'Okta domain',
                    'placeholder' => 'dev-123456.okta.com',
                    'hint' => 'Shown in the admin console under your name in the top right, without https://.',
                ],
                'steps' => [
                    'Admin console → Applications → Create App Integration.',
                    'Sign-in method "OIDC - OpenID Connect", application type "Web Application".',
                    'Add {redirect_uris} below as "Sign-in redirect URIs".',
                    'Under Assignments choose who may use it, then Save.',
                    'General tab → Client Credentials: copy the Client ID and Client secret.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'General tab → Client Credentials → Client ID.',
                'client_secret_hint' => 'General tab → Client Secrets → copy.',
            ],
            'auth0' => [
                'label' => 'Auth0',
                'kind' => 'Cloud',
                'summary' => 'Hosted identity platform by Okta.',
                'docs' => 'https://auth0.com/docs/get-started/auth0-overview/create-applications/regular-web-apps',
                'issuer' => 'https://{tenant}',
                'issuer_placeholder' => '',
                'issuer_hint' => 'Filled in from your Auth0 domain.',
                'tenant' => [
                    'label' => 'Auth0 domain',
                    'placeholder' => 'example.eu.auth0.com',
                    'hint' => 'Applications → your application → Settings → Domain.',
                ],
                'steps' => [
                    'Dashboard → Applications → Create Application → "Regular Web Applications".',
                    'Settings: put {redirect_uris} below in "Allowed Callback URLs", separated by a comma → Save.',
                    'Credentials tab: keep authentication method "Client Secret (Post)".',
                    'Copy the Domain, Client ID and Client Secret from the Settings tab.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'Settings → Client ID.',
                'client_secret_hint' => 'Settings → Client Secret.',
            ],
            'gitlab' => [
                'label' => 'GitLab',
                'kind' => 'Any',
                'summary' => 'gitlab.com or your own GitLab.',
                'docs' => 'https://docs.gitlab.com/integration/openid_connect_provider/',
                'issuer' => null,
                'issuer_placeholder' => 'https://gitlab.com',
                'issuer_hint' => 'https://gitlab.com, or the address of your own GitLab.',
                'tenant' => null,
                'steps' => [
                    'Avatar → Edit profile → Applications → Add new application (or under a group or the Admin area).',
                    'Name "Spool Tracker", put {redirect_uris} below on separate lines.',
                    'Keep "Confidential" checked and select the scopes openid, profile and email → Save.',
                    'Copy the Application ID and Secret.',
                    'Anyone with a GitLab account can pass this step, so the allow-list in step 3 matters.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'The "Application ID".',
                'client_secret_hint' => 'The "Secret", shown after saving.',
            ],
            'generic' => [
                'label' => 'Other OpenID Connect',
                'kind' => 'Any',
                'summary' => 'Any provider that supports OIDC discovery.',
                'docs' => 'https://openid.net/developers/how-connect-works/',
                'issuer' => null,
                'issuer_placeholder' => 'https://id.example.com',
                'issuer_hint' => 'The URL in front of /.well-known/openid-configuration.',
                'tenant' => null,
                'steps' => [
                    'Create a new OIDC client (also called application or relying party) with the settings in the table below.',
                    'Add {redirect_uris} below.',
                    'Copy the client ID and client secret it gives you.',
                ],
                'snippet' => null,
                'test_note' => null,
                'client_id_hint' => 'Also called application ID or consumer key.',
                'client_secret_hint' => 'Also called application secret or client password.',
            ],
        ];
    }

    /**
     * The issuer URL for a preset, or null when the user has to type it.
     */
    public static function issuerFor(string $preset, string $tenant = ''): ?string
    {
        $issuer = self::all()[$preset]['issuer'] ?? null;

        if ($issuer === null) {
            return null;
        }

        $tenant = rtrim((string) preg_replace('#^https?://#i', '', trim($tenant)), '/');

        return str_replace('{tenant}', rawurlencode($tenant), $issuer);
    }

    /**
     * Replace the redirect URI placeholders in a step or snippet. Without a
     * test callback (terminal setup), only the public one is mentioned.
     */
    public static function fill(string $text, string $publicCallback, ?string $testCallback = null): string
    {
        if ($testCallback === null) {
            $text = implode("\n", array_filter(
                explode("\n", $text),
                fn (string $line) => ! str_contains($line, '{test_callback}'),
            ));
        }

        return strtr($text, [
            '{redirect_uris}' => $testCallback === null ? 'the redirect URI' : 'both redirect URIs',
            '{public_callback}' => $publicCallback,
            '{test_callback}' => (string) $testCallback,
        ]);
    }
}
