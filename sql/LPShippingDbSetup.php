<?php

class LPShippingDbSetup
{
    private $controllers = [
        'AdminLPShippingConfiguration',
        'AdminLPShippingOrder',
        'AdminLPShippingOrderFormed',
        'AdminLPShippingOrderSaved',
    ];

    private $module;

    /**
     * Construct object
     */
    public function __construct() {
        $this->module = Module::getInstanceByName('lpshipping');
    }

    /**
     * All queries required for DP setup
     * 
     * @return array
     */
    private function getInstallQueries()
    {
        $sql = array();

        // terminals table
        $sql[] = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "lpshipping_express_terminal (
            `id_lpexpress_terminal` INT NOT NULL AUTO_INCREMENT,
            `terminal_id` VARCHAR(4) NOT NULL,
            `active` BOOLEAN NOT NULL DEFAULT 1,
            `name` VARCHAR(128) NOT NULL,
            `address` VARCHAR(128) NOT NULL,
            `zip` VARCHAR(10) NOT NULL,
            `city` VARCHAR(128) NOT NULL,
            `comment` VARCHAR(256) NOT NULL,
            `boxes` VARCHAR(256) NOT NULL,
            `working_hours` VARCHAR(256) NOT NULL,
            `servicing_hours` VARCHAR(256) NOT NULL,
            `latitude` DECIMAL(10, 8) NOT NULL,
            `longitude` DECIMAL(10, 8) NOT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_lpexpress_terminal`),
            INDEX (`id_lpexpress_terminal`, `terminal_id`, `city`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "DEFAULT CHARSET=utf8;";

        // shipping templates
        $sql[] = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "lpshipping_shipping_template (
            `id_lpshipping_shipping_template` INT NOT NULL AUTO_INCREMENT,
            `template_id` VARCHAR(4) NOT NULL,
            `weight` DECIMAL(10, 2) NOT NULL,
            `part_count` SMALLINT NOT NULL,
            `type` VARCHAR(256) NOT NULL,
            `title` VARCHAR(256) NOT NULL,
            `size` VARCHAR(256) NULL,
            `summary` VARCHAR(256) NULL,
            `description` VARCHAR(512) NULL,
            `additional_services` VARCHAR(1024) NULL,
            `sender` VARCHAR(1024) NULL,
            `receiver` VARCHAR(1024) NULL,
            PRIMARY KEY  (`id_lpshipping_shipping_template`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "DEFAULT CHARSET=utf8;";

        // orders associated with lp shipping module carrier; id_order_pdf is a field for barcode
        $sql[] = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "lpshipping_order (
            `id_lpshipping_order` INT NOT NULL AUTO_INCREMENT,
            `id_cart` INT NOT NULL,
            `id_order` INT NULL,
            `id_lpexpress_terminal` INT NULL,
            `status` VARCHAR(64) NULL,
            `selected_carrier` VARCHAR(256) NOT NULL,
            `weight` DECIMAL(20, 6) NOT NULL,
            `number_of_packages` INT NOT NULL DEFAULT 1,
            `shipping_template_id` INT NOT NULL DEFAULT 0,
            `cod_available` BOOLEAN NOT NULL DEFAULT 1,
            `cod_selected` BOOLEAN NOT NULL DEFAULT 0,
            `cod_amount` DECIMAL(20,6) NULL,
            `label_number` VARCHAR(64) NULL,
            `parcel_status` VARCHAR(128) NULL,
            `id_lp_internal_order` VARCHAR(64) NULL,
            `id_cart_internal_order` VARCHAR(64) NULL,
            `id_manifest` VARCHAR(64) NULL,
            `post_address` VARCHAR(128) NOT NULL,
            `sender_locality` VARCHAR(128) NOT NULL,
            `sender_street` VARCHAR(128) NOT NULL,
            `sender_building` VARCHAR(128) NOT NULL,
            `sender_postal_code` VARCHAR(128) NOT NULL,
            `sender_country` VARCHAR(128) NOT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_lpshipping_order`),
            INDEX (`id_cart`, `id_order`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "DEFAULT CHARSET=utf8;";

        // documents associated with lp shipping module order
        $sql[] = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "lpshipping_order_document (
            `id_lpshipping_order_document` INT NOT NULL AUTO_INCREMENT,
            `id_lpshipping_order` INT NOT NULL,
            `parcel_type` VARCHAR(64) NOT NULL,
            `parcel_type_notes` VARCHAR(512) NOT NULL,
            `description` VARCHAR(512) NULL,
            `notes` VARCHAR(512) NULL,
            PRIMARY KEY (`id_lpshipping_order_document`),
            INDEX (`id_lpshipping_order`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "DEFAULT CHARSET=utf8;";

        // document parts associated with lp shipping module order documents
        $sql[] = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "lpshipping_document_part (
            `id_lpshipping_document_part` INT NOT NULL AUTO_INCREMENT,
            `id_lpshipping_order_document` INT NOT NULL,
            `country_code` VARCHAR(2) NOT NULL,
            `currency_code` VARCHAR(10) NOT NULL,
            `amount` INT NOT NULL,
            `weight` DOUBLE NOT NULL,
            `quantity` INT NOT NULL,
            `summary` VARCHAR(256) NOT NULL,
            PRIMARY KEY (`id_lpshipping_document_part`),
            INDEX (`id_lpshipping_order_document`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "DEFAULT CHARSET=utf8;";

        $sql[] = "CREATE TABLE IF NOT EXISTS "._DB_PREFIX_ ."lpshipping_cart_terminal (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_cart` INT NOT NULL,
            `id_lpexpress_terminal` INT NOT NULL,
            PRIMARY KEY (`id`),
            INDEX(`id_cart`,`id_lpexpress_terminal`)
        ) ENGINE="._MYSQL_ENGINE_."DEFAULT CHARSET=utf8;";
        
        return $sql;
    }

    /**
     * Write for module required tables to database
     * 
     * @return void|false
     */
    public function install()
    {
        foreach ($this->module->getRequiredTabsForInstallation() as $tab) {
            $this->installTab($tab['class_name'], $tab['parent_class_name'], $tab['name']);
        }

        $queries = $this->getInstallQueries();

        return $this->executeQueries($queries);
    }

    public function installTab($className, $parent, $name, $active = true, $icon = '')
    {
        $idParent = is_int($parent) ? $parent : Tab::getIdFromClassName($parent);

        $moduleTab = new Tab();
        $moduleTab->class_name = $className;
        $moduleTab->id_parent = $idParent;
        $moduleTab->module = 'lpshipping';
        $moduleTab->active = $active;
        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            $moduleTab->name[$language['id_lang']] = $name;
        }

        if (!$moduleTab->save()) {
            return false;
        }

        return true;
    }

