<div class="tab-content lpshipping-container">
    <div class="lps-panel">
        <div class="lps-panel-heading">
            <span class="lps-card-header"><i class="icon-tags"></i> {l s="LP Shipping" mod="lpshipping"}</span>

            <span class="lps-pull-right lps-card-header">{l s="Status" m="lpshipping"}: <span>{$status}</span></span>
        </div>

        <hr class="lps-panel-divider" />

        <div class="lps-form-div">
            <form action="{$link->getAdminLink('AdminLPShippingOrder')}&id_order={$id_order}&view_order=1" method="post" id="lpshipping_order_submit_form">
                <div class="form-horizontal">

                    <!-- INPUT NAMES ARE MATCHED WITH LpShippingOrder model class -->
                    <div class="row form-group">

                        <div class="alert alert-danger" id="lpshipping-error-box" style="display: none">

                        </div>

                        {if isset($last_error) && !empty($last_error)}
                            <div class="col-md-12">
                                <div class="alert alert-warning alert-dismissible show" role="alert">
                                    <strong>{l s='Error!' mod='lpshipping'}</strong> {$last_error}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            </div>
                        {/if}

                        <!-- First column -->
                        <div class="col-md-6">
                            <div class="col-md-12 field-group">
                                <label for="number_of_packages" class="control-label required">{l s="Number of packages" mod='lpshipping'} *</label>
                                <input class="input-group form-control" type="text" name="number_of_packages" value="{$number_of_packages}" />
                            </div>

                            <div class="col-md-12 field-group">
                                <label for="weight" class="control-label required">{l s="Weight (kg)" mod='lpshipping'} *</label>
                                <input type="text" name="weight" value="{$weight}" class="form-control" />
                            </div>

                            {if $cod_available}
                            <div class="col-md-12 field-group">
                                <label for="cod_selected" class="control-label required">{l s="Cash on delivery (COD)" mod='lpshipping'}</label>
                                <select name="cod_selected" id="lpshipping-cod-select" autocomplete="off" class="form-control">
                                    <option value="0">{l s='No' mod='lpshipping'}</option>
                                    <option value="1" {if $is_cod_selected} selected="selected" {/if}>{l s='Yes' mod='lpshipping'}</option>
                                </select>
                            </div>

                            <div class="col-md-12 field-group" id="lpshipping-cod-amount-box">
                                <label for="cod_amount" class="control-label required">{l s="Cash on delivery (COD) amount" mod='lpshipping'}</label>
                                <input type="text" name="cod_amount" value="{$cod_amount}" class="form-control" />
                            </div>
                            {/if}

                            <div class="col-md-12 field-group" id="lpshipping-post-address-box">
                                <label for="post_address" class="control-label required">{l s='Post address' mod='lpshipping'}</label>
                                <input name="post_address" class="form-control" type="text" value="{$post_address}" disabled />
                            </div>


                        </div>
                        <!-- Second column -->
                        <div class="col-md-6">
                            <div class="col-md-12 field-group">
                                <label for="selected_carrier" class="control-label required">{l s='Carrier' mod='lpshipping'} *</label>
                                <select name="selected_carrier" class="custom-select" id="lpshipping-carier-select">
                                    {foreach from=$carriers item=carrier}
                                    <option
                                        value="{$carrier['configuration_name']}"
                                        {if $selected_carrier != null}
                                            {if $selected_carrier == $carrier['configuration_name']}selected="selected"{/if}
                                        {/if}>{$carrier['name_translation']}
                                    </option>
                                    {/foreach}
                                </select>
                            </div>

                            <div class="col-md-12 lpshipping-template-sizes-box field-group" id="lpshipping-template-sizes-box">
                                <label for="shipping_template_id" class="control-label required">{l s='Box size' mod='lpshipping'} *</label>
                                <select name="shipping_template_id" id="lpshipping-box-sizes-select" class="custom-select">
                                    <option>{l s='Select box size' mod='lpshipping'}</option>
                                </select>
                            </div>

                            <div class="col-md-12 lpshipping-terminal-box field-group">
                                <label for="id_lpexpress_terminal" class="control-label required">{l s='Terminal' mod='lpshipping'} *</label>
                                <select name="id_lpexpress_terminal" class="custom-select" id="lpshipping-terminal-select">
                                    <option value="-1">{l s='Select terminal' mod='lpexpress'}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-md-12 lps-form-btns">
                            {if $is_shipment_formed == false}
                                <button type="submit" class="btn btn-primary pull-right" name="saveLPShippingOrder">
                                    {if $is_order_saved == false}
                                        {l s='Save' mod='lpshipping'}
                                    {else}
                                        {l s='Update' mod='lpshipping'}
                                    {/if}
                                </button>

                                {if $is_order_saved == true}
                                    <button type="submit" class="btn btn-primary pull-right" name="removeLPShippingOrder" {if $is_shipment_formed == false}style="margin-right: 8px;"{/if}>
                                        {l s='Remove' mod='lpshipping'}
                                    </button>
                                    <button type="submit" class="btn btn-primary pull-right" name="formShipments" style="margin-right: 8px;">
                                        {l s='Form Shipments' mod='lpshipping'}
                                    </button>
                                {/if}
                            {/if}

                            {if $is_cancellable == true}
                                <button type="submit" class="btn btn-primary pull-right" name="cancelShipments" style="margin-right: 8px;">
                                    {l s='Cancel shipments' mod='lpshipping'}
                                </button>
                            {/if}

                            {if $is_call_courier_available == true}
                                <button type="submit" class="btn btn-primary pull-right" name="callCourier" style="margin-right: 8px;">
                                    {l s='Call courier' mod='lpshipping'}
                                </button>
                            {/if}

                            {if $are_documents_printable}
                                {if $is_label_printable}
                                <button type="submit" class="btn btn-primary pull-right" name="printLabel" style="margin-right: 8px;">
                                    {l s='Print label' mod='lpshipping'}
                                </button>
                                {/if}

                                {if $is_declaration_printable}
                                    <button type="submit" class="btn btn-primary pull-right" name="printDeclaration"
                                            style="margin-right: 8px;">
                                        {l s='Print declaration' mod='lpshipping'}
                                    </button>
                                {/if}

                                {if $is_manifest_printable || $is_declaration_printable}
                                    <button type="submit" class="btn btn-primary pull-right" name="printManifest"
                                            style="margin-right: 8px;">
                                        {l s='Print manifest' mod='lpshipping'}
                                    </button>
                                    <button type="submit" class="btn btn-primary pull-right" name="printAll"
                                            style="margin-right: 8px;">
                                        {l s='Print documents' mod='lpshipping'}
                                    </button>
                                {/if}
                            {/if}

                            {if $is_order_saved != true && ($is_declaration_cn22_required == true || $is_declaration_cn23_required == true) }
                                <button type="button" class="btn btn-primary pull-right" style="margin-right: 8px;" data-toggle="modal" data-target="#declarationsModal">
                                    {l s='Fill declaration' mod='lpshipping'}
                                </button>
                            {/if}
                            {if $show_sender_address}
                                <div style="flex: 1 1 auto">
                                    <button type="button" class="btn btn-primary pull-left" style="float:left"
                                            data-toggle="modal" data-target="#senderAddressModal">
                                        {l s='Edit sender address' mod='lpshipping'}
                                    </button>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <input type="hidden" name="cod_available" value="{$cod_available}" />
                    <input type="hidden" name="label_number" value="{$label_number}" />
                    <input type="hidden" name="id_lp_internal_order" value="{$id_lp_internal_order}" />
                    <input type="hidden" name="id_cart_internal_order" value="{$id_cart_internal_order}" />
                    <input type="hidden" name="id_lpshipping_order" value="{$id_lpshipping_order}" />
                    <input type="hidden" name="id_manifest" value="{$id_manifest}" />
                    <input type="hidden" name="id_cart" value="{$id_cart}" />
                    <input type="hidden" name="post_address" value="{$post_address}" />
                    <input type="hidden" name="is_declaration_cn22_required" value="{$is_declaration_cn22_required}" />
                    <input type="hidden" name="is_declaration_cn23_required" value="{$is_declaration_cn23_required}" />

                </div>

                {if $is_declaration_cn22_required == true || is_declaration_cn23_required == true}
                <!-- Modal edit action content for CN23/CN22 declarations -->
                <div class="modal fade" id="declarationsModal" tabindex="-1" role="dialog" aria-labelledby="declarationsModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title" id="declarationsModalLabel">{l s='Fill declaration' mod='lpshipping'}</h3>
                            </div>
                            <div class="modal-body">
                                <div class="lpshipping-declaration-edit-modal">
                                    <!-- Select parcel type -->
                                    <div class="parcel-type">
                                        <label for="parcel_type" class="control-label required">{l s='Parcel type' mod='lpshipping'}</label>
                                        <select name="parcel_type" class="custom-select" id="lpshipping-parcel-type">
                                            {foreach from=$declarations['parcelTypes'] key=key item=type}
                                                {if $parcel_type == $key}
                                                    <option value="{$key}" selected>{$type}</option>
                                                {else}
                                                    <option value="{$key}">{$type}</option>
                                                {/if}

                                            {/foreach}
                                        </select>
                                    </div>

                                    <!-- Parcel notes -->
                                    <div class="parcel-notes">
                                        <label for="parcel_notes" class="control-label required">{l s="Parcel notes" mod='lpshipping'}</label>
                                        <input type="text" name="parcel_notes" value="{$parcel_notes}" class="form-control" required />
                                    </div>

                                    <!-- Parcel description -->
                                    <div class="parcel-description" style="display: none">
                                        <label for="parcel_description" class="control-label required">{l s="Parcel description" mod='lpshipping'}</label>
                                        <input type="text" name="parcel_description" value="{$parcel_description}" class="form-control"/>
                                    </div>

                                    <!-- CN Parts -->
                                    <div class="cn-parts">
                                        <div class="cn-parts-amount">
                                            <label for="cn_parts_amount" class="control-label required">{l s="Amount" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_amount" value="{$cn_parts_amount}" class="form-control" />
                                        </div>

                                        <div class="cn-parts-country-code">
                                            <label for="cn_parts_country_code" class="control-label required">{l s="Country code (ISO 3166-1)" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_country_code" value="{$cn_parts_country_code}" class="form-control" />
                                        </div>

                                        <div class="cn-parts-currency-code">
                                            <label for="cn_parts_currency_code" class="control-label required">{l s="Currency code (EUR or USD)" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_currency_code" value="{$cn_parts_currency_code}" class="form-control" />
                                        </div>

                                        <div class="cn-parts-weight">
                                            <label for="cn_parts_weight" class="control-label required">{l s="Weight (kg)" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_weight" value="{$cn_parts_weight}" class="form-control" />
                                        </div>

                                        <div class="cn-parts-quantity">
                                            <label for="cn_parts_quantity" class="control-label required">{l s="Quantity" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_quantity" required value="{$cn_parts_quantity}" class="form-control" />
                                        </div>

                                        <div class="cn-parts-summary">
                                            <label for="cn_parts_summary" class="control-label">{l s="Summary" mod='lpshipping'}</label>
                                            <input type="text" name="cn_parts_summary" value="{$cn_parts_summary}" class="form-control"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{l s='Close' mod='lpshipping'}</button>
                                <button type="submit" name="saveDeclarationInfo" class="btn btn-primary">{l s='Save' mod='lpshipping'}</button>
                            </div>
                        </div>
                    </div>
                </div>
                {/if}

                <!-- Modal edit action content for CN23/CN22 declarations -->
                <div class="modal fade" id="senderAddressModal" tabindex="-1" role="dialog"
                     aria-labelledby="senderAddressModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title"
                                    id="senderAddressModalLabel">{l s='Edit sender address' mod='lpshipping'}</h3>
                            </div>
                            <div class="modal-body">
                                <div class="lpshipping-declaration-edit-modal">
                                    <!-- Select parcel type -->
                                    <div class="sender_locality-type">
                                        <label for="sender_locality"
                                               class="control-label required">{l s='City' mod='lpshipping'}</label>
                                        <input name="sender_locality" class="form-control" type="text"
                                               value="{$sender_locality}"/>
                                    </div>

                                    <!-- Parcel notes -->
                                    <div class="sender-street">
                                        <label for="sender_street"
                                               class="control-label required">{l s='Street' mod='lpshipping'}</label>
                                        <input name="sender_street" class="form-control" type="text"
                                               value="{$sender_street}"/>
                                    </div>

                                    <!-- Parcel description -->
                                    <div class="sender-building">
                                        <label for="sender_building"
                                               class="control-label required">{l s='Building' mod='lpshipping'}</label>
                                        <input name="sender_building" class="form-control" type="text"
                                               value="{$sender_building}"/>
                                    </div>

                                    <div class="sender_postal_code">
                                        <label for="sender_postal_code"
                                               class="control-label required">{l s='Post code' mod='lpshipping'}</label>
                                        <input name="sender_postal_code" class="form-control" type="text"
                                               value="{$sender_postal_code}"/>
                                    </div>

                                    <div class="sender-country">
                                        <label for="sender_country"
                                               class="control-label required">{l s='Country Code' mod='lpshipping'}</label>
                                        <input name="sender_country" class="form-control" type="text"
                                               value="{$sender_country}"/>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                        data-dismiss="modal">{l s='Close' mod='lpshipping'}</button>
                                <button type="submit" name="saveSenderAddressInfo"
                                        class="btn btn-primary">{l s='Save' mod='lpshipping'}</button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>


<script>
    var carriers = {json_encode($carriers)}
    var errorMessages = {json_encode($error_messages)}
    var terminals = {json_encode($terminals)}
    {if $selected_terminal}
        var selectedTerminalId = {$selected_terminal}
    {else}
        var selectedTerminalId = 0
    {/if}

    var CARRIER_TERMINAL = 'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL'

    if ({$shipping_template_id}) {
        var shippingTemplateId = {$shipping_template_id}
    } else {
        var shippingTemplateId = '';
    }


    $(document).ready(function(){
        hideErrorBox();
        changeVisibilityOfBoxSizes();
        changeVisibilityOfTerminals();
        changeVisibilityOfPostCode();
        changeVisibilityOfCod();

        function registerListeners() {
            $(document).on('change', '#lpshipping-carier-select', function() {
                changeVisibilityOfTerminals();
                changeVisibilityOfBoxSizes();
                changeVisibilityOfPostCode();
            });

            $(document).on('change', '#lpshipping-box-sizes-select', function() {
                const size = $(this).find(':selected').data('size');
                updateTerminalOptions(size);
            });

            $(document).on('change', '#lpshipping-terminal-select', function() {
                const id = $(this).find(':selected').val();
                if(parseInt(id)){
                   selectedTerminalId = parseInt(id);
                }
            });

            $(document).on('change', '#lpshipping-cod-select', function() {
                changeVisibilityOfCod();
            });

            $('#lpshipping-cod-select').on('popstate', function() {
                changeVisibilityOfCod();
            });

            $(document).on('click', '[name="saveLPShippingOrder"]', function (event) {
                let validatedFields = validateFields(this);

                if (validatedFields.success) {

                } else {
                    event.preventDefault();
                    event.stopPropagation();

                    let errorString = '';
                    for (let i = 0; i < validatedFields.messages.length; i++) {
                        errorString += validatedFields.messages[i] + '<br>';
                    }
                    showErrorBox(errorString);
                }
            });
        }

        function changeVisibilityOfTerminals() {
            let terminalsBox = $('.lpshipping-terminal-box');
            let carrier = $('#lpshipping-carier-select');

            if ($(carrier).val() === CARRIER_TERMINAL) {
                $(terminalsBox).slideDown();
            } else {
                $(terminalsBox).slideUp();
            }
        }

        function updateTerminalOptions(size){
            let carrier = $('#lpshipping-carier-select');
            if ($(carrier).val() !== CARRIER_TERMINAL) {
                return
            }

            var terminalsFiltered = [];
            for(var key in terminals){
                const filtered = Object.values(terminals[key]).filter(terminal => terminal.boxes.includes(size));
                if(filtered.length){
                    terminalsFiltered[key] = filtered;
                }
            }

            let terminalSelect = $('#lpshipping-terminal-select');

            $(terminalSelect).find('option').not(':first').remove();
            $(terminalSelect).find('optgroup').remove();

            terminalSelect.append(createTerminalOptions(terminalsFiltered));

        }

        function createTerminalOptions(terminalOptions) {
            let options = '';
            let found = false;
            for(var key in terminalOptions){
                options += '<optgroup label="' + key + '">';
                for(terminal of terminalOptions[key]){
                    options += '<option value="' + terminal.id_lpexpress_terminal + '" ';
                    if(terminal.id_lpexpress_terminal == '{$selected_terminal}'){
                        options += 'selected="true" ';
                        found = true;
                    }
                    options += '>';
                    options += terminal.name + ' ' + terminal.address + ', ' + terminal.city;
                    options += '</option>';
                }
                options += `</optgroup>`;
            }

            if(!found){
                selectedTerminalId = 0;
            }

            return options;
        }

        function getCarrier(carrierId){
            const carrier = carriers.find(x => x.configuration_name === carrierId)
            return carrier;
        }

        function changeVisibilityOfBoxSizes() {
            let carrierEl = $('#lpshipping-carier-select');
            let carrierVal = $(carrierEl).val();
            let boxesTemplatesBox = $('#lpshipping-template-sizes-box');

            let selectedCarrierData;

            for (carrierObj of carriers) {
                if (carrierObj.configuration_name === carrierVal) {
                    selectedCarrierData = carrierObj;
                    break;
                }
            }
            if (
                checkIfAbleToSelectBoxSize(CARRIER_TERMINAL, selectedCarrierData) ||
                checkIfAbleToSelectBoxSize('LP_SHIPPING_EXPRESS_CARRIER_HOME', selectedCarrierData) ||
                checkIfAbleToSelectBoxSize('LP_SHIPPING_EXPRESS_CARRIER_ABROAD', selectedCarrierData) ||
                checkIfAbleToSelectBoxSize('LP_SHIPPING_EXPRESS_CARRIER_POST', selectedCarrierData) ||
                checkIfAbleToSelectBoxSize('LP_SHIPPING_CARRIER_ABROAD', selectedCarrierData) ||
                checkIfAbleToSelectBoxSize('LP_SHIPPING_CARRIER_HOME_OFFICE_POST', selectedCarrierData)
            ) {
                $(boxesTemplatesBox).slideDown();
            } else {
                $(boxesTemplatesBox).slideUp();

                return;
            }

            let boxesTemplatesSelect = $('#lpshipping-box-sizes-select');

            $(boxesTemplatesSelect).find('option').not(':first').remove();

            if (carrierVal === 'LP_SHIPPING_EXPRESS_CARRIER_TERMINAL') {
                selectedTerminalId = $('[name="id_lpexpress_terminal"]').val();
            } else {
                selectedTerminalId = 0;
            }
            boxesTemplatesSelect.append(createTemplateSizesOptions(selectedCarrierData.templates));
        }

        function checkIfAbleToSelectBoxSize(selectedVal, carrier) {
            if (typeof carrier === 'object') {
                if (carrier.configuration_name === selectedVal) {
                    for (shippingTemplates of carrier.default_shipping_templates) {
                        if (shippingTemplates.allow_size_selection === true) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
        * If selected carrier is by terminal then terminal id will be more than 0, otherwise 0
        */
        function createTemplateSizesOptions(templates) {
            let options = '';
            for (template of templates) {

                options += '<option value="' + template.template_id + '" data-size="' + template.size + '" ';
                if (shippingTemplateId != '') {
                    if (shippingTemplateId != null && parseInt(template.template_id) === parseInt(shippingTemplateId) || templates.length === 1) {
                        options += 'selected="true" ';
                        updateTerminalOptions(template.size);
                    }
                } else {
                    if (template.size === '{$default_box_size}') {
                        options += 'selected="true" ';
                        updateTerminalOptions(template.size);
                    }
                }

                options += '>';
                if(template.size){
                    options += template.size;
                }
                else{
                    options += template.title;
                }
                options += '</option>';
            }

            return options;
        }

        function changeVisibilityOfCod() {
            let codSelect = $('#lpshipping-cod-select');
            let codAmountBox = ('#lpshipping-cod-amount-box');
            let codValue = parseInt($(codSelect).val());

            if (codValue == 1) {
                $(codAmountBox).slideDown();
            } else {
                $(codAmountBox).slideUp();
            }
        }

        function changeVisibilityOfPostCode() {
            let carrierEl = $('#lpshipping-carier-select');
            let carrierVal = $(carrierEl).val();
            let postAddress = $('#lpshipping-post-address-box');

            if (carrierVal === 'LP_SHIPPING_EXPRESS_CARRIER_POST') {
                $(postAddress).slideDown();
            } else {
                $(postAddress).slideUp();
            }
        }

        function getSelectedTerminalObj(selectedTerminalId) {

            for (let propCity in terminals) {
                for (let propId in terminals[propCity]) {
                    if (parseInt(selectedTerminalId) === parseInt(propId)) {
                        return terminals[propCity][propId];
                    }
                }
            }
        }

        function hideErrorBox() {
            let errBox = $('#lpshipping-error-box');

            $(errBox).hide();
        }

        function showErrorBox(message) {
            let errBox = $('#lpshipping-error-box');

            $(errBox).html(message);
            $(errBox).show();
        }

        function validateFields(el) {
            let returnedData = {
                success: true,
                messages: []
            }

            let packagesField = $('[name="number_of_packages"]').val(); // int
            let weightField = $('[name="weight"]').val(); // double
            let codField = $('[name="cod_selected"]').val(); // bool
            let codAmountField = $('[name="cod_amount"]').val(); // double
            let boxSizeField = $('[name="shipping_template_id"]').val(); // some value
            let terminalField = $('[name="id_lpexpress_terminal"]').val(); // some value

            if (!isInt(packagesField) || parseInt(packagesField) < 1) {
                returnedData.success = false;
                returnedData.messages.push(errorMessages.number_of_packages);
            }

            if (isNaN(weightField)) {
                returnedData.success = false;
                returnedData.messages.push(errorMessages.weight);
            }

            if (parseInt(codField, 10) === 1 && isNaN(codAmountField)) {
                returnedData.success = false;
                returnedData.messages.push(errorMessages.cod_amount);
            }

            if (!isInt(boxSizeField)) {
                returnedData.success = false;
                returnedData.messages.push(errorMessages.box_size);
            }

            let carrier = $('#lpshipping-carier-select');
            if ($(carrier).val() === CARRIER_TERMINAL) {
                if (!isInt(terminalField)) {
                    returnedData.success = false;
                    returnedData.messages.push(errorMessages.terminal);
                }
            }

            return returnedData;
        }

        function isInt(value) {
            return !isNaN(value) &&
                    parseInt(Number(value)) == value &&
                    !isNaN(parseInt(value, 10));
        }

        function isNumber(value) {
            return !isNaN(parseInt(value))
        }

        registerListeners();
    });
</script>
