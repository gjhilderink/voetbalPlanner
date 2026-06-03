import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/widgets.dart';

class MatchDetailScreen extends StatefulWidget {
  final String matchId;
  const MatchDetailScreen({super.key, required this.matchId});

  @override
  State<MatchDetailScreen> createState() => _MatchDetailScreenState();
}

class _MatchDetailScreenState extends State<MatchDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  FootballMatch? _match;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final match = await context.read<ApiService>().getMatch(widget.matchId);
      setState(() {
        _match = match;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_match?.opponent ?? 'Wedstrijd'),
        bottom: TabBar(
          controller: _tabs,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Info'),
            Tab(text: 'Opstelling'),
            Tab(text: 'Doelpunten'),
          ],
        ),
      ),
      body: _loading
          ? const LoadingCenter()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : TabBarView(
                  controller: _tabs,
                  children: [
                    _InfoTab(match: _match!, onRefresh: _load),
                    _LineupTab(match: _match!, onRefresh: _load),
                    _GoalsTab(match: _match!, onRefresh: _load),
                  ],
                ),
    );
  }
}

// ── Info Tab ──────────────────────────────────────────────────────────────────

class _InfoTab extends StatelessWidget {
  final FootballMatch match;
  final VoidCallback onRefresh;

  const _InfoTab({required this.match, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;
    final canEdit = user.isAdmin || user.isCoach;
    final date = DateFormat('EEEE d MMMM yyyy', 'nl_NL').format(match.matchDatetime);
    final time = DateFormat('HH:mm').format(match.matchDatetime);

    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _InfoCard(children: [
            _Row(Icons.calendar_today, '$date  $time'),
            _Row(match.isHome ? Icons.home : Icons.directions_car,
                match.isHome ? 'Thuiswedstrijd' : 'Uitwedstrijd'),
            _Row(Icons.location_on, match.location),
            if (match.arrivalTime != null)
              _Row(Icons.access_time, 'Verzamelen: ${match.arrivalTime}'),
          ]),
          const SizedBox(height: 12),
          _InfoCard(children: [
            if (match.coach != null) _Row(Icons.person, 'Coach: ${match.coach!.name}'),
            if (match.fruitHero != null)
              _Row(Icons.apple, 'Fruitheld: ${match.fruitHero!.name}'),
          ]),
          if (match.drivers.isNotEmpty) ...[
            const SizedBox(height: 12),
            _SectionHeader('Rijders'),
            _InfoCard(
              children: match.drivers
                  .map((d) => _Row(
                        Icons.drive_eta,
                        d.name,
                        trailing: d.phone != null
                            ? IconButton(
                                icon: const Icon(Icons.phone, size: 18, color: AppColors.primary),
                                onPressed: () => launchUrl(Uri.parse('tel:${d.phone}')),
                              )
                            : null,
                      ))
                  .toList(),
            ),
          ],
          if (match.notes != null && match.notes!.isNotEmpty) ...[
            const SizedBox(height: 12),
            _SectionHeader('Notities'),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(match.notes!,
                    style: const TextStyle(color: AppColors.textPrimary)),
              ),
            ),
          ],
          if (canEdit) ...[
            const SizedBox(height: 16),
            OutlinedButton.icon(
              icon: const Icon(Icons.edit),
              label: const Text('Bewerken'),
              onPressed: () => _showEditSheet(context),
            ),
          ],
        ],
      ),
    );
  }

  void _showEditSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
      builder: (_) => _EditMatchSheet(match: match, onSaved: onRefresh),
    );
  }
}

class _EditMatchSheet extends StatefulWidget {
  final FootballMatch match;
  final VoidCallback onSaved;
  const _EditMatchSheet({required this.match, required this.onSaved});

  @override
  State<_EditMatchSheet> createState() => _EditMatchSheetState();
}

