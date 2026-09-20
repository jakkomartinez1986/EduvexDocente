<?php

it('probe: literal sqlite_master for attendances', function (): void {
    $pdo = DB::connection()->getPdo();
    $rows = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='attendances' AND name LIKE 'attendances_%'")->fetchAll(PDO::FETCH_ASSOC);
    fwrite(STDOUT, "\n===LITERAL-ATTENDANCES-INDEXES===\n");
    foreach ($rows as $r) {
        fwrite(STDOUT, $r['name'].' |> '.$r['sql']."\n");
    }
    $this->assertTrue(true);
});
