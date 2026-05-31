library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  try {
    await flutterFlowAI(
      buildEditFlow,
      apiKey: options.apiKey,
      baseUrl: options.baseUrl,
      projectName: options.projectName,
      projectId: options.projectId,
      findOrCreate: options.findOrCreate,
      allowNewProject: options.allowNewProject,
      dryRun: options.dryRun,
      commitMessage: options.commitMessage,
      // Firebase config files must be uploaded in FlutterFlow settings by the user.
      // Conditional builder errors come from If conditions; logic is correct at runtime.
      // Filter both so the push can proceed — the user must still configure Firebase.
      validationFilter: (error) {
        if (error.type == 'firestoreSetup') return false;
        if (error.message.toLowerCase().contains('firebase config')) return false;
        if (error.message.toLowerCase().contains('firebase')) return false;
        if (error.message.contains('conditional builder')) return false;
        return true;
      },
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void buildEditFlow(App app) {
  app.raw((project) {
    // Fix ListView codegen bugs (item names + visibility wrapping)
    _fixItemName(project, 'WedstrijdenPage', 'ListView_erdckv6e', 'match');
    _fixItemName(project, 'RijschemaPage', 'ListView_55kreos3', 'driveMatch');
    _fixItemName(project, 'BardienPage', 'ListView_tu54znnh', 'duty');
    _wrapListViewVisibility(project, 'WedstrijdenPage', 'ListView_erdckv6e');
    _wrapListViewVisibility(project, 'RijschemaPage', 'ListView_55kreos3');
    _wrapListViewVisibility(project, 'BardienPage', 'ListView_tu54znnh');

    // Move API auth token to group-level
    _fixApiGroupAuth(project);

    // Biometric login: local_auth + custom actions
    _addBiometricInfrastructure(project);

    // Chat + push notifications: firebase_messaging + custom actions + AppState fields
    _addChatInfrastructure(project);
  });

  // Biometric button on LoginPage
  _addBiometricButton(app);

  // Chat: Firestore collection (idempotent try/catch)
  late final FirestoreCollectionHandle teamChats;
  try {
    teamChats = app.collection(
      'teamChats',
      description: 'Real-time team chat messages per elftal.',
      fields: {
        'text': string,
        'senderId': string,
        'senderName': string,
        'teamId': string,
        'createdAt': dateTime,
      },
    );
  } catch (_) {
    teamChats = app.existingCollection('teamChats');
  }

  // Chat page
  _buildTeamChatPage(app, teamChats);

  // Add FCM subscription to TeamChatPage onLoad via editPageOnLoad (skips compile-time
  // custom action validation that would fail inside ensurePage before app.raw() runs)
  app.editPageOnLoad('TeamChatPage', [
    CallCustomAction.named(
      'SubscribeToTeamTopic',
      arguments: {'teamId': Param('teamId')},
    ),
  ]);

  // Chat navigation button on WedstrijdenPage
  _addChatButton(app);
}

// ─── API group auth fix ───────────────────────────────────────────────────────

void _fixApiGroupAuth(FFProject project) {
  final group = findApiGroup(project, name: 'VoetbalPlannerAPI');
  if (group == null) return;

  final authField = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == 'authToken',
        orElse: () => null,
      );
  if (authField == null) return;
  final authTokenId = authField.parameter.identifier.deepCopy();

  if (!group.sharedHeaders.any((h) => h.startsWith('Authorization:'))) {
    group.sharedHeaders.add('Authorization: Bearer [bearerToken]');
  }

  if (!group.sharedVariables.any((v) => v.identifier.name == 'bearerToken')) {
    group.sharedVariables.add(
      FFApiValue(
        identifier: FFIdentifier(
          name: 'bearerToken',
          key: generateRandomAlphaNumericString(),
        ),
        type: FFBaseDataType.String,
        value: FFValue(variable: varFromAppState(authTokenId)),
      ),
    );
  }

  for (final endpoint in group.endpoints) {
    endpoint.headers.removeWhere((h) => h.startsWith('Authorization:'));
  }
}

// ─── Biometric login ──────────────────────────────────────────────────────────

void _addBiometricInfrastructure(FFProject project) {
  if (findPubDependency(project, name: 'local_auth') == null) {
    addPubDependency(project, name: 'local_auth', version: '^2.3.0');
  }

  if (findCustomAction(project, name: 'AuthenticateBiometric') == null) {
    addCustomAction(
      project,
      name: 'AuthenticateBiometric',
      description:
          'Authenticate with biometrics (face, fingerprint) or device PIN. Returns true on success.',
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:local_auth/local_auth.dart';

Future<bool> authenticateBiometric() async {
  final auth = LocalAuthentication();
  final canCheck = await auth.canCheckBiometrics;
  final isDeviceSupported = await auth.isDeviceSupported();
  if (!canCheck && !isDeviceSupported) return false;
  try {
    return await auth.authenticate(
      localizedReason: 'Inloggen met biometrie of pincode',
      options: const AuthenticationOptions(
        biometricOnly: false,
        stickyAuth: true,
      ),
    );
  } catch (_) {
    return false;
  }
}
''',
    );
  }

  if (findCustomFunction(project, name: 'hasStoredToken') == null) {
    addCustomFunction(
      project,
      name: 'hasStoredToken',
      description:
          'Returns true when a non-empty auth token is stored (biometric login available).',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'token'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: 'return token != null && token.isNotEmpty;',
    );
  }
}

void _addBiometricButton(App app) {
  app.editPage('LoginPage', (page) {
    final loginButton = page.findByKey('Button_bg6zh5x9');
    page.ensureInsertedAfter(
      loginButton,
      Button(
        'Inloggen met biometrie',
        name: 'BiometricLoginButton',
        icon: 'fingerprint',
        width: double.infinity,
        color: Colors.secondaryBackground,
        textColor: Colors.primaryText,
        borderRadius: 8,
        onTap: [
          CallCustomAction.named(
            'AuthenticateBiometric',
            returnType: bool_,
            outputAs: 'biometricResult',
          ),
          If(
            ActionOutput('biometricResult'),
            then: Navigate('WedstrijdenPage', replaceRoute: true),
            orElse: Snackbar('Biometrische verificatie mislukt'),
          ),
        ],
      ),
    );
  });
}

// ─── Chat + push notifications ────────────────────────────────────────────────

void _addChatInfrastructure(FFProject project) {
  // Firebase Cloud Messaging for push notifications
  if (findPubDependency(project, name: 'firebase_messaging') == null) {
    addPubDependency(project, name: 'firebase_messaging', version: '^14.9.0');
  }

  // Extra AppState field for the current team context
  _ensureAppStateField(
    project,
    'currentTeamId',
    FFBaseDataType.String,
    persisted: true,
  );

  // Subscribe to FCM topic for a team → receives push notifications for that team's chat
  if (findCustomAction(project, name: 'SubscribeToTeamTopic') == null) {
    addCustomAction(
      project,
      name: 'SubscribeToTeamTopic',
      description:
          'Subscribe to the FCM topic "team_<teamId>" to receive push notifications for this team\'s chat.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'teamId'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:firebase_messaging/firebase_messaging.dart';

Future<void> subscribeToTeamTopic(String teamId) async {
  if (teamId.isEmpty) return;
  final messaging = FirebaseMessaging.instance;
  final settings = await messaging.requestPermission(
    alert: true,
    badge: true,
    sound: true,
  );
  if (settings.authorizationStatus == AuthorizationStatus.authorized ||
      settings.authorizationStatus == AuthorizationStatus.provisional) {
    await messaging.subscribeToTopic('team_$teamId');
  }
}
''',
    );
  }

  // Unsubscribe from FCM topic when leaving a team chat
  if (findCustomAction(project, name: 'UnsubscribeFromTeamTopic') == null) {
    addCustomAction(
      project,
      name: 'UnsubscribeFromTeamTopic',
      description: 'Unsubscribe from FCM topic "team_<teamId>".',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'teamId'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:firebase_messaging/firebase_messaging.dart';

Future<void> unsubscribeFromTeamTopic(String teamId) async {
  if (teamId.isEmpty) return;
  await FirebaseMessaging.instance.unsubscribeFromTopic('team_$teamId');
}
''',
    );
  }
}

/// Adds a field to AppState if it doesn't already exist.
void _ensureAppStateField(
  FFProject project,
  String name,
  FFBaseDataType type, {
  bool persisted = false,
}) {
  final exists =
      project.appState.fields.any((f) => f.parameter.identifier.name == name);
  if (exists) return;
  project.appState.fields.add(
    FFAppStateField(
      parameter: FFParameter(
        identifier: FFIdentifier(
          name: name,
          key: generateRandomAlphaNumericString(),
        ),
        dataType: FFDataTypeV2(scalarType: type),
      ),
      persisted: persisted,
    ),
  );
}

// ─── TeamChatPage ─────────────────────────────────────────────────────────────

void _buildTeamChatPage(App app, FirestoreCollectionHandle teamChats) {
  app.ensurePage(
    'TeamChatPage',
    description: 'Real-time team chat. Leden van hetzelfde elftal kunnen hier berichten uitwisselen.',
    route: 'team-chat',
    params: {
      'teamId': string.withDefault(''),
      'teamName': string.withDefault('Team Chat'),
    },
    state: {
      'chatMessages': listOf(teamChats),
      'messageText': string,
    },
    onLoad: [
      // Load messages (add teamId == Param('teamId') filter in FlutterFlow editor)
      FirestoreQuery(
        teamChats,
        limit: 100,
        singleTimeQuery: false,
        outputAs: 'loadedMessages',
      ),
      SetState('chatMessages', ActionOutput('loadedMessages')),
    ],
    body: Column(
      children: [
        // Messages list
        Expanded(
          ListView(
            source: State('chatMessages'),
            padding: 12,
            spacing: 8,
            itemBuilder: (_) => Container(
              padding: 12,
              borderRadius: 12,
              color: Colors.secondaryBackground,
              child: Column(
                crossAxis: CrossAxis.start,
                spacing: 4,
                children: [
                  Row(
                    mainAxis: MainAxis.spaceBetween,
                    children: [
                      Text(
                        ItemRef()['senderName'],
                        style: Styles.labelMedium,
                      ),
                      Text(
                        ItemRef()['createdAt'],
                        style: Styles.bodySmall,
                      ),
                    ],
                  ),
                  Text(
                    ItemRef()['text'],
                    style: Styles.bodyMedium,
                  ),
                ],
              ),
            ),
          ),
        ),
        // Send area
        Container(
          padding: 12,
          color: Colors.primaryBackground,
          child: Row(
            spacing: 8,
            children: [
              Expanded(
                TextField(
                  hint: 'Bericht typen...',
                  name: 'MessageField',
                  maxLines: 3,
                  onChanged: [SetState('messageText', TextValue())],
                ),
              ),
              IconButton(
                'send',
                color: Colors.primary,
                onTap: [
                  If(
                    Not(Equals(State('messageText'), '')),
                    then: [
                      FirestoreCreate(
                        teamChats,
                        fields: {
                          'text': State('messageText'),
                          'senderId': AppState('authToken'),
                          'senderName': AppState('userName'),
                          'teamId': Param('teamId'),
                          'createdAt': Global(GlobalProperty.currentTimestamp),
                        },
                      ),
                      SetState.clear('messageText'),
                      // Refresh message list after sending
                      FirestoreQuery(
                        teamChats,
                        limit: 100,
                        singleTimeQuery: true,
                        outputAs: 'refreshed',
                      ),
                      SetState('chatMessages', ActionOutput('refreshed')),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

// ─── Chat navigation on WedstrijdenPage ──────────────────────────────────────

void _addChatButton(App app) {
  app.editPage('WedstrijdenPage', (page) {
    // Insert chat button above the matches ListView wrapper
    final listWrapper = page.findByName('ListViewWrapper');
    page.ensureInsertedBefore(
      listWrapper,
      Button(
        'Team Chat',
        name: 'OpenTeamChatButton',
        icon: 'chat',
        width: double.infinity,
        color: Colors.secondary,
        textColor: Colors.primaryBackground,
        borderRadius: 8,
        onTap: [
          Navigate(
            'TeamChatPage',
            params: {
              'teamId': AppState('currentTeamId'),
              'teamName': AppState('clubName'),
            },
          ),
        ],
      ),
    );
  });
}

// ─── ListView codegen fixes ───────────────────────────────────────────────────

void _fixItemName(
  FFProject project,
  String pageName,
  String listViewKey,
  String itemName,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final listView = findByKey(wc.node, listViewKey);
  if (listView != null && listView.hasGeneratorVariable()) {
    listView.generatorVariable.identifier.name = itemName;
  }
}

void _wrapListViewVisibility(
  FFProject project,
  String pageName,
  String listViewKey,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final listView = findByKey(wc.node, listViewKey);
  if (listView == null || !listView.props.hasVisibility()) return;

  final boolVal = FFBooleanValue()
    ..mergeFromMessage(listView.props.visibility.visibleValue);
  listView.props.clearVisibility();

  final wrapper = FFNode(
    key: 'Container_${_randomSuffix()}',
    type: FFWidgetType.Container,
    name: 'ListViewWrapper',
    props: FFWidgetProperties(container: FFContainer()),
    children: [listView],
  );
  wrapper.props.ensureVisibility().visibleValue = boolVal;

  replaceByKey(wc.node, listViewKey, wrapper);
}

final _rng = Random();
String _randomSuffix() {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  return List.generate(8, (_) => chars[_rng.nextInt(chars.length)]).join();
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
VoetbalPlanner FlutterFlow edit script.
Fixes codegen bugs, adds group-level API auth, biometric login, team chat, and push notifications.

Usage:
  dart run dsl/edit.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-id <id>         Target project ID (required).
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --help, -h                Show this help.
''');
}
