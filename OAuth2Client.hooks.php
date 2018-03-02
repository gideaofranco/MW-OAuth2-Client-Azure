<?php
class OAuth2ClientHooks {

        public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
                // https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
                // https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader
                // https://www.mediawiki.org/wiki/Manual:Developing_extensions#Making_your_extension_user_configurable
                // https://www.mediawiki.org/wiki/Manual:$wgResourceModules#Examples
                // https://doc.wikimedia.org/mediawiki-core/master/php/classOutputPage.html#a3de49a57fa4ab4d408afa0bf7d485dfb
                $out->addModules( [ 'ext.MwOAuth2Client' ] ); // Load scripts and styles

                return true;
        }

        public static function onUserLoginForm( &$tpl ) {
                global $wgRequest, $wgOAuth2Client;

                $btn_text = $wgOAuth2Client['config']['service_login_link_text'];
                $btn_link = Skin::makeSpecialUrlSubpage( 'OAuth2Client', 'redirect', 'returnto='.$wgRequest->getVal('returnto') );

                $header = $tpl->get( 'header' );
                $header .= '<a class="mw-htmlform-submit mw-ui-button mw-ui-primary dataporten-button" href="' . $btn_link . '">' . $btn_text . '</a>';
                $tpl->set( 'header', $header );
        }

        public static function onUserLogout( &$user ) {
                global $wgOut, $wgRequest;

                $logout_url = Skin::makeSpecialUrlSubpage( 'OAuth2Client', 'logout', 'returnto='.$wgRequest->getVal('returnto') );
                $wgOut->redirect($logout_url);

                return true;
        }

        // Change reset password link address to OAuth2 provider's
        public static function onGetPreferences( $user, &$defaultPreferences ) {
                global $wgOAuth2Client;

                if(OAuth2Helper::isExternalUser($user) && $defaultPreferences['password']) {
                        $url = $wgOAuth2Client['config']['change_endpoint'];
                        // https://github.com/wikimedia/mediawiki-extensions-GlobalPreferences/blob/master/GlobalPreferences.hooks.php
                        $link = '<a href="' . $url . '" target="_blank">' . wfMessage( 'prefs-resetpass' )->escaped() . '</a>';
                        $defaultPreferences['password'] = [
                                'type' => 'info',
                                'raw' => true,
                                'default' => $link,
                                'label-message' => 'yourpassword',
                                'section' => 'personal/info',
                        ];
                }
        }

        public static function getOAuth2VendorClassPath() {
                return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'oauth_2';
        }

        public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
                $updater->addExtensionTable( 'oauth2_client_states',
                        __DIR__ . '/sql/state.sql' );
                $updater->addExtensionTable( 'oauth2_client_users',
                        __DIR__ . '/sql/users.sql' );
                $updater->doUpdates();
                return true;
        }
}
