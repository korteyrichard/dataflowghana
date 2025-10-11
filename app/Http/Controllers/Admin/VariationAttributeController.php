<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VariationAttribute;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VariationAttributeController extends Controller
{
    public function index()
    {
        $attributes = VariationAttribute::orderBy('created_at', 'desc')->get();
        
        return Inertia::render('Admin/Variations', [
            'attributes' => $attributes
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:variation_attributes,name',
            'values' => 'required|string'
        ]);

        $values = array_map('trim', explode(',', $request->values));
        $values = array_filter($values); // Remove empty values

        VariationAttribute::create([
            'name' => $request->name,
            'values' => $values
        ]);

        return redirect()->back()->with('success', 'Variation attribute created successfully.');
    }

    public function update(Request $request, VariationAttribute $variationAttribute)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:variation_attributes,name,' . $variationAttribute->id,
            'values' => 'required|string'
        ]);

        $values = array_map('trim', explode(',', $request->values));
        $values = array_filter($values); // Remove empty values

        $variationAttribute->update([
            'name' => $request->name,
            'values' => $values
        ]);

        return redirect()->back()->with('success', 'Variation attribute updated successfully.');
    }

    public function destroy(VariationAttribute $variationAttribute)
    {
        $variationAttribute->delete();
        
        return redirect()->back()->with('success', 'Variation attribute deleted successfully.');
    }
}