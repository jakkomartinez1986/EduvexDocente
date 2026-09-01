import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/presentation/login_page.dart';
import 'shell/home_shell.dart';

/// Aplicación Eduvex Docente (cliente de escritorio).
class EduvexApp extends StatelessWidget {
  const EduvexApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Eduvex Docente',
      debugShowCheckedModeBanner: false,
      themeMode: ThemeMode.system,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF3B6FB5)),
      ),
      darkTheme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF3B6FB5),
          brightness: Brightness.dark,
        ),
      ),
      home: const BootGate(),
    );
  }
}

/// Puerta de arranque: token persistido → shell; si no → login.
class BootGate extends ConsumerWidget {
  const BootGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authSessionProvider);

    return session.when(
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (error, stack) => const Scaffold(
        body: Center(
          child: Text('No se pudo inicializar el almacenamiento local.'),
        ),
      ),
      data: (token) => (token == null || token.isEmpty)
          ? const LoginPage()
          : const HomeShell(),
    );
  }
}
