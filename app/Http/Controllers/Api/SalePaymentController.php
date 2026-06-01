<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalePaymentResource;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalePaymentController extends Controller
{
    use LogsActivity;

    // Get all payments for a sale
    public function index(Sale $sale)
    {
        $payments = $sale->payments()
                    ->with('receivedBy')
                    ->latest()
                    ->get();

        return response()->json([
            'payments' => SalePaymentResource::collection($payments)
        ], 200);
    }

    // Record a payment
    public function store(Request $request, Sale $sale)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0',
            'payment_method'   => 'required|in:cash,check,bank_transfer',
            'payment_date'     => 'required|date',
            'or_number'        => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
        ]);

        // Can only pay confirmed sales
        if ($sale->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed sales can be paid'
            ], 422);
        }

        // Check if already fully paid
        if ($sale->payment_status === 'paid') {
            return response()->json([
                'message' => 'Sale is already fully paid'
            ], 422);
        }

        // Check if payment exceeds balance
        if ($request->amount > $sale->balance) {
            return response()->json([
                'message' => "Payment amount exceeds balance of {$sale->balance}"
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create payment
            $payment = SalePayment::create([
                'sale_id'          => $sale->id,
                'received_by'      => auth()->id(),
                'or_number'        => $request->or_number,
                'amount'           => $request->amount,
                'payment_method'   => $request->payment_method,
                'payment_date'     => $request->payment_date,
                'reference_number' => $request->reference_number,
                'notes'            => $request->notes,
            ]);

            // Update OR number on sale if provided
            if ($request->or_number) {
                $sale->update(['or_number' => $request->or_number]);
            }

            // Update sale payment status
            $sale->load('payments');
            $sale->updatePaymentStatus();

            // Log activity
            $this->logActivity(
                action      : 'PAYMENT',
                module      : 'Sales',
                description : "Recorded payment of ₱{$request->amount} for sale: {$sale->sale_number} - OR: {$request->or_number} - Method: {$request->payment_method}",
                newData     : $payment->toArray()
            );

            DB::commit();

            return response()->json([
                'message'        => 'Payment recorded successfully',
                'payment'        => new SalePaymentResource($payment),
                'payment_status' => $sale->fresh()->payment_status,
                'balance'        => $sale->fresh()->balance,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record payment',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}