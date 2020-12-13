<?php

namespace frontend\models;

use frontend\components\payPal\PayPalSettings;
use frontend\components\payPal\PayPalToken;
use Yii;

/**
 * This is the model class for table "subscriptions".
 *
 * @property int $is_updating_status Active and suspend set to 1 and webhook set to 0 after update status subscription.
 * @property int $auto_renewal
 * @property string $subscription_id
 * @property string $subscriptions_plan_id
 * @property string $next_billing_time
 * @property string $last_payment_time
 * @property string $status
 */
class Subscriptions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'subscriptions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['is_updating_status', 'auto_renewal'], 'integer'],
            [['subscription_id', 'subscriptions_plan_id', 'next_billing_time', 'last_payment_time',
                'status'], 'string', 'max' => 255],
        ];
    }

    /**
     * Create product for plan subscriptions.
     * @see PayPalToken
     * @return object json result request.
     */
    public function createProduct()
    {
        $ch1 = curl_init();
        $productData = [
            "name" => "Product name",
            "description" => "Product description",
            "type" => "DIGITAL",
            "category" => "SOFTWARE",
        ];

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/catalogs/products");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $product = json_decode(curl_exec($ch1));
        curl_close($ch1);
        return $product;
    }

    /**
     * Create pan for subscriptions.
     * @param string $productID Pre-created plan id.
     * @param string $fixedPriceValue Amount to be debited at each interval.
     * @param string $intervalUnit Interval unit MONTH or YEAR or DAY.
     * @see PayPalToken
     * @return object json plan info.
     */
    public function createPlan($productID, $fixedPriceValue, $intervalUnit)
    {
        $total_cycles = 1;
        $ch1 = curl_init();
        $planData = [
            "product_id" => $productID,
            "name" => "Basic Plan Name",
            "status" => "ACTIVE",
            "description" => "Basic plan description",
            "billing_cycles" => [
                [
                    "frequency" => [
                        "interval_unit" => $intervalUnit,
                        "interval_count" => 1
                    ],
                    "tenure_type" => "REGULAR",
                    "sequence" => 1,
                    "total_cycles" => $total_cycles,
                    "pricing_scheme" => [
                        "fixed_price" => [
                            "value" => $fixedPriceValue,
                            "currency_code" => "USD"
                        ]
                    ]
                ]
            ],
            "payment_preferences" => [
                "service_type" => "PREPAID",
                "auto_bill_outstanding" => false,
                "setup_fee" => [
                    "value" => "0",
                    "currency_code" => "USD"
                ],
                "setup_fee_failure_action" => "CONTINUE",
                "payment_failure_threshold" => 3
            ],
            "taxes" => [
                "percentage" => "0",
                "inclusive" => false
            ]
        ];


        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/plans");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($planData));
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $plan = json_decode(curl_exec($ch1));
        $this->subscriptions_plan_id = $plan->id;
        curl_close($ch1);
        return $plan;
    }

    /**
     * Create new subscription.
     * @param integer | null $start_time Null for start new or timestamp for start in teh future.
     * @see PayPalToken
     * @return string URL for redirect user.
     */
    public function createSubscriptions($start_time = null)
    {
        $ch1 = curl_init();
        $cancel_url = "https://" . $_SERVER['HTTP_HOST'] . "/subscribe?status=cancel";
        if ($start_time === null){
            $start_time = date('c', time() + 3600);
        }
        $subscriptionsData = [
            "plan_id" => $this->subscriptions_plan_id,
            "start_time" => $start_time,
            "quantity" => "1",
            "auto_renewal" => 'true',
            "application_context" => [
                "brand_name" => "Your brand name",
                "locale" => "en-US",
                "shipping_preference" => "NO_SHIPPING",
                "user_action" => "SUBSCRIBE_NOW",
                "payment_method" => [
                    "payer_selected" => "PAYPAL",
                    "payee_preferred" => "IMMEDIATE_PAYMENT_REQUIRED"
                ],
                "return_url" => "https://" . $_SERVER['HTTP_HOST'] . "/subscribe/successfully",
                "cancel_url" => $cancel_url,
            ]
        ];

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/subscriptions");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($subscriptionsData));
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $result = json_decode(curl_exec($ch1));
        $this->subscription_id = $result->id;
        curl_close($ch1);
        return $result->links[0]->href;

    }

    /**
     * Returned result request.
     * @see PayPalToken
     * @return object json subscription details.
     */
    public function getSubscriptionDetails()
    {
        $ch1 = curl_init();

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/subscriptions/"
            . $this->subscription_id);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        $result = json_decode(curl_exec($ch1));
        curl_close($ch1);
        return $result;
    }

    /**
     * Active this subscription.
     * @see PayPalToken
     */
    public function activeSubscription()
    {
        $ch1 = curl_init();

        $reason = [
            "reason" => "Reactivating the subscription"
        ];        

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/subscriptions/" .
            $this->subscription_id . "/activate");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($reason));
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Content-Type: application/json'
        ));
//        $response = curl_exec($ch1);
//        $header_data= curl_getinfo($ch1);
//        $result = json_decode($response);
        curl_close($ch1);
        $this->is_updating_status = 1;
        //return $result;
    }

    /**
     * Suspended this subscription.
     * @see PayPalToken
     */
    public function suspendedSubscription()
    {
        $ch1 = curl_init();

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/subscriptions/" .
            $this->subscription_id . "/suspend");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Content-Type: application/json'
        ));
//        $response = curl_exec($ch1);
//        $header_data= curl_getinfo($ch1);
//        $result = json_decode($response);
        curl_close($ch1);
        $this->is_updating_status = 1;
    }

    /**
     * Cancel this subscription.
     * @see PayPalToken
     */
    public function cancelSubscription()
    {
        $ch1 = curl_init();
        $suspendedData = [
            "reason" => "user canceled subscriptions"
        ];

        curl_setopt($ch1, CURLOPT_URL, PayPalSettings::getBaseUrl() . "/v1/billing/subscriptions/" .
            $this->subscription_id . "/cancel");
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($suspendedData));
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . PayPalToken::getToken(),
            'Accept: application/json',
            'Content-Type: application/json'
        ));

        curl_exec($ch1);
        curl_close($ch1);

    }

    /**
     * Obtains subscription data and set the actual data in the attributes.
     */
    public function updateSubscriptionInfo()
    {
        $detail = $this->getSubscriptionDetails();
        if ($detail->billing_info->next_billing_time){
            $this->next_billing_time = $detail->billing_info->next_billing_time;
        } else {
            $this->next_billing_time = null;
        }
        if ($detail->billing_info->last_payment->time){
            $this->last_payment_time = $detail->billing_info->last_payment->time;
        } else {
            $this->last_payment_time = null;
        }
        if ($detail->status){
            $this->status = $detail->status;
        }
        if ($detail->plan_id) {
            $this->subscriptions_plan_id = $detail->plan_id;
        }
    }


    public static function primaryKey()
    {
        return ['subscription_id'];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'subscription_id' => 'Subscription ID',
            'status' => 'Status',
            'subscriptions_plan_id' => 'Subscriptions Plan ID',
            'next_billing_time' => 'Next billing time',
            'last_payment_time' => 'Last payment time',
            'is_updating_status' => 'Is updating status',
            'auto_renewal' => 'Auto renewal',
        ];
    }
}
