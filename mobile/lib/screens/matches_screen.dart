import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/widgets.dart';

class MatchesScreen extends StatefulWidget {
  const MatchesScreen({super.key});

  @override
  State<MatchesScreen> createState() => _MatchesScreenState();
}

class _MatchesScreenState extends State<MatchesScreen> {
  final _scrollCtrl = ScrollController();
  List<FootballMatch> _matches = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  bool _hasMore = true;

  String? _selectedTeamId;
  String? _selectedStatus;

  @override
  void initState() {
    super.initState();
    _load();
    _scrollCtrl.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200 &&
        !_loadingMore &&
        _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _matches = [];
        _page = 1;
        _hasMore = true;
        _loading = true;
        _error = null;
      });
    }
    try {
      final api = context.read<ApiService>();
      final user = context.read<AppState>().user!;
      final result = await api.getMatches(
        teamId: _selectedTeamId ??
            (user.isAdmin ? null : user.managedTeams.firstOrNull?.id),
        status: _selectedStatus,
        page: _page,
      );
      setState(() {
        _matches = reset ? result.data : [..._matches, ...result.data];
        _hasMore = result.hasMore;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    _page++;
    await _load();
    setState(() => _loadingMore = false);
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Wedstrijden'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _load(reset: true),
          ),
        ],
      ),
      body: Column(
        children: [
          _FilterBar(
            user: user,
            selectedTeamId: _selectedTeamId,
            selectedStatus: _selectedStatus,
            onTeamChanged: (id) {
              setState(() => _selectedTeamId = id);
              _load(reset: true);
            },
            onStatusChanged: (s) {
              setState(() => _selectedStatus = s);
              _load(reset: true);
            },
          ),
          Expanded(
            child: _loading
                ? const LoadingCenter()
                : _error != null
                    ? ErrorView(message: _error!, onRetry: () => _load(reset: true))
                    : _matches.isEmpty
                        ? const EmptyState(
                            icon: Icons.sports_soccer,
                            title: 'Geen wedstrijden',
                            subtitle: 'Er zijn geen wedstrijden gevonden.',
                          )
                        : RefreshIndicator(
                            onRefresh: () => _load(reset: true),
                            child: ListView.builder(
                              controller: _scrollCtrl,
                              padding: const EdgeInsets.all(12),
                              itemCount: _matches.length + (_loadingMore ? 1 : 0),
                              itemBuilder: (ctx, i) {
                                if (i == _matches.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: LoadingCenter(),
                                  );
                                }
                                return _MatchCard(
                                  match: _matches[i],
                                  onTap: () => context.push('/matches/${_matches[i].id}'),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  final AppUser user;
  final String? selectedTeamId;
  final String? selectedStatus;
  final ValueChanged<String?> onTeamChanged;
  final ValueChanged<String?> onStatusChanged;

  const _FilterBar({
    required this.user,
    this.selectedTeamId,
    this.selectedStatus,
    required this.onTeamChanged,
    required this.onStatusChanged,
  });

  @override
  Widget build(BuildContext context) {
    final statuses = [
      (null, 'Alle'),
      ('scheduled', 'Gepland'),
      ('played', 'Gespeeld'),
      ('postponed', 'Uitgesteld'),
      ('cancelled', 'Geannuleerd'),
    ];

    return Container(
      color: AppColors.white,
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        children: [
          if (user.managedTeams.length > 1)
            SizedBox(
              height: 40,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: [
                  _chip<String>('Alle teams', null, selectedTeamId, onTeamChanged),
                  ...user.managedTeams.map((t) => _chip<String>(t.name, t.id, selectedTeamId, onTeamChanged)),
                ],
              ),
            ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: statuses
                  .map((s) => _chip(s.$2, s.$1, selectedStatus, onStatusChanged))
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip<T>(String label, T? value, T? selected, ValueChanged<T?> onChanged) {
    final active = value == selected;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(label, style: TextStyle(fontSize: 12, color: active ? Colors.white : AppColors.textPrimary)),
        selected: active,
        selectedColor: AppColors.primary,
        backgroundColor: AppColors.white,
        checkmarkColor: Colors.white,
        side: BorderSide(color: active ? AppColors.primary : AppColors.border),
        onSelected: (_) => onChanged(active ? null : value),
      ),
    );
  }
}

class _MatchCard extends StatelessWidget {
  final FootballMatch match;
  final VoidCallback onTap;

  const _MatchCard({required this.match, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final date = DateFormat('EEE d MMM', 'nl_NL').format(match.matchDatetime);
    final time = DateFormat('HH:mm').format(match.matchDatetime);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(date,
                      style: const TextStyle(
                          fontSize: 12, color: AppColors.textMuted, fontWeight: FontWeight.w500)),
                  Text(time,
                      style: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (match.team != null)
                      Text(match.team!.name,
                          style: const TextStyle(
                              fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w600)),
                    Text(
                      match.opponent,
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Icon(
                          match.isHome ? Icons.home : Icons.directions_car,
                          size: 12,
                          color: AppColors.textMuted,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            match.location,
                            style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  StatusBadge.matchStatus(match.status),
                  if (match.scoreHome != null && match.scoreAway != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      '${match.scoreHome} - ${match.scoreAway}',
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
