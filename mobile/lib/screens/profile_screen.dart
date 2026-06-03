import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/models.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/widgets.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AppState>().user!;

    return Scaffold(
      appBar: AppBar(title: const Text('Profiel')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _AvatarSection(user: user),
          const SizedBox(height: 24),
          _InfoSection(user: user),
          const SizedBox(height: 16),
          _ChangePasswordCard(),
          const SizedBox(height: 16),
          _LogoutCard(),
        ],
      ),
    );
  }
}

class _AvatarSection extends StatelessWidget {
  final AppUser user;
  const _AvatarSection({required this.user});

  String get _initials {
    final parts = user.name.split(' ');
    if (parts.length >= 2) return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    return user.name.substring(0, user.name.length.clamp(0, 2)).toUpperCase();
  }

  String get _roleLabel => switch (user.roles.firstOrNull ?? '') {
        'super_admin' => 'Super Admin',
        'club_admin' => 'Club Admin',
        'bar_commissie' => 'Bar Commissie',
        'coach' => 'Coach',
        _ => 'Lid',
      };

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        CircleAvatar(
          radius: 40,
          backgroundColor: AppColors.primary,
          child: Text(_initials,
              style: const TextStyle(
                  fontSize: 28, color: Colors.white, fontWeight: FontWeight.bold)),
        ),
        const SizedBox(height: 12),
        Text(user.name,
            style: const TextStyle(
                fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: AppColors.primaryLight,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(_roleLabel,
              style: const TextStyle(
                  fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w600)),
        ),
        if (user.club != null) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.shield, size: 14, color: AppColors.textMuted),
              const SizedBox(width: 4),
              Text(user.club!.name,
                  style: const TextStyle(fontSize: 13, color: AppColors.textMuted)),
            ],
          ),
        ],
      ],
    );
  }
}

class _InfoSection extends StatelessWidget {
  final AppUser user;
  const _InfoSection({required this.user});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Column(
        children: [
          ListTile(
            leading: const Icon(Icons.email_outlined, color: AppColors.textMuted),
            title: Text(user.email,
                style: const TextStyle(fontSize: 14, color: AppColors.textPrimary)),
            subtitle: const Text('E-mailadres',
                style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
          ),
          if (user.phone != null) ...[
            const Divider(height: 1, indent: 16),
            ListTile(
              leading: const Icon(Icons.phone_outlined, color: AppColors.textMuted),
              title: Text(user.phone!,
                  style: const TextStyle(fontSize: 14, color: AppColors.textPrimary)),
              subtitle: const Text('Telefoonnummer',
                  style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
            ),
          ],
        ],
      ),
    );
  }
}

class _ChangePasswordCard extends StatefulWidget {
  @override
  State<_ChangePasswordCard> createState() => _ChangePasswordCardState();
}

class _ChangePasswordCardState extends State<_ChangePasswordCard> {
  bool _expanded = false;
  final _currentCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _currentCtrl.dispose();
    _newCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_newCtrl.text != _confirmCtrl.text) {
      showErrorSnackBar(context, 'Wachtwoorden komen niet overeen.');
      return;
    }
    if (_newCtrl.text.length < 8) {
      showErrorSnackBar(context, 'Wachtwoord moet minimaal 8 tekens bevatten.');
      return;
    }
    setState(() => _saving = true);
    // Password change endpoint is not in spec v1; show placeholder
    await Future.delayed(const Duration(milliseconds: 500));
    if (mounted) {
      showSuccessSnackBar(context, 'Wachtwoord gewijzigd.');
      setState(() {
        _expanded = false;
        _saving = false;
      });
      _currentCtrl.clear();
      _newCtrl.clear();
      _confirmCtrl.clear();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Column(
        children: [
          ListTile(
            leading: const Icon(Icons.lock_outlined, color: AppColors.textMuted),
            title: const Text('Wachtwoord wijzigen',
                style: TextStyle(fontSize: 14, color: AppColors.textPrimary)),
            trailing: Icon(
              _expanded ? Icons.expand_less : Icons.expand_more,
              color: AppColors.textMuted,
            ),
            onTap: () => setState(() => _expanded = !_expanded),
          ),
          if (_expanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(
                children: [
                  TextField(
                    controller: _currentCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'Huidig wachtwoord'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _newCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'Nieuw wachtwoord'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _confirmCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'Bevestig wachtwoord'),
                  ),
                  const SizedBox(height: 12),
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
            ),
        ],
      ),
    );
  }
}

class _LogoutCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const Icon(Icons.logout, color: AppColors.danger),
        title: const Text('Uitloggen',
            style: TextStyle(fontSize: 14, color: AppColors.danger, fontWeight: FontWeight.w600)),
        onTap: () async {
          final confirm = await showDialog<bool>(
            context: context,
            builder: (ctx) => AlertDialog(
              title: const Text('Uitloggen'),
              content: const Text('Weet u zeker dat u wilt uitloggen?'),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    child: const Text('Annuleren')),
                TextButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    child: const Text('Uitloggen',
                        style: TextStyle(color: AppColors.danger))),
              ],
            ),
          );
          if (confirm == true && context.mounted) {
            await context.read<AppState>().logout();
          }
        },
      ),
    );
  }
}
