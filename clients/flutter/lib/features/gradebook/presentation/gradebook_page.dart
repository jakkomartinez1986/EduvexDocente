import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../data/gradebook_repository.dart';
import '../../auth/presentation/login_page.dart';

final gradebookRepositoryProvider = Provider<GradebookRepository>(
  (ref) => GradebookRepository(ref.watch(apiClientProvider)),
);

/// Estado del libro (sealed).
sealed class GradebookState {
  const GradebookState();
}

class GradebookLoading extends GradebookState {
  const GradebookLoading();
}

/// Datos de demostración del flujo offline (requiere selectores de contexto
/// año/materia/grado/trimestre: pendiente de la fase de integración).
class GradebookOffline extends GradebookState {
  const GradebookOffline(this.download);

  final GradebookDownloadDto download;
}

class GradebookError extends GradebookState {
  const GradebookError(this.message);

  final String message;
}

class GradebookNotifier extends Notifier<GradebookState> {
  @override
  GradebookState build() {
    _load();
    return const GradebookLoading();
  }

  Future<void> _load() async {
    state = const GradebookLoading();
    try {
      final download = await ref.read(gradebookRepositoryProvider).download();
      state = GradebookOffline(download);
    } on ApiException catch (error) {
      state = GradebookError(error.message);
    } catch (error) {
      state = const GradebookError(
        'No se pudo cargar el libro de calificaciones.',
      );
    }
  }

  Future<void> refresh() => _load();
}

final gradebookProvider = NotifierProvider<GradebookNotifier, GradebookState>(
  GradebookNotifier.new,
);

/// Pestaña F2: libro de calificaciones (evaluación).
class GradebookPage extends ConsumerWidget {
  const GradebookPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(gradebookProvider);

    return switch (state) {
      GradebookLoading() => const Center(child: CircularProgressIndicator()),
      GradebookError() => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(state.message),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => ref.read(gradebookProvider.notifier).refresh(),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      GradebookOffline() => _OfflineView(download: state.download),
    };
  }
}

class _OfflineView extends StatelessWidget {
  const _OfflineView({required this.download});

  final GradebookDownloadDto download;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            leading: const Icon(Icons.cloud_download_outlined),
            title: const Text('Libro descargado para trabajo offline'),
            subtitle: Text(
              '${download.blockCount} bloques · ${download.examCount} exámenes · '
              '${download.projectCount} proyectos'
              '${download.generatedAt == null ? '' : ' · generado ${download.generatedAt}'}',
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'La vista de evaluación por curso/trimestre se habilita al seleccionar '
          'materia, grado y trimestre (fase de integración).',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      ],
    );
  }
}