class _EditMatchSheetState extends State<_EditMatchSheet> {
  late TextEditingController _arrivalCtrl;
  late TextEditingController _notesCtrl;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _arrivalCtrl = TextEditingController(text: widget.match.arrivalTime ?? '');
    _notesCtrl = TextEditingController(text: widget.match.notes ?? '');
  }

  @override
  void dispose() {
    _arrivalCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await context.read<ApiService>().updateMatch(widget.match.id, {
        if (_arrivalCtrl.text.isNotEmpty) 'arrival_time': _arrivalCtrl.text,
        if (_notesCtrl.text.isNotEmpty) 'notes': _notesCtrl.text,
      });
      if (mounted) Navigator.pop(context);
      widget.onSaved();
    } catch (e) {
      if (mounted) showErrorSnackBar(context, e.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Wedstrijd bewerken',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          TextField(
            controller: _arrivalCtrl,
            decoration: const InputDecoration(labelText: 'Verzameltijd (HH:mm)'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesCtrl,
            decoration: const InputDecoration(labelText: 'Notities'),
            maxLines: 3,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(height: 18, width: 18,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Opslaan'),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Lineup Tab ────────────────────────────────────────────────────────────────

class _LineupTab extends StatelessWidget {
  final FootballMatch match;
  final VoidCallback onRefresh;

  const _LineupTab({required this.match, required this.onRefresh});

  static const _positionGroups = [
    ('Keeper', ['GK']),
    ('Verdediging', ['LB', 'CB', 'RB']),
    ('Middenveld', ['LM', 'CM', 'DM', 'RM', 'CAM']),
    ('Aanval', ['LW', 'ST', 'RW', 'CF']),
    ('Wisselspelers', <String>[]),
  ];

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;
    final canEdit = user.isAdmin || user.isCoach;
    final starters = match.lineupPlayers.where((p) => p.isStarter).toList();
    final bench = match.lineupPlayers.where((p) => !p.isStarter).toList();

    if (match.lineupPlayers.isEmpty) {
      return EmptyState(
        icon: Icons.group,
        title: 'Geen opstelling',
        subtitle: canEdit ? 'Tik op bewerken om een opstelling in te stellen.' : null,
      );
    }

    List<Widget> buildGroup(String label, List<String> positions) {
      final players = positions.isEmpty
          ? bench
          : starters.where((p) => positions.contains(p.position)).toList();
      if (players.isEmpty) return [];
      return [
        _SectionHeader(label),
        ...players.map((p) => _PlayerTile(player: p)),
        const SizedBox(height: 8),
      ];
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final group in _positionGroups) ...buildGroup(group.$1, group.$2),
        if (canEdit)
          OutlinedButton.icon(
            icon: const Icon(Icons.edit),
            label: const Text('Opstelling bewerken'),
            onPressed: () {},
          ),
      ],
    );
  }
}

class _PlayerTile extends StatelessWidget {
  final LineupPlayer player;
  const _PlayerTile({required this.player});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        dense: true,
        leading: CircleAvatar(
          backgroundColor: AppColors.primaryLight,
          radius: 16,
          child: Text(
            player.jerseyNumber?.toString() ?? player.position,
            style: const TextStyle(
                fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.bold),
          ),
        ),
        title: Row(
          children: [
            Text(player.member.name,
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
            if (player.isCaptain) ...[
              const SizedBox(width: 6),
              const Icon(Icons.star, size: 14, color: AppColors.warning),
            ],
          ],
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
          decoration: BoxDecoration(
            color: AppColors.primaryLight,
            borderRadius: BorderRadius.circular(4),
          ),
          child: Text(player.position,
              style: const TextStyle(
                  fontSize: 10, color: AppColors.primary, fontWeight: FontWeight.bold)),
        ),
      ),
    );
  }
}

// ── Goals Tab ─────────────────────────────────────────────────────────────────

class _GoalsTab extends StatelessWidget {
  final FootballMatch match;
  final VoidCallback onRefresh;

  const _GoalsTab({required this.match, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;
    final canEdit = user.isAdmin || user.isCoach;

    if (match.goals.isEmpty) {
      return EmptyState(
        icon: Icons.sports_soccer,
        title: 'Geen doelpunten',
        subtitle: canEdit ? 'Tik op + om een doelpunt toe te voegen.' : null,
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ...match.goals.map((g) => _GoalTile(
              goal: g,
              canDelete: canEdit,
              onDelete: () async {
                await context.read<ApiService>().deleteGoal(match.id, g.id);
                onRefresh();
              },
            )),
        if (canEdit) ...[
          const SizedBox(height: 16),
          ElevatedButton.icon(
            icon: const Icon(Icons.add),
            label: const Text('Doelpunt toevoegen'),
            onPressed: () => _showAddGoalSheet(context),
          ),
        ],
      ],
    );
  }

  void _showAddGoalSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
      builder: (_) => _AddGoalSheet(match: match, onSaved: onRefresh),
    );
  }
}

