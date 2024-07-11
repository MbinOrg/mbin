# SSO (Single Sign On) Providers

SSOs are used to simplify the registration flow. You authorize the server to use an existing account from one 
of the available SSO providers.

Mbin supports a multitude of SSO providers:
- Google
- Facebook
- GitHub
- Keycloak
- Zitadel
- SimpleLogin
- Discord
- Authentik
- Privacy Portal
- Azure

To enable an SSO provider you (usually) have to create a developer account on the specific platform, create an app
and provide the app/client ID and a secret. These have to be entered in the correct environment variable 
in the `.env`|`.env.local` file 

### Google
https://developers.google.com/
```dotenv
OAUTH_GOOGLE_ID=AS2easdioh912 # your client ID
OAUTH_GOOGLE_SECRET=sdfpsajh329ura39ßseaoßjf30u # your client secret
```

### Facebook
https://developers.facebook.com
```dotenv
OAUTH_FACEBOOK_ID=AS2easdioh912 # your client ID
OAUTH_FACEBOOK_SECRET=sdfpsajh329ura39ßseaoßjf30u # your client secret
```

### GitHub

You need a GitHub account, if you do no have one, yet, go and create one: https://github.com/signup

1. Go to https://github.com/settings/apps
2. Click on "New GitHub App"
3. Enter the app name, description and Homepage URL (just your instance URL)
4. Click the checkbox "Request user authorization (OAuth) during installation"
5. Insert `https://YOURINSTANCE/oauth/github/verify` as the "Callback URL" (replace `YOURINSTANCE` with the URL of your instance)
6. Uncheck the checkbox `Active` under the "Webhook" section
7. Scroll down and click "Create GitHub App"
8. Now you have the chance to upload an icon (at the bottom of the page)
9. Click "Generate a new client secret"
10. Insert the "Client ID" and the generated client secret into the `.env` file:
```dotenv
OAUTH_GITHUB_ID=AS2easdioh912 # your client ID
OAUTH_GITHUB_SECRET=sdfpsajh329ura39ßseaoßjf30u # your client secret
```

### Keycloak
Self-hosted, https://www.keycloak.org/
```dotenv
OAUTH_KEYCLOAK_ID=AS2easdioh912 # your client ID
OAUTH_KEYCLOAK_SECRET=sdfpsajh329ura39ßseaoßjf30u # your client secret
OAUTH_KEYCLOAK_URI=
OAUTH_KEYCLOAK_REALM=
OAUTH_KEYCLOAK_VERSION=
```

### Zitadel
Self-hosted, https://zitadel.com/
```dotenv
OAUTH_ZITADEL_ID=AS2easdioh912 # your client ID
OAUTH_ZITADEL_SECRET=sdfpsajh329ura39ßseaoßjf30u # your client secret
OAUTH_ZITADEL_BASE_URL=
```

### SimpleLogin

You need a SimpleLogin account, if you do not have one, yet, go and create one: https://app.simplelogin.io/auth/register

1. Go to https://app.simplelogin.io/developer and click on "New website"
2. Enter the name of your instance and the url to your instance
3. Choose an icon (if you want to)
4. Click on "OAuth Settings" on the right
5. Insert the client ID ("AppID / OAuth2 Client ID") and the client secret ("AppSecret / OAuth2 Client Secret")
in your `.env` file
```dotenv
OAUTH_SIMPLELOGIN_ID=gehirneimer.de-vycjfiaznc # your client ID
OAUTH_SIMPLELOGIN_SECRET=fdiuasdfusdfsdfpsdagofweopf # your client secret
```
6. Back in the browser, scroll down to "Authorized Redirect URIs" and click on "Add new uri" 

### Discord

You need a Discord account, if you do not have one, yet, go and create one: https://discord.com/register

1. Go to https://discord.com/developers/applications and create a new application. If you want, add an image and a description.
2. Click the "OAuth2" tab on the left
3. Under "Client information" click "Reset Secret"
4. The newly generated secret and the "Client ID" need to go in our `.env` file:
```dotenv
OAUTH_DISCORD_ID=3245498543 # your client ID
OAUTH_DISCORD_SECRET=xJHGApsadOPUIAsdoih # your client secret
```
5. Back in the browser: click on "Add Redirect"
6. enter the URL: `https://YOURINSTANCE/oauth/discord/verify`, replace `YOURINSTANCE` with your instance domain
7. If you are on docker, restart the containers, on bare metal execute the `post-upgrade` script
8. When you go to the login page you should see a button to "Continue with Discord"

### Authentik
Self-hosted, https://goauthentik.io/
```dotenv
OAUTH_AUTHENTIK_ID=3245498543 # your client ID
OAUTH_AUTHENTIK_SECRET=xJHGApsadOPUIAsdoih # your client secret
OAUTH_AUTHENTIK_BASE_URL=
```

### Privacy Portal
https://privacyportal.org
```dotenv
OAUTH_PRIVACYPORTAL_ID=3245498543 # your client ID
OAUTH_PRIVACYPORTAL_SECRET=xJHGApsadOPUIAsdoih # your client secret
```

### Azure
https://login.microsoftonline.com
```dotenv
OAUTH_AZURE_ID=3245498543 # your client ID
OAUTH_AZURE_SECRET=xJHGApsadOPUIAsdoih # your client secret
OAUTH_AZURE_TENANT=
```
