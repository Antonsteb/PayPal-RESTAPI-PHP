<?php

namespace frontend\models;

use frontend\components\payPal\PayPalSettings;
use frontend\components\payPal\PayPalToken;
use Yii;

/**
 * This is the model class for table "payments".
 *
 * @property string $pay_id
 * @property string $state
 * @property double $amount
 */
class Payments extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'payments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['pay_id', 'state'], 'string', 'max' => 255],
            [['amount'], 'double'],
            [['pay_id'], 'unique'],
        ];
    }

    /**
     * Create new payment.
     * @param $amount float Count USD for payment.
     * @see PayPalToken
     * @return string URL for redirect user.
     */
     public function createPayment($amount) {
        $ch1 = curl_init();
        $paymentData = [
            "intent" => "sale",
            "redirect_urls" => [
                "return_url" => "https://" . $_SERVER['HTTP_HOST'] . "/subscribe/successfully",
                "cancel_url" => $canselURL = "https://" . $_SERVER['HTTP_HOST'] . "/subscribe"
            ],
            "payer" => [
                "payment_method" => "paypal"
            ],
            "application_context" => [
                "shipping_preference" => "NO_SHIPPING",
            ],
            "transactions" =>[
                [
                    "amount" => [
                        "total" => $amount,
                        "currency" => "USD"
                    ]
                ]

            ]
        ];

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/payments/payment");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $paymentInfo = json_decode(curl_exec($ch1));
        curl_close($ch1);
        $this->pay_id = $paymentInfo->id;
        $this->state = $paymentInfo->state;
        return $paymentInfo->links[1]->href;
    }

    /**
     * Execute payment after the user is authentication to PayPal.
     * @param $payerID string id authenticated user.
     * @return Object Request result in json format.
     */
    public function executePayment($payerID){
        $ch = curl_init();
        $paymentData = [
            'payer_id' =>$payerID
        ];

        curl_setopt($ch, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/payments/payment/" . $this->pay_id ."/execute");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $paymentInfo = json_decode(curl_exec($ch));
        $amount = $paymentInfo->transactions[0]->amount->total;
        $this->state = $paymentInfo->state;
        $this->amount = $amount;
        return $paymentInfo;
    }

    /**
     * Get actual payment information.
     * @return bool|string Result request.
     */
    public function getPaymentInfo()
    {
        $ch1 = curl_init();

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/payments/payment/" . $this->pay_id);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

//        $result = json_decode(curl_exec($ch1));
        $result = curl_exec($ch1);
        curl_close($ch1);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'pay_id' => 'Pay ID',
            'state' => 'State',
            'amount' => 'Amount',
        ];
    }

    public static function primaryKey()
    {
        return ['pay_id'];
    }
}
