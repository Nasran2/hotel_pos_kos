<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackOffice\AccountController;
use App\Http\Controllers\BackOffice\ActivityLogController;
use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\KitchenController;
use App\Http\Controllers\BackOffice\OnlineOrderController;
use App\Http\Controllers\BackOffice\PosController;
use App\Http\Controllers\BackOffice\ReportController;
use App\Http\Controllers\BackOffice\ResourceController;
use App\Http\Controllers\BackOffice\SettingsController;
use App\Http\Controllers\BackOffice\SystemToolsController;
use App\Http\Controllers\KitchenDisplayController;
use Illuminate\Support\Facades\Route;

Route::get('/kod', [KitchenDisplayController::class, 'index'])->name('kod.index')->block();
Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
Route::post('/kod/unlock', [KitchenDisplayController::class, 'unlock'])->middleware('throttle:kod-pin')->name('kod.unlock')->block();
Route::post('/kod/lock', [KitchenDisplayController::class, 'lock'])->name('kod.lock')->block();
Route::middleware('kitchen.pin')->prefix('kod')->name('kod.')->group(function (): void {
    Route::delete('/orders/{order}', [KitchenDisplayController::class, 'delete'])->whereNumber('order')->name('delete')->block();
    Route::get('/orders', [KitchenDisplayController::class, 'feed'])->name('feed')->block();
    Route::post('/orders/{order}/status', [KitchenDisplayController::class, 'update'])->whereNumber('order')->name('status')->block();
});

Route::get('/fix-db', function () {
    abort_if(config('demo.enabled'), 403, 'Database repair is disabled in demo mode.');
    DB::statement('ALTER TABLE order_tokens ENGINE = InnoDB;');
    DB::statement('ALTER TABLE daily_token_counters ENGINE = InnoDB;');

    $tables = ['hold_orders', 'sales', 'online_orders'];
    $dropped = [];

    foreach ($tables as $tableName) {
        $columns = Schema::getColumnListing($tableName);
        if (in_array('order_token_id', $columns)) {
            try {
                DB::statement("ALTER TABLE {$tableName} DROP FOREIGN KEY {$tableName}_order_token_id_foreign");
            } catch (Exception $e) {
            }
            try {
                DB::statement("ALTER TABLE {$tableName} DROP COLUMN order_token_id");
            } catch (Exception $e) {
            }
            $dropped[] = $tableName;
        }
    }

    return 'Database fixed! Engines changed to InnoDB and duplicate columns dropped from: '.implode(', ', $dropped).'.<br><br>You can now go back to your terminal and run <b>php artisan migrate</b>.';
});

