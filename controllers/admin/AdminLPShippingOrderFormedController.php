
<?php

require_once(dirname(__FILE__) . './../../services/LPShippingOrderService.php');
require_once(dirname(__FILE__) . './../../api/types/TrackingItemStatus.php');

class AdminLPShippingOrderFormedController extends ModuleAdminController
{
    const CLASS_NAME = 'AdminLPShippingOrderFormedController';

    /**
     * @var LPShipping
     */
    private $moduleInstance = null;

    /**
     * @var LPShippingOrderService
     */
    private $lpOrderService;

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
        $trackingItemStatus = new TrackingItemStatus(Context::getContext());

        $this->_where = 'AND (status = "' . LPShippingOrder::ORDER_STATUS_FORMED . '"'
            . ' OR status = "' . LPShippingOrder::ORDER_STATUS_COURIER_CALLED . '"' // lpshipping order pages are different just because of status
            . ' OR status = "' . LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED . '")';
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        $this->toolbar_btn = []; // set toolbar button (add new, show sql query to empty array)
        $this->bulk_actions = [
            'printLabelBulk' => array('text' => $this->module->l('Print labels', self::CLASS_NAME), 'icon' => 'icon-cogs', self::CLASS_NAME),
            'printAllBulk' => array('text' => $this->module->l('Print documents', self::CLASS_NAME), 'icon' => 'icon-cogs'),
            'printDeclarationBulk' => array('text' => $this->module->l('Print declarations', self::CLASS_NAME), 'icon' => 'icon-cogs'),
            'printManifestBulk' => array('text' => $this->module->l('Print manifests', self::CLASS_NAME), 'icon' => 'icon-cogs'),
            'callCourierBulk' => array('text' => $this->module->l('Call courier', self::CLASS_NAME), 'icon' => 'icon-cogs'),
            'cancelInitiatedShippingBulk' => array('text' => $this->module->l('Cancel initiated shipments', self::CLASS_NAME), 'icon' => 'icon-cogs'),
        ];
        // $this->page_header_toolbar_btn = [];

        $this->tpl_list_vars['status'] = $this->getOrderStatuses();
        $this->fields_list = $this->getTableFieldsArray(); // set fields list in table

        // $renderList = parent::renderList();
        $helper = $this->parentRenderListFunction();
        $helper->actions = ['view'];

        foreach ($this->_list as $key => $value) {
            $this->_list[$key]['status'] = $this->getOrderStatusNameByKey($this->_list[$key]['status']);
            $this->_list[$key]['parcel_status'] = $trackingItemStatus->getStatusByKey($this->_list[$key]['parcel_status']);
            $this->_list[$key]['selected_carrier'] = $this->getCarrierNameByKey($this->_list[$key]['selected_carrier']);
            $this->_list[$key]['id_lpexpress_terminal'] = $this->getFormedTerminalById($this->_list[$key]['id_lpexpress_terminal']);
        }

        // return $renderList;
        $list = $helper->generateList($this->_list, $this->fields_list);

