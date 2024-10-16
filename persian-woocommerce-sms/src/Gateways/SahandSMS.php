<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SahandSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'sahandsms';
    }

    public static function name() {
        return 'sahandsms.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $to       = $this->mobile;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( '-', $to );
        $to = str_ireplace( '+98', '0', $to );

        $url = 'http://webservice.sahandsms.com/NewSMSWebService.asmx/SendFromUrl?username=' . $username . '&password=' . $password . '&fromNumber=' . $from . '&toNumber=' . $to . '&message=' . urlencode( trim( $massage ) );

        wp_remote_get( $url );

        return true;
    }
}
