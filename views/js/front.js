if (typeof LPShippingToken === 'undefined') {
    var LPShippingToken = '';
    var LPShippingExpressCarrierTerminal = 0;
    var LPShippingExpressCarrierPost = 0;
    var LPShippingExpressCarrierHome = 0;
    var LPShippingExpressCarrierAbroad = 0;
    var LPShippingCarrierHomeOfficePost = 0;
    var LPShippingCarrierAbroad = 0;
    var LPShippingCartId = 0;
    var MessageTerminalNotSelected = '';
}

var carriersData = {
    lpToken: LPShippingToken,
    expressCarrierTerminal: LPShippingExpressCarrierTerminal,
    expressCarrierPost: LPShippingExpressCarrierPost,
    expressCarrierHome: LPShippingExpressCarrierHome,
    expressCarrierAbroad: LPShippingExpressCarrierAbroad,
    carrierHomeOfficePost: LPShippingCarrierHomeOfficePost,
    carrierAbroad: LPShippingCarrierAbroad,
    cartId: LPShippingCartId,
    terminalNotSelected: MessageTerminalNotSelected,
    terminalId: null,
    selectedCarrierId: null,
    resultAfterSubmit: false
};


$(document).ready( function () {
    hideErrors();
    registerListeners();
});

function registerListeners() {
    $(document).on('click', '[name="confirmDeliveryOption"], [name="processCarrier"], body#order-opc #HOOK_PAYMENT .payment_module a', function (e) {
        hideErrors();

        // send update to controller, create/update order with delivery information
        if (typeof carriersData.selectedCarrierId === 'undefined' || carriersData.selectedCarrierId === null) {
            carriersData.selectedCarrierId = getSelectedCarrier();
        }

        if (isCarrierLpShipping(carriersData.selectedCarrierId)) {

            if (isTerminalDelivery(carriersData.selectedCarrierId)) {
                if (carriersData.terminalId === null) {
                    // show error and return
                    showError(carriersData.terminalNotSelected, carriersData.selectedCarrierId);
                    return false;
                }
            }

            submitOrder($(this), e);
        }

    });

    $(document).on('change', '#lpshipping_express_terminal', function () {
        var terminalId = $('#lpshipping_express_terminal').val();
        carriersData.terminalId = terminalId;
    });

    if (!isPs17 && $('[name^="delivery_option"]')[0]) {
        var key = $('[name^="delivery_option"]:checked').data('key');
        var id_address = parseInt($('[name^="delivery_option"]:checked').data('id_address'));
        updateExtraCarrier(key, id_address)
    }

    $(document).on('change', '[name^="delivery_option"]', function () {
        carriersData.selectedCarrierId = getSelectedCarrier();
        carriersData.terminalId = null; // null it on change of delivery type
        if (isPs17) {
            return;
        }
        var key = $(this).data('key');
        var id_address = parseInt($(this).data('id_address'));
        updateExtraCarrier(key, id_address)
        var content = $('.lpshipping_carrier_container');
        content.hide();
    });

    setTimeout(function () {
        $('#lpshipping_express_terminal').select2();
    }, 250);
}

/**
 * Form data for order sending
 * 
 * @return array data
 */
function getSelectedCarrier() {
    /* Get selected carrier and cart ids */
    if (isPs17) {
        var deliveryOptionInput = $(".delivery-option .custom-radio input[name^='delivery_option']:checked");
    } else {
        var deliveryOptionInput = $(".delivery_option_radio input[name^='delivery_option']:checked");
    }
    var inputVal = $(deliveryOptionInput).val();

    var carrierId = inputVal.replace(',', ''); // replace comma with nothing

    return carrierId;
}

function isCarrierLpShipping(selectedCarrierId) {
    if (
        parseInt(selectedCarrierId) == carriersData.expressCarrierHome ||
        parseInt(selectedCarrierId) == carriersData.expressCarrierPost ||
        parseInt(selectedCarrierId) == carriersData.expressCarrierTerminal ||
        parseInt(selectedCarrierId) == carriersData.expressCarrierAbroad ||
        parseInt(selectedCarrierId) == carriersData.carrierHomeOfficePost ||
        parseInt(selectedCarrierId) == carriersData.carrierAbroad
    ) {
        return true;
    }

    return false;
}

function isTerminalDelivery(selectedCarrierId) {
    if (parseInt(selectedCarrierId) == carriersData.expressCarrierTerminal) {
        return true;
    }

    return false;
}

function getErrorBoxByCarrierId(carrierId) {
    var errorBox = $('.lpshipping_carrier.js-error-box[data-carrier-id="' + carrierId + '"]')[0];

    return errorBox;
}

function showError(message, carrierId) {
    var errBox = getErrorBoxByCarrierId(carrierId);

    $(errBox).html(message);
    $(errBox).show();
    errBox.scrollIntoView(false);
}

function hideErrors() {
    var errorBoxes = $('.lpshipping_carrier.js-error-box');

    if (errorBoxes.length > 1) {
        for (var i = 0; i < errorBoxes.length; i++) {
            $(errorBoxes[i]).hide();
        }
    } else if (errorBoxes.length == 1) {
        $(errorBoxes).hide();
    }
}


function submitOrder(proceedToPaymentsElement, event) {
    var dataToSend = {
        cartId: carriersData.cartId,
        selectedCarrierId: carriersData.selectedCarrierId,
        terminalId: carriersData.terminalId
    };

    sendAjax('submitOrder', dataToSend, 
    function(data) {
        if ($(proceedToPaymentsElement).is('a') && $(proceedToPaymentsElement).attr('href'))
        {
            location.href = $(proceedToPaymentsElement).attr('href');
        }
        return true;
    }, 
    function(data) {
        // prevent default behaviour of sending user to next step
        event.preventDefault();
        event.stopPropagation();

        console.log(data);
        return;
    });
}

function sendAjax(action, data, successCallback, failedCallback) {
    var parameters = {
        'action': action,
        'LPShippingToken': LPShippingToken
    };

    $.extend(parameters, data);

    $.ajax({
        url: LPShippingAjax,
        type: "POST",
        data: parameters,
        dataType: "JSON",
        success: function(data){
            if (data.success) {
                if (typeof successCallback === 'function') {
                    successCallback(data);
                }
            } else {
                if (typeof failedCallback === 'function') {
                    failedCallback(data);
                }

                if (!!$.prototype.fancybox) {
                    $.fancybox.open(
                        [{
                            type: 'inline',
                            autoScale: true,
                            minHeight: 30,
                            content: '<p class="fancybox-error">' + data.message + '</p>'
                        }],
                        {
                            padding: 0
                        });
                    }
                else {
                    console.log(data.message);
                }
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr); console.log(ajaxOptions); console.log(thrownError);
        }
    });
}

function movePS16ToCarrier(id_selected_lp_carrier, id_carrier_address) {
    // Remove all containers
    $('.lpshipping_carrier_container:not(.unvisible)').remove();

    // Find carrier container
    var carrier = $('[name="delivery_option['+id_carrier_address+']"][value^="'+id_selected_lp_carrier+'"]');

    if (!carrier.length) {
        console.log('Cant find carrier to store LP Shipping container');
        $('.lpshipping_carrier_container').remove();
    }

    var container = carrier.closest('.delivery_option');
    if (!container.length) {
        console.log('Cant find carrier container');
        $('.lpshipping_carrier_container').remove();
    }

    var content = $('.lpshipping_carrier_container.unvisible');
    content.hide();
    container.append(content);
    content.removeClass('unvisible');
    content.slideDown();
}
