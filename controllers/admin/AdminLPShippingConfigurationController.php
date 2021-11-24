<?php

require_once(dirname(__FILE__) . '../../../api/LPShippingRequest.php');
require_once(dirname(__FILE__) . '../../../classes/LPShippingTerminal.php');
require_once(dirname(__FILE__) . '../../../classes/LPShippingItemTemplate.php');
require_once(dirname(__FILE__) . '../../../classes/LPShippingConsts.php');

class AdminLPShippingConfigurationController extends ModuleAdminController
{
    const CLASS_NAME = 'AdminLPShippingConfigurationController';

    const CONFIGURATION_KEYS = [
        'LP_SHIPPING_LIVE_MODE',
        'LP_SHIPPING_ACCOUNT_EMAIL',
        'LP_SHIPPING_ACCOUNT_PASSWORD',
        'LP_SHIPPING_COMPANY_NAME',
        'LP_SHIPPING_COMPANY_PHONE',
        'LP_SHIPPING_COMPANY_EMAIL',
        'LP_SHIPPING_COUNTRY_CODE',
        'LP_SHIPPING_CITY',
        'LP_SHIPPING_STREET',
        'LP_SHIPPING_BUILDING_NUMBER',
        'LP_SHIPPING_FLAT_NUMBER',
        'LP_SHIPPING_POST_CODE',
        'LP_SHIPPING_SERVICE_ACTIVE',
        'LP_SHIPPING_EXPRESS_SERVICE_ACTIVE',
        'LP_SHIPPING_CALL_COURIER_ACTIVE', // @TODO Possible to check true if only LP Express contract is active
        'LP_SHIPPING_SHIPMENT_SENDING_TYPES',
        'LP_SHIPPING_SHIPMENT_PRIORITY',
        'LP_SHIPPING_SHIPMENT_REGISTERED',
        'LP_SHIPPING_STICKER_SIZE',
        'LP_SHIPPING_ORDER_TERMINAL_TYPE',
        'LP_SHIPPING_ORDER_HOME_TYPE',
        'LP_SHIPPING_ORDER_POST_TYPE',
        'LP_SHIPPING_ORDER_POST_TYPE_FOREIGN',
        'LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE',
        'LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES',
        'LP_SHIPPING_EXPRESS_ORDER_TYPE',
    ];

    const SHIPMENT_TEMPLATES = [
        'CHCA' => [
            'name' => 'CHCA',
            'allow_size_selection' => true,
            'template_ids' => [
                49, 50, 51, 52, 53
            ]
        ],
        'EBIN' => [
            'name' => 'EBIN',
            'allow_size_selection' => true,
            'template_ids' => [
                45
            ]
        ],
        'HC' => [
            'name' => 'HC',
            'allow_size_selection' => true,
            'template_ids' => [
                54, 55, 56, 57, 58
            ]
        ],
        'CC' => [
            'name' => 'CC',
            'allow_size_selection' => true,
            'template_ids' => [
                59, 60, 61, 62, 63
            ]
        ],
        'LP_ABROAD' => [
            'name' => 'LP_ABROAD',
            'allow_size_selection' => true,
            'template_ids' => [
                42, 43, 44, 66, 67
            ]
        ],
        'LP_DEFAULT' => [
            'name' => 'LP_DEFAULT',
            'allow_size_selection' => true,
            'template_ids' => [
                42, 43, 44
            ]
        ],
        'AB' => [
            'name' => 'AB',
            'allow_size_selection' => true,
            'template_ids' => [
                46
            ]
        ],
    ];

    /**
     * @final string
     */
    const TERMINALS_UPDATE_KEY = 'LP_SHIPPING_TERMINALS_UPDATED';

    /**
     * @final string
     */
    const SHIPPING_TEMPLATES_UPDATE_KEY = 'LP_SHIPPING_SHIPPING_TEMPLATES_UPDATED';

    /**
     * @final string
     */
    const CARRIERS_ASSOCIATION_WITH_TEMPLATES_KEY = 'LP_SHIPPING_CARRIERS_ASSOCIATION_UPDATED';

