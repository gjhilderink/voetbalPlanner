class Club {
  final String id;
  final String name;
  final String slug;
  final String? logoPath;

  const Club({required this.id, required this.name, required this.slug, this.logoPath});

  factory Club.fromJson(Map<String, dynamic> j) => Club(
        id: j['id'],
        name: j['name'],
        slug: j['slug'],
        logoPath: j['logo_path'],
      );
}

class Team {
  final String id;
  final String name;
  final bool isActive;

  const Team({required this.id, required this.name, this.isActive = true});

  factory Team.fromJson(Map<String, dynamic> j) => Team(
        id: j['id'],
        name: j['name'],
        isActive: j['is_active'] ?? true,
      );
}

class Member {
  final String id;
  final String name;
  final String? email;
  final String? phone;
  final String? role;
  final bool isActive;

  const Member({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    this.role,
    this.isActive = true,
  });

  factory Member.fromJson(Map<String, dynamic> j) => Member(
        id: j['id'],
        name: j['name'],
        email: j['email'],
        phone: j['phone'],
        role: j['role'],
        isActive: j['is_active'] ?? true,
      );
}

class AppUser {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final String? clubId;
  final Club? club;
  final List<String> roles;
  final List<Team> managedTeams;

  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.clubId,
    this.club,
    this.roles = const [],
    this.managedTeams = const [],
  });

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'],
        name: j['name'],
        email: j['email'],
        phone: j['phone'],
        clubId: j['club_id'],
        club: j['club'] != null ? Club.fromJson(j['club']) : null,
        roles: List<String>.from(j['roles'] ?? []),
        managedTeams: (j['managed_teams'] as List? ?? [])
            .map((t) => Team.fromJson(t))
            .toList(),
      );

  bool hasRole(String role) => roles.contains(role);
  bool get isAdmin => hasRole('super_admin') || hasRole('club_admin');
  bool get isBarCommissie => hasRole('bar_commissie');
  bool get isCoach => hasRole('coach');
}

class FootballMatch {
  final String id;
  final DateTime matchDatetime;
  final String? arrivalTime;
  final String opponent;
  final String location;
  final bool isHome;
  final String status;
  final int? scoreHome;
  final int? scoreAway;
  final String? notes;
  final Team? team;
  final Member? coach;
  final Member? fruitHero;
  final List<Member> drivers;
  final List<LineupPlayer> lineupPlayers;
  final List<Goal> goals;

  const FootballMatch({
    required this.id,
    required this.matchDatetime,
    this.arrivalTime,
    required this.opponent,
    required this.location,
    required this.isHome,
    required this.status,
    this.scoreHome,
    this.scoreAway,
    this.notes,
    this.team,
    this.coach,
    this.fruitHero,
    this.drivers = const [],
    this.lineupPlayers = const [],
    this.goals = const [],
  });

  factory FootballMatch.fromJson(Map<String, dynamic> j) => FootballMatch(
        id: j['id'],
        matchDatetime: DateTime.parse(j['match_datetime']),
        arrivalTime: j['arrival_time'],
        opponent: j['opponent'],
        location: j['location'],
        isHome: j['is_home'] ?? false,
        status: j['status'] ?? 'scheduled',
        scoreHome: j['score_home'],
        scoreAway: j['score_away'],
        notes: j['notes'],
        team: j['team'] != null ? Team.fromJson(j['team']) : null,
        coach: j['coach'] != null ? Member.fromJson(j['coach']) : null,
        fruitHero: j['fruit_hero'] != null ? Member.fromJson(j['fruit_hero']) : null,
        drivers: (j['drivers'] as List? ?? []).map((m) => Member.fromJson(m)).toList(),
        lineupPlayers: (j['lineup']?['players'] as List? ?? [])
            .map((p) => LineupPlayer.fromJson(p))
            .toList(),
        goals: (j['goals'] as List? ?? []).map((g) => Goal.fromJson(g)).toList(),
      );
}

class LineupPlayer {
  final String id;
  final String position;
  final int? jerseyNumber;
  final bool isStarter;
  final bool isCaptain;
  final Member member;

  const LineupPlayer({
    required this.id,
    required this.position,
    this.jerseyNumber,
    required this.isStarter,
    required this.isCaptain,
    required this.member,
  });

  factory LineupPlayer.fromJson(Map<String, dynamic> j) => LineupPlayer(
        id: j['id'],
        position: j['position'],
        jerseyNumber: j['jersey_number'],
        isStarter: j['is_starter'] ?? false,
        isCaptain: j['is_captain'] ?? false,
        member: Member.fromJson(j['member']),
      );
}

class Goal {
  final String id;
  final int minute;
  final String type;
  final Member scorer;
  final Member? assist;

  const Goal({
    required this.id,
    required this.minute,
    required this.type,
    required this.scorer,
    this.assist,
  });

  factory Goal.fromJson(Map<String, dynamic> j) => Goal(
        id: j['id'],
        minute: j['minute'] ?? 0,
        type: j['type'] ?? 'normal',
        scorer: Member.fromJson(j['scorer']),
        assist: j['assist'] != null ? Member.fromJson(j['assist']) : null,
      );
}

class BarDuty {
  final String id;
  final String date;
  final String shift;
  final String status;
  final String? notes;
  final Team? team;
  final List<Member> members;

  const BarDuty({
    required this.id,
    required this.date,
    required this.shift,
    required this.status,
    this.notes,
    this.team,
    this.members = const [],
  });

  factory BarDuty.fromJson(Map<String, dynamic> j) => BarDuty(
        id: j['id'],
        date: j['date'],
        shift: j['shift'],
        status: j['status'] ?? 'open',
        notes: j['notes'],
        team: j['team'] != null ? Team.fromJson(j['team']) : null,
        members: (j['members'] as List? ?? []).map((m) => Member.fromJson(m)).toList(),
      );
}

class PaginatedResult<T> {
  final List<T> data;
  final int currentPage;
  final int lastPage;
  final int total;

  const PaginatedResult({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  bool get hasMore => currentPage < lastPage;
}
