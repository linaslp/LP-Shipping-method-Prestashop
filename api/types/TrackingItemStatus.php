<?php

class TrackingItemStatus
{
    /** @var \Context Context */
    private $context;
    private $translator;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->translator = $this->context->getTranslator();
    }

    public function getAllStatuses()
    {
        return [
            'ACCEPTED' => $this->translator->trans('Accepted'),
            'BP_TERMINAL_REQUEST_ACCEPTED' => $this->translator->trans('LP express terminal request accepted'),
            'BP_TERMINAL_REQUEST_FAILED' => $this->translator->trans('LP express terminal request failed'),
            'BP_TERMINAL_REQUEST_REJECTEDACCEPTED' => $this->translator->trans('LP express terminal request rejected'),
            'BP_TERMINAL_REQUEST_SENT' => $this->translator->trans('BP terminal request submitted'),
            'CANCELLED' => $this->translator->trans('Canceled'),
            'DA_ACCEPTED_LP' => $this->translator->trans('The parcel was taken from the Lithuanian Post Office'),
            'DA_ACCEPTED' => $this->translator->trans('Parcel accepted from sender'),
            'DA_DELIVERED_LP' => $this->translator->trans('The parcel was delivered to the Lithuanian Post Office'),
            'DA_DELIVERED' => $this->translator->trans('The shipment has been delivered to the receiver'),
            'DA_DELIVERY_FAILED' => $this->translator->trans('Delivery failed'),
            'DA_EXPORTED' => $this->translator->trans('Item shipped from Lithuania'),
            'DA_PASSED_FOR_DELIVERY' => $this->translator->trans('The parcel has been handed over to the courier for delivery'),
            'DA_RETURNED' => $this->translator->trans('Shipment returned'),
            'DA_RETURNING' => $this->translator->trans('The parcel is being returned'),
            'DEAD' => $this->translator->trans('The item was delivered for destruction'),
            'DELIVERED' => $this->translator->trans('Delivered'),
            'DEP_RECEIVED' => $this->translator->trans('Item accepted at distribution center'),
            'DEP_SENT' => $this->translator->trans('The item shall be transported to another distribution center'),
            'DESTROYED' => $this->translator->trans('Destroyed'),
            'DISAPPEARED' => $this->translator->trans('It\'s gone'),
            'EDA' => $this->translator->trans('The shipment was detained at the distribution center of the recipient country'),
            'EDB' => $this->translator->trans('The item has been presented to the customs authorities of the country of destination'),
            'EDC' => $this->translator->trans('The consignment is subject to customs controls in the country of destination'),
            'EDD' => $this->translator->trans('Consignment at the distribution center in the country of destination'),
            'EDE' => $this->translator->trans('The shipment was sent from a distribution center in the recipient country'),
            'EDF' => $this->translator->trans('The shipment is on hold in the recipient\'s post office'),
            'EDG' => $this->translator->trans('The shipment has been delivered for delivery'),
            'EDH' => $this->translator->trans('The shipment was delivered to the place of collection'),
            'EMA' => $this->translator->trans('Consignment accepted from sender'),
            'EMB' => $this->translator->trans('Consignment at distribution center'),
            'EMC' => $this->translator->trans('Consignment shipped from Lithuania'),
            'EMD' => $this->translator->trans('Consignment in the country of destination'),
            'EME' => $this->translator->trans('Consignment at the customs office of destination'),
            'EMF' => $this->translator->trans('The shipment was sent to the recipient\'s post office'),
            'EMG' => $this->translator->trans('Parcel at the recipient\'s post office'),
            'EMH' => $this->translator->trans('Attempt to deliver failed'),
            'EMI' => $this->translator->trans('The shipment has been delivered to the consignee'),
            'EXA' => $this->translator->trans('The consignment has been presented to the customs authorities of the country of departure'),
            'EXB' => $this->translator->trans('The consignment was detained at the office of departure'),
            'EXC' => $this->translator->trans('The consignment has been checked at the customs office of dispatch'),
            'EXD' => $this->translator->trans('The item is detained at the dispatch center of the country of dispatch'),
            'EXPORTED' => $this->translator->trans('Exported'),
            'EXX' => $this->translator->trans('The shipment has been canceled from the sender\'s country'),
            'FETCHCODE' => $this->translator->trans('The shipment was delivered to the parcel self-service terminal'),
            'HANDED_IN_BKIS' => $this->translator->trans('Served (BKIS)'),
            'HANDED_IN_POST' => $this->translator->trans('Served at the post office'),
            'HANDED_TO_GOVERNMENT' => $this->translator->trans('Transferred to the State'),
            'IMPLICATED' => $this->translator->trans('Included'),
            'INFORMED' => $this->translator->trans('Receipt message'),
            'LABEL_CANCELLED' => $this->translator->trans('Delivery tag canceled'),
            'LABEL_CREATED' => $this->translator->trans('Delivery tag created'),
            'LP_DELIVERY_FAILED' => $this->translator->trans('Delivery failed'),
            'LP_RECEIVED' => $this->translator->trans('The parcel was received at the Lithuanian Post Office'),
            'NOT_INCLUDED' => $this->translator->trans('Not included'),
            'NOT_SET' => $this->translator->trans('Unknown'),
            'ON_HOLD' => $this->translator->trans('Detained'),
            'PARCEL_DELIVERED' => $this->translator->trans('The shipment was delivered to the parcel self-service terminal'),
            'PARCEL_DEMAND' => $this->translator->trans('Secure on demand'),
            'PARCEL_DETAINED' => $this->translator->trans('Detained'),
            'PARCEL_DROPPED' => $this->translator->trans('The shipment is placed in the parcel self-service terminal for shipment'),
            'PARCEL_LOST' => $this->translator->trans('The shipment is gone'),
            'PARCEL_PICKED_UP_AT_LP' => $this->translator->trans('The shipment has been delivered to the receiver'),
            'PARCEL_PICKED_UP_BY_DELIVERYAGENT' => $this->translator->trans('The parcel is taken by courier from the parcel self-service terminal'),
            'PARCEL_PICKED_UP_BY_RECIPIENT' => $this->translator->trans('The shipment has been withdrawn by the receiver'),
            'RECEIVED_FROM_ANY_POST' => $this->translator->trans('Received'),
            'REDIRECTED_AT_HOME' => $this->translator->trans('Forwarded'),
            'REDIRECTED_IN_POST' => $this->translator->trans('Forwarded in post office'),
            'REDIRECTED' => $this->translator->trans('Forwarded-Served'),
            'REDIRECTING' => $this->translator->trans('Forwarding started'),
            'REFUND_AT_HOME' => $this->translator->trans('Refunded'),
            'REFUNDED_IN_POST' => $this->translator->trans('Returned to post office'),
            'REFUNDED' => $this->translator->trans('Refunded'),
            'REFUNDING' => $this->translator->trans('Return started'),
            'SENT' => $this->translator->trans('Sent'),
            'STORING' => $this->translator->trans('Transferred to storage'),
            'TRANSFERRED_FOR_DELIVERY' => $this->translator->trans('Passed on for deliver'),
            'UNDELIVERED' => $this->translator->trans('Not delivered'),
            'UNSUCCESSFUL_DELIVERY' => $this->translator->trans('Delivery failed'),
        ];
    }

    public function getStatusByKey($key)
    {
        if ($key && !empty(trim($key))) {
            $allStatuses = $this->getAllStatuses();

            if (key_exists($key, $allStatuses)) {
                return $allStatuses[$key];
            }
        }

        return '';
    }
}
