import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../data/recoveries_repository.dart';
import '../../auth/presentation/login_page.dart';

final recoveriesRepositoryProvider = Provider<RecoveriesRepository>(
  (ref) => RecoveriesRepository(ref.watch(apiClientProvider)),
);

/// Estado de recuperaciones (sealed, mapea a los estados del web UI).
sealed class RecoveriesState {
  const RecoveriesState();
}

class RecoveriesLoading extends RecoveriesState {
  const RecoveriesLoading();
}

/// No hay nada recuperable → el web muestra "No se encontraron elementos".
class RecoveriesNoAssignments extends RecoveriesState {
  const RecoveriesNoAssignments();
}

/// Período de calificación cerrado → bloqueo de edición.
class RecoveriesClosed extends RecoveriesState {
  const RecoveriesClosed();
}

class RecoveriesReady extends RecoveriesState {
  const RecoveriesReady(this.snapshot);

  final RecoveriesSnapshot snapshot;
}

class RecoveriesError extends RecoveriesState {
  const RecoveriesError(this.message);

  final String message;
}

class RecoveriesNotifier extends Notifier<RecoveriesState> {
  @override
  RecoveriesState build() {
    _load();
    return const RecoveriesLoading();
  }

  Future<void> _load() async {
    state = const RecoveriesLoading();
    try {
      final snapshot = await ref.read(recoveriesRepositoryProvider).snapshot();
      if (snapshot.recoverable.isEmpty) {
        state = const RecoveriesNoAssignments();
        return;
      }
      state = RecoveriesReady(snapshot);
    } on ApiException catch (error) {
      state = RecoveriesError(error.message);
    } catch (error) {
      state = const RecoveriesError(
        'No se pudieron cargar las recuperaciones.',
      );
    }
  }

  Future<void> refresh() => _load();
}

final recoveriesProvider =
    NotifierProvider<RecoveriesNotifier, RecoveriesState>(
      RecoveriesNotifier.new,
    );

/// Pestaña F4: recuperaciones (aplicadas + elegibles).
class RecoveriesPage extends ConsumerWidget {
  const RecoveriesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(recoveriesProvider);

    return switch (state) {
      RecoveriesLoading() => const Center(child: CircularProgressIndicator()),
      RecoveriesNoAssignments() => const Center(
        child: Text(
          'No se encontraron elementos recuperables en este período.',
        ),
      ),
      RecoveriesClosed() => const Center(
        child: Text('El período de calificación está cerrado.'),
      ),
      RecoveriesError() => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(state.message),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => ref.read(recoveriesProvider.notifier).refresh(),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      RecoveriesReady() => _ReadyView(snapshot: state.snapshot),
    };
  }
}

class _ReadyView extends StatelessWidget {
  const _ReadyView({required this.snapshot});

  final RecoveriesSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Elementos recuperables',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),
        for (final item in snapshot.recoverable)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: Icon(
                item.type == 'exam'
                    ? Icons.assignment_returned_outlined
                    : Icons.assignment_outlined,
              ),
              title: Text(item.name),
              subtitle: Text('${item.eligible} estudiantes elegibles'),
            ),
          ),
        if (snapshot.applied.isNotEmpty) ...[
          const SizedBox(height: 24),
          Text(
            'Recuperaciones aplicadas',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          for (final applied in snapshot.applied)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const Icon(Icons.check_circle_outline),
                title: Text(applied.studentName),
                subtitle: Text(applied.element),
                trailing: Text(
                  '${applied.originalGrade} → ${applied.finalGrade}',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ),
        ],
      ],
    );
  }
}
