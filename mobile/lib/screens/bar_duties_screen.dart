import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/widgets.dart';

class BarDutiesScreen extends StatefulWidget {
  const BarDutiesScreen({super.key});

  @override
  State<BarDutiesScreen> createState() => _BarDutiesScreenState();
}

class _BarDutiesScreenState extends State<BarDutiesScreen> {
  final _scrollCtrl = ScrollController();
  List<BarDuty> _duties = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  bool _hasMore = true;

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
        !_loadingMore && _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _duties = [];
        _page = 1;
        _hasMore = true;
        _loading = true;
        _error = null;
      });
    }
    try {
      final result = await context.read<ApiService>().getBarDuties(page: _page);
      setState(() {
        _duties = reset ? result.data : [..._duties, ...result.data];
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

  Map<String, List<BarDuty>> get _groupedByWeek {
    final groups = <String, List<BarDuty>>{};
    for (final d in _duties) {
      final date = DateTime.parse(d.date);
      final monday = date.subtract(Duration(days: date.weekday - 1));
      final sunday = monday.add(const Duration(days: 6));
      final key =
          'Week ${DateFormat('d MMM', 'nl_NL').format(monday)} – ${DateFormat('d MMM yyyy', 'nl_NL').format(sunday)}';
      groups.putIfAbsent(key, () => []).add(d);
    }
    return groups;
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;
    final canCreate = user.isAdmin || user.isBarCommissie;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bardiensten'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: () => _load(reset: true)),
        ],
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              onPressed: () => _showCreateSheet(context),
              child: const Icon(Icons.add),
            )
          : null,
      body: _loading
          ? const LoadingCenter()
          : _error != null
              ? ErrorView(message: _error!, onRetry: () => _load(reset: true))
              : _duties.isEmpty
                  ? const EmptyState(
                      icon: Icons.local_bar,
                      title: 'Geen bardiensten',
                      subtitle: 'Er zijn nog geen bardiensten ingepland.',
                    )
                  : RefreshIndicator(
                      onRefresh: () => _load(reset: true),
                      child: ListView.builder(
                        controller: _scrollCtrl,
                        padding: const EdgeInsets.all(12),
                        itemCount: _groupedByWeek.entries.length + (_loadingMore ? 1 : 0),
                        itemBuilder: (ctx, i) {
                          if (i == _groupedByWeek.entries.length) {
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: LoadingCenter(),
                            );
                          }
                          final entry = _groupedByWeek.entries.elementAt(i);
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Padding(
                                padding: const EdgeInsets.symmetric(vertical: 8),
                                child: Text(
                                  entry.key,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.textMuted,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                              ),
                              ...entry.value.map((d) => _DutyCard(
                                    duty: d,
                                    user: user,
                                    onTap: () => _showDutyActions(context, d),
                                    onEdit: canCreate ? () => _showEditSheet(context, d) : null,
                                    onDelete: canCreate ? () => _deleteDuty(context, d) : null,
                                  )),
                            ],
                          );
                        },
                      ),
                    ),
    );
  }

  void _showDutyActions(BuildContext context, BarDuty duty) {
    final user = context.read<AppState>().user!;
    final canAssign = user.isAdmin || user.isBarCommissie || user.isCoach;

    if (!canAssign) return;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
      builder: (_) => _AssignMembersSheet(duty: duty, onSaved: () => _load(reset: true)),
    );
  }

  void _showCreateSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
      builder: (_) => _CreateDutySheet(onSaved: () => _load(reset: true)),
    );
  }

  void _showEditSheet(BuildContext context, BarDuty duty) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
      builder: (_) => _EditDutySheet(duty: duty, onSaved: () => _load(reset: true)),
    );
  }

  Future<void> _deleteDuty(BuildContext context, BarDuty duty) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Bardienst verwijderen'),
        content: const Text('Weet u zeker dat u deze bardienst wilt verwijderen?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuleren')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Verwijderen', style: TextStyle(color: AppColors.danger)),
          ),
        ],
      ),
    );
    if (confirm != true || !mounted) return;
    final api = context.read<ApiService>();
    try {
      await api.deleteBarDuty(duty.id);
      _load(reset: true);
    } catch (e) {
      if (mounted) showErrorSnackBar(context, e.toString());
    }
  }
}

