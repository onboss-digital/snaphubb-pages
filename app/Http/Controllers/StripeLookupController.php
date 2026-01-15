<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\StripeProductResolver;

class StripeLookupController extends Controller
{
    protected StripeProductResolver $resolver;

    public function __construct(StripeProductResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * GET /api/stripe/resolve-price?product_id=prod_xxx&currency=BRL
     */
    public function resolve(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string',
            'currency' => 'nullable|string',
        ]);

        $productId = $request->query('product_id');
        $currency = $request->query('currency', 'BRL');

        $result = $this->resolver->resolvePriceForProduct($productId, $currency);

        if (!$result) {
            return response()->json(['status' => 'error', 'message' => 'Price not found for product'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }
}
