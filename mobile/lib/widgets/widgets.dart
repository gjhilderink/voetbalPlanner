import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class StatusBadge extends StatelessWidget {
  final String label;
  final Color background;
  final Color textColor;

  const StatusBadge({
    super.key,
    required this.label,
    required this.background,
    this.textColor = Colors.white,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 11, color: textColor, fontWeight: FontWeight.w600),
      ),
    );
  }

  static StatusBadge matchStatus(String status) {
    return switch (status) {
      'played' => StatusBadge(label: 'Gespeeld', background: AppColors.primary),
      'cancelled' => StatusBadge(label: 'Geannuleerd', background: AppColors.danger),
      'postponed' => StatusBadge(label: 'Uitgesteld', background: AppColors.warning),
      _ => StatusBadge(label: 'Gepland', background: AppColors.statusBevestigd),
    };
  }

  static StatusBadge barDutyStatus(String status) {
    return switch (status) {
      'bevestigd' => StatusBadge(label: 'Bevestigd', background: AppColors.statusBevestigd),
      'vervuld' => StatusBadge(label: 'Vervuld', background: AppColors.statusVervuld),
      _ => StatusBadge(label: 'Open', background: AppColors.statusOpen),
    };
  }

  static StatusBadge shift(String shift) {
    return switch (shift) {
      'middag' => StatusBadge(label: 'Middag', background: AppColors.shiftMiddag),
      'avond' => StatusBadge(label: 'Avond', background: AppColors.shiftAvond),
      _ => StatusBadge(label: 'Ochtend', background: AppColors.shiftOchtend),
    };
  }
}

class LoadingCenter extends StatelessWidget {
  const LoadingCenter({super.key});

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: CircularProgressIndicator(color: AppColors.primary),
    );
  }
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;

  const EmptyState({super.key, required this.icon, required this.title, this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: AppColors.textMuted),
            const SizedBox(height: 16),
            Text(title,
                style: const TextStyle(fontSize: 16, color: AppColors.textPrimary, fontWeight: FontWeight.w600)),
            if (subtitle != null) ...[
              const SizedBox(height: 8),
              Text(subtitle!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 14, color: AppColors.textMuted)),
            ],
          ],
        ),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const ErrorView({super.key, required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: AppColors.danger),
            const SizedBox(height: 12),
            Text(message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.textPrimary)),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              TextButton(onPressed: onRetry, child: const Text('Opnieuw proberen')),
            ],
          ],
        ),
      ),
    );
  }
}

void showErrorSnackBar(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(message),
    backgroundColor: AppColors.danger,
  ));
}

void showSuccessSnackBar(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(message),
    backgroundColor: AppColors.primary,
  ));
}
