<?php

use Illuminate\Support\Facades\DB;

it('dumps the literal sqlite_master.sql for the two attendance unique indexes', function (): void {
    $pdo = DB::connection()->getPdo();

    $stmt = $pdo->query(
        "SELECT name, type, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'attendances' AND name IN ('attendances_schedule_student_date_unique', 'attendances_client_uuid_unique') ORDER BY name",
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    fwrite(STDOUT, "\n===LITERAL-SQLITE_MASTER-SQL===\n");
    foreach ($rows as $r) {
        $sql = trim((string) $r['sql']);          // el SQL EXACTO que SQLite guarda (preserva WHERE)
        fwrite(STDOUT, $r['name'].' ==> '.$sql."\n");

        fwrite(STDOUT, '  partial? '.(str_contains($sql, 'deleted_at IS NULL') ? 'YES' : 'NO')."\n");
    }
    fwrite(STDOUT, "===END===\n");

    $this->assertTrue(true);
});
