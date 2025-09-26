<?php
namespace App\Http\Controllers\AkilSecurity;

use App\Http\Controllers\Controller;
use App\Models\AkilSecurity\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function index()
    {
        $catalog_category_id = request('catalog_category_id');

        $search = request('search');

        return Catalog::query()
            ->when($catalog_category_id, fn($q) => $q->where('catalog_category_id', $catalog_category_id))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where("model_number", "LIKE", "%{$search}%")
                        ->orWhere("title", "LIKE", "%{$search}%");
                });
            })
            ->with("catalog_category")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        $tracerId = 'Store TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $validated = $request->validate([
            "title"                => "required|min:4|max:255",
            "image"                => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "content"              => "nullable",
            "description"          => "nullable",

            "model_number"         => "required",
            "video_link"           => "nullable",
            "data_sheet_link"      => "nullable",
            "product_gallery_link" => "nullable",
            "website_link"         => "nullable",
            "catalog_category_id"  => "required",
        ]);

        if ($request->hasFile('image')) {
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('AkilSecurity/catalogs'), $filename);
            $validated['image'] = 'AkilSecurity/catalogs/' . $filename;
        }

        $Catalog = Catalog::create($validated);

        info("Store Process End with $tracerId");

        return $Catalog;
    }

    public function updateProduct(Request $request)
    {
        $tracerId = 'Update TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $validated = $request->validate([
            "title"                => "required|min:4|max:255",
            "image"                => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "content"              => "nullable",
            "description"          => "nullable",

            "model_number"         => "required",
            "video_link"           => "nullable",
            "data_sheet_link"      => "nullable",
            "product_gallery_link" => "nullable",
            "website_link"         => "nullable",
            "catalog_category_id"  => "required",
        ]);

        $Catalog = Catalog::findOrFail($request->id);

        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($Catalog->image && File::exists(public_path($Catalog->image))) {
                File::delete(public_path($Catalog->image));
            }

            // Save new image
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('AkilSecurity/catalogs'), $filename);
            $validated['image'] = 'AkilSecurity/catalogs/' . $filename;
        }

        $Catalog->update($validated);

        info("Update Process End with $tracerId");

        return $Catalog->fresh();
    }

    public function destroy($id)
    {
        $Catalog = Catalog::find($id);

        if (! $Catalog) {
            return;
        }

        // Delete the image file if it exists
        if ($Catalog->image && File::exists(public_path($Catalog->image))) {
            File::delete(public_path($Catalog->image));
        }

        $Catalog->delete();

        return response()->noContent();
    }
}
