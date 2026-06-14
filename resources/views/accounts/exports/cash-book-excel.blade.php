<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #e5e7eb; font-weight: 700; }
        .section { color: #a00000; font-weight: 700; }
        .right { text-align: right; }
        .total { font-weight: 700; }
        .balance { color: #0047ff; font-weight: 700; }
    </style>
</head>
<body>
@php
    $money = fn (float $amount): string => $amount === 0.0 ? '-' : number_format($amount, 2);
    $rowDate = fn (string $date): string => \Carbon\Carbon::parse($date)->format('y-m-d');
    $dateLabel = $from->isSameDay($to) ? $from->toDateString() : $from->toDateString().' to '.$to->toDateString();
@endphp

<h2>{{ $businessName }}</h2>
<p>Location : {{ $locationName }}</p>
<h3>Cashbook Summary Report</h3>
<p>Date : {{ $dateLabel }}</p>

<h3 class="section">{{ $cashBook['title'] }}</h3>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Num#</th>
            <th>Payee / Split Account</th>
            <th>Particulars</th>
            <th>Debit</th>
            <th>Credit</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $from->format('y-m-d') }}</td>
            <td>-</td>
            <td>-</td>
            <td>BALANCE B/F</td>
            <td class="right">{{ $money(max(0, $cashBook['opening'])) }}</td>
            <td class="right">{{ $money(abs(min(0, $cashBook['opening']))) }}</td>
        </tr>
        @foreach($cashBook['rows'] as $row)
            <tr>
                <td>{{ $rowDate($row->date) }}</td>
                <td>{{ $row->number }}</td>
                <td>{{ $row->payee }}</td>
                <td>{{ $row->particulars }}</td>
                <td class="right">{{ $money((float) $row->debit) }}</td>
                <td class="right">{{ $money((float) $row->credit) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="4" class="right">Total Debits :</td>
            <td class="right">{{ number_format($cashBook['total_debits'], 2) }}</td>
            <td></td>
        </tr>
        <tr class="total">
            <td colspan="4" class="right">Total Credits :</td>
            <td></td>
            <td class="right">{{ number_format($cashBook['total_credits'], 2) }}</td>
        </tr>
        <tr class="balance">
            <td colspan="4" class="right">{{ $cashBook['title'] }} - BALANCE C/F :</td>
            <td colspan="2" class="right">{{ number_format($cashBook['closing'], 2) }}</td>
        </tr>
    </tbody>
</table>

@foreach($bankBooks as $book)
    <br>
    <h3 class="section">{{ $book['account']->name }}</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Num#</th>
                <th>Payee / Split Account</th>
                <th>Particulars</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $from->format('y-m-d') }}</td>
                <td>-</td>
                <td>-</td>
                <td>BALANCE B/F</td>
                <td class="right">{{ $money(max(0, $book['opening'])) }}</td>
                <td class="right">{{ $money(abs(min(0, $book['opening']))) }}</td>
            </tr>
            @foreach($book['rows'] as $row)
                <tr>
                    <td>{{ $rowDate($row->date) }}</td>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->payee }}</td>
                    <td>{{ $row->particulars }}</td>
                    <td class="right">{{ $money((float) $row->debit) }}</td>
                    <td class="right">{{ $money((float) $row->credit) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4" class="right">Total Debits :</td>
                <td class="right">{{ number_format($book['total_debits'], 2) }}</td>
                <td></td>
            </tr>
            <tr class="total">
                <td colspan="4" class="right">Total Credits :</td>
                <td></td>
                <td class="right">{{ number_format($book['total_credits'], 2) }}</td>
            </tr>
            <tr class="balance">
                <td colspan="4" class="right">{{ $book['account']->name }} - BALANCE C/F :</td>
                <td colspan="2" class="right">{{ number_format($book['closing'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endforeach
</body>
</html>
