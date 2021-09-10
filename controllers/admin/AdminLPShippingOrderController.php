<?php

require_once(dirname(__FILE__) . './../../services/LPShippingOrderService.php');

class AdminLPShippingOrderController extends ModuleAdminController
{
    const CLASS_NAME = 'AdminLPShippingOrderController';

    /**
     * @var LPShipping
     */
    private $moduleInstance = null;

    private $lpOrderService;

    /**
     * Constructs object by extending PrestaShop Core methods, pulls data automatically to from DB table to front table
     */
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        $this->table = 'lpshipping_order'; // set database table name to pull from
        $this->className = 'LPShippingOrder';
        $this->identifier = 'id_lpshipping_order';
        $this->moduleInstance = Module::getInstanceByName('lpshipping');
        $this->lpOrderService = new LPShippingOrderService();
    }

    /**
     * Renders admin configuration form
     */
    public function renderList()
    {
        $this->_where = 'AND status = "' . LPShippingOrder::ORDER_STATUS_NOT_SAVED . '" AND id_order != 0'; // pages are different just because of status
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        $this->toolbar_btn = []; // set toolbar button (add new, show sql query to empty array)
        $this->bulk_actions = [
            // 'formShipmentsBulk' => array('text' => $this->module->l('Form shipments', self::CLASS_NAME), 'icon' => 'icon-cogs'),
        ];
        $this->page_header_toolbar_btn = [];

        $this->tpl_list_vars['status'] = $this->getOrderStatuses();
        $this->fields_list = $this->getTableFieldsArray(); // set fields list in table

        // $renderList = parent::renderList();
        $helper = $this->parentRenderListFunction();
        $helper->actions = ['view'];

        foreach ($this->_list as $key => $value) {
            $this->_list[$key]['status'] = $this->getOrderStatusNameByKey($this->_list[$key]['status']);
            $this->_list[$key]['selected_carrier'] = $this->getCarrierNameByKey($this->_list[$key]['selected_carrier']);
            $this->_list[$key]['id_lpexpress_terminal'] = $this->getFormedTerminalById($this->_list[$key]['id_lpexpress_terminal']);
        }

        // return $renderList;
        $list = $helper->generateList($this->_list, $this->fields_list);

        return $list;
    }

    /**
     * Changed a little bit parent render function
     */
    private function parentRenderListFunction()
    {
        if (!($this->fields_list && is_array($this->fields_list))) {
            return false;
        }
        $this->getList($this->context->language->id);

        // If list has 'active' field, we automatically create bulk action
        if (
            isset($this->fields_list) && is_array($this->fields_list) && array_key_exists('active', $this->fields_list)
            && !empty($this->fields_list['active'])
        ) {
            if (!is_array($this->bulk_actions)) {
                $this->bulk_actions = array();
            }

            $this->bulk_actions = array_merge(array(
                'enableSelection' => array(
                    'text' => $this->l('Enable selection'),
                    'icon' => 'icon-power-off text-success',
                ),
                'disableSelection' => array(
                    'text' => $this->l('Disable selection'),
                    'icon' => 'icon-power-off text-danger',
                ),
                'divider' => array(
                    'text' => 'divider',
                ),
            ), $this->bulk_actions);
        }

        $helper = new HelperList();

        // Empty list is ok
        if (!is_array($this->_list)) {
            $this->displayWarning($this->l('Bad SQL query', 'Helper') . '<br />' . htmlspecialchars($this->_list_error));

            return false;
        }

        $this->setHelperDisplay($helper);
        $helper->_default_pagination = $this->_default_pagination;
        $helper->_pagination = $this->_pagination;
        $helper->tpl_vars = $this->getTemplateListVars();
        $helper->tpl_delete_link_vars = $this->tpl_delete_link_vars;

        // For compatibility reasons, we have to check standard actions in class attributes
        foreach ($this->actions_available as $action) {
            if (!in_array($action, $this->actions) && isset($this->$action) && $this->$action) {
                $this->actions[] = $action;
            }
        }

        $helper->is_cms = $this->is_cms;
        $helper->sql = $this->_listsql;

        return $helper;
    }

    public function getOrderStatuses()
    {
        $statuses[LPShippingOrder::ORDER_STATUS_NOT_SAVED] = $this->module->l('NOT saved', self::CLASS_NAME);
        $statuses[LPShippingOrder::ORDER_STATUS_SAVED] = $this->module->l('Saved', self::CLASS_NAME);
        $statuses[LPShippingOrder::ORDER_STATUS_NOT_FORMED] = $this->module->l('NOT formed', self::CLASS_NAME);
        $statuses[LPShippingOrder::ORDER_STATUS_FORMED] = $this->module->l('Formed', self::CLASS_NAME);
        $statuses[LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED] = $this->module->l('Courier NOT called', self::CLASS_NAME);
        $statuses[LPShippingOrder::ORDER_STATUS_COURIER_CALLED] = $this->module->l('Courier is called', self::CLASS_NAME);

        return $statuses;
    }


    /**
     * Get order translations
     *
     * @param string $lookingStatusName
     */
    public function getOrderStatusNameByKey($lookingStatusName)
    {
        foreach ($this->getOrderStatuses() as $key => $value) {
            if ($key == $lookingStatusName) {
                return $value;
            }
        }
    }


    /**
     * Get carrier by configuration_name key
     *
     * @param string $carrierKey
     */
    public function getCarrierNameByKey($carrierKey)
    {
        $moduleCarriers = $this->moduleInstance->getCarriers();
        foreach ($moduleCarriers as $key => $value) {
            if ($carrierKey == $value['configuration_name']) {
                return $value['name_translation'];
            }
        }
    }

    /**
     * Get terminal data by ID
     *
     * @param
     */
    public function getFormedTerminalById($id)
    {
        $terminal = LPShippingTerminal::getTerminalById($id);

        if ($terminal) {
            return "{$terminal['name']} {$terminal['address']}, {$terminal['city']}";
        }

        return '';
    }

    /**
     * Get table fields list in array format
     *
     * @return array
     */
    public function getTableFieldsArray()
    {
        return [
            'id_cart' => [
                'title' => $this->module->l('Cart ID', self::CLASS_NAME),
                'width' => 'fixed-width-xs',
                'remove_onclick' => true,
            ],
            'id_order' => [
                'title' => $this->module->l('Order ID', self::CLASS_NAME),
                'width' => 'fixed-width-xs',
                'remove_onclick' => true,
            ],
            'id_lpexpress_terminal' => [
                'title' => $this->module->l('Terminal', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
            ],
            'status' => [
                'title' => $this->module->l('Status', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
            ],
            'selected_carrier' => [
                'title' => $this->module->l('Carrier', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'weight' => [
                'title' => $this->module->l('Weight', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
        ];
    }


    public function postProcess()
    {
        parent::postProcess();
        $data = Tools::getAllValues();

        if (Tools::isSubmit('saveLPShippingOrder')) {
            $this->saveLPShippingOrder($data);
        }

        if (Tools::isSubmit('removeLPShippingOrder')) {
            $this->removeLPShippingOrder($data);
        }

        if (Tools::isSubmit('formShipments')) {
            $this->formShipments($data);
        }

        if (Tools::isSubmit('cancelShipments')) {
            $this->cancelInitiatedShipping($data);
        }

        if (Tools::isSubmit('callCourier')) {
            $this->callCourier($data);
        }

        if (Tools::isSubmit('printDocuments')) {
            $this->printDocuments($data);
        }

        if (Tools::isSubmit('printLabel')) {
            $this->printLabels([$data['id_order']]);
        }

        if (Tools::isSubmit('printAll')) {
            $this->printAll($data['id_order']);
        }

        if (Tools::isSubmit('printDeclaration')) {
            $this->printDeclaration($data);
        }

        if (Tools::isSubmit('printManifest')) {
            $this->printManifest($data);
        }

        if (Tools::isSubmit('saveDeclarationInfo')) {
            $this->saveDeclarationsInfo($data);
        }

        if (Tools::isSubmit('saveSenderAddressInfo')) {
            $this->saveSenderAddressInfo($data);
        }

        if (Tools::isSubmit('viewlpshipping_order')) { // if submits view button in orders page
            $order = LPShippingOrder::getOrderByRowId($data['id_lpshipping_order']);

            if ($order) {
                Tools::redirectAdmin($this->getSingleOrderRedirectLink($order['id_order']));
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminLPShippingOrder'));
            }
        }
    }


    /**
     * Save LPShipping order information
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    private function saveLPShippingOrder(array $orderData)
    {
        $this->lpOrderService->saveLPShippingOrder($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Remove LPShippingOrder order information
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    private function removeLPShippingOrder(array $orderData)
    {
        $this->lpOrderService->removeLPShippingOrder($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Initiate shipping process in LP API
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    private function formShipments(array $orderData)
    {
        $this->lpOrderService->formShipments($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Cancel initiated shipment in LP API
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    private function cancelInitiatedShipping(array $orderData)
    {
        $this->lpOrderService->cancelInitiatedShipping($orderData['id_order']);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Call courier process in LP API
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    private function callCourier(array $orderData)
    {
        $this->lpOrderService->callCourier($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Print all documents
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function printDocuments(array $orderData)
    {
        $this->lpOrderService->printDocuments($orderData);

        $this->checkErrors();

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Print label
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function printLabels(array $orderIds)
    {
        $ids = [];
        foreach ($orderIds as $id) {
            $ids[] = $id;
        }
        $this->lpOrderService->printLabels($ids);

        if (count($ids) == 1) {
            Tools::redirectAdmin($this->getSingleOrderRedirectLink($ids[0]));
        } else {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
        }
    }

    /**
     * Print label
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function printAll($orderId)
    {
        $this->lpOrderService->printAll($orderId);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderId));
    }

    /**
     * Print declaration
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function printDeclaration(array $orderData)
    {
        $this->lpOrderService->printDeclaration($orderData['id_order']);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Print manifest
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function printManifest(array $orderData)
    {
        $this->lpOrderService->printManifest($orderData['id_order']);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Save declaration info
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function saveDeclarationsInfo(array $orderData)
    {
        $this->lpOrderService->updateShippingItemWithDeclaration($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Save declaration info
     *
     * @param array $orderData
     *
     * @return Tools::redirectAdmin back
     */
    public function saveSenderAddressInfo(array $orderData)
    {
        $this->lpOrderService->updateSenderAddress($orderData);

        Tools::redirectAdmin($this->getSingleOrderRedirectLink($orderData['id_order']));
    }

    /**
     * Get redirect link to single order page depending on PS version
     */
    private function getSingleOrderRedirectLink($orderId)
    {
        $link = $this->context->link->getAdminLink('AdminOrders');

        if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            $parts = $this->getLinkParts($link);
            $link = $parts['base'] . $orderId . '/view?_token=' . $parts['token'];
        } else {
            $link .= '&id_order=' . $orderId . '&vieworder=1&conf=4';
        }

        return $link;
    }

    /**
     * Extracts token from link ant puts them both in array
     *
     * TODO
     * Trivial solution to link structure - needs deeper analysis of how PS builds link with various options
     *
     * @param string $link
     */
    private function getLinkParts($link)
    {
        $linkParts = [];
        $index = stripos($link, '_token');

        if ($index !== false) {
            $linkParts['token'] = substr($link, $index + 7, strlen($link) - ($index + 7));
            $linkParts['base'] = substr($link, 0, $index - 1);
        }

        return $linkParts;
    }

    private function checkErrors()
    {
        if (!$this->lpOrderService->hasErrors()) {
            return;
        }

        $errors = $this->lpOrderService->getErrors();

        foreach ($errors as $error) {
            $this->errors[] = $error;
        }
    }
}