        return $list;
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
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
            'id_order' => [
                'title' => $this->module->l('Order ID', self::CLASS_NAME),
                'width' => 'fixed-width-xs',
                'remove_onclick' => true,
            ],
            'parcel_status' => [
                'title' => $this->module->l('Shipment status', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'status' => [
                'title' => $this->module->l('Order status', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'label_number' => [ // actually it is a barcode
                'title' => $this->module->l('Barcode', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
            ],
            'id_lpexpress_terminal' => [
                'title' => $this->module->l('Terminal', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
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
            'number_of_packages' => [
                'title' => $this->module->l('Packages', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'cod_selected' => [
                'title' => $this->module->l('Is COD', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'cod_amount' => [
                'title' => $this->module->l('COD amount', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'id_lp_internal_order' => [
                'title' => $this->module->l('LP API formed ID', self::CLASS_NAME),
                'width' => 'auto',
                'remove_onclick' => true,
                'search' => false,
            ],
            'post_address' => [
                'title' => $this->module->l('Post Address', self::CLASS_NAME),
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

        if (Tools::isSubmit('viewlpshipping_order')) { // if submits view button in orders page
            $order = LPShippingOrder::getOrderByRowId($data['id_lpshipping_order']);

            if ($order) {
                Tools::redirectAdmin($this->getSingleOrderRedirectLink($order['id_order']));
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminLPShippingOrderFormed'));
            }
        }

        if (Tools::isSubmit('submitBulkprintLabelBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->printLabelBulk($data['lpshipping_orderBox']);
                }
            }
        }

        if (Tools::isSubmit('submitBulkprintAllBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->printAllBulk($data['lpshipping_orderBox']);
                }
            }
        }

        if (Tools::isSubmit('submitBulkprintDeclarationBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->printDeclarationBulk($data['lpshipping_orderBox']);
                }
            }
        }

        if (Tools::isSubmit('submitBulkprintManifestBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->printManifestBulk($data['lpshipping_orderBox']);
                }
            }
        }

        if (Tools::isSubmit('submitBulkcancelInitiatedShippingBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->cancelInitiatedShippingBulk($data['lpshipping_orderBox']);
                }
            }
        }

        if (Tools::isSubmit('submitBulkcallCourierBulklpshipping_order')) {
            if (is_array($data)) {
                if (key_exists('lpshipping_orderBox', $data)) {
                    $this->callCourierBulk($data['lpshipping_orderBox']);
                }
            }
        }
    }

    /**
     * Show error to user
     * 
     * @param bool $success
     * @param string $message
     */
    public function addMessage($success, $message)
    {
        if (!$success) {
            $this->errors[] = Context::getContext()->getTranslator()->trans($message);
            $errorHandlerInstance = LPShippingRequestErrorHandler::getInstance();
            if ($err = $errorHandlerInstance->getLastError()) {
                $this->errors[] = $err['message'];
            }
        } else {
            $this->confirmations[] = Context::getContext()->getTranslator()->trans($message);
        }
    }


    /**
     * Print a bunch of documents (labels, manifests, declarations)
     * 
     * @param array $rowIds
     */
    public function printDocumentsBulk(array $rowIds)
    {
        $ids = [];
        foreach ($rowIds as $rowId) {
            $row = LPShippingOrder::getOrderByRowId($rowId);
            $ids[] = $row['id_order'];

            if (!$this->lpOrderService->canPrintDocuments($row)) {
                $this->addMessage(false, 'Documents are missing or can not be printed for order ' . $row['id_order']);
            }
        }

        if (empty($this->errors)) {
            $this->lpOrderService->printDocumentsBulk($ids);
            $this->checkErrors();
        }
    }

    /**
     * Print a bunch of labels
     * 
     * @param array $rowIds
     */
    public function printLabelBulk(array $rowIds)
    {
        try {
            $orders = LPShippingOrder::getOrdersById($rowIds);
            $internalIds = array_column($orders, 'id_lp_internal_order');
            $trackingInfo = LPShippingRequest::getShippingItemsTrackingInformation($internalIds);
                
            $trackingStateById = $this->groupBy($trackingInfo, 'id', 'state');
            unset($trackingInfo);

            $this->lpOrderService->canPrintLabels($orders, $trackingStateById);
            
            $ordersKeyedByLpId = $this->groupBy($orders, 'id_lp_internal_order', 'id_order');
            unset($orders);

            $this->lpOrderService->printLabelsBulk($internalIds, $ordersKeyedByLpId);

        } catch (Exception $e) {
            $this->addMessage(false, $e->getMessage());
        }
    }

    private function groupBy(array $array, string $keyBy, string $valueKeyName): array 
    {
        $newArray = [];
        foreach($array as $item) {
            $newArray[$item[$keyBy]] = $item[$valueKeyName];
        }

        return $newArray;
    }

    /**
     * Print a bunch of declarations
     * 
     * @param array $rowIds
     */
    public function printDeclarationBulk(array $rowIds)
    {
        $ids = [];
        foreach ($rowIds as $rowId) {
            $row = LPShippingOrder::getOrderByRowId($rowId);
            $ids[] = $row['id_order'];

            if (!$this->lpOrderService->canPrintDeclaration($row)) {
                $this->addMessage(false, 'Declaration can not be printed for order ' . $row['id_order']);
            }
        }

        if (empty($this->errors)) {
            $this->lpOrderService->printDeclarationBulk($ids);
            $this->checkErrors();
        }
    }

    /**
     * Print a bunch of declarations
     * 
     * @param array $rowIds
     */
    public function printManifestBulk(array $rowIds)
    {
        $orders = LPShippingOrder::getOrdersById($rowIds);

        if (!$orders) {
            $this->addMessage(false, 'Unable to find LP orders');
            return;
        }

        foreach ($orders as $order) {
            if (!$this->lpOrderService->canPrintManifest($order)) {
                $this->addMessage(false, 'Manifest can not be printed for order ' . $order['id_order']);
            }
        }

        $lpCartIds = array_unique(array_column($orders, 'id_cart_internal_order'));

        if (empty($this->errors)) {
            $this->lpOrderService->printManifestBulk($lpCartIds);
            $this->checkErrors();
        }
    }
    /**>
     * Print a bunch of declarations
     *
     * @param array $rowIds
     */
    public function printAllBulk(array $rowIds)
    {
        $orders = LPShippingOrder::getOrdersById($rowIds);

        if (!$orders) {
            $this->addMessage(false, 'Cannot find any LP shippments');
            return;
        }

        $ids = array_column($orders, 'id_order');
        
        if (empty($this->errors)) {
            $this->lpOrderService->printAllBulk($ids);
            $this->checkErrors();
        }
    }


    /**
     * Initiate a bunch of shipments
     * 
     * @param array $ids
     */
    public function cancelInitiatedShippingBulk(array $rowIds)
    {
        $ids = [];
        foreach ($rowIds as $rowId) {
            $row = LPShippingOrder::getOrderByRowId($rowId);
            $ids[] = $row['id_order'];

            if ($this->lpOrderService->getOrderServiceType($row) != LPShippingOrderService::LP_EXPRESS_SERVICE) {
                $this->addMessage(false, 'Order ' . $row['id_order'] . ' is not cancellable');
            }
        }

        if (empty($this->errors)) {
            $this->lpOrderService->cancelInitiatedShippingBulk($ids);
        }
    }


    /**
     * Call courier bulk
     * 
     * @param array $rowIds
     */
    public function callCourierBulk(array $rowIds)
    {
        $ids = [];
        foreach ($rowIds as $rowId) {
            $row = LPShippingOrder::getOrderByRowId($rowId);
            $ids[] = $row['id_order'];

            if (!$this->lpOrderService->isCallCourierAvailable($row)) {
                $this->addMessage(false, 'Courier call for order ' . $row['id_order'] . ' is not available');
            }
        }

        if (empty($this->errors)) {
            $error = $this->lpOrderService->callCourierBulk($ids);
            if (!empty($error)){
                $this->addMessage(false, $error);
            }
        }
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
