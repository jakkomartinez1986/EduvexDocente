import '../../../core/network/api_client.dart';

/// Una hora académica (`class_schedule`) del docente en el año lectivo.
class ClassScheduleDto {
  const ClassScheduleDto({
    required this.id,
    required this.yearId,
    required this.scheduleType,
    required this.day,
    required this.startTime,
    required this.endTime,
    required this.classroom,
    required this.isActive,
    required this.subjectId,
    required this.subjectName,
    required this.areaName,
    required this.gradeId,
    required this.gradeName,
    required this.section,
  });

  final int id;
  final int yearId;
  final String scheduleType;
  final String day;
  final String startTime;
  final String endTime;
  final String? classroom;
  final bool isActive;
  final int subjectId;
  final String subjectName;
  final String areaName;
  final int gradeId;
  final String gradeName;
  final String section;

  String get gradeTitle =>
      section.isEmpty ? gradeName : '$gradeName - $section';

  factory ClassScheduleDto.fromJson(Map<String, dynamic> json) {
    final subject =
        (json['subject'] as Map?)?.cast<String, dynamic>() ?? const {};
    final grade = (json['grade'] as Map?)?.cast<String, dynamic>() ?? const {};

    return ClassScheduleDto(
      id: (json['id'] as num? ?? 0).toInt(),
      yearId: (json['year_id'] as num? ?? 0).toInt(),
      scheduleType: json['schedule_type'] as String? ?? 'OFFICIAL',
      day: json['day'] as String? ?? '',
      startTime: json['start_time'] as String? ?? '00:00',
      endTime: json['end_time'] as String? ?? '00:00',
      classroom: json['classroom'] as String?,
      isActive: json['is_active'] as bool? ?? true,
      subjectId: (subject['id'] as num? ?? 0).toInt(),
      subjectName: subject['subject_name'] as String? ?? '',
      areaName: subject['area_name'] as String? ?? '',
      gradeId: (grade['id'] as num? ?? 0).toInt(),
      gradeName: grade['grade_name'] as String? ?? '',
      section: grade['section'] as String? ?? '',
    );
  }
}

/// Payload de alta/actualización de horario (contrato `POST/PUT /schedules`).
class ScheduleMutation {
  const ScheduleMutation({
    required this.yearId,
    required this.subjectId,
    required this.gradeId,
    required this.scheduleType,
    required this.day,
    required this.startTime,
    required this.endTime,
    this.trimesterId,
    this.classroom,
    this.isActive = true,
    this.notes,
  });

  final int yearId;
  final int subjectId;
  final int gradeId;
  final String scheduleType;

  /// `MIERCOLES`/`MIÉRCOLES` se normalizan en el servidor a `MIERCOLES`.
  final String day;
  final String startTime;
  final String endTime;
  final int? trimesterId;
  final String? classroom;
  final bool isActive;
  final String? notes;

  Map<String, dynamic> toJson() => {
    'year_id': yearId,
    'subject_id': subjectId,
    'grade_id': gradeId,
    'schedule_type': scheduleType,
    'day': day,
    'start_time': startTime,
    'end_time': endTime,
    'trimester_id': trimesterId,
    'classroom': classroom,
    'is_active': isActive,
    'notes': notes,
  };
}

class ScheduleRepository {
  ScheduleRepository(this._api);

  final ApiClient _api;

  Future<List<ClassScheduleDto>> list({
    int? yearId,
    String? scheduleType,
    String? day,
  }) async {
    final envelope = await _api.get(
      '/teachermanagement/schedules',
      query: {'year_id': ?yearId, 'schedule_type': ?scheduleType, 'day': ?day},
    );

    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    final items = data['schedules'] as List? ?? const [];

    return items
        .cast<Map>()
        .map((json) => ClassScheduleDto.fromJson(json.cast<String, dynamic>()))
        .toList();
  }

  Future<ClassScheduleDto> create(ScheduleMutation mutation) async {
    final envelope = await _api.post(
      '/teachermanagement/schedules',
      body: mutation.toJson(),
    );
    return ClassScheduleDto.fromJson(
      (envelope.data as Map).cast<String, dynamic>(),
    );
  }

  Future<ClassScheduleDto> update(int id, ScheduleMutation mutation) async {
    final envelope = await _api.put(
      '/teachermanagement/schedules/$id',
      body: mutation.toJson(),
    );
    return ClassScheduleDto.fromJson(
      (envelope.data as Map).cast<String, dynamic>(),
    );
  }

  Future<void> delete(int id) async {
    await _api.delete('/teachermanagement/schedules/$id');
  }
}
