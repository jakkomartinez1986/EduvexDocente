import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eduvex_docente/features/auth/presentation/login_page.dart';
import 'package:eduvex_docente/features/schedule/presentation/schedule_page.dart';
import 'package:eduvex_docente/core/env/app_env.dart';
import 'package:eduvex_docente/core/network/api_client.dart';
import 'package:eduvex_docente/core/storage/local_store.dart';
import 'package:eduvex_docente/features/recoveries/presentation/recoveries_page.dart';

import 'package:eduvex_docente/shell/home_shell.dart';

class _MemoryStore implements LocalStore {
  String? token;

  @override
  Future<String?> bearerToken() async => token;

  @override
  Future<void> saveBearerToken(String bearerToken) async {
    token = bearerToken;
  }

  @override
  Future<void> clearAuth() async {
    token = null;
  }

  @override
  Future<String?> read(String key) async => null;

  @override
  Future<void> write(String key, String value) async {}
}

/// Sustituye a Dio en los tests: los repositorios quedan herméticos (sin timers).
class _FakeApiClient implements ApiClient {
  @override
  Future<ApiEnvelope> get(String path, {Map<String, dynamic>? query}) async =>
      const ApiEnvelope(
        success: true,
        data: {'schedules': [], 'recoverable': [], 'recoveries': []},
        errors: {},
        meta: {},
      );

  @override
  Future<ApiEnvelope> post(String path, {Object? body}) async =>
      const ApiEnvelope(
        success: true,
        data: {'token': 'fake-token'},
        errors: {},
        meta: {},
      );

  @override
  Future<ApiEnvelope> put(String path, {Object? body}) async =>
      const ApiEnvelope(
        success: true,
        data: <String, dynamic>{},
        errors: {},
        meta: {},
      );

  @override
  Future<ApiEnvelope> delete(String path) async =>
      const ApiEnvelope(success: true, data: null, errors: {}, meta: {});
}

ProviderScope _scope(Widget child, {String? token}) {
  return ProviderScope(
    overrides: [
      localStoreProvider.overrideWithValue(_MemoryStore()..token = token),
      apiClientProvider.overrideWithValue(_FakeApiClient()),
    ],
    child: MaterialApp(home: child),
  );
}

void main() {
  test('AppEnv expone la base URL de Herd sin slash final', () {
    expect(AppEnv.baseUrl, 'https://eduvexdocente.test');
    expect(AppEnv.apiBaseUrl, 'https://eduvexdocente.test/api/v1');
  });

  testWidgets('la shell arranca con las cuatro pestañas', (tester) async {
    await tester.pumpWidget(_scope(const HomeShell(), token: 'abc'));
    await tester.pumpAndSettle();

    expect(find.text('Horario'), findsWidgets);
    expect(find.text('Evaluación'), findsWidgets);
    expect(find.text('Asistencia'), findsWidgets);
    expect(find.text('Recuperación'), findsWidgets);
  });

  testWidgets('la shell muestra el estado vacío del horario', (tester) async {
    await tester.pumpWidget(_scope(const SchedulePage(), token: 'abc'));
    await tester.pumpAndSettle();

    expect(
      find.text('No hay horas académicas asignadas para este año lectivo.'),
      findsOneWidget,
    );
  });

  testWidgets('recuperaciones sin elementos eleva RecoveriesNoAssignments', (
    tester,
  ) async {
    await tester.pumpWidget(_scope(const RecoveriesPage()));
    await tester.pumpAndSettle();

    expect(
      find.text('No se encontraron elementos recuperables en este período.'),
      findsOneWidget,
    );
  });

  testWidgets('sin token se muestra la pantalla de login', (tester) async {
    await tester.pumpWidget(_scope(const LoginPage()));

    expect(find.text('Eduvex Docente'), findsOneWidget);
    expect(find.text('Ingresar'), findsOneWidget);
  });

  testWidgets('el login persiste el token al enviar el formulario', (
    tester,
  ) async {
    final store = _MemoryStore();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          localStoreProvider.overrideWithValue(store),
          apiClientProvider.overrideWithValue(_FakeApiClient()),
        ],
        child: const MaterialApp(home: LoginPage()),
      ),
    );

    await tester.enterText(
      find.byType(TextFormField).first,
      'teacher@eduvex.edu.ec',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'secret');
    await tester.tap(find.text('Ingresar'));
    await tester.pumpAndSettle();

    expect(store.token, 'fake-token');
  });
}