    /**
     * @var LPShipping
     */
    private $moduleInstance = null;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        $this->moduleInstance = Module::getInstanceByName('lpshipping');
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
    }

    /**
     * Renders admin configuration form
     */
    public function renderList()
    {
        if (LPShippingRequest::authenticate()) {
            $token = Configuration::get('LP_SHIPPING_CRON_TOKEN');

            $terminalsUrl = $this->context->link->getModuleLink($this->module->name, 'cron', ['action' => 'updateTerminals', 'token' => $token]);
            $terminalCronCommand = "0 0 * * 6 path/to/curl --silent \"{$terminalsUrl}\"";
            $this->displayInformation($this->module->l('Add this link to cron tasks list for automatic terminals update: ', self::CLASS_NAME) . '<b>' . $terminalsUrl . '</b><br>');
            $this->displayInformation($this->module->l('Terminal updating should be executed once a week or less. Cron command could look like this: ', self::CLASS_NAME) . '<b>' . $terminalCronCommand . '</b><br><br>');

            $trackingUrl = $this->context->link->getModuleLink($this->module->name, 'cron', ['action' => 'getTrackingInformation', 'token' => $token]);
            $trackingCronCommand = "0 */4 * * * path/to/curl --silent \"{$trackingUrl}\"";
            $this->displayInformation($this->module->l('Add this link to cron tasks list for automatic shipment status update: ', self::CLASS_NAME) . '<b>' . $trackingUrl . '</b><br>');
            $this->displayInformation($this->module->l('Status updating should be executed every day in 4 hours interval. Cron command could look like this: ', self::CLASS_NAME) . '<b>' . $trackingCronCommand . '</b>');
        }


        $sendingOptions = array();
        foreach ($this->getSendingOptions() as $key => $option) {
            $sendingOptions[$key] = $option;
        }

        $stickerSizes = array();
        foreach ($this->getStickerSizes() as $key => $option) {
            $stickerSizes[$key] = [
                'title' => $option,
                'hint' => '',
            ];
        }

        $fields = [
            'general_info' => [
                'title' => $this->module->l('General', self::CLASS_NAME),
                'fields' => [
                    'LP_SHIPPING_LIVE_MODE' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Live mode', self::CLASS_NAME)
                    ],
                    'LP_SHIPPING_ACCOUNT_EMAIL' => [
                        'type' => 'text',
                        'title' => $this->module->l('API username', self::CLASS_NAME) . ' *',
                        'hint' => $this->module->l('Issued by LP service provider', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_ACCOUNT_PASSWORD' => [
                        'type' => 'text',
                        'title' => $this->module->l('API password', self::CLASS_NAME) . ' *',
                        'hint' => $this->module->l('Issued by LP service provider', self::CLASS_NAME),
                    ],
                ],
                'submit' => [
                    'name' => 'submitLPShippingConfig',
                    'title' => $this->module->l('Save', self::CLASS_NAME)
                ],
            ],
            'sender_information' => [
                'title' => $this->module->l('Sender information', self::CLASS_NAME),
                'fields' => [
                    'LP_SHIPPING_COMPANY_NAME' => [
                        'title' => $this->module->l('Sender name', self::CLASS_NAME) . ' *',
                        'type' => 'text',
                        'hint' => $this->module->l('Will be displayed in the consignment documents (stickers, customs declarations)', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_COMPANY_PHONE' => [
                        'title' => $this->module->l('Mobile phone number', self::CLASS_NAME) . ' *',
                        'type' => 'text',
                        'prefix' => '370',
                        'hint' => 'Format must match the following format: 370XXXXXXXX without 370'
                    ],
                    'LP_SHIPPING_COMPANY_EMAIL' => [
                        'title' => $this->module->l('Company email', self::CLASS_NAME) . ' *',
                        'type' => 'text'
                    ],
                    'LP_SHIPPING_COUNTRY_CODE' => [
                        'title' => $this->module->l('Country Code', self::CLASS_NAME) . ' *',
                        'type' => 'text',
                        'desc' => $this->module->l('Required format is ', self::CLASS_NAME) . '<a target="_blank" href="https://www.nationsonline.org/oneworld/country_code_list.htm">ISO 3166-1 alpha-2</a>'
                    ],
                    'LP_SHIPPING_CITY' => [
                        'title' => $this->module->l('City', self::CLASS_NAME) . ' *',
                        'type' => 'text'
                    ],
                    'LP_SHIPPING_STREET' => [
                        'title' => $this->module->l('Street', self::CLASS_NAME) . ' *',
                        'type' => 'text',
                        'hint' => $this->module->l('E.g. Jasinskio g.', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_BUILDING_NUMBER' => [
                        'title' => $this->module->l('Building number', self::CLASS_NAME) . ' *',
                        'type' => 'text'
                    ],
                    'LP_SHIPPING_FLAT_NUMBER' => [
                        'title' => $this->module->l('Flat number', self::CLASS_NAME),
                        'type' => 'text'
                    ],
                    'LP_SHIPPING_POST_CODE' => [
                        'title' => $this->module->l('Post code', self::CLASS_NAME) . ' *',
                        'type' => 'text',
                        'desc' => $this->module->l('Find your post code ', self::CLASS_NAME)
                            . '<a target="_blank" href="https://www.post.lt/pasto-kodu-ir-adresu-paieska">'
                            . $this->module->l('here', self::CLASS_NAME) . '</a>',
                    ],
                ],
                'submit' => [
                    'name' => 'submitLPShippingConfig',
                    'title' => $this->module->l('Save', self::CLASS_NAME)
                ],
            ],
            'services_configuration' => [
                'title' => $this->module->l('Services Configuration', self::CLASS_NAME),
                'fields' => [
                    'LP_SHIPPING_SERVICE_ACTIVE' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Activate LP services', self::CLASS_NAME)
                    ],
                    'LP_SHIPPING_EXPRESS_SERVICE_ACTIVE' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Activate LP Express services', self::CLASS_NAME)
                    ],
                    'LP_SHIPPING_CALL_COURIER_ACTIVE' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Invite courier automatically', self::CLASS_NAME),
                        'hint' => $this->module->l('Option is valid only for LP EXPRESS services.', self::CLASS_NAME) . ' ' . $this->module->l('Contact LP service provider to use this service.', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_SHIPMENT_PRIORITY' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Shipments are prioritized', self::CLASS_NAME),
                        'hint' => $this->module->l('Option is valid only for LP services', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_SHIPMENT_REGISTERED' => [
                        'type' => 'bool',
                        'title' => $this->module->l('Shipments are registered', self::CLASS_NAME),
                        'hint' => $this->module->l('Option is valid only for LP services', self::CLASS_NAME),
                    ],
                    'LP_SHIPPING_SHIPMENT_SENDING_TYPES' => [
                        'type' => 'multiple_checkboxes',
                        'title' => $this->module->l('Possible shipment delivery types for shop users', self::CLASS_NAME) . ' *',
                        'auto_value' => false,
                        'value' => unserialize((string) Configuration::get('LP_SHIPPING_SHIPMENT_SENDING_TYPES')),
                        'choices' => $sendingOptions,
                    ],
                    'LP_SHIPPING_STICKER_SIZE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Sticker size', self::CLASS_NAME) . ' *',
                        'choices' => $stickerSizes,
                    ],
                ],

                'submit' => [
                    'name' => 'submitLPShippingConfig',
                    'title' => $this->module->l('Save', self::CLASS_NAME)
                ],
            ],
            'order_configuration' => [
                'title' => $this->module->l('Shipment sending and delivery configuration', self::CLASS_NAME),
                'fields' => [
                    'LP_SHIPPING_ORDER_TERMINAL_TYPE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Terminal order type', self::CLASS_NAME) . ' *',
                        'choices' => [
                            'HC' => [
                                'title' => $this->module->l('From sender to LP Express terminal', self::CLASS_NAME),
                                'hint' => $this->module->l('Shipment from home or office to LP EXPRESS parcel self-service terminal.', self::CLASS_NAME)
                            ],
                            'CC' => [
                                'title' => $this->module->l('From sender terminal to address terminal', self::CLASS_NAME),
                                'hint' => $this->module->l('Shipment, sending and delivery to the LP EXPRESS parcel self-service terminal.', self::CLASS_NAME)
                            ]
                        ],
                    ],
                    'LP_SHIPPING_ORDER_HOME_TYPE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Address order type', self::CLASS_NAME) . ' *',
                        'choices' => [
                            'EBIN' => [
                                'title' => $this->module->l('From sender to receiver address', self::CLASS_NAME),
                                'hint' => $this->module->l('Shipment from home or office, delivered by LP EXPRESS courier.', self::CLASS_NAME)
                            ],
                            'CHCA' => [
                                'title' => $this->module->l('From sender terminal to receiver address', self::CLASS_NAME),
                                'hint' => $this->module->l("Shipment from LP EXPRESS parcel self-service terminal, recipient's address.", self::CLASS_NAME)
                            ]
                        ],
                    ],
                    'LP_SHIPPING_ORDER_POST_TYPE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Post office order type', self::CLASS_NAME) . ' *',
                        'choices' => [
                            'SMALL_CORESPONDENCE' => [
                                'title' => $this->module->l("(S) Small items of correspondence, from the post office, to the recipient's address", self::CLASS_NAME),
                            ],
                            'BIG_CORESPONDENCE' => [
                                'title' => $this->module->l("(M) Large consignment of correspondence, from the post office, to the recipient's address.", self::CLASS_NAME),
                            ],
                            'PACKAGE' => [
                                'title' => $this->module->l("(L) Parcel, from the post office, to the recipient's address", self::CLASS_NAME),
                            ],
                        ],
                    ],
                    'LP_SHIPPING_ORDER_POST_TYPE_FOREIGN' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Post office order type to foreign countries', self::CLASS_NAME) . ' *',
                        'choices' => [
                            'SMALL_CORESPONDENCE' => [
                                'title' => $this->module->l("(S) Small items of correspondence, from the post office, to the recipient's address", self::CLASS_NAME),
                            ],
                            'BIG_CORESPONDENCE' => [
                                'title' => $this->module->l("(M) Large consignment of correspondence, from the post office, to the recipient's address.", self::CLASS_NAME),
                            ],
                            'PACKAGE' => [
                                'title' => $this->module->l("(L) Parcel, from the post office, to the recipient's address", self::CLASS_NAME),
                            ],
                            'SMALL_CORESPONDENCE_TRACKED' => [
                                'title' => $this->module->l("(S) Tracked. Small items of correspondence, from the post office, to the recipient's address", self::CLASS_NAME),
                            ],
                            'MEDIUM_CORESPONDENCE_TRACKED' => [
                                'title' => $this->module->l("(M) Tracked. Large consignment of correspondence, from the post office, to the recipient's address.", self::CLASS_NAME),
                            ],
                        ]
                    ],
                    'LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('Terminal package size', self::CLASS_NAME) . ' *',
                        'choices' => [
                            'XSmall' => [
                                'title' => $this->module->l("XSmall", self::CLASS_NAME),
                            ],
                            'Small' => [
                                'title' => $this->module->l("Small", self::CLASS_NAME),
                            ],
                            'Medium' => [
                                'title' => $this->module->l("Medium", self::CLASS_NAME),
                            ],
                            'Large' => [
                                'title' => $this->module->l("Large", self::CLASS_NAME),
                            ],
                            'XLarge' => [
                                'title' => $this->module->l("XLarge", self::CLASS_NAME),
                            ],
                        ]
                    ],
                    'LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('LPexpress Post office type to foreign countries', self::CLASS_NAME) . ' *',
                        'choices' => [
                            '45' => [
                                'title' => $this->module->l("Shipment from home or office, delivered by LPEXPRESS courier.", self::CLASS_NAME),
                            ]
                        ]
                    ],
                    'LP_SHIPPING_EXPRESS_ORDER_TYPE' => [
                        'type' => 'radio_hint',
                        'title' => $this->module->l('LPexpres Post office order type', self::CLASS_NAME) . ' *',
                        'choices' => [
                            '46' => [
                                'title' => $this->module->l("Shipment from home or office, delivered to receivers post.", self::CLASS_NAME),
                            ]
                        ]
                    ],
                ],
                'submit' => [
                    'name' => 'submitLPShippingConfig',
                    'title' => $this->module->l('Save', self::CLASS_NAME)
                ],
            ],
        ];

        $helper = new HelperOptions();
        $helper->module = $this->module;
        $helper->id = $this->module->id;
        $helper->token = Tools::getAdminTokenLite('AdminLPShippingConfiguration');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminLPShippingConfiguration');
        $helper->title = $this->module->displayName;

        $this->context->smarty->assign('module_dir', $this->module->getLocalPath());
        $output = $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/configure.tpl');

        return $output . $helper->generateOptions($fields);
    }

    /**
     * Save form data.
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('submitLPShippingConfig')) {
            $this->validateConfigurationFields(Tools::getAllValues());

            if (!count($this->errors)) {
                foreach (self::CONFIGURATION_KEYS as $key) {
                    if ($key == 'LP_SHIPPING_SHIPMENT_SENDING_TYPES') {
                        Configuration::updateValue('LP_SHIPPING_SHIPMENT_SENDING_TYPES', serialize((array) Tools::getValue($key, [])));
                        continue;
                    }

                    Configuration::updateValue($key, Tools::getValue($key));
                }
            }
        }
    }

    /**
     * Validate configuration fields
     * 
     * @param array $config
     */
    private function validateConfigurationFields(array $config)
    {
        $liveMode = $config['LP_SHIPPING_LIVE_MODE'];
        $apiUsername = $config['LP_SHIPPING_ACCOUNT_EMAIL'];
        $apiPassword = $config['LP_SHIPPING_ACCOUNT_PASSWORD'];
        $companyName = $config['LP_SHIPPING_COMPANY_NAME'];
        $companyPhone = $config['LP_SHIPPING_COMPANY_PHONE'];
        $companyEmail = $config['LP_SHIPPING_COMPANY_EMAIL'];
        $companyCountryCode = $config['LP_SHIPPING_COUNTRY_CODE'];
        $companyCity = $config['LP_SHIPPING_CITY'];
        $companyStreet = $config['LP_SHIPPING_STREET'];
        $companyBuildingNumber = $config['LP_SHIPPING_BUILDING_NUMBER'];
        $companyFlatNumber = $config['LP_SHIPPING_FLAT_NUMBER'];
        $companyPostCode = $config['LP_SHIPPING_POST_CODE'];
        $callCourier = $config['LP_SHIPPING_CALL_COURIER_ACTIVE'];
        $lpService = $config['LP_SHIPPING_SERVICE_ACTIVE'];
        $lpExpressService = $config['LP_SHIPPING_EXPRESS_SERVICE_ACTIVE'];
        $shipmentSendingType = isset($config['LP_SHIPPING_SHIPMENT_SENDING_TYPES']) ? $config['LP_SHIPPING_SHIPMENT_SENDING_TYPES'] : [];
        $terminalDeliveryType = isset($config['LP_SHIPPING_ORDER_TERMINAL_TYPE']) ? $config['LP_SHIPPING_ORDER_TERMINAL_TYPE'] : '';
        $addressDeliveryType = isset($config['LP_SHIPPING_ORDER_HOME_TYPE']) ? $config['LP_SHIPPING_ORDER_HOME_TYPE'] : '';
        $postDeliveryType = isset($config['LP_SHIPPING_ORDER_POST_TYPE']) ? $config['LP_SHIPPING_ORDER_POST_TYPE'] : '';
        $postDeliveryTypeForeign = isset($config['LP_SHIPPING_ORDER_POST_TYPE_FOREIGN']) ? $config['LP_SHIPPING_ORDER_POST_TYPE_FOREIGN'] : '';
        $defaultLpExpressBoxSize = isset($config['LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE']) ? $config['LP_SHIPPING_EXPRESS_SERVICE_PACKAGE_SIZE'] : '';
        $defaultLpExpressForeignCountries = isset($config['LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES']) ? $config['LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES'] : '';
        $defaultLpExpressOrderType = isset($config['LP_SHIPPING_EXPRESS_ORDER_TYPE']) ? $config['LP_SHIPPING_EXPRESS_ORDER_TYPE'] : '';

        if (empty(trim($apiUsername)) || empty(trim($apiPassword))) {
            $this->errors[] = $this->module->l('API username and API password are required for authentication', self::CLASS_NAME);
        } else {
            Configuration::updateValue('LP_SHIPPING_ACCOUNT_EMAIL', $apiUsername);
            Configuration::updateValue('LP_SHIPPING_ACCOUNT_PASSWORD', $apiPassword);
            Configuration::updateValue('LP_SHIPPING_LIVE_MODE', $liveMode);
            LPShippingRequest::setApiUrl();

            if (LPShippingRequest::authenticate()) {
                $this->postAuthSetup();
                $this->activateSelectedCarriers($shipmentSendingType);
            } else {
                $this->errors[] = $this->module->l('Failed authentication with LP API services', self::CLASS_NAME);
                $apiError = Configuration::get('LP_SHIPPING_LAST_ERROR');
                try {
                    $apiError = unserialize($apiError);
                } catch (Exception $e) {

                }
                if (isset($apiError['message'])) {
                    $this->errors[] = $apiError['message'];
                    Configuration::updateValue('LP_SHIPPING_LAST_ERROR', '');
                }
            }
        }

        $validityPattern = Tools::cleanNonUnicodeSupport('/^[^!<>,;?=+()@#"°{}_$%:¤|]*$/u');
        if (empty(trim($companyName))) {
            $this->errors[] = $this->module->l('Sender name is required', self::CLASS_NAME);
        } elseif (!preg_match($validityPattern, $companyName)) {
            $this->errors[] = $this->module->l('Invalid name format', self::CLASS_NAME);
        } elseif (Tools::strlen($companyName) > 100) {
            $this->errors[] = $this->module->l('Sender name is too long, 100 symbols are allowed', self::CLASS_NAME);
        }

        $firstNumber = substr($companyPhone, 0, 1);
        if (empty(trim($companyPhone))) {
            $this->errors[] = $this->module->l('Sender phone is required', self::CLASS_NAME);
        } elseif (!Validate::isPhoneNumber($companyPhone) || ($firstNumber != '5' && $firstNumber != '6') || Tools::strlen($companyPhone) != 8) {
            $this->errors[] = $this->module->l('Invalid phone format. Value must match the following format: 370XXXXXXXX without 370, e.g. 61234567', self::CLASS_NAME);
        }

        if (empty(trim($companyEmail))) {
            $this->errors[] = $this->module->l('Sender email is required', self::CLASS_NAME);
        } elseif (!Validate::isEmail($companyEmail)) {
            $this->errors[] = $this->module->l('Invalid company email format', self::CLASS_NAME);
        } elseif (Tools::strlen($companyEmail) > 128) {
            $this->errors[] = $this->module->l('Company email is too long, 128 symbols are allowed', self::CLASS_NAME);
        }

        if (empty(trim($companyCountryCode))) {
            $this->errors[] = $this->module->l('Country code is required', self::CLASS_NAME);
        } elseif (!Validate::isName($companyCountryCode) || Tools::strlen($companyCountryCode) != 2) {
            $this->errors[] = $this->module->l('Invalid country code format. Value must match following format: XX.', self::CLASS_NAME);
        }

        if (empty(trim($companyCity))) {
            $this->errors[] = $this->module->l('City is required', self::CLASS_NAME);
        } elseif (!Validate::isName($companyCity) || Tools::strlen($companyCity) > 200) {
            $this->errors[] = $this->module->l('Invalid city field. Only letters are allowed. 200 symbols are allowed.', self::CLASS_NAME);
        }

        if (empty(trim($companyStreet))) {
            $this->errors[] = $this->module->l('Street is required', self::CLASS_NAME);
        } elseif (!preg_match($validityPattern, $companyStreet) || Tools::strlen($companyStreet) > 50) {
            $this->errors[] = $this->module->l('Invalid street field. Alphanumeric characters are allowed. 50 symbols are allowed.', self::CLASS_NAME);
        }

        if (empty(trim($companyBuildingNumber))) {
            $this->errors[] = $this->module->l('Building number is required', self::CLASS_NAME);
        } elseif (!preg_match($validityPattern, $companyBuildingNumber) || Tools::strlen($companyBuildingNumber) > 20) {
            $this->errors[] = $this->module->l('Invalid building number format. Alphanumeric characters are allowed. 20 symbols are allowed.', self::CLASS_NAME);
        }

        if (Tools::strlen(trim($companyFlatNumber)) > 20) {
            $this->errors[] = $this->module->l('Invalid building number format. 20 symbols are allowed.', self::CLASS_NAME);
        }

        // @TODO LPShipping validate post code via API
        if (empty(trim($companyPostCode))) {
            $this->errors[] = $this->module->l('Postal code is required', self::CLASS_NAME);
        } elseif (1 == 0) {
            $this->errors[] = $this->module->l('Postal code is required', self::CLASS_NAME);
        }

        // @TODO LPShipping check for active services via API and throw error in case service is not allowed
//        if (empty($lpService) && empty($lpExpressService)) {
//            $this->errors[] = $this->module->l('Select at least one service (LP, LP Express) to use', self::CLASS_NAME);
//        }

        // @TODO LPShipping check for active services via API and throw error in case service is not allowed
//        if (empty($shipmentSendingType)) {
//            $this->errors[] = $this->module->l('Select shipment sending type(s)', self::CLASS_NAME);
//        }

        if (empty($defaultLpExpressBoxSize)) {
            $this->errors[] = $this->module->l('Select default LP Express box size', self::CLASS_NAME);
        }

        if (empty($defaultLpExpressForeignCountries)) {
            $this->errors[] = $this->module->l('Select default LP Express type to foreign countries', self::CLASS_NAME);
        }

        if (empty($defaultLpExpressOrderType)) {
            $this->errors[] = $this->module->l('Select default LP Express office order type', self::CLASS_NAME);
        }

        // if (empty($defaultLpBoxSize)) {
        //     $this->errors[] = $this->module->l('Select default LP box size', self::CLASS_NAME);
        // }

        if (empty($terminalDeliveryType)) {
            $this->errors[] = $this->module->l('Select terminal delivery type', self::CLASS_NAME);
        }

        if (empty($addressDeliveryType)) {
            $this->errors[] = $this->module->l('Select address delivery type', self::CLASS_NAME);
        }

        if (empty($postDeliveryType)) {
            $this->errors[] = $this->module->l('Select post delivery type', self::CLASS_NAME);
        }

        if (empty($postDeliveryTypeForeign)) {
            $this->errors[] = $this->module->l('Select post delivery to foreign countries type', self::CLASS_NAME);
        }

        if ($callCourier) {
            // @TODO LPShipping check for active services via API and throw error in case service is not allowed
            if (1 == 0) {
                $this->errors[] = $this->module->l('Must have LP Express contract and active service', self::CLASS_NAME);
            }
        }
    }

    /** 
     * Get possible sending options which depends on activated services
     * 
     * @return array
     */
    private function getSendingOptions()
    {
        // @TODO LPShipping Check activated services
        return [
            'LP_SHIPPING_EXPRESS_CARRIER_HOME' => $this->module->l('Shipment delivery to home or office (LP Express service (courier))', self::CLASS_NAME),
            'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL' => $this->module->l('Shipment delivery to parcel terminal (LP Express service)', self::CLASS_NAME),
            'LP_SHIPPING_EXPRESS_CARRIER_POST' => $this->module->l('Shipment delivery to post (LP Express service)', self::CLASS_NAME),
            'LP_SHIPPING_EXPRESS_CARRIER_ABROAD' => $this->module->l('Shipment delivery to foreign country (LP Express service)', self::CLASS_NAME),
            'LP_SHIPPING_CARRIER_HOME_OFFICE_POST' => $this->module->l('Shipment delivery to home, office or post (LP service)', self::CLASS_NAME),
            'LP_SHIPPING_CARRIER_ABROAD' => $this->module->l('Shipment delivery to foreign country (LP service)', self::CLASS_NAME)
        ];
    }

    /**
     * Get possible sticker sizes for shipments
     *
     * @return array
     */
    private function getStickerSizes()
    {
        return [
            'LAYOUT_MAX' => 'LAYOUT_MAX',
            'LAYOUT_10x15' => 'LAYOUT_10x15'
        ];
    }

    /**
     * Write given settings to ps_configuration table
     * 
     * @param array $settings
     */
    public static function writeConfigurationSettings(array $settings)
    {
        foreach ($settings as $settingsKey => $setting) {
            Configuration::updateValue($settingsKey, $setting);
        }

        LPShippingRequest::setApiUrl(); // refreshes variable in singleton class
    }

    /**
     * Return default configuration settings
     * 
     * @return array
     */
    private static function getDefaultConfigurationSettings()
    {
        $defaultSendingOptions = ['LP_SHIPPING_CARRIER_HOME_OFFICE_POST'];
        $shopName = Configuration::get('PS_SHOP_NAME');

        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $token = Tools::encrypt($shopName);
        } else {
            $token = Tools::hash($shopName);
        }

        return [
            'LP_SHIPPING_URL' => 'https://api-manosiuntos.post.lt/',
            'LP_SHIPPING_URL_TEST' => 'https://api-manosiuntostst.post.lt/',
            'LP_SHIPPING_LIVE_MODE' => true,
            'LP_SHIPPING_CALL_COURIER_ACTIVE' => true,
            'LP_SHIPPING_COUNTRY_CODE' => 'LT',
            'LP_SHIPPING_SHIPMENT_REGISTERED' => true,
            'LP_SHIPPING_SHIPMENT_PRIORITY' => true,
            'LP_SHIPPING_SHIPMENT_SENDING_TYPES' => serialize((array) $defaultSendingOptions),
            'LP_SHIPPING_STICKER_SIZE' => 'layout_max',
            'LP_SHIPPING_ORDER_HOME_TYPE' => 'EBIN',
            'LP_SHIPPING_ORDER_POST_TYPE' => 'SMALL_CORESPONDENCE',
            'LP_SHIPPING_ORDER_POST_TYPE_FOREIGN' => 'SMALL_CORESPONDENCE_TRACKED',
            'LP_SHIPPING_ORDER_TERMINAL_TYPE' => 'HC',
            'LP_SHIPPING_CRON_TOKEN' => $token,
            'LP_SHIPPING_DEFAULT_BOX_SIZE' => 'SMALL',
            'LP_SHIPPING_EXPRESS_FOREIGN_COUNTRIES' => '45',
            'LP_SHIPPING_EXPRESS_ORDER_TYPE' => '46'
        ];
    }

    /**
     * Store default configuration to database
     */
    public static function installConfiguration()
    {
        self::writeConfigurationSettings(self::getDefaultConfigurationSettings());
    }

    /**
     * Remove default configuration from database
     */
    public static function removeConfiguration()
    {
        foreach (self::CONFIGURATION_KEYS as $settingsKey) {
            Configuration::deleteByName($settingsKey);
        }

        Configuration::deleteByName(self::TERMINALS_UPDATE_KEY);
        Configuration::deleteByName(self::SHIPPING_TEMPLATES_UPDATE_KEY);
        Configuration::deleteByName(self::CARRIERS_ASSOCIATION_WITH_TEMPLATES_KEY);
    }

    /**
     * This will work only after authentication to API. Pull required info from API and write to DB
     */
    private function postAuthSetup()
    {
        if (!Configuration::get(self::TERMINALS_UPDATE_KEY)) {
            $this->updateTerminals();
        }

        if (!Configuration::get(self::SHIPPING_TEMPLATES_UPDATE_KEY)) {
            $this->updateShippingTemplates();
        }
    }

    /**
     * Get terminals from API and write them to Database
     */
    public function updateTerminals()
    {
        $terminals = LPShippingRequest::getTerminals();

        // write terminals
        if (LPShippingTerminal::saveTerminalBatch($terminals)) {
            Configuration::updateValue(self::TERMINALS_UPDATE_KEY, 1);
        };
    }

    /**
     * Get shipping templates from API and write them to Database
     */
    public function updateShippingTemplates()
    {
        $shippingTemplates = LPShippingRequest::getItemShippingTemplates();

        // write templates
        if (LPShippingItemTemplate::saveShippingTemplateBatch($shippingTemplates)) {
            Configuration::updateValue(self::SHIPPING_TEMPLATES_UPDATE_KEY, 1);
        };
    }

    /**
     * Activate selected carriers from configuration window and deactivate not selected ones
     * 
     * @param array $selectedCarriers
     */
    public function activateSelectedCarriers(array $selectedCarriers)
    {
        $allCarriers = $this->moduleInstance->getCarriers();

        foreach ($allCarriers as $carrier) {
            $carrierId = Configuration::get($carrier['configuration_name']);
            $psCarrier = new Carrier($carrierId);

            if (in_array($carrier['configuration_name'], $selectedCarriers)) {
                $psCarrier->active = true;
            } else {
                $psCarrier->active = false;
            }

            $psCarrier->update();
        }
    }
}
