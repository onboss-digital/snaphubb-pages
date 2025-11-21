<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    /**
     * Clear last order session keys after client has fired purchase event
     */
    public function clearLastOrder(Request $request)
    {
        try {
            session()->forget('last_order_transaction');
            session()->forget('last_order_amount');
            session()->forget('last_order_customer');
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController: failed to clear session', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
