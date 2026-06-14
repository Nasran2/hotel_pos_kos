<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; }
        th { background: #e2e8f0; font-weight: 700; }
        .debit { color: #047857; }
        .credit { color: #dc2626; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p>{{ $from->toDateString() }} to {{ $to->toDateString() }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Debit Account</th>
                <th>Credit Account</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ optional(\Carbon\Carbon::parse($row['date']))->toDateString() }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="debit">{{ $row['debit_account'] }}</td>
                    <td class="credit">{{ $row['credit_account'] }}</td>
                    <td>{{ number_format((float) $row['debit_amount'], 2) }}</td>
                    <td>{{ number_format((float) $row['credit_amount'], 2) }}</td>
                    <td>{{ $row['source'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
