<?php

require_once(dirname(__FILE__) . '/LPShippingBaseRequest.php');
require_once(dirname(__FILE__) . './../services/LPShippingOrderService.php');
require_once(dirname(__FILE__) . '/LPShippingRequestErrorHandler.php');
require_once(dirname(__FILE__) . './../classes/LPShippingConsts.php');

/**
 * Singleton class to make calls to API
 */
class LPShippingRequest extends LPShippingBaseRequest
{
    /**
     * Lietuvos Pastas API version
     */
    const API_VERSION = 'api/v1/';

    private $baseUrl;

    private $token = null;

    /** @var LPShippingRequestErrorHandler */
    private $errorHandler = null;

    /**
     * @var LPShippingRequest
     * API Requests class instance
     */
    private static $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get instance of requests class
     *
     * @return LPShippingRequest
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new LPShippingRequest();
            self::$instance->setApiUrl();
            self::$instance->errorHandler = LPShippingRequestErrorHandler::getInstance();
        }

        return self::$instance;
    }

    /**
     * Check if module working mode is live
     *
     * @return bool
     */
    protected function isLiveMode()
    {
        return Configuration::get('LP_SHIPPING_LIVE_MODE');
    }

    /**
     * Get test or production environment URL depending on setting set by administrator
     *
     * @return string
     */
    protected function getApiUrlByEnvironment()
    {
        if (self::getInstance()->isLiveMode()) {
            return Configuration::get('LP_SHIPPING_URL');
        } else {
            return Configuration::get('LP_SHIPPING_URL_TEST');
        }
    }

    /**
     * Set base url of API
     */
    public static function setApiUrl()
    {
        self::getInstance()->baseUrl = self::getInstance()->getApiUrlByEnvironment();
    }

    /**
     * Check if result contains error
     *
     * @param array $result
     *
     * @return bool|string
     */
    private function isError(array $result)
    {
        if (is_array($result) && key_exists('error', $result)) {
            return $result['error_description'];
        }

        return false;
    }

    /**
     * Build authentication header
     *
     * @return array
     */
    private function buildAuthHeader()
    {
        return [
            'Authorization: Bearer ' . self::getInstance()->getApiToken(),
        ];
    }

    /**
     * Get API token from DB or from instance, depends if token has been retrieved already
     *
     * @return string
     */
    private function getApiToken()
    {
        $dbToken = unserialize(Configuration::get('LP_SHIPPING_API_TOKEN'));
        if (is_array($dbToken) && count($dbToken) > 0) {
            if (time() >= $dbToken['expiration_date']) {
                //todo: change back to refresh token when API call is fixed
                $this->authenticate();

                $dbToken = unserialize(Configuration::get('LP_SHIPPING_API_TOKEN'));

                return $dbToken['access_token'];
            }

            self::getInstance()->token = $dbToken['access_token'];

            return $dbToken['access_token'];
        }

        return '';
    }

    /**
     * Authenticate to API and retrieve token
     *
     * @return bool
     */
    public static function authenticate()
    {
        $instance = self::getInstance();

        $authQuery = http_build_query([
            'username' => Configuration::get('LP_SHIPPING_ACCOUNT_EMAIL'),
            'password' => Configuration::get('LP_SHIPPING_ACCOUNT_PASSWORD'),
            'grant_type' => 'password',
            'scope' => 'read+write',
            'clientSystem' => 'PUBLIC'
        ]);

        $endpoint = $instance->baseUrl . 'oauth/token?' . $authQuery;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ];

        $instance->setHeaders([]); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return false;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        if (is_array($result) && key_exists('access_token', $result) && !empty($result['access_token'])) {
            $result['expiration_date'] = time() + ((int)$result['expires_in']);

            $instance->token = $result['access_token'];
            Configuration::updateValue('LP_SHIPPING_API_TOKEN', serialize($result)); // Save whole token to DB with expiration time

            return true;
        }

        return false;
    }


    /**
     * Refresh API token
     *
     * @return bool
     */
    private function refreshToken()
    {
        $tokenData = unserialize(Configuration::get('LP_SHIPPING_API_TOKEN'));
        $instance = self::getInstance();

        $authQuery = http_build_query([
            'grant_type' => 'refresh_token',
            'scope' => 'read+write',
            'clientSystem' => 'PUBLIC',
            'refresh_token' => $tokenData['refresh_token']
        ]);

        $endpoint = $instance->baseUrl . 'oauth/token?' . $authQuery;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
        ];

        $instance->setHeaders([]); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return false;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        if (is_array($result) && key_exists('access_token', $result) && !empty($result['access_token'])) {
            $result['expiration_date'] = time() + ((int)$result['expires_in']);

            $instance->token = $result['access_token'];
            Configuration::updateValue('LP_SHIPPING_API_TOKEN', serialize($result)); // Save whole token to DB with expiration time

            return true;
        }

        return false;
    }

    /**
     * Return all possible shipping templates
     *
     * @return array
     */
    public static function getItemShippingTemplates()
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/shippingItemTemplates';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_RETURNTRANSFER => true,
        ];

        $instance->setHeaders($instance->buildAuthHeader());
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        if ($err = $instance->isError($result)) {
            throw new Exception($err);
        }

        return $result;
    }


    /**
     * Pull all terminals from LP API service
     *
     * @return array
     */
    public static function getTerminals()
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'address/terminals?size=999999'; // get all terminals

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
        ];

        $instance->setHeaders($instance->buildAuthHeader());
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        if ($err = $instance->isError($result)) {
            throw new Exception($err);
        }

        return $result;
    }

    /**
     * Validate Post Code
     *
     * @param array $address
     *
     * @return false|array $address
     */
    public static function validatePostCode(array $address)
    {
        if ($address['country'] != 'LT') {
            return $address;
        }

        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'address/verification';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode([
                'countryId' => 118,
                'country' => $address['country'],
                'locality' => $address['locality'],
                'street' => $address['street'],
                'postalCode' => $address['postalCode'],
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        if (!is_array($result) || !key_exists('countryId', $result)) {
            return false;
        }

        return $result;
    }

    /**
     * Creates shipping item in LP API
     *
     * O - optional, R - required
     * (O) externalId string
     * (R) template string
     * (R) receiver ReceiverType
     * (O) sender SenderType
     * (R) partCount int
     * (O) weight float (g)
     * (O) additionalServices AdditionalServicesType (pass on update after getting created shipping item details)
     * (O) documents ShippingDocumentsType (pass on update after getting created shipping item details)
     *
     * @param array $order
     *
     * @return array
     */
    public static function createShippingItem(array $order)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping';

        if ((float)$order['weight'] <= 0.0) {
            $order['weight'] = 0.1;
        }
        $body = static::formRequestBody($order);
        $bodyEncoded = json_encode($body);
        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $bodyEncoded,
            CURLOPT_RETURNTRANSFER => true
        ];
        
        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);
        $instance->refreshCurl();

        return $result;
    }

    private static function formRequestBody($order)
    {
        $lpOrderService = new LPShippingOrderService();
        $receiver = $lpOrderService->formReceiverType($order);
        $sender = $lpOrderService->formSenderTypeFromOrder($order);
        $cart = new Cart($order['id_cart']);
        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);
        $cartTotal = $cart->getOrderTotal();
        $isoCode = $country->iso_code;
        $templateId = $order['shipping_template_id'];
        $isCn22Required = $lpOrderService->isDeclarationCN22Required($templateId, $isoCode, $cartTotal);
        $isCn23Required = $lpOrderService->isDeclarationCN23Required($templateId, $isoCode, $cartTotal);

        $additionalServices = [];
        if ((int)$templateId >= 70 && (int)$templateId <= 78) {
            $additionalServices[] = ['id' => 2];
        }

        // check if needed additional services and update shipping item
        $shipmentPriority = Configuration::get('LP_SHIPPING_SHIPMENT_PRIORITY');
        $shipmentRegistered = Configuration::get('LP_SHIPPING_SHIPMENT_REGISTERED');

        $orderOptions = $lpOrderService->getShipmentItemOptions($order['id_order']);
        // add priority and registered shipment service (depends on config)
        if (is_array($orderOptions) && key_exists('availableServices', $orderOptions)) {
            foreach ($orderOptions['availableServices'] as $option) {
                // check for priority sending
                if ($shipmentPriority && mb_stripos($option['title'], "pirmenyb", 0, 'UTF-8') !== false) {
                    $additionalServices[] = ["id" => (int)$option['id']];
                }

                if ($shipmentRegistered && $option['id'] == '1') { // registered shipment additional service id
                    $additionalServices[] = ["id" => (int)$option['id']];
                }

                // check for COD
                $isCod = $order['cod_selected'];
                if ($isCod && mb_stripos($option['title'], "išperkamoji", 0, 'UTF-8') !== false) {
                    $additionalServices[] = [
                        "id" => (int)$option['id'],
                        "amount" => (float)$order['cod_amount']
                    ];
                }
            }
        }

        $body = [
            'template' => $order['shipping_template_id'],
            'additionalServices' => $additionalServices,
            'receiver' => $receiver->getFormedReceiverType(),
            'sender' => $sender->getFormedSenderType(),
            'partCount' => $order['number_of_packages']
        ];

        $documents = self::formDocuments($order['id_lpshipping_order'], $isCn22Required, $isCn23Required);
        if (!empty($documents)) {
            $body['documents'] = $documents;
        }

        $body['weight'] = (int)($order['weight'] * 1000);

        return $body;
    }

    private static function formDocuments($orderId, $cn22Required, $cn23Required)
    {
        $result = [];

        if ((!$cn22Required && !$cn23Required) || !$orderId) {
            return $result;
        }

        $document = LPShippingDocument::getByParentId($orderId);
        if (!$document) {
            return $result;
        }

        $documentPart = LPShippingDocumentPart::getByParentId($document[LPShippingDocumentPart::PARENT_KEY]);

        if (!$documentPart) {
            return $result;
        }

        $documentDto = [
            'parcelType' => $document['parcel_type'],
            'parcelTypeNotes' => $document['notes'],
            'cnParts' => [
                [
                    'amount' => (int)$documentPart['amount'],
                    'countryCode' => $documentPart['country_code'],
                    'currencyCode' => $documentPart['currency_code'],
                    'weight' => (int)((float)$documentPart['weight'] * 1000),
                    'quantity' => (int)$documentPart['quantity'],
                    'summary' => !empty($documentPart['summary']) ? $documentPart['summary'] : ' '
                ]
            ]

        ];

        if ($cn22Required) {
            $result['cn22Form'] = $documentDto;
        } elseif ($cn23Required) {
            $result['cn23Form'] = $documentDto;
        }

        return $result;
    }

    /**
     * Update shipping item in LP API
     *
     * (O) - optional, R - required
     * (O) externalId string
     * (R) template string
     * (R) receiver ReceiverType
     * (O) sender SenderType
     * (R) partCount int
     * (O) weight float (g)
     * (O) additionalServices AdditionalServicesType (pass on update after getting created shipping item details)
     * (O) documents ShippingDocumentsType (pass on update after getting created shipping item details)
     *
     * @param array $order
     *
     * @return array
     */
    public static function updateShippingItem(array $orderData)
    {
        // check if ID of lp internal order exists
        //        $order = LPShippingOrder::getOrderById($orderData['id_order']);


        if (is_array($orderData) && key_exists('id_lp_internal_order', $orderData) && $orderData['id_lp_internal_order']) {
            $instance = self::getInstance();
            $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/' . $orderData['id_lp_internal_order'];

            if ((float) $orderData['weight'] <= 0.0) {
                $orderData['weight'] = 1;
            };


            $requestOptions = [
                CURLOPT_URL => $endpoint,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode(static::formRequestBody($orderData)),
                CURLOPT_RETURNTRANSFER => true
            ];

            $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
            $instance->setOptions($requestOptions);

            $res = $instance->executeCallAndGetResult();
            if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
                return $res;
            }

            $result = json_decode($res, true);

            $instance->refreshCurl();

            return $result;
        } else {
            return self::createShippingItem($orderData);
        }
    }

    /**
     * Delete shipping item from LP API
     *
     * @param string $id
     *
     * @return bool|string - true or exception message
     */
    public static function deleteShippingItem($id)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/' . $id;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Get shipping item from LP API
     *
     * @param string $id
     *
     * @return array
     * @return ShippingItemType structure array from LP API
     */
    public static function getShippingItem($id)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/' . $id;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Get shipping item options from LP API. Needed for additional services or declarations before update
     * return ShippingItemType structure array from LP API
     *
     * @param string $id
     *
     * @return array
     */
    public static function getShippingItemOptions($id)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/' . $id;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Creates shipping item in LP API
     *
     * @param array|string $orderIds
     *
     * @return array|string
     */
    public static function initiateShippingItem($orderIds)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/initiate';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($orderIds),
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);
    
        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Creates shipping item in LP API
     *
     * @param array|string $lpInternalShippingItemId
     *
     * @return array|string
     */
    public static function callCourier($lpInternalOrderId)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'shipping/courier/call';

        $ids = "[
            $lpInternalOrderId
        ]";

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $ids,
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Send request to print labels (can print more than one at once) and get it here
     *
     * @param string $id
     *
     * @return array
     */
    public static function printLabels(array $ids, $stickerSize)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'documents/item/sticker/?';

        foreach ($ids as $id) {
            $endpoint .= 'itemId=' . $id . '&';
        }
        $endpoint .= 'layout=' . $stickerSize;

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers

        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Send request to print declaration and get it here, ONLY for LP services when required CN23 declaration
     *
     * @param string $identId
     *
     * @return array
     */
    public static function printDeclaration($identId)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'documents/cart/' . $identId . '/cn23';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);
        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Send request to print manifest and get it here
     *
     * @param string $cartId
     *
     * @return array
     */
    public static function printManifest($cartId)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'documents/cart/' . $cartId . '/manifest';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Send request to print labels (can print more than one at once) and get it here
     *
     * @param string $id
     *
     * @return array
     */
    public static function printAll(array $ids, $stickerSize)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'documents/item/sticker/?';

        foreach ($ids as $id) {
            $endpoint .= 'itemId=' . $id . '&';
        }

        $endpoint .= 'layout=' . strtoupper($stickerSize);

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers

        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Get tracking info about formed order in LP API
     *
     * @param array $lpInternalIds
     *
     * @return array|string
     */
    public static function getShippingItemsTrackingInformation(array $lpInternalIds)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'tracking';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($lpInternalIds),
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }


    /**
     * Get post office data from LP API
     *
     * @param string $countryId
     * @param string $postalCode
     *
     * @return array
     */
    public static function getCountry($id)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . "address/country/$id";

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Get post office data from LP API
     *
     * @param string $countryId
     * @param string $postalCode
     *
     * @return array
     */
    public static function getCountryPostalCodePostOffice($countryId, $postalCode)
    {
        $instance = self::getInstance();
        $endpoint = $instance->baseUrl . self::API_VERSION . 'address/country/' . $countryId . '/postalcode/' . $postalCode . '/postoffice';

        $requestOptions = [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true
        ];

        $instance->setHeaders($instance->buildAuthHeader()); // sets default headers
        $instance->setOptions($requestOptions);

        $res = $instance->executeCallAndGetResult();
        if (!$instance->errorHandler->isRequestCompletedSuccessfully($res)) {
            return $res;
        }

        $result = json_decode($res, true);

        $instance->refreshCurl();

        return $result;
    }

    /**
     * Check if request does have success message false
     *
     * @return bool
     */
    public function isRequestCompletedSuccessfully($result)
    {
        if (is_array($result) && array_key_exists('success', $result) && $result['success'] == false) {
            return false;
        }

        return true;
    }
}
