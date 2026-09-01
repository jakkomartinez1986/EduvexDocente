import '../../../core/network/api_client.dart';

/// Bloque de evaluación (con actividades y notas) del libro de calificaciones.
class BlockDto {
  const BlockDto({
    required this.id,
    required this.name,
    required this.order,
    required this.activities,
  });

  final int id;
  final String name;
  final int order;
  final List<ActivityDto> activities;

  factory BlockDto.fromJson(Map<String, dynamic> json) {
    final rawActivities = (json['activities'] as List?) ?? const [];
    return BlockDto(
      id: (json['id'] as num? ?? 0).toInt(),
      name: json['name'] as String? ?? '',
      order: (json['order'] as num? ?? 0).toInt(),
      activities: rawActivities
          .cast<Map>()
          .map((item) => ActivityDto.fromJson(item.cast<String, dynamic>()))
          .toList(),
    );
  }
}

class ActivityDto {
  const ActivityDto({
    required this.id,
    required this.name,
    required this.maxScore,
  });

  final int id;
  final String name;
  final double maxScore;

  factory ActivityDto.fromJson(Map<String, dynamic> json) {
    return ActivityDto(
      id: (json['id'] as num? ?? 0).toInt(),
      name: json['name'] as String? ?? '',
      maxScore: (json['max_score'] as num? ?? 0).toDouble(),
    );
  }
}

class GradebookDto {
  const GradebookDto({required this.blocks, required this.isOpen});

  final List<BlockDto> blocks;

  /// `data.context.trimester.is_grading_open`.
  final bool isOpen;

  factory GradebookDto.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    final context =
        (data['context'] as Map?)?.cast<String, dynamic>() ?? const {};
    final trimester =
        (context['trimester'] as Map?)?.cast<String, dynamic>() ?? const {};
    final rawBlocks = (data['blocks'] as List?) ?? const [];

    return GradebookDto(
      blocks: rawBlocks
          .cast<Map>()
          .map((item) => BlockDto.fromJson(item.cast<String, dynamic>()))
          .toList(),
      isOpen: trimester['is_grading_open'] as bool? ?? false,
    );
  }
}

/// Dataset offline de `/academic/gradebook/download`.
class GradebookDownloadDto {
  const GradebookDownloadDto({
    required this.yearId,
    required this.generatedAt,
    required this.blockCount,
    required this.examCount,
    required this.projectCount,
  });

  final int? yearId;
  final String? generatedAt;
  final int blockCount;
  final int examCount;
  final int projectCount;

  factory GradebookDownloadDto.fromEnvelope(ApiEnvelope envelope) {
    final data = (envelope.data as Map?)?.cast<String, dynamic>() ?? const {};
    return GradebookDownloadDto(
      yearId: data['year_id'] as int?,
      generatedAt: data['generated_at'] as String?,
      blockCount: ((data['blocks'] as List?) ?? const []).length,
      examCount: ((data['exams'] as List?) ?? const []).length,
      projectCount: ((data['projects'] as List?) ?? const []).length,
    );
  }
}

class GradebookRepository {
  GradebookRepository(this._api);

  final ApiClient _api;

  Future<GradebookDto> view({
    required int subjectId,
    required int gradeId,
    required int trimesterId,
    int? yearId,
  }) async {
    final envelope = await _api.get(
      '/academic/gradebook',
      query: {
        'subject_id': subjectId,
        'grade_id': gradeId,
        'trimester_id': trimesterId,
        'year_id': ?yearId,
      },
    );

    return GradebookDto.fromEnvelope(envelope);
  }

  Future<GradebookDownloadDto> download({
    int? yearId,
    int? subjectId,
    int? gradeId,
    int? trimesterId,
  }) async {
    final envelope = await _api.get(
      '/academic/gradebook/download',
      query: {
        'year_id': ?yearId,
        'subject_id': ?subjectId,
        'grade_id': ?gradeId,
        'trimester_id': ?trimesterId,
      },
    );

    return GradebookDownloadDto.fromEnvelope(envelope);
  }
}
