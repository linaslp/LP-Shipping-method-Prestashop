jQuery(document).ready(function($){
     
    let ordersPageForm = $('form#form-order');

    if (ordersPageForm.length) {
        let bulkActions = $('div.bulk-actions');

        if (bulkActions.length) {
            $(bulkActions).find('ul.dropdown-menu').append('<li><a href="#"><i class="icon-cloud-download"></i>&nbsp;' + lpShippingPrintLabelsString + '</a></li>');
            $(bulkActions).find('ul.dropdown-menu').append('<li><a href="#"><i class="icon-cloud-download"></i>&nbsp;' + lpShippingPrintManifestsString + '</a></li>');
        }
    }

});