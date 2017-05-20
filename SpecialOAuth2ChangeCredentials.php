<?php

/**
 * Override the default special page Special:ChangeCredentials
 */
class SpecialOAuth2ChangeCredentials extends SpecialChangeCredentials {

        public function execute( $subPage ) {
                global $wgOAuth2Client;

                $user = $this->getUser();

                if(OAuth2Helper::isExternalUser($user)) {
                        $this->setHeaders();
                        $this->outputHeader();

                        $url = $wgOAuth2Client['config']['change_endpoint'];
                        $out = $this->getOutput();
                        $out->addHTML('You are an external user, please click <a href="' . $url . '" target="_blank">here</a> to change your password.');
                } else {
                        parent::execute($subPage);
                }
        }
}