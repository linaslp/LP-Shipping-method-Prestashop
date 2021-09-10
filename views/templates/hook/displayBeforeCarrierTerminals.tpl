<div class="lpshipping_carrier js-error-box col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}"></div>
{if isset($error) && !empty($error)}
    <div class="lpshipping_carrier error col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        {$error}
    </div>
{elseif isset($terminals) && !empty($terminals)}
    <div class="lpshipping_carrier col-xs-12" data-carrier-id="{$id_carrier}" data-cart-id="{$id_cart}">
        <select id="lpshipping_express_terminal" name="lpshipping_express_terminal">
            <option value="-1">{$select_terminal_message}</option>

            {foreach $terminals as $city => $terminals_by_city}
                <optgroup label="{$city}">
                    {foreach $terminals_by_city as $terminal}
                        <option value="{$terminal['id_lpexpress_terminal']}"{if isset($selected_terminal) && $selected_terminal == $terminal['id_lpexpress_terminal']} selected{/if}>{$terminal['name']} {$terminal['address']}, {$terminal['city']}</option>
                    {/foreach}
                </optgroup>
            {/foreach}
        </select>
    </div>
{/if}
