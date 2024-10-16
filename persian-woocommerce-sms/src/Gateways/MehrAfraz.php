<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class MehrAfraz implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'mehrafraz';
    }

    public static function name() {
        return 'mehrafraz.com';
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


        try {

            $client = new SoapClient( "http://www.mehrafraz.com/webservice/service.asmx?wsdl" );

            $result = $client->SendSms( [
                "cUserName"     => $username,
                "cPassword"     => $password,
                "cDomainname"   => $from,
                "cBody"         => $massage,
                "cSmsnumber"    => implode( ',', $to ),
                "cGetid"        => "0",
                "nCMessage"     => "1",
                "nTypeSent"     => "1",
                "m_SchedulDate" => "",
                "nSpeedsms"     => "0",
                "nPeriodmin"    => "0",
                "cstarttime"    => "",
                "cEndTime"      => "",
            ] );

            if ( ! empty( $result->SendSmsResult ) ) {

                $result  = $result->SendSmsResult;
                $results = explode( ',', $result );
                unset( $result );

                foreach ( $results as $result ) {
                    if ( intval( $result ) < 1000 ) {
                        $result       = $client->ShowError( [ "cErrorCode" => $result, "cLanShow" => "FA" ] );
                        $sms_response = ! empty( $result->ShowErrorResult ) ? $result->ShowErrorResult : $results;
                        break;
                    }
                }
            } else {
                $sms_response = 'unknown';
            }
        } catch ( Exception $ex ) {
            $sms_response = $ex->getMessage();
        }

        if ( empty( $sms_response ) ) {
            return true; // Success
        }

        return $sms_response;
    }
}
