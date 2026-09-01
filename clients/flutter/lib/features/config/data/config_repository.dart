import '../../../core/network/api_client.dart';

/// Configuración global de `/configuration` (catálogos, períodos, escala,
/// estados de asistencia, tipos de horario, módulos habilitados).
class ConfigDto {
  const ConfigDto({
    required this.modules,
    required this.gradingScheme,
    required this.attendanceStatuses,
    required this.scheduleTypes,
  });

  final Map<String, bool> modules;
  final GradingSchemeDto gradingScheme;
  final Map<String, String> attendanceStatuses;
  final Map<String, String> scheduleTypes;

  factory ConfigDto.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};

    final gradingRaw =
        (data['grading_scheme'] as Map?)?.cast<String, dynamic>() ?? const {};
    final grading = GradingSchemeDto(
      formativePercentage: (gradingRaw['formative_percentage'] as num? ?? 0)
          .toDouble(),
      summativePercentage: (gradingRaw['summative_percentage'] as num? ?? 0)
          .toDouble(),
      examPercentage: (gradingRaw['exam_percentage'] as num? ?? 0).toDouble(),
      projectPercentage: (gradingRaw['project_percentage'] as num? ?? 0)
          .toDouble(),
    );

    return ConfigDto(
      modules: (data['modules'] as Map?)?.cast<String, bool>() ?? const {},
      gradingScheme: grading,
      attendanceStatuses:
          (data['attendance_statuses'] as Map?)?.cast<String, String>() ??
          const {},
      scheduleTypes:
          (data['schedule_types'] as Map?)?.cast<String, String>() ?? const {},
    );
  }
}

class GradingSchemeDto {
  const GradingSchemeDto({
    required this.formativePercentage,
    required this.summativePercentage,
    required this.examPercentage,
    required this.projectPercentage,
  });

  final double formativePercentage;
  final double summativePercentage;
  final double examPercentage;
  final double projectPercentage;
}

class ConfigRepository {
  ConfigRepository(this._api);

  final ApiClient _api;

  Future<ConfigDto> fetch([String version = '']) async {
    final envelope = await _api.get(
      '/configuration',
      query: {if (version.isNotEmpty) 'version': version},
    );

    return ConfigDto.fromEnvelope(envelope);
  }
}
