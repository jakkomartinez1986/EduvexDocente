<?php

use App\Models\Identity\Users\Users;
use Illuminate\Support\Facades\DB;

it('profiles libro de incidencias page load', function () {
    DB::enableQueryLog();
    $user = DB::table('users')->where('id', 27)->first();

    $response = $this->actingAs(\App\Models\User::find(27))
        ->get('/system/teacher/incidents');

    $queries = count(DB::getQueryLog());
    dump('status: ' . $response->getStatusCode());
    dump('queries: ' . $queries);
    $response->assertStatus(200);
});

it('tests asistencia category tab', function () {
    $user = \App\Models\User::find(27);

    $component = new class extends \Livewire\Component {};
    $response = \Livewire\Livewire::test('pages::system.teachers-management.teachers.incidents.index')
        ->call('setCategory', 'asistencia');

    dump('asistencia tab status ok');
    $response->assertSuccessful();
});
