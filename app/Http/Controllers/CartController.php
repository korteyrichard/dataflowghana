<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Handle new single order format
        if ($request->has('network')) {
            $request->validate([
                'network' => 'required|string',
                'quantity' => 'required|string',
                'beneficiary_number' => 'required|string|max:20',
            ]);
            
            // Determine product type based on user role
            if ($user->role === 'customer') {
                $productType = 'customer_product';
            } elseif ($user->role === 'agent') {
                $productType = 'agent_product';
            } elseif ($user->role === 'dealer' || $user->role === 'admin') {
                $productType = 'dealer_product';
            } else {
                $productType = 'customer_product';
            }
            
            // Find the product by network and product type
            $product = Product::where('network', $request->network)
                ->where('product_type', $productType)
                ->first();
            
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 400);
            }
            
            // Find the variant by size - try multiple formats
            $sizeKey = strtolower($request->quantity) . 'gb'; // lowercase format
            $alternateSizeKey = strtoupper($request->quantity) . 'GB'; // uppercase format
            $plainSize = $request->quantity; // just the number
            
            $variant = $product->variants()
                ->where(function($query) use ($sizeKey, $alternateSizeKey, $plainSize) {
                    $query->whereJsonContains('variant_attributes->size', $sizeKey)
                          ->orWhereJsonContains('variant_attributes->size', $alternateSizeKey)
                          ->orWhereJsonContains('variant_attributes->size', $plainSize);
                })
                ->where('status', 'IN STOCK')
                ->first();
                
            if (!$variant) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Product variant not available'], 400);
                }
                return redirect()->back()->withErrors(['error' => 'Product variant not available']);
            }
            
            Cart::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'beneficiary_number' => $request->beneficiary_number,
                'network' => $request->network,
                'price' => $variant->price
            ]);
            
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Added to cart']);
            }
            
            return redirect()->back()->with('success', 'Product added to cart!');
        }
        
        // Original product-based logic
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|string|min:1',
            'beneficiary_number' => 'required|string|max:20',
        ]);
        
        // Find the first available variant for this product
        $variant = ProductVariant::where('product_id', $request->product_id)
            ->where('status', 'IN STOCK')
            ->where('quantity', '>', 0)
            ->first();
            
        if (!$variant) {
            return response()->json(['success' => false, 'message' => 'Product variant not available'], 400);
        }
        
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => $request->quantity,
            'beneficiary_number' => $request->beneficiary_number,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Added to cart']);
        }
        
        return redirect()->back()->with('success', 'Product added to cart!');
    }

    public function index()
    {
        $cartItems = Cart::with(['product', 'productVariant'])
            ->where('user_id', Auth::id())
            ->get()
            ->map(function($item) {
                $size = 'Unknown';
                if ($item->productVariant && isset($item->productVariant->variant_attributes['size'])) {
                    $size = strtoupper($item->productVariant->variant_attributes['size']);
                }
                
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $size,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product' => [
                        'name' => $item->product ? $item->product->name : 'Data Bundle',
                        'price' => $item->price ?? ($item->productVariant ? $item->productVariant->price : 0),
                        'network' => $item->network ?? ($item->product ? $item->product->network : 'Unknown'),
                        'expiry' => $item->product ? $item->product->expiry : '30 Days'
                    ]
                ];
            });
        return inertia('Dashboard/Cart', [
            'cartItems' => $cartItems,
        ]);
    }

    public function destroy($id)
    {
        $cart = Cart::where('user_id', Auth::id())->where('id', $id)->first();
        
        if (!$cart) {
            abort(404, 'Cart item not found');
        }
        
        $cart->delete();
        return redirect()->back()->with('success', 'Product removed from cart!');
    }
    
    public function processExcelToCart(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'network' => 'required|string'
        ]);
        
        try {
            $file = $request->file('file');
            Log::info('Processing Excel file: ' . $file->getClientOriginalName());
            
            // Read CSV file directly
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            
            if (empty($csvData)) {
                Log::error('Excel file is empty or has no data');
                return response()->json(['success' => false, 'message' => 'Excel file is empty']);
            }
            
            $data = [$csvData]; // Wrap in array to match Excel format
            
            $user = Auth::user();
            
            // Determine product type based on user role
            if ($user->role === 'customer') {
                $productType = 'customer_product';
            } elseif ($user->role === 'agent') {
                $productType = 'agent_product';
            } elseif ($user->role === 'dealer' || $user->role === 'admin') {
                $productType = 'dealer_product';
            } else {
                $productType = 'customer_product';
            }
            
            // Find the product by network and product type
            $product = Product::where('network', $request->network)
                ->where('product_type', $productType)
                ->first();
            
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 400);
            }
            $addedCount = 0;
            $phoneNumbers = [];
            $duplicates = [];
            $unavailableVariants = [];
            
            foreach ($csvData as $index => $row) {
                if ($index === 0) continue; // Skip header row
                
                Log::info('Processing row ' . $index . ': ' . json_encode($row));
                
                if (count($row) >= 2 && !empty($row[0]) && !empty($row[1])) {
                    $phoneNumber = trim($row[0]);
                    $bundleSize = trim($row[1]);
                    
                    // Add leading zero if phone number is 9 digits
                    if (preg_match('/^\d{9}$/', $phoneNumber)) {
                        $phoneNumber = '0' . $phoneNumber;
                    }
                    
                    Log::info('Phone: ' . $phoneNumber . ', Bundle: ' . $bundleSize);
                    
                    // Check for duplicates
                    if (in_array($phoneNumber, $phoneNumbers)) {
                        $duplicates[] = $phoneNumber;
                        Log::info('Duplicate phone number: ' . $phoneNumber);
                        continue;
                    }
                    $phoneNumbers[] = $phoneNumber;
                    
                    // Validate phone number format
                    if (preg_match('/^\d{10}$/', $phoneNumber)) {
                        // Find the variant by size - handle both cases and avoid double GB
                        $bundleSize = trim($bundleSize);
                        if (strtoupper(substr($bundleSize, -2)) === 'GB') {
                            $sizeKey = $bundleSize; // Already has GB
                        } else {
                            $sizeKey = $bundleSize . 'GB'; // Add GB
                        }
                        
                        Log::info('Looking for variant with size: ' . $sizeKey);
                        
                        // Try both uppercase and lowercase versions
                        $variant = $product->variants()
                            ->where(function($query) use ($sizeKey) {
                                $query->whereJsonContains('variant_attributes->size', strtoupper($sizeKey))
                                      ->orWhereJsonContains('variant_attributes->size', strtolower($sizeKey));
                            })
                            ->where('status', 'IN STOCK')
                            ->first();
                            
                        if ($variant) {
                            Log::info('Found variant: ' . $variant->id . ' with price: ' . $variant->price);
                            Cart::create([
                                'user_id' => $user->id,
                                'product_id' => $product->id,
                                'product_variant_id' => $variant->id,
                                'quantity' => 1,
                                'beneficiary_number' => $phoneNumber,
                                'network' => $request->network,
                                'price' => $variant->price
                            ]);
                            $addedCount++;
                            Log::info('Added to cart successfully');
                        } else {
                            Log::warning('No variant found for size: ' . $sizeKey);
                            // Check what variants are actually available
                            $allVariants = $product->variants()->where('status', 'IN STOCK')->get();
                            Log::info('All available variants for this product: ' . $allVariants->pluck('variant_attributes.size')->implode(', '));
                            $unavailableVariants[] = "$phoneNumber ({$bundleSize}GB)";
                        }
                    } else {
                        Log::warning('Invalid phone number format: ' . $phoneNumber);
                    }
                } else {
                    Log::warning('Row has insufficient data or empty values');
                }
            }
            
            $message = "Successfully added {$addedCount} items to cart";
            if (!empty($duplicates)) {
                $message .= ". Duplicate numbers skipped: " . implode(', ', array_unique($duplicates));
            }
            if (!empty($unavailableVariants)) {
                $message .= ". Unavailable variants: " . implode(', ', $unavailableVariants);
            }
            
            return response()->json([
                'success' => true, 
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            Log::error('Excel processing error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Error processing Excel file: ' . $e->getMessage()]);
        }
    }
    
    public function processBulkToCart(Request $request)
    {
        Log::info('Bulk order processing started', ['request' => $request->all()]);
        
        $request->validate([
            'numbers' => 'required|string',
            'network' => 'required|string'
        ]);
        
        try {
            $user = Auth::user();
            Log::info('User authenticated', ['user_id' => $user->id, 'role' => $user->role]);
            
            // Determine product type based on user role
            if ($user->role === 'customer') {
                $productType = 'customer_product';
            } elseif ($user->role === 'agent') {
                $productType = 'agent_product';
            } elseif ($user->role === 'dealer' || $user->role === 'admin') {
                $productType = 'dealer_product';
            } else {
                $productType = 'customer_product';
            }
            Log::info('Product type determined', ['product_type' => $productType]);
            
            // Find the product by network and product type
            $product = Product::where('network', $request->network)
                ->where('product_type', $productType)
                ->first();
            
            Log::info('Product search result', ['product_found' => $product ? true : false, 'network' => $request->network, 'product_type' => $productType]);
            
            if (!$product) {
                Log::error('Product not found', ['network' => $request->network, 'product_type' => $productType]);
                return response()->json(['success' => false, 'message' => 'Product not found'], 400);
            }
            
            $lines = explode("\n", trim($request->numbers));
            Log::info('Processing lines', ['lines' => $lines]);
            
            $addedCount = 0;
            $phoneNumbers = [];
            $duplicates = [];
            $unavailableVariants = [];
            
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                Log::info('Processing line', ['line' => $line, 'parts' => $parts]);
                
                if (count($parts) >= 2) {
                    $phoneNumber = trim($parts[0]);
                    $bundleSize = trim($parts[1]);
                    
                    Log::info('Parsed data', ['phone' => $phoneNumber, 'bundle' => $bundleSize]);
                    
                    // Check for duplicates
                    if (in_array($phoneNumber, $phoneNumbers)) {
                        $duplicates[] = $phoneNumber;
                        Log::info('Duplicate phone number found', ['phone' => $phoneNumber]);
                        continue;
                    }
                    $phoneNumbers[] = $phoneNumber;
                    
                    // Validate phone number format
                    Log::info('Validating phone number', ['phone' => $phoneNumber, 'length' => strlen($phoneNumber)]);
                    if (preg_match('/^\d{10}$/', $phoneNumber)) {
                        Log::info('Phone number validation passed', ['phone' => $phoneNumber]);
                        // Find the variant by size - try multiple formats
                        $sizeKey = $bundleSize . 'gb'; // lowercase format
                        $alternateSizeKey = $bundleSize . 'GB'; // uppercase format
                        Log::info('Looking for variant', ['size_keys' => [$sizeKey, $alternateSizeKey]]);
                        
                        $variant = $product->variants()
                            ->where(function($query) use ($sizeKey, $alternateSizeKey, $bundleSize) {
                                $query->whereJsonContains('variant_attributes->size', $sizeKey)
                                      ->orWhereJsonContains('variant_attributes->size', $alternateSizeKey)
                                      ->orWhereJsonContains('variant_attributes->size', $bundleSize);
                            })
                            ->where('status', 'IN STOCK')
                            ->first();
                            
                        Log::info('Variant search result', ['variant_found' => $variant ? true : false, 'tried_size_keys' => [$sizeKey, $alternateSizeKey, $bundleSize]]);
                            
                        if ($variant) {
                            Log::info('Found variant', ['variant_id' => $variant->id, 'price' => $variant->price]);
                            
                            try {
                                Cart::create([
                                    'user_id' => $user->id,
                                    'product_id' => $product->id,
                                    'product_variant_id' => $variant->id,
                                    'quantity' => 1,
                                    'beneficiary_number' => $phoneNumber,
                                    'network' => $request->network,
                                    'price' => $variant->price
                                ]);
                                $addedCount++;
                                Log::info('Successfully added to cart', ['phone' => $phoneNumber, 'bundle' => $bundleSize]);
                            } catch (\Exception $e) {
                                Log::error('Failed to add to cart', ['phone' => $phoneNumber, 'error' => $e->getMessage()]);
                            }
                        } else {
                            $unavailableVariants[] = "$phoneNumber ({$bundleSize}GB)";
                        }
                    }
                }
            }
            
            $message = "Successfully added {$addedCount} items to cart";
            if (!empty($duplicates)) {
                $message .= ". Duplicate numbers skipped: " . implode(', ', array_unique($duplicates));
            }
            if (!empty($unavailableVariants)) {
                $message .= ". Unavailable variants: " . implode(', ', $unavailableVariants);
            }
            
            return response()->json([
                'success' => true, 
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            Log::error('Bulk processing error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error processing bulk numbers']);
        }
    }
}