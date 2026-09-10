<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\User;
use App\Services\Messaging\ChannelStatusService;
use App\Services\Messaging\MessagingManager;
use App\Services\Messaging\NotificationMessageBuilder;
use App\Services\Messaging\WaMeLinkService;
use App\Services\Messaging\WhatsAppCloudSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Cache::flush());

function channelStatusAdminUser(): User
{
    $role = Role::firstOrCreate(
        ['name' => 'SUPER-ADMIN', 'guard_name' => 'web'],
        ['description' => 'Super Administrador'],
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('telegram sin configuracion reporta canal no disponible y sin api', function () {
    ChannelConfiguration::factory()->telegram()->create();

    $service = app(ChannelStatusService::class);
    $status = $service->forChannels(['telegram'])['telegram'];

    expect($service->apiAvailable('telegram'))->toBeFalse()
        ->and($status['enabled'])->toBeFalse()
        ->and($status['manual_available'])->toBeFalse();
});

test('telegram habilitado con bot token permite envio por api', function () {
    ChannelConfiguration::factory()->telegram()->enabled()->create();

    expect(app(ChannelStatusService::class)->apiAvailable('telegram'))->toBeTrue();
});

test('telegram habilitado sin bot token no permite envio por api', function () {
    ChannelConfiguration::factory()->telegram()->enabled()->create(['credentials' => null]);

    expect(app(ChannelStatusService::class)->apiAvailable('telegram'))->toBeFalse();
});

test('whatsapp habilitado con credenciales completas permite api', function () {
    ChannelConfiguration::factory()->whatsapp()->enabled()->create();

    $status = app(ChannelStatusService::class)->forChannels(['whatsapp'])['whatsapp'];

    expect($status['api_available'])->toBeTrue()
        ->and($status['enabled'])->toBeTrue()
        ->and($status['manual_available'])->toBeTrue();
});

test('whatsapp habilitado sin phone number id no permite api pero si manual', function () {
    ChannelConfiguration::factory()->whatsapp()->enabled()->create(['credentials' => ['token' => 'wa-token']]);

    $status = app(ChannelStatusService::class)->forChannels(['whatsapp'])['whatsapp'];

    expect($status['api_available'])->toBeFalse()
        ->and($status['manual_available'])->toBeTrue();
});

test('la matriz de estados nunca expone credenciales', function () {
    ChannelConfiguration::factory()->whatsapp()->enabled()->create([
        'credentials' => ['token' => 'TOKEN-SECRETO-WA', 'phone_number_id' => 'PNID-XYZ'],
    ]);
    ChannelConfiguration::factory()->telegram()->enabled()->create([
        'credentials' => ['bot_token' => 'TOKEN-SECRETO-TG'],
    ]);

    $statuses = app(ChannelStatusService::class)->forChannels(['whatsapp', 'telegram']);
    $encoded = json_encode($statuses);

    expect($encoded)->not->toContain('TOKEN-SECRETO')
        ->and($encoded)->not->toContain('PNID-XYZ');
});

test('el status del canal se cachea y se invalida al guardar la configuracion', function () {
    $service = app(ChannelStatusService::class);

    expect($service->apiAvailable('telegram'))->toBeFalse();

    $key = ChannelStatusService::statusKey('telegram');
    expect(Cache::has($key))->toBeTrue();

    ChannelConfiguration::factory()->telegram()->enabled()->create();

    expect(Cache::has($key))->toBeFalse()
        ->and($service->apiAvailable('telegram'))->toBeTrue();
});

test('el snapshot de credenciales se cachea y se invalida al guardar el canal', function () {
    $config = ChannelConfiguration::factory()->whatsapp()->enabled()->create();

    app(MessagingManager::class)->for('whatsapp');

    expect(Cache::has(MessagingManager::credsKey('whatsapp')))->toBeTrue();

    $config->update(['enabled' => false]);

    expect(Cache::has(MessagingManager::credsKey('whatsapp')))->toBeFalse()
        ->and(Cache::has(ChannelStatusService::statusKey('whatsapp')))->toBeFalse();
});

test('el snapshot de credenciales respeta el override y nunca cachea el modelo', function () {
    ChannelConfiguration::factory()->whatsapp()->enabled()->create(['credentials' => ['token' => 'DB-TOKEN', 'phone_number_id' => 'Q']]);

    expect(Cache::has(MessagingManager::credsKey('whatsapp')))->toBeFalse();

    $sender = app(MessagingManager::class)->for('whatsapp', ['token' => 'OVERRIDE', 'phone_number_id' => 'R']);

    expect($sender)->toBeInstanceOf(WhatsAppCloudSender::class);

    $stored = Cache::get(MessagingManager::credsKey('whatsapp'));

    expect($stored)->toBeArray()
        ->and($stored['enabled'])->toBeTrue()
        ->and($stored['credentials']['token'])->toBe('DB-TOKEN');
});

dataset('phones', [
    '0991234567 -> 593991234567' => ['0991234567', '593991234567'],
    '+593 99 123 4567 -> 593991234567' => ['+593 99 123 4567', '593991234567'],
    '593991234567 se conserva' => ['593991234567', '593991234567'],
    '9 digitos anteponen 593' => ['991234567', '593991234567'],
    'guiones y espacios se limpian' => ['0991-234-567', '593991234567'],
    'vacio devuelve null' => ['', null],
    'sin digitos devuelve null' => ['abc-def', null],
]);

test('normalizacion de telefonos para wa me', function (string $input, ?string $expected) {
    expect(app(WaMeLinkService::class)->normalizePhone($input))->toBe($expected);
})->with('phones');

test('el enlace wa me usa el formato exacto con texto url encoded', function () {
    $message = "Estimado(a) representante:\n\nLe informamos sobre Juan (ácción ñ).";

    $link = app(WaMeLinkService::class)->buildLink('0991234567', $message);

    expect($link)->toStartWith('https://wa.me/593991234567?text=')
        ->and($link)->toBe('https://wa.me/593991234567?text='.rawurlencode($message))
        ->and($link)->toContain('%20')
        ->and($link)->not->toContain(' ');
});

test('genera un enlace individual por destinatario con el mismo mensaje', function () {
    $message = 'Mensaje unico para todos';

    $links = app(WaMeLinkService::class)->buildLinks(
        [['phone' => '0991112223'], ['phone' => '593982223344'], ['phone' => 'sin-digitos']],
        $message,
    );

    expect($links)->toHaveCount(2)
        ->and($links[0])->toBe('https://wa.me/593991112223?text='.rawurlencode($message))
        ->and($links[1])->toBe('https://wa.me/593982223344?text='.rawurlencode($message));
});

test('el mensaje manual es exactamente igual al generado por el servicio existente', function () {
    $builder = app(NotificationMessageBuilder::class);

    $repUser = new User;
    $repUser->lastname = 'TORRES';
    $repUser->name = 'ANA';

    $studentUser = new User;
    $studentUser->lastname = 'LOPEZ';
    $studentUser->name = 'LUIS';

    $student = new Student;
    $student->setRelation('user', $studentUser);

    $notification = new AcademicNotification;
    $notification->forceFill(['code' => 'NOT-20260824-0001']);
    $notification->setRelation('student', $student);

    $representative = new Representative;
    $representative->setRelation('user', $repUser);

    $expected = "Estimado(a) TORRES ANA:\n".
        "\n".
        'Le compartimos la notificación NOT-20260824-0001 correspondiente al estudiante LOPEZ LUIS.'."\n".
        "\n".
        'Adjunto encontrará el documento PDF con el detalle.';

    expect($builder->whatsappMessage($notification, $representative))->toBe($expected);
});

test('el mensaje usa el nombre del representante cuando esta disponible', function () {
    $builder = app(NotificationMessageBuilder::class);

    $student = new Student;
    $student->setRelation('user', new User);

    $notification = new AcademicNotification;
    $notification->forceFill(['code' => 'NOT-1']);
    $notification->setRelation('student', $student);

    $representative = new Representative;
    $representative->setRelation('user', (object) ['full_name' => 'María Torres']);

    expect($builder->whatsappMessage($notification, $representative))->toStartWith("Estimado(a) María Torres:\n");
});

test('la pagina de notificaciones muestra telegram y estados manuales sin api', function () {
    $this->actingAs(channelStatusAdminUser());

    $response = $this->get('/system/teacher/notifications');

    $response
        ->assertOk()
        ->assertSee('Estado de canales')
        ->assertSee('Envío manual')
        ->assertSee('No disponible')
        ->assertDontSee('API activa');
});

test('la pagina muestra estado de api activa cuando los canales estan habilitados', function () {
    ChannelConfiguration::factory()->whatsapp()->enabled()->create();
    ChannelConfiguration::factory()->telegram()->enabled()->create();

    $this->actingAs(channelStatusAdminUser());

    $this->get('/system/teacher/notifications')
        ->assertOk()
        ->assertSee('API activa');
});

test('los enlaces manuales no incluyen adjuntos ni rutas de almacenamiento', function () {
    ChannelConfiguration::factory()->whatsapp()->create();

    $link = app(WaMeLinkService::class)->buildLink('0999999999', 'texto puro');

    expect($link)->not->toContain('.pdf')
        ->and($link)->not->toContain('storage')
        ->and(DB::table('jobs')->count())->toBe(0);
});
