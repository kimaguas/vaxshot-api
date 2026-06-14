<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Mail\QuotationMail;
use App\Models\EmailTemplate;
use App\Models\Quotation;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuotationController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Quotation::with(['createdBy', 'items'])
            ->withCount('items')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($sq) use ($request) {
                $sq->where('quotation_number', 'like', "%{$request->search}%")
                   ->orWhere('customer_name',  'like', "%{$request->search}%")
                   ->orWhere('email',          'like', "%{$request->search}%");
            }))
            ->latest();

        $quotations = $query->paginate(10);

        return response()->json([
            'quotations' => QuotationResource::collection($quotations),
            'pagination' => [
                'total'        => $quotations->total(),
                'per_page'     => $quotations->perPage(),
                'current_page' => $quotations->currentPage(),
                'last_page'    => $quotations->lastPage(),
                'from'         => $quotations->firstItem(),
                'to'           => $quotations->lastItem(),
            ],
        ]);
    }

    public function show(Quotation $quotation)
    {
        return response()->json([
            'quotation' => new QuotationResource($quotation->load(['createdBy', 'items'])),
        ]);
    }

    public function store(StoreQuotationRequest $request)
    {
        DB::beginTransaction();
        try {
            $quotation = Quotation::create([
                'created_by'     => Auth::id(),
                'customer_name'  => $request->customer_name,
                'contact_name'   => $request->contact_name,
                'address'        => $request->address,
                'email'          => $request->emails[0],
                'emails'         => $request->emails,
                'cc_emails'      => $request->cc_emails ?? [],
                'quotation_date' => $request->quotation_date,
                'total_amount'   => 0,
                'status'         => 'draft',
                'notes'          => $request->notes,
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $product    = Product::findOrFail($item['product_id']);
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $total     += $totalPrice;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $product->brand_name,
                    'description'  => $item['description'] ?? null,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'total_price'  => $totalPrice,
                    'expiry_date'  => $item['expiry_date'] ?? null,
                ]);
            }

            $quotation->update(['total_amount' => $total]);

            $this->logActivity(
                action:      'CREATE',
                module:      'Quotations',
                description: "Created quotation {$quotation->quotation_number} for {$quotation->customer_name}",
                newData:     $quotation->toArray()
            );

            DB::commit();

            return response()->json([
                'message'   => 'Quotation created successfully',
                'quotation' => new QuotationResource($quotation->load('items')),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create quotation',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be edited'], 422);
        }

        $request->validate([
            'customer_name'        => 'sometimes|required|string|max:255',
            'contact_name'         => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'emails'               => 'sometimes|required|array|min:1',
            'emails.*'             => 'required|email|max:255',
            'cc_emails'            => 'nullable|array',
            'cc_emails.*'          => 'email|max:255',
            'quotation_date'       => 'sometimes|required|date',
            'notes'                => 'nullable|string',
            'items'                => 'sometimes|array|min:1',
            'items.*.product_id'   => 'required_with:items|exists:products,id',
            'items.*.quantity'     => 'required_with:items|integer|min:1',
            'items.*.unit_price'   => 'required_with:items|numeric|min:0',
            'items.*.description'  => 'nullable|string|max:500',
            'items.*.expiry_date'  => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            $oldData = $quotation->only([
                'customer_name', 'contact_name', 'address', 'emails', 'cc_emails', 'notes', 'total_amount',
            ]);

            $updateData = $request->only(['customer_name', 'contact_name', 'address', 'quotation_date', 'notes']);
            if ($request->has('emails')) {
                $updateData['emails'] = $request->emails;
                $updateData['email']  = $request->emails[0];
            }
            if ($request->has('cc_emails')) {
                $updateData['cc_emails'] = $request->cc_emails ?? [];
            }
            $quotation->update($updateData);

            if ($request->has('items')) {
                $quotation->items()->delete();
                $total = 0;
                foreach ($request->items as $item) {
                    $product    = Product::findOrFail($item['product_id']);
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $total     += $totalPrice;
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'product_id'   => $item['product_id'],
                        'product_name' => $product->brand_name,
                        'description'  => $item['description'] ?? null,
                        'quantity'     => $item['quantity'],
                        'unit_price'   => $item['unit_price'],
                        'total_price'  => $totalPrice,
                        'expiry_date'  => $item['expiry_date'] ?? null,
                    ]);
                }
                $quotation->update(['total_amount' => $total]);
            }

            $this->logActivity(
                action:      'UPDATE',
                module:      'Quotations',
                description: "Updated quotation {$quotation->quotation_number}",
                oldData:     $oldData,
                newData:     $quotation->fresh()->toArray()
            );

            DB::commit();

            return response()->json([
                'message'   => 'Quotation updated successfully',
                'quotation' => new QuotationResource($quotation->load('items')),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update quotation',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be deleted'], 422);
        }

        $this->logActivity(
            action:      'DELETE',
            module:      'Quotations',
            description: "Deleted draft quotation {$quotation->quotation_number} for {$quotation->customer_name}",
            oldData:     $quotation->toArray()
        );

        $quotation->items()->delete();
        $quotation->delete();

        return response()->json(['message' => 'Quotation deleted successfully']);
    }

    public function send(Request $request, Quotation $quotation)
    {
        $resolvedSubject   = null;
        $resolvedBody      = null;
        $resolvedSignature = null;

        if ($request->template_id) {
            $template = EmailTemplate::findOrFail($request->template_id);
            $map = [
                '{customer_name}'    => $quotation->customer_name,
                '{contact_name}'     => $quotation->contact_name ?? $quotation->customer_name,
                '{quotation_number}' => $quotation->quotation_number,
                '{quotation_date}'   => $quotation->quotation_date->format('F d, Y'),
            ];
            $resolvedSubject   = str_replace(array_keys($map), $map, $template->subject);
            $resolvedBody      = str_replace(array_keys($map), $map, $template->body);
            $resolvedSignature = $template->signature;
        }

        try {
            $recipients = $quotation->emails ?? [$quotation->email];

            $mailable = (new QuotationMail(
                $quotation->load(['items', 'items.product.tiers']),
                $resolvedSubject,
                $resolvedBody,
                $resolvedSignature
            ))->to($recipients);

            if (!empty($quotation->cc_emails)) {
                $mailable->cc($quotation->cc_emails);
            }

            Mail::send($mailable);

            $quotation->update(['status' => 'sent']);

            $recipientList = implode(', ', $recipients);

            $this->logActivity(
                action:      'SEND',
                module:      'Quotations',
                description: "Sent quotation {$quotation->quotation_number} to {$recipientList}"
            );

            return response()->json([
                'message'   => "Quotation sent successfully to {$recipientList}",
                'quotation' => new QuotationResource($quotation->load('items')),
            ]);

        } catch (\Exception $e) {
            \Log::error('Quotation mail failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send email. Please check your mail configuration.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
