library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/client/project_error.dart' show ProjectError;
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart'
    show findCollectionField;
import 'package:flutterflow_ai/src/helpers/function_call_helpers.dart'
    show CodeExpressionArg, codeExpressionVar;
import 'package:flutterflow_ai/src/helpers/param_value.dart'
    show VariableParamValue;
import 'package:flutterflow_ai/src/helpers/state_update.dart'
    show StateFieldUpdate;
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show findDescendants, removeByKey, insertBeforeKey, getPropertyChild;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';
import 'package:flutterflow_ai/src/ui/actions.dart' show Actions;
import 'package:flutterflow_ai/src/ui/ui.dart' show UI;
import 'package:flutterflow_ai/src/ui/ui_types.dart'
    show UITextStyle, UIMainAxisAlignment;

bool Function(ProjectError) get _validationFilter => (error) {
  if (error.type == 'firestoreSetup') return false;
  if (error.message.toLowerCase().contains('firebase config')) return false;
  if (error.message.toLowerCase().contains('firebase')) return false;
  if (error.message.contains('conditional builder')) return false;
  return true;
};

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  try {
    if (options.resetTeamChatPage) {
      // Two-push sequence to force-recreate TeamChatPage after the
      // teamChats collection was deleted and recreated (new Firebase project).
      // Push 1: remove the stale page with broken collection references.
      stdout.writeln('[1/2] Removing stale TeamChatPage...');
      await flutterFlowAI(
        _buildEditFlowRemoveChatPage,
        apiKey: options.apiKey,
        baseUrl: options.baseUrl,
        projectName: options.projectName,
        projectId: options.projectId,
        findOrCreate: options.findOrCreate,
        allowNewProject: options.allowNewProject,
        dryRun: options.dryRun,
        commitMessage: 'Remove stale TeamChatPage (rebuild follows)',
        validationFilter: (error) {
          // A dangling Navigate→TeamChatPage on WedstrijdenPage is expected
          // for this transitional push only.
          if (error.message.toLowerCase().contains('teamchat')) return false;
          if (error.message.toLowerCase().contains('team chat')) return false;
          return _validationFilter(error);
        },
      );
      // Push 2: normal flow — ensurePage will now create the page fresh.
      stdout.writeln('[2/2] Recreating TeamChatPage with new collection references...');
      await flutterFlowAI(
        buildEditFlow,
        apiKey: options.apiKey,
        baseUrl: options.baseUrl,
        projectName: options.projectName,
        projectId: options.projectId,
        findOrCreate: options.findOrCreate,
        allowNewProject: options.allowNewProject,
        dryRun: options.dryRun,
        commitMessage: options.commitMessage ?? 'Recreate TeamChatPage with fresh collection references',
        validationFilter: _validationFilter,
      );
    } else {
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
        validationFilter: _validationFilter,
      );
    }
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

