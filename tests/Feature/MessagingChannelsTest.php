<?php

declare(strict_types=1);

use App\Jobs\SendChannelMessageJob;
use App\Mail\GenericChannelMail;
use App\Models\Incidents\NotificationChannel;
use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Models\User;
use App\Services\Messaging\MessagingManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

function messagingAdminUser(bool $super = true): User
{
    $role = Role::firstOrCreate(
        ['name' => $super ? 'SUPER-ADMIN' : 'DOCENTE', 'guard_name' => 'web'],
        ['description' => $super ? 'Super Administrador' : 'Docente'],
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function messagingSeedNotification(): int
{
    $now = now();

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '2025-2026',
        'start_date' => $now->copy()->subDays(60)->toDateString(),
        'end_date' => $now->copy()->addDays(120)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $periodId = DB::table('academic_periods')->insertGetId([
        'year_id' => $yearId,
        'trimester_name' => 'Trimestre I',
        'start_date' => $now->toDateString(),
        'end_date' => $now->copy()->addDays(60)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeId = DB::table('grades')->insertGetId([
        'nivel_id' => DB::table('nivels')->insertGetId([
            'shift_id' => DB::table('shifts')->insertGetId([
                'shift_name' => 'Matutina',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'nivel_name' => 'Bachillerato',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]),
        'grade_name' => '1° BT',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $studentId = DB::table('students')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'student_code' => 'MSG-JOB-1',
        'enrollment_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $teacherId = DB::table('teachers')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'teacher_code' => 'DOC-MSG-1',
        'hire_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return DB::table('academic_notifications')->insertGetId([
        'code' => 'NOT-JOB-1',
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'student_id' => $studentId,
        'grade_id' => $gradeId,
        'teacher_id' => $teacherId,
        'year_id' => $yearId,
        'trimester_id' => $periodId,
        'message' => 'Prueba de job',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

test('la pagina de canales carga para admin y muestra los tres canales', function () {
    $this->actingAs(messagingAdminUser());

    $response = $this->get('/system/settings/messaging-channels');
    $response
        ->assertOk()
        ->assertSee('Canales de Mensajería')
        ->assertSee('WhatsApp')
        ->assertSee('Telegram')
        ->assertSee('Email');

    expect(DB::table('channel_configurations')->count())->toBe(3);
});

test('docente sin rol admin ve aviso de acceso restringido', function () {
    $this->actingAs(messagingAdminUser(super: false));

    $this->get('/system/settings/messaging-channels')
        ->assertOk()
        ->assertSee(__('Acceso restringido'));
});

test('save del componente persiste credenciales cifradas y habilitado', function () {
    $admin = messagingAdminUser();
    $this->actingAs($admin);

    $this->get('/system/settings/messaging-channels')->assertOk();

    $compiled = collect(glob(storage_path('framework/views/livewire/classes/*.php')))
        ->filter(fn ($f) => str_contains((string) file_get_contents($f), 'Canales de Mensajería'))
        ->sortByDesc(fn ($f) => filemtime($f))
        ->first();

    expect($compiled)->not->toBeNull();

    $instance = require $compiled;
    $instance->mount();

    $instance->forms['whatsapp'] = [
        'enabled' => true,
        'credentials' => ['token' => 'mi-token-secreto', 'phone_number_id' => 'PNID9'],
        'sender_name' => 'Colegio',
        'test_destination' => '593991234567',
    ];

    try {
        $instance->save('whatsapp');
    } catch (Throwable) {
        logger('Flux toast fuera de contexto Livewire; persistencia ya aplicada');
    }

    $row = ChannelConfiguration::query()->where('channel', 'whatsapp')->first();

    expect($row->enabled)->toBeTrue()
        ->and($row->credentials['token'])->toBe('mi-token-secreto')
        ->and($row->credentials['phone_number_id'])->toBe('PNID9')
        ->and($row->test_destination)->toBe('593991234567');

    $raw = (string) DB::table('channel_configurations')->where('channel', 'whatsapp')->value('credentials');

    expect($raw)->not->toContain('mi-token-secreto');
});

test('messaging manager falla con mensaje claro si el canal no esta habilitado', function () {
    ChannelConfiguration::factory()->whatsapp()->create();

    $manager = app(MessagingManager::class);

    expect($manager->isEnabled('whatsapp'))->toBeFalse()
        ->and($manager->send('whatsapp', '593991234567', 'hola')->error)->toContain('no está habilitado');
});

test('verify de whatsapp cloud consulta graph api y marca ok', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PNID1' => Http::response(['id' => 'PNID1']),
    ]);

    ChannelConfiguration::factory()->whatsapp()->enabled()->create(['credentials' => ['token' => 'T', 'phone_number_id' => 'PNID1']]);

    expect(app(MessagingManager::class)->verify('whatsapp')->success)->toBeTrue();
});

test('send por whatsapp cloud sube el pdf y envia documento con caption', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PNID1/media' => Http::response(['id' => 'MEDIA1']),
        'graph.facebook.com/v21.0/PNID1/messages' => Http::response(['messages' => [['id' => 'WAMID1']]]),
    ]);

    ChannelConfiguration::factory()->whatsapp()->enabled()->create([
        'credentials' => ['token' => 'T', 'phone_number_id' => 'PNID1'],
    ]);

    $pdfPath = storage_path('app/public/whatsapp-pdfs/test-doc.pdf');
    if (! is_dir(dirname($pdfPath))) {
        mkdir(dirname($pdfPath), 0777, true);
    }
    file_put_contents($pdfPath, '%PDF-1.4 test');

    try {
        $result = app(MessagingManager::class)->send(
            'whatsapp',
            '593991234567',
            'Mensaje con adjunto',
            $pdfPath,
            'notificacion-test.pdf',
        );

        expect($result->success)->toBeTrue()
            ->and($result->externalId)->toBe('WAMID1');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages')
            && data_get($request->data(), 'to') === '593991234567'
            && data_get($request->data(), 'type') === 'document'
            && data_get($request->data(), 'document.id') === 'MEDIA1'
            && data_get($request->data(), 'document.caption') === 'Mensaje con adjunto');
    } finally {
        @unlink($pdfPath);
    }
});

test('verify de telegram consulta getme y detecta token invalido', function () {
    Http::fake([
        'api.telegram.org/botGOOD/getMe' => Http::response(['ok' => true, 'result' => ['id' => 42]]),
        'api.telegram.org/botBAD/getMe' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
    ]);

    ChannelConfiguration::factory()->telegram()->enabled()->create();

    $manager = app(MessagingManager::class);

    expect($manager->verify('telegram', ['bot_token' => 'GOOD'])->success)->toBeTrue()
        ->and($manager->verify('telegram', ['bot_token' => 'BAD'])->error)->toContain('Unauthorized');
});

test('send por email adjunta el pdf y usa la cola', function () {
    Mail::fake();

    ChannelConfiguration::factory()->email()->enabled()->create();

    $pdfPath = storage_path('app/public/whatsapp-pdfs/test-mail.pdf');
    file_put_contents($pdfPath, '%PDF-1.4 mail');

    try {
        $result = app(MessagingManager::class)->send('email', 'rep@correo.com', 'Cuerpo del correo', $pdfPath, 'adjunto.pdf');

        expect($result->success)->toBeTrue();

        Mail::assertQueued(GenericChannelMail::class, fn (GenericChannelMail $mail) => $mail->hasTo('rep@correo.com'));
    } finally {
        @unlink($pdfPath);
    }
});

test('el job actualiza el canal de notificacion a sent o failed', function () {
    Http::fake([
        'api.telegram.org/botTOK/*' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 7]])
            ->push(['ok' => false, 'description' => 'chat not found'], 400),
    ]);

    ChannelConfiguration::factory()->telegram()->enabled()->create(['credentials' => ['bot_token' => 'TOK']]);

    $notificationId = messagingSeedNotification();

    $sentRow = NotificationChannel::query()->create(['notification_id' => $notificationId, 'channel' => 'telegram', 'status' => 'pending']);

    (new SendChannelMessageJob('telegram', 'CHATID', 'mensaje', null, null, $sentRow->id))->handle(app(MessagingManager::class));

    expect($sentRow->refresh()->status)->toBe('sent');

    $failedRow = NotificationChannel::query()->create(['notification_id' => $notificationId, 'channel' => 'telegram', 'status' => 'pending']);

    (new SendChannelMessageJob('telegram', 'CHATID', 'mensaje', null, null, $failedRow->id))->handle(app(MessagingManager::class));

    expect($failedRow->refresh()->status)->toBe('failed');
});

test('dispatch del job se encola correctamente', function () {
    Queue::fake();

    SendChannelMessageJob::dispatch('whatsapp', '593999999999', 'texto');

    Queue::assertPushed(SendChannelMessageJob::class);
});
