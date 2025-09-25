<?php
namespace App\Models\AkilSecurity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Catalog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "akil_security_catalogs";

    protected $appends = ['date_time', 'display_image'];

    public function getDisplayImageAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return URL::to($this->image); // returns full URL like http://yourdomain.com/products/filename.webp
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }

    /**
     * Get the user that owns the Catalog
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function catalog_category()
    {
        return $this->belongsTo(CatalogCategory::class);
    }

}