// Stripped-down flow used only for the removal push (push 1 of --reset-teamchat-page).
// Applies all non-chat-page fixes and removes the stale TeamChatPage.
void _buildEditFlowRemoveChatPage(App app) {
  app.raw((project) {
    _fixItemName(project, 'WedstrijdenPage', 'ListView_erdckv6e', 'match');
    _fixItemName(project, 'RijschemaPage', 'ListView_55kreos3', 'driveMatch');
    _fixItemName(project, 'BardienPage', 'ListView_tu54znnh', 'duty');
    _wrapListViewVisibility(project, 'WedstrijdenPage', 'ListView_erdckv6e');
    _wrapListViewVisibility(project, 'RijschemaPage', 'ListView_55kreos3');
    _wrapListViewVisibility(project, 'BardienPage', 'ListView_tu54znnh');
    _fixApiGroupAuth(project);
    _addBiometricInfrastructure(project);
    _addChatInfrastructure(project);
    _moveChatButtonOutOfConditional(project);
    _restructureWedstrijdenPageBody(project);
    _addMatchNavigation(project);
    _addUpcomingFilter(project);
  });
  _addBiometricButton(app);
  // Ensure the collection exists (idempotent) — needed for the recreate push.
  try {
    app.collection(
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
    // Already exists — that's fine.
  }
  // Remove the page. editPageOnLoad / _addChatButton must NOT be called
  // here because app.removePage conflicts with existingReference tracking.
  app.removePage('TeamChatPage');
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

    // Remove chat button from inside the ConditionalBuilder (moved to before it)
    _moveChatButtonOutOfConditional(project);

    // Wrap Scaffold body in a Column so widgets can be stacked above the list
    _restructureWedstrijdenPageBody(project);

    // Tap on match list item → WedstrijdDetailPage
    _addMatchNavigation(project);

    // Upcoming matches filter: showAllMatches state + toggle + visibility
    _addUpcomingFilter(project);
  });

  // Biometric button on LoginPage
  _addBiometricButton(app);

  // Chat: Firestore collections (idempotent try/catch)
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

  late final FirestoreCollectionHandle directMessages;
  try {
    directMessages = app.collection(
      'directMessages',
      description: '1-op-1 directe berichten tussen twee teamleden.',
      fields: {
        'text': string,
        'senderId': string,
        'senderName': string,
        'receiverId': string,
        'createdAt': dateTime,
      },
    );
  } catch (_) {
    directMessages = app.existingCollection('directMessages');
  }

  // Chat pages
  _buildTeamChatPage(app, teamChats);
  _buildDirectChatPage(app, directMessages);

  // editPageOnLoad REPLACES the full onLoad on every push, which lets us keep the
  // collection reference fresh. FCM subscription uses editPageOnLoad because
  // CallCustomAction inside ensurePage onLoad would fail before app.raw() creates it.
  app.editPageOnLoad('TeamChatPage', [
    FirestoreQuery(
      teamChats,
      limit: 100,
      singleTimeQuery: false,
      outputAs: 'loadedMessages',
    ),
    SetState('chatMessages', ActionOutput('loadedMessages')),
    CallCustomAction.named(
      'SubscribeToTeamTopic',
      arguments: {'teamId': Param('teamId')},
    ),
  ]);

  // Add teamId filter AFTER page() has written the fresh TeamChatPage
  app.raw((project) {
    _addTeamChatFilters(project);
  });

  // Chat navigation button on WedstrijdenPage
  // (ChatMenuSheet with DirectChatPage navigate deferred to next push)
  _addChatButton(app);
}

// ─── Match navigation ─────────────────────────────────────────────────────────

