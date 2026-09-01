import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:window_manager/window_manager.dart';

import 'app.dart';
import 'core/storage/local_store.dart';
import 'features/auth/presentation/login_page.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await windowManager.ensureInitialized();
  await windowManager.setTitle('Eduvex Docente');
  await windowManager.setMinimumSize(const Size(900, 600));
  await windowManager.center();

  final store = await PrefsLocalStore.create();
  final root = ProviderScope(
    overrides: [localStoreProvider.overrideWithValue(store)],
    child: const EduvexApp(),
  );

  runApp(root);
}
