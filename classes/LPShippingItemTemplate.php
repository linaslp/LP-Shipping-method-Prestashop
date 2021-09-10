<?php

class LPShippingItemTemplate extends ObjectModel
{
    public $template_id;
    public $weight;
    public $part_count;
    public $type;
    public $size;
    public $title;
    public $description;
    public $summary;
    public $additional_services;
    public $sender;
    public $receiver;

    private static $tableName = 'lpshipping_shipping_template';

    public static $definition = array(
        'table' => 'lpshipping_shipping_template',
        'primary' => 'id_lpshipping_shipping_template',
        'fields' => array(
            'template_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'part_count' => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
            'type' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'size' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'title' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'summary' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'description' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 512),
            'additional_services' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 1024),
            'receiver' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 1024),
            'sender' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 1024)
        ),
    );

    /**
     * Save batch of shipping templates to DB
     * 
     * @param array $shippingTemplates
     * 
     * @return bool
     */
    public static function saveShippingTemplateBatch(array $shippingTemplates)
    {
        foreach ($shippingTemplates as $row) {
            self::saveShippingTemplate($row);
        }

        return true;
    }

    /**
     * Save shipping template to DB
     * 
     * @param array $shippingTemplate
     * 
     * @return bool
     */
    public static function saveShippingTemplate(array $row)
    {
        $shippingTemplate = new LPShippingItemTemplate();

        $shippingTemplate->template_id = $row['id'];
        $shippingTemplate->weight = $row['weight'];
        $shippingTemplate->part_count = $row['partCount'];
        $shippingTemplate->type = $row['type'];
        $shippingTemplate->size = key_exists('size', $row) ? $row['size'] : NULL;
        $shippingTemplate->title = $row['title'];
        $shippingTemplate->description = key_exists('description', $row) ? $row['description'] : NULL;
        $shippingTemplate->summary = key_exists('summary', $row) ? $row['summary'] : NULL;
        $shippingTemplate->additional_services = serialize($row['additionalServices']);
        $shippingTemplate->receiver = serialize($row['receiver']);
        $shippingTemplate->sender = serialize($row['sender']);

        if (!$shippingTemplate->save()) {
            throw new Exception('Failed to save terminal to database');
        }

        return true;
    }

    /**
     * Get one ShippingTemplate row
     * 
     * @param int $id
     * 
     * @return LPShippingItemTemplate|null
     */
    public static function getShippingTemplate($id)
    {
        $query = new DbQuery();

        $query->select('*');
        $query->from(self::$tableName);
        $query->where('template_id = ' . $id);

        $result = DB::getInstance()->getRow($query);

        return $result;
    }

    /**
     * Get ShippingItemTemplates by type
     * 
     * @param string $type
     * 
     * @return array
     */
    public static function getShippingTemplatesByType($type)
    {
        if ($type && !empty(trim($type))) {
            $query = new DbQuery();

            $query->select('*');
            $query->from(self::$tableName);
            $query->where('type = "' . $type . '"');

            $results = DB::getInstance()->executeS($query);

            return $results;
        } else {
            return 0;
        }
    }

    /**
     * Get ShippingItemTemplates by type and size
     *
     * @param string $type
     *
     * @return array
     */
    public static function getShippingTemplateIdByTypeAndSize($type, $size = '')
    {
        if ($type && !empty(trim($type))) {
            $query = new DbQuery();

            $query->select('*');
            $query->from(self::$tableName);
            $query->where('type = "' . $type . '"');
            if($size && !empty(trim($size))) {
                $query->where('size = "' . $size . '"');
            }

            $results = DB::getInstance()->getRow($query);

            return $results['template_id'];
        } else {
            return 0;
        }
    }
}
