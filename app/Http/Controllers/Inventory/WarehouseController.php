<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /** Lightweight list for the PO delivery-address (Deliver To) select. */
    public function dropDown()
    {
        return Warehouse::where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        return Warehouse::when($request->filled('search'), fn ($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function store(Request $request)
    {
        return Warehouse::create($this->validated($request));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->validated($request));

        return $warehouse;
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'phone'   => 'nullable|string|max:120',
            'trn'     => 'nullable|string|max:60',
            'status'  => 'nullable|in:active,inactive',
        ]);
    }
}
