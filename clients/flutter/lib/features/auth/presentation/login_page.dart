import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/env/app_env.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/local_store.dart';
import '../data/auth_repository.dart';

final localStoreProvider = Provider<LocalStore>(
  (ref) =>
      throw UnimplementedError('Sobrescrito en main() con PrefsLocalStore.'),
);

final apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient(store: ref.watch(localStoreProvider)),
);

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(apiClientProvider)),
);

/// Token persistido (fuente de la puerta de arranque).
final authSessionProvider = FutureProvider<String?>(
  (ref) async => ref.watch(localStoreProvider).bearerToken(),
);

/// Estado del login (sealed, mismo vocabulario que el web).
sealed class AuthState {
  const AuthState();
}

class AuthIdle extends AuthState {
  const AuthIdle();
}

class AuthSubmitting extends AuthState {
  const AuthSubmitting();
}

class AuthFailed extends AuthState {
  const AuthFailed(this.message);

  final String message;
}

class AuthSuccess extends AuthState {
  const AuthSuccess(this.token);

  final String token;
}

class AuthNotifier extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthIdle();

  Future<void> submit({required String login, required String password}) async {
    if (login.isEmpty || password.isEmpty) {
      state = const AuthFailed('Ingrese el usuario y la contraseña.');
      return;
    }

    state = const AuthSubmitting();

    try {
      final session = await ref
          .read(authRepositoryProvider)
          .login(login: login, password: password);
      await ref.read(localStoreProvider).saveBearerToken(session.token);
      state = AuthSuccess(session.token);
    } on ApiException catch (error) {
      state = AuthFailed(error.message);
    } catch (error) {
      state = AuthFailed('No se pudo conectar con el servidor.');
    }
  }

  Future<void> logout() async {
    await ref.read(localStoreProvider).clearAuth();
    state = const AuthIdle();
    // Fuerza a BootGate a reconsiderar el token persistido.
    ref.invalidate(authSessionProvider);
  }
}

final authProvider = NotifierProvider<AuthNotifier, AuthState>(
  AuthNotifier.new,
);

/// Pantalla de login (placeholder funcional del flujo Fortify de la API v1).
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    await ref
        .read(authProvider.notifier)
        .submit(login: _login.text.trim(), password: _password.text);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(authProvider);
    final submitting = state is AuthSubmitting;

    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Eduvex Docente',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Base URL: ${AppEnv.baseUrl}',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: _login,
                      decoration: const InputDecoration(
                        labelText: 'Usuario (email)',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty)
                          ? 'Ingrese el usuario.'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Contraseña',
                        prefixIcon: Icon(Icons.lock_outline),
                      ),
                      validator: (value) => (value == null || value.isEmpty)
                          ? 'Ingrese la contraseña.'
                          : null,
                      onFieldSubmitted: (_) => _submit(),
                    ),
                    if (state is AuthFailed) ...[
                      const SizedBox(height: 16),
                      Text(
                        state.message,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: submitting ? null : _submit,
                      child: submitting
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Ingresar'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
