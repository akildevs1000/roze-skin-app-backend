<?php
namespace App\Models;

use App\Traits\HasReferenceId;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasReferenceId;

    const CANCELLED = "cancelled";
    const COMPLETED = "completed";
    const PROCESSING = "processing";

    protected $fillable = [
        "customer_id",
        "user_id",
        "username",
        "email",
        "order_id",
        "order_date",
        "order_status",
        "currency",
        "shipping_charges",
        "total",
        "payment_method",
        "payment_method_title",
        "shipping_method",
        "items",

        "business_source_id",
        "delivery_service_id",
        "tracking_number",

        "paid_amount",

        "special_instructions",

        "cancel_reason",
    ];

    protected $with = [
        'customer.shipping_address',
        'customer.billing_address',
    ];

    protected $casts = [
        "items" => "array",
    ];

    protected $appends = ['date_time', 'total_paid_amount', 'reference_id', 'status_class'];

    public static array $statuses = [
        ['id' => 'processing', 'name' => 'Processing'],
        ['id' => 'completed', 'name' => 'Completed'],
        ['id' => 'cancelled', 'name' => 'Cancelled'],
        ['id' => 'refunded', 'name' => 'Refunded'],
    ];

    public static function getStatuses(): array
    {
        return self::$statuses;
    }

    public static function getStatusName(string $id): string
    {
        $status = collect(self::$statuses)->firstWhere('id', $id);
        return $status['name'] ?? ucfirst($id);
    }

    public function getStatusLabel(): string
    {
        return self::getStatusName($this->order_status);
    }

    /**
     * Get the CSS class for the order status (for frontend usage)
     */
    public function getStatusClassAttribute()
    {
        switch (strtolower($this->order_status)) {
            case 'processing':
                return 'blue';
            case 'paid':
                return 'green lighten-3 text--darken-3';
            case 'dispatched':
                return 'blue lighten-3 text--darken-3';
            case 'unpaid':
                return 'red';
            case 'cancelled':
                return 'grey';
            default:
                return 'green  text--darken-3';
        }
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function business_source()
    {
        return $this->belongsTo(BusinessSource::class)
            ->withDefault(
                ["name" => "---"]
            );
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function delivery_service()
    {
        return $this->belongsTo(DeliveryService::class)
            ->withDefault(
                ["name" => "---"]
            );
    }


    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId("ORD");
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->order_date));
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPaidAmountAttribute()
    {
        return $this->payments->sum('paid_amount');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
