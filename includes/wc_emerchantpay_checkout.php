<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      emerchantpay Ltd.
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined( 'ABSPATH' )) {
    exit(0);
}

if (!class_exists('WC_emerchantpay_Method')) {
    require_once dirname(dirname(__FILE__)) . '/classes/wc_emerchantpay_method_base.php';
}

/**
 * emerchantpay Checkout
 *
 * @class   WC_emerchantpay_Checkout
 * @extends WC_Payment_Gateway
 *
 * @SuppressWarnings(PHPMD)
 */
class WC_emerchantpay_Checkout extends WC_emerchantpay_Method
{
    /**
     * Payment Method Code
     *
     * @var null|string
     */
    protected static $method_code = 'emerchantpay_checkout';

    /**
     * Additional Method Setting Keys
     */
    const SETTING_KEY_TRANSACTION_TYPES        = 'transaction_types';
    const SETTING_KEY_CHECKOUT_LANGUAGE        = 'checkout_language';
    const SETTING_KEY_INIT_RECURRING_TXN_TYPES = 'init_recurring_txn_types';
    const SETTING_KEY_TOKENIZATION             = 'tokenization';

    /**
     * Additional Order/User Meta Constants
     */
    const META_CHECKOUT_TRANSACTION_ID  = '_genesis_checkout_id';
    const META_TOKENIZATION_CONSUMER_ID = '_consumer_id';

    /**
     * @return string
     */
    protected function getModuleTitle()
    {
        return static::getTranslatedText('emerchantpay Checkout');
    }

    /**
     * Holds the Meta Key used to extract the checkout Transaction
     *   - Checkout Method -> WPF Unique Id
     *
     * @return string
     */
    protected function getCheckoutTransactionIdMetaKey()
    {
        return self::META_CHECKOUT_TRANSACTION_ID;
    }

    /**
     * Determines if the a post notification is a valida Gateway Notification
     *
     * @param array $postValues
     * @return bool
     */
    protected function getIsValidNotification($postValues)
    {
        return
            parent::getIsValidNotification($postValues) &&
            isset($postValues['wpf_unique_id']);
    }

    /**
     * Setup and initialize this module
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Check if this gateway is enabled and all dependencies are fine.
     * Disable the plugin if dependencies fail.
     *
     * @access      public
     * @return      bool
     */
    public function is_available() {
        return
            parent::is_available() &&
            $this->getMethodHasSetting(
                self::SETTING_KEY_TRANSACTION_TYPES
            );
    }

    /**
     * Determines if the Payment Method can be used for the configured Store
     *
     * @return bool
     */
    protected function is_applicable()
    {
        return parent::is_applicable();
    }

    /**
     * Event Handler for displaying Admin Notices
     *
     * @return bool
     */
    public function admin_notices()
    {
        if (!parent::admin_notices()) {
            return false;
        }

        $areApiTransactionTypesDefined = true;

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if (!$this->getMethodHasSetting(self::SETTING_KEY_TRANSACTION_TYPES)) {
                $areApiTransactionTypesDefined = false;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $transactionTypesPostParamName = $this->getMethodAdminSettingPostParamName(
                self::SETTING_KEY_TRANSACTION_TYPES
            );

            if (!isset($_POST[$transactionTypesPostParamName]) || empty($_POST[$transactionTypesPostParamName])) {
                $areApiTransactionTypesDefined = false;;
            }
        }

        if (!$areApiTransactionTypesDefined) {
            WC_emerchantpay_Helper::printWpNotice(
                static::getTranslatedText('You must specify at least one transaction type in order to be able to use this payment method!'),
                WC_emerchantpay_Helper::WP_NOTICE_TYPE_ERROR
            );
        }

        if ($this->getMethodBoolSetting(self::SETTING_KEY_TOKENIZATION)) {
            WC_emerchantpay_Helper::printWpNotice(
                static::getTranslatedText(
                    'Tokenization is enabled for Web Payment Form, ' .
                    'please make sure Guest Checkout is disabled.'
                ),
                WC_emerchantpay_Helper::WP_NOTICE_TYPE_ERROR
            );
        }

        return true;
    }

