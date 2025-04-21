<?php

namespace App\Models;

use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    use HasReferenceId;


    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp',
    ];

    protected $appends = ['full_name', 'date_time', 'reference_id'];

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId('CST');
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function shipping_address()
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function billing_address()
    {
        return $this->hasOne(BillingAddress::class);
    }

    /**
     * Get all of the orders for the Customer
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public static function storeOrUpdateCustomerWithAddresses(array $data)
    {
        $customer = self::where('phone', $data['customer']['phone'])
            ->orWhere('email', $data['customer']['email'] ?? '')
            ->first();

        if ($customer) {
            // Update
            $customer->first_name = $data['customer']['first_name'];
            $customer->last_name = $data['customer']['last_name'];
            $customer->email = $data['customer']['email'] ?? null;
            $customer->phone = $data['customer']['phone'];
            $customer->save();
        } else {
            // Create
            $customer = self::create([
                'first_name' => $data['customer']['first_name'],
                'last_name' => $data['customer']['last_name'],
                'email' => $data['customer']['email'] ?? null,
                'phone' => $data['customer']['phone'],
            ]);
        }

        self::storeOrUpdateShippingAddress($customer->id, $data['shipping_address']);
        self::storeOrUpdateBillingAddress($customer->id, $data['billing_address']);

        return $customer;
    }


    public static function storeOrUpdateShippingAddress($customerId, array $shippingData)
    {
        ShippingAddress::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'address_1' => $shippingData['address_1'] ?? null,
                'address_2' => $shippingData['address_2'] ?? null,
                'city' => $shippingData['city'] ?? null,
                'state' => $shippingData['state'] ?? null,
                'postcode' => $shippingData['postcode'] ?? null,
                'country' => $shippingData['country'] ?? null,
            ]
        );
    }

    public static function storeOrUpdateBillingAddress($customerId, array $billingData)
    {
        BillingAddress::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'address_1' => $billingData['address_1'] ?? null,
                'address_2' => $billingData['address_2'] ?? null,
                'city' => $billingData['city'] ?? null,
                'state' => $billingData['state'] ?? null,
                'postcode' => $billingData['postcode'] ?? null,
                'country' => $billingData['country'] ?? null,
            ]
        );
    }
}
