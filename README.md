MediaWiki OAuth2 Client Extension
========================================

OAuth2 extension for MediaWiki to integrate OAuth2 provider (Keycloak, Github, etc.) as an identity provider.

MediaWiki implementation of the [OAuth2 Client library](https://github.com/kasperrt/OAuth2-Client).

## Features

- Login or logout with OAuth2 provider.
- Force external user to login or change password through OAuth2 provider only.
- The external users will not replace the internal users, and the internal users can login/logout via the MediaWiki way.

## Install

First, clone this repository to `extensions` directory in the mediawiki's root directory:

```bash
git clone https://github.com/flytreeleft/MediaWiki-OAuth2-Client.git extensions/MediaWiki-OAuth2-Client
```

Then, load the plugin by putting the following line into your `LocalSettings.php`:

```php
wfLoadExtension( 'MediaWiki-OAuth2-Client' );
```

Finally, run [maintenance/update.php](https://www.mediawiki.org/wiki/Manual:Update.php) to create the extra tables:

```bash
php maintenance/update.php
```

## Configuration

Required settings in global `$wgOAuth2Client` in your `LocalSettings.php`(Example for Keycloak users):

```php
# The client id, e.g. mediawiki
$wgOAuth2Client['client']['id']             = 'mediawiki';
# First, change the 'Access Type' to 'confidential' in the client settings of Keycloak,
# then, switch to the 'Credentials' tab and copy the value of 'Secret'.
$wgOAuth2Client['client']['secret']         = 'xxx-xx-xxx-xx';

# Access the URL 'https://<keycloak server>/auth/realms/<realm name>/.well-known/openid-configuration' to get the endpoints.
# Authorization URL which is 'authorization_endpoint'
$wgOAuth2Client['config']['auth_endpoint']  = 'https://<keycloak server>/auth/realms/<realm name>/protocol/openid-connect/auth';
# Token URL which is 'token_endpoint'
$wgOAuth2Client['config']['token_endpoint'] = 'https://<keycloak server>/auth/realms/<realm name>/protocol/openid-connect/token';
# Logout URL which is 'end_session_endpoint'
$wgOAuth2Client['config']['logout_endpoint']  = 'https://<keycloak server>/auth/realms/<realm name>/protocol/openid-connect/logout';
# User info URL which is 'userinfo_endpoint'
$wgOAuth2Client['config']['info_endpoint']  = 'https://<keycloak server>/auth/realms/<realm name>/protocol/openid-connect/userinfo';
# The URL to change password
$wgOAuth2Client['config']['change_endpoint']  = 'https://<keycloak server>/auth/realms/<realm name>/account/password';
```

Optional settings in global `$wgOAuth2Client` in your `LocalSettings.php`:

```php
$wgOAuth2Client['config']['service_name'] = '<Server name>';
$wgOAuth2Client['config']['service_login_link_text'] = '<Login button text>';
```

**Note**: The callback URL to use when the OAuth2 provider needs to redirect or link back to the MediaWiki after login successfully would be: `http://your.wiki.domain/path/to/wiki/Special:OAuth2Client/callback`. Copy the URL to the 'Base URL' field in the client settings of Keycloak.

## Anonymous access denied wiki?

If your Wiki is completely closed for anonymous (login required for every page):

```php
# https://www.mediawiki.org/wiki/Manual:Preventing_access
# Disable reading by anonymous users
$wgGroupPermissions['*']['read'] = false;
# Disable anonymous editing
$wgGroupPermissions['*']['edit'] = false;
# Anonymous users can't create pages
$wgGroupPermissions['*']['createpage'] = false;
# Prevent new user registrations except by sysops
$wgGroupPermissions['*']['createaccount'] = false;
```

You need to add the plugin's hook to the whitelists:

```php
# Allow anonymous to access the login and auth page
# If you are using a content language other than English, you may need to use the translated special page names instead of their English names.
# e.g. `$wgWhitelistRead = array ("特殊:OAuth2Client")` for Chinese
# More detials in https://www.mediawiki.org/wiki/Manual:$wgWhitelistRead
$wgWhitelistRead = array ("Help:Contents", "Special:Userlogin", "Special:OAuth2Client");
```

## License

MIT

## Thanks

- [LosFuzzys/MediaWiki-OAuth2-Github](https://github.com/LosFuzzys/MediaWiki-OAuth2-Github)
- [kasperrt/OAuth2-Client](https://github.com/kasperrt/OAuth2-Client)

## References

- [MediaWiki Preventing Access](https://www.mediawiki.org/wiki/Manual:Preventing_access)
- [MediaWiki $wgWhitelistRead](https://www.mediawiki.org/wiki/Manual:$wgWhitelistRead)
- [MediaWiki $wgResourceModules](https://www.mediawiki.org/wiki/Manual:$wgResourceModules)
- [MediaWiki Developing Extensions ](https://www.mediawiki.org/wiki/Manual:Developing_extensions)
- [MediaWiki Developing with ResourceLoader](https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader)
