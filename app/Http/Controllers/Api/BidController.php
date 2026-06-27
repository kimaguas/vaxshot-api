<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BidResource;
use App\Models\Bid;
use App\Models\BidItem;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BidController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Bid::with(['items', 'createdBy'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('bid_number',         'like', "%{$request->search}%")
                        ->orWhere('project_title',     'like', "%{$request->search}%")
                        ->orWhere('agency',             'like', "%{$request->search}%")
                        ->orWhere('bid_reference_no',  'like', "%{$request->search}%");
                });
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('bid_deadline', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('bid_deadline', '<=', $request->date_to));

        $perPage = min((int) ($request->per_page ?? 15), 999);
        $bids = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $allBids = Bid::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'new'         THEN 1 ELSE 0 END) as cnt_new,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as cnt_in_progress,
            SUM(CASE WHEN status = 'submitted'   THEN 1 ELSE 0 END) as cnt_submitted,
            SUM(CASE WHEN status = 'won'         THEN 1 ELSE 0 END) as cnt_won,
            SUM(CASE WHEN status = 'lose'        THEN 1 ELSE 0 END) as cnt_lose,
            SUM(CASE WHEN status = 'no_feedback' THEN 1 ELSE 0 END) as cnt_no_feedback,
            SUM(CASE WHEN status = 'cancelled'   THEN 1 ELSE 0 END) as cnt_cancelled,
            SUM(CASE WHEN status = 'rejected'    THEN 1 ELSE 0 END) as cnt_rejected,
            SUM(CASE WHEN status IN ('new','in_progress','submitted','won') THEN grand_total ELSE 0 END) as pipeline_value
        ")->first();

        return response()->json([
            'bids' => BidResource::collection($bids),
            'pagination' => [
                'total'        => $bids->total(),
                'per_page'     => $bids->perPage(),
                'current_page' => $bids->currentPage(),
                'last_page'    => $bids->lastPage(),
                'from'         => $bids->firstItem(),
                'to'           => $bids->lastItem(),
            ],
            'summary' => [
                'total'          => (int) $allBids->total,
                'active'         => (int) $allBids->cnt_new + (int) $allBids->cnt_in_progress + (int) $allBids->cnt_submitted,
                'won'            => (int) $allBids->cnt_won,
                'pipeline_value' => (float) $allBids->pipeline_value,
            ],
        ]);
    }

    public function show(Bid $bid)
    {
        return response()->json([
            'bid' => new BidResource($bid->load(['items', 'createdBy', 'attachments'])),
        ]);
    }

    public function extractFromFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:20480',
        ]);

        $file     = $request->file('file');
        $mime     = $file->getMimeType();
        $base64   = base64_encode(file_get_contents($file->getRealPath()));

        $contentBlock = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image',    'source' => ['type' => 'base64', 'media_type' => $mime,               'data' => $base64]];

        $prompt = <<<'PROMPT'
You are extracting bid/procurement data from a Philippine government PhilGEPS Bid Notice document.

Extract these fields and return ONLY a valid JSON object (no markdown, no explanation):
{
  "project_title":    "value from Title field",
  "agency":           "value from Procuring Entity field",
  "address":          "value from Area of Delivery field",
  "bid_reference_no":         "value from Reference Number field",
  "procurement_reference_no": "value from Solicitation Number field",
  "contact_person":   "value from Contact Person field (name only, no address)",
  "bid_posted_date":  "value from Date Published field in YYYY-MM-DD format",
  "bid_deadline":     "combine Closing Date and Closing Time into YYYY-MM-DDTHH:mm format (24h)",
  "pre_bid_date":     "combine Pre-bid Conference date and time into YYYY-MM-DDTHH:mm format (24h), null if not found",
  "bid_opening_date": "combine Bid Opening / Opening of Bids date and time into YYYY-MM-DDTHH:mm format (24h), null if not found",
  "notes":            "extract the Description section and Line Items table if present. Format with each piece of information on its own line using \\n. Start with the Description paragraph, then a blank line, then each line item on its own line as: '- [Item No.]. [Product/Service Name]: [Description] | Qty: [Quantity] [UOM] | Budget: PHP [Budget]'. Null if not found."
}

