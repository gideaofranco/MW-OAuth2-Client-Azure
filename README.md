MediaWiki-OAuth2-Client
==========================

**MediaWiki OAuth2 Client Extension**

OAuth2 extension for MediaWiki to integrate OAuth2 server (Keycloak, Github, etc.) as an identity provider.

MediaWiki implementation of the [OAuth2 Client library](https://github.com/kasperrt/OAuth2-Client).

### Install

First, clone this repository to `extensions` directory in the mediawiki's root directory:

```bash
$ cd extensions
$ git clone https://github.com/flytreeleft/MediaWiki-OAuth2-Client.git
```

Then, load the plugin by putting the following line into your `LocalSettings.php`:

```php
wfLoadExtension( 'MediaWiki-OAuth2-Client' );
```

Finally, run [update.php](https://www.mediawiki.org/wiki/Manual:Update.php) to create the extra tables:

```bash
$ cd maintenance
$ php update.php
```

### Configuration

Required settings in global $wgOAuth2Client (in your `LocalSettings.php`):

```php
$wgOAuth2Client['client']['id']             = '';
$wgOAuth2Client['client']['secret']         = '';
$wgOAuth2Client['config']['auth_endpoint']  = ''; // Authorization URL
$wgOAuth2Client['config']['token_endpoint'] = ''; // Token URL
$wgOAuth2Client['config']['info_endpoint']  = ''; // URL to fetch user JSON
```

Optional settings in global $wgOAuth2Client (in your `LocalSettings.php`)

```php
$wgOAuth2Client['config']['service_name'] = '<Server name>';
$wgOAuth2Client['config']['service_login_link_text'] = '<Login button text>';
```

The callback url back to your wiki would be:

    http://your.wiki.domain/path/to/wiki/Special:OAuth2Client/callback

### Closed wiki?

If your Wiki is completely closed (login required for every page), you need to whitelist the plugin's hook:

```php
// whitelist oauth hooks for non-authed users:
$wgWhitelistRead = array('Special:OAuth2Client');
```


### License

MIT

