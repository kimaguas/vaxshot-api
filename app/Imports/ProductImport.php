<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductTier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{
    protected int $supplierId;
    protected int $importedCount = 0;
    protected array $errors = [];

    public function __construct(int $supplierId)
    {
        $this->supplierId = $supplierId;
    }

    /**
     * Expected columns (one row per tier):
     *   brand_name*    — product name; rows with the same brand_name share one product
     *   min_qty*       — minimum quantity for this tier (integer)
     *   price*         — price for this tier
     *   max_qty        — maximum quantity (leave blank for unlimited last tier)
     *   indication     — vaccine type / indication (first row of each product)
     *   lot_no         — lot number
     *   acquisition_cost
     *   expiry_date    — YYYY-MM-DD
     *   effective_date — YYYY-MM-DD (price list date)
     */
    public function collection(Collection $rows)
    {
        $grouped = [];

        foreach ($rows as $index => $row) {
            $brandName = trim($row['brand_name'] ?? '');
            $minQty    = $row['min_qty'] ?? null;
            $price     = $row['price']   ?? null;

            if (!$brandName || $minQty === null || $price === null) {
                $this->errors[] = "Row " . ($index + 2) . ": brand_name, min_qty, and price are required";
                continue;
            }

            if (!is_numeric($minQty) || (int) $minQty < 1) {
                $this->errors[] = "Row " . ($index + 2) . ": min_qty must be a positive integer";
                continue;
            }

            if (!is_numeric($price) || $price < 0) {
                $this->errors[] = "Row " . ($index + 2) . ": price must be a valid number";
                continue;
            }

            $maxQty = isset($row['max_qty']) && $row['max_qty'] !== '' && $row['max_qty'] !== null
                ? (int) $row['max_qty']
                : null;

            $tierLabel = $maxQty !== null
                ? "{$minQty}-{$maxQty}vls"
                : (int) $minQty . "vls & up";

            if (!isset($grouped[$brandName])) {
                $grouped[$brandName] = [
                    'brand_name'       => $brandName,
                    'indication'       => trim($row['indication']       ?? ''),
                    'lot_no'           => trim($row['lot_no']           ?? ''),
                    'acquisition_cost' => is_numeric($row['acquisition_cost'] ?? null) ? (float) $row['acquisition_cost'] : null,
                    'expiry_date'      => $row['expiry_date']      ?? null,
                    'effective_date'   => $row['effective_date']   ?? null,
                    'tiers'            => [],
                ];
            }

            $grouped[$brandName]['tiers'][] = [
                'min_qty'    => (int) $minQty,
                'max_qty'    => $maxQty,
                'tier_label' => $tierLabel,
                'price'      => (float) $price,
            ];
        }

        DB::beginTransaction();
        try {
            foreach ($grouped as $entry) {
                $product = Product::create([
                    'supplier_id'      => $this->supplierId,
                    'brand_name'       => $entry['brand_name'],
                    'indication'       => $entry['indication']       ?: null,
                    'lot_no'           => $entry['lot_no']           ?: null,
                    'acquisition_cost' => $entry['acquisition_cost'],
                    'expiry_date'      => $entry['expiry_date']      ?: null,
                    'effective_date'   => $entry['effective_date']   ?: null,
                    'status'           => 'active',
                ]);

                foreach ($entry['tiers'] as $sortOrder => $tier) {
                    ProductTier::create([
                        'catalog_id'  => $product->id,
                        'tier_label'  => $tier['tier_label'],
                        'min_qty'     => $tier['min_qty'],
                        'max_qty'     => $tier['max_qty'],
                        'price'       => $tier['price'],
                        'sort_order'  => $sortOrder,
                    ]);
                }

                $this->importedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
