<?php

/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('_LPSHIPPING_ROOT_')) {
    define('_LPSHIPPING_ROOT_', dirname(__FILE__));
}

require_once __DIR__ . '/vendor/autoload.php';


class LPShipping extends CarrierModule
{
    protected $config_form = false;

    private $needTabsInstall = false;

    /**
     * Prestashop fills this property automatically with selected carrier ID in FO checkout
     *
     * @var int $id_carrier
     */
    public $id_carrier;

    public function __construct()
    {
        $this->name = 'lpshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.8';
        $this->author = 'Kirotech';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LP Shipping (Lietuvos paštas)');
        $this->description = $this->l('Shipping via terminals, couriers by address, zip code');
        $this->confirmUninstall = $this->l('This action will remove all saved data for this module from database');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        $this->logger = new Logger(_LPSHIPPING_ROOT_ . '/logs/', ['create_file_on_awake' => false]);
//        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
//            foreach ($this->getRequiredTabsForInstallation() as $tab) {
//                $this->tabs[] = $tab;
//            }
//        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $resultOfInstall = parent::install();

        if ($resultOfInstall) {
            // install default configuration settings to PREFIX_configuration table
            AdminLPShippingConfigurationController::installConfiguration();

            // create tables with shipments delivery methods
            $setup = new LPShippingDbSetup();
            $setup->install();
      if (!$setup->installCarriers($this->getCarriers(), $this->name)) {
                return false;
            }

            // if (version_compare(_PS_VERSION_, '1.7', '<')) {
            //     $this->installAdminPageTabs();
            // }

            $this->registerHook('header');
            $this->registerHook('backOfficeHeader');

            $this->registerHook('actionCarrierUpdate'); // PS 1.7 hook after updating carrier configuration
            $this->registerHook('updateCarrier'); // PS 1.6 hook after updating carrier configuration
            $this->registerHook('actionValidateOrder'); // Execute when new order is created

            $this->registerHook('displayBeforeCarrier'); // Hook call one time on front office order page load
            $this->registerHook('displayCarrierList'); // Front office hook after carrier selection
            $this->registerHook('displayCarrierExtraContent'); // Display content for each carrier after carrier name, for 1.6 compatibility
            $this->registerHook('orderDetailDisplayed');
            $this->registerHook('displayAdminOrder'); // hook to display module block in single order page
            $this->registerHook('actionAdminControllerSetMedia'); // hook to display module block in single order page
            $this->registerHook('actionValidateStepComplete');
            $this->registerHook('actionCarrierProcess');

        }

        return $resultOfInstall;
    }

    public function uninstall()
    {
        $this->unregisterHook('header');
        $this->unregisterHook('backOfficeHeader');

        $this->unregisterHook('actionCarrierUpdate');
        $this->unregisterHook('updateCarrier');
        $this->unregisterHook('actionValidateOrder');

        $this->unregisterHook('displayBeforeCarrier');
        $this->unregisterHook('displayCarrierList');
        $this->unregisterHook('displayCarrierExtraContent');
        $this->unregisterHook('orderDetailDisplayed');
        $this->unregisterHook('displayAdminOrder');
        $this->unregisterHook('actionCarrierProcess');

        AdminLPShippingConfigurationController::removeConfiguration();

        $setup = new LPShippingDbSetup();
        $setup->uninstallCarriers($this->getCarriers());
        $setup->uninstall();
        $this->uninstallAdminTabs();

        // if (version_compare(_PS_VERSION_, '1.7', '<')) {
        //     $this->uninstallAdminTabs();
        // }

        return parent::uninstall();
    }