    /**
     * Admin Panel Field Definition
     *
     * @return void
     */
    public function init_form_fields()
    {
        // Admin description
        $this->method_description =
            static::getTranslatedText('emerchantpay\'s Gateway works by sending your client, to our secure (PCI-DSS certified) server.');

        parent::init_form_fields();

        $this->form_fields += array(
            self::SETTING_KEY_TRANSACTION_TYPES => array(
                'type'        => 'multiselect',
                'css'         => 'height:auto',
                'title'       => static::getTranslatedText('Transaction Type'),
                'options'     => array(
                    \Genesis\API\Constants\Transaction\Types::ALIPAY              =>
                        static::getTranslatedText('Alipay'),
                    \Genesis\API\Constants\Transaction\Types::AUTHORIZE           =>
                        static::getTranslatedText('Authorize'),
                    \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D        =>
                        static::getTranslatedText('Authorize (3D-Secure)'),
                    \Genesis\API\Constants\Transaction\Types::BITPAY_SALE         =>
                        static::getTranslatedText( 'BitPay' ),
                    \Genesis\API\Constants\Transaction\Types::BANCO_DO_BRASIL     =>
                        static::getTranslatedText( 'Banco do Brasil' ),
                    \Genesis\API\Constants\Transaction\Types::CASHU               =>
                        static::getTranslatedText('CashU'),
                    \Genesis\API\Constants\Transaction\Types::ENTERCASH           =>
                        static::getTranslatedText( 'Entercash' ),
                    \Genesis\API\Constants\Payment\Methods::EPS                   =>
                        static::getTranslatedText('eps'),
                    \Genesis\API\Constants\Transaction\Types::EZEEWALLET          =>
                        static::getTranslatedText('eZeeWallet'),
                    \Genesis\API\Constants\Transaction\Types::FASHIONCHEQUE       =>
                        static::getTranslatedText('Fashioncheque'),
                    \Genesis\API\Constants\Payment\Methods::GIRO_PAY              =>
                        static::getTranslatedText('GiroPay'),
                    \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN        =>
                        static::getTranslatedText('iDebit'),
                    \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN   =>
                        static::getTranslatedText('InstaDebit'),
                    \Genesis\API\Constants\Transaction\Types::INSTANT_TRANSFER    =>
                        static::getTranslatedText( 'InstantTransfer' ),
                    \Genesis\API\Constants\Transaction\Types::INTERSOLVE          =>
                        static::getTranslatedText('Intersolve'),
                    \Genesis\API\Constants\Payment\Methods::BCMC                  =>
                        static::getTranslatedText('Mr.Cash'),
                    \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE    =>
                        static::getTranslatedText('Klarna'),
                    \Genesis\API\Constants\Payment\Methods::MYBANK                =>
                        static::getTranslatedText('MyBank'),
                    \Genesis\API\Constants\Transaction\Types::MULTIBANCO          =>
                        static::getTranslatedText( 'Multibanco' ),
                    \Genesis\API\Constants\Transaction\Types::NETELLER            =>
                        static::getTranslatedText('Neteller'),
                    \Genesis\API\Constants\Transaction\Types::P24                 =>
                        static::getTranslatedText('P24'),
                    \Genesis\API\Constants\Transaction\Types::PAYPAL_EXPRESS      =>
                        static::getTranslatedText('PayPal Express'),
                    \Genesis\API\Constants\Transaction\Types::PAYSAFECARD         =>
                        static::getTranslatedText('PaySafeCard'),
                    \Genesis\API\Constants\Transaction\Types::PAYU                =>
                        static::getTranslatedText( 'PayU' ),
                    \Genesis\API\Constants\Transaction\Types::POLI                =>
                        static::getTranslatedText('POLi'),
                    \Genesis\API\Constants\Payment\Methods::PRZELEWY24            =>
                        static::getTranslatedText('Przelewy24'),
                    \Genesis\API\Constants\Payment\Methods::QIWI                  =>
                        static::getTranslatedText('Qiwi'),
                    \Genesis\API\Constants\Payment\Methods::SAFETY_PAY            =>
                        static::getTranslatedText('SafetyPay'),
                    \Genesis\API\Constants\Transaction\Types::SALE                =>
                        static::getTranslatedText('Sale'),
                    \Genesis\API\Constants\Transaction\Types::SALE_3D             =>
                        static::getTranslatedText('Sale (3D-Secure)'),
                    \Genesis\API\Constants\Transaction\Types::SDD_SALE            =>
                        static::getTranslatedText('Sepa Direct Debit'),
                    \Genesis\API\Constants\Transaction\Types::SOFORT              =>
                        static::getTranslatedText('SOFORT'),
                    \Genesis\API\Constants\Transaction\Types::TCS                 =>
                        static::getTranslatedText('TCS'),
                    \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE        =>
                        static::getTranslatedText('Trustly'),
                    \Genesis\API\Constants\Payment\Methods::TRUST_PAY             =>
                        static::getTranslatedText('TrustPay'),
                    \Genesis\API\Constants\Transaction\Types::WEBMONEY            =>
                        static::getTranslatedText('WebMoney'),
                    \Genesis\API\Constants\Transaction\Types::WECHAT              =>
                        static::getTranslatedText('WeChat'),
                    \Genesis\API\Constants\Transaction\Types::ZIMPLER             =>
                        static::getTranslatedText( 'Zimpler' ),
                ),
                'description' => static::getTranslatedText('Select transaction type for the payment transaction'),
                'desc_tip'    => true,
            ),
            self::SETTING_KEY_CHECKOUT_LANGUAGE => array(
                'type'        => 'select',
                'title'       => static::getTranslatedText('Checkout Language'),
                'options'     => array(
                    'en' => static::getTranslatedText(\Genesis\API\Constants\i18n::EN),
                    'es' => static::getTranslatedText(\Genesis\API\Constants\i18n::ES),
                    'fr' => static::getTranslatedText(\Genesis\API\Constants\i18n::FR),
                    'de' => static::getTranslatedText(\Genesis\API\Constants\i18n::DE),
                    'it' => static::getTranslatedText(\Genesis\API\Constants\i18n::IT),
                    'ja' => static::getTranslatedText(\Genesis\API\Constants\i18n::JA),
                    'zh' => static::getTranslatedText(\Genesis\API\Constants\i18n::ZH),
                    'ar' => static::getTranslatedText(\Genesis\API\Constants\i18n::AR),
                    'pt' => static::getTranslatedText(\Genesis\API\Constants\i18n::PT),
                    'tr' => static::getTranslatedText(\Genesis\API\Constants\i18n::TR),
                    'ru' => static::getTranslatedText(\Genesis\API\Constants\i18n::RU),
                    'bg' => static::getTranslatedText(\Genesis\API\Constants\i18n::BG),
                    'hi' => static::getTranslatedText(\Genesis\API\Constants\i18n::HI),
                ),
                'description' => __('Select language for the customer UI on the remote server'),
                'desc_tip'    => true,
            ),
            self::SETTING_KEY_TOKENIZATION => array(
                'type'    => 'checkbox',
                'title'   => static::getTranslatedText('Enable/Disable'),
                'label'   => static::getTranslatedText('Enable Tokenization'),
                'default' => self::SETTING_VALUE_NO
            )
        );

        $this->form_fields += $this->build_subscription_form_fields();
    }

