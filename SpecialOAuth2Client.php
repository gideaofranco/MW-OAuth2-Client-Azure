<?php
if ( !defined( 'MEDIAWIKI' )) {
        die('This is a MediaWiki extension, and must be run from within MediaWiki.');
}

//require_once('OAuth2-Client/OAuth2Client.php');

class SpecialOAuth2Client extends SpecialPage {

        private $client;
        private $table = 'oauth2_client_users';

        public function __construct() {
                if( !self::OAuthEnabled() ) return;

                parent::__construct('OAuth2Client');
                global $wgOAuth2Client, $wgServer, $wgArticlePath;

                $this->client = new OAuth2([
                        'client_id'              => $wgOAuth2Client['client']['id'],
                        'client_secret'          => $wgOAuth2Client['client']['secret'],
                        'redirect_uri'           => $wgServer . str_replace( '$1', 'Special:OAuth2Client/callback', $wgArticlePath),
                        'auth'                   => $wgOAuth2Client['config']['auth_endpoint'],
                        'token'                  => $wgOAuth2Client['config']['token_endpoint'],
                        'authorization_type'     => $wgOAuth2Client['config']['auth_type'],
                        'scope'                  => ''
                ]);
        }

        public function execute( $parameter ) {
                $this->setHeaders();
                switch($parameter) {
                        case 'redirect':
                                $this->_redirect();
                                break;
                        case 'callback':
                                $this->_callback();
                                break;
                        default:
                                $this->_default();
                                break;
                }
        }

        private function _logout() {
                global $wgOAuth2Client, $wgOut, $wgUser;
                if( $wgUser->isLoggedIn() ) $wgUser->logout();

        }

        private function _redirect() {
                global $wgRequest;

                $state = uniqid('', true);
                $url   = $wgRequest->getVal('returnto');

                $dbw   = wfGetDB(DB_MASTER);
                $dbw->insert( 'github_states',
                        array( 'state' => $state,
                                   'return_to' => $url ),
                                   'Database::insert' );
                $dbw->begin();
                $this->client->redirect($state);
        }

        private function _callback() {
                global $wgOAuth2Client, $wgOut, $wgRequest;

                $dbr = wfGetDB(DB_SLAVE);
                $row = $dbr->selectRow(
                        'github_states',
                        '*',
                        array('state' => $wgRequest->getVal('state')));

                $row = json_decode(json_encode($row),true);
                if(!$row) {
                        //throw new MWException('States differ');
                        $this->_redirect();
                }

                $dbw = wfGetDB(DB_MASTER);
                $dbw->delete('oauth2_client_states',
                                         array('state' => $wgRequest->getVal('state')));
                $dbw->begin();

                $access_token = $this->client->get_access_token();
                if( !$access_token ) {
                        throw new MWException('Something went wrong fetching the access token');
                }

                $credentials = $this->fix_return($this->client->get_identity($access_token, $wgOAuth2Client['config']['info_endpoint']));

                // https://api.github.com/users/$name/orgs
                //$orgsEndpoint = 'https://api.github.com/users/' .$credentials['id'] . '/orgs';
                /*$orgsEndpoint = 'https://api.github.com/user/orgs';
                $orgs = $this->client->get_identity($access_token, $orgsEndpoint); // $wgOAuth2Client['config']['group_endpoint']);


                if(isset($wgOAuth2Client['config']['required_org']) && $wgOAuth2Client['config']['required_org'] != NULL) {
                        if(!$this->checkGroupmembership($orgs, $wgOAuth2Client['config']['required_org'])) {
                                $error = ('You a not part of the ' . $wgOAuth2Client['config']['required_org'] . ' organization on Client!');

                                global $wgOut;
                                $wgOut->setPageTitle('Auth Error');
                                $wgOut->addHTML('<strong>' . $error . '</strong>');

                                return false;
                        }
                }*/


                $user = $this->userHandling($credentials);
                $user->setCookies(null, null, true);

                //$this->add_user_to_groups($user, $2);

                if($row['return_to']) {
                        $title = Title::newFromText($row['return_to']);
                } else {
                        $title = Title::newMainPage();
                }

                $wgOut->redirect($title->getFullUrl());

                return true;
        }

