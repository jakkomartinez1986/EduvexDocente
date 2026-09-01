import 'package:flutter/material.dart';

/// Pill/badge semántico equivalente a `flux:badge` del web.
class PillBadge extends StatelessWidget {
  const PillBadge({
    super.key,
    required this.label,
    required this.color,
    this.tonal = true,
  });

  final String label;
  final Color color;
  final bool tonal;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final background = tonal ? color.withValues(alpha: 0.12) : color;
    final foreground = tonal ? color : scheme.onPrimary;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: isDark && tonal ? color.withValues(alpha: 0.22) : background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: foreground,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
