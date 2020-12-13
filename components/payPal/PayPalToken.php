<?php


namespace frontend\components\payPal;

/**
 * Class PayPalToken get and update payPal token.
 * @final
 * @package frontend\components\payPal
 */
final class PayPalToken
{
    /** @var object all info about token get from payPal */
    private static $tokenInfo;
    /** @var int timestamp expire this token */
    private static $timestampExpire = 0;

    /**
     * PayPalToken private constructor.
     * @private
     */
    private function __construct()
    {
    }

    /**
     * If necessary, it update the token and returns the currently active token.
     * @public
     * @return string payPal access token.
     */
    public static function getToken()
    {
        if (self::$timestampExpire === 0){
            self::cheekTokenInCache();
        }
        if (self::$timestampExpire <= time()){
           self::updateToken();
        }
        return self::$tokenInfo->access_token;
    }

    /**
     * Update this current info about token from cache.
     * @see $tokenInfo
     * @see $timestampExpire
     */
    private static function cheekTokenInCache()
    {
        self::$tokenInfo = json_decode(\Yii::$app->cache->get('tokenInfo'));
        self::$timestampExpire = \Yii::$app->cache->get('timestampExpire');
    }

    /**
     * Makes a post-request for a new token in PayPal.
     * @private
     * @return bool Indicates whether a response was received.
     */
    private static function updateToken()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,  PayPalSettings::getBaseUrl() . "/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, PayPalSettings::getAuthInfo());
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $result = curl_exec($ch);

        if (empty($result)) {
            curl_close($ch);
            return false;
        } else {
            $json = json_decode($result);
            self::$tokenInfo = $json;
            self::$timestampExpire = time() + $json->expires_in * 1000;
            curl_close($ch);
            \Yii::$app->cache->set('tokenInfo',$result,$json->expires_in);
            \Yii::$app->cache->set('timestampExpire',self::$timestampExpire,$json->expires_in);
            return true;
        }

    }

    /**
     * private magic function for block clone.
     * @private
     */
    private function __clone()
    {
    }

    /**
     * private magic function for block deserializable.
     * @private
     */
    private function __wakeup()
    {
    }
}