    /**
     * Admin Panel Subscription Field Definition
     *
     * @return array
     */
    protected function build_subscription_form_fields()
    {
        $subscription_form_fields = parent::build_subscription_form_fields();

        return array_merge(
            $subscription_form_fields,
            array(
                self::SETTING_KEY_INIT_RECURRING_TXN_TYPES => array(
                    'type'        => 'multiselect',
                    'css'         => 'height:auto',
                    'title'       => static::getTranslatedText('Init Recurring Transaction Types'),
                    'options'     => array(
                        \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE =>
                            static::getTranslatedText('Init Recurring Sale'),
                        \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE_3D =>
                            static::getTranslatedText('Init Recurring Sale (3D-Secure)')
                    ),
                    'description' => static::getTranslatedText('Select transaction types for the initial recurring transaction'),
                    'desc_tip'    => true,
                ),
            )
        );
    }

    /**
     * Returns a list with data used for preparing a request to the gateway
     *
     * @param WC_Order $order
     * @param bool $isRecurring
     * @return array
     */
    protected function populateGateRequestData($order, $isRecurring = false)
    {
        $data = parent::populateGateRequestData($order, $isRecurring);

        return array_merge(
            $data,
            array(
                'return_cancel_url' => $order->get_cancel_order_url_raw()
            )
        );
    }

