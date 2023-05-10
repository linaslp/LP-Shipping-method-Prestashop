<?php

require_once(dirname(__FILE__) . './../api/types/AddressType.php');
require_once(dirname(__FILE__) . './../api/types/ReceiverType.php');
require_once(dirname(__FILE__) . './../api/types/SenderType.php');
require_once(dirname(__FILE__) . './../classes/LPShippingDocument.php');
require_once(dirname(__FILE__) . './../classes/LPShippingDocumentPart.php');
require_once(dirname(__FILE__) . './../classes/LPShippingConsts.php');

/**
 * LPShippingOrderService is for helper methods with order service
 */
class LPShippingOrderService
{
    const LABEL_NOT_CREATED_ERROR = 'Label has not been created yet';
    const LP_SERVICE = 1;
    const LP_EXPRESS_SERVICE = 2;
    const LT_COUNTRY_ID = 118;

    const ERROR_ZIP_CREATE = 'Failed to create ZIP file';
    const LP_ID_KEY_NAME = 'id_lp_internal_order';

    /**
     * @var string $baseDownloadPath path to downloaded files from LP API; with ending forward slash
     */
    private $baseDownloadPath;

    private $errors = [];
    private $moduleInstance = null;

    public function __construct()
    {
        $this->baseDownloadPath = _LPSHIPPING_ROOT_ . '/api/downloads/';

        if (!file_exists(_LPSHIPPING_ROOT_ . '/api/downloads')) {
            if (!mkdir($concurrentDirectory = _LPSHIPPING_ROOT_ . '/api/downloads') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $this->moduleInstance = Module::getInstanceByName('lpshipping');
    }


    /**
     * Returns AddressType object with filled address information
     *
     * @param array LPShippingOrder $order
     *
     * @return AddressType
     */
    public function formReceiverAddressType(array $order)
    {
        $psOrder = new Order($order['id_order']);
        $PSAddress = new Address($psOrder->id_address_delivery);
        $country = new Country($PSAddress->id_country);

        $address = new AddressType();
        $address->setCurrentAddressType($address::STRUCTURED_ADDRESS);
        $address->setCountry($country->iso_code);
        $address->setLocality($PSAddress->city);
        $address->setPostalCode($PSAddress->postcode);
        $address->setStreet(str_replace($this->formBuildingNumber($PSAddress->address1), '', $PSAddress->address1));
        $address->setBuilding($this->formBuildingNumber($PSAddress->address1));

        if ($country->iso_code != 'LT') {
            $address->setNotLithuanianAddress($PSAddress->address1);
            $address->setCurrentAddressType($address::NOT_LITHUANIAN_ADDRESS);
        }

        if ($order['selected_carrier'] == 'LP_SHIPPING_EXPRESS_CARRIER_POST' || $order['selected_carrier'] == 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST') {
            // data for post office id can be acquired from LP API
            $countryData = LPShippingRequest::getCountry(118);
            $countryId = $countryData['id'];

            if ($countryData) {
                $postOfficeData = LPShippingRequest::getCountryPostalCodePostOffice($countryId, $PSAddress->postcode);

                if ($postOfficeData) {
                    // set post office id and/or post office box id;
                    $address->setPostOfficeId($postOfficeData['id']);
                }
            }
        }

        return $address;
    }

    /**
     * Save order from user side to BE
     *
     * @param array orderData from views/js/front.js
     */
    public function saveOrder(Cart $cart)
    {
        $lpOrderService = new LPShippingOrderService();
        $carrierInfo = $this->getSelectedCarrier($cart->id_carrier);
        $selectedCarrier = $carrierInfo['configuration_name'];
        $senderAddress = $lpOrderService->formSenderAddressType();
        if ($carrierInfo) {
            /* Create LPShippingOrder object */
            $lpOrderArray = LPShippingOrder::getOrderByCartId($cart->id);

            if (is_array($lpOrderArray) && !empty($lpOrderArray)) {
                $lpOrder = new LPShippingOrder($lpOrderArray['id_lpshipping_order']);

                if (Validate::isLoadedObject($lpOrder)) {
                    $lpOrder->selected_carrier = $carrierInfo['configuration_name'];
                    $lpOrder->weight = $cart->getTotalWeight();
                    $lpOrder->cod_available = $lpOrderService->isCodAvailable($cart->id);
                    $lpOrder->cod_amount = $cart->getOrderTotal();
                    $lpOrder->post_address = $this->validateAndReturnFormedAddress($cart->id);
                    $lpOrder->sender_locality = $senderAddress->getLocality();
                    $lpOrder->sender_street = $senderAddress->getStreet();
                    $lpOrder->sender_building = $senderAddress->getBuilding();
                    $lpOrder->sender_postal_code = $senderAddress->getPostalCode();
                    $lpOrder->sender_country = $senderAddress->getCountry();
                    $lpOrder->shipping_template_id = $lpOrderService->getDefaultTemplate($selectedCarrier);

                    $lpOrder->update();
                }

            } else {
                $lpOrder = new LPShippingOrder();
                $numberOfPackages = $cart->getNbOfPackages();
                $lpOrder->id_cart = $cart->id;
                $lpOrder->selected_carrier = $selectedCarrier;
                $lpOrder->weight = $cart->getTotalWeight();
                $lpOrder->number_of_packages = $numberOfPackages != false ? $numberOfPackages : 1;
                $lpOrder->cod_available = $lpOrderService->isCodAvailable($cart->id);
                $lpOrder->cod_amount = $cart->getOrderTotal();
                $lpOrder->status = LPShippingOrder::ORDER_STATUS_NOT_SAVED;
                $lpOrder->post_address = $this->validateAndReturnFormedAddress($cart->id);
                $lpOrder->sender_locality = $senderAddress->getLocality();
                $lpOrder->sender_street = $senderAddress->getStreet();
                $lpOrder->sender_building = $senderAddress->getBuilding();
                $lpOrder->sender_postal_code = $senderAddress->getPostalCode();
                $lpOrder->sender_country = $senderAddress->getCountry();
                $lpOrder->shipping_template_id = $lpOrderService->getDefaultTemplate($selectedCarrier);

                $lpOrder->save();
            }

            $declarationData = [
                LPShippingDocument::PARENT_KEY => LPShippingOrder::getOrderByCartId($cart->id)[LPShippingDocument::PARENT_KEY],
                'parcel_type' => 'SELL',
                'parcel_notes' => 'Sell items',
                'parcel_description' => '',
                'cn_parts_amount' => $cart->getOrderTotal(),
                'cn_parts_country_code' => 'LT',
                'cn_parts_currency_code' => 'EUR',
                'cn_parts_weight' => $cart->getTotalWeight(),
                'cn_parts_quantity' => $cart->getNbProducts($cart->id),
                'cn_parts_summary' => $cart->getProducts()[0]['legend']
            ];
            $lpOrderService->updateShippingItemWithDeclaration($declarationData);

            return true;
        }

        return false;
    }

    /**
     * Creates ReceiverType object and sets required information for LP API request variable
     *
     * @param array LPShippingOrder $order
     *
     * @return ReceiverType
     */
    public function formReceiverType(array $order)
    {
        $PSOrder = new Order($order['id_order']);
        $PSAddress = new Address($PSOrder->id_address_delivery);
        $customer = new Customer($PSOrder->id_customer);

        if (!empty(trim($PSAddress->phone_mobile))) {
            $phone = $PSAddress->phone_mobile;
        } else {
            $phone = $PSAddress->phone;
        }

        $receiver = new ReceiverType();
        $receiver->setAddress($this->formReceiverAddressType($order));
        $receiver->setCompanyName($PSAddress->company);
        $receiver->setName(trim($PSAddress->firstname . ' ' . $PSAddress->lastname));
        $receiver->setPhone($this->formatPhoneNumber($phone));
        $receiver->setEmail($customer->email);
        $receiver->setPostalCode($PSAddress->postcode);

        if ($order['selected_carrier'] == 'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL') {
            $terminal = LPShippingTerminal::getTerminalById($order['id_lpexpress_terminal']);
            $receiver->setTerminalId($terminal['terminal_id']);
        } elseif ($order['selected_carrier'] == 'LP_SHIPPING_EXPRESS_CARRIER_POST' || $order['selected_carrier'] == 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST') {
            $receiver->setNeedOfficeParam(true);
        }

        return $receiver;
    }


    /**
     * Form AddressType for SenderType object
     *
     * @param array LPShippingOrder $order
     *
     * @return AddressType
     */
    public function formSenderAddressType()
    {
        $address = new AddressType();
        $address->setCurrentAddressType($address::STRUCTURED_ADDRESS);
        $address->setCountry(Configuration::get('LP_SHIPPING_COUNTRY_CODE'));
        $address->setLocality(Configuration::get('LP_SHIPPING_CITY'));
        $address->setStreet(Configuration::get('LP_SHIPPING_STREET'));
        $address->setBuilding(Configuration::get('LP_SHIPPING_BUILDING_NUMBER'));
        $address->setPostalCode(Configuration::get('LP_SHIPPING_POST_CODE'));

        return $address;
    }

    /**
     * Form AddressType for SenderType object
     *
     * @param array LPShippingOrder $order
     *
     * @return AddressType
     */
    public function formSenderAddressTypeFromOrder(array $order)
    {
        $address = new AddressType();
        $address->setCurrentAddressType($address::STRUCTURED_ADDRESS);
        $address->setCountry($order['sender_country']);
        $address->setLocality($order['sender_locality']);
        $address->setStreet($order['sender_street']);
        $address->setBuilding($order['sender_building']);
        $address->setPostalCode($order['sender_postal_code']);

        return $address;
    }

    /**
     * Form SenderType for LP API request
     *
     * @param array LPShippingOrder $order
     *
     * @return SenderType
     */
    public function formSenderType()
    {
        $sender = new SenderType();
        $sender->setAddress($this->formSenderAddressType());
        $sender->setEmail(Configuration::get('LP_SHIPPING_COMPANY_EMAIL'));
        $sender->setName(Configuration::get('LP_SHIPPING_COMPANY_NAME'));
        $sender->setPhone('370' . Configuration::get('LP_SHIPPING_COMPANY_PHONE'));

        return $sender;
    }

    /**
     * Form SenderType for LP API request
     *
     * @param array LPShippingOrder $order
     *
     * @return SenderType
     */
    public function formSenderTypeFromOrder(array $order)
    {
        $sender = new SenderType();
        $sender->setAddress($this->formSenderAddressTypeFromOrder($order));
        $sender->setEmail(Configuration::get('LP_SHIPPING_COMPANY_EMAIL'));
        $sender->setName(Configuration::get('LP_SHIPPING_COMPANY_NAME'));
        $sender->setPhone('370' . Configuration::get('LP_SHIPPING_COMPANY_PHONE'));

        return $sender;
    }

    /**
     * Format a phone number to fit LP API requirements
     *
     * @param string $number
     *
     * @return string
     */
    private function formatPhoneNumber($number)
    {
        $formattedNumber = '370';
        if (substr($number, 0, 1) === '8') {
            $formattedNumber = $formattedNumber . substr($number, 1, strlen($number) - 1);
        } elseif (substr($number, 0, 1) === '6') {
            $formattedNumber = $formattedNumber . $number;
        } elseif (substr($number, 0, 1) === '+') {
            $formattedNumber = substr($number, 1, strlen($number) - 1);
        } else {
            return $number;
        }

        return $formattedNumber;
    }


    /**
     * Format a building number to fit LP API requirements
     *
     * @param string $number
     *
     * @return string
     */
    public function formBuildingNumber($address)
    {
        $position = 0;

        // check if building number is at the start of address
        if (is_numeric($address[0])) {
            for ($i = 1, $iMax = strlen($address); $i < $iMax; $i++) {
                if ($address[$i] == ' ' || $address[$i] == ',' || $address[$i] == ';') {
                    $position = $i;

                    return substr($address, 0, $position - 1);
                }
            }
        }

        // find building number at the end of address
        for ($i = 0, $iMax = strlen($address); $i < $iMax; $i++) {
            if (is_numeric($address[$i])) {
                $position = $i;
                break;
            }
        }

        return substr($address, $position, strlen($address) - $position);
    }


    /**
     * Form user address array for LP API request
     *
     * @param Address address
     *
     * @return array
     */
    public function formAddress(Address $address)
    {
        $country = new Country($address->id_country);

        $data['country'] = self::LT_COUNTRY_ID;
        $data['locality'] = $address->city;
        $data['postalCode'] = $address->postcode;
        $data['street'] = $address->address1;

        return $data;
    }


    /**
     * Associates carriers with current selected order configuration delivery type settings,
     * adds additional array 'templates' to one of each of passed carriers['default_shipping_templates'] array
     *
     * @param array $carriers
     *
     * @return array
     */
    public function addTemplatesToCarriers(array $carriers)
    {
        $data = [];
        $terminalType = Configuration::get('LP_SHIPPING_ORDER_TERMINAL_TYPE');
        $addressType = Configuration::get('LP_SHIPPING_ORDER_HOME_TYPE');
        $postType = Configuration::get('LP_SHIPPING_ORDER_POST_TYPE');
        $postTypeForeign = Configuration::get('LP_SHIPPING_ORDER_POST_TYPE_FOREIGN');

        foreach ($carriers as $carrier) {
            $temp = $carrier;

            foreach ($carrier['default_shipping_templates'] as $template) {
                if ($template['name'] == $terminalType || $template['name'] == $addressType || $template['name'] == $postType) {
                    $temp['templates'] = LPShippingItemTemplate::getShippingTemplatesByType($template['name']);

                    break;
                }

                if ($template['name'] == 'LP_ABROAD') {
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplateByTypeAndSize(
                        LpShippingConsts::SMALL_CORRESPONDENCE,
                        LpShippingConsts::SMALL_CORRESPONDENCE_SIZE_NAME
                    );
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplateByTypeAndSize(
                        LpShippingConsts::MEDIUM_CORRESPONDENCE,
                        LpShippingConsts::MEDIUM_CORRESPONDENCE_SIZE_NAME
                    );

                    break;
                }

                if ($template['name'] == 'LP_DEFAULT') {
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplateByTypeAndSize(
                        LpShippingConsts::SMALL_CORRESPONDENCE
                    );
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplateByTypeAndSize(
                        LpShippingConsts::MEDIUM_CORRESPONDENCE
                    );
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplateByTypeAndSize(
                        LpShippingConsts::PARCEL
                    );

                    break;
                }

                if ($template['name'] == 'AB') {
                    $temp['templates'][] = LPShippingItemTemplate::getShippingTemplatesByType('AB')[0];
                }
            }

            $data[] = $temp;
        }

        return $data;
    }


    /**
     * Try to retrieve order tied with LP carrier from database
     *
     * @param string $id
     *
     * @return null|array
     */
    public function getOrder($id)
    {
        $order = LPShippingOrder::getOrderById((int)$id);

        if ($order) {
            return $order;
        }

        return null;
    }


    /**
     * Try to retrieve order tied with LP carrier from database
     *
     * @param string $id
     *
     * @return null|array
     */
    public function getOrderByCartId($cartId)
    {
        $order = LPShippingOrder::getOrderByCartId((int)$cartId);

        if ($order) {
            return $order;
        }

        return null;
    }

    /**
     * Check if order is initiated by order status
     *
     * @param string $orderStatus
     *
     * @return bool
     */
    public function isShipmentFormed($orderStatus)
    {
        if (
            $orderStatus == LPShippingOrder::ORDER_STATUS_COURIER_CALLED ||
            $orderStatus == LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED ||
            $orderStatus == LPShippingOrder::ORDER_STATUS_FORMED
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if order is saved
     *
     * @param string $orderStatus
     *
     * @return bool
     */
    public function isOrderSaved($orderStatus)
    {
        return $orderStatus != LPShippingOrder::ORDER_STATUS_NOT_SAVED;
    }

    /**
     * Checks if cod is available, which is only in Lithuania
     */
    public function isCodAvailable($cartId)
    {
        $cart = new Cart($cartId);

        if (Validate::isLoadedObject($cart)) {
            $address = new Address($cart->id_address_delivery);
            $country = new Country($address->id_country);

            if ($country->iso_code == 'LT') {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if user is available to call courier
     *
     * @param array $order LPShippingOrder
     *
     * @return bool
     */
    public function isCallCourierAvailable(array $order)
    {
        // @TODO LPShipping check if LP user does have right to call courier LATER

        // does have service to call courier; does this delivery type have ability to courier; is order formed and courier not called already
        if (
            (!Configuration::get('LP_SHIPPING_CALL_COURIER_ACTIVE')) &&
            $this->isCourier($order) &&
            ($order['status'] == LPShippingOrder::ORDER_STATUS_FORMED ||
                $order['status'] == LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is available to call courier
     *
     * @param array $order LPShippingOrder
     *
     * @return bool
     */
    public function isCallCourierAvailableAfterShipmentInit(array $order)
    {
        if (!Configuration::get('LP_SHIPPING_CALL_COURIER_ACTIVE') && $this->isCourier($order)) {
            return true;
        }

        return false;
    }


    /**
     * Is selected carrier shipment delivered by courier
     *
     * @param array $order LPShippingOrder
     *
     * @return bool
     */
    public function isCourier(array $order)
    {
        //        $availableTemplatesIds = [
        //            45, 46, 54, 55, 56, 57, 58
        //        ];

        $availableCarriers = [
            'LP_SHIPPING_EXPRESS_CARRIER_HOME',
            'LP_SHIPPING_EXPRESS_CARRIER_ABROAD',
            'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL',
            'LP_SHIPPING_EXPRESS_CARRIER_POST'
        ];

        if (
            in_array($order['selected_carrier'], $availableCarriers)
            //            && in_array($order['shipping_template_id'], $availableTemplatesIds)
            && $order['status'] !== LPShippingOrder::ORDER_STATUS_COMPLETED
        ) {
            return true;
        }

        return false;
    }


    /**
     * Check if shipment can be cancelled after initiation
     *
     * @param array $order LPShippingOrder
     *
     * @return bool
     */
    public function isFormedShipmentCancellable(array $order)
    {
        $availableCarriers = [
            'LP_SHIPPING_EXPRESS_CARRIER_HOME',
            'LP_SHIPPING_EXPRESS_CARRIER_ABROAD',
            'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL',
            'LP_SHIPPING_EXPRESS_CARRIER_POST'
        ];

        if ($this->isShipmentFormed($order['status']) && (in_array($order['selected_carrier'], $availableCarriers))) {
            return true;
        }

        return false;
    }


    /**
     * Get order service type, either LP Service or LP Express Service
     *
     * @param array $orderData
     *
     * @return LP_SERVICE|LP_EXPRESS_SERVICE
     */
    public function getOrderServiceType($orderData)
    {
        $availableExpressCarriers = [
            'LP_SHIPPING_EXPRESS_CARRIER_HOME',
            'LP_SHIPPING_EXPRESS_CARRIER_ABROAD',
            'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL',
            'LP_SHIPPING_EXPRESS_CARRIER_POST'
        ];

        if (in_array($orderData['selected_carrier'], $availableExpressCarriers)) {
            return self::LP_EXPRESS_SERVICE;
        } else {
            return self::LP_SERVICE;
        }
    }


    /**
     * Check if shipping item id exists in order and is not empty which is created after successful call to create shipping item in LP API
     *
     * @param $orderData
     *
     * @return bool
     */
    public function lpItemIdExists($orderData)
    {
        if (is_array($orderData) && key_exists('id_lp_internal_order', $orderData) && !empty(trim($orderData['id_lp_internal_order']))) {
            return true;
        }

        false;
    }

    /**
     * Checks if documents can be printed
     *
     * @param array $order
     *
     * @return bool
     */
    public function canPrintDocuments(array $order)
    {
        if (($order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_CALLED ||
                $order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED ||
                $order['status'] === LPShippingOrder::ORDER_STATUS_FORMED) &&
            $this->isShipmentReady($order)
        ) {
            return true;
        }

        return false;
    }


    /**
     * Checks if documents can be printed
     *
     * @param array $order
     *
     * @return bool
     */
    public function canPrintLabel(array $order)
    {
        // if order is saved we can check status from LP API
        return $this->isShipmentReady($order);
    }

    /**
     * Checks if shipment is ready
     *
     * @param array $order
     *
     * @return bool
     */
    public function isShipmentReady(array $order)
    {
        if ($this->lpItemIdExists($order)) {
            $item = LPShippingRequest::getShippingItem($order['id_lp_internal_order']);

            if (is_array($item) && isset($item['status'])) {
                if ($item['status'] === 'PENDING') {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Checks if documents can be printed (ONLY FOR LP SERVICES WHICH NEEDS cn23 DECLARATION)
     *
     * @param array $orderData
     *
     * @return bool
     */
    public function canPrintDeclaration(array $orderData)
    {
        if ($this->getOrderServiceType($orderData) === self::LP_SERVICE) {
            $orderOptions = $this->getShipmentItem($orderData['id_order']);

            if (is_array($orderOptions)) {
                if (key_exists('documents', $orderOptions)) {
                    foreach ($orderOptions['documents'] as $key => $doc) {
                        if ($key == 'cn23Form') {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function isDeclarationCN22Required($templateId, $isoCode, $total)
    {
        if (in_array($templateId, LpShippingConsts::LP_SHIPMENT_CN_TEMPLATES)) {
            if (!self::inEu($isoCode)) {
                return $total < LpShippingConsts::CN22_THRESHOLD;
            }
        }

        return false;
    }

    public function isDeclarationCN23Required($templateId, $isoCode, $total)
    {
        if ($templateId == LpShippingConsts::LP_SHIPPING_CN_ALWAYS) {
            return true;
        }

        if (in_array($templateId, LpShippingConsts::LP_SHIPMENT_CN_TEMPLATES)) {
            if (self::inEu($isoCode)) {
                return false;
            }

            return $total > LpShippingConsts::CN22_THRESHOLD;
        }

        if (
            $templateId == LpShippingConsts::LP_SHIPPING_PARCEL_FROM_HOME ||
            in_array($templateId, LpShippingConsts::LP_SHIPPING_CHCA_TEMPLATES)
        ) {
            return !self::inEu($isoCode);
        }

        return false;
    }

    private static function inEu($countryCode)
    {
        return in_array($countryCode, LpShippingConsts::EU_COUNTRIES);
    }

    /**
     * Checks if documents can be printed
     *
     * @param array $order
     *
     * @return bool
     */
    public function canPrintManifest(array $order)
    {
        if ($this->isCourier($order) && $order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_CALLED) {
            return true;
        }

        return false;
    }


    /**
     * Get Shipment Data from LP API
     *
     * @param string $id
     *
     * @return array|null
     */
    public function getShipmentItem($id)
    {
        $order = LPShippingOrder::getOrderById($id);

        if ($this->lpItemIdExists($order)) {
            $result = LPShippingRequest::getShippingItem($order['id_lp_internal_order']);

            if ($result) {
                return $result;
            }
        }

        return null;
    }


    /**
     * Get Shipping item options from LP API
     *
     * @param string $id
     *
     * @return array|null
     */
    public function getShipmentItemOptions($id)
    {
        $order = LPShippingOrder::getOrderById($id);
        if ($this->lpItemIdExists($order)) {
            $orderOptions = LPShippingRequest::getShippingItemOptions($order['id_lp_internal_order']);

            if ($orderOptions) {
                return $orderOptions;
            }
        }

        return null;
    }


    /**
     * Save LPShipping order information
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function saveLPShippingOrder(array $orderData)
    {
        // set weight to 1 if selected carrier type is to post office
        if (
            ($orderData['selected_carrier'] == 'LP_SHIPPING_CARRIER_ABROAD' ||
                $orderData['selected_carrier'] == 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST') &&
            (float)$orderData['weight'] < 0.01
        ) {
            $orderData['weight'] = 0.01;
        }

        $createdItem = LPShippingRequest::updateShippingItem($orderData);
        if (!$this->isRequestSuccessful($createdItem)) {
            return $createdItem;
        }

        if (is_array($createdItem)) {
            if (key_exists('id', $createdItem)) {
                $orderData['id_lp_internal_order'] = $createdItem['id'];
            }

            if (key_exists('status', $createdItem)) {
                $orderData['parcel_status'] = $createdItem['status'];
            }

            $orderData['status'] = LPShippingOrder::ORDER_STATUS_SAVED;
            LPShippingOrder::updateOrder($orderData);
        }

        return true;
    }

    /**
     * Remove LPShippingOrder order information
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function removeLPShippingOrder(array $orderData)
    {
        $order = LPShippingOrder::getOrderById($orderData['id_order']);
        if ($this->lpItemIdExists($order)) {
            LPShippingRequest::deleteShippingItem($order['id_lp_internal_order']);
        }

        LPShippingOrder::removeOrder($order);
    }


    /**
     * Initiate shipping process in LP API
     *
     * @param array $orderData
     *
     * @return null|true
     */
    public function formShipments(array $orderData)
    {
        $order = LPShippingOrder::getOrderById($orderData['id_order']);
        if ($this->lpItemIdExists($order)) {
            $order['is_declaration_cn22_required'] = $orderData['is_declaration_cn22_required'];
            $order['is_declaration_cn23_required'] = $orderData['is_declaration_cn23_required'];
            // add additional required data
            $this->updateShippingItems([$order]);

            // returned format is ["id_cart_internal_order"]
            $identId = LPShippingRequest::initiateShippingItem([$order['id_lp_internal_order']]);
            if (!$this->isRequestSuccessful($identId)) {
                return null;
            }

            $order['id_cart_internal_order'] = $identId[0];

            // make another call to get data after initiation and update label number with barcode
            $initiatedItemData = LPShippingRequest::getShippingItem($order['id_lp_internal_order']);
            if ($initiatedItemData) {
                $psOrder = new Order($orderData['id_order']);
                $orderCarrier = new OrderCarrier($psOrder->getIdOrderCarrier());
                $orderCarrier->tracking_number = $initiatedItemData['barcode'];
                $orderCarrier->update();
                $order['label_number'] = $initiatedItemData['barcode'];
                $order['parcel_status'] = $initiatedItemData['status'];
            }

            // if order doesn't belong to courier process
            if ($this->isCourier($order)) {
                if (!$this->isCallCourierAvailableAfterShipmentInit($order)) {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_COURIER_CALLED;
                } else {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED;
                }
            } else {
                // if formed successfully
                if (is_numeric($order['id_cart_internal_order'])) {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_FORMED;
                } else { // if formed unsuccessfully
                    $order['status'] = LPShippingOrder::ORDER_STATUS_NOT_FORMED;
                }
            }

            return LPShippingOrder::updateOrder($order);
        } else {
            return null;
        }
    }

    /**
     * Initiate shipping process in LP API, it differs from one item initialization because there can be many items initialized at once
     *
     * @param array $orderIds
     *
     * @return array|true|string
     */
    public function formShipmentsBulk(array $orders)
    {
        $autoCourierCall = Configuration::get('LP_SHIPPING_CALL_COURIER_ACTIVE');

        $missingInternalIds = [];
        $ordersInternalIds = array_column($orders, 'id_lp_internal_order');

        if (count($missingInternalIds) > 0) {
            return $missingInternalIds;
        }

        $this->updateShippingItems($orders);
        $identId = LPShippingRequest::initiateShippingItem($ordersInternalIds);

        if (!$identId || !$this->isRequestSuccessful($identId)) {
            return null;
        }

        // check if order initialization is fine
        if (!is_numeric($identId[0])) {
            return $identId[0];
        }

        $initiatedItemsTracking = LPShippingRequest::getShippingItemsTrackingInformation($ordersInternalIds);
        $trackingGroupedByLpId = $this->groupBy($initiatedItemsTracking, 'id');
        unset($initiatedItemsTracking);

        $chunkSize = 0;
        $currentChunk = 0;
        foreach ($orders as $order) {
            if (!(LpShippingConsts::LP_SHIPPING_BULK_INIT_CHUNK_SIZE - $chunkSize)) {
                $currentChunk += 1;
                $chunkSize = 0;
            }
            $chunkSize += 1;

            $order['id_cart_internal_order'] = $identId[$currentChunk];

            $initiatedItemData = $trackingGroupedByLpId[$order[self::LP_ID_KEY_NAME]];
            if ($initiatedItemData) {
                $psOrder = new Order($order['id_order']);
                $orderCarrier = new OrderCarrier($psOrder->getIdOrderCarrier());
                $orderCarrier->tracking_number = $initiatedItemData['barcode'] ?: null;
                $orderCarrier->update();
                $order['label_number'] = $initiatedItemData['barcode'] ?: null;
                $order['parcel_status'] = $initiatedItemData['state'];
            }
            // if order doesn't belong to courier process
            if ($this->isCourier($order)) {
                if ($autoCourierCall) {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_COURIER_CALLED;
                } else {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED;
                }
            } else {
                // if formed successfully
                if (is_numeric($order['id_cart_internal_order'])) {
                    $order['status'] = LPShippingOrder::ORDER_STATUS_FORMED;
                } else { // if formed unsuccessfully
                    $order['status'] = LPShippingOrder::ORDER_STATUS_NOT_FORMED;
                }
            }

            LPShippingOrder::updateOrder($order);
        }

        return true;
    }

    public function groupBy(array $array, string $key)
    {
        $newArray = [];

        foreach($array as $item) {
            $newArray[$item[$key]] = $item;
        }

        return $newArray;
    }

    /**
     * Initiate shipping process in LP API, it differs from one item initialization because there can be many items initialized at once
     *
     * @param array $orderIds
     *
     * @return array
     */
    public function saveShipmentsBulk(array $orders)
    {
        $messages = [];

        foreach ($orders as $lpOrder) {
            $response = $this->saveLPShippingOrder($lpOrder);

            if (isset($response['message'])) {
                $message = json_decode($response['message']);
                $errors = $message->valueValidationErrors[0] ?: $message[0];

                if (is_string($errors)) {
                    $messages[] = sprintf('Order %s failed: %s', $lpOrder['id_order'], $errors);

                    continue;
                }

                $messages[] = sprintf(
                    'Order %s failed: %s, %s',
                    $lpOrder['id_order'],
                    $errors->field,
                    $errors->message
                );
            }
        }

        return $messages;
    }

    public function collectShippmentData($id)
    {
        $query = new DbQuery();
        $query->select('*')->from('lpshipping_order');
        $query->where('id_order = ' . (int)$id);

        return DB::getInstance()->getRow($query);
    }

    /**
     * Update shipping item by adding required documents and services
     *
     * @param array $orders
     *
     * Returned pseudo data
     * "availableServices": [
     * {
     *  "id": 2,
     *  "title": "Pirmenybinis siuntimas",
     *  "description": "Siunta, kuri žymima specialiu \"Prioritaire/Pirmenybinė\" ženklu ir yra pristatoma pirmumo tvarka.",
     *  "summary": "Siunta pažymėta ženklu „Prioritaire / Pirmenybinė“ ir gavėją pasiekia greičiau.",
     *  "price": {
     *        "amount": 0.410,
     *         "vat": 0.0000,
     *         "currency": "EUR"
     *  }
     *  }
     *  ],
     *  "itemId": "3591"
     */
    public function updateShippingItems(array $orders)
    {
        // check if needed additional services and update shipping item
        $shipmentPriority = Configuration::get('LP_SHIPPING_SHIPMENT_PRIORITY');
        $shipmentRegistered = Configuration::get('LP_SHIPPING_SHIPMENT_REGISTERED');
        $servicesToAddIds = [];

        foreach ($orders as $orderData) {
            $orderOptions = $this->getShipmentItemOptions($orderData['id_order']);

            // add priority and registered shipment service (depends on config)
            if (is_array($orderOptions) && key_exists('availableServices', $orderOptions)) {
                foreach ($orderOptions['availableServices'] as $option) {
                    // check for priority sending
                    if ($shipmentPriority && mb_stripos($option['title'], "pirmenyb", 0, 'UTF-8') !== false) {
                        $servicesToAddIds[] = ["id" => (int)$option['id']];
                    }

                    if ($shipmentRegistered && $option['id'] == '1') { // registered shipment additional service id
                        $servicesToAddIds[] = ["id" => (int)$option['id']];
                    }

                    // check for COD
                    $isCod = $orderData['cod_selected'];
                    if ($isCod && mb_stripos($option['title'], "išperkamoji", 0, 'UTF-8') !== false) {
                        $servicesToAddIds[] = [
                            "id" => (int)$option['id'],
                            "amount" => (float)$orderData['cod_amount'],
                        ];
                    }
                }
            }

            if (count($servicesToAddIds) > 0) {
                $orderData['additionalServices'] = $servicesToAddIds;

                LPShippingRequest::updateShippingItem($orderData);
            }

            $servicesToAddIds = []; // clear previous array for other orders to check
        }
    }

    public function updateShippingItemWithDeclaration(array $orderData)
    {
        $lpOrderId = (int)$orderData[LPShippingDocument::PARENT_KEY];
        $declarationData = [
            LPShippingDocument::PARENT_KEY => $lpOrderId,
            'parcel_type' => $orderData['parcel_type'],
            'notes' => $orderData['parcel_notes'],
            'description' => $orderData['parcel_description']
        ];

        LPShippingDocument::updateEntity($declarationData);

        $document = LPShippingDocument::getByParentId($lpOrderId);

        $cnPart = [
            LPShippingDocumentPart::PARENT_KEY => (int)$document[LPShippingDocument::PRIMARY_KEY],
            'amount' => (int)$orderData['cn_parts_amount'],
            'country_code' => $orderData['cn_parts_country_code'],
            'currency_code' => $orderData['cn_parts_currency_code'],
            'weight' => (float)$orderData['cn_parts_weight'],
            'quantity' => (int)$orderData['cn_parts_quantity'],
            'summary' => $orderData['cn_parts_summary']
        ];

        LPShippingDocumentPart::updateEntity($cnPart);
    }

    public function updateSenderAddress(array $orderData)
    {
        $lpOrder = new LPShippingOrder($orderData[LPShippingDocument::PARENT_KEY]);
        $lpOrder->sender_locality = $orderData['sender_locality'];
        $lpOrder->sender_street = $orderData['sender_street'];
        $lpOrder->sender_building = $orderData['sender_building'];
        $lpOrder->sender_postal_code = $orderData['sender_postal_code'];
        $lpOrder->sender_country = $orderData['sender_country'];

        $lpOrder->update();
    }

    /**
     * Cancel initiated shipment in LP API
     *
     * @param string $orderId
     */
    public function cancelInitiatedShipping($orderId)
    {
        $order = LPShippingOrder::getOrderById($orderId);
        if ($this->lpItemIdExists($order)) {
            LPShippingRequest::deleteShippingItem($order['id_lp_internal_order']);

            $order['id_cart_internal_order'] = '';
            $order['id_lp_internal_order'] = '';
            $order['status'] = LPShippingOrder::ORDER_STATUS_NOT_SAVED;
            LPShippingOrder::updateOrder($order);
        }
    }

    /**
     * Cancel initiated shipment in LP API
     *
     * @param array $orderIds
     */
    public function cancelInitiatedShippingBulk(array $ids)
    {
        foreach ($ids as $id) {
            $this->cancelInitiatedShipping($id);
        }
    }

    /**
     * Call courier process in LP API
     *
     * @param array $orderData |null
     */
    public function callCourier(array $orderData)
    {
        $order = LPShippingOrder::getOrderById($orderData['id_order']);
        if ($this->lpItemIdExists($order)) {
            // returned format is ["id_cart_internal_order"]
            $result = LPShippingRequest::callCourier($order['id_lp_internal_order']);
            if (!$this->isRequestSuccessful($result)) {
                return $result['message'];
            }

            if (isset($result[0])) {
                $result = $result[0];
            }
            // get barcode and write it in DB
            if (is_array($result) && key_exists('manifestId', $result)) {
                $order['id_manifest'] = $result['manifestId'];
                $order['status'] = LPShippingOrder::ORDER_STATUS_COURIER_CALLED;
                LPShippingOrder::updateOrder($order);
            }
        }

        return null;
    }

    /**
     * Call courier bulk process in LP API
     *
     * @param array $ids
     */
    public function callCourierBulk(array $ids)
    {
        foreach ($ids as $id) {
            $order = LPShippingOrder::getOrderById($id);
            $responseStr = $this->callCourier($order);
            if ($responseStr) {
                return $responseStr;
            }
        }
    }


    /**
     * Print all documents
     *
     * @param array $orderData
     *
     * @return readfile>download>exit|null
     */
    public function printDocuments(array $orderData)
    {
        $filePath = $this->downloadDocuments($orderData);
        $zipName = basename($filePath);

        if (file_exists($this->baseDownloadPath . $filePath)) {
            $this->downloadFile($this->baseDownloadPath, $zipName, 'application/zip');
        }

        return null;
    }


    /**
     * Print all documents
     *
     * @param array $orderData
     *
     * @return string|null
     */
    public function downloadDocuments(array $orderData)
    {
        $zip = new ZipArchive();
        $zipName = $orderData['id_order'] . '_order_documents_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== TRUE) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        $orderData = LPShippingOrder::getOrderById($orderData['id_order']);

        $isReady = $this->isShipmentReady($orderData);
        if ($isReady) {
            $labelsFileName = $this->downloadLabels([$orderData['id_order']]);

            if (!empty($labelsFileName) && is_string($labelsFileName)) {
                $zip->addFile($this->baseDownloadPath . $labelsFileName, 'documents/' . $labelsFileName);
            }
        }

        if ($this->canPrintDeclaration($orderData)) {
            $declarationFileName = $this->downloadDeclaration($orderData['id_order']);

            if (!empty($declarationFileName && is_string($declarationFileName))) {
                $zip->addFile($this->baseDownloadPath . $declarationFileName, 'documents/' . $declarationFileName);
            }
        }

        if ($this->canPrintManifest($orderData)) {
            $manifestFileName = $this->downloadManifest($orderData['id_order']);

            if (!empty($manifestFileName) && is_string($manifestFileName)) {
                $zip->addFile($this->baseDownloadPath . $manifestFileName, 'documents/' . $manifestFileName);
            }
        }

        if ($zip->numFiles > 0) {
            $zip->close();
        } else {
            if (file_exists($this->baseDownloadPath . $zipName)) {
                unlink($this->baseDownloadPath . $zipName);
            }
        }

        if (file_exists($this->baseDownloadPath . $zipName)) {
            return $zipName;
        }

        return null;
    }

    /**
     * Print documents bulk
     *
     * @param array $ids
     *
     * @return readfile>download>exit|null
     */
    public function printDocumentsBulk(array $ids)
    {
        $zip = new ZipArchive();
        $zipName = 'bulk_documents_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== TRUE) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        foreach ($ids as $id) {
            $orderData = LPShippingOrder::getOrderById($id);
            $filePath = $this->downloadDocuments($orderData);

            if ($filePath) {
                $zip->addFile($this->baseDownloadPath . $filePath, 'documents/' . $filePath);
            }
        }

        $zip->close();

        $this->downloadFile($this->baseDownloadPath, $zipName, 'application/zip');
    }


    /**
     * Print labels if they are present in order, if not return order ids which does not have label
     *
     * @param array $orderIds
     *
     * @return readfile>download>exit|null
     */
    public function printLabels(array $orderIds)
    {
        $fileName = $this->downloadLabels($orderIds);

        if (!empty(trim($fileName))) {
            $mimeType = '';

            if (stripos($fileName, 'zip') !== false) {
                $mimeType = 'application/zip';
            } elseif (stripos($fileName, 'pdf') !== false) {
                $mimeType = 'application/pdf';
            }

            $this->downloadFile($this->baseDownloadPath, $fileName, $mimeType);
        }
    }

    /**
     * Print labels if they are present in order, if not return order ids which does not have label
     *
     * @param array $orderIds
     *
     * @return readfile>download>exit|null
     */
    public function printAll($orderId)
    {
        $fileName = $this->downloadAll($orderId);

        if (!empty(trim($fileName))) {
            $mimeType = '';

            if (stripos($fileName, 'zip') !== false) {
                $mimeType = 'application/zip';
            } elseif (stripos($fileName, 'pdf') !== false) {
                $mimeType = 'application/pdf';
            }

            $this->downloadFile($this->baseDownloadPath, $fileName, $mimeType);
        }
    }


    /**
     * Print declaration, ONLY for LP Service which requires CN23 declaration
     *
     * @param array $orderData
     *
     * @return readfile>download>exit|null
     */
    public function printDeclaration($orderId)
    {
        $fileName = $this->downloadDeclaration($orderId);
        if (!empty(trim($fileName))) {
            $this->downloadFile($this->baseDownloadPath, $fileName, 'application/pdf');
        }
    }


    /**
     * Print manifest (printable for couriers delivery type only)
     *
     * @param array $orderData
     *
     * @return readfile>download>exit|null
     */
    public function printManifest($orderId)
    {
        $fileName = $this->downloadManifest($orderId);
        if (!empty(trim($fileName))) {
            $this->downloadFile($this->baseDownloadPath, $fileName, 'application/pdf');
        }
    }

    /**
     * Print a bunch of declarations by order id's
     *
     * @param array $ids
     *
     * @return readfile>download>exit|null
     */
    public function printDeclarationBulk(array $ids)
    {
        $zip = new ZipArchive();
        $zipName = 'declarations_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE !== true)) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        foreach ($ids as $id) {
            $fileName = $this->downloadDeclaration($id);
            if (!empty(trim($fileName))) {
                $zip->addFile($this->baseDownloadPath . $fileName, 'declarations/' . $fileName);
            }
        }

        $zip->close();

        $this->downloadFile($this->baseDownloadPath, $zipName, 'application/zip');
    }

    /**
     * Print a bunch of manifests by order id's
     *
     * @param array $ids
     *
     * @return readfile>download>exit|null
     */
    public function printManifestBulk(array $lpCartId)
    {
        $zip = new ZipArchive();
        $zipName = 'manifests_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== true) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        foreach ($lpCartId as $id) {
            $fileName = $this->downloadManifestByLpCartId($id);
            if (!empty(trim($fileName))) {
                $zip->addFile($this->baseDownloadPath . $fileName, 'manifests_/' . $fileName);
            }
        }

        $zip->close();

        $this->downloadFile($this->baseDownloadPath, $zipName, 'application/zip');
    }

    public function getDocuments($lpOrderId)
    {
        $document = LPShippingDocument::getByParentId($lpOrderId);

        $result = [];

        if (!is_array($document)) {
            return $result;
        }

        $result['parcel_notes'] = $document['notes'];
        $result['parcel_description'] = $document['description'];
        $result['parcel_type'] = $document['parcel_type'];

        //Will only process one row since the requirments for multiple CN parts was not defined.
        $documentPart = LPShippingDocumentPart::getByParentId($document[LPShippingDocument::PRIMARY_KEY]);

        if (!is_array($documentPart)) {
            return $result;
        }

        $result['cn_parts_amount'] = $documentPart['amount'];
        $result['cn_parts_country_code'] = $documentPart['country_code'];
        $result['cn_parts_currency_code'] = $documentPart['currency_code'];
        $result['cn_parts_weight'] = $documentPart['weight'];
        $result['cn_parts_quantity'] = $documentPart['quantity'];
        $result['cn_parts_summary'] = $documentPart['summary'];

        return $result;
    }

    public function getDefaultTemplate($carrierInfo)
    {
        if ($carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_CARRIER_HOME_OFFICE_POST) {
            $templateKey = Configuration::get('LP_SHIPPING_ORDER_POST_TYPE');
            $template = LPShippingItemTemplate::getShippingTemplateIdByTypeAndSize(
                $this->getLpTemplateType($templateKey),
                null
            );

            return $template;
        }
        if ($carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_CARRIER_ABROAD) {
            $templateKey = Configuration::get('LP_SHIPPING_ORDER_POST_TYPE_FOREIGN');
            $template = LPShippingItemTemplate::getShippingTemplateIdByTypeAndSize(
                $this->getLpTemplateType($templateKey),
                $this->getLpTemplateSizeByType($templateKey)
            );

            return $template;
        }
        if ($carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_EXPRESS_CARRIER_POST) {
            return Configuration::get('LP_SHIPPING_EXPRESS_ORDER_TYPE');
        }

        if (
            $carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_EXPRESS_CARRIER_HOME ||
            $carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_EXPRESS_CARRIER_ABROAD
        ) {
            $type = Configuration::get('LP_SHIPPING_ORDER_HOME_TYPE');
            if ($type == 'CHCA') {
                $size = Configuration::get('LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE');
                $template = LPShippingItemTemplate::getShippingTemplateIdByTypeAndSize($type, $size);

                return $template;
            }

            return Configuration::get('LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES');
        }

        if ($carrierInfo == LpShippingCourierConfigNames::LP_SHIPPING_EXPRESS_CARRIER_TERMINAL) {
            $type = Configuration::get('LP_SHIPPING_ORDER_TERMINAL_TYPE');
            $size = Configuration::get('LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE');

            return LPShippingItemTemplate::getShippingTemplateIdByTypeAndSize($type, $size);
        }

        return null;
    }

    public function getLpTemplateType(string $templateType)
    {
        switch($templateType) {
            case LpShippingConsts::SMALL_CORRESPONDENCE_OLD:
            case LpShippingConsts::SMALL_CORESPONDENCE_TRACKED_OLD:
                return LpShippingConsts::SMALL_CORRESPONDENCE;
            case LpShippingConsts::MEDIUM_CORRESPONDENCE_OLD:
            case LpShippingConsts::MEDIUM_CORRESPONDENCE_TRACKED_OLD:
                return LpShippingConsts::MEDIUM_CORRESPONDENCE;
            case LpShippingConsts::PARCEL_OLD:
                return LpShippingConsts::PARCEL;
            default:
                return null;
        }
    }

    public function getLpTemplateSizeByType(string $templateType)
    {
        switch($templateType) {
            case LpShippingConsts::SMALL_CORESPONDENCE_TRACKED_OLD:
                return LpShippingConsts::SMALL_CORRESPONDENCE_SIZE_NAME;
            case LpShippingConsts::MEDIUM_CORRESPONDENCE_TRACKED_OLD:
                return LpShippingConsts::MEDIUM_CORRESPONDENCE_SIZE_NAME;
            default:
                return null;
        }
    }

    /**
     * Print a bunch of manifests by order id's
     *
     * @param array $ids
     *
     * @return readfile>download>exit|null
     */
    public function printAllBulk(array $ids)
    {
        $zip = new ZipArchive();
        $zipName = 'documents_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== true) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }
        $stickerSize = Configuration::get('LP_SHIPPING_STICKER_SIZE', '');

        $files = [];
        foreach ($ids as $id) {
            $pdfs = $this->getShippingFiles($id, $stickerSize);
            if (!is_array($pdfs)) {
                $this->addError('Failed to create documents for order: ' . $id);
                continue;
            }
            foreach ($pdfs as $pdf) {
                $files[] = $pdf;
            }
        }
        if (!$files) {
            $this->addError('No documents were created');

            return;
        }
        $fileName = 'documents_' . date('Y-m-d') . '.pdf';

        $this->saveFiles($this->baseDownloadPath, $fileName, $files);

        $this->downloadFile($this->baseDownloadPath, $fileName, 'application/pdf');
    }

    /**
     * download and save labels if they are present in order, if not return order ids which does not have label
     *
     * @param array $orderIds
     *
     * @return string|array path to file or missing labels array
     */
    private function downloadLabels(array $orderIds)
    {
        $stickerSize = Configuration::get('LP_SHIPPING_STICKER_SIZE', '');
        if (empty($stickerSize)) {
            return [];
        }

        // get each order and check their status if possible to create labels for each order
        $missingLabels = [];
        $itemIds = [];
        foreach ($orderIds as $id) {
            $newestOrderData = $this->getShipmentItem($id);

            $itemIds[] = $newestOrderData['id'];

            if (!is_array($newestOrderData) || $newestOrderData['status'] !== 'LABEL_CREATED') {
                $missingLabels[] = $id;
            }
        }

        if (count($missingLabels) > 0) {
            return $missingLabels;
        }

        $labels = LPShippingRequest::printLabels($itemIds, $stickerSize);
        if ($labels === null || !$this->isRequestSuccessful($labels)) {
            return null;
        }

        $moreThanOne = false;
        if (is_array($labels)) {
            if (count($labels) > 1) {
                $moreThanOne = true;
            }
        }

        if ($moreThanOne) {
            $zipFile = new ZipArchive();
            $zipName = 'labels_' . date('Ymdhis') . '.zip';
            if ($zipFile->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== TRUE) {
                $this->addError(self::ERROR_ZIP_CREATE);

                return null;
            }

            $i = 0;
            foreach ($labels as $label) {
                if ($label['contentType'] !== 'application/pdf') {
                    return [];
                }

                $fileName = 'label_' . $orderIds[$i++] . '.pdf';
                $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($label['label']));
                $zipFile->addFile($this->baseDownloadPath . $fileName, 'labels_/' . $fileName);
            }
            $zipFile->close();

            return $zipName;
        } else {
            $fileName = 'label_' . $orderIds[0] . '.pdf';
            $mimeType = $labels[0]['contentType'];

            $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($labels[0]['label']));

            return $fileName;
        }

        return true;
    }

    /**
     * download and save labels if they are present in order, if not return order ids which does not have label
     *
     * @param array $orderIds
     *
     * @return string|array path to file or missing labels array
     */
    private function downloadAll($orderId)
    {
        $stickerSize = Configuration::get('LP_SHIPPING_STICKER_SIZE', '');
        if (empty($stickerSize)) {
            return [];
        }

        return self::printFullDocument($orderId, $stickerSize);
    }

    /**
     * Print all documents
     *
     * @param array $orderData
     *
     * @return string|null
     */
    public function downloadAll2(array $orderData)
    {
        $zip = new ZipArchive();
        $zipName = $orderData['id_order'] . '_order_documents_' . date('Ymdhis') . '.zip';
        if ($zip->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== TRUE) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        $orderData = LPShippingOrder::getOrderById($orderData['id_order']);

        $isReady = $this->isShipmentReady($orderData);
        if ($isReady) {
            $labelsFileName = $this->downloadLabels([$orderData['id_order']]);

            if (!empty($labelsFileName) && is_string($labelsFileName)) {
                $zip->addFile($this->baseDownloadPath . $labelsFileName, 'documents/' . $labelsFileName);
            }
        }

        if ($this->canPrintDeclaration($orderData)) {
            $declarationFileName = $this->downloadDeclaration($orderData['id_order']);

            if (!empty($declarationFileName && is_string($declarationFileName))) {
                $zip->addFile($this->baseDownloadPath . $declarationFileName, 'documents/' . $declarationFileName);
            }
        }

        if ($this->canPrintManifest($orderData)) {
            $manifestFileName = $this->downloadManifest($orderData['id_order']);

            if (!empty($manifestFileName) && is_string($manifestFileName)) {
                $zip->addFile($this->baseDownloadPath . $manifestFileName, 'documents/' . $manifestFileName);
            }
        }

        if ($zip->numFiles > 0) {
            $zip->close();
        } else {
            if (file_exists($this->baseDownloadPath . $zipName)) {
                unlink($this->baseDownloadPath . $zipName);
            }
        }

        if (file_exists($this->baseDownloadPath . $zipName)) {
            return $zipName;
        }

        return null;
    }

    private function printFullDocument($orderId, $stickerSize)
    {
        $files = $this->getShippingFiles($orderId, $stickerSize);

        if (!$files) {
            return false;
        }
        $fileName = 'documents_' . $orderId . '.pdf';
        $this->saveFiles($this->baseDownloadPath, $fileName, $files);

        return $fileName;
    }

    public function getShippingFiles($orderId, $stickerSize)
    {
        $newestOrderData = $this->getShipmentItem($orderId);

        if (isset($newestOrderData['success']) && $newestOrderData['success'] === false) {
            return null;
        }

        $itemIds[] = $newestOrderData['id'];

        if (!is_array($newestOrderData) || $newestOrderData['status'] !== 'LABEL_CREATED') {
            if (!is_array($newestOrderData) || $newestOrderData['status'] !== 'LABEL_CREATED') {
                return null;
            }
        }
        $files = [];

        $label = LPShippingRequest::printLabels($itemIds, $stickerSize);
        if ($label === null || !$this->isRequestSuccessful($label)) {
            return null;
        }
        $files[] = base64_decode($label[0]['label']);

        $order = LPShippingOrder::getOrderById($orderId);

        if ($this->canPrintManifest($order)) {
            $manifest = LPShippingRequest::printManifest($order['id_cart_internal_order']);
            if (isset($manifest['document'])) {
                $files[] = base64_decode($manifest['document']);
            }
        }

        if ($this->canPrintDeclaration($order)) {
            $declaration = LPShippingRequest::printDeclaration($order['id_lp_internal_order']);
            if (isset($declaration[0]['declaration'])) {
                $files[] = base64_decode($declaration[0]['declaration']);
            }
        }

        return $files;
    }

    /**
     * Download and save declaration, ONLY for LP Service which requires CN23 declaration
     *
     * @param array $orderData
     *
     * @return string|null
     */
    private function downloadDeclaration($orderId)
    {
        $orderData = LPShippingOrder::getOrderById($orderId);

        $declaration = LPShippingRequest::printDeclaration($orderData['id_cart_internal_order']);
        if (!$this->isRequestSuccessful($declaration)) {
            return null;
        }
        $fileName = 'declaration_' . $orderId . '.pdf';

        $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($declaration['document']));

        if (file_exists($this->baseDownloadPath . $fileName)) {
            return $fileName;
        }

        return null;
    }

    /**
     * Download manifest (printable for couriers delivery type only)
     *
     * @param array $orderData
     *
     * @return string|null
     */
    private function downloadManifest($orderId)
    {
        $order = LPShippingOrder::getOrderById($orderId);
        if ($this->lpItemIdExists($order) && $order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_CALLED) {
            $manifest = LPShippingRequest::printManifest($order['id_cart_internal_order']);
            if (!$this->isRequestSuccessful($manifest)) {
                return null;
            }
            $fileName = 'manifest_' . $orderId . '.pdf';

            if ($manifest) {
                $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($manifest['document']));

                if (file_exists($this->baseDownloadPath . $fileName)) {
                    return $fileName;
                }
            }
        }

        return null;
    }

    public function downloadManifestByLpCartId(string $lpCartId)
    {
        $manifest = LPShippingRequest::printManifest($lpCartId);
        if (!$this->isRequestSuccessful($manifest)) {
            return null;
        }

        $fileName = 'manifest_' . $lpCartId . '.pdf';

        if ($manifest) {
            $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($manifest['document']));

            if (file_exists($this->baseDownloadPath . $fileName)) {
                return $fileName;
            }
        }

        return null;
    }

    /**
     * Save file to hard disk
     *
     * @param string $path - path to directory where file will be stored with ending right slash
     * @param string $fileName only file name
     * @param string $content content to write to file
     */
    public function saveFile($path, $fileName, $content)
    {
        if (file_exists($path . $fileName)) {
            unlink($path . $fileName);
        }

        // open and write to it
        $fHandle = fopen($path . $fileName, 'w');
        fwrite($fHandle, $content);
        fclose($fHandle);
    }

    /**
     * Save file to hard disk
     *
     * @param string $path - path to directory where file will be stored with ending right slash
     * @param string $fileName only file name
     * @param string $content content to write to file
     */
    public function saveFiles($path, $fileName, array $contents)
    {
        // open and write to it
        $merger = new \iio\libmergepdf\Merger();
        foreach ($contents as $key => $content) {
            $customFileName = $key . $fileName;
            if (file_exists($path . $customFileName)) {
                unlink($path . $customFileName);
            }
            $fHandle = fopen($path . $customFileName, 'w');
            fwrite($fHandle, $content);
            fclose($fHandle);
            $merger->addFile($path . $customFileName);
        }

        $createdPdf = $merger->merge();
        $fHandle = fopen($path . $fileName, 'w');
        fwrite($fHandle, $createdPdf);
        fclose($fHandle);
    }

    /**
     * Download file
     *
     * @param string $path Path to directory where file will be stored with ending right slash
     * @param string $fileName Name of the file to be created
     * @param string $mimeType Type of the file e.g. application/pdf
     */
    public function downloadFile($path, $fileName, $mimeType)
    {
        if (file_exists($path . $fileName)) {
            header('Content-type: ' . $mimeType);
            header('Content-Transfer-Encoding: Binary');
            header('Content-disposition: attachment; filename="' . $fileName . '"');

            $fHandle = fopen($path . $fileName, 'r');
            ob_clean();
            flush();
            while (!feof($fHandle)) {
                $buff = fread($fHandle, 1024);
                print $buff;
            }

            unlink($path . $fileName);
        }

        exit;
    }


    /**
     * Is Request from LP API successful
     *
     * @return bool
     */
    public function isRequestSuccessful($result)
    {
        if (is_array($result) && array_key_exists('success', $result) && $result['success'] == false) {
            $resultMessage = [];
            $errors = [];
            $messages = json_decode($result['message'], true);
            if (!$messages) {
                $errors[] = $result['message'];
            }
            $fieldErrors = isset($messages['fieldValidationErrors']) ? $messages['fieldValidationErrors'] : [];
            $valueErrors = isset($messages['valueValidationErrors']) ? $messages['valueValidationErrors'] : [];
            foreach ($fieldErrors as $fieldError) {
                $message = isset($fieldError['message']) ? $fieldError['message'] : (isset($fieldError['code']) ? $fieldError['code'] : '');
                $errors[] = $fieldError['field'] . ': ' . $message;
            }
            foreach ($valueErrors as $valueError) {
                $message = isset($valueError['message']) ? $valueError['message'] : (isset($valueError['code']) ? $valueError['code'] : '');
                $errors[] = $valueError['field'] . ': ' . $message;
            }

            if (isset($messages[0]) && is_array($messages[0])) {
                foreach ($messages as $message) {
                    if (isset($message['messages'])) {
                        $errorMessage = implode(',', $message['messages']);
                    } elseif (isset($message['message'])) {
                        $errorMessage = $message['message'];
                    } else {
                        continue;
                    }
                    $errors[] = $message['field'] . ': ' . $errorMessage;
                }
            }

            if (isset($messages['error_description'])) {
                $errors[] = $messages['error_description'];
            }
            $resultMessage['message'] = implode(',', $errors);
            Configuration::updateValue('LP_SHIPPING_LAST_ERROR', serialize($resultMessage));

            return false;
        }

        Configuration::updateValue('LP_SHIPPING_LAST_ERROR', '');

        return true;
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function getErrors()
    {
        $errors = $this->errors;
        unset($this->errors);

        return $errors;
    }

    private function addError($message)
    {
        if (!$this->errors) {
            $this->errors = [];
        }
        $this->errors[] = $message;
    }

    /**
     * Get selected carrier info by carrier ID
     *
     * @param string $carrierId
     *
     * @return array
     */
    private function getSelectedCarrier($carrierId)
    {
        $availableCarriers = $this->moduleInstance->getFilteredCarriers();

        foreach ($availableCarriers as $carrier) {
            $carrierIdFromConfig = Configuration::get($carrier['configuration_name']);

            if ((int)$carrierIdFromConfig === (int)$carrierId) {
                return $carrier;
            }
        }

        return false;
    }

    /**
     * Verify user address if delivery type is to post
     *
     * @param string $cartId
     *
     * @return string
     */
    private function validateAndReturnFormedAddress($cartId)
    {
        $lpOrderService = new LPShippingOrderService();
        $cart = new Cart($cartId);
        $address = new Address($cart->id_address_delivery);

        $addressData = $lpOrderService->formAddress($address);

        if ($addressData) {
            $addressString = "{$addressData['street']}, {$addressData['locality']}, {$addressData['postalCode']}";
        } else {
            $addressString = 'Address could not be verified successfully';
        }

        return $addressString;
    }

    public function canPrintLabels(array $orders, array $statesKeyedByLpId)
    {
        foreach($orders as $order) {
            $status = $statesKeyedByLpId[$order['id_lp_internal_order']];

            if (
                $status === LpShippingConsts::STATUS_PENDING ||
                $status === LpShippingConsts::STATUS_NOT_FOUND
            ) {
                throw new Exception(
                    sprintf('Label can not be printed for order %s it has status of %s', $order['id_order'], $status)
                );
            }
        }
    }

    public function printLabelsBulk(array $internalIds, array $ordersKeyedByLpId)
    {
        $stickerSize = Configuration::get('LP_SHIPPING_STICKER_SIZE', '');
        $chunkedIds = array_chunk($internalIds, LpShippingConsts::LP_SHIPPING_LABEL_PRINT_CHUNK_SIZE);

        $labels = [];
        foreach($chunkedIds as $chunk) {
            $labels = array_merge($labels, $this->downloadLabelsBulk($chunk, $stickerSize));
        }

        $zipFile = $this->zipLabels($labels, $ordersKeyedByLpId);
        $this->downloadFile($this->baseDownloadPath, $zipFile, 'application/zip');
    }

    private function downloadLabelsBulk(array $internalIds, string $stickerSize)
    {
        $labels = LPShippingRequest::printLabels($internalIds, $stickerSize);

        if (is_null($labels) || !$this->isRequestSuccessful($labels)) {
            $message = isset($labels['message']) ? $labels['message'] : "Something went wrong with LP request";
            throw new Exception($message);
        }

        return $labels;
    }

    private function zipLabels(array $labels, array $ordersKeyedByLpId)
    {
        $zipFile = new ZipArchive();
        $zipName = 'labels_' . date('Ymdhis') . '.zip';
        if ($zipFile->open($this->baseDownloadPath . $zipName, ZipArchive::CREATE) !== TRUE) {
            $this->addError(self::ERROR_ZIP_CREATE);

            return null;
        }

        foreach ($labels as $label) {
            if ($label['contentType'] !== 'application/pdf') {
                return [];
            }

            $fileName = 'label_' . $ordersKeyedByLpId[$label['itemId']] . '.pdf';
            $this->saveFile($this->baseDownloadPath, $fileName, base64_decode($label['label']));
            $zipFile->addFile($this->baseDownloadPath . $fileName, 'labels_/' . $fileName);
        }
        $zipFile->close();

        return $zipName;
    }
}