Route::middleware('guest')->group(function (): void {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::get('/login', [LoginController::class, 'create']);
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/system-tools', [SystemToolsController::class, 'index'])->middleware('permission:system_tools.view')->name('system-tools.index');
    Route::post('/system-tools/maintenance', [SystemToolsController::class, 'toggleMaintenance'])->middleware('permission:system_tools.view')->name('system-tools.maintenance');
    Route::post('/system-tools/commands', [SystemToolsController::class, 'runCommand'])->middleware('permission:system_tools.view')->name('system-tools.commands.run');
    Route::post('/system-tools/sequence', [SystemToolsController::class, 'runSequence'])->middleware('permission:system_tools.view')->name('system-tools.sequence');
    Route::post('/system-tools/upgrade', [SystemToolsController::class, 'uploadUpgrade'])->middleware('permission:system_tools.view')->name('system-tools.upgrade');
    Route::patch('/system-tools/users/{user}/toggle-active', [SystemToolsController::class, 'toggleUserStatus'])->middleware('permission:system_tools.view')->name('system-tools.users.toggle-active');

    Route::middleware('system.lock')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

        Route::delete('/kitchen/orders/{order}', [KitchenController::class, 'delete'])->middleware('permission:kitchen.update')->whereNumber('order')->name('kitchen.delete')->block();
        Route::post('/pos/kitchen/{order}/stop', [KitchenController::class, 'requestStop'])->middleware('permission:pos.send_kitchen')->whereNumber('order')->name('pos.kitchen.stop')->block();
        Route::get('/kitchen/orders', [KitchenController::class, 'feed'])->name('kitchen.feed')->block();
        Route::post('/kitchen/orders/{order}/status', [KitchenController::class, 'update'])->middleware('permission:kitchen.update')->whereNumber('order')->name('kitchen.status')->block();
        Route::post('/pos/kitchen/{order}/served', [KitchenController::class, 'update'])->middleware('permission:pos.send_kitchen')->whereNumber('order')->name('pos.kitchen.served')->block();
        Route::post('/pos/kitchen/send', [PosController::class, 'sendToKitchen'])->middleware('permission:pos.send_kitchen')->name('pos.kitchen.send')->block();

        Route::get('/pos', [PosController::class, 'index'])->middleware('permission:pos.access')->name('pos.index');
        Route::get('/pos/next-token', [PosController::class, 'nextToken'])->middleware('permission:pos.access')->name('pos.next-token');
        Route::post('/pos/register/open', [PosController::class, 'openRegister'])->middleware('permission:pos.open_register')->name('pos.register.open');
        Route::post('/pos/register/close', [PosController::class, 'closeRegister'])->middleware('permission:pos.close_register')->name('pos.register.close');
        Route::get('/pos/register/close-summary', [PosController::class, 'getRegisterCloseSummary'])->middleware('permission:pos.close_register')->name('pos.register.close-summary');
        Route::get('/pos/register/close-cash-book/pdf', [PosController::class, 'downloadRegisterCloseCashBookPdf'])->middleware('permission:pos.close_register')->name('pos.register.close-cash-book.pdf');
        Route::post('/pos/hold', [PosController::class, 'hold'])->middleware('permission:pos.hold_order')->name('pos.hold');
        Route::get('/pos/held-order/{hold}', [PosController::class, 'resumeHeldOrder'])->middleware('permission:pos.resume_hold_order')->name('pos.resume-held-order');
        Route::get('/pos/hold/{table}', [PosController::class, 'resume'])->middleware('permission:pos.resume_hold_order')->name('pos.resume');
        Route::delete('/pos/hold/{hold}', [PosController::class, 'cancelHold'])->middleware('permission:pos.hold_order')->name('pos.hold.cancel');
        Route::post('/pos/transfer', [PosController::class, 'transfer'])->middleware('permission:pos.transfer_table')->name('pos.transfer');
        Route::post('/pos/print-bill', [PosController::class, 'printBill'])->middleware('permission:pos.print_bill')->name('pos.print');
        Route::post('/pos/payment', [PosController::class, 'pay'])->middleware('permission:pos.payment')->name('pos.pay');
        Route::post('/pos/customer-due-payment', [PosController::class, 'storeCustomerDuePayment'])->middleware('permission:pos.payment')->name('pos.customer-due-payment.store');
        Route::post('/pos/supplier-payment', [PosController::class, 'storeSupplierPayment'])->middleware('permission:purchases.edit')->name('pos.supplier-payment.store');
        Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->middleware('permission:customers.create')->name('pos.customers.store');
        Route::post('/pos/waiters', [PosController::class, 'storeWaiter'])->middleware('permission:waiters.create')->name('pos.waiters.store');
        Route::post('/pos/expense-categories', [PosController::class, 'storeExpenseCategory'])->middleware('permission:pos.close_register')->name('pos.expense-categories.store');
        Route::post('/pos/expense', [PosController::class, 'storeExpense'])->middleware('permission:pos.close_register')->name('pos.expense.store');
        Route::delete('/pos/expense/{expense}', [PosController::class, 'destroyExpense'])->middleware('permission:pos.close_register')->name('pos.expense.destroy');

        Route::get('/pos/online-orders', [OnlineOrderController::class, 'index'])->middleware('permission:online_orders.view')->name('online-orders.index');
        Route::post('/pos/online-orders', [OnlineOrderController::class, 'store'])->middleware('permission:online_orders.create')->name('online-orders.store');
        Route::post('/pos/online-orders/{onlineOrder}/payment', [OnlineOrderController::class, 'addPayment'])->middleware('permission:online_orders.add_payment')->name('online-orders.payment');
        Route::post('/pos/online-orders/{onlineOrder}/status', [OnlineOrderController::class, 'updateStatus'])->middleware('permission:online_orders.edit')->name('online-orders.status');
        Route::get('/pos/online-orders/{onlineOrder}/print', [OnlineOrderController::class, 'printInvoice'])->middleware('permission:online_orders.print')->name('online-orders.print');

        Route::get('/settings', [SettingsController::class, 'edit'])->middleware('permission:settings.view')->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:settings.update')->name('settings.update');
        Route::put('/settings/kitchen-pin', [SettingsController::class, 'updateKitchenPin'])->middleware('permission:settings.update')->name('settings.kitchen-pin');
        Route::get('/activity-logs', ActivityLogController::class)->middleware('permission:activity_logs.view')->name('activity-logs.index');
        Route::get('/reports/{report}/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['excel', 'pdf'])->name('reports.export');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/accounts/cash-book', [AccountController::class, 'cashBook'])->middleware('permission:accounts.view')->name('accounts.cash-book');
        Route::get('/accounts/cash-book/export/{format}', [AccountController::class, 'exportCashBook'])->whereIn('format', ['excel', 'pdf'])->middleware('permission:accounts.view')->name('accounts.cash-book.export');
        Route::post('/accounts/bank-accounts', [AccountController::class, 'storeBankAccount'])->middleware('permission:accounts.bank_transfer.create')->name('accounts.bank-accounts.store');
        Route::post('/accounts/bank-transfers', [AccountController::class, 'storeTransfer'])->middleware('permission:accounts.bank_transfer.create')->name('accounts.bank-transfers.store');

        Route::prefix('manage/{module}')->name('backoffice.modules.')->group(function (): void {
            Route::get('/', [ResourceController::class, 'index'])->name('index');
            Route::get('/create', [ResourceController::class, 'create'])->name('create');
            Route::post('/', [ResourceController::class, 'store'])->name('store');
            Route::get('/{id}/print', [ResourceController::class, 'printSale'])->name('print');
            Route::get('/{id}', [ResourceController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ResourceController::class, 'update'])->name('update');
            Route::post('/{id}/toggle-active', [ResourceController::class, 'toggleActive'])->name('toggle-active');
            Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/payments', [ResourceController::class, 'payments'])->name('payments');
            Route::post('/{id}/payments', [ResourceController::class, 'storePayment'])->name('payments.store');
        });
    });
});