    /**
     * Returns required Tabs data for installation
     *
     * @return array
     */
    public function getRequiredTabsForInstallation()
    {
        return [
            ['name' => $this->l('LP Configuration'), 'parent_class_name' => 0, 'class_name' => 'AdminLPShippingConfiguration', 'visible' => true],
            ['name' => $this->l('LP Orders'), 'parent_class_name' => 'AdminParentOrders', 'class_name' => 'AdminLPShippingOrder', 'visible' => true],
            ['name' => $this->l('Created orders'), 'parent_class_name' => 'AdminLPShippingOrder', 'class_name' => 'AdminLPShippingOrderCreated', 'visible' => true],
            ['name' => $this->l('Saved orders'), 'parent_class_name' => 'AdminLPShippingOrder', 'class_name' => 'AdminLPShippingOrderSaved', 'visible' => true],
            ['name' => $this->l('Formed orders'), 'parent_class_name' => 'AdminLPShippingOrder', 'class_name' => 'AdminLPShippingOrderFormed', 'visible' => true],
        ];
    }

//    /**
//     * Installs a tab under orders page, only in 1.6 prestashop
//     */
//    private function installAdminPageTabs()
//    {
//        foreach (array_reverse($this->getRequiredTabsForInstallation()) as $requiredTab) {
//            $parentTabOrdersId = Tab::getIdFromClassName($requiredTab['parent_class_name']);
//
//            $tab = new Tab();
//            $tab->class_name = $requiredTab['class_name'];
//            $tab->id_parent = $parentTabOrdersId;
//            $tab->module = $this->name;
//            $languages = Language::getLanguages(false);
//            foreach ($languages as $lang) {
//                $tab->name[$lang['id_lang']] = $this->name;
//            }
//
//            $tab->save();
//        }
//    }

