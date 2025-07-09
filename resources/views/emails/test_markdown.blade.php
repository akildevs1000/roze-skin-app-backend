@component('mail::message')
# Dear {{ $full_name }},

The status of your shipment has been updated.
You can track your order using the link below:

[Track your shipment here](https://rozeskin.com/tracking/?tracking_id={{ $trackingId }})

Thank you for shopping with Roze Skincare!
@endcomponent
