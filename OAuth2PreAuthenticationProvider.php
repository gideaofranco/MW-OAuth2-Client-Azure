<?php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;

// https://github.com/wikimedia/mediawiki-extensions-MinimumNameLength/blob/master/includes/auth/MinimumNameLengthPreAuthenticationProvider.php
class OAuth2PreAuthenticationProvider extends AbstractPreAuthenticationProvider {

        // Forbidden external user to login by internal way.
        public function testForAuthentication( array $reqs ) {
                global $wgOAuth2Client;

                $ret = StatusValue::newGood();
                // https://doc.wikimedia.org/mediawiki-core/master/php/classMediaWiki_1_1Auth_1_1AuthenticationRequest.html#ad73ea062805459f33275c66b24099167
                $username = AuthenticationRequest::getUsernameFromRequests( $reqs );
                $user = User::newFromName( $username );

                if(OAuth2Helper::isExternalUser($user)) {
                        $btn_text = $wgOAuth2Client['config']['service_login_link_text'];
                        $ret->fatal('You are an external user, please click button "' . $btn_text . '" to login.');
                }
                return $ret;
        }
}
