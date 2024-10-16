<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SabaNovin implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'sabanovin';
    }

    public static function name() {
        return 'sabanovin.com';
    }

    public function send() {

        $api_key = $this->username;
        $from    = $this->senderNumber;
        $to      = $this->mobile;
        $massage = $this->message;

        if ( empty( $api_key ) ) {
            return false;
        }

        $data = [
            'gateway' => $from,
            'to'      => implode( ",", $to ),
            'text'    => urlencode( $massage ),
        ];

        $remote = wp_remote_get( "https://api.sabanovin.com/v1/{$api_key}/sms/send.json?" . http_build_query( $data ) );

        $response = json_decode( wp_remote_retrieve_body( $remote ) );

        if ( ! empty( $response->status->code ) && $response->status->code == 200 ) {
            return true; // Success
        } elseif ( ! empty( $response->status->message ) ) {
            $response = $response->status->code . ":" . $response->status->message;
        }

        return $response;
    }
}
