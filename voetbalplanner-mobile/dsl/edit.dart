library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/client/project_error.dart' show ProjectError;
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart'
    show findCollectionField;
import 'package:flutterflow_ai/src/helpers/function_call_helpers.dart'
    show CodeExpressionArg, codeExpressionVar, interpolateVar;
import 'package:flutterflow_ai/src/helpers/param_value.dart'
    show VariableParamValue;
import 'package:flutterflow_ai/src/helpers/state_update.dart'
    show StateFieldUpdate;
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show findDescendants, removeByKey, insertBeforeKey, getPropertyChild;
import 'package:flutterflow_ai/src/helpers/nav_bar_helpers.dart'
    show setNavBarEnabled, addNavBarPage;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';
import 'package:flutterflow_ai/src/ui/actions.dart' show Actions;
import 'package:flutterflow_ai/src/ui/ui.dart' show UI;
import 'package:flutterflow_ai/src/ui/ui_types.dart'
    show UITextStyle, UIMainAxisAlignment, UIEdgeInsets;

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
    _addWelcomeGreeting(project);
    _setupProfielPage(project);
    _addChatPageAppBars(project);
    _setupNavBar(project);
    _addMagicLinkInfrastructure(project);
    _makeLoginPageScrollable(project);
    _fixLoginButtonBindings(project);
  });
  _addBiometricButton(app);
  _addLedenLoginSection(app);
  app.raw((project) { _fixLoginPageLabels(project); });
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

    // Welcome greeting at the top of WedstrijdenPage
    _addWelcomeGreeting(project);

    // Profile page: bind AppState fields (naam, e-mail, club)
    _setupProfielPage(project);

    // AppBar (+ back button) on chat sub-pages, global NavBar for main pages
    _addChatPageAppBars(project);
    _setupNavBar(project);
    _addMagicLinkInfrastructure(project);
    _makeLoginPageScrollable(project);
    _fixLoginButtonBindings(project);
  });

  // Biometric button on LoginPage
  _addBiometricButton(app);
  _addLedenLoginSection(app);

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
  _buildMagicLinkVerifyPage(app);

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
      arguments: {'teamId': AppState('currentTeamId')},
    ),
  ]);

  // Add teamId filter AFTER page() has written the fresh TeamChatPage.
  // Also fix login page labels here — after _addLedenLoginSection ran.
  app.raw((project) {
    _addTeamChatFilters(project);
    _fixLoginPageLabels(project);
    _resetTeamChatAppBar(project);
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

  // 1. Ensure showAllMatches boolean state field.
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

  // 2. Remove broken codeExpressionVar visibility — it was hiding ALL items.
  //    Server-side ?upcoming=1 replaces client-side date checks.
  final listView = findByKey(wc.node, 'ListView_erdckv6e');
  if (listView != null && listView.children.isNotEmpty) {
    listView.children.first.props.clearVisibility();
  }

  // 3. Ensure GetUpcomingMatches endpoint exists (idempotent).
  final group = findApiGroup(project, name: 'VoetbalPlannerAPI');
  if (group == null) return;

  final getMatchesEp = group.endpoints
      .cast<FFApiEndpoint?>()
      .firstWhere((ep) => ep?.identifier.name == 'GetMatches', orElse: () => null);
  final existingUpcomingEp = group.endpoints
      .cast<FFApiEndpoint?>()
      .firstWhere(
        (ep) => ep?.identifier.name == 'GetUpcomingMatches',
        orElse: () => null,
      );
  if (existingUpcomingEp == null) {
    group.endpoints.add(FFApiEndpoint(
      identifier: FFIdentifier(
        name: 'GetUpcomingMatches',
        key: generateRandomAlphaNumericString(),
      ),
      url: '/matches?upcoming=1&per_page=50&team_id=[teamId]',
      callType: FFApiEndpoint_CallType.GET,
      bodyType: FFApiEndpoint_BodyType.NONE,
      body: '',
      variables: [
        FFApiValue(
          identifier: FFIdentifier(name: 'token', key: generateRandomAlphaNumericString()),
          type: FFBaseDataType.String,
        ),
        FFApiValue(
          identifier: FFIdentifier(name: 'teamId', key: generateRandomAlphaNumericString()),
          type: FFBaseDataType.String,
        ),
      ],
      headers: ['Authorization: Bearer [bearerToken]'],
      groupIdentifier: group.identifier.deepCopy(),
      responseDataStructParam: getMatchesEp?.responseDataStructParam.deepCopy(),
    ));
  } else {
    existingUpcomingEp.url = '/matches?upcoming=1&per_page=50&team_id=[teamId]';
    if (!existingUpcomingEp.variables.any((v) => v.identifier.name == 'teamId')) {
      existingUpcomingEp.variables.add(FFApiValue(
        identifier: FFIdentifier(name: 'teamId', key: generateRandomAlphaNumericString()),
        type: FFBaseDataType.String,
      ));
    }
  }

  // 4. Replace onLoad: use GetUpcomingMatches so upcoming matches show on first load.
  //    Also sets isLoading=false consistent with the original chain.
  final authTokenId = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'authToken', orElse: () => null)
      ?.parameter.identifier;
  if (authTokenId == null) return;

  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetUpcomingMatches',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        if (currentTeamIdId != null) 'teamId': varFromAppState(currentTeamIdId.deepCopy()),
      },
      outputVariableName: 'matchesLoad',
      nodeKey: 'Scaffold_xjabl8lh',
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdenPage',
          updates: [
            StateFieldUpdate.setFromVariable('matches', ctx.responseVar),
            StateFieldUpdate.set('isLoading', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdenPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon wedstrijden niet laden.'),
      ]),
    ),
  );

  // 5. Toggle row: create if missing, wire up toggle + conditional data reload.
  const toggleRowName = 'ShowAllMatchesRow';
  if (findDescendants(wc.node, (n) => n.name == toggleRowName).isEmpty) {
    final switchNode = UI.toggle(name: 'ShowAllMatchesToggle');
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

  final switchNode = findDescendants(wc.node, (n) => n.name == 'ShowAllMatchesToggle').firstOrNull;
  if (switchNode == null) return;

  // Replace all toggle triggers: use ON_TOGGLE_ON / ON_TOGGLE_OFF for clean separate chains.
  switchNode.triggerActions.removeWhere((t) => t.hasTrigger() && (
    t.trigger.triggerType == FFActionTriggerType.ON_TOGGLE ||
    t.trigger.triggerType == FFActionTriggerType.ON_TOGGLE_ON ||
    t.trigger.triggerType == FFActionTriggerType.ON_TOGGLE_OFF
  ));

  // The toggle widget's node key — needed for action-output nodeKeyRef.
  const _toggleKey = 'Switch_ugcso2gz';

  // Toggle ON → show all matches (GetMatches without upcoming filter).
  Actions.addTriggerChain(
    switchNode,
    FFActionTriggerType.ON_TOGGLE_ON,
    Actions.apiCallNode(
      project,
      endpointName: 'GetMatches',
      groupName: 'VoetbalPlannerAPI',
      variables: {'page': '1'},
      dynamicVariables: {'token': varFromAppState(authTokenId.deepCopy())},
      outputVariableName: 'allMatchesResult',
      nodeKey: _toggleKey,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdenPage',
          updates: [StateFieldUpdate.setFromVariable('matches', ctx.responseVar)],
        ),
      ]),
    ),
  );

  // Toggle OFF → show upcoming matches only (GetUpcomingMatches), filtered by team.
  Actions.addTriggerChain(
    switchNode,
    FFActionTriggerType.ON_TOGGLE_OFF,
    Actions.apiCallNode(
      project,
      endpointName: 'GetUpcomingMatches',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        if (currentTeamIdId != null) 'teamId': varFromAppState(currentTeamIdId.deepCopy()),
      },
      outputVariableName: 'upcomingMatchesResult',
      nodeKey: _toggleKey,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdenPage',
          updates: [StateFieldUpdate.setFromVariable('matches', ctx.responseVar)],
        ),
      ]),
    ),
  );
}