    /**
     * @param array $data
     * @return \Genesis\Genesis
     */
    protected function prepareInitialGenesisRequest($data)
    {
        $genesis = new \Genesis\Genesis( 'WPF\Create' );

        /** @var \Genesis\API\Request\WPF\Create $wpfRequest */
        $wpfRequest = $genesis->request();

        $wpfRequest
            ->setTransactionId(
                $data['transaction_id']
            )
            ->setCurrency(
                $data['currency']
            )
            ->setAmount(
                $data['amount']
            )
            ->setUsage(
                $data['usage']
            )
            ->setDescription(
                $data['description']
            )
            ->setCustomerEmail(
                $data['customer_email']
            )
            ->setCustomerPhone(
                $data['customer_phone']
            );

        /**
         * Notification & Urls
         */
        $wpfRequest
            ->setNotificationUrl(
                $data['notification_url']
            )
            ->setReturnSuccessUrl(
                $data['return_success_url']
            )
            ->setReturnFailureUrl(
                $data['return_failure_url']
            )
            ->setReturnCancelUrl(
                $data['return_cancel_url']
            );

        /**
         * Billing
         */
        $wpfRequest
            ->setBillingFirstName(
                $data['billing']['first_name']
            )
            ->setBillingLastName(
                $data['billing']['last_name']
            )
            ->setBillingAddress1(
                $data['billing']['address1']
            )
            ->setBillingAddress2(
                $data['billing']['address2']
            )
            ->setBillingZipCode(
                $data['billing']['zip_code']
            )
            ->setBillingCity(
                $data['billing']['city']
            )
            ->setBillingState(
                $data['billing']['state']
            )
            ->setBillingCountry(
                $data['billing']['country']
            );

        /**
         * Shipping
         */
        $wpfRequest
            ->setShippingFirstName(
                $data['shipping']['first_name']
            )
            ->setShippingLastName(
                $data['shipping']['last_name']
            )
            ->setShippingAddress1(
                $data['shipping']['address1']
            )
            ->setShippingAddress2(
                $data['shipping']['address2']
            )
            ->setShippingZipCode(
                $data['shipping']['zip_code']
            )
            ->setShippingCity(
                $data['shipping']['city']
            )
            ->setShippingState(
                $data['shipping']['state']
            )
            ->setShippingCountry(
                $data['shipping']['country']
            );

        /**
         * WPF Language
         */
        if ($this->getMethodHasSetting(self::SETTING_KEY_CHECKOUT_LANGUAGE)) {
            $wpfRequest->setLanguage(
                $this->getMethodSetting(self::SETTING_KEY_CHECKOUT_LANGUAGE)
            );
        }

        if ($this->isTokenizationAvailable($data['customer_email'])) {
            $consumerId = $this->getGatewayConsumerIdFor($data['customer_email']);

            if ($consumerId) {
                $wpfRequest->setConsumerId($consumerId);
            }

            $wpfRequest->setRememberCard(true);
        }

        return $genesis;
    }

    /**
     * @param $customerEmail
     *
     * @return bool
     */
    protected function isTokenizationAvailable($customerEmail)
    {
        return !empty($customerEmail) &&
               $this->getMethodBoolSetting(self::SETTING_KEY_TOKENIZATION) &&
               get_current_user_id() !== 0;
    }

