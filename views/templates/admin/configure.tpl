{*
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<div class="panel">
	<h3><i class="icon icon-tags"></i> {l s='Documentation' mod='lp_shipping'}</h3>
	<p>
		&raquo; {l s='You can get a PDF documentation to configure this module' mod='lp_shipping'} :
		<ul>
			<li><a href="#" target="_blank">{l s='English' mod='lp_shipping'}</a></li>
			<li><a href="#" target="_blank">{l s='French' mod='lp_shipping'}</a></li>
		</ul>
	</p>
</div>

<script>
    $(document).ready( function () {
        turnOnOffLpServices();
        // turnOnOffLpExpressServices();

        $(document).on('load change', '[name="LP_SHIPPING_SERVICE_ACTIVE"]', function () {
            turnOnOffLpServices();
        });

        $(document).on('load change', '[name="LP_SHIPPING_EXPRESS_SERVICE_ACTIVE"]', function () {
            turnOnOffLpExpressServices();
        });
    });

    function turnOnOffLpServices() {
        let lpServicesOptOn = $('#LP_SHIPPING_SERVICE_ACTIVE_on');
        let lpServicesOptOff = $('#LP_SHIPPING_SERVICE_ACTIVE_off');

        let lpCarriers = [
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_CARRIER_HOME_OFFICE_POST_on',
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_CARRIER_ABROAD_on'
        ];

        let shipmentsPriorityOn = ('#LP_SHIPPING_SHIPMENT_PRIORITY_on');
        let shipmentsPriorityOff = ('#LP_SHIPPING_SHIPMENT_PRIORITY_off');

        let shipmentsRegisteredOn = ('#LP_SHIPPING_SHIPMENT_REGISTERED_on');
        let shipmentsRegisteredOff = ('#LP_SHIPPING_SHIPMENT_REGISTERED_off');

        if ($(lpServicesOptOff).is(':checked')) {
            for (let i = 0; i < lpCarriers.length; i++) {
                let el = $('#' + lpCarriers[i]);
                if (el) {
                    $(el).prop('checked', false);
                    $(el).prop('disabled', true);
                }
            }

            $(shipmentsPriorityOn).prop('checked', false);
            $(shipmentsPriorityOff).prop('checked', true);
            $(shipmentsRegisteredOn).prop('checked', false);
            $(shipmentsRegisteredOff).prop('checked', true);
        }

        if ($(lpServicesOptOn).is(':checked')) {
            for (let i = 0; i < lpCarriers.length; i++) {
                let el = $('#' + lpCarriers[i]);
                if (el) {
                    $(el).prop('disabled', false);
                }
            }

            $(shipmentsPriorityOn).prop('checked', true);
            $(shipmentsPriorityOff).prop('checked', false);
            $(shipmentsRegisteredOn).prop('checked', true);
            $(shipmentsRegisteredOff).prop('checked', false);
        }
    }

     function turnOnOffLpExpressServices() {
        let lpExpressServicesOptOn = $('#LP_SHIPPING_EXPRESS_SERVICE_ACTIVE_on');
        let lpExpressServicesOptOff = $('#LP_SHIPPING_EXPRESS_SERVICE_ACTIVE_off');

        let inviteCourierAutoOn = ('#LP_SHIPPING_CALL_COURIER_ACTIVE_on');
        let inviteCourierAutoOff = ('#LP_SHIPPING_CALL_COURIER_ACTIVE_off');

        let lpExpressCarriers = [
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_EXPRESS_CARRIER_HOME_on',
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_EXPRESS_CARRIER_TERMINAL_on',
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_EXPRESS_CARRIER_POST_on',
            'LP_SHIPPING_SHIPMENT_SENDING_TYPESLP_SHIPPING_EXPRESS_CARRIER_ABROAD_on'
        ];

        if ($(lpExpressServicesOptOff).is(':checked')) {
            for (let i = 0; i < lpExpressCarriers.length; i++) {
                let el = $('#' + lpExpressCarriers[i]);
                if (el) {
                    $(el).prop('checked', false);
                    $(el).prop('disabled', true);
                }
            }

            $(inviteCourierAutoOn).prop('checked', false);
            $(inviteCourierAutoOff).prop('checked', true);
        }

        if ($(lpExpressServicesOptOn).is(':checked')) {
            for (let i = 0; i < lpExpressCarriers.length; i++) {
                let el = $('#' + lpExpressCarriers[i]);
                if (el) {
                    $(el).prop('disabled', false);
                }
            }

            $(inviteCourierAutoOn).prop('checked', true);
            $(inviteCourierAutoOff).prop('checked', false);
        }
    }

    
</script>
