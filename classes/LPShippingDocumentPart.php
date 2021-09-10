<?php

use DoctrineExtensions\Types\CarbonDateType;

class LPShippingDocumentPart extends ObjectModel
{
    const PARENT_KEY = LPShippingDocument::PRIMARY_KEY;
    const TABLE_NAME = 'lpshipping_document_part';
    const PRIMARY_KEY = 'id_lpshipping_document_part';

    public $id_lpshipping_order_document;
    public $country_code;
    public $currency_code;
    public $amount;
    public $weight;
    public $quantity;
    public $summary;

    public static $definition = [
        'table' => self::TABLE_NAME,
        'primary' => self::PRIMARY_KEY,
        'fields' => array(
            self::PARENT_KEY => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'country_code' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'currency_code' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'amount' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'quantity' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'summary' => array('type' => self::TYPE_STRING, 'validate' => 'isString')
        ),
    ];

    function __construct($id = null){
        parent::__construct($id);
    }

    /**
     * Save LP order to DB
     *
     * @param array $row - LPShippingDocumentPart data structure array
     *
     * @return bool
     */
    public static function saveEntity(array $row)
    {
        $entity = new LPShippingDocumentPart();
        $entity = self::write($entity, $row);

        if (!$entity->save()) {
            throw new Exception('Failed to save LP document part to database');
        }

        return true;
    }

    /**
     * Update LPShippingOrder
     *
     * @param array $row - LPShippingDocumentPart data structure array
     *
     * @return bool;
     */
    public static function updateEntity(array $row)
    {
        $entityArr = self::getByParentId($row[self::PARENT_KEY]);
        $entity = new LPShippingDocumentPart($entityArr[self::PRIMARY_KEY]);

        if ($entity->id_lpshipping_order_document != null) {
            self::write($entity, $row);

            return $entity->update();
        } else {
            self::saveEntity($row);
        }
    }

    /**
     * Write object
     *
     * @param LPShippingDocumentPart $entity
     * @param array LPShippingDocument data structure array
     *
     * @return LPShippingDocumentPart
     */
    private static function write(LPShippingDocumentPart $entity, array $row)
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
        $entity = new LPShippingDocumentPart($entityArr[self::PRIMARY_KEY]);

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
        $query->select('*')->from(self::TABLE_NAME)->where("${parentKey} = " . $id);

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
