import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/widgets.dart';

class DriveScheduleScreen extends StatefulWidget {
  const DriveScheduleScreen({super.key});

  @override
  State<DriveScheduleScreen> createState() => _DriveScheduleScreenState();
}

class _DriveScheduleScreenState extends State<DriveScheduleScreen> {
  List<FootballMatch> _matches = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiService>();
      final result = await api.getMatches(isHome: false, hasDrivers: true, perPage: 50);
      setState(() {
        _matches = result.data;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Map<String, List<FootballMatch>> get _grouped {
    final groups = <String, List<FootballMatch>>{};
    for (final m in _matches) {
      final team = m.team?.name ?? 'Overig';
      groups.putIfAbsent(team, () => []).add(m);
    }
    return groups;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rijschema'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const LoadingCenter()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _matches.isEmpty
                  ? const EmptyState(
                      icon: Icons.directions_car,
                      title: 'Geen ritten ingepland',
                      subtitle: 'Er zijn geen uitwedstrijden met rijders gevonden.',
                    )
                  : RefreshIndicator(
                      onRefresh: () => _load(),
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: [
                          for (final entry in _grouped.entries) ...[
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: Text(
                                entry.key,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.textMuted,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ),
                            ...entry.value.map((m) => _DriveCard(match: m)),
                          ],
                        ],
                      ),
                    ),
    );
  }
}

class _DriveCard extends StatelessWidget {
  final FootballMatch match;
  const _DriveCard({required this.match});

  @override
  Widget build(BuildContext context) {
    final dayFmt = DateFormat('EEEE d MMMM yyyy', 'nl_NL');
    final timeFmt = DateFormat('HH:mm');

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.calendar_today, size: 14, color: AppColors.textMuted),
                const SizedBox(width: 6),
                Text(
                  '${dayFmt.format(match.matchDatetime)}  ${timeFmt.format(match.matchDatetime)}',
                  style: const TextStyle(
                      fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.sports_soccer, size: 14, color: AppColors.primary),
                const SizedBox(width: 6),
                Text(
                  'vs ${match.opponent}',
                  style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
                ),
              ],
            ),
            if (match.arrivalTime != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(Icons.access_time, size: 14, color: AppColors.textMuted),
                  const SizedBox(width: 6),
                  Text(
                    'Verzamelen: ${match.arrivalTime}',
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ],
            if (match.drivers.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Divider(height: 1, color: AppColors.border),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.drive_eta, size: 14, color: AppColors.textMuted),
                  const SizedBox(width: 6),
                  const Text('Rijders:',
                      style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Wrap(
                      spacing: 8,
                      children: match.drivers
                          .map((d) => GestureDetector(
                                onTap: d.phone != null
                                    ? () => launchUrl(Uri.parse('tel:${d.phone}'))
                                    : null,
                                child: Text(
                                  d.name,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: d.phone != null ? AppColors.primary : AppColors.textPrimary,
                                    decoration: d.phone != null ? TextDecoration.underline : null,
                                  ),
                                ),
                              ))
                          .toList(),
                    ),
                  ),
                ],
              ),
            ],
            if (match.coach != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(Icons.person, size: 14, color: AppColors.textMuted),
                  const SizedBox(width: 6),
                  Text(
                    'Coach: ${match.coach!.name}',
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
