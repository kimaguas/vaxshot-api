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
     * Expected columns: brand_name, generic_name, tier_label, price, effective_date (optional)
     * Rows with the same brand_name are grouped into one product entry with multiple tiers.
     */
    public function collection(Collection $rows)
    {
        $grouped = [];

        foreach ($rows as $index => $row) {
            $brandName   = trim($row['brand_name'] ?? '');
            $genericName = trim($row['generic_name'] ?? '');
            $tierLabel   = trim($row['tier_label'] ?? '');
            $price       = $row['price'] ?? null;

            if (!$brandName || !$tierLabel || $price === null) {
                $this->errors[] = "Row " . ($index + 2) . ": brand_name, tier_label, and price are required";
                continue;
            }

            if (!is_numeric($price) || $price < 0) {
                $this->errors[] = "Row " . ($index + 2) . ": price must be a valid number";
                continue;
            }

            if (!isset($grouped[$brandName])) {
                $grouped[$brandName] = [
                    'brand_name'     => $brandName,
                    'generic_name'   => $genericName ?: $brandName,
                    'effective_date' => $row['effective_date'] ?? null,
                    'tiers'          => [],
                ];
            }

            $grouped[$brandName]['tiers'][] = [
                'tier_label' => $tierLabel,
                'price'      => (float) $price,
            ];
        }

        DB::beginTransaction();
        try {
            foreach ($grouped as $entry) {
                $product = Product::create([
                    'supplier_id'    => $this->supplierId,
                    'brand_name'     => $entry['brand_name'],
                    'generic_name'   => $entry['generic_name'],
                    'effective_date' => $entry['effective_date'] ?: null,
                    'status'         => 'active',
                ]);

                foreach ($entry['tiers'] as $sortOrder => $tier) {
                    ProductTier::create([
                        'catalog_id'  => $product->id,
                        'tier_label'  => $tier['tier_label'],
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
