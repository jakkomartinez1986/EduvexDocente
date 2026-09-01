import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/attendance/presentation/attendance_page.dart';
import '../features/auth/presentation/login_page.dart';
import '../features/gradebook/presentation/gradebook_page.dart';
import '../features/recoveries/presentation/recoveries_page.dart';
import '../features/schedule/presentation/schedule_page.dart';

final _pages = const [
  SchedulePage(),
  GradebookPage(),
  AttendancePage(),
  RecoveriesPage(),
];

/// Cáscara de escritorio: NavigationRail + breadcrumb + logout.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _selectedIndex = 0;

  static const _titles = [
    'Horario',
    'Evaluación',
    'Asistencia',
    'Recuperaciones',
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            selectedIndex: _selectedIndex,
            onDestinationSelected: (index) =>
                setState(() => _selectedIndex = index),
            labelType: NavigationRailLabelType.all,
            leading: const Padding(
              padding: EdgeInsets.only(top: 8),
              child: FlutterLogo(size: 32),
            ),
            trailing: Expanded(
              child: Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Align(
                  alignment: Alignment.bottomCenter,
                  child: IconButton(
                    tooltip: 'Cerrar sesión',
                    icon: const Icon(Icons.logout),
                    onPressed: () => ref.read(authProvider.notifier).logout(),
                  ),
                ),
              ),
            ),
            destinations: const [
              NavigationRailDestination(
                icon: Icon(Icons.calendar_month_outlined),
                selectedIcon: Icon(Icons.calendar_month),
                label: Text('Horario'),
              ),
              NavigationRailDestination(
                icon: Icon(Icons.grading_outlined),
                selectedIcon: Icon(Icons.grading),
                label: Text('Evaluación'),
              ),
              NavigationRailDestination(
                icon: Icon(Icons.person_pin_circle_outlined),
                selectedIcon: Icon(Icons.person_pin_circle),
                label: Text('Asistencia'),
              ),
              NavigationRailDestination(
                icon: Icon(Icons.sync_alt_outlined),
                selectedIcon: Icon(Icons.sync_alt),
                label: Text('Recuperación'),
              ),
            ],
          ),
          Expanded(
            child: Column(
              children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: Text(
                    _titles[_selectedIndex],
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                Expanded(
                  child: IndexedStack(index: _selectedIndex, children: _pages),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
