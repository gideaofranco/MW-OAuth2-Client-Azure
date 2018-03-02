<?php

class OAuth2Helper {

        public static function isExternalUser( $user ) {
                $internal_id = $user->getId();

                if($internal_id <= 0) {
                        $internal_id = User::idFromName( $user->getName() );
                }

                if ( defined( 'DB_REPLICA' )) {
                        $dbr = wfGetDB(DB_REPLICA);
                } else {
                        $dbr = wfGetDB(DB_SLAVE);
                }
                $row = $dbr->selectRow(
                        'oauth2_client_users',
                        '*',
                        array('internal_id' => $internal_id)
                );

                return $internal_id && $row;
        }
}
