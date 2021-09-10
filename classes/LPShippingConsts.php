<?php

class LpShippingConsts
{

    const COURIER_EXPRESS_FOREIGN = 'LP_SHIPPING_EXPRESS_CARRIER_ABROAD';
    const COURIER_FOREIGN = 'LP_SHIPPING_CARRIER_ABROAD';
    const COURIER_HOME_OFFICE_POST = 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST';

    //Declaration constants

    const CN22_THRESHOLD = 325;
    const LP_SHIPMENT_CN_TEMPLATES = [42, 43, 70, 73, 74, 78];
    const LP_SHIPPING_CHCA_TEMPLATES = [49, 50, 51, 52, 53];
    const LP_SHIPPING_HC_TEMPLATES = [54, 55, 56, 57, 58];
    const LP_SHIPPING_PARCEL_FROM_HOME = 45;
    const LP_SHIPPING_EXPRESS_PARCEL_TO_POST = 46;
    const LP_SHIPPING_EBIN_PACKAGE_SIZE = 45;
    const LP_SHIPPING_CN_ALWAYS = 44;
    const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL',
        'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
        'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'
    ];
}

class LpShippingCourierConfigNames {
    const LP_SHIPPING_EXPRESS_CARRIER_HOME = 'LP_SHIPPING_EXPRESS_CARRIER_HOME';
    const LP_SHIPPING_EXPRESS_CARRIER_ABROAD = 'LP_SHIPPING_EXPRESS_CARRIER_ABROAD';
    const LP_SHIPPING_EXPRESS_CARRIER_TERMINAL = 'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL';
    const LP_SHIPPING_EXPRESS_CARRIER_POST = 'LP_SHIPPING_EXPRESS_CARRIER_POST';
    const LP_SHIPPING_CARRIER_ABROAD = 'LP_SHIPPING_CARRIER_ABROAD';
    const LP_SHIPPING_CARRIER_HOME_OFFICE_POST = 'LP_SHIPPING_CARRIER_HOME_OFFICE_POST';

    const LP_SHIPPING_IN_LITHUANIA = [
        self::LP_SHIPPING_EXPRESS_CARRIER_HOME,
        self::LP_SHIPPING_EXPRESS_CARRIER_ABROAD,
        self::LP_SHIPPING_EXPRESS_CARRIER_TERMINAL,
        self::LP_SHIPPING_EXPRESS_CARRIER_POST,
    ];

    const LP_SHIPPING_NOT_IN_LITHUANIA = [
        self::LP_SHIPPING_CARRIER_ABROAD,
        self::LP_SHIPPING_CARRIER_HOME_OFFICE_POST,
    ];
}
