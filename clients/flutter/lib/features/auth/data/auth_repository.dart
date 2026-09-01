import '../../../core/network/api_client.dart';

/// Resultado del login (envelope de `/auth/login`).
class AuthSession {
  const AuthSession({required this.token, required this.userId});

  final String token;
  final int userId;

  factory AuthSession.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    final token =
        data['token'] as String? ??
        data['access_token'] as String? ??
        data['plainTextToken'] as String?;

    if (token == null || token.isEmpty) {
      throw const ApiException(
        ApiErrorKind.server,
        'La API no devolvió un token. Revisa la forma del envelope.',
      );
    }

    return AuthSession(
      token: token,
      userId: (data['user_id'] as num? ?? 0).toInt(),
    );
  }
}

/// Datos mínimos de `/auth/me`.
class MeDto {
  const MeDto({required this.id, required this.name, required this.roles});

  final int id;
  final String name;
  final List<String> roles;

  factory MeDto.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    final roles = (data['roles'] as List?)?.cast<String>() ?? const <String>[];

    return MeDto(
      id: (data['id'] as num? ?? 0).toInt(),
      name: data['name'] as String? ?? '',
      roles: roles,
    );
  }
}

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  Future<AuthSession> login({
    required String login,
    required String password,
  }) async {
    final envelope = await _api.post(
      '/auth/login',
      body: {'login': login, 'password': password},
    );

    return AuthSession.fromEnvelope(envelope);
  }

  Future<MeDto> me() async => MeDto.fromEnvelope(await _api.get('/auth/me'));

  Future<void> logout() async {
    await _api.post('/auth/logout');
  }
}
