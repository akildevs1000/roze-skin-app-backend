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
        'dob',
        'phone',
        'whatsapp',
    ];

    protected $appends = ['full_name', 'customer_with_phone', 'date_time', 'dob_display', 'reference_id'];

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId('CST');
    }

    public function getDOBDisplayAttribute()
    {
        return date("d-M-Y", strtotime($this->dob));
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getCustomerWithPhoneAttribute()
    {
        return $this->full_name . ' - ' . $this->phone;
    }

    /**
     * The customer's current address (the row that is not tied to any order).
     * This preserves the existing single-address behaviour everywhere it is read.
     */
    public function shipping_address()
    {
        return $this->hasOne(ShippingAddress::class)->whereNull('order_id');
    }

    public function billing_address()
    {
        return $this->hasOne(BillingAddress::class)->whereNull('order_id');
    }

    /**
     * The customer's full address book — the current address plus every
     * address frozen onto one of their orders.
     */
    public function shipping_addresses()
    {
        return $this->hasMany(ShippingAddress::class)->orderByDesc('id');
    }

    public function billing_addresses()
    {
        return $this->hasMany(BillingAddress::class)->orderByDesc('id');
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

public static function storeOrUpdateCustomerWithAddresses(array $data, ?int $customerId = null, bool $overrideCurrentAddress = true)
{
    // On update, find by ID. On create, try phone to avoid duplicates.
    $customer = $customerId
        ? self::find($customerId)
        : self::where('phone', $data['customer']['phone'])->first();

    if ($customer) {
        $customer->first_name = $data['customer']['first_name'];
        $customer->last_name  = $data['customer']['last_name'];
        $customer->email      = $data['customer']['email'] ?? null;
        $customer->dob        = $data['customer']['dob'] ?? date("Y-m-d");
        $customer->phone      = $data['customer']['phone'];
        $customer->whatsapp   = $data['customer']['whatsapp'];
        $customer->save();
    } else {
        $customer = self::create([
            'first_name' => $data['customer']['first_name'],
            'last_name'  => $data['customer']['last_name'],
            'email'      => $data['customer']['email'] ?? null,
            'dob'        => $data['customer']['dob'] ?? date("Y-m-d"),
            'phone'      => $data['customer']['phone'],
            'whatsapp'   => $data['customer']['whatsapp'],
        ]);
    }

    self::storeOrUpdateShippingAddress($customer->id, $data['shipping_address'], $overrideCurrentAddress);
    self::storeOrUpdateBillingAddress($customer->id, $data['billing_address'], $overrideCurrentAddress);

    return $customer;
}


    public static function storeOrUpdateShippingAddress($customerId, array $shippingData, bool $override = true)
    {
        // The "current" address is the one not tied to any order (order_id IS NULL).
        self::upsertCurrentAddress(ShippingAddress::class, $customerId, $shippingData, $override);
    }

    public static function storeOrUpdateBillingAddress($customerId, array $billingData, bool $override = true)
    {
        self::upsertCurrentAddress(BillingAddress::class, $customerId, $billingData, $override);
    }

    /**
     * Maintain the customer's single "current" address (order_id IS NULL).
     *
     * When $override is true (customer form) the current address is updated.
     * When false (placing an order) the existing current address is kept as-is
     * — it is only seeded if the customer has no current address yet.
     */
    private static function upsertCurrentAddress(string $model, $customerId, array $data, bool $override): void
    {
        $match = ['customer_id' => $customerId, 'order_id' => null];
        $cols  = self::addressColumns($data);

        if ($override) {
            $model::updateOrCreate($match, $cols);
            return;
        }

        // Order flow: don't overwrite an address the customer already has.
        $hasContent = collect($cols)->contains(fn($v) => trim((string) $v) !== '');
        if (! $hasContent) {
            return;
        }

        $existing = $model::where($match)->first();
        if (! $existing) {
            $model::create($match + $cols);
        } elseif (self::isAddressEmpty($existing)) {
            $existing->update($cols);
        }
    }

    private static function isAddressEmpty($row): bool
    {
        foreach (['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $col) {
            if (trim((string) $row->$col) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Freeze the shipping/billing address onto a single order. Keyed on
     * order_id so re-saving the same order updates that order's own copy
     * and never touches another order's or the customer's current address.
     */
    public static function storeOrderAddresses(int $customerId, int $orderId, array $shippingData, array $billingData): void
    {
        ShippingAddress::updateOrCreate(
            ['order_id' => $orderId],
            self::addressColumns($shippingData) + ['customer_id' => $customerId]
        );

        BillingAddress::updateOrCreate(
            ['order_id' => $orderId],
            self::addressColumns($billingData) + ['customer_id' => $customerId]
        );
    }

    private static function addressColumns(array $data): array
    {
        return [
            'address_1' => $data['address_1'] ?? null,
            'address_2' => $data['address_2'] ?? null,
            'city'      => $data['city'] ?? null,
            'state'     => $data['state'] ?? null,
            'postcode'  => $data['postcode'] ?? null,
            'country'   => $data['country'] ?? null,
        ];
    }
}
