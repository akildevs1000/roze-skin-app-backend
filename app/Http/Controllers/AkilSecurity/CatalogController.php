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
        return Catalog::where("title", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        $tracerId = 'Store TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $validated = $request->validate([
            "title"   => "required|min:5|max:255",
            "image"   => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "content" => "nullable",
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
            "title"   => "required|min:5|max:255",
            "image"   => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "content" => "nullable",
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
