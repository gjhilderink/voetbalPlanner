import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/models.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, {this.statusCode});
  @override
  String toString() => message;
}

class ApiService {
  static const _base = 'https://voetbalplanner.nubix.nl/api/v1';

  String? _token;

  void setToken(String? token) => _token = token;

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Future<Map<String, dynamic>> _get(String path,
      {Map<String, String>? params}) async {
    final uri = Uri.parse('$_base$path').replace(queryParameters: params);
    final res = await http.get(uri, headers: _headers);
    return _parse(res);
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body) async {
    final res = await http.post(
      Uri.parse('$_base$path'),
      headers: _headers,
      body: jsonEncode(body),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> _patch(String path, Map<String, dynamic> body) async {
    final res = await http.patch(
      Uri.parse('$_base$path'),
      headers: _headers,
      body: jsonEncode(body),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> _delete(String path) async {
    final res = await http.delete(Uri.parse('$_base$path'), headers: _headers);
    return _parse(res);
  }

  Map<String, dynamic> _parse(http.Response res) {
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (res.statusCode == 401) throw ApiException('Sessie verlopen.', statusCode: 401);
    if (res.statusCode >= 400) {
      throw ApiException(json['message'] ?? 'Er is een fout opgetreden.', statusCode: res.statusCode);
    }
    return json;
  }

  // Auth
  Future<Map<String, dynamic>> login(String email, String password) async {
    final res = await _post('/auth/login', {'email': email, 'password': password});
    return res['data'];
  }

  Future<void> logout() => _post('/auth/logout', {});

  Future<AppUser> me() async {
    final res = await _get('/auth/me');
    return AppUser.fromJson(res['data']);
  }

  // Teams
  Future<List<Team>> getTeams() async {
    final res = await _get('/teams');
    return (res['data'] as List).map((t) => Team.fromJson(t)).toList();
  }

  Future<List<Member>> getTeamMembers(String teamId) async {
    final res = await _get('/teams/$teamId/members');
    return (res['data'] as List).map((m) => Member.fromJson(m)).toList();
  }

  // Members
  Future<PaginatedResult<Member>> getMembers({
    String? teamId,
    String? search,
    int page = 1,
    int perPage = 25,
  }) async {
    final res = await _get('/members', params: {
      if (teamId != null) 'team_id': teamId,
      if (search != null) 'search': search,
      'page': '$page',
      'per_page': '$perPage',
    });
    return PaginatedResult(
      data: (res['data'] as List).map((m) => Member.fromJson(m)).toList(),
      currentPage: res['meta']['current_page'],
      lastPage: res['meta']['last_page'],
      total: res['meta']['total'],
    );
  }

  // Matches
  Future<PaginatedResult<FootballMatch>> getMatches({
    String? teamId,
    String? status,
    bool? upcoming,
    bool? isHome,
    String? dateFrom,
    String? dateTo,
    bool? hasDrivers,
    int page = 1,
    int perPage = 15,
  }) async {
    final res = await _get('/matches', params: {
      if (teamId != null) 'team_id': teamId,
      if (status != null) 'status': status,
      if (upcoming == true) 'upcoming': '1',
      if (isHome != null) 'is_home': isHome ? 'true' : 'false',
      if (dateFrom != null) 'date_from': dateFrom,
      if (dateTo != null) 'date_to': dateTo,
      if (hasDrivers == true) 'has_drivers': '1',
      'page': '$page',
      'per_page': '$perPage',
    });
    return PaginatedResult(
      data: (res['data'] as List).map((m) => FootballMatch.fromJson(m)).toList(),
      currentPage: res['meta']['current_page'],
      lastPage: res['meta']['last_page'],
      total: res['meta']['total'],
    );
  }

  Future<FootballMatch> getMatch(String id) async {
    final res = await _get('/matches/$id');
    return FootballMatch.fromJson(res['data']);
  }

  Future<FootballMatch> updateMatch(String id, Map<String, dynamic> body) async {
    final res = await _patch('/matches/$id', body);
    return FootballMatch.fromJson(res['data']);
  }

  // Lineup
  Future<List<LineupPlayer>> getLineup(String matchId) async {
    final res = await _get('/matches/$matchId/lineup');
    final data = res['data'];
    return (data['players'] as List).map((p) => LineupPlayer.fromJson(p)).toList();
  }

  Future<void> saveLineup(String matchId, List<Map<String, dynamic>> players) async {
    await _post('/matches/$matchId/lineup', {'players': players});
  }

  // Goals
  Future<List<Goal>> getGoals(String matchId) async {
    final res = await _get('/matches/$matchId/goals');
    return (res['data'] as List).map((g) => Goal.fromJson(g)).toList();
  }

  Future<Goal> addGoal(String matchId, Map<String, dynamic> body) async {
    final res = await _post('/matches/$matchId/goals', body);
    return Goal.fromJson(res['data']);
  }

  Future<void> deleteGoal(String matchId, String goalId) async {
    await _delete('/matches/$matchId/goals/$goalId');
  }

  // Bar duties
  Future<PaginatedResult<BarDuty>> getBarDuties({
    String? teamId,
    String? status,
    String? dateFrom,
    String? dateTo,
    int page = 1,
    int perPage = 15,
  }) async {
    final res = await _get('/bar-duties', params: {
      if (teamId != null) 'team_id': teamId,
      if (status != null) 'status': status,
      if (dateFrom != null) 'date_from': dateFrom,
      if (dateTo != null) 'date_to': dateTo,
      'page': '$page',
      'per_page': '$perPage',
    });
    return PaginatedResult(
      data: (res['data'] as List).map((d) => BarDuty.fromJson(d)).toList(),
      currentPage: res['meta']['current_page'],
      lastPage: res['meta']['last_page'],
      total: res['meta']['total'],
    );
  }

  Future<BarDuty> getBarDuty(String id) async {
    final res = await _get('/bar-duties/$id');
    return BarDuty.fromJson(res['data']);
  }

  Future<BarDuty> createBarDuty(Map<String, dynamic> body) async {
    final res = await _post('/bar-duties', body);
    return BarDuty.fromJson(res['data']);
  }

  Future<BarDuty> updateBarDuty(String id, Map<String, dynamic> body) async {
    final res = await _patch('/bar-duties/$id', body);
    return BarDuty.fromJson(res['data']);
  }

  Future<void> deleteBarDuty(String id) async {
    await _delete('/bar-duties/$id');
  }

  Future<BarDuty> assignBarDutyMembers(String id, List<String> memberIds) async {
    final res = await _patch('/bar-duties/$id/members', {'member_ids': memberIds});
    return BarDuty.fromJson(res['data']);
  }
}
