<div class="lpshipping_carrier_container unvisible" data-id-carrier="{$id_carrier}" data-cart-id="{$id_cart}">
    {$terminals_content}
</div>

{literal}
<script>
    $(document).ready(function() {
        movePS16ToCarrier("{/literal}{$id_carrier}{literal}", "{/literal}{$id_address}{literal}");

        setTimeout(function () {
            $('#lp_express_terminal').select2();
        }, 250);
    });
</script>
{/literal}

