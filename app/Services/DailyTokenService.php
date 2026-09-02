<?php

namespace App\Services;

use App\Models\DailyTokenCounter;
use App\Models\OrderToken;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class DailyTokenService
{
    /**
     * Allocate the next token for the configured application-local date.
     */
    public function issue(): OrderToken
    {
        return DB::transaction(function (): OrderToken {
            $tokenDate = $this->currentTokenDate();
            $now = now();
            $nextNumber = DB::getDriverName() === 'mysql'
                ? $this->incrementMySqlCounter($tokenDate, $now)
                : $this->incrementCounterWithLock($tokenDate, $now);

            $orderTokenId = DB::table('order_tokens')->insertGetId([
                'token_date' => $tokenDate,
                'token_number' => $nextNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return OrderToken::query()->findOrFail($orderTokenId);
        }, 5);
    }

    private function incrementMySqlCounter(string $tokenDate, DateTimeInterface $now): int
    {
        $connection = DB::connection();
        $counterTable = $connection->getQueryGrammar()->wrapTable(
            (new DailyTokenCounter)->getTable()
        );

        $connection->statement(
            "INSERT INTO {$counterTable} (`token_date`, `last_number`, `created_at`, `updated_at`)
             VALUES (?, LAST_INSERT_ID(1), ?, ?)
             ON DUPLICATE KEY UPDATE
                `last_number` = LAST_INSERT_ID(`last_number` + 1),
                `updated_at` = ?",
            [$tokenDate, $now, $now, $now],
        );

        return (int) $connection->getPdo()->lastInsertId();
    }

    private function incrementCounterWithLock(string $tokenDate, DateTimeInterface $now): int
    {
        DailyTokenCounter::query()->insertOrIgnore([
            'token_date' => $tokenDate,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counter = DailyTokenCounter::query()
            ->where('token_date', $tokenDate)
            ->lockForUpdate()
            ->firstOrFail();

        $nextNumber = $counter->last_number + 1;
        $counter->update(['last_number' => $nextNumber]);

        return $nextNumber;
    }

    /**
     * @return array{date: string, number: int, display: string}
     */
    public function nextAvailable(): array
    {
        $tokenDate = $this->currentTokenDate();
        $lastNumber = (int) (DailyTokenCounter::query()
            ->where('token_date', $tokenDate)
            ->value('last_number') ?? 0);

        $nextNumber = $lastNumber + 1;

        return [
            'date' => $tokenDate,
            'number' => $nextNumber,
            'display' => $this->format($nextNumber),
        ];
    }

    public function format(int $tokenNumber): string
    {
        return str_pad((string) $tokenNumber, 2, '0', STR_PAD_LEFT);
    }

    public function currentTokenDate(): string
    {
        return now(config('app.timezone'))->toDateString();
    }
}
