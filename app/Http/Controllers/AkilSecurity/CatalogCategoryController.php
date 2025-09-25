<?php
namespace App\Http\Controllers\AkilSecurity;

use App\Http\Controllers\Controller;
use App\Models\AkilSecurity\CatalogCategory;
use Illuminate\Http\Request;

class CatalogCategoryController extends Controller
{
    public function dropDown()
    {
        return CatalogCategory::get();
    }

    public function index()
    {
        return CatalogCategory::where("name", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        $tracerId = 'Store TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $validated = $request->validate([
            "name"                => "required|min:4|max:255",
            "description"          => "nullable",
        ]);

        $Catalog = CatalogCategory::create($validated);

        info("Store Process End with $tracerId");

        return $Catalog;
    }

    public function updateProduct(Request $request)
    {
        $tracerId = 'Update TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $validated = $request->validate([
            "name"                => "required|min:4|max:255",
            "description"          => "nullable",
        ]);

        $Catalog = CatalogCategory::findOrFail($request->id);

        $Catalog->update($validated);

        info("Update Process End with $tracerId");

        return $Catalog->fresh();
    }

    public function destroy($id)
    {
        $Catalog = CatalogCategory::find($id);

        if (! $Catalog) {
            return;
        }

        $Catalog->delete();

        return response()->noContent();
    }
}
