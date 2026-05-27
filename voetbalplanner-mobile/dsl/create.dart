library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  try {
    await flutterFlowAI(
      buildVoetbalPlannerApp,
      apiKey: options.apiKey,
      baseUrl: options.baseUrl,
      projectName: options.projectName,
      projectId: options.projectId,
      findOrCreate: options.findOrCreate,
      allowNewProject: options.allowNewProject,
      dryRun: options.dryRun,
      commitMessage: options.commitMessage,
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void buildVoetbalPlannerApp(App app) {
  // ─── Theme ─────────────────────────────────────────────────────────────────
  app.themeColor('primary', 0xFF16a34a);
  app.themeColor('primaryBackground', 0xFFf9fafb);
  app.themeColor('secondaryBackground', 0xFFffffff);
  app.themeColor('primaryText', 0xFF1a1a1a);
  app.themeColor('secondaryText', 0xFF6b7280);
  app.themeColor('error', 0xFFb91c1c);
  app.themeColor('warning', 0xFFa16207);
  app.primaryFont('Roboto');

  // ─── Global app state ─────────────────────────────────────────────────────
  app.state('authToken', string, persisted: true);
  app.state('userName', string, persisted: true);
  app.state('userEmail', string, persisted: true);
  app.state('userRoles', listOf(string), persisted: true);
  app.state('clubName', string, persisted: true);

  // ─── Data structs ─────────────────────────────────────────────────────────
  final footMatch = app.struct('FootMatch', {
    'id': string,
    'opponent': string,
    'location': string,
    'matchDatetime': string,
    'arrivalTime': string,
    'isHome': bool_,
    'status': string,
    'scoreHome': int_,
    'scoreAway': int_,
    'teamName': string,
    'coachName': string,
    'fruitHeroName': string,
    'notes': string,
  });

  final lineupPlayer = app.struct('LineupPlayer', {
    'id': string,
    'memberName': string,
    'position': string,
    'jerseyNumber': string,
    'isStarter': bool_,
    'isCaptain': bool_,
  });

  final matchGoal = app.struct('MatchGoal', {
    'id': string,
    'minute': int_,
    'type': string,
    'scorerName': string,
    'assistName': string,
  });

  final barDuty = app.struct('BarDuty', {
    'id': string,
    'date': string,
    'shift': string,
    'status': string,
    'teamName': string,
    'members': string,
    'notes': string,
  });

  // Auth response structs — nested to match API response shape
  final clubRef = app.struct('ClubRef', {'id': string, 'name': string});
  final userRef = app.struct('UserRef', {
    'id': string,
    'name': string,
    'email': string,
    'roles': listOf(string),
    'club': clubRef,
  });
  final loginData = app.struct('LoginData', {
    'token': string,
    'user': userRef,
  });
  final loginResponse = app.struct('LoginResponse', {
    'success': bool_,
    'data': loginData,
    'message': string,
  });

  // ─── API group ────────────────────────────────────────────────────────────
  final loginEp = Endpoint.post(
    'Login',
    '/auth/login',
    variables: {'email': string, 'password': string},
    body: {'email': '<email>', 'password': '<password>'},
    response: loginResponse,
  );

  final getMeEp = Endpoint.get(
    'GetMe',
    '/auth/me',
    variables: {'token': string},
    headers: {'Authorization': 'Bearer <token>'},
  );

  final logoutEp = Endpoint.post(
    'Logout',
    '/auth/logout',
    variables: {'token': string},
    headers: {'Authorization': 'Bearer <token>'},
    body: {},
  );

  final getMatchesEp = Endpoint.get(
    'GetMatches',
    '/matches?per_page=15&page=[page]',
    variables: {'token': string, 'page': int_},
    headers: {'Authorization': 'Bearer <token>'},
    response: listOf(footMatch),
  );

  final getMatchEp = Endpoint.get(
    'GetMatch',
    '/matches/[id]',
    variables: {'token': string, 'id': string},
    headers: {'Authorization': 'Bearer <token>'},
    response: footMatch,
  );

  final getLineupEp = Endpoint.get(
    'GetLineup',
    '/matches/[id]/lineup',
    variables: {'token': string, 'id': string},
    headers: {'Authorization': 'Bearer <token>'},
    response: listOf(lineupPlayer),
  );

  final getGoalsEp = Endpoint.get(
    'GetGoals',
    '/matches/[id]/goals',
    variables: {'token': string, 'id': string},
    headers: {'Authorization': 'Bearer <token>'},
    response: listOf(matchGoal),
  );

  final getDriveEp = Endpoint.get(
    'GetDriveSchedule',
    '/matches?is_home=false&has_drivers=1&per_page=50',
    variables: {'token': string},
    headers: {'Authorization': 'Bearer <token>'},
    response: listOf(footMatch),
  );

  final getBarDutiesEp = Endpoint.get(
    'GetBarDuties',
    '/bar-duties?per_page=15&page=[page]',
    variables: {'token': string, 'page': int_},
    headers: {'Authorization': 'Bearer <token>'},
    response: listOf(barDuty),
  );

  app.apiGroup(
    'VoetbalPlannerAPI',
    baseUrl: 'https://voetbalplanner.nubix.nl/api/v1',
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
    endpoints: [
      loginEp,
      getMeEp,
      logoutEp,
      getMatchesEp,
      getMatchEp,
      getLineupEp,
      getGoalsEp,
      getDriveEp,
      getBarDutiesEp,
    ],
  );

  // ─── Reusable components ──────────────────────────────────────────────────

  final statusBadge = app.component(
    'StatusBadge',
    params: {'label': string},
    body: Container(
      padding: EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      borderRadius: 12,
      color: Colors.primary,
      child: Text(
        Param('label'),
        style: Styles.bodySmall,
        color: Colors.secondaryBackground,
      ),
    ),
  );

  final matchCard = app.component(
    'MatchCard',
    params: {
      'opponent': string,
      'location': string,
      'matchDate': string,
      'status': string,
      'onTapAction': action,
    },
    body: Container(
      padding: 12,
      borderRadius: 8,
      color: Colors.secondaryBackground,
      borderColor: Colors.hex(0xFFe5e7eb),
      borderWidth: 1,
      onTap: ParamAction('onTapAction'),
      child: Row(
        mainAxis: MainAxis.spaceBetween,
        children: [
          Expanded(
            Column(
              crossAxis: CrossAxis.start,
              spacing: 2,
              children: [
                Text(
                  Param('matchDate'),
                  style: Styles.bodySmall,
                  color: Colors.secondaryText,
                ),
                Text(
                  Param('opponent'),
                  style: Styles.labelMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  Param('location'),
                  style: Styles.bodySmall,
                  color: Colors.secondaryText,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          statusBadge(label: Param('status')),
        ],
      ),
    ),
  );

  final barDutyCard = app.component(
    'BarDutyCard',
    params: {
      'date': string,
      'shift': string,
      'teamName': string,
      'members': string,
      'status': string,
      'onTapAction': action,
    },
    body: Container(
      padding: 12,
      borderRadius: 8,
      color: Colors.secondaryBackground,
      borderColor: Colors.hex(0xFFe5e7eb),
      borderWidth: 1,
      onTap: ParamAction('onTapAction'),
      child: Column(
        crossAxis: CrossAxis.start,
        spacing: 6,
        children: [
          Row(
            mainAxis: MainAxis.spaceBetween,
            children: [
              Row(
                spacing: 8,
                children: [
                  Text(Param('date'), style: Styles.labelMedium),
                  statusBadge(label: Param('shift')),
                ],
              ),
              statusBadge(label: Param('status')),
            ],
          ),
          Text(
            Param('teamName'),
            style: Styles.bodySmall,
            color: Colors.primary,
          ),
          Text(
            Param('members'),
            style: Styles.bodySmall,
            color: Colors.secondaryText,
          ),
        ],
      ),
    ),
  );

  // ─── Login page ───────────────────────────────────────────────────────────
  final loginPage = app.page(
    'LoginPage',
    route: '/login',
    isInitial: true,
    state: {
      'emailInput': string.withDefault(''),
      'passwordInput': string.withDefault(''),
      'isLoading': bool_.withDefault(false),
    },
    body: Scaffold(
      body: Column(
        mainAxis: MainAxis.center,
        crossAxis: CrossAxis.center,
        padding: 32,
        spacing: 16,
        children: [
          Container(
            width: 80,
            height: 80,
            borderRadius: 16,
            color: Colors.primary,
            child: Icon(
              'sports_soccer',
              color: Colors.secondaryBackground,
              size: 48,
            ),
          ),
          Text(
            'VoetbalPlanner',
            style: Styles.headlineMedium,
            color: Colors.primaryText,
          ),
          Text(
            'Inloggen bij uw club',
            style: Styles.bodyMedium,
            color: Colors.secondaryText,
          ),
          Spacer(height: 8),
          TextField(
            label: 'E-mailadres',
            onChanged: SetState('emailInput', TextValue()),
          ),
          TextField(
            label: 'Wachtwoord',
            obscureText: true,
            onChanged: SetState('passwordInput', TextValue()),
          ),
          Spacer(height: 8),
          Button(
            'Inloggen',
            color: Colors.primary,
            textColor: Colors.secondaryBackground,
            borderRadius: 8,
            padding: EdgeInsets.symmetric(vertical: 14),
            onTap: [
              SetState('isLoading', true),
              ApiCall(
                loginEp,
                outputAs: 'loginResult',
                params: {
                  'email': State('emailInput'),
                  'password': State('passwordInput'),
                },
                onSuccess: (res) => [
                  UpdateAppState.set('authToken', res['data']['token']),
                  UpdateAppState.set('userName', res['data']['user']['name']),
                  UpdateAppState.set('userEmail', res['data']['user']['email']),
                  UpdateAppState.set('userRoles', res['data']['user']['roles']),
                  UpdateAppState.set(
                    'clubName',
                    res['data']['user']['club']['name'],
                  ),
                  SetState('isLoading', false),
                  Navigate('WedstrijdenPage'),
                ],
                onFailure: [
                  SetState('isLoading', false),
                  Snackbar('Inloggen mislukt. Controleer uw gegevens.'),
                ],
              ),
            ],
          ),
        ],
      ),
    ),
  );

  // ─── Wedstrijden (Matches list) ───────────────────────────────────────────
  final wedstrijdenPage = app.page(
    'WedstrijdenPage',
    route: '/wedstrijden',
    state: {
      'matches': listOf(footMatch),
      'isLoading': bool_.withDefault(true),
    },
    onLoad: [
      ApiCall(
        getMatchesEp,
        outputAs: 'matchesLoad',
        params: {'token': AppState('authToken'), 'page': 1},
        onSuccess: (res) => [
          SetState('matches', res),
          SetState('isLoading', false),
        ],
        onFailure: [
          SetState('isLoading', false),
          Snackbar('Kon wedstrijden niet laden.'),
        ],
      ),
    ],
    body: Scaffold(
      appBar: AppBar(title: 'Wedstrijden'),
      body: ConditionalBuilder(
        children: [
          Column(
            mainAxis: MainAxis.center,
            visible: State('isLoading'),
            children: [ProgressBar.circular(size: 40)],
          ),
          ListView(
            source: State('matches'),
            padding: EdgeInsets.all(12),
            spacing: 8,
            visible: Not(State('isLoading')),
            itemBuilder:
                (item) => matchCard(
                  opponent: item['opponent'],
                  location: item['location'],
                  matchDate: item['matchDatetime'],
                  status: item['status'],
                  onTapAction: Navigate(
                    'WedstrijdDetailPage',
                    params: {'matchId': item['id']},
                  ),
                ),
          ),
        ],
      ),
    ),
  );

  // ─── Wedstrijd detail ───────────────────────────────────────────────────── ─────────────────────────────────────────────────────
  final matchDetailPage = app.page(
    'WedstrijdDetailPage',
    route: '/wedstrijd',
    params: {'matchId': string.withDefault('')},
    state: {
      'match': footMatch,
      'lineup': listOf(lineupPlayer),
      'goals': listOf(matchGoal),
      'isLoading': bool_.withDefault(true),
    },
    onLoad: [
      ApiCall(
        getMatchEp,
        outputAs: 'detailMatchRes',
        params: {
          'token': AppState('authToken'),
          'id': Param('matchId'),
        },
        onSuccess: (res) => [
          SetState('match', res),
          SetState('isLoading', false),
          ApiCall(
            getLineupEp,
            outputAs: 'detailLineupRes',
            params: {
              'token': AppState('authToken'),
              'id': Param('matchId'),
            },
            onSuccess: (r) => [
              SetState('lineup', r),
              ApiCall(
                getGoalsEp,
                outputAs: 'detailGoalsRes',
                params: {
                  'token': AppState('authToken'),
                  'id': Param('matchId'),
                },
                onSuccess: (g) => [SetState('goals', g)],
              ),
            ],
          ),
        ],
        onFailure: [
          SetState('isLoading', false),
          Snackbar('Kon wedstrijd niet laden.'),
        ],
      ),
    ],
    body: Scaffold(
      appBar: AppBar(title: State('match')['opponent']),
      body: ConditionalBuilder(
        children: [
          Column(
            mainAxis: MainAxis.center,
            visible: State('isLoading'),
            children: [ProgressBar.circular(size: 40)],
          ),
          TabBar(
            name: 'MatchDetailTabs',
            style: TabBarStyle.indicator,
            visible: Not(State('isLoading')),
            tabs: [
              // Info tab
              TabItem(
                'Info',
                Column(
                  scrollable: true,
                  padding: 16,
                  spacing: 12,
                  children: [
                    Container(
                      padding: 16,
                      borderRadius: 8,
                      color: Colors.secondaryBackground,
                      borderColor: Colors.hex(0xFFe5e7eb),
                      borderWidth: 1,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        spacing: 8,
                        children: [
                          Text(
                            'Datum & Tijd',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['matchDatetime'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            'Locatie',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['location'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            'Verzamelen',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['arrivalTime'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            'Coach',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['coachName'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            'Fruitheld',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['fruitHeroName'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            'Notities',
                            style: Styles.labelSmall,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            State('match')['notes'],
                            style: Styles.bodyMedium,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              // Opstelling tab
              TabItem(
                'Opstelling',
                ListView(
                  source: State('lineup'),
                  padding: EdgeInsets.all(12),
                  spacing: 6,
                  itemBuilder:
                      (item) => Container(
                        padding: EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        borderRadius: 8,
                        color: Colors.secondaryBackground,
                        borderColor: Colors.hex(0xFFe5e7eb),
                        borderWidth: 1,
                        child: Row(
                          spacing: 12,
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              borderRadius: 18,
                              color: Colors.hex(0xFFdcfce7),
                              child: Text(
                                item['position'],
                                style: Styles.labelSmall,
                                color: Colors.primary,
                                textAlign: TextAlign.center,
                              ),
                            ),
                            Expanded(
                              Text(item['memberName'], style: Styles.labelMedium),
                            ),
                          ],
                        ),
                      ),
                ),
              ),

              // Doelpunten tab
              TabItem(
                'Doelpunten',
                ListView(
                  source: State('goals'),
                  padding: EdgeInsets.all(12),
                  spacing: 6,
                  itemBuilder:
                      (item) => Container(
                        padding: EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        borderRadius: 8,
                        color: Colors.secondaryBackground,
                        borderColor: Colors.hex(0xFFe5e7eb),
                        borderWidth: 1,
                        child: Row(
                          spacing: 12,
                          children: [
                            Container(
                              width: 40,
                              height: 40,
                              borderRadius: 20,
                              color: Colors.hex(0xFFdcfce7),
                              child: Text(
                                item['minute'],
                                style: Styles.labelSmall,
                                color: Colors.primary,
                                textAlign: TextAlign.center,
                              ),
                            ),
                            Expanded(
                              Column(
                                crossAxis: CrossAxis.start,
                                spacing: 2,
                                children: [
                                  Text(
                                    item['scorerName'],
                                    style: Styles.labelMedium,
                                  ),
                                  Text(
                                    item['type'],
                                    style: Styles.bodySmall,
                                    color: Colors.secondaryText,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );

  // ─── Rijschema (Drive schedule) ───────────────────────────────────────────
  final rijschemaPage = app.page(
    'RijschemaPage',
    route: '/rijschema',
    state: {
      'driveMatches': listOf(footMatch),
      'isLoading': bool_.withDefault(true),
    },
    onLoad: [
      ApiCall(
        getDriveEp,
        outputAs: 'driveLoad',
        params: {'token': AppState('authToken')},
        onSuccess: (res) => [
          SetState('driveMatches', res),
          SetState('isLoading', false),
        ],
        onFailure: [
          SetState('isLoading', false),
          Snackbar('Kon rijschema niet laden.'),
        ],
      ),
    ],
    body: Scaffold(
      appBar: AppBar(title: 'Rijschema'),
      body: ConditionalBuilder(
        children: [
          Column(
            mainAxis: MainAxis.center,
            visible: State('isLoading'),
            children: [ProgressBar.circular(size: 40)],
          ),
          ListView(
            source: State('driveMatches'),
            padding: EdgeInsets.all(12),
            spacing: 8,
            visible: Not(State('isLoading')),
            itemBuilder:
                (item) => Container(
                  padding: 12,
                  borderRadius: 8,
                  color: Colors.secondaryBackground,
                  borderColor: Colors.hex(0xFFe5e7eb),
                  borderWidth: 1,
                  child: Column(
                    crossAxis: CrossAxis.start,
                    spacing: 6,
                    children: [
                      Row(
                        spacing: 8,
                        children: [
                          Icon(
                            'calendar_today',
                            size: 14,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            item['matchDatetime'],
                            style: Styles.labelMedium,
                          ),
                        ],
                      ),
                      Row(
                        spacing: 8,
                        children: [
                          Icon('sports_soccer', size: 14, color: Colors.primary),
                          Text(item['opponent'], style: Styles.bodyMedium),
                        ],
                      ),
                      Row(
                        spacing: 8,
                        children: [
                          Icon(
                            'person',
                            size: 14,
                            color: Colors.secondaryText,
                          ),
                          Text(
                            item['coachName'],
                            style: Styles.bodySmall,
                            color: Colors.secondaryText,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
          ),
        ],
      ),
    ),
  );

  // ─── Bardiensten ──────────────────────────────────────────────────────────
  final bardienPage = app.page(
    'BardienPage',
    route: '/bardiensten',
    state: {
      'duties': listOf(barDuty),
      'isLoading': bool_.withDefault(true),
    },
    onLoad: [
      ApiCall(
        getBarDutiesEp,
        outputAs: 'dutiesLoad',
        params: {'token': AppState('authToken'), 'page': 1},
        onSuccess: (res) => [
          SetState('duties', res),
          SetState('isLoading', false),
        ],
        onFailure: [
          SetState('isLoading', false),
          Snackbar('Kon bardiensten niet laden.'),
        ],
      ),
    ],
    body: Scaffold(
      appBar: AppBar(title: 'Bardiensten'),
      body: ConditionalBuilder(
        children: [
          Column(
            mainAxis: MainAxis.center,
            visible: State('isLoading'),
            children: [ProgressBar.circular(size: 40)],
          ),
          ListView(
            source: State('duties'),
            padding: EdgeInsets.all(12),
            spacing: 8,
            visible: Not(State('isLoading')),
            itemBuilder:
                (item) => barDutyCard(
                  date: item['date'],
                  shift: item['shift'],
                  teamName: item['teamName'],
                  members: item['members'],
                  status: item['status'],
                  onTapAction: Snackbar(item['id']),
                ),
          ),
        ],
      ),
    ),
  );

  // ─── Profiel ──────────────────────────────────────────────────────────────
  final profielPage = app.page(
    'ProfielPage',
    route: '/profiel',
    body: Scaffold(
      appBar: AppBar(title: 'Profiel'),
      body: Column(
        scrollable: true,
        padding: 16,
        spacing: 16,
        children: [
          // Avatar + info card
          Container(
            padding: 20,
            borderRadius: 8,
            color: Colors.secondaryBackground,
            borderColor: Colors.hex(0xFFe5e7eb),
            borderWidth: 1,
            child: Column(
              mainAxis: MainAxis.center,
              spacing: 8,
              children: [
                Avatar(
                  text: AppState('userName'),
                  size: 64,
                  backgroundColor: Colors.primary,
                  textColor: Colors.secondaryBackground,
                ),
                Text(
                  AppState('userName'),
                  style: Styles.titleLarge,
                  color: Colors.primaryText,
                  textAlign: TextAlign.center,
                ),
                Text(
                  AppState('userEmail'),
                  style: Styles.bodyMedium,
                  color: Colors.secondaryText,
                  textAlign: TextAlign.center,
                ),
                Container(
                  padding: EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  borderRadius: 12,
                  color: Colors.hex(0xFFdcfce7),
                  child: Text(
                    AppState('clubName'),
                    style: Styles.bodySmall,
                    color: Colors.primary,
                  ),
                ),
              ],
            ),
          ),

          // Logout button
          Button(
            'Uitloggen',
            color: Colors.hex(0xFFfee2e2),
            textColor: Colors.hex(0xFFb91c1c),
            borderRadius: 8,
            padding: EdgeInsets.symmetric(vertical: 14),
            onTap: [
              ApiCall(
                logoutEp,
                outputAs: 'logoutResult',
                params: {'token': AppState('authToken')},
              ),
              UpdateAppState.set('authToken', ''),
              UpdateAppState.set('userName', ''),
              UpdateAppState.set('userEmail', ''),
              UpdateAppState.clear('userRoles'),
              UpdateAppState.set('clubName', ''),
              Navigate(loginPage),
            ],
          ),
        ],
      ),
    ),
  );

  // ─── Bottom navigation ────────────────────────────────────────────────────
  app.bottomNav(
    items: [
      BottomNavItem(wedstrijdenPage, icon: 'sports_soccer'),
      BottomNavItem(rijschemaPage, icon: 'directions_car'),
      BottomNavItem(bardienPage, icon: 'local_bar'),
      BottomNavItem(profielPage, icon: 'person'),
    ],
    backgroundColor: Colors.secondaryBackground,
    selectedColor: Colors.primary,
    unselectedColor: Colors.secondaryText,
  );
}

// ─── CLI boilerplate ─────────────────────────────────────────────────────────

final class _CliOptions {
  const _CliOptions({
    this.apiKey,
    this.baseUrl,
    this.projectName,
    this.projectId,
    this.findOrCreate = false,
    this.allowNewProject = false,
    this.dryRun = false,
    this.commitMessage,
  });

  final String? apiKey;
  final String? baseUrl;
  final String? projectName;
  final String? projectId;
  final bool findOrCreate;
  final bool allowNewProject;
  final bool dryRun;
  final String? commitMessage;
}

_CliOptions _parseCliOptions(List<String> args) {
  String? apiKey;
  String? baseUrl;
  String? projectName;
  String? projectId;
  String? commitMessage;
  var findOrCreate = false;
  var allowNewProject = false;
  var dryRun = false;

  for (var i = 0; i < args.length; i++) {
    final arg = args[i];
    switch (arg) {
      case '--help':
      case '-h':
        _printUsage();
        exit(0);
      case '--api-key':
        apiKey = _requireValue(args, ++i, '--api-key');
      case '--base-url':
        baseUrl = _requireValue(args, ++i, '--base-url');
      case '--project-name':
        projectName = _requireValue(args, ++i, '--project-name');
      case '--project-id':
        projectId = _requireValue(args, ++i, '--project-id');
      case '--commit-message':
        commitMessage = _requireValue(args, ++i, '--commit-message');
      case '--find-or-create':
        findOrCreate = true;
      case '--allow-new-project':
        allowNewProject = true;
      case '--dry-run':
        dryRun = true;
      default:
        stderr.writeln('Unknown option: $arg');
        _printUsage();
        exit(64);
    }
  }

  return _CliOptions(
    apiKey: apiKey,
    baseUrl: baseUrl,
    projectName: projectName,
    projectId: projectId,
    findOrCreate: findOrCreate,
    allowNewProject: allowNewProject,
    dryRun: dryRun,
    commitMessage: commitMessage,
  );
}

String _requireValue(List<String> args, int index, String flag) {
  if (index >= args.length) {
    stderr.writeln('Missing value for $flag.');
    _printUsage();
    exit(64);
  }
  return args[index];
}

void _printUsage() {
  stdout.writeln('''
VoetbalPlanner FlutterFlow create script.

Usage:
  dart run dsl/create.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-name <name>     Create a new project with this name.
  --project-id <id>         Push into an existing project by ID.
  --find-or-create          Retry by reusing a same-name project before creating.
  --allow-new-project       Bypass the workspace binding guard.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --help, -h                Show this help.
''');
}