Rules:
- If a field is not found, use null.
- For bid_posted_date and bid_deadline, convert Philippine date formats (e.g. 24/06/2026 → 2026-06-24).
- For bid_deadline time, convert from 12h to 24h if needed (e.g. 1:00 PM → 13:00).
- Return ONLY the JSON object, nothing else.
PROMPT;

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]],
                ]],
            ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'AI extraction failed: ' . $response->body(),
            ], 500);
        }

        $text      = $response->json('content.0.text', '{}');
        // Strip markdown code fences if present
        $text      = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text      = preg_replace('/\s*```$/', '', $text);
        $extracted = json_decode($text, true) ?? [];

        return response()->json(['extracted' => $extracted]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_title'            => 'required|string|max:500',
            'agency'                   => 'required|string|max:500',
            'address'                  => 'nullable|string',
            'bid_reference_no'         => 'nullable|string|max:255',
            'procurement_reference_no' => 'nullable|string|max:255',
            'contact_person'           => 'nullable|string|max:255',
            'contact_no'               => 'nullable|string|max:100',
            'bid_posted_date'          => 'nullable|date',
            'pre_bid_date'             => 'nullable|date',
            'bid_deadline'             => 'nullable|date',
            'bid_submission_date'      => 'nullable|date',
            'bid_opening_date'         => 'nullable|date',
            'delivery_date'            => 'nullable|date',
            'status'                   => 'nullable|in:new,in_progress,submitted,won,lose,no_feedback,cancelled,rejected',
            'notes'                    => 'nullable|string',
            'items'                    => 'nullable|array',
            'items.*.item_description' => 'nullable|string|max:500',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit'             => 'nullable|string|max:50',
            'items.*.abc_budget'       => 'nullable|numeric|min:0',
            'items.*.bid_price'        => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $bid = Bid::create([
                'project_title'            => $request->project_title,
                'agency'                   => $request->agency,
                'address'                  => $request->address,
                'bid_reference_no'         => $request->bid_reference_no,
                'procurement_reference_no' => $request->procurement_reference_no,
                'contact_person'           => $request->contact_person,
                'contact_no'               => $request->contact_no,
                'bid_posted_date'          => $request->bid_posted_date,
                'pre_bid_date'             => $request->pre_bid_date,
                'bid_deadline'             => $request->bid_deadline,
                'bid_submission_date'      => $request->bid_submission_date,
                'bid_opening_date'         => $request->bid_opening_date,
                'delivery_date'            => $request->delivery_date,
                'status'                   => $request->status ?? 'new',
                'notes'                    => $request->notes,
                'created_by'               => Auth::id(),
                'grand_total'              => 0,
                'total_abc_amount'         => 0,
            ]);

            $grandTotal    = 0;
            $totalAbcAmount = 0;

            foreach ($request->items ?? [] as $item) {
                $qty              = (int) $item['quantity'];
                $bidPrice         = (float) ($item['bid_price'] ?? 0);
                $abcBudget        = (float) ($item['abc_budget'] ?? 0);
                $totalBidAmount   = $bidPrice * $qty;
                $itemAbcAmount    = $abcBudget * $qty;

                $grandTotal     += $totalBidAmount;
                $totalAbcAmount += $itemAbcAmount;

                BidItem::create([
                    'bid_id'           => $bid->id,
                    'item_description' => $item['item_description'],
                    'quantity'         => $qty,
                    'unit'             => $item['unit'] ?? null,
                    'abc_budget'       => $abcBudget,
                    'bid_price'        => $bidPrice,
                    'total_bid_amount' => $totalBidAmount,
                    'total_abc_amount' => $itemAbcAmount,
                ]);
            }

            $bid->update([
                'grand_total'      => $grandTotal,
                'total_abc_amount' => $totalAbcAmount,
            ]);

            $this->logActivity(
                action:      'CREATE',
                module:      'Bid Tracker',
                description: "Created bid: {$bid->bid_number} — {$bid->project_title} (₱" . number_format($grandTotal, 2) . ")",
            );

            DB::commit();

            return response()->json([
                'message' => 'Bid created successfully',
                'bid'     => new BidResource($bid->load(['items', 'createdBy'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create bid', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Bid $bid)
    {
        $request->validate([
            'project_title'            => 'required|string|max:500',
            'agency'                   => 'required|string|max:500',
            'address'                  => 'nullable|string',
            'bid_reference_no'         => 'nullable|string|max:255',
            'procurement_reference_no' => 'nullable|string|max:255',
            'contact_person'           => 'nullable|string|max:255',
            'contact_no'               => 'nullable|string|max:100',
            'bid_posted_date'          => 'nullable|date',
            'pre_bid_date'             => 'nullable|date',
            'bid_deadline'             => 'nullable|date',
            'bid_submission_date'      => 'nullable|date',
            'bid_opening_date'         => 'nullable|date',
            'delivery_date'            => 'nullable|date',
            'status'                   => 'nullable|in:new,in_progress,submitted,won,lose,no_feedback,cancelled,rejected',
            'notes'                    => 'nullable|string',
            'items'                    => 'nullable|array',
            'items.*.item_description' => 'nullable|string|max:500',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit'             => 'nullable|string|max:50',
            'items.*.abc_budget'       => 'nullable|numeric|min:0',
            'items.*.bid_price'        => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $bid->update([
                'project_title'            => $request->project_title,
                'agency'                   => $request->agency,
                'address'                  => $request->address,
                'bid_reference_no'         => $request->bid_reference_no,
                'procurement_reference_no' => $request->procurement_reference_no,
                'contact_person'           => $request->contact_person,
                'contact_no'               => $request->contact_no,
                'bid_posted_date'          => $request->bid_posted_date,
                'pre_bid_date'             => $request->pre_bid_date,
                'bid_deadline'             => $request->bid_deadline,
                'bid_submission_date'      => $request->bid_submission_date,
                'bid_opening_date'         => $request->bid_opening_date,
                'delivery_date'            => $request->delivery_date,
                'status'                   => $request->status ?? $bid->status,
                'notes'                    => $request->notes,
            ]);

            $bid->items()->delete();

            $grandTotal    = 0;
            $totalAbcAmount = 0;

            foreach ($request->items ?? [] as $item) {
                $qty             = (int) $item['quantity'];
                $bidPrice        = (float) ($item['bid_price'] ?? 0);
                $abcBudget       = (float) ($item['abc_budget'] ?? 0);
                $totalBidAmount  = $bidPrice * $qty;
                $itemAbcAmount   = $abcBudget * $qty;

                $grandTotal     += $totalBidAmount;
                $totalAbcAmount += $itemAbcAmount;

                BidItem::create([
                    'bid_id'           => $bid->id,
                    'item_description' => $item['item_description'],
                    'quantity'         => $qty,
                    'unit'             => $item['unit'] ?? null,
                    'abc_budget'       => $abcBudget,
                    'bid_price'        => $bidPrice,
                    'total_bid_amount' => $totalBidAmount,
                    'total_abc_amount' => $itemAbcAmount,
                ]);
            }

            $bid->update([
                'grand_total'      => $grandTotal,
                'total_abc_amount' => $totalAbcAmount,
            ]);

            $this->logActivity(
                action:      'UPDATE',
                module:      'Bid Tracker',
                description: "Updated bid: {$bid->bid_number} — {$bid->project_title}",
            );

            DB::commit();

            return response()->json([
                'message' => 'Bid updated successfully',
                'bid'     => new BidResource($bid->load(['items', 'createdBy', 'attachments'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update bid', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, Bid $bid)
    {
        $request->validate([
            'status' => 'required|in:new,in_progress,submitted,won,lose,no_feedback,cancelled,rejected',
        ]);

        $old = $bid->status;
        $bid->update(['status' => $request->status]);

        $this->logActivity(
            action:      'UPDATE',
            module:      'Bid Tracker',
            description: "Status changed for {$bid->bid_number}: {$old} → {$request->status}",
        );

        return response()->json(['message' => 'Status updated', 'bid' => new BidResource($bid->load(['items', 'createdBy']))]);
    }

    public function destroy(Bid $bid)
    {
        $this->logActivity(
            action:      'DELETE',
            module:      'Bid Tracker',
            description: "Deleted bid: {$bid->bid_number} — {$bid->project_title}",
        );

        $bid->items()->delete();
        $bid->delete();

        return response()->json(['message' => 'Bid deleted successfully']);
    }
}
