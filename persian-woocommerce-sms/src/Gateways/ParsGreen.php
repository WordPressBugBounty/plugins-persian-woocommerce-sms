<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class ParsGreen implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'parsgreen';
    }

    public static function name() {
        return 'parsgreen.com';
    }

    public function send() {
        $username = $this->username;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) ) {
            return false;
        }

        $to = $this->mobile;


        $body = [
            'SmsBody' => $massage,
            'Mobiles' => $to,
        ];


        $args = [
            'body'        => json_encode( $body ),
            'timeout'     => '45',
            'headers'     => [
                "Content-Type"  => "application/json; charset=utf-8",
                "Accept"        => "application/json",
                "Authorization" => "basic apikey:" . $username,
            ],
            'data_format' => 'body',
        ];

        try {

            $remote = wp_remote_post( 'http://sms.parsgreen.ir/Apiv2/Message/SendSms', $args );

            $response = json_decode( wp_remote_retrieve_body( $remote ) );


        } catch ( Exception $ex ) {
            return $response = "error";
        }

        if ( $response->R_Success ) {
            return $response = true;
        } else {
            $response = $response->R_Message;
        }

        return $response;
    }
}
