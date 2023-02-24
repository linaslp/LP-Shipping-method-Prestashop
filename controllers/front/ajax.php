<?php

// require_once(dirname(__FILE__) . './../../classes/LPShippingOrder.php');
require_once(dirname(__FILE__).'./../../classes/LPShippingCartTerminal.php');

class LPShippingAjaxModuleFrontController extends ModuleFrontController
{
    const CLASS_NAME = 'LPShippingAjaxModuleFrontController';

    private $moduleInstance = null;

    public function init()
    {
        $this->ajax = true;
        parent::init();

        if (Tools::getValue('LPShippingToken') != Tools::getToken(false)) {
            http_response_code(403);
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
            http_response_code(400);
            die(json_encode(['success' => 0, 'message' => $this->module->l('Invalid action.', self::CLASS_NAME)]));
        }

        switch ($action) {
            case 'submitOrder':
                $this->saveCartTerminal(Tools::getAllValues());
                break;
            default:
                http_response_code(400);
                die(json_encode(['success' => 0, 'message' => $this->module->l('Invalid action.', self::CLASS_NAME)]));
        }
    }

    /**
     * Save order from user side to BE
     * 
     * @param array orderData from views/js/front.js
     */
    public function saveCartTerminal(array $orderData)
    {        
        try {            
            $terminal = new LPShippingCartTerminal($orderData['cartId']);
            $terminalId = $orderData['terminalId'] ? $orderData['terminalId'] : null;

            if($terminal->id_cart){
                LPShippingCartTerminal::updateTerminalByCartId($orderData['cartId'], $terminalId);
            } else if ($terminalId) {
                $terminal->id_cart = $orderData['cartId'];
                $terminal->id_lpexpress_terminal = $terminalId;
                $terminal->save();
            }
        } catch(Exception $e) {
            http_response_code(400);

            die(
                json_encode(
                    [
                        'success' => 0, 
                        'message' => Context::getContext()->getTranslator()->trans('Error while saving selected carrier information')
                    ]
            ));
        }

        die(json_encode(['success' => 1]));
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
