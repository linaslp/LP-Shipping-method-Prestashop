<?php

use DoctrineExtensions\Types\CarbonDateType;

class LPShippingOrder extends ObjectModel
{
    const ORDER_STATUS_SAVED = 'ORDER_STATUS_SAVED';
    const ORDER_STATUS_NOT_SAVED = 'ORDER_STATUS_NOT_SAVED';
    const ORDER_STATUS_FORMED = 'ORDER_STATUS_FORMED';
    const ORDER_STATUS_NOT_FORMED = 'ORDER_STATUS_NOT_FORMED';
    const ORDER_STATUS_COURIER_CALLED = 'ORDER_STATUS_COURIER_CALLED';
    const ORDER_STATUS_COURIER_NOT_CALLED = 'ORDER_STATUS_COURIER_NOT_CALLED';
    const ORDER_STATUS_COMPLETED = 'ORDER_STATUS_COMPLETED';

    public $id_cart;
    public $id_order;
    public $id_lpexpress_terminal;
    public $status;
    public $parcel_status;
    public $selected_carrier;
    public $shipping_template_id;
    public $weight;
    public $number_of_packages;
    public $cod_available;
    public $cod_selected;
    public $cod_amount;
    public $label_number;
    public $id_lp_internal_order;
    public $id_cart_internal_order;
    public $id_manifest;
    public $post_address;
    public $sender_locality;
    public $sender_street;
    public $sender_building;
    public $sender_postal_code;
    public $sender_country;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'lpshipping_order',
        'primary' => 'id_lpshipping_order',
        'fields' => array(
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_order' => array('type' => self::TYPE_INT),
            'id_lpexpress_terminal' => array('type' => self::TYPE_INT),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'selected_carrier' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'shipping_template_id' => array('type' => self::TYPE_INT),
            'weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'),
            'number_of_packages' => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
            'cod_available' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'cod_selected' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'cod_amount' => array('type' => self::TYPE_FLOAT),
            'label_number' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'parcel_status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_lp_internal_order' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_cart_internal_order' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_manifest' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'post_address' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'sender_locality' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'sender_street' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'sender_building' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'sender_postal_code' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'sender_country' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    ];

    private const TABLE_NAME = 'lpshipping_order';

    /**
     * Save batch of terminals to DB
     * 
     * @param array $terminals
     * 
     * @return bool
     */
    public static function saveOrderBatch(array $lpOrders)
    {
        foreach ($lpOrders as $row) {
            self::saveOrder($row);
        }

        return true;
    }

    /**
     * Save LP order to DB
     * 
     * @param array $row - LPShippingOrder data structure array
     * 
     * @return bool
     */
    public static function saveOrder(array $row)
    {
        $lpOrder = new LPShippingOrder();
        $lpOrder = self::writeFields($lpOrder, $row);

        if (!$lpOrder->save()) {
            throw new Exception('Failed to save LP order to database');
        }

        return true;
    }

    /**
     * Update LPShippingOrder
     * 
     * @param array $row - LPShippingOrder data structure array
     * 
     * @return bool;
     */
    public static function updateOrder(array $row)
    {
        $id = self::getObjectIdByOrderId($row['id_order']);
        $lpOrder = new LPShippingOrder($id);

        if ($lpOrder->id_order != null) {
            self::writeFields($lpOrder, $row);

            return $lpOrder->update();
        } else {
            self::saveOrder($row);
        }
    }

    /**
     * Write object
     * 
     * @param LPShippingOrder $lpOrder
     * @param array LPShippingOrder data structure array
     * 
     * @return LPShippingOrder
     */
    private static function writeFields(LPShippingOrder $lpOrder, array $row)
    {
        if ($lpOrder) {
            $fields = $lpOrder->getFields();

            foreach ($fields as $key => $value) {
                if (isset($row[$key])) {
                    $lpOrder->$key = $row[$key];
                }
            }
        }

        return $lpOrder;
    }

    /**
     * Remove LPShippingOrder in DB
     * 
     * @param array $row - LPShippingOrder data structure array
     * 
     * @return bool
     */
    public static function removeOrder(array $row)
    {
        $id = self::getObjectIdByOrderId($row['id_order']);
        $lpOrder = new LPShippingOrder($id);

        if ($lpOrder) {
            $lpOrder->id_lp_internal_order = null;
            $lpOrder->status = self::ORDER_STATUS_NOT_SAVED;
            $lpOrder->update();
            return true;
        }

        return false;
    }

    /**
     * Remove LPShippingOrder in DB
     *
     * @param array $row - LPShippingOrder data structure array
     *
     * @return bool
     */
    public static function forceRemoveOrder(array $row)
    {
        $id = self::getObjectIdByOrderId($row['id_order']);
        $lpOrder = new LPShippingOrder($id);

        if ($lpOrder) {
            $lpOrder->delete();

            return true;
        }

        return false;
    }

    /**
     * Get LP orders from database
     * 
     */
    public static function getOrders()
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order');

        $query->orderBy('date_add');

        $results = DB::getInstance()->executeS($query);

        return $results;
    }

    /**
     * Get LPShippingOrder by order id
     * 
     * @param int $id
     */
    public static function getOrderById($id)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order')->where('id_order = ' . $id);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }

    /**
     * Get LPShippingOrder row id by order id
     * 
     * @param string $id
     * 
     * @return int|0
     */
    public static function getObjectIdByOrderId($id)
    {
        $row = self::getOrderById($id);

        if (!is_array($row)) {
            return 0;
        }

        if (!key_exists('id_lpshipping_order', $row)) {
            return 0;
        }

        return $row['id_lpshipping_order'];
    }

    /**
     * Get LPShippingOrder by cart id
     * 
     * @param string $cartId
     * 
     * @return array|false
     */
    public static function getOrderByCartId($cartId)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order')->where('id_cart = ' . $cartId);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }

    /**
     * Get LPShippingOrder by row id
     * 
     * @param string $id
     * 
     * @return array|false
     */
    public static function getOrderByRowId($id)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order')->where('id_lpshipping_order = ' . $id);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }

    /**
     * Get LPShippingOrder by status
     * 
     * @param string $id
     * 
     * @return array|false
     */
    public static function getOrdersByStatus($status)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order')->where('status = ' . $status);

        $query->orderBy('date_add');

        $results = DB::getInstance()->executeS($query);

        return $results;
    }

    /**
     * Get LPShippingOrder by item id in LP API
     * 
     * @param string $internalId
     * 
     * @return array|false
     */
    public static function getOrderByInternalLpId($internalId)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_order')->where('id_lp_internal_order = ' . $internalId);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }

    /**
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public static function getOrdersById(array $ids) 
    {
        $query = new DbQuery();
        $idString = implode(",", $ids);

        $query
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(sprintf('id_lpshipping_order IN (%s)', $idString));

        return DB::getInstance()->executeS($query);
    }
}
