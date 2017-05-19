<?php
class OAuth2ClientHooks {

        public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
                $script = '<link rel="stylesheet" type="text/css" href="/wiki/extensions/OAuth2Client/modules/OAuth2Client.css">';
                $out->addHeadItem("jsonTree script", $script);

                return true;
        }

        public static function onUserLoginForm( &$tpl ) {
                global $wgRequest, $wgOAuth2Client;

                if(isset($wgOAuth2Client['config']['service_login_link_text'])) {
                        $btn_text = $wgOAuth2Client['config']['service_login_link_text'];
                } else {
                        $btn_text = 'Login with OAuth2';
                }
                $btn_link = Skin::makeSpecialUrlSubpage( 'OAuth2Client', 'redirect', 'returnto='.$wgRequest->getVal('returnto') );

                $header = $tpl->get( 'header' );
                $header .= '<a class="mw-ui-button dataporten-button" href="' . $btn_link . '">' . $btn_text . '</a>';
                $tpl->set( 'header', $header );
        }

        public static function onUserLogout( &$user ) {
                global $wgOut, $wgRequest;

                $logout_url = Skin::makeSpecialUrlSubpage( 'OAuth2Client', 'logout', 'returnto='.$wgRequest->getVal('returnto') );
                $wgOut->redirect($logout_url);

                return true;
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
