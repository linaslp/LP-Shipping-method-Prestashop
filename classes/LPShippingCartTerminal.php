<?php

class LPShippingCartTerminal extends ObjectModel {
    public $id_cart;
    public $id_lpexpress_terminal;

    private static $tableName = 'lpshipping_cart_terminal';

    public static $definition = [
        'table' => 'lpshipping_cart_terminal',
        'primary' => 'id_cart',
        'fields' => [
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'id_lpexpress_terminal' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'allow_null' => true]
        ]
    ];

    public static function updateTerminalByCartId(int $cartId, ?int $terminalId)
    {
        if (!$terminalId) {
            $terminalId = "NULL";
        }

        $sql = "UPDATE %slpshipping_cart_terminal SET id_lpexpress_terminal = %s WHERE id_cart = %s";
        $sql = sprintf($sql, _DB_PREFIX_, bqSQL($terminalId), bqSQL($cartId));

        return Db::getInstance()->execute($sql);
    }
}