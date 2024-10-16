<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class KaveNegarLookUp implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'kavenegar_lookUp';
    }

    public static function name() {
        return 'kavenegar.com(lookup)';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        //    $password = $this->password;
        $from    = $this->senderNumber;
        $massage = $this->message;
        if ( empty( $username ) ) {
            return $response;
        }


        $regex_template = '/(?<=template=)(.*?)(?=token\d*=|$)/is';

        $regex_tokens = '/(token=|token\d=|token\d\d=)/is';

        $regex_variables = '/(?<=token=|token\d=|token\d\d=)(.*?)(?=token\d*=|$|template)/is';

        preg_match_all( $regex_template, $massage, $template_matches, PREG_PATTERN_ORDER, 0 );
        preg_match_all( $regex_tokens, $massage, $tokens_matches, PREG_PATTERN_ORDER, 0 );
        preg_match_all( $regex_variables, $massage, $variables_matches, PREG_PATTERN_ORDER, 0 );

        $to = implode( ',', $this->mobile );


        $templateName = $template_matches[0][0];
        $tokensParam  = "";


        for ( $i = 0; $i <= count( $tokens_matches[0] ) - 1; $i ++ ) {
            $tokenName = $tokens_matches[0][ $i ];
            $lookupval = html_entity_decode( $variables_matches[0][ $i ] );

            if ( ( strcasecmp( $tokenName, 'token10=' ) != 0 ) && ( strcasecmp( $tokenName, 'token20=' ) != 0 ) ) {
                $lookupval = str_replace( ' ', '-', $lookupval );
            }

            $tokensParam .= "&" . $tokenName . rawurlencode( html_entity_decode( $lookupval, ENT_QUOTES, 'UTF-8' ) );

        }


        $templateName = trim( $templateName );

        $url = "http://api.kavenegar.com/v1/$username/verify/lookup.json?receptor=$to&template=$templateName" . $tokensParam;

        $remote = wp_remote_get( $url );

        $sms_response = wp_remote_retrieve_body( $remote );

        if ( false !== $sms_response ) {
            $json_response = json_decode( $sms_response );
            if ( ! empty( $json_response->return->status ) && $json_response->return->status == 200 ) {
                return true; // Success
            }
        }

        if ( $response !== true ) {
            $response = $sms_response;
        }

        return $response;
    }
}