class _DutyCard extends StatelessWidget {
  final BarDuty duty;
  final AppUser user;
  final VoidCallback onTap;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  const _DutyCard({
    required this.duty,
    required this.user,
    required this.onTap,
    this.onEdit,
    this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final date = DateTime.parse(duty.date);
    final dateFmt = DateFormat('EEEE d MMMM', 'nl_NL').format(date);
    final memberNames = duty.members.isEmpty
        ? 'Niemand ingepland'
        : duty.members.map((m) => m.name).join(', ');

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              dateFmt,
                              style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.textPrimary),
                            ),
                            const SizedBox(width: 8),
                            StatusBadge.shift(duty.shift),
                          ],
                        ),
                        if (duty.team != null) ...[
                          const SizedBox(height: 2),
                          Text(duty.team!.name,
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w500)),
                        ],
                      ],
                    ),
                  ),
                  StatusBadge.barDutyStatus(duty.status),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.person, size: 14, color: AppColors.textMuted),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Ingepland: $memberNames',
                      style: TextStyle(
                        fontSize: 12,
                        color: duty.members.isEmpty ? AppColors.textMuted : AppColors.textPrimary,
                        fontStyle: duty.members.isEmpty ? FontStyle.italic : FontStyle.normal,
                      ),
                    ),
                  ),
                ],
              ),
              if (onEdit != null || onDelete != null) ...[
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (onEdit != null)
                      TextButton.icon(
                        icon: const Icon(Icons.edit, size: 14),
                        label: const Text('Bewerken', style: TextStyle(fontSize: 12)),
                        onPressed: onEdit,
                      ),
                    if (onDelete != null)
                      TextButton.icon(
                        icon: const Icon(Icons.delete_outline, size: 14, color: AppColors.danger),
                        label: const Text('Verwijderen',
                            style: TextStyle(fontSize: 12, color: AppColors.danger)),
                        onPressed: onDelete,
                      ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

// ── Assign Members Sheet ──────────────────────────────────────────────────────

class _AssignMembersSheet extends StatefulWidget {
  final BarDuty duty;
  final VoidCallback onSaved;
  const _AssignMembersSheet({required this.duty, required this.onSaved});

  @override
  State<_AssignMembersSheet> createState() => _AssignMembersSheetState();
}

class _AssignMembersSheetState extends State<_AssignMembersSheet> {
  List<Member> _teamMembers = [];
  Set<String> _selected = {};
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _selected = widget.duty.members.map((m) => m.id).toSet();
    _loadMembers();
  }

  Future<void> _loadMembers() async {
    if (widget.duty.team == null) {
      setState(() => _loading = false);
      return;
    }
    try {
      final members = await context.read<ApiService>().getTeamMembers(widget.duty.team!.id);
      setState(() {
        _teamMembers = members;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (_selected.length > 2) {
      showErrorSnackBar(context, 'Maximaal 2 leden per bardienst.');
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<ApiService>().assignBarDutyMembers(widget.duty.id, _selected.toList());
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
          const Text('Leden toewijzen',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          const Text('Selecteer maximaal 2 leden',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
          const SizedBox(height: 12),
          if (_loading)
            const LoadingCenter()
          else if (_teamMembers.isEmpty)
            const Text('Geen leden gevonden.',
                style: TextStyle(color: AppColors.textMuted))
          else
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 300),
              child: ListView(
                shrinkWrap: true,
                children: _teamMembers
                    .map((m) => CheckboxListTile(
                          dense: true,
                          title: Text(m.name),
                          value: _selected.contains(m.id),
                          activeColor: AppColors.primary,
                          onChanged: (v) {
                            setState(() {
                              if (v == true) {
                                if (_selected.length < 2) _selected.add(m.id);
                              } else {
                                _selected.remove(m.id);
                              }
                            });
                          },
                        ))
                    .toList(),
              ),
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

// ── Create Duty Sheet ─────────────────────────────────────────────────────────

class _CreateDutySheet extends StatefulWidget {
  final VoidCallback onSaved;
  const _CreateDutySheet({required this.onSaved});

  @override
  State<_CreateDutySheet> createState() => _CreateDutySheetState();
}

class _CreateDutySheetState extends State<_CreateDutySheet> {
  DateTime? _date;
  String _shift = 'ochtend';
  String? _teamId;
  final _notesCtrl = TextEditingController();
  bool _saving = false;
  List<Team> _teams = [];

  @override
  void initState() {
    super.initState();
    _loadTeams();
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadTeams() async {
    try {
      final teams = await context.read<ApiService>().getTeams();
      setState(() => _teams = teams);
    } catch (_) {}
  }

  Future<void> _save() async {
    if (_date == null) {
      showErrorSnackBar(context, 'Selecteer een datum.');
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<ApiService>().createBarDuty({
        'date': DateFormat('yyyy-MM-dd').format(_date!),
        'shift': _shift,
        if (_teamId != null) 'team_id': _teamId,
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
          const Text('Bardienst aanmaken',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            icon: const Icon(Icons.calendar_today),
            label: Text(
              _date == null
                  ? 'Selecteer datum'
                  : DateFormat('EEEE d MMMM yyyy', 'nl_NL').format(_date!),
            ),
            onPressed: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: DateTime.now(),
                firstDate: DateTime.now(),
                lastDate: DateTime.now().add(const Duration(days: 365)),
              );
              if (picked != null) setState(() => _date = picked);
            },
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _shift,
            decoration: const InputDecoration(labelText: 'Dienst'),
            items: const [
              DropdownMenuItem(value: 'ochtend', child: Text('Ochtend')),
              DropdownMenuItem(value: 'middag', child: Text('Middag')),
              DropdownMenuItem(value: 'avond', child: Text('Avond')),
            ],
            onChanged: (v) => setState(() => _shift = v!),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _teamId,
            decoration: const InputDecoration(labelText: 'Elftal (optioneel)'),
            items: [
              const DropdownMenuItem(value: null, child: Text('Geen elftal')),
              ..._teams.map((t) => DropdownMenuItem(value: t.id, child: Text(t.name))),
            ],
            onChanged: (v) => setState(() => _teamId = v),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesCtrl,
            decoration: const InputDecoration(labelText: 'Notities (optioneel)'),
            maxLines: 2,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(height: 18, width: 18,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Aanmaken'),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Edit Duty Sheet ───────────────────────────────────────────────────────────

class _EditDutySheet extends StatefulWidget {
  final BarDuty duty;
  final VoidCallback onSaved;
  const _EditDutySheet({required this.duty, required this.onSaved});

  @override
  State<_EditDutySheet> createState() => _EditDutySheetState();
}

class _EditDutySheetState extends State<_EditDutySheet> {
  late String _shift;
  late String _status;
  late TextEditingController _notesCtrl;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _shift = widget.duty.shift;
    _status = widget.duty.status;
    _notesCtrl = TextEditingController(text: widget.duty.notes ?? '');
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await context.read<ApiService>().updateBarDuty(widget.duty.id, {
        'shift': _shift,
        'status': _status,
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
          const Text('Bardienst bewerken',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            value: _shift,
            decoration: const InputDecoration(labelText: 'Dienst'),
            items: const [
              DropdownMenuItem(value: 'ochtend', child: Text('Ochtend')),
              DropdownMenuItem(value: 'middag', child: Text('Middag')),
              DropdownMenuItem(value: 'avond', child: Text('Avond')),
            ],
            onChanged: (v) => setState(() => _shift = v!),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _status,
            decoration: const InputDecoration(labelText: 'Status'),
            items: const [
              DropdownMenuItem(value: 'open', child: Text('Open')),
              DropdownMenuItem(value: 'bevestigd', child: Text('Bevestigd')),
              DropdownMenuItem(value: 'vervuld', child: Text('Vervuld')),
            ],
            onChanged: (v) => setState(() => _status = v!),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesCtrl,
            decoration: const InputDecoration(labelText: 'Notities'),
            maxLines: 2,
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
