<div class="lpshipping_carrier js-error-box col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}"></div>
{if isset($error) && !empty($error)}
    <div class="lpshipping_carrier error col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        {$error}
    </div>
{elseif $check_available_countries == true && !empty($available_countries_url)}
    <div class="lpshipping_carrier error-box col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}"></div>
    <div class="lpshipping_carrier col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        <span class="lpshipping_carrier_text">{$available_countries_text}</span> 
        <a class="lpshipping_carrier_link" href="{$available_countries_url}" target="_blank">{$available_countries_url}</a>
    </div>
{/if}