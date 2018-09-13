<?php
if ( !defined( 'MEDIAWIKI' )) {
        die('This is a MediaWiki extension, and must be run from within MediaWiki.');
}

//require_once('OAuth2-Client/OAuth2Client.php');

class SpecialOAuth2Client extends SpecialPage {

        private $client;
        private $table_user = 'oauth2_client_users';
        private $table_state = 'oauth2_client_states';
        private $db_read_index;

        public function __construct() {
                if( !self::OAuthEnabled() ) return;

                parent::__construct('OAuth2Client');
                global $wgOAuth2Client, $wgServer, $wgArticlePath;

                // https://www.mediawiki.org/wiki/Manual:Database_access#Database_Abstraction_Layer
                // https://doc.wikimedia.org/mediawiki-core/master/php/GlobalFunctions_8php.html#aaee9057ac6cf79cb4bf9ad71c5ecc909
                // Note: DB_SLAVE is already deprecated since 1.28 and replaced by DB_REPLICA:
                // https://doc.wikimedia.org/mediawiki-core/master/php/Defines_8php.html#af9a09ab91a9583e960a610723f5f4330
                if ( defined( 'DB_REPLICA' )) {
                        $this->db_read_index = DB_REPLICA;
                } else {
                        $this->db_read_index = DB_SLAVE;
                }
                $this->client = new OAuth2([
                        'client_id'              => $wgOAuth2Client['client']['id'],
                        'client_secret'          => $wgOAuth2Client['client']['secret'],
                        'redirect_uri'           => $wgServer . str_replace( '$1', $wgOAuth2Client['config']['redirect_uri'], $wgArticlePath),
                        'auth'                   => $wgOAuth2Client['config']['auth_endpoint'],
                        'token'                  => $wgOAuth2Client['config']['token_endpoint'],
                        'logout'                 => $wgOAuth2Client['config']['logout_endpoint'],
                        'authorization_type'     => $wgOAuth2Client['config']['auth_type'],
                        'scope'                  => $wgOAuth2Client['config']['scope']
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
                        case 'logout':
                                $this->_logout();
                                break;
                        default:
                                $this->_default();
                                break;
                }
        }

        private function _logout() {
                global $wgOAuth2Client, $wgOut, $wgUser, $wgRequest;

                /*if( $wgUser->isLoggedIn() ) { $wgUser->logout(); }*/
                $returnto = $wgRequest->getVal('returnto');
                if($returnto) {
                        $title = Title::newFromText($returnto);
                } else {
                        $title = Title::newMainPage();
                }

                $this->client->logout($title->getFullUrl());

        }

        private function _redirect() {
                global $wgRequest;

                $state = MWCryptRand::generateHex( 32 );//uniqid('', true);
                $url   = $wgRequest->getVal('returnto');

                $dbw   = wfGetDB(DB_MASTER);
                $dbw->begin();
                $res = $dbw->insert( $this->table_state,
                        array( 'state' => $state,
                                   'return_to' => $url ) );
                $dbw->commit();

                $this->client->redirect($state);
        }

        private function _callback() {
                global $wgOAuth2Client, $wgOut, $wgRequest;

                $dbr = wfGetDB($this->db_read_index);
                $row = $dbr->selectRow(
                        $this->table_state,
                        '*',
                        array('state' => $wgRequest->getVal('state')));

                $row = json_decode(json_encode($row), true); // Parse the row to JSON object
                if(!$row) {
                        //$this->_redirect();
                        throw new ErrorPageError('Auth Error', 'States differ');
                }

                $dbw = wfGetDB(DB_MASTER);
                $dbw->begin();
                $dbw->delete($this->table_state,
                                array('state' => $wgRequest->getVal('state')));
                $dbw->commit();

                $access_token = $this->client->get_access_token();
                if( !$access_token ) {
                        throw new ErrorPageError('Auth Error', 'Something went wrong fetching the access token');
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
                if( !$user ) {
                        $login_url = Skin::makeSpecialUrl( 'UserLogin', 'returnto='.$row['return_to'] );
                        $wgOut->setPageTitle('Auth Error');
                        $wgOut->addHTML('You are an internal user. Please <a href="' . $login_url . '">relogin</a> through login form.');
                        return false;
                }

                $user->setCookies(null, null, true);

                //$this->add_user_to_groups($user, $2);
                $this->setSessionDataForUser($user);

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
                wfErrorLog( json_encode($response), '/var/log/mediawiki/my-custom-debug.log' );

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
                global $wgOAuth2Client;

                $username       = $credentials['username'];
                $realname       = $credentials['realname'];
                $id             = $credentials['id'];
                $email          = $credentials['email'];
                $external_id     = $id;
                $dbr            = wfGetDB($this->db_read_index);
                $row            = $dbr->selectRow(
                        $this->table_user,
                        '*',
                        array('external_id' => $external_id)
                );

                // https://doc.wikimedia.org/mediawiki-core/master/php/classUser.html
                if($row) { // OAuth user already exists
                        return User::newFromId($row->internal_id);
                }

                $user = User::newFromName($username, 'creatable');
                if( false === $user) {
                        throw new MWException('Unable to create user: ' . $username);
                } else if($user->getId() != 0) {
                        return false;
                }

                if($realname) {
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
                $dbw->begin();
                $dbw->replace(
                        $this->table_user,
                        array('internal_id', 'external_id'),
                        array('internal_id' => $user->getId(),
                                  'external_id' => $external_id),
                        __METHOD__);
                $dbw->commit();

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

        // https://doc.wikimedia.org/mediawiki-core/master/php/AuthManager_8php_source.html#l02370
        // Update authenticate timestamp to make sure AuthManager#securitySensitiveOperationStatus return AuthManager::SEC_OK
        // after the user was authorized by OAuth2 provider.
        private function setSessionDataForUser( $user, $remember = null ) {
                global $wgRequest;

                $session = $wgRequest->getSession();
                $delay = $session->delaySave();

                $session->resetId();
                $session->resetAllTokens();
                if ( $session->canSetUser() ) {
                        $session->setUser( $user );
                }
                if ( $remember !== null ) {
                        $session->setRememberUser( $remember );
                }
                $session->set( 'AuthManager:lastAuthId', $user->getId() );
                $session->set( 'AuthManager:lastAuthTimestamp', time() );
                $session->persist();

                \Wikimedia\ScopedCallback::consume( $delay );
        }
}