// ─── Welcome greeting on WedstrijdenPage ─────────────────────────────────────

void _addWelcomeGreeting(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'WelcomeGreetingContainer').isNotEmpty) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;

  final greetingText = UI.text('Welkom!', name: 'WelcomeGreetingText', style: UITextStyle.titleMedium);
  greetingText.props.text.textValue = interpolateVar([
    'Welkom, ',
    varFromAppState(userNameId.deepCopy()),
    '!',
  ]);

  final greetingContainer = UI.container(
    name: 'WelcomeGreetingContainer',
    child: greetingText,
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 8),
    width: double.infinity,
  );

  final showAllRow = findDescendants(wc.node, (n) => n.name == 'ShowAllMatchesRow').firstOrNull;
  if (showAllRow != null) {
    insertBeforeKey(wc.node, showAllRow.key, greetingContainer);
  }
}

// ─── ProfielPage: bind AppState data ─────────────────────────────────────────

void _setupProfielPage(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'ProfielInfoCard').isNotEmpty) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  final clubNameId = _findAppStateFieldId(project, 'clubName');

  FFNode _boundText(String name, FFIdentifier? fieldId, String fallback, UITextStyle style) {
    final node = UI.text(fallback, name: name, style: style);
    if (fieldId != null) {
      node.props.text.textValue = FFStringValue(variable: varFromAppState(fieldId.deepCopy()));
    }
    return node;
  }

  final infoCard = UI.container(
    name: 'ProfielInfoCard',
    padding: UIEdgeInsets.all(16),
    width: double.infinity,
    child: UI.column(
      name: 'ProfielInfoContent',
      spacing: 8,
      children: [
        _boundText('ProfielNaam', userNameId, 'Naam', UITextStyle.titleLarge),
        _boundText('ProfielEmail', userEmailId, 'E-mailadres', UITextStyle.bodyMedium),
        _boundText('ProfielClub', clubNameId, 'Club', UITextStyle.bodyMedium),
      ],
    ),
  );

  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild == null) {
    final column = UI.column(name: 'ProfielBodyColumn');
    column.children.add(infoCard);
    wc.node.children.add(column);
    wc.node.childPropertyMap['body'] = FFChildrenKeys(
      keyRefs: [FFNodeKeyReference(key: column.key)],
    );
  } else if (bodyChild.type == FFWidgetType.Column) {
    bodyChild.children.insert(0, infoCard);
  } else {
    // Existing body is not a column — wrap it with our card prepended
    UI.expanded(bodyChild);
    final column = UI.column(name: 'ProfielBodyColumn', mainAxisMin: false);
    column.children.addAll([infoCard, bodyChild]);
    final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
    if (idx >= 0) wc.node.children[idx] = column;
    wc.node.childPropertyMap['body'] = FFChildrenKeys(
      keyRefs: [FFNodeKeyReference(key: column.key)],
    );
  }
}

