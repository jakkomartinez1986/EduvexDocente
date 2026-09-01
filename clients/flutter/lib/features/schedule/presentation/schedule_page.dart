import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../data/schedule_repository.dart';
import '../../auth/presentation/login_page.dart';

final scheduleRepositoryProvider = Provider<ScheduleRepository>(
  (ref) => ScheduleRepository(ref.watch(apiClientProvider)),
);

/// Estado del horario (sealed; Ready → data editable del día/curso).
sealed class ScheduleState {
  const ScheduleState();
}

class ScheduleLoading extends ScheduleState {
  const ScheduleLoading();
}

class ScheduleReady extends ScheduleState {
  const ScheduleReady(this.schedules);

  final List<ClassScheduleDto> schedules;
}

class ScheduleError extends ScheduleState {
  const ScheduleError(this.message);

  final String message;
}

class ScheduleNotifier extends Notifier<ScheduleState> {
  @override
  ScheduleState build() {
    _load();
    return const ScheduleLoading();
  }

  Future<void> _load() async {
    state = const ScheduleLoading();
    try {
      final items = await ref.read(scheduleRepositoryProvider).list();
      state = ScheduleReady(items);
    } on ApiException catch (error) {
      state = ScheduleError(error.message);
    } catch (error) {
      state = const ScheduleError('No se pudo cargar el horario.');
    }
  }

  Future<void> refresh() => _load();
}

final scheduleProvider = NotifierProvider<ScheduleNotifier, ScheduleState>(
  ScheduleNotifier.new,
);

/// Muestra el horario del docente agrupado por día (pestaña F1).
class SchedulePage extends ConsumerWidget {
  const SchedulePage({super.key});

  static const dayOrder = {
    'LUNES': 0,
    'MARTES': 1,
    'MIERCOLES': 2,
    'JUEVES': 3,
    'VIERNES': 4,
    'SABADO': 5,
    'DOMINGO': 6,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(scheduleProvider);

    return switch (state) {
      ScheduleLoading() => const Center(child: CircularProgressIndicator()),
      ScheduleError() => _ErrorView(
        message: state.message,
        onRetry: () => ref.read(scheduleProvider.notifier).refresh(),
      ),
      ScheduleReady() =>
        state.schedules.isEmpty
            ? const _EmptyView()
            : _ScheduleBoard(schedules: state.schedules),
    };
  }
}

class _ScheduleBoard extends StatelessWidget {
  const _ScheduleBoard({required this.schedules});

  final List<ClassScheduleDto> schedules;

  @override
  Widget build(BuildContext context) {
    final grouped = <String, List<ClassScheduleDto>>{};
    for (final entry in schedules) {
      grouped.putIfAbsent(entry.day, () => []).add(entry);
    }

    final sortedDays = grouped.keys.toList()
      ..sort(
        (a, b) => (SchedulePage.dayOrder[a] ?? 99).compareTo(
          SchedulePage.dayOrder[b] ?? 99,
        ),
      );

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final day in sortedDays) ...[
          Text(day, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          for (final entry in grouped[day]!)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const Icon(Icons.schedule),
                title: Text(entry.subjectName),
                subtitle: Text(
                  '${entry.startTime} - ${entry.endTime} · ${entry.gradeTitle}',
                ),
                trailing: entry.classroom == null
                    ? null
                    : Text(
                        entry.classroom!,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
              ),
            ),
          const SizedBox(height: 8),
        ],
      ],
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.error_outline,
            size: 40,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: const Text('Reintentar')),
        ],
      ),
    );
  }
}

class _EmptyView extends StatelessWidget {
  const _EmptyView();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('No hay horas académicas asignadas para este año lectivo.'),
    );
  }
}
