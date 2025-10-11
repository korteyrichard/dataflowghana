<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AFAProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AFAProductController extends Controller
{
    public function index(Request $request)
    {
        $afaProducts = AFAProduct::query();

        if ($request->has('status') && $request->input('status') !== '') {
            $afaProducts->where('status', $request->input('status'));
        }

        return Inertia::render('Admin/AFAProducts', [
            'afaProducts' => $afaProducts->latest()->paginate(15),
            'filterStatus' => $request->input('status', ''),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:IN_STOCK,OUT_OF_STOCK',
        ]);

        AFAProduct::create([
            'name' => $request->name,
            'price' => $request->price,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.afa-products')->with('success', 'AFA Product created successfully.');
    }

    public function update(Request $request, AFAProduct $afaProduct)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:IN_STOCK,OUT_OF_STOCK',
        ]);

        $afaProduct->update([
            'name' => $request->name,
            'price' => $request->price,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.afa-products')->with('success', 'AFA Product updated successfully.');
    }

    public function destroy(AFAProduct $afaProduct)
    {
        $afaProduct->delete();
        return redirect()->route('admin.afa-products')->with('success', 'AFA Product deleted successfully.');
    }
}