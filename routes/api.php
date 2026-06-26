<?php

use App\Http\Controllers\AkilSecurity\CatalogCategoryController;
use App\Http\Controllers\AkilSecurity\CatalogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessSourceController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentModeController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WhatsappClientController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    Log::channel('health')->info('Backend is working');
    return "Backend is working";
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/top-menu', [MenuController::class, 'getTopmenu']);
Route::get('/side-menu', [MenuController::class, 'getSidemenu']);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/generate_otp/{useId}', [AuthController::class, 'generateOTP']);
Route::post('/check_otp/{otp}', [AuthController::class, 'checkOTP']);

Route::apiResource('products', ProductController::class);
Route::get('product-list', [ProductController::class, "dropDown"]);
Route::post('products-update', [ProductController::class, "updateProduct"]);

Route::apiResource('business-sources', BusinessSourceController::class);
Route::get('business-source-list', [BusinessSourceController::class, "dropDown"]);

Route::apiResource('payments', PaymentController::class);
Route::get('payment-list', [PaymentController::class, "dropDown"]);

Route::apiResource('payment-modes', PaymentModeController::class);
Route::get('payment-mode-list', [PaymentModeController::class, "dropDown"]);

Route::apiResource('product-categories', ProductCategoryController::class);
Route::get('product-category-list', [ProductCategoryController::class, "dropDown"]);

Route::apiResource('customers', CustomerController::class);
Route::get('customer-list', [CustomerController::class, "dropDown"]);
Route::get('get-customer', [CustomerController::class, "getCustomer"]);

Route::apiResource('products', ProductController::class);
Route::get('product-list', [ProductController::class, "dropDown"]);

Route::apiResource('delivery-services', DeliveryServiceController::class);
Route::get('delivery-service-list', [DeliveryServiceController::class, "dropDown"]);

Route::apiResource('orders', OrderController::class);
Route::get('orders-stats', [OrderController::class, "stats"]);
Route::get('order-list', [OrderController::class, "dropDown"]);
Route::get('lattest-order', [OrderController::class, "latestOrder"]);
Route::post('order-creater-acknowledge', [OrderController::class, "orderCreateAcknowledge"]);
Route::post('cancel-order', [OrderController::class, "cancelOrder"]);
Route::post('return-order', [OrderController::class, "returnOrder"]);
Route::get('status-list', [OrderController::class, "getStatusesDropdown"]);
Route::get('order-qty-by-date', [OrderController::class, "orderQtyByDate"]);
Route::get('order-sum-by-date', [OrderController::class, "orderSumByDate"]);
Route::get('orders-stats-by-date', [OrderController::class, "statsByDate"]);
Route::post('whatsapp-order', [OrderController::class, "WhatsappStore"]);

Route::middleware('auth:sanctum')->prefix('external')->group(function () {
    Route::get('orders', [\App\Http\Controllers\External\OrderController::class, 'index']);
});


Route::apiResource('invoices', InvoiceController::class);
Route::get('invoice-list', [InvoiceController::class, "dropDown"]);
Route::get('invoices-stats', [InvoiceController::class, "stats"]);

Route::post('/whatsapp-client-json', [WhatsappClientController::class, 'store']);
Route::get('/whatsapp-client-json', [WhatsappClientController::class, 'show']);
Route::get('/whatsapp-all-clients', [WhatsappClientController::class, 'list']);

Route::resource('template', TemplateController::class);
Route::get('template-list', [TemplateController::class, "dropDown"]);
Route::get('template-types', [TemplateController::class, "templateTypes"]);

Route::get('report-products', [ReportController::class, "products"]);
Route::get('report-payment-modes', [ReportController::class, "payment_modes"]);
Route::get('report-sources', [ReportController::class, "sources"]);

Route::get('manifest-report', [ReportController::class, "manifestReport"]);
Route::get('awb-print-report', [ReportController::class, "awbPrintReport"]);

Route::get('product-report', [ProductController::class, "report"]);
Route::get('source-report', [BusinessSourceController::class, "report"]);
Route::get('deliver-service-report', [DeliveryServiceController::class, "report"]);
Route::get('payment-mode-report', [PaymentModeController::class, "report"]);
Route::get('city-report', [CityController::class, "report"]);
Route::get('customer-report', [CustomerController::class, "report"]);
Route::get('repeated-customer-report', [CustomerController::class, "repeatedCustomerReport"]);

