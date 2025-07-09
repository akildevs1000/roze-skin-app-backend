@component('mail::message')
# Dear Customer!

The status of your shipment has been updated.

[Track your shipment here](https://rozeskin.com/tracking/?tracking_id={{ $trackingId }})

Thanks,<br>
RozeSkin
@endcomponent
