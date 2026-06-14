@php
    $money = fn (float $amount): string => $amount === 0.0 ? '-' : number_format($amount, 2);
    $dateLabel = $from->isSameDay($to) ? $from->toDateString() : $from->toDateString().' to '.$to->toDateString();
    $rowDate = fn (string $date): string => \Carbon\Carbon::parse($date)->format('y-m-d');
    $exportQuery = request()->query();
@endphp

<x-layouts.app heading="Cash Book" title="Cash Book">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-end gap-3">
            <a class="btn-primary bg-emerald-600 hover:bg-emerald-700" href="{{ route('accounts.cash-book.export', ['format' => 'excel'] + $exportQuery) }}">
                <x-lucide name="file-spreadsheet" class="size-4" />
                <span>Excel</span>
            </a>
            <a class="btn-primary" href="{{ route('accounts.cash-book.export', ['format' => 'pdf'] + $exportQuery) }}">
                <x-lucide name="file-text" class="size-4" />
                <span>PDF</span>
            </a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm print:border-0 print:shadow-none">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-slate-950 pb-4">
                <div>
                    <h2 class="text-2xl font-black uppercase tracking-wide text-slate-950">{{ $businessName }}</h2>
                    <p class="mt-2 text-sm font-bold uppercase text-slate-800">Location : {{ $locationName }}</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-lg font-black uppercase underline decoration-2 underline-offset-4">Cashbook Summary Report</p>
                    <p class="mt-3 text-sm font-bold uppercase text-slate-800">Date : {{ $dateLabel }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-10">
                <section>
                    <h3 class="mb-4 text-base font-black text-red-700">{{ $cashBook['title'] }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-[980px] w-full border border-slate-950 text-sm">
                            <thead>
                                <tr class="bg-slate-100 text-left text-sm uppercase">
                                    <th class="border border-slate-950 px-3 py-2">Date</th>
                                    <th class="border border-slate-950 px-3 py-2">Num#</th>
                                    <th class="border border-slate-950 px-3 py-2">Payee / Split Account</th>
                                    <th class="border border-slate-950 px-3 py-2">Particulars</th>
                                    <th class="border border-slate-950 px-3 py-2 text-right">Debit</th>
                                    <th class="border border-slate-950 px-3 py-2 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $from->format('y-m-d') }}</td>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5">-</td>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5">-</td>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5 font-bold">BALANCE B/F</td>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right font-bold">{{ $money(max(0, $cashBook['opening'])) }}</td>
                                    <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right font-bold">{{ $money(abs(min(0, $cashBook['opening']))) }}</td>
                                </tr>
                                @forelse($cashBook['rows'] as $row)
                                    <tr>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $rowDate($row->date) }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 font-semibold">{{ $row->number }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $row->payee }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $row->particulars }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right">{{ $money((float) $row->debit) }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right">{{ $money((float) $row->credit) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="border-x border-dashed border-slate-500 px-3 py-8 text-center font-semibold text-slate-500">No cash transactions for this range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 ml-auto grid max-w-xl gap-2 text-sm font-black">
                        <div class="grid grid-cols-[1fr_10rem] gap-3">
                            <span class="text-right uppercase">Total Debits :</span>
                            <span class="text-right">{{ number_format($cashBook['total_debits'], 2) }}</span>
                        </div>
                        <div class="grid grid-cols-[1fr_10rem] gap-3">
                            <span class="text-right uppercase">Total Credits :</span>
                            <span class="border-b-2 border-slate-950 text-right">{{ number_format($cashBook['total_credits'], 2) }}</span>
                        </div>
                        <div class="grid grid-cols-[1fr_10rem] gap-3">
                            <span class="text-right">{{ $cashBook['title'] }} - BALANCE C/F :</span>
                            <span class="border-b-4 border-double border-slate-950 text-right text-blue-700">{{ number_format($cashBook['closing'], 2) }}</span>
                        </div>
                    </div>
                </section>

                @foreach($bankBooks as $book)
                    <section>
                        <h3 class="mb-4 text-base font-black text-red-700">{{ $book['account']->name }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-[980px] w-full border border-slate-950 text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-left text-sm uppercase">
                                        <th class="border border-slate-950 px-3 py-2">Date</th>
                                        <th class="border border-slate-950 px-3 py-2">Num#</th>
                                        <th class="border border-slate-950 px-3 py-2">Payee / Split Account</th>
                                        <th class="border border-slate-950 px-3 py-2">Particulars</th>
                                        <th class="border border-slate-950 px-3 py-2 text-right">Debit</th>
                                        <th class="border border-slate-950 px-3 py-2 text-right">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $from->format('y-m-d') }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">-</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5">-</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 font-bold">BALANCE B/F</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right font-bold">{{ $money(max(0, $book['opening'])) }}</td>
                                        <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right font-bold">{{ $money(abs(min(0, $book['opening']))) }}</td>
                                    </tr>
                                    @forelse($book['rows'] as $row)
                                        <tr>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $rowDate($row->date) }}</td>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5 font-semibold">{{ $row->number }}</td>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $row->payee }}</td>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5">{{ $row->particulars }}</td>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right">{{ $money((float) $row->debit) }}</td>
                                            <td class="border-x border-dashed border-slate-500 px-3 py-1.5 text-right">{{ $money((float) $row->credit) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="border-x border-dashed border-slate-500 px-3 py-8 text-center font-semibold text-slate-500">No bank transactions for this range.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 ml-auto grid max-w-xl gap-2 text-sm font-black">
                            <div class="grid grid-cols-[1fr_10rem] gap-3">
                                <span class="text-right uppercase">Total Debits :</span>
                                <span class="text-right">{{ number_format($book['total_debits'], 2) }}</span>
                            </div>
                            <div class="grid grid-cols-[1fr_10rem] gap-3">
                                <span class="text-right uppercase">Total Credits :</span>
                                <span class="border-b-2 border-slate-950 text-right">{{ number_format($book['total_credits'], 2) }}</span>
                            </div>
                            <div class="grid grid-cols-[1fr_10rem] gap-3">
                                <span class="text-right">{{ $book['account']->name }} - BALANCE C/F :</span>
                                <span class="border-b-4 border-double border-slate-950 text-right text-blue-700">{{ number_format($book['closing'], 2) }}</span>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
