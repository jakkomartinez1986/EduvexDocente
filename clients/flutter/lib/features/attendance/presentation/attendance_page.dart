import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../data/attendance_repository.dart';
import '../../auth/presentation/login_page.dart';

final attendanceRepositoryProvider = Provider<AttendanceRepository>(
  (ref) => AttendanceRepository(ref.watch(apiClientProvider)),
);

/// Estado del registro del día (sealed; requiere fecha + hora).
sealed class AttendanceState {
  const AttendanceState();
}

class AttendanceNoSelection extends AttendanceState {
  const AttendanceNoSelection();
}

class AttendanceLoading extends AttendanceState {
  const AttendanceLoading();
}

class AttendanceReady extends AttendanceState {
  const AttendanceReady(this.day);

  final AttendanceDayDto day;
}

class AttendanceError extends AttendanceState {
  const AttendanceError(this.message);

  final String message;
}

class AttendanceNotifier extends Notifier<AttendanceState> {
  @override
  AttendanceState build() => const AttendanceNoSelection();

  Future<void> load({
    required int yearId,
    required int scheduleId,
    required String date,
  }) async {
    state = const AttendanceLoading();
    try {
      final day = await ref
          .read(attendanceRepositoryProvider)
          .register(yearId: yearId, scheduleId: scheduleId, date: date);
      state = AttendanceReady(day);
    } on ApiException catch (error) {
      state = AttendanceError(error.message);
    } catch (error) {
      state = const AttendanceError(
        'No se pudo cargar el registro de asistencia.',
      );
    }
  }
}

final attendanceProvider =
    NotifierProvider<AttendanceNotifier, AttendanceState>(
      AttendanceNotifier.new,
    );

/// Pestaña F3: asistencia (placeholder; el selector de hora/fecha llega luego).
class AttendancePage extends ConsumerWidget {
  const AttendancePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(attendanceProvider);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.event_note_outlined, size: 40),
            const SizedBox(height: 12),
            switch (state) {
              AttendanceNoSelection() => const Text(
                'Seleccione una hora del horario para registrar o editar asistencia.',
              ),
              AttendanceLoading() => const CircularProgressIndicator(),
              AttendanceError() => Text(state.message),
              AttendanceReady() => Text(
                '${state.day.studentNames.length} estudiantes · ${state.day.date}',
              ),
            },
          ],
        ),
      ),
    );
  }
}
