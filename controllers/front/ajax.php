<?php

// require_once(dirname(__FILE__) . './../../classes/LPShippingOrder.php');

class LPShippingAjaxModuleFrontController extends ModuleFrontController
{
    const CLASS_NAME = 'LPShippingAjaxModuleFrontController';

    private $moduleInstance = null;

    public function init()
    {
        $this->ajax = true;
        parent::init();

        if (Tools::getValue('LPShippingToken') != Tools::getToken(false)) {
            die(json_encode(['success' => 0, 'message' => $this->module->l('Invalid ajax token', self::CLASS_NAME)]));
        }

        $this->moduleInstance = Module::getInstanceByName('lpshipping');
    }

    /**
     * Method called automatically after submit
     */
    public function postProcess()
    {
        $action = Tools::getValue('action');
        if (!$action) {
            die(json_encode(['success' => 0, 'message' => $this->module->l('Invalid action.', self::CLASS_NAME)]));
        }

        switch ($action) {
            case 'submitOrder':
                $this->saveOrder(Tools::getAllValues());
                break;
            default:
                die(json_encode(['success' => 0, 'message' => $this->module->l('Invalid action.', self::CLASS_NAME)]));
        }
    }

    /**
     * Save order from user side to BE
     * 
     * @param array orderData from views/js/front.js
     */
    public function saveOrder(array $orderData)
    {
        $lpOrderService = new LPShippingOrderService();
        $carrierInfo = $this->getSelectedCarrier($orderData['selectedCarrierId']);
        $selectedCarrier = $carrierInfo['configuration_name'];
        $senderAddress = $lpOrderService->formSenderAddressType();
        if ($carrierInfo) {
            /* Create LPShippingOrder object */
            $lpOrderArray = LPShippingOrder::getOrderByCartId($orderData['cartId']);
            $cart = new Cart($orderData['cartId']);

            if (is_array($lpOrderArray) && !empty($lpOrderArray)) {
                $lpOrder = new LPShippingOrder($lpOrderArray['id_lpshipping_order']);

                if (Validate::isLoadedObject($lpOrder)) {
                    $lpOrder->selected_carrier = $carrierInfo['configuration_name'];
                    $lpOrder->weight = $cart->getTotalWeight();
                    $lpOrder->cod_available = $lpOrderService->isCodAvailable($orderData['cartId']);
                    $lpOrder->cod_amount = $cart->getOrderTotal();
                    $lpOrder->post_address = $this->validateAndReturnFormedAddress($orderData['cartId']);
                    $lpOrder->sender_locality = $senderAddress->getLocality();
                    $lpOrder->sender_street = $senderAddress->getStreet();
                    $lpOrder->sender_building = $senderAddress->getBuilding();
                    $lpOrder->sender_postal_code = $senderAddress->getPostalCode();
                    $lpOrder->sender_country = $senderAddress->getCountry();
                    $lpOrder->shipping_template_id = $lpOrderService->getDefaultTemplate($selectedCarrier);
                    if ($orderData['terminalId'] !== null) {
                        $lpOrder->id_lpexpress_terminal = $orderData['terminalId'];
                    }
    
                    $lpOrder->update();
                }
                
            } else { 
                $lpOrder = new LPShippingOrder();
                $numberOfPackages = $cart->getNbOfPackages();
                $lpOrder->id_cart = $orderData['cartId'];
                $lpOrder->selected_carrier = $selectedCarrier;
                $lpOrder->weight = $cart->getTotalWeight();
                $lpOrder->number_of_packages = $numberOfPackages != false ? $numberOfPackages : 1;
                $lpOrder->cod_available = $lpOrderService->isCodAvailable($orderData['cartId']);
                $lpOrder->cod_amount = $cart->getOrderTotal();
                $lpOrder->status = LPShippingOrder::ORDER_STATUS_NOT_SAVED;
                $lpOrder->post_address = $this->validateAndReturnFormedAddress($orderData['cartId']);
                $lpOrder->sender_locality = $senderAddress->getLocality();
                $lpOrder->sender_street = $senderAddress->getStreet();
                $lpOrder->sender_building = $senderAddress->getBuilding();
                $lpOrder->sender_postal_code = $senderAddress->getPostalCode();
                $lpOrder->sender_country = $senderAddress->getCountry();
                $lpOrder->shipping_template_id = $lpOrderService->getDefaultTemplate($selectedCarrier);
                if ($orderData['terminalId'] !== null) {
                    $lpOrder->id_lpexpress_terminal = $orderData['terminalId'];
                }
                $lpOrder->save();
            }

            $declarationData = [
                LPShippingDocument::PARENT_KEY => LPShippingOrder::getOrderByCartId($orderData['cartId'])[LPShippingDocument::PARENT_KEY],
                'parcel_type' => 'SELL',
                'parcel_notes' => 'Sell items',
                'parcel_description' => '',
                'cn_parts_amount' => $cart->getOrderTotal(),
                'cn_parts_country_code' => 'LT',
                'cn_parts_currency_code' =>'EUR',
                'cn_parts_weight' => $cart->getTotalWeight(),
                'cn_parts_quantity' => $cart->getNbProducts($orderData['cartId']),
                'cn_parts_summary' => ''
            ];
            $lpOrderService->updateShippingItemWithDeclaration($declarationData);

            die(json_encode(['success' => 1, 'data' => $lpOrder]));
        }

        die(json_encode(['success' => 0, 'message' => Context::getContext()->getTranslator()->trans('Error while saving selected carrier information')]));
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

            if ((int) $carrierIdFromConfig === (int) $carrierId) {
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

    private function getSenderAddress()
    {
        $lpOrderService = new LPShippingOrderService();
        $senderAddress = $lpOrderService->formSenderAddressType();
        return $senderAddress->getStreet() . ', ' . $senderAddress->getLocality() . ', ' . $senderAddress->getPostalCode();
    }
}
