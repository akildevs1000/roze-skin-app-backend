<?php

namespace App\Models;

use App\Http\Controllers\BookingController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookedRoom extends Model
{
    use HasFactory;

    protected $guarded = [];

    const AVAILABLE = 0;
    const BOOKED = 1;
    const CHECKED_IN = 2;
    const CHECKED_OUT = 3;
    const DIRTY_ROOM = 4;

    const ROOM_STATUS = [0 => "Available", 1 => "Booked", 2 => "Checked In", 3 => "Checked Out", 4 => "Checked", 5 => "Dirty"];


    // protected $fillable = [
    //     "room_no",
    //     "room_type",
    //     "room_id",
    //     "price",
    //     "days",
    //     "sgst",
    //     "cgst",
    //     "check_in",
    //     "check_out",
    //     "bed_amount",
    //     "room_discount",
    //     "after_discount",
    //     "room_tax",
    //     "total_with_tax",
    //     "total",
    //     "grand_total",
    //     "company_id",
    //     "no_of_adult",
    //     "no_of_child",
    //     "no_of_baby",
    //     "tot_adult_food",
    //     "tot_child_food",
    //     "discount_reason",
    //     "meal"
    // ];

    protected $appends = [
        'resourceId',
        'title',
        'background',
        'check_in_time',
        'check_out_time',
        'end',

        'checkin_date_only',
        'checkin_datetime_only',

        'checkout_date_only',
        'checkout_datetime_only'
    ];

    protected $casts = [
        'posting_date' => 'datetime:',
    ];

    protected $with = ['postings', 'booking'];

    public function getAppends()
    {
        return array_merge($this->with, $this->appends);
    }

    public function getCustomAppends()
    {
        $custom = [
            'created_at',
            'updated_at',
            'booking_status',
        ];
        return array_merge($this->with, $this->appends, $custom);
    }

    public function getWithoutAppends()
    {
        $attributes = $this->attributesToArray();

        unset($attributes['check_in_time']);
        unset($attributes['check_out_time']);
        unset($attributes['end']);
        unset($attributes['background']);
        unset($attributes['resourceId']);
        unset($attributes['title']);
        unset($attributes['booking_status']);
        unset($attributes['created_at']);
        unset($attributes['updated_at']);

        return $attributes;
    }
    public function orderRooms()
    {
        return $this->hasMany(OrderRoom::class, "booked_room_id", "id");
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class)
            ->withSum('orderRooms', 'base_price')
            ->withSum('orderRooms', 'grand_total')
            ->withSum('orderRooms', 'food_plan_price')
            ->withSum('orderRooms', 'bed_amount')
            ->withSum('orderRooms', 'early_check_in')
            ->withSum('orderRooms', 'late_check_out')
            ->with("orderRooms")->orderBy("id", "desc");
    }

    public function sub_customer_room_history()
    {
        return $this->belongsTo(SubCustomerRoomHistory::class, "room_id", "room_id")
            ->with("sub_customer");
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function GetTitleAttribute()
    {
        return Customer::find($this->customer_id)->full_name ?? '';
    }

    /**
     * Get all of the posts for the BookedRoom
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function postings()
    {
        return $this->hasMany(Posting::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function foodplan()
    {
        return $this->belongsTo(FoodPlan::class, "food_plan_id");
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all of the comments for the BookedRoom
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */

    public function device()
    {
        return $this->belongsTo(Device::class, "room_id", "room_id");
    }

    public function roomType()
    {
        return $this->hasOneThrough(RoomType::class, BookedRoom::class, 'room_id', 'id', 'room_id', 'room_id');
    }
    public function GetBackgroundAttribute()
    {
        $model = Booking::find($this->booking_id);


        if (!$model) {
            return  "green";
        }
        if ($model->booking_status == 1 && $model->advance_price == 0) {
            (int) $status = 6;
        } else if (($model->booking_status == 2) && (date('Y-m-d', strtotime($model->check_out)) <= date('Y-m-d'))) {
            (int) $status = 7;
        } else if (($model->booking_status > 3 || $model->booking_status == 0) && $model->balance > 0) {
            if ($this->booking_status == 3) {
                (int)  $status = $this->booking_status;
            } else {
                (int) $status = 8;
            }
        } else {
            if ($model->booking_status == 3) {
                (int)  $status = $this->booking_status;
                // echo $status;
            } else {
                (int) $status = $model->booking_status ?? 0;
                // echo $model->booking_status;
            }
        }

        if ($status == 0 && $model->balance > 0) {
            (int) $status = 8;
        }


        return (new BookingController())->getRoomStatusColorCode($status);

        // return match ($status) {
        //     1 => 'linear-gradient(135deg, #02ADA4  0, #02ADA4 100%)', //paid advance
        //     0 => 'linear-gradient(135deg, #23bdb8 0, #65a986 100%)', //available room
        //     // 2 => 'linear-gradient(135deg, #F95C39 0, #F95C39 100%)', //check in room
        //     2 => 'linear-gradient(135deg, #800000 0, #800000 100%)', //check in room
        //     // 3 => 'linear-gradient(135deg, #4390FC, #4390FC)',
        //     // 3 => 'linear-gradient(135deg, #d66d75   0, #e29587 100%)', //dirty room
        //     3 => 'linear-gradient(135deg, #ff0000   0, #ff0000 100%)', //dirty room
        //     4 => 'linear-gradient(135deg, #34444c 0, #657177 100%)',
        //     5 => 'green',
        //     6 => 'linear-gradient(135deg, #FFBE00 0, #FFBE00 100%)', //only booking
        //     7 => 'linear-gradient(135deg, #4390FC      0, #4390FC 100%)', //expect check out
        //     8 => 'linear-gradient(135deg, #680081      0, #680081 100%)', //city ledger
        // };

        return match ($status) {

            // 0 => 'linear-gradient(135deg, #23bdb8 0, #65a986 100%)', //available room
            // 1 => '#92d051',
            // 2 => '#0f642b',
            // 3 => '#fe0000',
            // 4 => 'linear-gradient(135deg, #34444c 0, #657177 100%)',
            // 5 => 'green',
            // 6 => 'linear-gradient(135deg, #FFBE00 0, #FFBE00 100%)', //only booking
            // 7 => 'linear-gradient(135deg, #4390FC      0, #4390FC 100%)', //expect check out
            // 8 => 'linear-gradient(135deg, #680081      0, #680081 100%)', //city ledger
        };
    }

    // public function SetCheckInAttribute($value)
    // {
    //     if (isset($this->attributes['room_category_type'])) {
    //         if ($this->attributes['room_category_type'] == 'Hall') {

    //             $this->attributes['check_in'] = $value;
    //         }
    //     } else {
    //         $this->attributes['check_in'] = $value . ' ' . date('H:i:s');
    //     }
    //     // $this->attributes['check_in'] = date('Y-m-d h:m', strtotime($value));


    // }

    // public function SetCheckOutAttribute($value)
    // {
    //     // dd($this->attributes['check_out'] = date('Y-m-d 11:00', strtotime($value)));

    //     if (isset($this->attributes['room_category_type'])) {
    //         if ($this->attributes['room_category_type'] == 'Hall') {

    //             $this->attributes['check_out'] = $value;
    //         }
    //     } else {

    //     }

    //     $this->attributes['check_out'] = date('Y-m-d 11:00', strtotime($value));

    //     // $date = Carbon::parse($value);
    //     // $date->addDays(1);
    //     // $d = $date->format('Y-m-d');
    //     // $this->attributes['check_out'] = $d . ' ' . date('11:00:00');

    // }

    public function GetCheckInTimeAttribute()
    {
        // CheckOUtDateForEachDate
        $time = $this->check_in;

        return date('H:i', strtotime($time));
    }

    public function GetCheckOutTimeAttribute()
    {
        // CheckOUtDateForEachDate
        $time = $this->check_out;

        return date('H:i', strtotime($time));
    }

    public function GetEndAttribute()
    {
        $date = date_create($this->check_out);
        // date_modify($date, "-1 days");
        return date_format($date, "Y-m-d");
        // return date_format($date, "Y-m-d H:i");
    }
    public function GetCheckInDateOnlyAttribute()
    {
        return date('Y-m-d', strtotime($this->check_in));
    }

    public function GetCheckInDateTimeOnlyAttribute()
    {
        return date('Y-m-d 12:00', strtotime($this->check_in));
    }

    public function GetCheckOutDateOnlyAttribute()
    {
        return date('Y-m-d', strtotime($this->check_out));
    }

    public function GetCheckOutDateTimeOnlyAttribute()
    {
        return date('Y-m-d 11:00', strtotime($this->check_out));
    }

    public function GetResourceIdAttribute()
    {
        return Room::find($this->room_id)->room_no ?? '';
    }

    public static function bookedRoomAttributes()
    {
        return [
            "room_no",
            "room_type",
            "room_id",
            "price",
            "days",
            "sgst",
            "cgst",
            "check_in",
            "check_out",
            "bed_amount",
            "room_discount",
            "after_discount",
            "room_tax",
            "total_with_tax",
            "total",
            "grand_total",
            "company_id",
            "no_of_adult",
            "no_of_child",
            "no_of_baby",
            "tot_adult_food",
            "tot_child_food",
            "discount_reason",
            "meal",
        ];
    }
}