        private function checkGroupmembership($orgs, $requiredOrg) {
                foreach($orgs as $org) {
                        if($org['login'] === $requiredOrg) return true;
                }

                return false;
        }

        private function add_user_to_groups($user, $groups) {
                foreach($groups as $key => $value) {
                        $user->addGroup($groups[$key]['id']);
                }
        }

        private function fix_return($response) {
                global $wgOAuth2Client;

                if(isset($response['username'])) {
                        $username = $response['username'];
                } else if(isset($response['preferred_username'])) { // For Keycloak
                        $username = $response['preferred_username'];
                } else if(isset($response['user']['name'])) {
                        $username = $response['user']['name'];
                } else {
                        $username = null;
                }

                // NOTE: 'name' is used as full name by Keycloak
                if(isset($response['name'])) {
                        if ($username == null) {
                                $username = $response['name'];
                        } else {
                                $realname = $response['name'];
                        }
                } else {
                        $realname = null;
                }

                if(isset($response['id'])) {
                        $id = $response['id'];
                } else if(isset($response['userid'])) {
                        $id = $response['userid'];
                } else if(isset($response['user_id'])) {
                        $id = $response['user_id'];
                } else if(isset($response['sub'])) { // For Keycloak
                        $id = $response['sub'];
                } else if(isset($response['user']['userid'])) {
                        $id = $response['user']['userid'];
                } else if(isset($response['user']['user_id'])) {
                        $id = $response['user']['user_id'];
                }

                if(isset($response['email'])) {
                        $email = $response['email'];
                } else if(isset($response['user']['email'])) {
                        $email = $response['user']['email'];
                } else {
                        $email = null;
                }

                //wfDebugLog('MediaWiki-OAuth2-Client', json_encode($response));
                $oauth_identity = array(
                        'id'           => $id,
                        'email'        => $email,
                        'username'     => $username,
                        'realname'     => $realname
                );

                return $oauth_identity;
        }

        private function _default() {
                global $wgOAuth2Client, $wgOut, $wgUser, $wgExtensionAssetsPath;

                return true;
        }

        private function userHandling($credentials) {
                global $wgOAuth2Client, $wgAuth;

                $username       = $credentials['username'];
                $realname       = $credentials['realname'];
                $id             = $credentials['id'];
                $email          = $credentials['email'];
                $externalId     = $id;
                $dbr            = wfGetDB(DB_SLAVE);
                $row            = $dbr->selectRow(
                        $this->table,
                        '*',
                        array('external_id' => $externalId)
                );

                // https://doc.wikimedia.org/mediawiki-core/master/php/classUser.html
                if($row) { // OAuth user already exists
                        return User::newFromId($row->internal_id);
                }
                $user = User::newFromName($username, 'creatable');
                if( false === $user || $user->getId() != 0) {
                        throw new MWException('Unable to create user.');
                }
                if ($realname) {
                        $user->setRealName($realname);
                }
                /*if ( $wgAuth->allowPasswordChange() ) {
                        $user->setPassword(PasswordFactory::generateRandomPasswordString(128));
                }*/
                if($email) {
                        $user->setEmail($email);
                        $user->setEmailAuthenticationTimestamp(time());
                }
                $user->addToDatabase();
                $dbw = wfGetDB(DB_MASTER);
                $dbw->replace(
                        $this->table,
                        array('internal_id', 'external_id'),
                        array('internal_id' => $user->getId(),
                                  'external_id' => $externalId),
                        __METHOD__);
                return $user;
        }

        public static function OAuthEnabled() {
                global $wgOAuth2Client;
                return isset(
                        $wgOAuth2Client['client']['id'],
                        $wgOAuth2Client['client']['secret'],
                        $wgOAuth2Client['config']['auth_endpoint'],
                        $wgOAuth2Client['config']['token_endpoint']
                );
        }

}
