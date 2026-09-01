import 'package:shared_preferences/shared_preferences.dart';

import '../env/app_env.dart';

/// Abstracción de persistencia local. El contrato es agnóstico del backend:
/// el token y la cola offline (sync) viven detrás de esta interfaz.
abstract interface class LocalStore {
  Future<String?> bearerToken();

  Future<void> saveBearerToken(String token);

  Future<void> clearAuth();

  /// Lectura/escritura genérica para cola de sync (LWW con reporte).
  Future<String?> read(String key);

  Future<void> write(String key, String value);
}

/// Implementación sobre `shared_preferences`. Si más adelante se adopta
/// `drift`/`sqflite`, solo hay que cambiar esta fábrica.
class PrefsLocalStore implements LocalStore {
  PrefsLocalStore._(this._prefs);

  final SharedPreferences _prefs;

  static Future<PrefsLocalStore> create() async {
    return PrefsLocalStore._(await SharedPreferences.getInstance());
  }

  @override
  Future<String?> bearerToken() async => _prefs.getString(AppEnv.tokenPrefsKey);

  @override
  Future<void> saveBearerToken(String token) async {
    await _prefs.setString(AppEnv.tokenPrefsKey, token);
  }

  @override
  Future<void> clearAuth() async {
    await _prefs.remove(AppEnv.tokenPrefsKey);
  }

  @override
  Future<String?> read(String key) async => _prefs.getString(key);

  @override
  Future<void> write(String key, String value) async {
    await _prefs.setString(key, value);
  }
}
