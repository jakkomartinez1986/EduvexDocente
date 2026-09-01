import '../../../core/network/api_client.dart';

/// Registro del día para asistencia (`/teachermanagement/attendances/register`).
class AttendanceDayDto {
  const AttendanceDayDto({
    required this.scheduleId,
    required this.date,
    required this.studentNames,
  });

  final int scheduleId;
  final String date;
  final List<String> studentNames;

  factory AttendanceDayDto.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    final students = (data['students'] as List?) ?? const [];

    return AttendanceDayDto(
      scheduleId: (data['schedule_id'] as num? ?? 0).toInt(),
      date: data['date'] as String? ?? '',
      studentNames: students
          .cast<Map>()
          .map((student) {
            final cast = student.cast<String, dynamic>();
            final user = (cast['user'] as Map?)?.cast<String, dynamic>();
            final name =
                user?['full_name'] as String? ?? user?['name'] as String?;
            return name ?? '';
          })
          .where((name) => name.isNotEmpty)
          .toList(),
    );
  }
}

/// Observación por estudiante al guardar (`PUT /teachermanagement/attendances/register`).
class AttendanceEntry {
  const AttendanceEntry({
    required this.studentId,
    required this.status,
    this.observation,
  });

  final int studentId;
  final String status;
  final String? observation;

  Map<String, dynamic> toJson() => {
    'student_id': studentId,
    'status': status,
    if (observation != null && observation!.isNotEmpty)
      'observation': observation,
  };
}

class AttendanceRepository {
  AttendanceRepository(this._api);

  final ApiClient _api;

  Future<AttendanceDayDto> register({
    required int yearId,
    required int scheduleId,
    required String date,
  }) async {
    final envelope = await _api.get(
      '/teachermanagement/attendances/register',
      query: {'year_id': yearId, 'schedule_id': scheduleId, 'date': date},
    );

    return AttendanceDayDto.fromEnvelope(envelope);
  }

  Future<void> saveRegister({
    required int yearId,
    required int scheduleId,
    required String date,
    required List<AttendanceEntry> entries,
  }) async {
    await _api.put(
      '/teachermanagement/attendances/register',
      body: {
        'year_id': yearId,
        'schedule_id': scheduleId,
        'date': date,
        'entries': entries.map((entry) => entry.toJson()).toList(),
      },
    );
  }
}
