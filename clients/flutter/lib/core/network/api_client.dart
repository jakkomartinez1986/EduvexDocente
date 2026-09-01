import 'package:dio/dio.dart';

import '../env/app_env.dart';
import '../storage/local_store.dart';

/// Envelope de la API v1: `{success, data, errors, meta}`.
///
/// [data] se mantiene sin tipar; los repositorios lo decodifican con sus DTOs.
class ApiEnvelope {
  const ApiEnvelope({
    required this.success,
    required this.data,
    required this.errors,
    required this.meta,
  });

  final bool success;
  final Object? data;
  final Map<String, dynamic> errors;
  final Map<String, dynamic> meta;

  factory ApiEnvelope.fromJson(Map<String, dynamic> json) {
    return ApiEnvelope(
      success: json['success'] as bool? ?? false,
      data: json['data'],
      errors: (json['errors'] as Map?)?.cast<String, dynamic>() ?? const {},
      meta: (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {},
    );
  }

  /// Mensaje human-readable: prioriza `errors` coalescido y luego `meta.message`
  /// o el campo `message` de nivel raíz si la API lo envía suelto.
  String get message {
    final errorValues = errors.values.whereType<String>().toList();
    if (errorValues.isNotEmpty) {
      return errorValues.first;
    }
    return meta['message'] as String? ?? 'Respuesta inesperada del servidor.';
  }
}

/// Error tipado que el UI muestra tal cual llega del servidor
/// (envelope), sin traducir (los mensajes ya vienen en español).
class ApiException implements Exception {
  const ApiException(this.kind, this.message, {this.errors = const {}});

  final ApiErrorKind kind;
  final String message;
  final Map<String, dynamic> errors;

  @override
  String toString() => message;
}

enum ApiErrorKind {
  network,
  unauthorized,
  forbidden,
  validation,
  notFound,
  conflict,
  server,
}

/// Cliente HTTP único con inyección del Bearer token y mapeo de errores
/// al envelope uniforme de la API.
class ApiClient {
  ApiClient({required this._store})
    : _dio = Dio(
        BaseOptions(
          baseUrl: AppEnv.apiBaseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 30),
          headers: const {'Accept': 'application/json'},
        ),
      );

  final LocalStore _store;
  final Dio _dio;

  Future<ApiEnvelope> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get<Map<String, dynamic>>(path, queryParameters: query));

  Future<ApiEnvelope> post(String path, {Object? body}) =>
      _send(() => _dio.post<Map<String, dynamic>>(path, data: body));

  Future<ApiEnvelope> put(String path, {Object? body}) =>
      _send(() => _dio.put<Map<String, dynamic>>(path, data: body));

  Future<ApiEnvelope> delete(String path) =>
      _send(() => _dio.delete<Map<String, dynamic>>(path));

  Future<ApiEnvelope> _send(
    Future<Response<Map<String, dynamic>>> Function() request,
  ) async {
    try {
      final token = await _store.bearerToken();
      if (token != null) {
        _dio.options.headers['Authorization'] = 'Bearer $token';
      }

      final response = await request();
      final envelope = ApiEnvelope.fromJson(response.data ?? const {});

      if (envelope.success) {
        return envelope;
      }

      throw ApiException(
        ApiErrorKind.server,
        envelope.message,
        errors: envelope.errors,
      );
    } on DioException catch (error) {
      throw _mapDio(error);
    } on ApiException {
      rethrow;
    }
  }

  ApiException _mapDio(DioException error) {
    final status = error.response?.statusCode;
    final envelope = error.response?.data is Map
        ? ApiEnvelope.fromJson(
            (error.response!.data as Map).cast<String, dynamic>(),
          )
        : null;

    return switch (status) {
      401 => ApiException(
        ApiErrorKind.unauthorized,
        envelope?.message ?? 'No autorizado. Vuelve a iniciar sesión.',
        errors: envelope?.errors ?? const {},
      ),
      403 => ApiException(
        ApiErrorKind.forbidden,
        envelope?.message ?? 'No tienes permisos para esta acción.',
        errors: envelope?.errors ?? const {},
      ),
      404 => ApiException(
        ApiErrorKind.notFound,
        envelope?.message ?? 'Recurso no encontrado.',
        errors: envelope?.errors ?? const {},
      ),
      409 => ApiException(
        ApiErrorKind.conflict,
        envelope?.message ?? 'Conflicto con el estado actual.',
        errors: envelope?.errors ?? const {},
      ),
      422 => ApiException(
        ApiErrorKind.validation,
        envelope?.message ?? 'Datos inválidos.',
        errors: envelope?.errors ?? const {},
      ),
      _ => ApiException(
        ApiErrorKind.server,
        envelope?.message ?? 'Error del servidor (HTTP $status).',
        errors: envelope?.errors ?? const {},
      ),
    };
  }
}