    /**
     * Uninstall a tab from orders tabs tree, only in 1.6 prestashop
     */
    private function uninstallAdminTabs()
    {
        foreach (array_reverse($this->getRequiredTabsForInstallation()) as $requiredTab) {
            $installedTabName = TabCore::getIdFromClassName($requiredTab['class_name']);
            $tab = new Tab($installedTabName);

            if (Validate::isLoadedObject($tab)) {
                $tab->delete();
            }
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLPShippingConfiguration'));
    }

    /**
     * Get default module carriers to install
     *
     * @return array
     */
    public function getCarriers()
    {
        return [
            [
                'name' => 'Delivery to home or office (LP EXPRESS (courier))',
                'name_translation' => $this->l('Delivery to home or office (LP EXPRESS (courier))'),
                'logo' => $this->getLocalPath() . 'lpexpress_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_EXPRESS_CARRIER_HOME',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['CHCA'],
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['EBIN'],
                ],
                'allZones' => false,
            ],
            [
                'name' => 'Delivery of shipments abroad (LP EXPRESS)',
                'name_translation' => $this->l('Delivery of shipments abroad (LP EXPRESS)'),
                'logo' => $this->getLocalPath() . 'lpexpress_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_EXPRESS_CARRIER_ABROAD',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['CHCA'],
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['EBIN'],
                ],
                'allZones' => true
            ],
            [
                'name' => 'Delivery of shipments abroad (LP)',
                'name_translation' => $this->l('Delivery of shipments abroad (LP)'),
                'logo' => $this->getLocalPath() . 'lp_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_CARRIER_ABROAD',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['LP_ABROAD'],
                ],
                'allZones' => true
            ],
            [
                'name' => 'Delivery of shipments to the LP EXPRESS terminal',
                'name_translation' => $this->l('Delivery of shipments to the LP EXPRESS terminal'),
                'logo' => $this->getLocalPath() . 'lpexpress_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['HC'],
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['CC'],
                ],
                'allZones' => false,
            ],
            [
                'name' => 'Delivery of shipments to post office (LP EXPRESS)',
                'name_translation' => $this->l('Delivery of shipments to post office (LP EXPRESS)'),
                'logo' => $this->getLocalPath() . 'lpexpress_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_EXPRESS_CARRIER_POST',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['AB'],
                ],
                'allZones' => false,
            ],
            [
                'name' => 'Delivery of shipments post office (LP)',
                'name_translation' => $this->l('Delivery of shipments to post office (LP)'),
                'logo' => $this->getLocalPath() . 'lp_logo.png',
                'delay' => $this->l('Delivery in 1-2 business days.'),
                'configuration_name' => 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST',
                'default_shipping_templates' => [
                    AdminLPShippingConfigurationController::SHIPMENT_TEMPLATES['LP_DEFAULT'],
                ],
                'allZones' => false,
            ],
        ];
    }

    /**
     * Get selected by admin carriers
     *
     * @return array
     */
    public function getFilteredCarriers()
    {
        $allCarriers = $this->getCarriers();
        $availableCarriers = unserialize(Configuration::get('LP_SHIPPING_SHIPMENT_SENDING_TYPES', 'a:0{}'));

        $temp = [];
        foreach ($allCarriers as $carrier) {
            if (!in_array($carrier['configuration_name'], array_values($availableCarriers))) {
                continue;
            }

            $temp[] = $carrier;
        }

        return $temp;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     * registerStylesheet() or registerJavascript() does not work with BO controllers
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->setMedia();
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');
    }

    public function hookActionAdminControllerSetMedia()
    {
        $currentController = Tools::getValue('controller');
        if ('AdminOrders' === $currentController) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/adminOrder.js');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader($params)
    {
        $isPs17 = version_compare(_PS_VERSION_, '1.7.0', '>=');
        if ($this->context->controller instanceof OrderController) {
            Media::addJsDef([
                'isPs17' => $isPs17,
                'LPShippingToken' => Tools::getToken(false),
                'LPShippingAjax' => $this->context->link->getModuleLink($this->name, 'ajax'),
                'LPShippingExpressCarrierTerminal' => Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL'),
                'LPShippingExpressCarrierPost' => Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_POST'),
                'LPShippingExpressCarrierHome' => Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_HOME'),
                'LPShippingExpressCarrierAbroad' => Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_ABROAD'),
                'LPShippingCarrierHomeOfficePost' => Configuration::get('LP_SHIPPING_CARRIER_HOME_OFFICE_POST'),
                'LPShippingCarrierAbroad' => Configuration::get('LP_SHIPPING_CARRIER_ABROAD'),
                'LPShippingCartId' => $params['cart']->id,
                'MessageTerminalNotSelected' => $this->l('Please select a terminal'),
            ]);
        }

        if ($isPs17) {
            $this->context->controller->registerJavascript('modules-lpshipping-js', 'modules/' . $this->name . '/views/js/front.js');
            $this->context->controller->registerStylesheet('modules-lpshipping-css', 'modules/' . $this->name . '/views/css/front.css');
            $this->context->controller->registerJavascript('modules-lpshipping-select2-js', 'modules/' . $this->name . '/views/js/select2.min.js');
            $this->context->controller->registerStylesheet('modules-lpshipping-select2-css', 'modules/' . $this->name . '/views/css/select2.min.css');
        } else {
            $this->context->controller->addJS($this->_path . 'views/js/front.js');
            $this->context->controller->addCss($this->_path . 'views/css/front.css');
            $this->context->controller->addJS($this->_path . 'views/js/select2.min.js');
            $this->context->controller->addCss($this->_path . 'views/css/select2.min.css');
        }
    }

    public function hookActionCarrierUpdate($params)
    {
        $this->updateCarrierID($params);
    }

    public function hookUpdateCarrier($params)
    {
        $this->updateCarrierID($params);
    }

    /**
     * Update carrier ID after updating carrier configuration
     * @param array $params
     */
    public function updateCarrierID($params)
    {
        switch ($params['id_carrier']) {
            case Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_HOME'):
                Configuration::updateValue('LP_SHIPPING_EXPRESS_CARRIER_HOME', $params['carrier']->id);
                break;

            case Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_ABROAD'):
                Configuration::updateValue('LP_SHIPPING_EXPRESS_CARRIER_ABROAD', $params['carrier']->id);
                break;

            case Configuration::get('LP_SHIPPING_CARRIER_ABROAD'):
                Configuration::updateValue('LP_SHIPPING_CARRIER_ABROAD', $params['carrier']->id);
                break;

            case Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL'):
                Configuration::updateValue('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL', $params['carrier']->id);
                break;

            case Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_POST'):
                Configuration::updateValue('LP_SHIPPING_EXPRESS_CARRIER_POST', $params['carrier']->id);
                break;

            case Configuration::get('LP_SHIPPING_CARRIER_HOME_OFFICE_POST'):
                Configuration::updateValue('LP_SHIPPING_CARRIER_HOME_OFFICE_POST', $params['carrier']->id);
                break;

            default:
                break;
        }
    }

    /**
     * Hook form to admin order page
     *
     * @param array $params - an array of contextual data e.g. Cart object
     *
     * @return string form - template path
     */
    public function hookDisplayAdminOrder(array $params)
    {
        $lpOrderService = new LPShippingOrderService();
        $errorHandlerInstance = LPShippingRequestErrorHandler::getInstance();
        $data = [];

        if ($err = $errorHandlerInstance->getLastError()) {
            $data['last_error'] = $err['message'];
        }

        if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            $data['id_order'] = $params['id_order'];
        } else {
            $data['id_order'] = Tools::getValue('id_order');
        }

        if ($params['cart']->id) {
            $data['id_cart'] = $params['cart']->id;
        } else {
            $orderObj = new Order($data['id_order']);
            $data['id_cart'] = $orderObj->id_cart;
        }

        $data['carriers'] = $lpOrderService->addTemplatesToCarriers($this->getFilteredCarriers());
        $data['terminals'] = LPShippingTerminal::getTerminals();
        $data['selected_carrier'] = null;
        $data['selected_terminal'] = null;
        $data['label_number'] = 0;
        $data['id_lp_internal_order'] = null;
        $data['id_cart_internal_order'] = null;
        $data['id_manifest'] = null;
        $data['shipping_template_id'] = 0;
        $data['post_address'] = '';
        $data['status'] = null;
        $data['parcel_status'] = null;
        $data['is_shipment_formed'] = false;
        $data['is_call_courier_available'] = false;
        $data['is_order_saved'] = false;
        $data['is_cancellable'] = false;
        $data['are_documents_printable'] = false;
        $data['is_label_printable'] = false;
        $data['is_declaration_printable'] = false;
        $data['is_manifest_printable'] = false;
        $data['is_declaration_cn22_required'] = false;
        $data['is_declaration_cn23_required'] = false;
        $data['declarations'] = [];
        $data['declarations']['parcelTypes'] = [];

        $data['error_messages'] = [
            'number_of_packages' => $this->l('Number of packages field value must be a whole number and more than 0'),
            'weight' => $this->l('Weight field value must be a number'),
            'cod_amount' => $this->l('COD amount field value must be a number'),
            'box_size' => $this->l('Must select a box size'),
            'terminal' => $this->l('Must select a terminal'),
        ];

        // Check if there is order id already saved in database
        $order = $lpOrderService->getOrder($data['id_order']);
        $cart = new Cart($data['id_cart']);
        $address = new Address($cart->id_address_delivery);
        $cartTotal = $cart->getOrderTotal();

        $addressData = $lpOrderService->formAddress($address);

        if ($addressData) {
            $addressString = $addressData['street'] . ', ' . $addressData['locality'] . ', ' . $addressData['postalCode'];
        } else {
            $addressString = $this->l('Address could not be verified successfully');
        }
        $data['post_address'] = $addressString;

        $showSenderAddress = false;
        if ($order['selected_carrier'] == LpShippingCourierConfigNames::LP_SHIPPING_EXPRESS_CARRIER_TERMINAL) {
            if (Configuration::get('LP_SHIPPING_ORDER_TERMINAL_TYPE') === 'HC') {
                $showSenderAddress = true;
            }
        } else {
            if (Configuration::get('LP_SHIPPING_ORDER_HOME_TYPE') === 'EBIN') {
                $showSenderAddress = true;
            }
        }
        if ($order == null) {
            return;
        }

        $shippingTemplateId = $order['shipping_template_id'];

        $data['label_number'] = $order['label_number'];
        $data['id_cart'] = $order['id_cart'];
        $data['selected_carrier'] = $order['selected_carrier'];
        $data['selected_terminal'] = $order['id_lpexpress_terminal'];
        $data['default_box_size'] = Configuration::get('LP_SHIPPING_TERMINAL_PACKAGE_SIZE');
        $data['is_cod_selected'] = $order['cod_selected'];
        $data['weight'] = $order['weight'];
        $data['number_of_packages'] = $order['number_of_packages'];
        $data['cod_available'] = $order['cod_available'];
        $data['cod_amount'] = $order['cod_amount'];
        $data['id_lp_internal_order'] = $order['id_lp_internal_order'];
        $data['id_cart_internal_order'] = $order['id_cart_internal_order'];
        $data['shipping_template_id'] = $shippingTemplateId;
        $data['id_lpshipping_order'] = $order['id_lpshipping_order'];
        $data['status'] = $this->getOrderPossibleStatuses()[$order['status']];
        $data['is_shipment_formed'] = $lpOrderService->isShipmentFormed($order['status']);
        $data['is_call_courier_available'] = $lpOrderService->isCallCourierAvailable($order);
        $data['is_order_saved'] = $lpOrderService->isOrderSaved($order['status']);
        $data['is_cancellable'] = $lpOrderService->isFormedShipmentCancellable($order);
        $data['are_documents_printable'] = $lpOrderService->canPrintDocuments($order);
        $data['is_label_printable'] = $lpOrderService->canPrintLabel($order);
        $data['is_all_printable'] = $lpOrderService->canPrintLabel($order);
        $data['is_declaration_printable'] = $lpOrderService->canPrintDeclaration($order);
        $data['is_manifest_printable'] = $lpOrderService->canPrintManifest($order);
        $data['show_sender_address'] = $showSenderAddress;
        $data['sender_locality'] = $order['sender_locality'];
        $data['sender_street'] = $order['sender_street'];
        $data['sender_building'] = $order['sender_building'];
        $data['sender_postal_code'] = $order['sender_postal_code'];
        $data['sender_country'] = $order['sender_country'];

        $country = new Country($address->id_country);
//
        $data['is_declaration_cn22_required'] = $lpOrderService->isDeclarationCN22Required($shippingTemplateId, $country->iso_code, $cartTotal);
        $data['is_declaration_cn23_required'] = $lpOrderService->isDeclarationCN23Required($shippingTemplateId, $country->iso_code, $cartTotal);

        if ($data['is_declaration_cn22_required'] || $data['is_declaration_cn23_required']) {
            $data['declarations']['parcelTypes'] = $this->getParcelPossibleTypes($order);
        }

        $orderDocument = $lpOrderService->getDocuments($order[LPShippingDocument::PARENT_KEY]);
        if (empty($orderDocument)) {
            $orderDocument['cn_parts_amount'] = ceil($cartTotal);
            $orderDocument['cn_parts_country_code'] = $country->iso_code;
            $orderDocument['cn_parts_currency_code'] = Currency::getDefaultCurrency()->iso_code;
            $orderDocument['cn_parts_quantity'] = 1;
            $orderDocument['cn_parts_weight'] = $order['weight'];
            $orderDocument['parcel_type'] = 'SELL';
            $orderDocument['parcel_notes'] = 'Sell Items';
        }
        $data = array_merge($data, $orderDocument);

        $this->smarty->assign($data);

        return $this->display($this->getLocalPath(), 'blockinorder.tpl');
    }


    /**
     * Get order possible statuses
     *
     * @return array
     */
    public function getOrderPossibleStatuses()
    {
        return [
            LPShippingOrder::ORDER_STATUS_FORMED => $this->l('Shipment is formed'),
            LPShippingOrder::ORDER_STATUS_NOT_FORMED => $this->l('Shipment is not formed'),
            LPShippingOrder::ORDER_STATUS_SAVED => $this->l('Order saved'),
            LPShippingOrder::ORDER_STATUS_NOT_SAVED => $this->l('Order is not saved'),
            LPShippingOrder::ORDER_STATUS_COURIER_CALLED => $this->l('The courier is called'),
            LPShippingOrder::ORDER_STATUS_COURIER_NOT_CALLED => $this->l('The courier is not called'),
            LPShippingOrder::ORDER_STATUS_COMPLETED => $this->l('Order completed'),
        ];
    }

    /**
     * Get order possible parcel types
     *      * @return array
     */
    public function getParcelPossibleTypes(array $orderData)
    {
        $lpOrderService = new LPShippingOrderService();

        $types = [
            'GIFT' => $this->l('a Gift'),
            'DOCUMENT' => $this->l('a Document'),
            'SAMPLE' => $this->l('a Sample of an item'),
            'SELL' => $this->l('Merchandise'),
            'RETURN' => $this->l('Goods to be returned'),
        ];

        if ($lpOrderService->getOrderServiceType($orderData) == $lpOrderService::LP_SERVICE) {
            $types['OTHER'] = $this->l('Other');
        }

        return $types;
    }

    /**
     * Hooks once into carriers selection page, for 1.6 compatibility
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCarrierList($params)
    {
        if (!version_compare(_PS_VERSION_, '1.7', '>=')) {
            return $this->hookDisplayCarrierExtraContent($params);
        }
    }

    /**
     * Set Display content for each carrier separated
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCarrierExtraContent(array $params)
    {
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $idCarrier = $params['carrier']['id'];
        } else {
            $idCarrier = $params['cart']->id_carrier;
        }

        switch ((int)$idCarrier) {
            case (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL'):
                return $this->getTerminalsContent($params);

            case (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_ABROAD'):
                return $this->getAbroadContent($params);

            case (int)Configuration::get('LP_SHIPPING_CARRIER_ABROAD'):
                return $this->getAbroadContent($params);

            case (int)Configuration::get('LP_SHIPPING_CARRIER_HOME_OFFICE_POST'):
                return $this->getHomeOfficePostContent($params);

            case (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_POST'):
                return $this->getHomeOfficePostContent($params);

            case (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_HOME'):
                return $this->getHomeOfficePostContent($params);

            default:
                break;
        }
    }

    /**
     * Get content for terminals delivery type carrier
     *
     * @param array @params
     *
     * @return display(...)
     */
    private function getTerminalsContent(array $params)
    {
        $data = [];

        $data['error'] = null;
        $data['terminals'] = LPShippingTerminal::getTerminals(true, 'city', Configuration::get('LP_SHIPPING_TERMINAL_PACKAGE_SIZE'));
        $data['id_cart'] = $params['cart']->id;
        $data['select_terminal_message'] = $this->l('Select terminal');
        if (empty($data['terminals'])) {
            $data['error'] = $this->l('Failed to load terminals');
        }

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $data['id_carrier'] = $params['carrier']['id'];
            $this->context->smarty->assign($data);

            return $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrierTerminals.tpl');
        } else {
            $data['id_carrier'] = $params['carrier']['id'];
            $this->context->smarty->assign($data);
            $data['terminals_content'] = $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrierTerminals.tpl');
            $data['id_carrier'] = $params['cart']->id_carrier;
            $data['id_address'] = $params['cart']->id_address_delivery;
            $this->context->smarty->assign($data);

            return $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrierTerminals_1-6.tpl');
        }
    }

    /**
     * Get content for abroad delivery type carrier
     *
     * @param array @params
     *
     * @return display(...)
     */
    private function getAbroadContent(array $params)
    {
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $data['id_carrier'] = $params['carrier']['id'];
        } else {
            $data['id_carrier'] = $params['cart']->id_carrier;
        }

        $data['error'] = null;
        $data['chosen_sending_type'] = Configuration::get('LP_SHIPPING_ORDER_HOME_TYPE');
        $data['available_countries_url'] = 'https://www.lpexpress.lt/Verslui/';
        $data['available_countries_text'] = $this->l('Possible delivery countries');
        $data['check_available_countries'] = false;
        $data['id_cart'] = $params['cart']->id;
        $address = new Address($params['cart']->id_address_delivery);
        $country = new Country($address->id_country);

        if ($country->iso_code !== 'LT' && $data['chosen_sending_type'] === 'CHCA') {
            $data['check_available_countries'] = true;
        }

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrierAbroad.tpl');
    }

    /**
     * Get content for home/office/post delivery type carrier
     *
     * @param array @params*
     *
     * @return display(...)
     */
    private function getHomeOfficePostContent(array $params)
    {
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $data['id_carrier'] = $params['carrier']['id'];
        } else {
            $data['id_carrier'] = $params['cart']->id_carrier;
        }

        $data['error'] = null;
        $data['is_valid'] = false;
        $lpOrderService = new LPShippingOrderService();
        $data['id_cart'] = $params['cart']->id;

        $formedAddress = $lpOrderService->formAddress(new Address($params['cart']->id_address_delivery));

        if (LPShippingRequest::authenticate()) {
            $isPostCodeValid = LPShippingRequest::validatePostCode($formedAddress);

            if (!$isPostCodeValid) {
                $data['error'] = $this->l('Could not validate post code');
            }

            $data['custom_address'] = $formedAddress;
        }

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrierHomeOfficePost.tpl');
    }

    /**
     * Hook is called when order is validated, not necessarily been paid
     *
     * @param array $params array(
     * 'cart' => (object) Cart,
     * 'order' => (object) Order,
     * 'customer' => (object) Customer,
     * 'currency' => (object) Currency,
     * 'orderStatus' => (object) OrderState
     * );
     *
     */
    public function hookActionValidateOrder(array $params)
    {
        $cart = $params['cart'];
        $order = $params['order'];

        $lpOrderArray = LPShippingOrder::getOrderByCartId($cart->id);
        $lpOrder = new LPShippingOrder($lpOrderArray['id_lpshipping_order']);

        if (Validate::isLoadedObject($lpOrder)) {
            $lpOrder->id_order = $order->id;

            $lpOrder->update();
        }
    }

    public function hookActionCarrierProcess($params) {
        return $this->hookActionValidateStepComplete($params, false);
    }

    public function hookActionValidateStepComplete($params, $isPs17 = true)
    {
        if ($isPs17 && $params['step_name'] !== 'delivery') {
            return true;
        }

        $terminal = Tools::getValue('lpshipping_express_terminal');
        if ((int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL') === (int)$params['cart']->id_carrier && (int)$terminal == -1){
            $params['completed'] = false;
            $this->context->controller->errors[] =
                $this->l('Please select terminal.');

            return false;
        }

        $carrier = new Carrier($params['cart']->id_carrier);
        if ($carrier->external_module_name === $this->name) {
            $orderData = [
                'selectedCarrierId' => $carrier->id,
                'cartId' => $params['cart']->id,
                'terminalId' => Tools::getValue('lpshipping_express_terminal')

            ];
            if ($this->saveOrder($orderData)) {
                return true;
            }

            $this->context->controller->errors[] = $this->l('Failed to save carrier option. Please try again.');
            $params['completed'] = false;

            return false;
        }
    }

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
                'cn_parts_currency_code' => 'EUR',
                'cn_parts_weight' => $cart->getTotalWeight(),
                'cn_parts_quantity' => $cart->getNbProducts($orderData['cartId']),
                'cn_parts_summary' => ''
            ];
            $lpOrderService->updateShippingItemWithDeclaration($declarationData);

            return true;
        }

        return false;
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
        $availableCarriers = $this->getFilteredCarriers();

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

    public function getOrderShippingCost($cart, $shippingCost)
    {
        // This method is still called when module is disabled so we need to do a manual check here
        if (!$this->active) {
            return false;
        }

        $carrier = new Carrier($this->id_carrier);
        if ($carrier->external_module_name !== $this->name) {
            return true;
        }

        $deliveryAddress = new Address($cart->id_address_delivery);
        $country = new Country($deliveryAddress->id_country);
        if ($country->iso_code === 'LT') {
            if (
                (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_HOME') ||
                (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_ABROAD') ||
                (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_TERMINAL') ||
                (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_POST')
            ) {
                return $shippingCost;
            }

            return false;
        }
        if (
            (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_CARRIER_ABROAD') ||
            (int)$carrier->id === (int)Configuration::get('LP_SHIPPING_EXPRESS_CARRIER_ABROAD')
        ) {
            return $shippingCost;
        }

        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        $this->getOrderShippingCost($params, 0);
    }
}
