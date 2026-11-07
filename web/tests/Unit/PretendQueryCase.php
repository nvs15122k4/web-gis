<?php

namespace Tests\Unit;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/** Nền cho test cần xem SQL sinh ra nhưng không chạm CSDL thật: dùng pretend() + listen(). */
abstract class PretendQueryCase extends BaseUnitTest
{
    /**
     * Chạy $fn ở chế độ pretend trên kết nối $conn, trả về danh sách [sql, bindings] đã "thực thi".
     * Exception trong $fn được bắt lại và trả về ở khoá 'error' để vẫn xem được các truy vấn đã chạy.
     *
     * @return array{queries: array<int, array{sql: string, bindings: array}>, result: mixed, error: ?\Throwable}
     */
    protected function capture(callable $fn, string $conn = 'pgsql'): array
    {
        $queries = [];
        $c = DB::connection($conn);
        $c->listen(function (QueryExecuted $e) use (&$queries) {
            $queries[] = ['sql' => $e->sql, 'bindings' => $e->bindings];
        });
        $result = null;
        $error = null;
        $c->pretend(function () use ($fn, &$result, &$error) {
            try {
                $result = $fn();
            } catch (\Throwable $t) {
                $error = $t;
            }
        });

        return ['queries' => $queries, 'result' => $result, 'error' => $error];
    }
}
