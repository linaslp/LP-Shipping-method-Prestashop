<?php

use DoctrineExtensions\Types\CarbonDateType;

class LPShippingDocument extends ObjectModel
{
    const PARENT_KEY = 'id_lpshipping_order';
    const TABLE_NAME = 'lpshipping_order_document';
    const PRIMARY_KEY = 'id_lpshipping_order_document';

    const PARCEL_TYPE_LP = 'LP';
    const PARCEL_TYPE_LP_EXPRESS = 'LPEXPRESS';

    public $id_lpshipping_order;

    //CN23
    public $parcel_type;
    public $notes;
    public $parcel_type_notes;
    //CN22
    public $description;

    public static $definition = [
        'table' => self::TABLE_NAME,
        'primary' => self::PRIMARY_KEY,
        'fields' => array(
            self::PARENT_KEY => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'parcel_type' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'notes' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'parcel_type_notes' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'description' => array('type' => self::TYPE_STRING, 'validate' => 'isString')
        ),
    ];

    function __construct($id = null){
        parent::__construct($id);
    }

    /**
     * Save LP order to DB
     *
     * @param array $row - LPShippingDocument data structure array
     *
     * @return bool
     */
    public static function saveEntity(array $row)
    {
        $entity = new LPShippingDocument();
        $entity = self::write($entity, $row);

        if (!$entity->save()) {
            throw new Exception('Failed to save LP order to database');
        }

        return true;
    }

    /**
     * Update LPShippingOrder
     *
     * @param array $row - LPShippingDocument data structure array
     *
     * @return bool;
     */
    public static function updateEntity(array $row)
    {
        $entityArr = self::getByParentId($row[self::PARENT_KEY]);
        $entity = new LPShippingDocument($entityArr[self::PRIMARY_KEY]);

        if ($entity->id_lpshipping_order != null) {
            self::write($entity, $row);

            return $entity->update();
        } else {
            self::saveEntity($row);
        }
    }

    /**
     * Write object
     *
     * @param LPShippingDocument $entity
     * @param array LPShippingDocument data structure array
     *
     * @return LPShippingDocument
     */
    private static function write(LPShippingDocument $entity, array $row)
    {
        if ($entity) {
            $fields = $entity->getFields();

            foreach ($fields as $key => $value) {
                if (isset($row[$key])) {
                    $entity->$key = $row[$key];
                }
            }
        }

        return $entity;
    }

    /**
     * Remove LPShippingDocument in DB
     *
     * @param array $row - LPShippingOrder data structure array
     *
     * @return bool
     */
    public static function deleteEntity(array $row)
    {
        $entityArr = self::getByParentId($row[self::PARENT_KEY]);
        $entity = new LPShippingDocument($entityArr[self::PRIMARY_KEY]);

        if ($entity) {
            $entity->delete();

            return true;
        }

        return false;
    }

    /**
     * Get LPShippingOrder by order id
     *
     * @param int $id
     */
    public static function getByParentId($id)
    {
        $query = new DbQuery();
        $parentKey = self::PARENT_KEY;
        $query->select('*')->from(self::TABLE_NAME)->where("$parentKey = " . $id);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }


    /**
     * Get LPShippingDocument by row id
     *
     * @param string $id
     *
     * @return array|false
     */
    public static function getById($id)
    {
        $query = new DbQuery();
        $primaryKey = self::PRIMARY_KEY;
        $query->select('*')->from(self::TABLE_NAME)->where("$primaryKey = " . $id);

        $results = DB::getInstance()->getRow($query);

        return $results;
    }
}
