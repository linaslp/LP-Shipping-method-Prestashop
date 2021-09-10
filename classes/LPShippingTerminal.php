<?php

class LPShippingTerminal extends ObjectModel
{
    public $id;
    public $terminal_id;
    public $active;
    public $name;
    public $address;
    public $zip;
    public $city;
    public $comment;
    public $boxes;
    public $collecting_hours;
    public $working_hours;
    public $latitude;
    public $longitude;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'lpshipping_express_terminal',
        'primary' => 'id_lpexpress_terminal',
        'fields' => array(
            'terminal_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 128),
            'address' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 128),
            'zip' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 10),
            'city' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 128),
            'comment' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'boxes' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'servicing_hours' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'working_hours' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 256),
            'latitude' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'longitude' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );

    /**
     * Save batch of terminals to DB
     * 
     * @param array $terminals
     * 
     * @return bool
     */
    public static function saveTerminalBatch(array $terminals)
    {
        foreach ($terminals as $row) {
            self::saveTerminal($row);
        }

        return true;
    }

    /**
     * Save terminal to DB
     * 
     * @param array $terminal
     * 
     * @return bool
     */
    public static function saveTerminal(array $row)
    {
        $terminal = new LPShippingTerminal();

        $terminal->terminal_id = $row['id'];
        $terminal->name = $row['name'];
        $terminal->address = $row['address'];
        $terminal->city = $row['city'];
        $terminal->zip = $row['postalCode'];
        $terminal->boxes = serialize($row['boxes']);
        $terminal->latitude = $row['latitude'];
        $terminal->longitude = $row['longitude'];
        $terminal->working_hours = $row['workingHours'];
        $terminal->servicing_hours = $row['servicingHours'];
        $terminal->comment = $row['comment'];
        // @TODO LPShipping check how obtain info if terminal is active/not active
        $terminal->active = 1;

        if (!$terminal->save()) {
            throw new Exception('Failed to save terminal to database');
        }

        return true;
    }

    /**
     * Update LPShippingTerminal
     * 
     * @param array $newData - LPShippingTerminal data structure array
     * 
     * @return bool;
     */
    public static function updateTerminal($rowId = null, array $newData)
    {
        if ($rowId) {
            $lpTerminal = new LPShippingTerminal($rowId);

            if (Validate::isLoadedObject($lpTerminal)) {
                $lpTerminal->terminal_id = $newData['id'];
                $lpTerminal->name = $newData['name'];
                $lpTerminal->address = $newData['address'];
                $lpTerminal->city = $newData['city'];
                $lpTerminal->zip = $newData['postalCode'];
                $lpTerminal->boxes = serialize($newData['boxes']);
                $lpTerminal->latitude = $newData['latitude'];
                $lpTerminal->longitude = $newData['longitude'];
                $lpTerminal->working_hours = $newData['workingHours'];
                $lpTerminal->servicing_hours = $newData['servicingHours'];
                $lpTerminal->comment = $newData['comment'];
                // @TODO LPShipping check how obtain info if Terminal is active/not active
                $lpTerminal->active = 1;

                return $lpTerminal->update();
            }
        } else {
            return self::saveTerminal($newData);
        }
    }

    /**
     * Get terminals from database
     * 
     * @param bool $active
     * @param string $orderBy
     */
    public static function getTerminals($active = true, $orderBy = 'city', $sizeFilter = '')
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_express_terminal');

        if ($active == true) {
            $query->where('active = 1');
        }

        if ($orderBy != '') {
            $query->orderBy(pSQL($orderBy));
        }

        $query->orderBy('terminal_id');

        $results = DB::getInstance()->executeS($query);

        $terminals = [];
        if (!empty($results)) {
            foreach ($results as $terminal) {
                $terminal['boxes'] = unserialize($terminal['boxes']);
                if ($sizeFilter && !in_array($sizeFilter, $terminal['boxes'])) {
                    continue;
                }
                $terminals[$terminal['city']][$terminal['id_lpexpress_terminal']] = $terminal;
            }
        }

        return $terminals;
    }

    /**
     * Get Terminal by row ID
     * 
     * @param string $id
     * 
     * @return array
     */
    public static function getTerminalById($id)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_express_terminal')->where('id_lpexpress_terminal = ' . $id);

        $result = DB::getInstance()->getRow($query);

        return $result;
    }

    /**
     * Get Terminal by terminal ID
     * 
     * @param string $terminalId
     * 
     * @return array
     */
    public static function getTerminalByTerminalId($terminalId)
    {
        $query = new DbQuery();

        $query->select('*')->from('lpshipping_express_terminal')->where('terminal_id = ' . $terminalId);

        $result = DB::getInstance()->getRow($query);

        return $result;
    }
}
