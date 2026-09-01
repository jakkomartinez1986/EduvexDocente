import 'package:flutter/material.dart';

/// Sistema de diseño Material 3 para el cliente desktop.
///
/// Paleta por módulo (ver docs/ui/DESIGN_SYSTEM.md):
/// gradebook=azul · attendance=verde · recoveries=ámbar · schedule=morado.
abstract final class EduvexTheme {
  static const Color brand = Color(0xFF4F46E5);
  static const Color gradebook = Color(0xFF2563EB);
  static const Color attendance = Color(0xFF059669);
  static const Color recoveries = Color(0xFFD97706);
  static const Color schedule = Color(0xFF7C3AED);

  /// Colores semánticos de la escala de notas (0-10).
  static Color gradingColor(double score) {
    if (score >= 7.0) return const Color(0xFF047857);
    if (score >= 5.0) return const Color(0xFFD97706);
    return const Color(0xFFB91C1C);
  }

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      brightness: Brightness.light,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      navigationRailTheme: const NavigationRailThemeData(
        minWidth: 72,
        selectedIconTheme: IconThemeData(size: 24),
      ),
    );
  }

  static ThemeData dark() {
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      brightness: Brightness.dark,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      navigationRailTheme: const NavigationRailThemeData(
        minWidth: 72,
        selectedIconTheme: IconThemeData(size: 24),
      ),
    );
  }
}