// ─── NavBar + chat page AppBars ──────────────────────────────────────────────

// Adds an AppBar to TeamChatPage and DirectChatPage so users can navigate back.
// Idempotent: skipped when an appBar slot is already registered.
void _addChatPageAppBars(FFProject project) {
  // TeamChatPage AppBar is managed by _resetTeamChatAppBar (NavBar page — no back button).
  _ensureChatAppBar(project, 'DirectChatPage', 'memberName');
}

void _ensureChatAppBar(FFProject project, String pageName, String titleParamName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  if (getPropertyChild(wc.node, 'appBar') != null) return; // already set

  // Find the title param identifier.
  FFIdentifier? titleParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == titleParamName) {
      titleParamId = param.identifier.deepCopy();
      break;
    }
  }

  final titleNode = UI.text('', name: 'AppBar Title');
  if (titleParamId != null) {
    titleNode.props.text.textValue = FFStringValue(variable: varFromPageParam(titleParamId));
  }

  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);

  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Force-resets TeamChatPage's AppBar every push: NavBar page needs no back button,
// and title should come from AppState (not a page param).
void _resetTeamChatAppBar(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');

  final clubNameId = _findAppStateFieldId(project, 'clubName');
  final titleNode = UI.text('Teamchat', name: 'TeamChatTitle');
  if (clubNameId != null) {
    titleNode.props.text.textValue =
        FFStringValue(variable: varFromAppState(clubNameId.deepCopy()));
  }

  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: false);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Enables the global NavBar and registers the 4 main pages.
