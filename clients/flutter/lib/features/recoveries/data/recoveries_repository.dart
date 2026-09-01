import '../../../core/network/api_client.dart';

/// Elemento recuperable (actividad o examen) con estudiantes elegibles.
class RecoverableDto {
  const RecoverableDto({
    required this.id,
    required this.type,
    required this.name,
    required this.eligible,
  });

  final int id;

  /// `activity` | `exam`.
  final String type;
  final String name;
  final int eligible;

  factory RecoverableDto.fromJson(Map<String, dynamic> json) {
    return RecoverableDto(
      id: (json['id'] as num? ?? 0).toInt(),
      type: json['type'] as String? ?? 'activity',
      name: json['name'] as String? ?? '',
      eligible:
          (json['eligible_students'] as List?)?.length ??
          (json['eligible'] as num? ?? 0).toInt(),
    );
  }
}

/// Intento de recuperación aplique el historial (`/recoveries/applied`).
class AppliedRecoveryDto {
  const AppliedRecoveryDto({
    required this.studentName,
    required this.element,
    required this.originalGrade,
    required this.recoveryGrade,
    required this.finalGrade,
  });

  final String studentName;
  final String element;
  final double originalGrade;
  final double recoveryGrade;
  final double finalGrade;

  factory AppliedRecoveryDto.fromJson(Map<String, dynamic> json) {
    return AppliedRecoveryDto(
      studentName: json['student_name'] as String? ?? '',
      element: json['element'] as String? ?? '',
      originalGrade: (json['original_grade'] as num? ?? 0).toDouble(),
      recoveryGrade: (json['recovery_grade'] as num? ?? 0).toDouble(),
      finalGrade: (json['final_grade'] as num? ?? 0).toDouble(),
    );
  }
}

class RecoveriesSnapshot {
  const RecoveriesSnapshot({required this.recoverable, required this.applied});

  final List<RecoverableDto> recoverable;
  final List<AppliedRecoveryDto> applied;
}

class RecoveriesRepository {
  RecoveriesRepository(this._api);

  final ApiClient _api;

  Future<RecoveriesSnapshot> snapshot({int? yearId, int? trimesterId}) async {
    final recoverable = await _api.get(
      '/recoveries/recoverable',
      query: {'year_id': ?yearId},
    );
    final applied = await _api.get(
      '/recoveries/applied',
      query: {'year_id': ?yearId, 'trimester_id': ?trimesterId},
    );

    List<RecoverableDto> items(String key) {
      final raw = (recoverable.data as Map?)?.cast<String, dynamic>()[key];
      // Hoja de ruta: los payloads se consolidan en la fase de integración;
      // aquí se tolera el fallback a la raíz del array.
      final list = raw is List
          ? raw
          : (recoverable.data is List ? recoverable.data as List : const []);
      return list
          .cast<Map>()
          .map((item) => RecoverableDto.fromJson(item.cast<String, dynamic>()))
          .toList();
    }

    final appliedRaw = applied.data is List
        ? applied.data as List
        : ((applied.data as Map?)?.cast<String, dynamic>()['recoveries']
                  as List? ??
              const []);

    return RecoveriesSnapshot(
      recoverable: items('recoverable'),
      applied: appliedRaw
          .cast<Map>()
          .map(
            (item) => AppliedRecoveryDto.fromJson(item.cast<String, dynamic>()),
          )
          .toList(),
    );
  }
}
