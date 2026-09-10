<?php

use App\Jobs\CleanupExpiredTokens;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

it('elimina los tokens de Sanctum ya expirados y conserva los vigentes', function (): void {
    $user = User::factory()->create();

    $user->createToken('vigente');
    $user->createToken('expirado');

    DB::table('personal_access_tokens')
        ->where('name', 'expirado')
        ->update(['expires_at' => now()->subDay()]);

    CleanupExpiredTokens::dispatchSync();

    expect(PersonalAccessToken::query()->pluck('name'))->toContain('vigente')
        ->not->toContain('expirado');
});