// Idempotent: addNavBarPage silently skips duplicates.
void _setupNavBar(FFProject project) {
  setNavBarEnabled(project, enabled: true);
  addNavBarPage(project, pageName: 'WedstrijdenPage', iconName: 'sports');
  addNavBarPage(project, pageName: 'RijschemaPage',   iconName: 'directions_car');
  addNavBarPage(project, pageName: 'BardienPage',     iconName: 'sports_bar');
  addNavBarPage(project, pageName: 'TeamChatPage',    iconName: 'chat');
  addNavBarPage(project, pageName: 'ProfielPage',     iconName: 'person');
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

  // Ensure group-level bearerToken variable exists (referenced by per-endpoint headers).
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

  // Use per-endpoint Authorization instead of a group-level shared header.
  // Login and VerifyMagicLink are public endpoints — sending an empty
  // "Authorization: Bearer " causes some proxies/WAFs to reject with 400.
  group.sharedHeaders.removeWhere((h) => h.startsWith('Authorization:'));

  const _noAuthEndpoints = {'Login', 'VerifyMagicLink', 'SendMagicLink'};
  for (final endpoint in group.endpoints) {
    endpoint.headers.removeWhere((h) => h.startsWith('Authorization:'));
    if (!_noAuthEndpoints.contains(endpoint.identifier.name)) {
      endpoint.headers.add('Authorization: Bearer [bearerToken]');
    }
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
  // Biometric button removed — each login flow is now tested independently via
  // "Inloggen beheerders" (credentials) and "Inloggen leden" (magic link).
  app.raw((project) {
    final wc = findPage(project, name: 'LoginPage');
    if (wc == null) return;
    for (final n in findDescendants(wc.node, (n) => n.name == 'BiometricLoginButton')) {
      removeByKey(wc.node, n.key);
    }
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

FFIdentifier? _findAppStateFieldId(FFProject project, String name) {
  final field = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == name,
        orElse: () => null,
      );
  return field?.parameter.identifier;
}

// ─── TeamChatPage ─────────────────────────────────────────────────────────────

void _buildTeamChatPage(App app, FirestoreCollectionHandle teamChats) {
  app.ensurePage(
    'TeamChatPage',
    description: 'Real-time team chat. Leden van hetzelfde elftal kunnen hier berichten uitwisselen.',
    route: 'team-chat',
    params: {
      'teamId': string.withDefault(''),
      'teamName': string.withDefault('Teamchat'),
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
        'Teamchat',
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

  // Use AppState currentTeamId — TeamChatPage is now a NavBar page, accessed
  // without navigation params. currentTeamId is set during login.
  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

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
          variable: varFromAppState(currentTeamIdFieldId.deepCopy()),
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

// ─── Magic link / ledenlogin ─────────────────────────────────────────────────

void _addMagicLinkInfrastructure(FFProject project) {
  if (findPubDependency(project, name: 'http') == null) {
    addPubDependency(project, name: 'http', version: '^1.2.0');
  }

  const _sendCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<bool> sendMagicLink(String? email) async {
  if (email == null || email.isEmpty) {
    debugPrint('[SendMagicLink] email is null or empty');
    return false;
  }
  try {
    debugPrint('[SendMagicLink] POST to magic-link, email=$email');
    final response = await http.post(
      Uri.parse('https://voetbalplanner.nubix.nl/api/v1/auth/magic-link'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'email': email}),
    );
    debugPrint('[SendMagicLink] status=${response.statusCode} body=${response.body}');
    return response.statusCode == 200;
  } catch (e) {
    debugPrint('[SendMagicLink] exception: $e');
    return false;
  }
}
''';

  if (findCustomAction(project, name: 'SendMagicLink') == null) {
    addCustomAction(
      project,
      name: 'SendMagicLink',
      description: 'Verstuurt een magic link naar het opgegeven e-mailadres via de Laravel API.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'email'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: _sendCode,
    );
  } else {
    updateCustomAction(project, name: 'SendMagicLink', code: _sendCode);
  }

  const _verifyCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<String> verifyMagicLink(String? token) async {
  if (token == null || token.isEmpty) return '';
  try {
    final response = await http.post(
      Uri.parse('https://voetbalplanner.nubix.nl/api/v1/auth/verify-magic-link'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'token': token}),
    );
    if (response.statusCode != 200) return '';
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (data['success'] != true) return '';
    return (data['data']?['token'] as String?) ?? '';
  } catch (_) {
    return '';
  }
}
''';

  if (findCustomAction(project, name: 'VerifyMagicLink') == null) {
    addCustomAction(
      project,
      name: 'VerifyMagicLink',
      description: 'Verifieert een magic link token. Retourneert het Sanctum bearer token bij succes, of lege string bij mislukking.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'token'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
      ),
      code: _verifyCode,
    );
  } else {
    updateCustomAction(project, name: 'VerifyMagicLink', code: _verifyCode);
  }

  // State field on LoginPage for magic link email input
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;
  _ensurePageStateField(wc, 'magicLinkEmail', FFBaseDataType.String);
}

void _ensurePageStateField(FFWidgetClass wc, String name, FFBaseDataType type) {
  final exists = wc.classModel.stateFields.any(
    (f) => f.parameter.identifier.name == name,
  );
  if (exists) return;
  wc.classModel.stateFields.add(
    FFWidgetClassStateField(
      parameter: FFParameter(
        identifier: FFIdentifier(
          name: name,
          key: generateRandomAlphaNumericString(),
        ),
        dataType: FFDataTypeV2(scalarType: type),
      ),
    ),
  );
}

// Scrollable column intentionally disabled — caused tap events on login buttons
// to be absorbed by the scroll wrapper.
void _makeLoginPageScrollable(FFProject project) {}

// Route placeholder replaced at push time with the actual WedstrijdenPage route.
const _loginWithCredentialsCodeTemplate = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:firebase_auth/firebase_auth.dart';

Future<bool> loginWithCredentials(BuildContext context, String? email, String? password) async {
  final emailVal = email ?? '';
  final passwordVal = password ?? '';

  // Capture messenger BEFORE any await — context may be unmounted after HTTP.
  final messenger = ScaffoldMessenger.of(context);

  debugPrint('[Login] email=$emailVal password=${passwordVal.isEmpty ? "empty" : "set"}');

  void showError(String msg) {
    debugPrint('[Login] $msg');
    FFAppState().update(() { FFAppState().loginError = msg; });
    messenger.showSnackBar(SnackBar(
      content: Text(msg),
      duration: const Duration(milliseconds: 6000),
    ));
  }

  if (emailVal.isEmpty || passwordVal.isEmpty) {
    showError('email=${emailVal.isEmpty ? "LEEG" : "ok"} ww=${passwordVal.isEmpty ? "LEEG" : "ok"}');
    return false;
  }

  try {
    // application/x-www-form-urlencoded is a CORS "simple request" — no preflight OPTIONS.
    // Accept: application/json tells Laravel to return JSON errors instead of redirecting.
    final response = await http.post(
      Uri.parse('https://voetbalplanner.nubix.nl/api/v1/auth/login'),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: 'email=${Uri.encodeQueryComponent(emailVal)}'
          '&password=${Uri.encodeQueryComponent(passwordVal)}',
    );

    debugPrint('[Login] status=${response.statusCode} body=${response.body}');

    if (response.statusCode != 200) {
      final snippet = response.body.length > 80 ? response.body.substring(0, 80) : response.body;
      showError('HTTP ${response.statusCode}: $snippet');
      return false;
    }

    final body = jsonDecode(response.body) as Map<String, dynamic>?;
    if (body == null || body['success'] != true) {
      showError('bad response: ${response.body.substring(0, response.body.length.clamp(0, 80))}');
      return false;
    }

    final data = (body['data'] as Map<String, dynamic>?) ?? {};
    final token = (data['token'] as String?) ?? '';
    if (token.isEmpty) {
      showError('no token in response');
      return false;
    }

    final user = (data['user'] as Map<String, dynamic>?) ?? {};
    final club = (user['club'] as Map<String, dynamic>?) ?? {};
    final firstTeamId = (user['team_id'] as String?) ?? '';

    FFAppState().update(() {
      FFAppState().loginError = '';
      FFAppState().authToken = token;
      FFAppState().userName = (user['name'] as String?) ?? '';
      FFAppState().userEmail = (user['email'] as String?) ?? '';
      FFAppState().clubName = (club['name'] as String?) ?? '';
      FFAppState().currentTeamId = firstTeamId;
    });

    // Sign in anonymously so FlutterFlow's Firebase Auth route guard (loggedIn)
    // passes. Without this, the router redirects every page back to LoginPage.
    try {
      await FirebaseAuth.instance.signInAnonymously();
      debugPrint('[Login] anonymous firebase sign-in OK');
    } catch (e) {
      debugPrint('[Login] anonymous firebase sign-in failed: $e');
    }

    debugPrint('[Login] success, token stored, returning true');
    return true;
  } catch (e) {
    debugPrint('[Login] exception: $e');
    showError('fout: $e');
    return false;
  }
}
''';

String _buildLoginCode(String wedstrijdenRoute) =>
    _loginWithCredentialsCodeTemplate.replaceAll('__WEDSTRIJDEN_ROUTE__', wedstrijdenRoute);

// Appends an AppState update action to the tail of the TextField's
// ON_TEXTFIELD_CHANGE chain. AppState survives widget rebuilds, unlike
// WIDGET_STATE (TextEditingController) which resets on any setState() call.
void _wireTextFieldToAppState(
  FFNode pageNode,
  String textFieldKey,
  FFIdentifier appStateFieldId,
) {
  final textField = findByKey(pageNode, textFieldKey);
  if (textField == null) return;
  for (final ta in textField.triggerActions) {
    if (!ta.hasTrigger()) continue;
    if (ta.trigger.triggerType != FFActionTriggerType.ON_TEXTFIELD_CHANGE) continue;
    if (!ta.hasRootAction()) continue;

    // Walk the full chain and bail if this AppState field is already wired.
    var node = ta.rootAction;
    while (true) {
      if (node.hasAction() && node.action.hasLocalStateUpdate()) {
        final lsu = node.action.localStateUpdate;
        if (lsu.stateVariableType == FFStateVariableType.APP_STATE) {
          if (lsu.updates.any((u) => u.fieldIdentifier.name == appStateFieldId.name)) {
            return;
          }
        }
      }
      if (!node.hasFollowUpAction()) break;
      node = node.followUpAction;
    }

    // `node` is the tail — append the AppState update.
    node.followUpAction = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        localStateUpdate: FFLocalStateUpdate(
          updates: [
            FFLocalStateFieldUpdate(
              fieldIdentifier: appStateFieldId.deepCopy(),
              setValue: FFValue(variable: varFromTextFieldValue(textFieldKey)),
            ),
          ],
          updateType: FFLocalStateUpdate_UpdateType.WIDGET,
          stateVariableType: FFStateVariableType.APP_STATE,
        ),
      ),
    );
    return;
  }
}

void _fixLoginButtonBindings(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  // Keep AppState fields (harmless, used elsewhere).
  _ensureAppStateField(project, 'loginEmail', FFBaseDataType.String);
  _ensureAppStateField(project, 'loginPassword', FFBaseDataType.String);
  _ensureAppStateField(project, 'loginError', FFBaseDataType.String);

  // Resolve WedstrijdenPage route so the custom action can navigate via GoRouter.
  final wedstrijdenWc = findPage(project, name: 'WedstrijdenPage');
  if (wedstrijdenWc == null) return;

  var _routePath = wedstrijdenWc.hasPageRouteSettings()
      ? wedstrijdenWc.pageRouteSettings.routePath
      : '';
  if (_routePath.isEmpty) _routePath = '/wedstrijdenPage';
  if (!_routePath.startsWith('/')) _routePath = '/$_routePath';
  final _loginCode = _buildLoginCode(_routePath);

  // LoginWithCredentials takes email and password as direct arguments.
  // Stable keys required: FFFunctionCallValues.arguments is keyed by
  // parameter identifier.key — action definition and call site must match.
  const _emailArgKey = 'login_cred_email';
  const _passwordArgKey = 'login_cred_password';
  final _loginArgs = [
    FFParameter(
      identifier: FFIdentifier(name: 'email', key: _emailArgKey),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    ),
    FFParameter(
      identifier: FFIdentifier(name: 'password', key: _passwordArgKey),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    ),
  ];

  if (findCustomAction(project, name: 'LoginWithCredentials') == null) {
    addCustomAction(
      project,
      name: 'LoginWithCredentials',
      description:
          'Logt in met email en wachtwoord. Retourneert true bij succes, false bij mislukte login.',
      arguments: _loginArgs,
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      includeContext: true,
      code: _loginCode,
    );
  } else {
    // Always sync code, arguments and includeContext so they remain consistent across pushes.
    updateCustomAction(
      project,
      name: 'LoginWithCredentials',
      code: _loginCode,
      arguments: _loginArgs,
      includeContext: true,
    );
  }

  final loginAction = findCustomAction(project, name: 'LoginWithCredentials');
  if (loginAction == null) return;

  final loginButton = findByKey(wc.node, 'Button_bg6zh5x9');
  if (loginButton == null) return;

  // Keys used across the action chain.
  final actionNodeKey = generateRandomAlphaNumericString();
  final actionKey = generateRandomAlphaNumericString();

  FFAppStateField? _findField(String name) => project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == name, orElse: () => null);

  final loginEmailField    = _findField('loginEmail');
  final loginPasswordField = _findField('loginPassword');

  // Arguments read from AppState (updated by onChange and pre-sync action below).
  final emailArgVar = loginEmailField != null
      ? varFromAppState(loginEmailField.parameter.identifier.deepCopy())
      : varFromTextFieldValue('TextField_73irroiw');
  final passwordArgVar = loginPasswordField != null
      ? varFromAppState(loginPasswordField.parameter.identifier.deepCopy())
      : varFromTextFieldValue('TextField_v1ycg741');

  final argValues = FFFunctionCallValues();
  argValues.arguments[_emailArgKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: emailArgVar),
  );
  argValues.arguments[_passwordArgKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: passwordArgVar),
  );

  // Navigation via FlutterFlow's own Actions.navigate as a direct followUpAction.
  // Error display is handled inside the custom action via ScaffoldMessenger.
  // Note: followUpAction always fires (even on failed login) — acceptable for now
  // while testing that FF navigation works; conditionality can be added once confirmed.
  final customActionNode = FFActionNode(
    key: actionNodeKey,
    action: FFAction(
      key: actionKey,
      customAction: FFCustomActionCall(
        customActionIdentifier: loginAction.identifier.deepCopy(),
        argumentValues: argValues,
      ),
    ),
    followUpAction: FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.navigate(
        project,
        pageName: 'WedstrijdenPage',
        replaceRoute: true,
      ),
    ),
  );

  // Pre-sync: at button-press time, copy the current TextField values into
  // AppState before the custom action reads them. This captures browser
  // autofill which does not fire onChange (so AppState would otherwise be
  // stale). localStateUpdate with WIDGET_STATE reads the TextEditingController
  // directly, picking up autofilled values.
  final syncUpdates = <FFLocalStateFieldUpdate>[
    if (loginEmailField != null)
      FFLocalStateFieldUpdate(
        fieldIdentifier: loginEmailField.parameter.identifier.deepCopy(),
        setValue: FFValue(variable: varFromTextFieldValue('TextField_73irroiw')),
      ),
    if (loginPasswordField != null)
      FFLocalStateFieldUpdate(
        fieldIdentifier: loginPasswordField.parameter.identifier.deepCopy(),
        setValue: FFValue(variable: varFromTextFieldValue('TextField_v1ycg741')),
      ),
  ];

  final chain = syncUpdates.isEmpty
      ? customActionNode
      : FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: FFAction(
            key: generateRandomAlphaNumericString(),
            localStateUpdate: FFLocalStateUpdate(
              updates: syncUpdates,
              updateType: FFLocalStateUpdate_UpdateType.WIDGET,
              stateVariableType: FFStateVariableType.APP_STATE,
            ),
          ),
          followUpAction: customActionNode,
        );

  loginButton.triggerActions.removeWhere(
    (ta) => ta.hasTrigger() && ta.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  loginButton.triggerActions.add(
    FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: chain,
    ),
  );
}

// If rootAction is a non-API-call action (e.g. isLoading=true) whose immediate
// followUpAction is the Login API call, promote the API call to root position.
// This drops the pre-API-call setup action so no setState() fires before
// WIDGET_STATE TextField values are read.
void _ensureApiCallFirst(FFActionNode root) {
  if (!root.hasAction()) return;
  if (root.action.hasDatabase() && root.action.database.hasApiCall()) return;
  if (!root.hasFollowUpAction()) return;

  final next = root.followUpAction;
  if (!next.hasAction()) return;
  if (!next.action.hasDatabase()) return;
  if (!next.action.database.hasApiCall()) return;

  final promotedContent = next.deepCopy();
  final savedKey = root.key;
  root.clear();
  root.key = savedKey;
  root.mergeFromMessage(promotedContent);
}

void _repairLoginApiCallBindings(
  FFActionNode node,
  FFIdentifier emailVarId,
  FFIdentifier passwordVarId,
  FFIdentifier loginEmailAppStateId,
  FFIdentifier loginPasswordAppStateId,
) {
  if (node.hasAction() &&
      node.action.hasDatabase() &&
      node.action.database.hasApiCall()) {
    final apiCall = node.action.database.apiCall;

    void _repair(FFIdentifier varId, FFIdentifier appStateId) {
      final existing = apiCall.variables.cast<FFApiCallValue?>().firstWhere(
        (v) => v?.variableIdentifier.name == varId.name,
        orElse: () => null,
      );
      final binding = varFromAppState(appStateId);
      if (existing == null) {
        apiCall.variables.add(FFApiCallValue(
          variableIdentifier: varId.deepCopy(),
          variable: binding,
        ));
      } else {
        existing.clearValue();
        existing.variable = binding;
      }
    }

    _repair(emailVarId, loginEmailAppStateId);
    _repair(passwordVarId, loginPasswordAppStateId);
  }

  if (node.hasFollowUpAction()) {
    _repairLoginApiCallBindings(
      node.followUpAction,
      emailVarId,
      passwordVarId,
      loginEmailAppStateId,
      loginPasswordAppStateId,
    );
  }
}

void _addLedenLoginSection(App app) {
  app.editPage('LoginPage', (page) {
    final loginButton = page.findByKey('Button_bg6zh5x9');
    page.ensureInsertedAfter(
      loginButton,
      Column(
        name: 'LedenLoginSection',
        spacing: 12,
        children: [
          Divider(),
          Text(
            'of login als lid',
            style: Styles.bodyMedium,
            name: 'LedenLoginLabel',
          ),
          TextField(
            hint: 'E-mailadres',
            name: 'MagicLinkEmailField',
            onChanged: [SetState('magicLinkEmail', TextValue())],
          ),
          Button(
            'Stuur inloglink',
            name: 'SendMagicLinkButton',
            icon: 'mail_outline',
            width: double.infinity,
            color: Colors.secondary,
            textColor: Colors.primaryBackground,
            borderRadius: 8,
            onTap: [
              CallCustomAction.named(
                'SendMagicLink',
                returnType: bool_,
                arguments: {'email': State('magicLinkEmail')},
                outputAs: 'magicLinkSendResult',
              ),
              If(
                ActionOutput('magicLinkSendResult'),
                then: Snackbar('E-mail verstuurd! Controleer uw inbox voor de inloglink.'),
                orElse: Snackbar('Kon geen inloglink versturen. Probeer het opnieuw.'),
              ),
            ],
          ),
        ],
      ),
    );
  });
}

// Updates button/label text to "Inloggen beheerders" / "Inloggen leden".
// Called after _addLedenLoginSection so LedenLoginSection exists on first push.
void _fixLoginPageLabels(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  final loginButton = findByKey(wc.node, 'Button_bg6zh5x9');
  if (loginButton != null && loginButton.props.hasButton()) {
    loginButton.props.button.text.textValue =
        FFStringValue(inputValue: 'Inloggen beheerders');
  }

  final labelNode = findDescendants(wc.node, (n) => n.name == 'LedenLoginLabel').firstOrNull;
  if (labelNode != null && labelNode.props.hasText()) {
    labelNode.props.text.textValue =
        FFStringValue(inputValue: 'Inloggen leden');
  }
}

void _buildMagicLinkVerifyPage(App app) {
  // Create page without onLoad so the body compiles independently of action
  // lookups. The onLoad (which calls VerifyMagicLink) is wired via
  // app.editPageOnLoad — same pattern as TeamChatPage + SubscribeToTeamTopic.
  app.ensurePage(
    'MagicLinkVerifyPage',
    description: 'Verwerkt een magic link token uit de e-mail deep link en logt de gebruiker automatisch in.',
    route: 'verify',
    params: {
      'token': string.withDefault(''),
    },
    body: Column(
      mainAxis: MainAxis.center,
      spacing: 16,
      children: [
        ProgressBar.circular(size: 40, thickness: 4),
        Text(
          'Inloglink verifiëren...',
          style: Styles.bodyMedium,
          name: 'VerifyingText',
        ),
      ],
    ),
  );

  app.editPageOnLoad('MagicLinkVerifyPage', [
    CallCustomAction.named(
      'VerifyMagicLink',
      returnType: string,
      arguments: {'token': Param('token')},
      outputAs: 'sanctumToken',
    ),
    If(
      Not(Equals(ActionOutput('sanctumToken'), '')),
      then: [
        UpdateAppState.set('authToken', ActionOutput('sanctumToken')),
        Navigate('WedstrijdenPage', replaceRoute: true),
      ],
      orElse: [
        Snackbar('Deze inloglink is ongeldig of verlopen.'),
        Navigate('LoginPage', replaceRoute: true),
      ],
    ),
  ]);
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
