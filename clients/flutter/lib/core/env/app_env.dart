/// Configuración de entorno del cliente Eduvex Desktop.
///
/// La base URL apunta al proyecto servido por Laravel Herd (`*.test`).
/// En producción se reemplaza por el dominio real de la API.
abstract final class AppEnv {
  static const String baseUrl = 'http://eduvexdocente.test';
  static const String apiVersion = 'v1';
  static const String apiBaseUrl = '$baseUrl/api/$apiVersion';

  /// Umbrales de escala 0-10 (fuente: docs/ui/FLUTTER_DESKTOP_GUIDELINES.md, §8).
  static const double passingThreshold = 7.0;
  static const double supplementaryThreshold = 5.0;

  /// Clave de persistencia del token Bearer (no exponer más datos aquí).
  static const String tokenPrefsKey = 'auth.bearer_token';
}