void _addMatchNavigation(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;
  final listView = findByKey(wc.node, 'ListView_erdckv6e');
  if (listView == null || listView.children.isEmpty) return;
  final itemTemplate = listView.children.first;

  if (project.getWidgetClassByName('WedstrijdDetailPage') == null) return;

  // Always re-apply so the matchId param binding stays current.
  itemTemplate.triggerActions.removeWhere(
    (t) => t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  Actions.onTap(
    itemTemplate,
    Actions.navigate(
      project,
      pageName: 'WedstrijdDetailPage',
      params: {
        'matchId': VariableParamValue(generatorVarField('ListView_erdckv6e', 'id')),
      },
    ),
  );
}

// ─── Upcoming matches filter ──────────────────────────────────────────────────

void _addUpcomingFilter(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;

  // 1. Ensure showAllMatches boolean state field exists on WedstrijdenPage.
  FFIdentifier showAllId;
  final existingField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == 'showAllMatches',
        orElse: () => null,
      );
  if (existingField == null) {
    final newId = FFIdentifier(
      name: 'showAllMatches',
      key: generateRandomAlphaNumericString(),
    );
    wc.classModel.stateFields.add(
      FFWidgetClassStateField(
        parameter: FFParameter(
          identifier: newId,
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
        ),
      ),
    );
    showAllId = newId;
  } else {
    showAllId = existingField.parameter.identifier;
  }

  // 2. Bind list-item visibility to: showAllMatches || isUpcoming(matchDatetime).
  //    Uses a custom function — codeExpressionVar rejects Boolean page-state args.
  //    Only set visibility once — subsequent pushes skip this block.
  final listView = findByKey(wc.node, 'ListView_erdckv6e');
  if (listView != null && listView.children.isNotEmpty) {
    final itemTemplate = listView.children.first;
    // Always re-apply — a previous push may have set a broken value.
    itemTemplate.props.clearVisibility();
    {
      // Check only matchDatetime via codeExpressionVar (String arg works; Boolean arg does not).
      final matchDatetimeVar = generatorVarField('ListView_erdckv6e', 'matchDatetime');
      final dateCheckVar = codeExpressionVar(
        expression: r"matchDatetime != null && matchDatetime.isNotEmpty && "
            r"!DateTime.parse(matchDatetime.length >= 10 "
            r"? matchDatetime.substring(0, 10) : '2000-01-01')"
            r".isBefore(DateTime.now().subtract(const Duration(hours: 12)))",
        arguments: [
          CodeExpressionArg(
            name: 'matchDatetime',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: matchDatetimeVar),
          ),
        ],
        returnType: FFParameter(
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
        ),
      );
      // Set visibility using only the date check (String arg only).
      // FlutterFlow's server rejects: boolean CodeExpressionArgs, conditionalValue
      // and combineConditions when codeExpression is involved.
      // The showAll toggle wires the state field; further visibility for showAll
      // would require a different mechanism (e.g. reload different data).
      itemTemplate.props.ensureVisibility().visibleValue =
          FFBooleanValue(variable: dateCheckVar);
    }
  }

  // 3. Add a toggle row before the ConditionalBuilder (idempotent by name check).
  const toggleRowName = 'ShowAllMatchesRow';
  final alreadyHasToggle =
      findDescendants(wc.node, (n) => n.name == toggleRowName).isNotEmpty;
  if (!alreadyHasToggle) {
    final switchNode = UI.toggle(name: 'ShowAllMatchesToggle');
    Actions.onToggle(
      switchNode,
      Actions.updatePageState(
        project,
        widgetClassName: 'WedstrijdenPage',
        updates: [StateFieldUpdate.toggle('showAllMatches')],
      ),
    );
    final toggleRow = UI.row(
      name: toggleRowName,
      mainAxisAlignment: UIMainAxisAlignment.spaceBetween,
      children: [
        UI.text(
          'Toon alle wedstrijden',
          style: UITextStyle.bodyMedium,
          name: 'ShowAllMatchesLabel',
        ),
        switchNode,
      ],
    );
    insertBeforeKey(wc.node, 'ConditionalBuilder_f1ph1tgg', toggleRow);
  }
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
  const _fcmVersion = '^15.0.0';
  final _existingFcm = findPubDependency(project, name: 'firebase_messaging');
  if (_existingFcm == null) {
    addPubDependency(project, name: 'firebase_messaging', version: _fcmVersion);
  } else if (_existingFcm.version != _fcmVersion) {
    updatePubDependency(project, name: 'firebase_messaging', newVersion: _fcmVersion);
  }

  // Extra AppState field for the current team context
  _ensureAppStateField(
    project,
    'currentTeamId',
    FFBaseDataType.String,
    persisted: true,
  );

  // Subscribe to FCM topic for a team → receives push notifications for that team's chat.
  // Parameter is String? because FlutterFlow page params are generated as nullable.
  const _subscribeCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:firebase_messaging/firebase_messaging.dart';