Route::apiResource('akil-security-catalog', CatalogController::class);
Route::get('akil-security-catalog-list', [CatalogController::class, "dropDown"]);
Route::post('akil-security-catalog-update', [CatalogController::class, "updateProduct"]);

Route::apiResource('catalog-categories', CatalogCategoryController::class);
Route::get('catalog-category-list', [CatalogCategoryController::class, "dropDown"]);


Route::get('order-items-list', [OrderItemController::class, "dropDown"]);
Route::get('order-items', [OrderItemController::class, "index"]);

/*
|--------------------------------------------------------------------------
| Inventory / Stock management
|--------------------------------------------------------------------------
*/

// Vendors (suppliers) — managed separately, linked to purchasing
Route::apiResource('vendors', \App\Http\Controllers\Inventory\VendorController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::get('vendor-list', [\App\Http\Controllers\Inventory\VendorController::class, "dropDown"]);

// Purchase Orders (creating a PO never changes stock)
Route::apiResource('purchase-orders', \App\Http\Controllers\Inventory\PurchaseOrderController::class);
Route::get('purchase-order-list', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "dropDown"]);
Route::get('purchase-orders-next-number', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "nextNumber"]);
Route::post('purchase-orders/{purchaseOrder}/cancel', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "cancel"]);
Route::post('purchase-orders/{purchaseOrder}/attachments', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "storeAttachments"]);
Route::delete('purchase-order-attachments/{attachment}', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "destroyAttachment"]);
Route::get('purchase-order-report', [\App\Http\Controllers\Inventory\PurchaseOrderController::class, "report"]);

// Warehouses — PO delivery destination (Deliver To)
Route::apiResource('warehouses', \App\Http\Controllers\Inventory\WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
Route::get('warehouse-list', [\App\Http\Controllers\Inventory\WarehouseController::class, "dropDown"]);

// Goods Receiving (GRN) — stock increases here, partial receiving supported
Route::apiResource('goods-receipts', \App\Http\Controllers\Inventory\GoodsReceiptController::class)->only(['index', 'show', 'store']);

// Inventory item catalog (master items)
Route::get('inventory-items/{inventoryItem}/history', [\App\Http\Controllers\Inventory\InventoryItemController::class, "history"]);
Route::apiResource('inventory-items', \App\Http\Controllers\Inventory\InventoryItemController::class)->only(['index', 'store', 'update', 'destroy']);

// Inventory list / low stock / reorder level
Route::get('inventory', [\App\Http\Controllers\Inventory\InventoryController::class, "index"]);
Route::get('inventory-list', [\App\Http\Controllers\Inventory\InventoryController::class, "dropDown"]);
Route::get('inventory-low-stock', [\App\Http\Controllers\Inventory\InventoryController::class, "lowStock"]);
Route::post('inventory-reorder-level', [\App\Http\Controllers\Inventory\InventoryController::class, "setReorderLevel"]);
Route::post('inventory-opening-stock', [\App\Http\Controllers\Inventory\InventoryController::class, "openingStock"]);
Route::get('inventory-stock-sync', [\App\Http\Controllers\Inventory\InventoryController::class, "stockSyncStatus"]);
Route::post('inventory-stock-sync', [\App\Http\Controllers\Inventory\InventoryController::class, "setStockSync"]);

// Inventory dashboard
Route::get('inventory-dashboard', [\App\Http\Controllers\Inventory\InventoryDashboardController::class, "index"]);
Route::get('inventory-dashboard-recent', [\App\Http\Controllers\Inventory\InventoryDashboardController::class, "recentMovements"]);

// WordPress product -> inventory item mapping (drives stock sync on convert-to-invoice)
Route::get('wp-product-map-products', [\App\Http\Controllers\Inventory\WpProductMapController::class, "products"]);
Route::post('wp-product-map', [\App\Http\Controllers\Inventory\WpProductMapController::class, "store"]);
Route::delete('wp-product-map/{id}', [\App\Http\Controllers\Inventory\WpProductMapController::class, "destroy"]);

// In routes/web.php
Route::get('/test-mail', function() {
    Mail::raw('Test text', function ($message) {
        $message->to('akildevs1000@gmail.com')->subject('Test');
    });
    return 'Check your inbox/logs';
});