class _GoalTile extends StatelessWidget {
  final Goal goal;
  final bool canDelete;
  final VoidCallback onDelete;

  const _GoalTile({required this.goal, required this.canDelete, required this.onDelete});

  String get _typeLabel => switch (goal.type) {
        'penalty' => 'Penalty',
        'own_goal' => 'Eigen doelpunt',
        _ => 'Doelpunt',
      };

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppColors.primaryLight,
          child: Text('${goal.minute}\'',
              style: const TextStyle(
                  fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.bold)),
        ),
        title: Text(goal.scorer.name,
            style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(
          goal.assist != null
              ? '$_typeLabel · Assist: ${goal.assist!.name}'
              : _typeLabel,
          style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
        ),
        trailing: canDelete
            ? IconButton(
                icon: const Icon(Icons.delete_outline, color: AppColors.danger, size: 20),
                onPressed: onDelete,
              )
            : null,
      ),
    );
  }
}

class _AddGoalSheet extends StatefulWidget {
  final FootballMatch match;
  final VoidCallback onSaved;
  const _AddGoalSheet({required this.match, required this.onSaved});

  @override
  State<_AddGoalSheet> createState() => _AddGoalSheetState();
}

class _AddGoalSheetState extends State<_AddGoalSheet> {
  final _minuteCtrl = TextEditingController();
  String _type = 'normal';
  String? _scorerId;
  String? _assistId;
  bool _saving = false;

  @override
  void dispose() {
    _minuteCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_scorerId == null) {
      showErrorSnackBar(context, 'Selecteer een schutter.');
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<ApiService>().addGoal(widget.match.id, {
        'minute': int.tryParse(_minuteCtrl.text) ?? 0,
        'type': _type,
        'scorer_id': _scorerId,
        if (_assistId != null) 'assist_id': _assistId,
      });
      if (mounted) Navigator.pop(context);
      widget.onSaved();
    } catch (e) {
      if (mounted) showErrorSnackBar(context, e.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final players = widget.match.lineupPlayers.map((p) => p.member).toList();

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Doelpunt toevoegen',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          TextField(
            controller: _minuteCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Minuut'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _type,
            decoration: const InputDecoration(labelText: 'Type'),
            items: const [
              DropdownMenuItem(value: 'normal', child: Text('Doelpunt')),
              DropdownMenuItem(value: 'penalty', child: Text('Penalty')),
              DropdownMenuItem(value: 'own_goal', child: Text('Eigen doelpunt')),
            ],
            onChanged: (v) => setState(() => _type = v!),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _scorerId,
            decoration: const InputDecoration(labelText: 'Schutter'),
            items: players
                .map((m) => DropdownMenuItem(value: m.id, child: Text(m.name)))
                .toList(),
            onChanged: (v) => setState(() => _scorerId = v),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _assistId,
            decoration: const InputDecoration(labelText: 'Assist (optioneel)'),
            items: [
              const DropdownMenuItem(value: null, child: Text('Geen')),
              ...players.map((m) => DropdownMenuItem(value: m.id, child: Text(m.name))),
            ],
            onChanged: (v) => setState(() => _assistId = v),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(height: 18, width: 18,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Opslaan'),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Shared helpers ────────────────────────────────────────────────────────────

class _InfoCard extends StatelessWidget {
  final List<Widget> children;
  const _InfoCard({required this.children});

  @override
  Widget build(BuildContext context) {
    if (children.isEmpty) return const SizedBox.shrink();
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(children: children),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  final IconData icon;
  final String label;
  final Widget? trailing;

  const _Row(this.icon, this.label, {this.trailing});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      leading: Icon(icon, size: 18, color: AppColors.textMuted),
      title: Text(label, style: const TextStyle(fontSize: 14, color: AppColors.textPrimary)),
      trailing: trailing,
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  const _SectionHeader(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(title,
          style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: AppColors.textMuted,
              letterSpacing: 0.5)),
    );
  }
}
