<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function cashBook(Request $request): View
    {
        $this->authorize('accounts.view');

        return view('accounts.cash-book', $this->cashBookPayload($request));
    }

    public function exportCashBook(Request $request, string $format): mixed
    {
        $this->authorize('accounts.view');
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $payload = $this->cashBookPayload($request);
        $filename = 'cash-book-'.now()->format('Ymd-His');

        if ($format === 'excel') {
            return response()->streamDownload(function () use ($payload): void {
                echo view('accounts.exports.cash-book-excel', $payload)->render();
            }, "{$filename}.xls", [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            ]);
        }

        return response($this->cashBookPdf($payload), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    public function storeBankAccount(Request $request): JsonResponse
    {
        $this->authorize('accounts.bank_transfer.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_no' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $accountId = DB::transaction(function () use ($request, $validated): int {
            if ($request->boolean('is_default')) {
                DB::table('bank_accounts')->update(['is_default' => false, 'updated_at' => now()]);
            }

            $accountId = DB::table('bank_accounts')->insertGetId([
                'name' => $validated['name'],
                'account_no' => $validated['account_no'] ?? null,
                'opening_balance' => $validated['opening_balance'] ?? 0,
                'is_default' => $request->boolean('is_default'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ActivityLog::record('create', 'bank_account', 'Bank account created.', ['id' => $accountId]);

            return $accountId;
        });

        return response()->json([
            'message' => 'Bank account saved.',
            'account' => $this->bankAccountWithBalance($accountId),
        ], 201);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $this->authorize('accounts.bank_transfer.create');

        $validated = $request->validate([
            'transfer_type' => ['required', 'in:cash_to_bank,bank_to_bank,bank_to_cash'],
            'to_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        $defaultAccount = DB::table('bank_accounts')->where('is_default', true)->where('is_active', true)->first();
        abort_unless($defaultAccount, 422, 'Default bank account is missing.');

        $amount = (float) $validated['amount'];
        $note = $validated['note'] ?? null;

        if ($validated['transfer_type'] === 'cash_to_bank') {
            $targetAccount = DB::table('bank_accounts')->where('id', $validated['to_bank_account_id'])->where('is_active', true)->first();
            abort_unless($targetAccount, 422, 'Selected bank account is not active.');

            if ($amount > $this->cashBalance()) {
                return back()->withErrors(['amount' => 'Transfer amount is higher than available cash in hand.'])->withInput();
            }

            DB::transaction(function () use ($amount, $note, $targetAccount): void {
                $bankTransactionId = DB::table('bank_transactions')->insertGetId([
                    'bank_account_id' => $targetAccount->id,
                    'type' => 'cash_to_bank',
                    'amount' => $amount,
                    'transaction_date' => now(),
                    'source_type' => 'cash_transfer',
                    'source_id' => auth()->id(),
                    'note' => $note ?: 'Cash to bank transfer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($targetAccount->is_default) {
                    DB::table('cash_outs')->insert([
                        'register_id' => $this->currentRegister()?->id,
                        'user_id' => auth()->id(),
                        'amount' => $amount,
                        'movement_date' => now(),
                        'note' => $note ?: 'Cash to bank transfer',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('expenses')->insert([
                        'expense_category_id' => $this->expenseCategoryId('Bank Transfer'),
                        'user_id' => auth()->id(),
                        'amount' => $amount,
                        'payment_method' => 'cash',
                        'bank_account_id' => null,
                        'expense_date' => now(),
                        'note' => $note ?: 'Cash transferred to '.$targetAccount->name,
                        'source_type' => 'cash_to_bank_transfer',
                        'source_id' => $bankTransactionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                ActivityLog::record('create', 'bank_transfer', 'Cash transferred to bank.', [
                    'bank_account_id' => $targetAccount->id,
                    'amount' => $amount,
                ]);
            });

            return back()->with('status', 'Cash transferred to bank.');
        }

        if ($validated['transfer_type'] === 'bank_to_cash') {
            if ($amount > $this->bankBalance((int) $defaultAccount->id)) {
                return back()->withErrors(['amount' => 'Transfer amount is higher than the default bank balance.'])->withInput();
            }

            DB::transaction(function () use ($amount, $note, $defaultAccount): void {
                $bankTransactionId = DB::table('bank_transactions')->insertGetId([
                    'bank_account_id' => $defaultAccount->id,
                    'type' => 'bank_to_cash',
                    'amount' => $amount,
                    'transaction_date' => now(),
                    'source_type' => 'bank_to_cash_transfer',
                    'source_id' => auth()->id(),
                    'note' => $note ?: 'Bank to cash transfer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('cash_ins')->insert([
                    'register_id' => $this->currentRegister()?->id,
                    'user_id' => auth()->id(),
                    'amount' => $amount,
                    'movement_date' => now(),
                    'note' => $note ?: 'Bank to cash transfer from '.$defaultAccount->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                ActivityLog::record('create', 'bank_transfer', 'Bank transferred to cash.', [
                    'bank_account_id' => $defaultAccount->id,
                    'bank_transaction_id' => $bankTransactionId,
                    'amount' => $amount,
                ]);
            });

            return back()->with('status', 'Bank transferred to cash.');
        }

        $targetAccount = DB::table('bank_accounts')->where('id', $validated['to_bank_account_id'])->where('is_active', true)->first();
        abort_unless($targetAccount, 422, 'Selected bank account is not active.');

        if ((int) $targetAccount->id === (int) $defaultAccount->id) {
            return back()->withErrors(['to_bank_account_id' => 'Choose a non-default account for bank to bank transfer.'])->withInput();
        }

        if ($amount > $this->bankBalance((int) $defaultAccount->id)) {
            return back()->withErrors(['amount' => 'Transfer amount is higher than the default bank balance.'])->withInput();
        }

        DB::transaction(function () use ($amount, $note, $defaultAccount, $targetAccount): void {
            $transferOutId = DB::table('bank_transactions')->insertGetId([
                'bank_account_id' => $defaultAccount->id,
                'type' => 'bank_transfer_out',
                'amount' => $amount,
                'transaction_date' => now(),
                'source_type' => 'bank_transfer',
                'source_id' => $targetAccount->id,
                'note' => $note ?: 'Bank transfer to '.$targetAccount->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bank_transactions')->insert([
                'bank_account_id' => $targetAccount->id,
                'type' => 'bank_transfer_in',
                'amount' => $amount,
                'transaction_date' => now(),
                'source_type' => 'bank_transfer',
                'source_id' => $transferOutId,
                'note' => $note ?: 'Bank transfer from '.$defaultAccount->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('expenses')->insert([
                'expense_category_id' => $this->expenseCategoryId('Bank Transfer'),
                'user_id' => auth()->id(),
                'amount' => $amount,
                'payment_method' => 'bank',
                'bank_account_id' => $defaultAccount->id,
                'expense_date' => now(),
                'note' => $note ?: 'Bank transfer to '.$targetAccount->name,
                'source_type' => 'bank_to_bank_transfer',
                'source_id' => $transferOutId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ActivityLog::record('create', 'bank_transfer', 'Bank transferred to bank.', [
                'from_bank_account_id' => $defaultAccount->id,
                'to_bank_account_id' => $targetAccount->id,
                'amount' => $amount,
            ]);
        });

        return back()->with('status', 'Bank transfer saved.');
    }

    private function bankAccountWithBalance(int $accountId): object
    {
        $account = DB::table('bank_accounts')->where('id', $accountId)->first();
        $account->balance = $this->bankBalance($accountId);

        return $account;
    }

    /**
     * @return array{from: Carbon, to: Carbon, cashBook: array<string, mixed>, bankBooks: Collection<int, array<string, mixed>>, businessName: mixed, locationName: mixed}
     */
    private function cashBookPayload(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);

        return [
            'from' => $from,
            'to' => $to,
            'cashBook' => $this->cashBookSection($from, $to),
            'bankBooks' => $this->bankBookSections($from, $to),
            'businessName' => $this->setting('business.name', 'Hotel POS'),
            'locationName' => $this->setting('business.location', 'Main'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        return match ($request->string('range', 'today')->toString()) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'all_time' => [Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay()],
            'custom' => [Carbon::parse($request->input('from', today()))->startOfDay(), Carbon::parse($request->input('to', today()))->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * @return array{title: string, opening: float, rows: Collection<int, object>, total_debits: float, total_credits: float, closing: float}
     */
    private function cashBookSection(Carbon $from, Carbon $to): array
    {
        $opening = $this->cashBookOpeningBalance($from);
        $rows = $this->cashBookRows($from, $to);
        $totalDebits = max(0.0, $opening) + (float) $rows->sum('debit');
        $totalCredits = abs(min(0.0, $opening)) + (float) $rows->sum('credit');

        return [
            'title' => 'Cash Drawer',
            'opening' => $opening,
            'rows' => $rows,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'closing' => $totalDebits - $totalCredits,
        ];
    }

    private function cashBookOpeningBalance(Carbon $from): float
    {
        $registerOpening = (float) DB::table('registers')->where('opened_at', '<', $from)->sum('opening_cash');
        $cashSales = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->where('sale_payments.paid_at', '<', $from)
            ->sum('sale_payments.amount');
        $cashIns = (float) DB::table('cash_ins')->whereNull('deleted_at')->where('movement_date', '<', $from)->sum('amount');
        $cashOuts = (float) DB::table('cash_outs')->whereNull('deleted_at')->where('movement_date', '<', $from)->sum('amount');
        $cashExpenses = (float) DB::table('expenses')
            ->whereNull('deleted_at')
            ->where('payment_method', 'cash')
            ->where('expense_date', '<', $from)
            ->sum('amount');

        return $registerOpening + $cashSales + $cashIns - $cashOuts - $cashExpenses;
    }

    /**
     * @return Collection<int, object>
     */
    private function cashBookRows(Carbon $from, Carbon $to): Collection
    {
        $registerOpenings = DB::table('registers')
            ->leftJoin('users', 'users.id', '=', 'registers.user_id')
            ->whereBetween('registers.opened_at', [$from, $to])
            ->get(['registers.id', 'registers.opened_at as date', 'registers.opening_cash as amount', 'registers.opening_note as note', 'users.name as user_name'])
            ->map(fn (object $row): object => $this->cashBookRow(
                date: $row->date,
                number: 'REG'.$row->id,
                payee: User::visibleName($row->user_name, 'Cashier'),
                particulars: $row->note ?: 'Register opening',
                debit: (float) $row->amount
            ));

        $cashSales = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->whereBetween('sale_payments.paid_at', [$from, $to])
            ->get(['sales.invoice_no', 'sale_payments.paid_at as date', 'sale_payments.amount', 'customers.name as customer_name'])
            ->map(fn (object $row): object => $this->cashBookRow(
                date: $row->date,
                number: $row->invoice_no ?? '-',
                payee: $row->customer_name ?? 'Cash',
                particulars: trim(($row->invoice_no ?? 'Sale').' CASH SALE'),
                debit: (float) $row->amount
            ));

        $cashIns = DB::table('cash_ins')
            ->leftJoin('users', 'users.id', '=', 'cash_ins.user_id')
            ->whereNull('cash_ins.deleted_at')
            ->whereBetween('cash_ins.movement_date', [$from, $to])
            ->get(['cash_ins.id', 'cash_ins.movement_date as date', 'cash_ins.amount', 'cash_ins.note', 'users.name as user_name'])
            ->map(fn (object $row): object => $this->cashBookRow(
                date: $row->date,
                number: 'CI'.$row->id,
                payee: User::visibleName($row->user_name, 'Cash'),
                particulars: $row->note ?: 'Cash in',
                debit: (float) $row->amount
            ));

        $cashOuts = DB::table('cash_outs')
            ->leftJoin('users', 'users.id', '=', 'cash_outs.user_id')
            ->whereNull('cash_outs.deleted_at')
            ->whereBetween('cash_outs.movement_date', [$from, $to])
            ->get(['cash_outs.id', 'cash_outs.movement_date as date', 'cash_outs.amount', 'cash_outs.note', 'users.name as user_name'])
            ->map(fn (object $row): object => $this->cashBookRow(
                date: $row->date,
                number: 'CO'.$row->id,
                payee: User::visibleName($row->user_name, 'Cash'),
                particulars: $row->note ?: 'Cash out',
                credit: (float) $row->amount
            ));

        $cashExpenses = DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.payment_method', 'cash')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->get(['expenses.id', 'expenses.expense_date as date', 'expenses.amount', 'expenses.note', 'expense_categories.name as category'])
            ->map(fn (object $row): object => $this->cashBookRow(
                date: $row->date,
                number: 'EX'.$row->id,
                payee: $row->category ?? 'Expense',
                particulars: $row->note ?: 'Cash expense',
                credit: (float) $row->amount
            ));

        return $registerOpenings
            ->merge($cashSales)
            ->merge($cashIns)
            ->merge($cashOuts)
            ->merge($cashExpenses)
            ->sortBy([['date', 'asc'], ['number', 'asc']])
            ->values();
    }

    /**
     * @return Collection<int, array{account: object, opening: float, rows: Collection<int, object>, total_debits: float, total_credits: float, closing: float}>
     */
    private function bankBookSections(Carbon $from, Carbon $to): Collection
    {
        return DB::table('bank_accounts')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (object $account) use ($from, $to): array {
                $opening = $this->bankBookOpeningBalance((int) $account->id, $from);
                $rows = $this->bankBookRows((int) $account->id, $from, $to);
                $totalDebits = max(0.0, $opening) + (float) $rows->sum('debit');
                $totalCredits = abs(min(0.0, $opening)) + (float) $rows->sum('credit');

                return [
                    'account' => $account,
                    'opening' => $opening,
                    'rows' => $rows,
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'closing' => $totalDebits - $totalCredits,
                ];
            });
    }

    private function bankBookOpeningBalance(int $accountId, Carbon $from): float
    {
        $openingBalance = (float) DB::table('bank_accounts')->where('id', $accountId)->value('opening_balance');
        $movement = (float) DB::table('bank_transactions')
            ->where('bank_account_id', $accountId)
            ->where('transaction_date', '<', $from)
            ->sum(DB::raw("case when type in ('deposit','qr_payment','card_payment','cash_to_bank','bank_transfer_in') then amount else -amount end"));

        return $openingBalance + $movement;
    }

    /**
     * @return Collection<int, object>
     */
    private function bankBookRows(int $accountId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('bank_transactions')
            ->leftJoin('sales', function ($join): void {
                $join->on('sales.id', '=', 'bank_transactions.source_id')
                    ->where('bank_transactions.source_type', 'sale');
            })
            ->leftJoin('expenses', function ($join): void {
                $join->on('expenses.id', '=', 'bank_transactions.source_id')
                    ->where('bank_transactions.source_type', 'expense');
            })
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('bank_transactions.bank_account_id', $accountId)
            ->whereBetween('bank_transactions.transaction_date', [$from, $to])
            ->orderBy('bank_transactions.transaction_date')
            ->orderBy('bank_transactions.id')
            ->get([
                'bank_transactions.id',
                'bank_transactions.type',
                'bank_transactions.amount',
                'bank_transactions.transaction_date as date',
                'bank_transactions.note',
                'bank_transactions.source_type',
                'sales.invoice_no',
                'expense_categories.name as expense_category',
            ])
            ->map(function (object $row): object {
                $isDebit = in_array($row->type, ['deposit', 'qr_payment', 'card_payment', 'cash_to_bank', 'bank_transfer_in'], true);

                return $this->cashBookRow(
                    date: $row->date,
                    number: 'BT'.$row->id,
                    payee: $this->bankTransactionPayee($row),
                    particulars: $row->note ?: $this->bankTransactionLabel($row->type),
                    debit: $isDebit ? (float) $row->amount : 0.0,
                    credit: $isDebit ? 0.0 : (float) $row->amount
                );
            });
    }

    private function cashBookRow(string $date, string $number, string $payee, string $particulars, float $debit = 0.0, float $credit = 0.0): object
    {
        return (object) [
            'date' => $date,
            'number' => $number,
            'payee' => $payee,
            'particulars' => $particulars,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    private function bankTransactionPayee(object $transaction): string
    {
        return match ($transaction->type) {
            'card_payment' => 'Card Sale',
            'qr_payment' => 'QR Payment',
            'cash_to_bank', 'bank_to_cash' => 'Cash Drawer',
            'bank_transfer_in' => 'Bank Transfer In',
            'bank_transfer_out' => 'Bank Transfer Out',
            'bank_expense' => $transaction->expense_category ?? 'Bank Expense',
            default => str((string) $transaction->source_type)->replace('_', ' ')->headline()->toString() ?: 'Bank',
        };
    }

    private function bankTransactionLabel(string $type): string
    {
        return str($type)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @param  array{from: Carbon, to: Carbon, cashBook: array<string, mixed>, bankBooks: Collection<int, array<string, mixed>>, businessName: mixed, locationName: mixed}  $payload
     */
    private function cashBookPdf(array $payload): string
    {
        $sections = collect([$payload['cashBook']])
            ->merge($payload['bankBooks']->map(fn (array $book): array => [
                'title' => $book['account']->name,
                'opening' => $book['opening'],
                'rows' => $book['rows'],
                'total_debits' => $book['total_debits'],
                'total_credits' => $book['total_credits'],
                'closing' => $book['closing'],
            ]));

        $pages = [];
        foreach ($sections as $section) {
            $rows = collect([
                (object) [
                    'date' => $payload['from']->toDateString(),
                    'number' => '-',
                    'payee' => '-',
                    'particulars' => 'BALANCE B/F',
                    'debit' => max(0.0, (float) $section['opening']),
                    'credit' => abs(min(0.0, (float) $section['opening'])),
                ],
            ])
                ->merge($section['rows'])
                ->values();

            $chunks = $rows->chunk(24);

            foreach ($chunks as $index => $chunk) {
                $pages[] = [
                    'section' => $section,
                    'rows' => $chunk->values(),
                    'show_totals' => $index === $chunks->count() - 1,
                ];
            }
        }

        if ($pages === []) {
            $pages[] = [
                'section' => $payload['cashBook'],
                'rows' => collect(),
                'show_totals' => true,
            ];
        }

        $pageObjectIds = [];
        $contentObjectIds = [];
        foreach ($pages as $index => $_page) {
            $pageObjectIds[] = 3 + ($index * 2);
            $contentObjectIds[] = 4 + ($index * 2);
        }

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids ['.collect($pageObjectIds)->map(fn (int $id): string => "{$id} 0 R")->implode(' ').'] /Count '.count($pageObjectIds).' >> endobj',
        ];
        $fontObjectId = 3 + (count($pages) * 2);

        foreach ($pages as $index => $page) {
            $pageId = $pageObjectIds[$index];
            $contentId = $contentObjectIds[$index];
            $content = $this->cashBookPdfPageContent($payload, $page, $index + 1, count($pages));

            $objects[] = "{$pageId} 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentId} 0 R >> endobj";
            $objects[] = "{$contentId} 0 obj << /Length ".strlen($content)." >> stream\n{$content}\nendstream endobj";
        }

        $objects[] = "{$fontObjectId} 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier >> endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{section: array<string, mixed>, rows: Collection<int, object>, show_totals: bool}  $page
     */
    private function cashBookPdfPageContent(array $payload, array $page, int $pageNumber, int $pageCount): string
    {
        $dateLabel = $payload['from']->isSameDay($payload['to'])
            ? $payload['from']->toDateString()
            : $payload['from']->toDateString().' to '.$payload['to']->toDateString();

        $content = $this->pdfTextAt(32, 558, mb_strimwidth((string) $payload['businessName'], 0, 38), 17);
        $content .= $this->pdfTextAt(32, 538, 'LOCATION : '.mb_strimwidth((string) $payload['locationName'], 0, 28), 10);
        $content .= $this->pdfTextAt(612, 558, 'CASHBOOK SUMMARY REPORT', 14);
        $content .= $this->pdfTextAt(612, 538, 'DATE : '.$dateLabel, 10);
        $content .= "30 526 m 812 526 l S\n";
        $content .= $this->pdfTextAt(32, 508, mb_strimwidth((string) $page['section']['title'], 0, 60), 12);
        $content .= $this->pdfTextAt(32, 486, 'DATE', 9);
        $content .= $this->pdfTextAt(96, 486, 'NUM#', 9);
        $content .= $this->pdfTextAt(184, 486, 'PAYEE / SPLIT ACCOUNT', 9);
        $content .= $this->pdfTextAt(380, 486, 'PARTICULARS', 9);
        $content .= $this->pdfTextAt(670, 486, 'DEBIT', 9);
        $content .= $this->pdfTextAt(750, 486, 'CREDIT', 9);
        $content .= "30 480 m 812 480 l S\n";

        $y = 464;
        foreach ($page['rows'] as $row) {
            $content .= $this->pdfTextAt(32, $y, Carbon::parse($row->date)->format('y-m-d'), 8);
            $content .= $this->pdfTextAt(96, $y, mb_strimwidth((string) $row->number, 0, 18), 8);
            $content .= $this->pdfTextAt(184, $y, mb_strimwidth((string) $row->payee, 0, 28), 8);
            $content .= $this->pdfTextAt(380, $y, mb_strimwidth((string) $row->particulars, 0, 42), 8);
            $content .= $this->pdfTextAt(650, $y, $this->pdfMoney((float) $row->debit), 8);
            $content .= $this->pdfTextAt(735, $y, $this->pdfMoney((float) $row->credit), 8);
            $y -= 15;
        }

        if ($page['show_totals']) {
            $y -= 8;
            $content .= $this->pdfTextAt(526, $y, 'TOTAL DEBITS :', 9);
            $content .= $this->pdfTextAt(675, $y, number_format((float) $page['section']['total_debits'], 2), 9);
            $y -= 17;
            $content .= $this->pdfTextAt(526, $y, 'TOTAL CREDITS :', 9);
            $content .= $this->pdfTextAt(675, $y, number_format((float) $page['section']['total_credits'], 2), 9);
            $y -= 17;
            $content .= $this->pdfTextAt(430, $y, mb_strimwidth((string) $page['section']['title'], 0, 30).' - BALANCE C/F :', 9);
            $content .= $this->pdfTextAt(675, $y, number_format((float) $page['section']['closing'], 2), 9);
        }

        $content .= $this->pdfTextAt(32, 28, now()->format('l, d F, Y'), 8);
        $content .= $this->pdfTextAt(732, 28, "Page {$pageNumber} of {$pageCount}", 8);

        return $content;
    }

    private function pdfMoney(float $amount): string
    {
        return $amount === 0.0 ? '-' : number_format($amount, 2);
    }

    private function pdfTextAt(float $x, float $y, string $text, int $size = 9): string
    {
        return "BT /F1 {$size} Tf {$x} {$y} Td ({$this->pdfText($text)}) Tj ET\n";
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'));
    }

    private function bankBalance(int $accountId): float
    {
        $openingBalance = (float) DB::table('bank_accounts')->where('id', $accountId)->value('opening_balance');
        $movement = (float) DB::table('bank_transactions')
            ->where('bank_account_id', $accountId)
            ->sum(DB::raw("case when type in ('deposit','qr_payment','card_payment','cash_to_bank','bank_transfer_in') then amount else -amount end"));

        return $openingBalance + $movement;
    }

    private function cashBalance(): float
    {
        $opening = DB::table('registers')->sum('opening_cash');
        $cashSales = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->sum('sale_payments.amount');
        $cashIns = DB::table('cash_ins')->whereNull('deleted_at')->sum('amount');
        $cashOuts = DB::table('cash_outs')->whereNull('deleted_at')->sum('amount');
        $expenses = DB::table('expenses')->where('payment_method', 'cash')->whereNull('deleted_at')->sum('amount');

        return (float) ($opening + $cashSales + $cashIns - $cashOuts - $expenses);
    }

    private function currentRegister(): ?object
    {
        return DB::table('registers')->where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }

    private function expenseCategoryId(string $name): int
    {
        $category = DB::table('expense_categories')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first();

        if ($category) {
            return (int) $category->id;
        }

        return DB::table('expense_categories')->insertGetId([
            'name' => $name,
            'description' => $name.' expenses',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = explode('.', $key, 2);

        return DB::table('settings')->where('group', $group)->where('key', $settingKey)->value('value') ?? $default;
    }
}
