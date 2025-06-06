<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomCleaning extends Model
{
    use HasFactory;

    const DIRTY = "Dirty";
    const CLEANED = "Cleaned";

    protected $guarded = [];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function cleaned_by_user()
    {
        return $this->belongsTo(User::class, "cleaned_by_user_id");
    }

    public function response_by_user()
    {
        return $this->belongsTo(User::class, "response_by_user_id");
    }

    public function company()
    {
        return $this->belongsTo(Company::class, "company_id");
    }

    public function getBeforeAttachmentAttribute($value)
    {
        if (!$value) return null;
        return asset('before_attachments/' . $value);
    }

    public function getAfterAttachmentAttribute($value)
    {
        if (!$value) return null;
        return asset('after_attachments/' . $value);
    }

    public function getVoiceNoteAttribute($value)
    {
        if (!$value) return null;
        return asset('voice_notes/' . $value);
    }

    public function getMaintenanceVoiceNoteAttribute($value)
    {
        if (!$value) return null;
        return asset('maintenance_voice_notes/' . $value);
    }
}
