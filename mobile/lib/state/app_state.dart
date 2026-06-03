import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/models.dart';
import '../services/api_service.dart';

class AppState extends ChangeNotifier {
  final ApiService api;

  AppUser? _user;
  String? _token;
  bool _initialized = false;

  AppState(this.api);

  AppUser? get user => _user;
  String? get token => _token;
  bool get isLoggedIn => _token != null && _user != null;
  bool get initialized => _initialized;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('auth_token');
    final userJson = prefs.getString('current_user');
    if (_token != null && userJson != null) {
      _user = AppUser.fromJson(jsonDecode(userJson));
      api.setToken(_token);
      try {
        _user = await api.me();
        await _persistUser(_user!);
      } catch (e) {
        if (e is ApiException && e.statusCode == 401) {
          await logout();
        }
      }
    }
    _initialized = true;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final data = await api.login(email, password);
    _token = data['token'];
    _user = AppUser.fromJson(data['user']);
    api.setToken(_token);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', _token!);
    await _persistUser(_user!);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.logout();
    } catch (_) {}
    _token = null;
    _user = null;
    api.setToken(null);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('current_user');
    notifyListeners();
  }

  Future<void> _persistUser(AppUser user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('current_user', jsonEncode({
      'id': user.id,
      'name': user.name,
      'email': user.email,
      'phone': user.phone,
      'club_id': user.clubId,
      'club': user.club != null
          ? {'id': user.club!.id, 'name': user.club!.name, 'slug': user.club!.slug, 'logo_path': user.club!.logoPath}
          : null,
      'roles': user.roles,
      'managed_teams': user.managedTeams.map((t) => {'id': t.id, 'name': t.name, 'is_active': t.isActive}).toList(),
    }));
  }
}
