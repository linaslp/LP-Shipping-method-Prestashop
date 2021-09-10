<div class="lpshipping_carrier js-error-box col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}"></div>
{if isset($error) && !empty($error)}
    <div class="lpshipping_carrier error col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        {$error}
    </div>
{elseif isset($address) && !empty($address)}
    <div class="lpshipping_carrier col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        {$address['street']} {$address['postCode']} {$address['locality']} {$address['country']}
    </div>
{/if}