    /**
     * @param $customerEmail
     *
     * @return string|null
     */
    protected function getGatewayConsumerIdFor($customerEmail)
    {
        $meta = $this->getMetaConsumerIdForLoggedUser();

        return !empty($meta[$customerEmail]) ? $meta[$customerEmail] : null;
    }

    /**
     * @return array
     */
    protected function getMetaConsumerIdForLoggedUser()
    {
        if (!WC_emerchantpay_Helper::isUserLogged()) {
            return [];
        }

        $meta = json_decode(
            get_user_meta(get_current_user_id(), self::META_TOKENIZATION_CONSUMER_ID, true),
            true
        );

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param $customerEmail
     * @param $consumerId
     */
    protected function setGatewayConsumerIdFor($customerEmail, $consumerId)
    {
        if (!WC_emerchantpay_Helper::isUserLogged()) {
            return;
        }

        $meta = $this->getMetaConsumerIdForLoggedUser();

        $meta[$customerEmail] = $consumerId;

        update_user_meta(get_current_user_id(), self::META_TOKENIZATION_CONSUMER_ID, json_encode($meta));
    }

    /**
     * @param \Genesis\Genesis $genesis
     * @param WC_Order $order
     * @param array $requestData
     * @param bool $isRecurring
     * @return void
     */
    protected function addTransactionTypesToGatewayRequest($genesis, $order, $requestData, $isRecurring)
    {
        /** @var \Genesis\API\Request\WPF\Create $wpfRequest */
        $wpfRequest = $genesis->request();

        if ($isRecurring) {
            $recurring_types = $this->get_recurring_payment_types();
            foreach ($recurring_types as $type) {
                $wpfRequest->addTransactionType( $type );
            }

            return;
        }

        $this->addCustomParametersToTrxTypes($wpfRequest, $order, $requestData);
    }

    /**
     * @param \Genesis\API\Request\WPF\Create $wpfRequest $wpfRequest
     * @param WC_Order $order
     * @param array $requestData
     */
    private function addCustomParametersToTrxTypes($wpfRequest, WC_Order $order, $requestData)
    {
        $types = $this->get_payment_types();

        foreach ( $types as $type ) {
            if (is_array($type)) {
                $wpfRequest->addTransactionType($type['name'], $type['parameters']);

                continue;
            }

            switch ($type) {
                case \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN:
                case \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN:
                    $userIdHash              = WC_emerchantpay_Genesis_Helper::getCurrentUserIdHash();
                    $transactionCustomParams = array(
                        'customer_account_id' => $userIdHash
                    );
                    break;
                case \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE:
                    $transactionCustomParams = WC_emerchantpay_Order_Helper::getKlarnaCustomParamItems($order)->toArray();
                    break;
                default:
                    $transactionCustomParams = [];
            }

            $wpfRequest->addTransactionType($type, $transactionCustomParams);
        }
    }

    /**
     * Initiate Order Checkout session
     *
     * @param int $order_id
     * @return array|bool
     */
    protected function process_order_payment( $order_id )
    {
        return $this->process_common_payment( $order_id, false);
    }

    /**
     * Initiate Gateway Payment Session
     *
     * @param int $order_id
     * @return array|bool
     */
    protected function process_init_subscription_payment( $order_id )
    {
        return $this->process_common_payment( $order_id, true);
    }

    /**
     * Initiate Order Checkout session
     *   or
     * Init Recurring Checkout
     *
     * @param int $order_id
     * @param bool $isRecurring
     * @return array|bool
     */
    protected function process_common_payment( $order_id, $isRecurring )
    {
        $order = new WC_Order( absint($order_id)  );

        $data = $this->populateGateRequestData($order, $isRecurring);

        try {
            $this->set_credentials();

            $genesis = $this->prepareInitialGenesisRequest($data);
            $this->addTransactionTypesToGatewayRequest($genesis, $order, $data, $isRecurring);

            $genesis->execute();

            $response = $genesis->response()->getResponseObject();

            $isWpfSuccessfullyCreated =
                ($response->status == \Genesis\API\Constants\Transaction\States::NEW_STATUS) &&
                isset($response->redirect_url);

            if ($isWpfSuccessfullyCreated) {
                $this->save_checkout_trx_to_order($response, WC_emerchantpay_Order_Helper::getOrderProp($order, 'id'));

                if ($this->isTokenizationAvailable($data['customer_email'])) {
                    $this->save_tokenization_data($data['customer_email'], $response);
                }

                // Create One-time token to prevent redirect abuse
                $this->set_one_time_token($order_id, $this->generateTransactionId());

                return array(
                    'result'   => static::RESPONSE_SUCCESS,
                    'redirect' => $response->redirect_url
                );
            } else {
                throw new \Exception(
                    static::getTranslatedText(
                        'An error has been encountered while initiating Web Payment Form! Please try again later.'
                    )
                );
            }

        } catch (\Exception $exception) {
            if (isset($genesis) && isset($genesis->response()->getResponseObject()->message)) {
                $error_message = $genesis->response()->getResponseObject()->message;
            } else {
                $error_message = self::getTranslatedText(
                    'We were unable to process your order!' . '<br/>' .
                    'Please double check your data and try again.'
                );
            }

            WC_emerchantpay_Message_Helper::addErrorNotice($error_message);

            WC_emerchantpay_Helper::logException($exception);

            return false;
        }
    }

    protected function save_checkout_trx_to_order($response_obj, $order_id) {
        // Save the Checkout Id
        WC_emerchantpay_Order_Helper::setOrderMetaData(
            $order_id,
            self::META_CHECKOUT_TRANSACTION_ID,
            $response_obj->unique_id
        );

        // Save whole trx
        WC_emerchantpay_Order_Helper::saveInitialTrxToOrder($order_id, $response_obj);
    }

    /**
     * @param $customer_email
     * @param $response_obj
     */
    protected function save_tokenization_data($customer_email, $response_obj)
    {
        if (!empty($response_obj->consumer_id)) {
            $this->setGatewayConsumerIdFor($customer_email, $response_obj->consumer_id);
        }
    }

    /**
     * Set the Terminal token associated with an order
     *
     * @param $order
     *
     * @return bool
     */
    protected function set_terminal_token( $order )
    {
         $token = WC_emerchantpay_Order_Helper::getOrderMetaData(
             $order->id,
             self::META_TRANSACTION_TERMINAL_TOKEN
         );

         if (empty( $token ) ) {
             return false;
         }

         \Genesis\Config::setToken( $token );

         return true;
    }

    /**
     * Get payment/transaction types array
     *
     * @return array
     */
    private function get_payment_types()
    {
        $processed_list = array();

        $selected_types = $this->getMethodSetting(self::SETTING_KEY_TRANSACTION_TYPES);

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::EPS         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY    =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::QIWI        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY   =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::BCMC        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::MYBANK      =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
        );

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

    /**
     * @return array
     */
    protected function get_recurring_payment_types()
    {
        return $this->getMethodSetting(self::SETTING_KEY_INIT_RECURRING_TXN_TYPES);
    }

    /**
     * @return bool
     */
    protected function isSubscriptionEnabled()
    {
        return
            parent::isSubscriptionEnabled() &&
            $this->getMethodHasSetting(
                self::SETTING_KEY_INIT_RECURRING_TXN_TYPES
            );
    }

    /**
     * Determines the Recurring Token, which needs to used for the RecurringSale Transactions
     *
     * @param WC_Order $order
     * @return string
     */
    protected function getRecurringToken( $order )
    {
        $recurringToken = parent::getRecurringToken( $order );

        if (!empty($recurringToken)) {
            return $recurringToken;
        }

        return WC_emerchantpay_Subscription_Helper::getTerminalTokenMetaFromSubscriptionOrder( $order->id );
    }
}

WC_emerchantpay_Checkout::registerStaticActions();