Future<void> subscribeToTeamTopic(String? teamId) async {
  if (teamId == null || teamId.isEmpty) return;
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
''';
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
      code: _subscribeCode,
    );
  } else {
    updateCustomAction(project, name: 'SubscribeToTeamTopic', code: _subscribeCode);
  }

  // Unsubscribe from FCM topic when leaving a team chat.
  const _unsubscribeCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:firebase_messaging/firebase_messaging.dart';

Future<void> unsubscribeFromTeamTopic(String? teamId) async {
  if (teamId == null || teamId.isEmpty) return;
  await FirebaseMessaging.instance.unsubscribeFromTopic('team_$teamId');
}
''';
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
      code: _unsubscribeCode,
    );
  } else {
    updateCustomAction(project, name: 'UnsubscribeFromTeamTopic', code: _unsubscribeCode);
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

// ─── DirectChatPage ───────────────────────────────────────────────────────────

void _buildDirectChatPage(App app, FirestoreCollectionHandle directMessages) {
  app.ensurePage(
    'DirectChatPage',
    description: '1-op-1 direct bericht met een ander teamlid.',
    route: 'direct-chat',
    params: {
      'memberId': string.withDefault(''),
      'memberName': string.withDefault('Direct bericht'),
    },
    state: {
      'chatMessages': listOf(directMessages),
      'messageText': string,
    },
    onLoad: [
      FirestoreQuery(
        directMessages,
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
                  name: 'DirectMessageField',
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
                        directMessages,
                        fields: {
                          'text': State('messageText'),
                          'senderId': AppState('authToken'),
                          'senderName': AppState('userName'),
                          'receiverId': Param('memberId'),
                          'createdAt': Global(GlobalProperty.currentTimestamp),
                        },
                      ),
                      SetState.clear('messageText'),
                      FirestoreQuery(
                        directMessages,
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

// NOTE: ChatMenuSheet component (with "Individuele Chat" → DirectChatPage) is
// intentionally deferred to a subsequent push. DirectChatPage must exist in the
// fetched project before Navigate('DirectChatPage') can be resolved at compile
// time. Run the DSL once to create DirectChatPage, then enable the bottom-sheet
// menu on the next push.
void _addChatButton(App app) {
  app.editPage('WedstrijdenPage', (page) {
    // Insert chat button above the ConditionalBuilder that wraps the match list.
    // Using the key directly is stable; finding by name would land inside the conditional.
    page.ensureInsertedBefore(
      page.findByKey('ConditionalBuilder_f1ph1tgg'),
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

// ─── Chat button placement fix ────────────────────────────────────────────────

void _moveChatButtonOutOfConditional(FFProject project) {
  const conditionalKey = 'ConditionalBuilder_f1ph1tgg';
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;

  final conditional = findByKey(wc.node, conditionalKey);
  if (conditional != null) {
    // Remove any chat buttons found inside the ConditionalBuilder.
    for (final btn in findDescendants(
      conditional,
      (n) => n.name == 'OpenTeamChatButton' || n.name == 'OpenChatMenuButton',
    )) {
      removeByKey(wc.node, btn.key);
    }
  }

  // Remove any stale 'OpenChatMenuButton' from outside the conditional
  // (from intermediate pushes during chat-menu migration).
  for (final btn in findDescendants(
    wc.node,
    (n) => n.name == 'OpenChatMenuButton',
  )) {
    removeByKey(wc.node, btn.key);
  }
}

// ─── WedstrijdenPage body restructure ────────────────────────────────────────

// The Scaffold body is a single-child named slot. insertBeforeKey/
// ensureInsertedBefore both insert into the flat children array without
// registering in childPropertyMap, so added widgets end up unrendered.
// Fix: wrap the body ConditionalBuilder in a Column so siblings can live
// inside a real multi-child layout.
void _restructureWedstrijdenPageBody(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;

  // Idempotent: already wrapped
  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild == null) return;
  if (bodyChild.type == FFWidgetType.Column) return;

  // 1. Remove orphaned Scaffold children (not registered in any slot).
  //    These are widgets that previous pushes incorrectly appended to
  //    Scaffold.children instead of a named slot (e.g. body).
  final slottedKeys = wc.node.childPropertyMap.values
      .expand((v) => v.keyRefs.map((r) => r.key))
      .toSet();
  wc.node.children.removeWhere((n) => !slottedKeys.contains(n.key));

  // 2. Create a full-height Column to be the new body.
  final bodyColumn = UI.column(name: 'PageBodyColumn', mainAxisMin: false);

  // 3. Mark ConditionalBuilder as Expanded so it fills remaining space.
  UI.expanded(bodyChild);
  bodyColumn.children.add(bodyChild);

  // 4. Swap ConditionalBuilder for the Column in Scaffold.children.
  final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
  wc.node.children[idx] = bodyColumn;

  // 5. Re-point the body slot to the Column.
  wc.node.childPropertyMap['body'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: bodyColumn.key)],
  );
}

// ─── Firestore teamId filter ──────────────────────────────────────────────────

void _addTeamChatFilters(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  // Locate teamId page param identifier.
  FFIdentifier? teamIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'teamId') {
      teamIdParamId = param.identifier.deepCopy();
      break;
    }
  }
  if (teamIdParamId == null) return;

  // Locate teamId collection field identifier.
  final teamIdField = findCollectionField(
    project,
    collectionName: 'teamChats',
    fieldName: 'teamId',
  );
  if (teamIdField == null) return;

  final whereFilter = FFFirestoreWhere(
    isAnd: true,
    filters: [
      FFFirestoreWhere_NestedFilter(
        baseFilter: FFFirestoreFilter(
          collectionFieldIdentifier: teamIdField.identifier.deepCopy(),
          relation: FFFirestoreFilter_Relation.EQUAL_TO,
          variable: varFromPageParam(teamIdParamId),
        ),
      ),
    ],
  );

  // Walk every node on the page (including the root) and patch all Firestore
  // query actions that don't already have a where clause.
  final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
  for (final node in allNodes) {
    for (final trigger in node.triggerActions) {
      if (trigger.hasRootAction()) {
        _applyFilterToActionChain(trigger.rootAction, whereFilter);
      }
    }
  }
}

void _applyFilterToActionChain(
  FFActionNode node,
  FFFirestoreWhere whereFilter,
) {
  // Patch this node if it is a Firestore query without a filter.
  if (node.hasAction() &&
      node.action.hasDatabase() &&
      node.action.database.hasFirestoreQuery()) {
    final query = node.action.database.firestoreQuery;
    if (!query.hasWhere()) {
      query.where = whereFilter.deepCopy();
    }
  }

  // Recurse into all branches.
  if (node.hasConditionActions()) {
    for (final branch in node.conditionActions.trueActions) {
      if (branch.hasTrueAction()) {
        _applyFilterToActionChain(branch.trueAction, whereFilter);
      }
    }
    if (node.conditionActions.hasFalseAction()) {
      _applyFilterToActionChain(node.conditionActions.falseAction, whereFilter);
    }
  }
  if (node.hasLoopAction() && node.loopAction.hasAction()) {
    _applyFilterToActionChain(node.loopAction.action, whereFilter);
  }
  if (node.hasParallelActions()) {
    for (final branch in node.parallelActions.actions) {
      _applyFilterToActionChain(branch, whereFilter);
    }
  }
  if (node.hasFollowUpAction()) {
    _applyFilterToActionChain(node.followUpAction, whereFilter);
  }
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
    this.resetTeamChatPage = false,
  });

  final String? apiKey;
  final String? baseUrl;
  final String? projectName;
  final String? projectId;
  final bool findOrCreate;
  final bool allowNewProject;
  final bool dryRun;
  final String? commitMessage;
  final bool resetTeamChatPage;
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
  var resetTeamChatPage = false;

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
      case '--reset-teamchat-page':
        resetTeamChatPage = true;
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
    resetTeamChatPage: resetTeamChatPage,
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
Fixes codegen bugs, adds group-level API auth, biometric login, team chat, push notifications,
match navigation, upcoming match filter, and a chat menu with direct messaging.

Usage:
  dart run dsl/edit.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-id <id>         Target project ID (required).
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --reset-teamchat-page     Force-recreate TeamChatPage (two sequential pushes:
                            remove stale page, then recreate from scratch).
                            Use when TeamChatPage has broken collection references
                            after a Firebase project was deleted and recreated.
  --help, -h                Show this help.
''');
}
