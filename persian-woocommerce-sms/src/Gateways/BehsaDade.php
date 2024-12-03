<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class BehsaDade implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'behsadade';
    }

    public static function name() {
        return 'sms.behsadade.com';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $to       = $this->mobile;
        $massage  = $this->message;


        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to       = implode( ",", $to );
        $massage  = urlencode( $massage );
        $username = urlencode( $username );
        $password = urlencode( $password );
        $from     = urlencode( $from );

        try {

            $data = [
                'login_username'  => $username,
                'login_password'  => $password,
                'receiver_number' => $to,
                'note_arr'        => $massage,
                'sender_number'   => $from,
            ];

            $remote = wp_remote_get( 'http://sms.behsadade.com/webservice/rest/sms_send?' . http_build_query( $data ) );

            $response = json_decode( wp_remote_retrieve_body( $remote ) );

            if ( isset( $results->error ) ) {
                $response = $results->error;
            } elseif ( ! empty( $results->result ) && $results->result && ! empty( $results->list ) ) {
                return true; // Success
            }

            return $response;

        } catch ( Exception $ex ) {
            return $ex->getMessage();
        }
    }
}
