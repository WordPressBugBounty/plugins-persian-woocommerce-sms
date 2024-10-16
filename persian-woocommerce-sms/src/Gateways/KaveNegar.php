<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class KaveNegar implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'kavenegar';
    }

    public static function name() {
        return 'kavenegar.com';
    }

    public function send() {
        $username = $this->username;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) ) {
            return false;
        }

        $messages = urlencode( $massage );
        $to       = implode( ',', $this->mobile );

        $url = "https://api.kavenegar.com/v1/$username/sms/send.json?sender=$from&receptor=$to&message=$messages";

        $remote = wp_remote_get( $url );

        $response = wp_remote_retrieve_body( $remote );

        if ( false !== $response ) {
            $json_response = json_decode( $response );
            if ( ! empty( $json_response->return->status ) && $json_response->return->status == 200 ) {
                return true; // Success
            }
        }

        return $response;
    }
}
