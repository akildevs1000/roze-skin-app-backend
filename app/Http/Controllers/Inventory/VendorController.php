<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /** Lightweight list for the vendor search/select on POs & GRNs. */
    public function dropDown()
    {
        return Vendor::where('status', 'active')->orderBy('name')->get();
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        return Vendor::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = "%{$request->search}%";
                $q->where('company_name', 'LIKE', $term)
                    ->orWhere('first_name', 'LIKE', $term)
                    ->orWhere('last_name', 'LIKE', $term)
                    ->orWhere('mobile', 'LIKE', $term)
                    ->orWhere('work_phone', 'LIKE', $term)
                    ->orWhere('email', 'LIKE', $term);
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('company_name')
            ->paginate($perPage);
    }

    public function show(Vendor $vendor)
    {
        return $vendor;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return Vendor::create($data);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validated($request));

        return $vendor->fresh();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'        => 'nullable|string|max:20',
            'first_name'   => 'nullable|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'tax_number'   => 'nullable|string|max:100',
            'email'        => 'nullable|email|max:255',
            'work_phone'   => 'nullable|string|max:50',
            'mobile'       => 'nullable|string|max:50',
            'country'      => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'city'         => 'nullable|string|max:100',
            'zip_code'     => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
        ]);

        // A vendor needs at least a company name or a person name.
        $fullName = trim(implode(' ', array_filter([$data['first_name'] ?? null, $data['last_name'] ?? null])));
        if (empty($data['company_name']) && $fullName === '') {
            abort(response()->json(['message' => 'Provide a company name or a first/last name.'], 422));
        }

        // 'name' is the derived display label kept in sync for selects & listings.
        $data['name']   = $data['company_name'] ?: $fullName;
        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return response()->noContent();
    }
}
