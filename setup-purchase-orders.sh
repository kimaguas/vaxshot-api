#!/bin/bash

echo "📋 Setting up Purchase Orders Module..."

# Create Migrations
php artisan make:migration create_purchase_orders_table
php artisan make:migration create_purchase_order_items_table
php artisan make:migration create_purchase_order_receipts_table
php artisan make:migration create_purchase_order_receipt_items_table
php artisan make:migration create_product_batches_table
php artisan make:migration create_inventory_logs_table

# Create Models
php artisan make:model PurchaseOrder
php artisan make:model PurchaseOrderItem
php artisan make:model PurchaseOrderReceipt
php artisan make:model PurchaseOrderReceiptItem
php artisan make:model ProductBatch
php artisan make:model InventoryLog

# Create Controllers
php artisan make:controller Api/PurchaseOrderController --api
php artisan make:controller Api/PurchaseOrderReceiptController --api

# Create Resources
php artisan make:resource PurchaseOrderResource
php artisan make:resource PurchaseOrderItemResource
php artisan make:resource PurchaseOrderReceiptResource
php artisan make:resource ProductBatchResource
php artisan make:resource InventoryLogResource

# Create Requests
php artisan make:request StorePurchaseOrderRequest
php artisan make:request UpdatePurchaseOrderRequest
php artisan make:request StorePurchaseOrderReceiptRequest

echo "✅ Purchase Orders module files created!"