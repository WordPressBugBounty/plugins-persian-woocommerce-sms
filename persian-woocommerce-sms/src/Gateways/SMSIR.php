<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SMSIR implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'smsir';
    }

    public static function name() {
        return 'SMS.ir';
    }

    public function send() {

        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );

        $content = 'user=' . rawurlencode( $username ) .
                   '&pass=' . rawurlencode( $password ) .
                   '&to=' . rawurlencode( $to ) .
                   '&lineNo=' . rawurlencode( $from ) .
                   '&text=' . $massage;

        $remote = wp_remote_get( 'https://ip.sms.ir/SendMessage.ashx?' . $content );

        $response = wp_remote_retrieve_body( $remote );

        if ( strtolower( $response ) == 'ok' || stripos( $response, 'ارسال با موفقیت انجام شد' ) !== false ) {
            return true; // Success
        }

        return $response;
    }

}