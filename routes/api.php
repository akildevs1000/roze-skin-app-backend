<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\BusinessSourceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\PaymentModeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WhatsappClientController;

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


Route::apiResource('invoices', InvoiceController::class);
Route::get('invoice-list', [InvoiceController::class, "dropDown"]);
Route::get('invoices-stats', [InvoiceController::class, "stats"]);

Route::post('/whatsapp-client-json', [WhatsappClientController::class, 'store']);
Route::get('/whatsapp-client-json', [WhatsappClientController::class, 'show']);
Route::get('/whatsapp-all-clients', [WhatsappClientController::class, 'list']);

Route::resource('template', TemplateController::class);
Route::get('template-list', [TemplateController::class, "dropDown"]);
Route::get('template-types', [TemplateController::class, "templateTypes"]);
