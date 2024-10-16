<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SignalAds implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'signalads';
    }

    public static function name() {
        return 'panel.signalads.com';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );

        $url = "https://panel.signalads.com/webservice/url/send.php?method=sendsms?from=$from&to=$to&text=$massage&username=$username&password=$password&type=0&format=json";

        $remote = wp_remote_get( $url );

        $response = wp_remote_retrieve_body( $remote );

        if ( intval( $response ) > 1 ) {
            return true; // Success
        } else {
            return $response;
        }

        return $response;
    }
}
