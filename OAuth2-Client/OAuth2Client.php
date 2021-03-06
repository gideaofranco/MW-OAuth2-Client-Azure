<?php

class OAuth2 {

        private $client_id;
        private $client_secret;
        private $redirect_uri;
        private $scope;
        private $access_token;
        private $url;
        private $URL_AUTH;
        private $URL_TOKEN;
        private $URL_USER;
        private $URL_GROUP;
        private $auth_type;
        private $session;
        private $grant_type;
        private $response_type;

        public function __construct($params){

                /* REQUIRED */
                $this->client_id           = $params["client_id"];
                $this->client_secret       = $params["client_secret"];
                $this->redirect_uri        = $params["redirect_uri"];
                $this->URL_AUTH            = $params["auth"] . "?";
                $this->URL_TOKEN           = $params["token"] . "?";
                $this->URL_LOGOUT          = $params["logout"] . "?";

                /* OPTIONAL */
                $this->auth_type           = isset($params["authorization_type"]) ? $params["authorization_type"] : "Bearer";
                $this->session             = isset($params["session"]) ? $params["session"] : false;
                $this->verify_ssl_peer     = isset($params["verify"]) ? ($params["verify"] ? 1 : 0) : 1;
                $this->verify_ssl_host     = $this->verify_ssl_peer === 1 ? 2 : 0;
                $this->grant_type          = isset($params["grant_type"]) ? $params["grant_type"] : "authorization_code";
                $this->response_type       = isset($params["response_type"]) ? $params["response_type"] : "code";
                $this->scope               = isset($params["scope"]) ? $params["scope"] : "";
        }

        public function get_access_token($state = false) {
                if($this->session && $state) {
                        if($_SESSION['state'] != $state) {
                                die('States does not match');
                        }
                }

                $access_token = $this->get_oauth_token();
                return $access_token;
        }

        private function get_oauth_token() {
                $code   = htmlspecialchars($_GET['code']);
                $params = array(
                        'grant_type'    => $this->grant_type,
                        'client_id'     => $this->client_id,
                        'client_secret' => $this->client_secret,
                        'code'          => $code,
                        'redirect_uri'  => $this->redirect_uri,
                );

                $url_params   = http_build_query($params);
                $url          = $this->URL_TOKEN;// . $url_params;
                // NOTE: Passing url encoded params as post fields. If passing array params, the keycloak cannot accept them.
                $result       = curl_exec($this->create_curl($url, array('Accept: application/json'), $url_params));
                wfErrorLog( $result, '/var/log/mediawiki/my-custom-debug.log' );
                $result_obj   = json_decode($result, true);
                $access_token = $result_obj['access_token'];
                //$expires_in   = $result_obj['expires_in'];
                //$expires_at   = time() + $expires_in;

                return $access_token;
        }

        public function get_identity($access_token, $identity_url) {
                $params = array(
                        'access_token' => $access_token,
                );
                $url_params = http_build_query($params);
                $url        = $identity_url;// . "?" . $url_params;
                $header     = array('Authorization: ' . $this->auth_type . ' ' . $access_token, 'Accept: application/json');
                $result     = curl_exec($this->create_curl($url, $header, false));
                $result_obj = json_decode($result, true);

                return $result_obj;
        }

        public function logout($redirect_uri) {
                $params = array(
                        'redirect_uri' => $redirect_uri,
                );
                $url = $this->URL_LOGOUT . http_build_query($params);

                header("Location: $url");
                return true;
        }

        public function redirect($state = false) {
                if(!$state) $state = uniqid('', true);
                $params = array(
                        'client_id'     => $this->client_id,
                        'response_type' => $this->response_type,
                        'redirect_uri'  => $this->redirect_uri,
                        'scope'         => $this->scope,
                        'state'         => $state
                );

                if($this->session) $_SESSION['state'] = $state;

                $url = $this->URL_AUTH . http_build_query($params);

                header("Location: $url");
                return true;
        }


        private function create_curl($url, $header, $extended) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_USERAGENT, 'MW Oauth2 Client');
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                if ($header){
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                }
                if ($extended) {
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $extended);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl_peer);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl_host);
                }
                return $curl;
        }
}

?>
