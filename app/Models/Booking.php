<?php

namespace App\Models;

use App\Http\Controllers\BookingController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use NumberFormatter;

class Booking extends Model
{
    use HasFactory;

    const VERIFICATION_SLEEP = 0;
    const VERIFICATION_REQUIRED = 1;
    const VERIFICATION_COMPLETED = 2;

    const AVAILABLE = 0;
    const BOOKED = 1;
    const CHECKED_IN = 2;
    const CHECKED_OUT = 3;

    const ROOM = "room";
    const HALL = "hall";



    protected $guarded = [];
    protected $appends = [
        'resourceId',
        'title',
        'background',
        'color',
        'status',
        'check_in_date',
        'check_out_date',

        'hall_check_in_date',
        'hall_check_out_date',
        'formatted_invoice_date',
        'total_with_posting',
        'total_with_posting_in_words',
    ];

    public function getFormattedInvoiceDateAttribute()
    {
        $dateToBeFilter = min(Carbon::parse($this->check_out_date), Carbon::parse($this->updated_at));
        return $dateToBeFilter->format('d M Y');
    }

    protected $casts = [
        // 'booking_date' => 'datetime:Y-m-d',
        'check_in_date' => 'datetime:d-M-y H:i',
        'check_out_date' => 'datetime:d-M-y H:i',
        'created_at' => 'datetime:Y-m-d H:i',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
    public function hallBooking()
    {
        return $this->belongsTo(HallBookings::class, 'id', 'booking_id');
    }
    public function postings()
    {
        return $this->hasMany(Posting::class);
    }

    public function payment_mode()
    {
        return $this->belongsTo(PaymentMode::class);
    }
    public function bookedRooms()
    {
        return $this->hasMany(BookedRoom::class);
    }

    public function orderRooms()
    {
        return $this->hasMany(OrderRoom::class);
    }

    public function GetResourceIdAttribute()
    {
        return Room::find($this->room_id)->room_no ?? '';
    }

    public function getCheckInDateAttribute()
    {
        return date('Y-m-d 12:00', strtotime($this->check_in));
    }

    public function getCheckOutDateAttribute()
    {
        return date('Y-m-d 11:00', strtotime($this->check_out));
    }

    public function getCheckInAttribute($val)
    {
        return date('Y-m-d', strtotime($val));
    }

    public function getCheckOutAttribute($val)
    {
        return date('Y-m-d', strtotime($val));
    }

    // public function getCheckOutAttribute()
    // {
    //     return date('Y-m-d', strtotime($this->check_out));
    // }

    public function getHallCheckInDateAttribute()
    {
        return $this->check_in;
    }

    public function getHallCheckOutDateAttribute()
    {
        return $this->check_out;
    }

    public function GetTitleAttribute()
    {
        return Customer::find($this->customer_id)->full_name ?? '';
    }

    public function GetBackgroundAttribute()
    {
        $status = Room::find($this->room_id)->status ?? '0';

        return (new BookingController())->getRoomStatusColorCode($status);


        // return match ($status) {
        //     '0' => 'linear-gradient(135deg, #23bdb8 0, #65a986 100%)',
        //     '1' => 'linear-gradient(135deg, #f48665 0, #d68e41 100%)',
        //     '2' => 'linear-gradient(135deg, #8e4cf1 0, #c554bc 100%)',
        //     '3' => 'linear-gradient(135deg, #289cf5, #4f8bb7)',
        //     '4' => 'linear-gradient(135deg, #34444c 0, #657177 100%)',
        //     '5' => 'green',
        // };
    }

    public function GetColorAttribute()
    {
        $status = Room::find($this->room_id)->status ?? '0';

        return (new BookingController())->getRoomStatusColorCode($status);


        // return match ($status) {
        //     '0' => 'linear-gradient(135deg, #23bdb8 0, #65a986 100%)',
        //     '1' => 'linear-gradient(135deg, #f48665 0, #d68e41 100%)',
        //     '2' => 'linear-gradient(135deg, #8e4cf1 0, #c554bc 100%)',
        //     '3' => 'linear-gradient(135deg, #289cf5, #4f8bb7)',
        //     '4' => 'linear-gradient(135deg, #34444c 0, #657177 100%)',
        //     '5' => 'green',
        // };
    }

    public function GetStatusAttribute()
    {
        return Room::find($this->room_id)->status ?? '';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withDefault([
            "name" => "---",
        ])
            ->with("sub_customers", "source");
    }

    // public function SetCheckInAttribute($value)
    // {


    //     // $this->attributes['check_in'] = $value . ' ' . date('H:i:s');
    //     if (session('isCheckInSes')) {
    //         $cod = $this->attributes['check_in'] = date('Y-m-d H:i', strtotime($value));
    //         BookedRoom::whereBookingId($this->attributes['id'])->update(['check_in' => $cod, 'booking_status' => 2]);
    //     } else {
    //         $this->attributes['check_in'] = $value . ' ' . date('H:i:s');
    //     }

    //     if (isset($this->attributes['room_category_type'])) {
    //         if ($this->attributes['room_category_type'] == 'Hall') {

    //             $this->attributes['check_in'] = $value;
    //         }
    //     }
    // }

    public function SetReferenceNoAttribute($value)
    {
        $this->attributes['reference_no'] = Str::lower($value);
    }

    // public function SetCheckOutAttribute($value)
    // {
    //     // if (session('isCheckoutSes')) {
    //     //     $cod = $this->attributes['check_out'] = date('Y-m-d H:i', strtotime($value));
    //     //     BookedRoom::whereBookingId($this->attributes['id'])->update(['check_out' => $cod, 'booking_status' => 3]);
    //     // } else {
    //     //     $cod = $this->attributes['check_out'] = date('Y-m-d 11:00', strtotime($value));
    //     // }

    //     // if (isset($this->attributes['room_category_type'])) {
    //     //     if ($this->attributes['room_category_type'] == 'Hall') {

    //     //         $this->attributes['check_out'] = $value;
    //     //     }
    //     // }
    //     // dd($cod);

    //     // $date = Carbon::parse($value);
    //     // $date->addDays(1);
    //     // $d = $date->format('Y-m-d');
    //     // $this->attributes['check_out'] = $d . ' ' . date('11:00:00');
    // }

    // public function GetBackgroundAttribute()
    // {
    //     $roomType =  Room::with('roomType')->find($this->room_id)->roomType->name ?? '';

    //     return match ($roomType) {
    //         'Single' => 'red',
    //         'Double' => 'green',
    //         'Triple' => 'Pink',
    //         'Family' => '#000',
    //         'King' => 'blue',
    //         'Sized' => 'gray',
    //         'Single' => 'black',
    //     };
    // }

    public function getDocumentAttribute($value)
    {
        if (!$value) {
            return null;
        }
        return asset('storage/documents/booking/' . $value);
    }

    public function scopeFilter($query, $filter)
    {
        $query->when($filter ?? false, fn($query, $search) =>
        $query->where(
            fn($query) => $query
                ->orWhere('id', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                ->orWhere('reservation_no', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                ->orWhere('reference_no', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                ->orWhere('type', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                ->orWhereHas(
                    'customer',
                    fn($query) =>
                    $query->Where('first_name', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                        ->orWhere('last_name', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                        ->orWhere('title', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                        ->orWhere('whatsapp', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                        ->orWhere('contact_no', env("WILD_CARD") ?? 'ILIKE', '%' . $search . '%')
                )
        ));
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function cityLedgerPayments()
    {
        return $this->hasMany(Payment::class)->where('is_city_ledger', 1);
    }

    public function withOutCityLedgerPayments()
    {
        return $this->hasMany(Payment::class)->where('is_city_ledger', 0);
    }

    public static function bookingAttributes()
    {
        return [
            "customer_id",
            "room_category_type",
            "booking_status",
            "group_name",
            "customer_type",
            "customer_status",
            "all_room_Total_amount",
            "total_extra",
            "type",
            "source",
            "agent_name",
            "check_in",
            "check_out",
            "discount",
            "advance_price",
            "payment_mode_id",
            "total_days",
            "sub_total",
            "after_discount",
            "sales_tax",
            "total_price",
            "remaining_price",
            "request",
            "receptionist_comments", //receptionist_comments
            "company_id",
            "remark",
            "rooms",
            "reference_no",
            "paid_by",
            "purpose",
            "gst_number",
            "source_type",
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, "id");
    }

    public function getTotalWithPostingAttribute()
    {
        return $this->total_price + ($this->postings()->sum("amount_with_tax") ?? 0);
    }

    public function getTotalWithPostingInWordsAttribute()
    {
        $amount = $this->total_with_posting;

        $formatter = new NumberFormatter('en_US', NumberFormatter::SPELLOUT);
        $text = ucwords($formatter->format($amount));
        return $text . " Only";
    }





    // protected static function boot()
    // {
    //     parent::boot();
    //     static::addGlobalScope('order', function (Builder $builder) {
    //         $builder->orderBy('id', 'desc');
    //     });
    // }
}
