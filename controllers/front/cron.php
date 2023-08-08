<?php

class LPShippingCronModuleFrontController extends ModuleFrontController
{
    private $cronAction = '';

    /**
     * @var Logger
     */
    private $logger; // fails to construct object

    public function init()
    {
        // get action and then write it out in private variable to check (updateTerminals or getTrackingInformation)
        $token = Tools::getValue('token');
        if ($token != Configuration::get('LP_SHIPPING_CRON_TOKEN')) {
            // $this->module->logger->error('Attempt to launch cron task without correct token.');
            die('Invalid token!');
        }

        $this->cronAction = Tools::getValue('action');
        if (!$this->cronAction) {
            // $this->module->logger->error('Undefined cron action');
            die('Invalid action!');
        }

    }

    public function initContent()
    {
        if (LPShippingRequest::authenticate()) {
            if ($this->cronAction === 'updateTerminals') {
                $this->updateTerminals();
            } elseif ($this->cronAction === 'getTrackingInformation') {
                $this->getTrackingInformation();
            } else {
                die('Undefined action');
            }
        } else {
            die('Authentication to LP API failed');
        }
    }

    /**
     * Updates all terminals from LP API
     */
    private function updateTerminals()
    {
        $newTerminals = LPShippingRequest::getTerminals();
        if (isset($newTerminals['success']) && !$newTerminals['success']) {
            die('Failed to get terminals from API.');
        }
        foreach ($newTerminals as $newTerminal) {

            $oldTerminals = LPShippingTerminal::getTerminalsByTerminalId($newTerminal['id']);
            if(count($oldTerminals) > 1)
            {
                foreach ($oldTerminals as $key => $oldTerminal)
                {
                    if($key == 0)
                    {
                        continue;
                    }
                    $lpShippingTerminal = new LPShippingTerminal($oldTerminal['id_lpexpress_terminal']);
                    $lpShippingTerminal->delete();
                }
            }

            $oldTerminal = LPShippingTerminal::getTerminalByTerminalId($newTerminal['id']);

            $success = LPShippingTerminal::updateTerminal($oldTerminal['id_lpexpress_terminal'], $newTerminal);

            if (!$success) {
                PrestaShopLogger::addLog('Error in terminal which id is -' . $oldTerminal['id_lpexpress_terminal'] . ' automatic update process', 2);
            }
        }

        die('Successfully updated terminal list.');
    }

    /**
     * Get tracking information of every initiated shipment and update its status
     */
    private function getTrackingInformation()
    {
        $orders = LPShippingOrder::getOrders();

        foreach ($orders as $order) {
            if (
                $order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_CALLED ||
                $order['status'] === LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED ||
                $order['status'] === LPShippingOrder::ORDER_STATUS_FORMED
            ) {
                $trackingInfo = LPShippingRequest::getShippingItemsTrackingInformation([$order['id_lp_internal_order']]);

                if ($trackingInfo && !empty($trackingInfo) && is_array($trackingInfo)) {
                    if (key_exists('barcode', $trackingInfo[0])) {
                        $order['label_number'] = $trackingInfo[0]['barcode'];
                    }
                    $order['parcel_status'] = $trackingInfo[0]['state'];

                    $success = LPShippingOrder::updateOrder($order);

                    if (!$success) {
                        PrestaShopLogger::addLog('Error in order which id is -' . $order['id_order'] . ' automatic update process', 2);
                    }
                }
            }
        }

        die('Finished orders tracking information automatic update process.');
    }

}
