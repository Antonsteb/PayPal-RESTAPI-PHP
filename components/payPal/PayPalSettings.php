<?php


namespace frontend\components\payPal;


final class PayPalSettings
{
    private static $isLive = true;


    /**
     * PayPalToken private constructor.
     * @private
     */
    private function __construct()
    {
    }

    public static function getBaseUrl()
    {
        if (self::$isLive){
            return 'https://api.paypal.com';
        }
        return 'https://api.sandbox.paypal.com';
    }

    public static function getAuthInfo()
    {
        if (self::$isLive) {
            /** Eric Burton Live */
            $clientId = "your live client id";
            $secret = "your live secret key";
        } else {
            $clientId = "your sandbox client id";
            $secret = "your sandbox secret key";
        }
        return $clientId . ":" . $secret;

    }

    /**
     * private magic function for block clone
     * @private
     */
    private function __clone()
    {
    }

    /**
     * private magic function for block deserializable
     * @private
     */
    private function __wakeup()
    {
    }
}