    /**
     * All queries required for installed tables removal
     * 
     * @return array
     */
    private function getUninstallQueries()
    {
        $sql = array();

        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_express_terminal';
        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_shipping_template';
        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_order';
        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_order_document';
        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_document_part';
        $sql[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'lpshipping_cart_terminal';

        return $sql;
    }

    /**
     * Remove for module required tables from database
     * 
     * @return void|false
     */
    public function uninstall()
    {
        foreach ($this->controllers as $controller) {
            $this->uninstallTab($controller);
        }

        $queries = $this->getUninstallQueries();

        $this->executeQueries($queries);
    }

    private function uninstallTab($controller)
    {
        $idTab = Tab::getIdFromClassName($controller);
        if (!$idTab) {
            return true;
        }

        $tab = new Tab($idTab);
        if (!$tab->delete()) {
            return false;
        }
    }

    /**
     * Execute all SQL queries
     * 
     * @return void|false
     */
    private function executeQueries($queries)
    {
        foreach ($queries as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }
    }

    /**
     * Install default carriers to database
     * 
     * @param array $carriers
     * 
     * @return bool
     */
    public function installCarriers($carriers, $moduleName) 
    {
        // create zone and apply lithuania country to this zone
//        if ((int) $zoneId > 0) {
//            $zone = new Zone($zoneId);
//        } else {
//            $zone = new Zone();
//            $zone->name = 'Lithuania Custom Zone';
//            $zone->active = true;
//            $zone->save();
//        }

        $zoneId = Zone::getIdByName('Europe');
        if ((int) $zoneId > 0) {
            $europeZone = new Zone($zoneId);
        }

        foreach ($carriers as $carrier_row)
        {
            $carrier = new Carrier();

            $carrier->name = $carrier_row['name'];
            $carrier->active = true;
            $carrier->deleted = false;
            $carrier->is_module = true;
            $carrier->external_module_name = $moduleName;

            $carrier->shipping_handling = false;
            $carrier->shipping_external = true;
            $carrier->range_behavior = false;
            $carrier->need_range = true;
            $carrier->is_free = false;
            $carrier->shipping_method = Carrier::SHIPPING_METHOD_PRICE;
            $carrier->setTaxRulesGroup(0);

            foreach (Language::getLanguages(false) as $language) {
                $carrier->delay[$language['id_lang']] = $carrier_row['delay'];
            }

            foreach (Shop::getShops() as $shop) {
                $carrier->id_shop_list[] = $shop['id_shop'];
            }

            if (!$carrier->add()) {
                return false;
            }

            $rangePrice = new RangePrice();
            $rangePrice->delimiter1 = 0;
            $rangePrice->delimiter2 = 99999999;
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->add();

            Configuration::updateValue($carrier_row['configuration_name'], $carrier->id);

            foreach (Group::getGroups(true) as $group) {
                $data = [
                    'id_carrier' => (int) $carrier->id,
                    'id_group' => (int) $group['id_group']
                ];

                Db::getInstance()->insert('carrier_group', $data);
            }
            if (!$carrier_row['allZones']) {
                $carrier->addZone($europeZone->id);
            } else {
                $zones = Zone::getZones(true);
                foreach ($zones as $zone) {
                    $carrier->addZone($zone['id_zone']);
                }
            }

            if (isset($carrier_row['logo']) && !empty($carrier_row['logo'])) {
                $destination = _PS_SHIP_IMG_DIR_ . $carrier->id . '.jpg';
                if (!Tools::copy(realpath($carrier_row['logo']), $destination))
                {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Remove module installed carriers from shop
     * 
     * @param array $carriers
     * 
     * @return bool
     */
    public function uninstallCarriers($carriers)
    {
        foreach ($carriers as $carrier_row)
        {
            $id_carrier = Configuration::get($carrier_row['configuration_name']);
            if (!$id_carrier) {
                return true;
            }

            $carrier = new Carrier($id_carrier);
            if (!Validate::isLoadedObject($carrier)) {
                return true;
            }

            if (!$carrier->delete()) {
                return false;
            }

            Configuration::deleteByName($carrier_row['configuration_name']);
        }
        return true;
    }
    
}
