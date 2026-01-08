<?php

namespace App\Http\Controllers;

use App\Models\OrderBump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BumpController extends Controller
{
    /**
     * Get bumps by payment method or all active bumps
     * GET /api/bumps?method=card
     * GET /api/bumps?method=pix
     * GET /api/bumps (all active)
     */
    public function list(Request $request)
    {
        try {
            $method = $request->query('method', null);

            if ($method) {
                $bumps = OrderBump::getByPaymentMethod($method);
            } else {
                $bumps = OrderBump::getAllActive();
            }

            return response()->json([
                'status' => 'success',
                'data' => $bumps,
                'count' => count($bumps),
            ]);
        } catch (\Exception $e) {
            Log::error('BumpController: Error fetching bumps', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar order bumps',
            ], 500);
        }
    }

    /**
     * Get bumps separated by payment method
     * GET /api/bumps/by-method
     */
    public function byMethod(Request $request)
    {
        try {
            $cardBumps = OrderBump::getByPaymentMethod('card');
            $pixBumps = OrderBump::getByPaymentMethod('pix');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'card_bumps' => $cardBumps,
                    'pix_bumps' => $pixBumps,
                ],
                'count' => count($cardBumps) + count($pixBumps),
            ]);
        } catch (\Exception $e) {
            Log::error('BumpController: Error fetching bumps by method', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar order bumps',
            ], 500);
        }
    }
}
