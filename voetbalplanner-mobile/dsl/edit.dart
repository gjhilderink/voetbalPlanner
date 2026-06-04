library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/client/project_error.dart' show ProjectError;
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart'
    show findCollectionField;
import 'package:flutterflow_ai/src/helpers/data_schema_helpers.dart'
    show addDataStruct, structField;
import 'package:flutterflow_ai/src/helpers/data_type_helpers.dart'
    show dataStructType, stringType;
import 'package:flutterflow_ai/src/helpers/ensure_helpers.dart'
    show ensureDataStruct;
import 'package:flutterflow_ai/src/helpers/function_call_helpers.dart'
    show CodeExpressionArg, codeExpressionVar, colorFromStringVar, interpolateVar;
import 'package:flutterflow_ai/src/helpers/param_value.dart'
    show StaticParamValue, VariableParamValue;
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
    show UIColor, UITextStyle, UIMainAxisAlignment, UICrossAxisAlignment, UIEdgeInsets;
import 'package:voetbalplanner_mobile/flutterflow_project.dart' as ff;

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
    _setupBardienFilter(project);
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

    // Upcoming matches filter: showAllMatches state + toggle + visibility
    _addUpcomingFilter(project);

    // Welcome greeting at the top of WedstrijdenPage
    _addWelcomeGreeting(project);

    // Profile page: bind AppState fields (naam, e-mail, club)
    _setupProfielPage(project);

    // Bardiensten: filter by member's team + update onLoad
    _setupBardienFilter(project);

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

  // Chat: Firestore collections — use typed SDK handles; attempt creation for new projects.
  final teamChats = ff.Collections.teamChats;
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
  } catch (_) {}

  final directMessages = ff.Collections.directMessages;
  try {
    app.collection(
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
  } catch (_) {}

  // Chat pages
  _buildTeamChatPage(app, teamChats);
  _buildDirectChatPage(app, directMessages);
  _buildMagicLinkVerifyPage(app);

  // editPageOnLoad REPLACES the full onLoad on every push, which lets us keep the
  // collection reference fresh. FCM subscription uses editPageOnLoad because
  // CallCustomAction inside ensurePage onLoad would fail before app.raw() creates it.
  app.editPageOnLoad(ff.Pages.teamChatPage, [
    FirestoreQuery(
      teamChats,
      limit: 100,
      singleTimeQuery: true,
      outputAs: 'loadedMessages',
    ),
    SetState(ff.Pages.teamChatPage.state.chatMessages, ActionOutput('loadedMessages')),
    CallCustomAction.named(
      'SubscribeToTeamTopic',
      arguments: {'teamId': AppState(ff.AppState.currentTeamId)},
    ),
  ]);

  // Add teamId filter AFTER page() has written the fresh TeamChatPage.
  // Also fix login page labels here — after _addLedenLoginSection ran.
  app.raw((project) {
    _addTeamChatFilters(project);
    _fixLoginPageLabels(project);
    _resetTeamChatAppBar(project);
    _addDocumentatieAppBar(project);
    _fixChatTimestamp(project);
  });

  // Chat navigation button on WedstrijdenPage
  // (ChatMenuSheet with DirectChatPage navigate deferred to next push)
  _addChatButton(app);

  // Documentation page for members + handleiding button on ProfielPage.
  // Struct must be declared before the raw endpoint and page so the type
  // is available when _addDocumentationEndpoint references it by name.
  final documentSection = ff.Structs.documentSection;
  try {
    app.struct('DocumentSection', {
      'id':       string,
      'category': string,
      'title':    string,
      'body':     string,
    });
  } catch (_) {}
  app.raw((project) => _addDocumentationEndpoint(project));
  _buildDocumentatiePage(app, documentSection);
  app.raw((project) => _wireDocumentationPageLoad(project));
  app.raw((project) => _addHandleidingButton(project));

  // ─── Wissel (swap) feature ────────────────────────────────────────────────
  // Must run after struct/page declarations above so existing names compile.
  app.raw((project) => _addSwapStructFields(project));
  app.raw((project) => _addSwapParamsToBarDutyCard(project));

  final swapMember = ff.Structs.swapMember;
  try {
    app.struct('SwapMember', {
      'id':   string,
      'name': string,
    });
  } catch (_) {}

  final swapRequest = ff.Structs.swapRequest;
  try {
    app.struct('SwapRequest', {
      'id':                string,
      'type':              string,
      'typeLabel':         string,
      'targetId':          string,
      'targetDescription': string,
      'requesterName':     string,
      'requesteeName':     string,
      'status':            string,
      'message':           string,
      'date':              string,
    });
  } catch (_) {}

  app.raw((project) => _addSwapEndpoints(project));
  _buildSwapRequestCard(app, swapRequest);
  _buildWisselAanvraagPage(app, swapMember);
  _buildWisselVerzoekenPage(app, swapRequest);

  // MatchCard: add matchId param and navigate internally.
  app.editComponentParams(ff.Components.matchCard, (params) {
    params.ensureParam('matchId', string.withDefault(''), description: 'Match ID for navigation');
  });
  // Remove unused action params idempotently (removeParam throws if already gone).
  app.raw((project) => _removeComponentParamIfExists(project, 'MatchCard', 'onTapAction'));
  app.editComponent(ff.Components.matchCard, (c) {
    c.ensureActions(
      c.findByKey('Container_oa8ojh9i'),
      triggerType: FFActionTriggerType.ON_TAP,
      actions: [Navigate(ff.Pages.wedstrijdDetailPage, params: {'matchId': Param(ff.Components.matchCard.params.matchId)})],
    );
  });

  // WedstrijdDetailPage must exist before match navigation can be set up.
  _buildWedstrijdDetailPage(app);
  // Bind matchId on the MatchCard instance via the DSL compile path (setComponentParam
  // compiles ItemRef()['id'] through the proper generator context).
  app.editPage(ff.Pages.wedstrijdenPage, (page) {
    page.setComponentParam(
      page.findByKey('Container_f1p12fqf'),
      'matchId',
      ItemRef()['id'],
    );
  });
  app.raw((project) {
    _wireWedstrijdDetailPageLoad(project);
    _bindWedstrijdDetailAppBarTitle(project);
    _bindWedstrijdDetailInfoTexts(project);
  });

  // BarDutyCard: add barDutyId/barDutyDate params and navigate internally.
  app.editComponentParams(ff.Components.barDutyCard, (params) {
    params.ensureParam('barDutyId', string.withDefault(''), description: 'Bar duty ID for navigation');
    params.ensureParam('barDutyDate', string.withDefault(''), description: 'Bar duty date for swap request label');
  });
  // Remove unused action params idempotently.
  app.raw((project) {
    _removeComponentParamIfExists(project, 'BarDutyCard', 'onTapAction');
    _removeComponentParamIfExists(project, 'BarDutyCard', 'onSwapAction');
  });
  app.editComponent(ff.Components.barDutyCard, (c) {
    c.ensureActions(
      c.findByKey('Container_itc21arg'),
      triggerType: FFActionTriggerType.ON_TAP,
      actions: [Navigate(ff.Pages.bardienDetailPage, params: {'dutyId': Param(ff.Components.barDutyCard.params.barDutyId)})],
    );
    c.ensureInsertedAfter(
      c.findByKey('Text_k81dicy1'),
      Button(
        'Wissel aanvragen',
        name: 'WisselAanvraagButton',
        visible: Param(ff.Components.barDutyCard.params.isAssignedToMe),
        onTap: Navigate(
          ff.Pages.wisselAanvraagPage,
          params: {
            'dutyType': 'bardienst',
            'targetId': Param(ff.Components.barDutyCard.params.barDutyId),
            'targetLabel': Param(ff.Components.barDutyCard.params.barDutyDate),
          },
        ),
        width: double.infinity,
        padding: 16,
      ),
    );
  });

  // Wire BardienPage: bind isAssignedToMe + onSwapAction on BarDutyCard instance.
  app.raw((project) => _wireBarDutySwap(project));

  // BardienDetailPage: new page for full bar duty info.
  _buildBardienDetailPage(app);
  app.raw((project) {
    _wireBardienDetailPageLoad(project);
    _wireBardienDetailPageUI(project);
    _addBardienNavigation(project);
  });

  // RijschemaDetailPage: new page for driving assignment details.
  _buildRijschemaDetailPage(app);
  app.raw((project) {
    _wireRijschemaDetailPageLoad(project);
    _wireRijschemaDetailPageUI(project);
    _wireRijschemaNavigation(project);
  });

  // Wire WedstrijdDetailPage: add fruitheld + rijden swap buttons into MatchInfoColumn.
  app.raw((project) => _wireMatchSwap(project));
  // Fix ListView generator variable names (same codegen bug as existing pages).
  app.raw((project) {
    _fixListViewItemNameByNodeName(project, 'WisselAanvraagPage',  'TeamMembersListView',  'member');
    _fixListViewItemNameByNodeName(project, 'WisselVerzoekenPage', 'SwapRequestsListView', 'swapReq');
  });
  // Wire swap pages: page loads + button actions (run after _addSwapEndpoints).
  app.raw((project) => _wireWisselAanvraagPageLoad(project));
  app.raw((project) => _wireWisselAanvraagButton(project));
  app.raw((project) => _wireWisselVerzoekenPageLoad(project));
  app.raw((project) => _wireWisselVerzoekenActions(project));

  // Apply club primary color to all AppBar backgrounds; set back button + title to white.
  // Runs last so the AppBar nodes already exist from all the preceding wiring steps.
  app.raw((project) => _applyBrandingToAllAppBars(project));
}

// ─── Match navigation ─────────────────────────────────────────────────────────

// Binds matchId variable on the MatchCard instance so the component's internal
// Navigate action receives the correct match ID from the list generator.
void _addMatchNavigation(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;
  final listView = findByKey(wc.node, 'ListView_erdckv6e');
  if (listView == null || listView.children.isEmpty) return;
  final itemTemplate = listView.children.first;

  final componentClassKey = itemTemplate.componentClassKeyRef.key;
  final matchCardClass = project.widgetClasses[componentClassKey];
  if (matchCardClass == null) return;

  FFParameter? matchIdParam;
  for (final candidate in matchCardClass.params.values) {
    if (candidate.hasIdentifier() && candidate.identifier.name == 'matchId') {
      matchIdParam = candidate;
      break;
    }
  }
  if (matchIdParam == null) return;

  if (!itemTemplate.hasParameterValues()) {
    itemTemplate.parameterValues = FFPassedParameters(
      widgetClassNodeKeyRef: FFNodeKeyReference(key: componentClassKey),
    );
  }

  // Use a properly-keyed field identifier so FlutterFlow codegen can resolve
  // the field access (name-only FFIdentifier is not reliably resolved server-side).
  final idFieldId = _findStructFieldId(project, 'FootMatch', 'id');
  itemTemplate.parameterValues.parameterPasses[matchIdParam.identifier.key] =
      FFParameterPass(
        paramIdentifier: matchIdParam.identifier.deepCopy(),
        variable: idFieldId != null
            ? (varFromGeneratorVariable('ListView_erdckv6e')
                ..operations.add(FFVariableOperation(
                  accessDataStructField: FFAccessDataStructField(
                    fieldIdentifier: idFieldId.deepCopy(),
                  ),
                )))
            : generatorVarField('ListView_erdckv6e', 'id'),
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

  final userNameId = _findAppStateFieldId(project, 'userName');
  final clubNameId = _findAppStateFieldId(project, 'clubName');

  final existing = findDescendants(wc.node, (n) => n.name == 'WelcomeGreetingContainer');
  if (existing.isNotEmpty) {
    final container = existing.first;
    // Remove any previously set background color so the container is white/transparent.
    if (container.props.container.hasBoxDecoration()) {
      container.props.container.boxDecoration.clearColorValue();
    }
    _rebuildWelcomeGreetingContent(container, userNameId, clubNameId);
    return;
  }

  if (userNameId == null) return;

  final greetingContainer = UI.container(
    name: 'WelcomeGreetingContainer',
    child: UI.column(
      name: 'WelcomeGreetingColumn',
      crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 4,
      children: [],
    ),
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 12),
    width: double.infinity,
  );
  // No background color — inherits white page background.

  _rebuildWelcomeGreetingContent(greetingContainer, userNameId, clubNameId);

  final showAllRow = findDescendants(wc.node, (n) => n.name == 'ShowAllMatchesRow').firstOrNull;
  if (showAllRow != null) {
    insertBeforeKey(wc.node, showAllRow.key, greetingContainer);
  }
}

void _rebuildWelcomeGreetingContent(
  FFNode container,
  FFIdentifier? userNameId,
  FFIdentifier? clubNameId,
) {
  // Find or create the inner column.
  FFNode col = container.children.isNotEmpty
      ? container.children.first
      : UI.column(name: 'WelcomeGreetingColumn', crossAxisAlignment: UICrossAxisAlignment.start, spacing: 4, children: []);
  if (container.children.isEmpty) container.children.add(col);

  col.children.clear();

  // Club name sub-label.
  if (clubNameId != null) {
    final clubText = UI.text(
      '',
      name: 'WelcomeClubText',
      style: UITextStyle.bodySmall,
    );
    clubText.props.text.textValue = FFStringValue(variable: varFromAppState(clubNameId.deepCopy()));
    col.children.add(clubText);
  }

  // Greeting line.
  if (userNameId != null) {
    final greetingText = UI.text(
      'Welkom!',
      name: 'WelcomeGreetingText',
      style: UITextStyle.titleMedium,
    );
    greetingText.props.text.textValue = interpolateVar([
      'Welkom, ',
      varFromAppState(userNameId.deepCopy()),
      '!',
    ]);
    col.children.add(greetingText);
  }
}

// ─── ProfielPage: bind AppState data ─────────────────────────────────────────

void _setupProfielPage(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;

  final userNameId      = _findAppStateFieldId(project, 'userName');
  final userEmailId     = _findAppStateFieldId(project, 'userEmail');
  final clubNameId      = _findAppStateFieldId(project, 'clubName');
  final secondaryColorId = _findAppStateFieldId(project, 'secondaryColor');

  final existingCards = findDescendants(wc.node, (n) => n.name == 'ProfielInfoCard');

  if (existingCards.isNotEmpty) {
    // Card already exists — update secondary color.
    if (secondaryColorId != null) {
      _setContainerColor(existingCards.first,
          colorFromStringVar(varFromAppState(secondaryColorId.deepCopy())));
    }
    return;
  }

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
        _boundText('ProfielNaam',  userNameId,  'Naam',        UITextStyle.titleLarge),
        _boundText('ProfielEmail', userEmailId, 'E-mailadres', UITextStyle.bodyMedium),
        _boundText('ProfielClub',  clubNameId,  'Club',        UITextStyle.bodyMedium),
      ],
    ),
  );
  if (secondaryColorId != null) {
    _setContainerColor(infoCard,
        colorFromStringVar(varFromAppState(secondaryColorId.deepCopy())));
  }

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

// ─── BardienPage: filter by member's own team ────────────────────────────────

void _setupBardienFilter(FFProject project) {
  final group = findApiGroup(project, name: 'VoetbalPlannerAPI');
  if (group == null) return;

  final authTokenId = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'authToken', orElse: () => null)
      ?.parameter.identifier;
  if (authTokenId == null) return;

  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');

  // 1. Ensure GetBarDuties endpoint has team_id variable + URL param.
  final existingEp = group.endpoints
      .cast<FFApiEndpoint?>()
      .firstWhere((ep) => ep?.identifier.name == 'GetBarDuties', orElse: () => null);

  if (existingEp != null) {
    if (!existingEp.url.contains('team_id=')) {
      existingEp.url = '${existingEp.url}&team_id=[teamId]';
    }
    if (!existingEp.variables.any((v) => v.identifier.name == 'teamId')) {
      existingEp.variables.add(FFApiValue(
        identifier: FFIdentifier(name: 'teamId', key: generateRandomAlphaNumericString()),
        type: FFBaseDataType.String,
      ));
    }
  }

  // 2. Replace BardienPage onLoad to include teamId from AppState.
  final wc = findPage(project, name: 'BardienPage');
  if (wc == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetBarDuties',
      groupName: 'VoetbalPlannerAPI',
      variables: {'page': '1'},
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        if (currentTeamIdId != null) 'teamId': varFromAppState(currentTeamIdId.deepCopy()),
      },
      outputVariableName: 'dutiesLoad',
      nodeKey: 'Scaffold_ljui3hun',
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'BardienPage',
          updates: [
            StateFieldUpdate.setFromVariable('duties', ctx.responseVar),
            StateFieldUpdate.set('isLoading', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'BardienPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon bardiensten niet laden.'),
      ]),
    ),
  );
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

// Force-resets TeamChatPage's AppBar every push: NavBar page needs no back button.
// Title comes from AppState.clubName so it works whether accessed via NavBar or Navigate.
void _resetTeamChatAppBar(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');

  // Bind title to AppState.currentTeamName — set at login, persisted across restarts.
  final teamNameFieldId = _findAppStateFieldId(project, 'currentTeamName');

  final titleNode = UI.text('Teamchat', name: 'TeamChatTitle');
  if (teamNameFieldId != null) {
    titleNode.props.text.textValue =
        FFStringValue(variable: varFromAppState(teamNameFieldId.deepCopy()));
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

/// Marks an existing AppState field as persisted (survives app restarts).
/// Uses deepCopy to avoid frozen-proto mutation errors.
void _makeAppStateFieldPersisted(FFProject project, String name) {
  final idx = project.appState.fields
      .indexWhere((f) => f.parameter.identifier.name == name);
  if (idx < 0) return;
  if (project.appState.fields[idx].persisted) return;
  final copy = project.appState.fields[idx].deepCopy();
  copy.persisted = true;
  project.appState.fields[idx] = copy;
}

/// Sets (or replaces) a container's background color using a deepCopy to avoid
/// mutating frozen protobuf messages.
void _setContainerColor(FFNode node, FFColorValue color) {
  final bd = node.props.container.hasBoxDecoration()
      ? node.props.container.boxDecoration.deepCopy()
      : FFBoxDecoration();
  bd.colorValue = color;
  node.props.container.boxDecoration = bd;
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
      SetState(ff.Pages.teamChatPage.state.chatMessages, ActionOutput('loadedMessages')),
    ],
    body: Column(
      children: [
        // Messages list
        Expanded(
          ListView(
            source: State(ff.Pages.teamChatPage.state.chatMessages),
            padding: 12,
            spacing: 8,
            itemBuilder: (_) => Column(
              crossAxis: CrossAxis.stretch,
              children: [
                // Others' message — left-aligned, sender name visible
                Row(
                  mainAxis: MainAxis.start,
                  visible: Not(Equals(ItemRef()['senderId'], AppState(ff.AppState.authToken))),
                  children: [
                    Container(
                      padding: 12,
                      borderRadius: 12,
                      color: Colors.secondaryBackground,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        spacing: 4,
                        children: [
                          Text(
                            ItemRef()['senderName'],
                            style: Styles.labelMedium,
                            color: Colors.primary,
                          ),
                          Text(
                            ItemRef()['text'],
                            style: Styles.bodyMedium,
                          ),
                          Text(
                            ItemRef()['createdAt'],
                            style: Styles.bodySmall,
                            color: Colors.secondaryText,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                // Own message — right-aligned, no sender name
                Row(
                  mainAxis: MainAxis.end,
                  visible: Equals(ItemRef()['senderId'], AppState(ff.AppState.authToken)),
                  children: [
                    Container(
                      padding: 12,
                      borderRadius: 12,
                      color: Colors.primary,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        spacing: 4,
                        children: [
                          Text(
                            ItemRef()['text'],
                            style: Styles.bodyMedium,
                            color: Colors.primaryBackground,
                          ),
                          Text(
                            ItemRef()['createdAt'],
                            style: Styles.bodySmall,
                            color: Colors.primaryBackground,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
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
                  onChanged: [SetState(ff.Pages.teamChatPage.state.messageText, TextValue())],
                ),
              ),
              IconButton(
                'send',
                color: Colors.primary,
                onTap: [
                  If(
                    Not(Equals(State(ff.Pages.teamChatPage.state.messageText), '')),
                    then: [
                      FirestoreCreate(
                        teamChats,
                        fields: {
                          'text': State(ff.Pages.teamChatPage.state.messageText),
                          'senderId': AppState(ff.AppState.authToken),
                          'senderName': AppState(ff.AppState.userName),
                          'teamId': Param('teamId'),
                          'createdAt': Global(GlobalProperty.currentTimestamp),
                        },
                      ),
                      SetState.clear(ff.Pages.teamChatPage.state.messageText),
                      // Refresh message list after sending
                      FirestoreQuery(
                        teamChats,
                        limit: 100,
                        singleTimeQuery: true,
                        outputAs: 'refreshed',
                      ),
                      SetState(ff.Pages.teamChatPage.state.chatMessages, ActionOutput('refreshed')),
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

// ─── Documentatie ────────────────────────────────────────────────────────────

void _addDocumentationEndpoint(FFProject project) {
  const groupName    = 'VoetbalPlannerAPI';
  const endpointName = 'GetDocumentation';

  if (findApiEndpoint(project, name: endpointName, groupName: groupName) != null) return;

  final group = findApiGroup(project, name: groupName);
  if (group == null) return;

  addEndpointToGroup(
    project,
    groupName:                groupName,
    name:                     endpointName,
    url:                      '/documentation',
    method:                   FFApiEndpoint_CallType.GET,
    bodyType:                 FFApiEndpoint_BodyType.NONE,
    headers:                  ['Authorization: Bearer [bearerToken]'],
    responseDataStructName:   'DocumentSection',
    responseDataStructIsList: true,
  );
}

void _buildDocumentatiePage(App app, StructHandle documentSection) {
  app.ensurePage(
    'DocumentatiePage',
    description: 'Handleiding — uitleg over de app, het platform en de koppelingen.',
    route: 'documentatie',
    state: {
      'sections': listOf(documentSection),
      'isLoading': bool_.withDefault(true),
    },
    body: Column(
      children: [
        // Loading indicator
        Row(
          mainAxis: MainAxis.center,
          visible: State(ff.Pages.documentatiePage.state.isLoading),
          children: [ProgressBar.circular(size: 40, thickness: 4)],
        ),
        // Documentation list
        Expanded(
          ListView(
            source: State(ff.Pages.documentatiePage.state.sections),
            padding: EdgeInsets.all(12),
            spacing: 12,
            visible: Not(State(ff.Pages.documentatiePage.state.isLoading)),
            itemBuilder: (_) => Container(
              padding: 16,
              borderRadius: 10,
              color: Colors.secondaryBackground,
              child: Column(
                crossAxis: CrossAxis.start,
                spacing: 8,
                children: [
                  Text(
                    ItemRef()['title'],
                    style: Styles.titleSmall,
                    color: Colors.primary,
                  ),
                  Text(
                    ItemRef()['body'],
                    style: Styles.bodySmall,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

// Wires the onLoad API call for DocumentatiePage using the VoetbalPlannerAPI group.
// Must run after _addDocumentationEndpoint so the endpoint exists in the group.
// Auth is handled by the group-level bearerToken shared variable — no per-endpoint token needed.
void _wireDocumentationPageLoad(FFProject project) {
  final wc = findPage(project, name: 'DocumentatiePage');
  if (wc == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  final scaffoldKey = wc.node.key;

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetDocumentation',
      groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'docsLoad',
      nodeKey: scaffoldKey,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'DocumentatiePage',
          updates: [
            StateFieldUpdate.setFromVariable('sections', ctx.responseVar),
            StateFieldUpdate.set('isLoading', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'DocumentatiePage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
      ]),
    ),
  );
}

// Adds an AppBar with title and back button to DocumentatiePage every push.
void _addDocumentatieAppBar(FFProject project) {
  final wc = findPage(project, name: 'DocumentatiePage');
  if (wc == null) return;
  if (getPropertyChild(wc.node, 'appBar') != null) return;

  final titleNode = UI.text('Handleiding', name: 'DocumentatieTitle');
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Adds a "Handleiding" navigation button at the bottom of ProfielPage.
// Re-wires the Navigate action on every push so the target is always fresh.
void _addHandleidingButton(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;

  final docsPage = project.getWidgetClassByName('DocumentatiePage');
  if (docsPage == null) return;

  final navigateAction = Actions.navigate(project, pageName: 'DocumentatiePage');

  // If button already exists: re-wire its tap action and return.
  final existing = findDescendants(wc.node, (n) => n.name == 'HandleidingButton');
  if (existing.isNotEmpty) {
    final btn = existing.first;
    btn.triggerActions.removeWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    Actions.onTap(btn, navigateAction);
    return;
  }

  final button = UI.button(
    'Handleiding bekijken',
    name: 'HandleidingButton',
  );
  Actions.onTap(button, navigateAction);

  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild != null && bodyChild.type == FFWidgetType.Column) {
    bodyChild.children.add(button);
  } else {
    wc.node.children.add(button);
  }
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
      SetState(ff.Pages.directChatPage.state.chatMessages, ActionOutput('loadedMessages')),
    ],
    body: Column(
      children: [
        // Messages list
        Expanded(
          ListView(
            source: State(ff.Pages.directChatPage.state.chatMessages),
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
                  onChanged: [SetState(ff.Pages.directChatPage.state.messageText, TextValue())],
                ),
              ),
              IconButton(
                'send',
                color: Colors.primary,
                onTap: [
                  If(
                    Not(Equals(State(ff.Pages.directChatPage.state.messageText), '')),
                    then: [
                      FirestoreCreate(
                        directMessages,
                        fields: {
                          'text': State(ff.Pages.directChatPage.state.messageText),
                          'senderId': AppState(ff.AppState.authToken),
                          'senderName': AppState(ff.AppState.userName),
                          'receiverId': Param('memberId'),
                          'createdAt': Global(GlobalProperty.currentTimestamp),
                        },
                      ),
                      SetState.clear(ff.Pages.directChatPage.state.messageText),
                      FirestoreQuery(
                        directMessages,
                        limit: 100,
                        singleTimeQuery: true,
                        outputAs: 'refreshed',
                      ),
                      SetState(ff.Pages.directChatPage.state.chatMessages, ActionOutput('refreshed')),
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
  app.editPage(ff.Pages.wedstrijdenPage, (page) {
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
            ff.Pages.teamChatPage,
            params: {
              'teamId': AppState(ff.AppState.currentTeamId),
              'teamName': AppState(ff.AppState.clubName),
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

// Like _fixItemName + _wrapListViewVisibility combined, for DSL-created
// ListViews whose keys are randomly generated.
// Fixes identifier.name AND moves visibility (+ expanded flag) to a wrapper
// Container, which is required to get the local-variable codegen pattern.
void _fixListViewItemNameByNodeName(
  FFProject project,
  String pageName,
  String listViewNodeName,
  String itemName,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final listView = findDescendants(wc.node, (n) => n.name == listViewNodeName).firstOrNull;
  if (listView == null) return;

  if (listView.hasGeneratorVariable()) {
    listView.generatorVariable.identifier.name = itemName;
  }

  // Wrap in a Container and move visibility (+ expanded) to it, exactly like
  // _wrapListViewVisibility. Idempotent: skips if no visibility is set.
  if (!listView.props.hasVisibility()) return;

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

  // Move the Expanded flag so the wrapper fills the flex space, not the ListView.
  if (listView.props.hasExpanded()) {
    wrapper.props.expanded = listView.props.expanded;
    listView.props.clearExpanded();
  }

  replaceByKey(wc.node, listView.key, wrapper);
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
    final responseData = (data['data'] as Map<String, dynamic>?) ?? {};
    final sanctumToken = (responseData['token'] as String?) ?? '';
    if (sanctumToken.isEmpty) return '';
    final user = (responseData['user'] as Map<String, dynamic>?) ?? {};
    final club = (user['club'] as Map<String, dynamic>?) ?? {};
    FFAppState().update(() {
      FFAppState().userName       = (user['name']   as String?) ?? '';
      FFAppState().userEmail      = (user['email']  as String?) ?? '';
      FFAppState().clubName       = (club['name']   as String?) ?? '';
      FFAppState().currentTeamId   = (user['team_id']   as String?) ?? '';
      FFAppState().currentTeamName = (user['team_name'] as String?) ?? '';
      FFAppState().primaryColor   = (club['primary_color']   as String?) ?? '#1e3a5f';
      FFAppState().secondaryColor = (club['secondary_color'] as String?) ?? '#3b82f6';
      FFAppState().accentColor    = (club['accent_color']    as String?) ?? '#10b981';
    });
    return sanctumToken;
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
      FFAppState().currentTeamId   = firstTeamId;
      FFAppState().currentTeamName = (user['team_name'] as String?) ?? '';
      FFAppState().primaryColor   = (club['primary_color']   as String?) ?? '#1e3a5f';
      FFAppState().secondaryColor = (club['secondary_color'] as String?) ?? '#3b82f6';
      FFAppState().accentColor    = (club['accent_color']    as String?) ?? '#10b981';
    });

    // Sign in anonymously so FlutterFlow's Firebase Auth route guard (loggedIn)
    // passes. Without this, the router redirects every page back to LoginPage.
    try {
      await FirebaseAuth.instance.signInAnonymously();
      // Wait for the auth-state stream to propagate before returning.
      // FlutterFlow's firebase_auth_manager reads currentUser from the stream;
      // without this wait the NavBar IndexedStack can build while the stream
      // still emits null, causing a null-check crash on the chat page.
      await FirebaseAuth.instance
          .authStateChanges()
          .firstWhere((u) => u != null)
          .timeout(const Duration(seconds: 3), onTimeout: () => null);
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
  _ensureAppStateField(project, 'primaryColor',    FFBaseDataType.String, persisted: true);
  _ensureAppStateField(project, 'secondaryColor',  FFBaseDataType.String, persisted: true);
  _ensureAppStateField(project, 'accentColor',     FFBaseDataType.String, persisted: true);
  _ensureAppStateField(project, 'currentTeamName', FFBaseDataType.String, persisted: true);

  // Ensure user-identity fields survive app restarts.
  for (final field in ['authToken', 'userName', 'userEmail', 'clubName', 'currentTeamName']) {
    _makeAppStateFieldPersisted(project, field);
  }

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
  app.editPage(ff.Pages.loginPage, (page) {
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
            onChanged: [SetState(ff.Pages.loginPage.state.magicLinkEmail, TextValue())],
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
                arguments: {'email': State(ff.Pages.loginPage.state.magicLinkEmail)},
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

  app.editPageOnLoad(ff.Pages.magicLinkVerifyPage, [
    CallCustomAction.named(
      'VerifyMagicLink',
      returnType: string,
      arguments: {'token': Param('token')},
      outputAs: 'sanctumToken',
    ),
    If(
      Not(Equals(ActionOutput('sanctumToken'), '')),
      then: [
        UpdateAppState.set(ff.AppState.authToken, ActionOutput('sanctumToken')),
        Navigate(ff.Pages.wedstrijdenPage, replaceRoute: true),
      ],
      orElse: [
        Snackbar('Deze inloglink is ongeldig of verlopen.'),
        Navigate(ff.Pages.loginPage, replaceRoute: true),
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

// ─── Swap (wissel) feature ────────────────────────────────────────────────────

// Add isAssignedToMe to BarDuty struct + isFruitHero / isDriver / fruitHeroId to FootMatch.
void _addSwapStructFields(FFProject project) {
  for (final entry in [
    ('BarDuty',      [('isAssignedToMe', FFBaseDataType.Boolean)]),
    ('FootMatch',    [
      ('isFruitHero', FFBaseDataType.Boolean),
      ('isDriver',    FFBaseDataType.Boolean),
      ('fruitHeroId', FFBaseDataType.String),
    ]),
  ] as List<(String, List<(String, FFBaseDataType)>)>) {
    final (structName, fieldDefs) = entry;
    final struct = project.backend.dataSchemaConfig.dataStructs
        .cast<FFDataStruct?>()
        .firstWhere((s) => s?.identifier.name == structName, orElse: () => null);
    if (struct == null) continue;
    for (final (name, type) in fieldDefs) {
      if (struct.fields.every((f) => f.identifier.name != name)) {
        struct.fields.add(FFParameter(
          identifier: FFIdentifier(
            name: name,
            key: generateRandomAlphaNumericString(),
          ),
          dataType: FFDataTypeV2(scalarType: type),
        ));
      }
    }
  }
}

// Add isAssignedToMe, onSwapAction params to BarDutyCard component.
void _addSwapParamsToBarDutyCard(FFProject project) {
  final wc = findComponent(project, name: 'BarDutyCard');
  if (wc == null) return;

  final newParams = [
    ('isAssignedToMe',  FFBaseDataType.Boolean),
    ('onSwapAction',    FFBaseDataType.Action),
  ] as List<(String, FFBaseDataType)>;

  for (final (name, type) in newParams) {
    if (wc.params.values.any((p) => p.hasIdentifier() && p.identifier.name == name)) {
      continue;
    }
    final id = FFIdentifier(name: name, key: generateRandomAlphaNumericString());
    wc.params[id.key] = FFParameter(
      identifier: id,
      dataType: FFDataTypeV2(scalarType: type),
    );
  }
}

// Add GetTeamMembers, GetSwapRequests, CreateSwapRequest, Accept/Decline endpoints.
void _addSwapEndpoints(FFProject project) {
  // Ensure ClubBranding struct exists (used by GetBranding endpoint).
  if (!project.backend.dataSchemaConfig.dataStructs
      .any((s) => s.identifier.name == 'ClubBranding')) {
    addDataStruct(
      project,
      name: 'ClubBranding',
      description: 'Clubkleuren en naam voor dynamische branding in de app.',
      fields: [
        structField('primaryColor',   stringType, description: 'Primaire clubkleur als hex string'),
        structField('secondaryColor', stringType, description: 'Secundaire clubkleur als hex string'),
        structField('accentColor',    stringType, description: 'Accentkleur als hex string'),
        structField('clubName',       stringType, description: 'Naam van de club'),
        structField('logoPath',       stringType, description: 'Pad naar het clublogo'),
      ],
    );
  }

  final existing = <String>{};
  for (final group in project.backend.apiConfig.apiGroups) {
    for (final ep in group.endpoints) {
      existing.add(ep.identifier.name);
    }
  }

  void addIfMissing({
    required String name,
    required String url,
    FFApiEndpoint_CallType method = FFApiEndpoint_CallType.GET,
    FFApiEndpoint_BodyType bodyType = FFApiEndpoint_BodyType.NONE,
    String? body,
    Map<String, FFDataTypeV2>? variables,
    String? responseDataStructName,
    bool responseDataStructIsList = false,
  }) {
    if (existing.contains(name)) return;
    addEndpointToGroup(
      project,
      groupName:                'VoetbalPlannerAPI',
      name:                     name,
      url:                      url,
      method:                   method,
      bodyType:                 bodyType,
      body:                     body,
      variables:                variables,
      headers:                  ['Authorization: Bearer [bearerToken]'],
      responseDataStructName:   responseDataStructName,
      responseDataStructIsList: responseDataStructIsList,
    );
  }

  addIfMissing(
    name:                   'GetMatchDetail',
    url:                    '/matches/[matchId]',
    variables:              {'matchId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
    responseDataStructName: 'FootMatch',
  );
  // If the endpoint already existed (addIfMissing skipped), force-set the
  // responseDataStructParam so the action can use DATA_STRUCT mode.
  if (existing.contains('GetMatchDetail')) {
    updateApiEndpoint(
      project,
      name:                   'GetMatchDetail',
      groupName:              'VoetbalPlannerAPI',
      responseDataStructName: 'FootMatch',
    );
  }

  addIfMissing(
    name:                     'GetTeamMembers',
    url:                      '/teams/[teamId]/members',
    variables:                {'teamId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
    responseDataStructName:   'SwapMember',
    responseDataStructIsList: true,
  );

  addIfMissing(
    name:                     'GetSwapRequests',
    url:                      '/swap-requests/incoming',
    responseDataStructName:   'SwapRequest',
    responseDataStructIsList: true,
  );

  addIfMissing(
    name:     'CreateSwapRequest',
    url:      '/swap-requests',
    method:   FFApiEndpoint_CallType.POST,
    bodyType: FFApiEndpoint_BodyType.JSON,
    body:     '{"type":"[type]","target_id":"[target_id]","requestee_id":"[requestee_id]"}',
    variables: {
      'type':         FFDataTypeV2(scalarType: FFBaseDataType.String),
      'target_id':    FFDataTypeV2(scalarType: FFBaseDataType.String),
      'requestee_id': FFDataTypeV2(scalarType: FFBaseDataType.String),
    },
  );

  addIfMissing(
    name:      'AcceptSwapRequest',
    url:       '/swap-requests/[id]/accept',
    method:    FFApiEndpoint_CallType.PATCH,
    variables: {'id': FFDataTypeV2(scalarType: FFBaseDataType.String)},
  );

  addIfMissing(
    name:      'DeclineSwapRequest',
    url:       '/swap-requests/[id]/decline',
    method:    FFApiEndpoint_CallType.PATCH,
    variables: {'id': FFDataTypeV2(scalarType: FFBaseDataType.String)},
  );

  addIfMissing(
    name:                   'GetBarDutyDetail',
    url:                    '/bar-duties/[dutyId]',
    variables:              {'dutyId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
    responseDataStructName: 'BarDuty',
  );
  if (existing.contains('GetBarDutyDetail')) {
    updateApiEndpoint(
      project,
      name:                   'GetBarDutyDetail',
      groupName:              'VoetbalPlannerAPI',
      responseDataStructName: 'BarDuty',
    );
  }

  addIfMissing(
    name:                   'GetBranding',
    url:                    '/branding',
    responseDataStructName: 'ClubBranding',
  );
  if (existing.contains('GetBranding')) {
    updateApiEndpoint(
      project,
      name:                   'GetBranding',
      groupName:              'VoetbalPlannerAPI',
      responseDataStructName: 'ClubBranding',
    );
  }
}

// SwapRequestCard component: shows requester, duty label, accept + decline buttons.
void _buildSwapRequestCard(App app, StructHandle swapRequest) {
  // addComponent in project_helpers.dart is idempotent for components:
  // if SwapRequestCard already exists it returns the existing key and the
  // _compileComponents second-pass updates the body — no "already exists" error.
  app.component(
    'SwapRequestCard',
    description: 'Toont een binnenkomend wissel-verzoek met accepteer- en weigerknop.',
    params: {
      'requesterName':     string,
      'typeLabel':         string,
      'targetDescription': string,
      'date':              string,
      'onAccept':          action,
      'onDecline':         action,
    },
    body: Container(
      padding: 16,
      borderRadius: 12,
      color: Colors.secondaryBackground,
      child: Column(
        crossAxis: CrossAxis.start,
        spacing: 8,
        children: [
          Row(
            mainAxis: MainAxis.spaceBetween,
            children: [
              Text(Param(ff.Components.swapRequestCard.params.typeLabel), style: Styles.titleSmall),
              Text(Param(ff.Components.swapRequestCard.params.date), style: Styles.bodySmall),
            ],
          ),
          Text(Param(ff.Components.swapRequestCard.params.targetDescription), style: Styles.bodyMedium),
          Text(
            Param(ff.Components.swapRequestCard.params.requesterName),
            style: Styles.bodySmall,
          ),
          Row(
            spacing: 8,
            mainAxis: MainAxis.end,
            children: [
              Button(
                'Weigeren',
                onTap: ParamAction('onDecline'),
                variant: ButtonVariant.outlined,
              ),
              Button(
                'Accepteren',
                onTap: ParamAction('onAccept'),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

// WisselAanvraagPage: pick a team member and send the swap request.
void _buildWisselAanvraagPage(
  App app,
  StructHandle swapMember,
) {
  app.ensurePage(
    'WisselAanvraagPage',
    description: 'Vraag een teamlid om een dienst over te nemen.',
    route: 'wissel-aanvraag',
    params: {
      'dutyType':   string,
      'targetId':   string,
      'targetLabel': string.withDefault(''),
    },
    state: {
      'teamMembers': listOf(swapMember),
      'isLoading':   bool_.withDefault(true),
      'isSending':   bool_.withDefault(false),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Wissel aanvragen'),
      body: Column(
        children: [
          Container(
            padding: 16,
            child: Text(
              PageParam('targetLabel'),
              style: Styles.bodyMedium,
            ),
          ),
          ConditionalBuilder(
            children: [
              Column(
                visible: State(ff.Pages.wisselAanvraagPage.state.isLoading),
                mainAxis: MainAxis.center,
                children: [
                  ProgressBar.circular(size: 40, thickness: 4),
                ],
              ),
              Expanded(
                ListView(
                  name: 'TeamMembersListView',
                  source: State(ff.Pages.wisselAanvraagPage.state.teamMembers),
                  spacing: 8,
                  padding: 16,
                  itemBuilder: (member) => Container(
                    padding: 16,
                    borderRadius: 12,
                    color: Colors.secondaryBackground,
                    child: Row(
                      mainAxis: MainAxis.spaceBetween,
                      children: [
                        Text(member['name'], style: Styles.bodyMedium),
                        Button(
                          'Vraag',
                          name: 'VraagButton',
                          visible: Not(State(ff.Pages.wisselAanvraagPage.state.isSending)),
                        ),
                      ],
                    ),
                  ),
                ),
                visible: Not(State(ff.Pages.wisselAanvraagPage.state.isLoading)),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

// WisselVerzoekenPage: incoming swap requests with accept / decline.
void _buildWisselVerzoekenPage(
  App app,
  StructHandle swapRequest,
) {
  final swapRequestCard = ff.Components.swapRequestCard;

  app.ensurePage(
    'WisselVerzoekenPage',
    description: 'Overzicht van binnenkomende wissel-verzoeken.',
    route: 'wissel-verzoeken',
    state: {
      'requests':  listOf(swapRequest),
      'isLoading': bool_.withDefault(true),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Wissel verzoeken'),
      body: ConditionalBuilder(
        children: [
          Column(
            visible: State(ff.Pages.wisselVerzoekenPage.state.isLoading),
            mainAxis: MainAxis.center,
            children: [ProgressBar.circular(size: 40, thickness: 4)],
          ),
          Expanded(
            ListView(
              name: 'SwapRequestsListView',
              source: State(ff.Pages.wisselVerzoekenPage.state.requests),
              spacing: 12,
              padding: 16,
              itemBuilder: (req) => swapRequestCard(
                requesterName:     req['requesterName'],
                typeLabel:         req['typeLabel'],
                targetDescription: req['targetDescription'],
                date:              req['date'],
                onAccept:  [Snackbar('')],
                onDecline: [Snackbar('')],
              ),
            ),
            visible: Not(State(ff.Pages.wisselVerzoekenPage.state.isLoading)),
          ),
        ],
      ),
    ),
  );
}

// Binds isAssignedToMe, barDutyId, and barDutyDate variables on the BarDutyCard
// instance so the component's internal Navigate actions receive correct data.
void _wireBarDutySwap(FFProject project) {
  final wc = findPage(project, name: 'BardienPage');
  if (wc == null) return;
  final listView = findByKey(wc.node, 'ListView_tu54znnh');
  if (listView == null || listView.children.isEmpty) return;
  final itemTemplate = listView.children.first;

  final componentClassKey = itemTemplate.componentClassKeyRef.key;
  final barDutyCard = project.widgetClasses[componentClassKey];
  if (barDutyCard == null) return;

  if (!itemTemplate.hasParameterValues()) {
    itemTemplate.parameterValues = FFPassedParameters(
      widgetClassNodeKeyRef: FFNodeKeyReference(key: componentClassKey),
    );
  }

  FFParameter? isAssignedParam;
  FFParameter? barDutyIdParam;
  FFParameter? barDutyDateParam;
  for (final p in barDutyCard.params.values) {
    if (!p.hasIdentifier()) continue;
    switch (p.identifier.name) {
      case 'isAssignedToMe': isAssignedParam = p;
      case 'barDutyId':      barDutyIdParam = p;
      case 'barDutyDate':    barDutyDateParam = p;
    }
  }

  // Use properly-keyed field identifiers so FlutterFlow codegen can resolve
  // field access; fall back to name-only if struct field is not found.
  final isAssignedFieldId = _findStructFieldId(project, 'BarDuty', 'isAssignedToMe');
  final dutyIdFieldId     = _findStructFieldId(project, 'BarDuty', 'id');
  final dutyDateFieldId   = _findStructFieldId(project, 'BarDuty', 'date');

  FFVariable _genVar(String listKey, String fieldName, FFIdentifier? fieldId) {
    if (fieldId != null) {
      return varFromGeneratorVariable(listKey)
        ..operations.add(FFVariableOperation(
          accessDataStructField: FFAccessDataStructField(
            fieldIdentifier: fieldId.deepCopy(),
          ),
        ));
    }
    return generatorVarField(listKey, fieldName);
  }

  if (isAssignedParam != null) {
    itemTemplate.parameterValues.parameterPasses[isAssignedParam.identifier.key] =
        FFParameterPass(
          paramIdentifier: isAssignedParam.identifier.deepCopy(),
          variable: _genVar('ListView_tu54znnh', 'isAssignedToMe', isAssignedFieldId),
        );
  }
  if (barDutyIdParam != null) {
    itemTemplate.parameterValues.parameterPasses[barDutyIdParam.identifier.key] =
        FFParameterPass(
          paramIdentifier: barDutyIdParam.identifier.deepCopy(),
          variable: _genVar('ListView_tu54znnh', 'id', dutyIdFieldId),
        );
  }
  if (barDutyDateParam != null) {
    itemTemplate.parameterValues.parameterPasses[barDutyDateParam.identifier.key] =
        FFParameterPass(
          paramIdentifier: barDutyDateParam.identifier.deepCopy(),
          variable: _genVar('ListView_tu54znnh', 'date', dutyDateFieldId),
        );
  }
}

// ─── BardienDetailPage ────────────────────────────────────────────────────────

void _buildBardienDetailPage(App app) {
  app.ensurePage(
    'BardienDetailPage',
    description: 'Bardienst details: dienst info, bezetting en wissel-opties.',
    route: 'bardienst-detail',
    params: {
      'dutyId': string,
    },
    state: {
      'isLoading': bool_.withDefault(true),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Bardienst details'),
      body: Column(
        children: [
          ConditionalBuilder(
            children: [
              Column(
                visible: State(ff.Pages.bardienDetailPage.state.isLoading),
                mainAxis: MainAxis.center,
                children: [ProgressBar.circular(size: 40, thickness: 4)],
              ),
              Column(
                name: 'DutyInfoColumn',
                visible: Not(State(ff.Pages.bardienDetailPage.state.isLoading)),
                crossAxis: CrossAxis.start,
                padding: 16,
                spacing: 16,
                children: [
                  Text('Bardienst info', style: Styles.titleMedium),
                ],
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

void _wireBardienDetailPageLoad(FFProject project) {
  final wc = findPage(project, name: 'BardienDetailPage');
  if (wc == null) return;

  FFIdentifier? dutyIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'dutyId') {
      dutyIdParamId = param.identifier;
      break;
    }
  }
  if (dutyIdParamId == null) return;

  for (final name in const [
    'dutyDate', 'dutyShift', 'dutyStatus', 'dutyTeamName', 'dutyMembers', 'dutyNotes',
  ]) {
    _ensurePageStateField(wc, name, FFBaseDataType.String);
  }

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetBarDutyDetail',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'dutyId': varFromPageParam(dutyIdParamId.deepCopy()),
      },
      outputVariableName: 'dutyLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) {
        const fieldMap = {
          'dutyDate':     'date',
          'dutyShift':    'shift',
          'dutyStatus':   'status',
          'dutyTeamName': 'teamName',
          'dutyMembers':  'members',
          'dutyNotes':    'notes',
        };
        final updates = <StateFieldUpdate>[StateFieldUpdate.set('isLoading', 'false')];
        for (final entry in fieldMap.entries) {
          final structFieldId = _findStructFieldId(project, 'BarDuty', entry.value);
          if (structFieldId == null) continue;
          final v = ctx.responseVar.deepCopy()
            ..operations.add(FFVariableOperation(
              accessDataStructField: FFAccessDataStructField(
                fieldIdentifier: structFieldId.deepCopy(),
              ),
            ));
          updates.add(StateFieldUpdate.setFromVariable(entry.key, v));
        }
        return Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: 'BardienDetailPage',
            updates: updates,
          ),
        ]);
      },
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'BardienDetailPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon bardienst niet laden.'),
      ]),
    ),
  );
}

// Navigation is now handled inside BarDutyCard via its barDutyId param;
// _wireBarDutySwap binds the variable. Nothing to do here.
void _addBardienNavigation(FFProject project) {}

// ─── RijschemaDetailPage ──────────────────────────────────────────────────────

void _buildRijschemaDetailPage(App app) {
  app.ensurePage(
    'RijschemaDetailPage',
    description: 'Rijschema detail: wedstrijdinformatie voor de chauffeur.',
    route: 'rijschema-detail',
    params: {'matchId': string},
    state: {'isLoading': bool_.withDefault(true)},
    body: Scaffold(
      appBar: AppBar(title: 'Rit details'),
      body: Column(
        crossAxis: CrossAxis.start,
        padding: 16,
        spacing: 12,
        name: 'RijInfoColumn',
        children: [
          Text('Wedstrijd info', style: Styles.titleMedium),
        ],
      ),
    ),
  );
}

void _wireRijschemaDetailPageLoad(FFProject project) {
  final wc = findPage(project, name: 'RijschemaDetailPage');
  if (wc == null) return;

  FFIdentifier? matchIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'matchId') {
      matchIdParamId = param.identifier;
      break;
    }
  }
  if (matchIdParamId == null) return;

  for (final name in const [
    'rijOpponent', 'rijDatetime', 'rijLocation', 'rijArrivalTime', 'rijNotes',
  ]) {
    _ensurePageStateField(wc, name, FFBaseDataType.String);
  }

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetMatchDetail',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {'matchId': varFromPageParam(matchIdParamId.deepCopy())},
      outputVariableName: 'rijLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) {
        const fieldMap = {
          'rijOpponent':    'opponent',
          'rijDatetime':    'matchDatetime',
          'rijLocation':    'location',
          'rijArrivalTime': 'arrivalTime',
          'rijNotes':       'notes',
        };
        final updates = <StateFieldUpdate>[StateFieldUpdate.set('isLoading', 'false')];
        for (final entry in fieldMap.entries) {
          final structFieldId = _findStructFieldId(project, 'FootMatch', entry.value);
          if (structFieldId == null) continue;
          final v = ctx.responseVar.deepCopy()
            ..operations.add(FFVariableOperation(
              accessDataStructField: FFAccessDataStructField(
                fieldIdentifier: structFieldId.deepCopy(),
              ),
            ));
          updates.add(StateFieldUpdate.setFromVariable(entry.key, v));
        }
        return Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: 'RijschemaDetailPage',
            updates: updates,
          ),
        ]);
      },
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'RijschemaDetailPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon ritdetails niet laden.'),
      ]),
    ),
  );
}

// Populates RijInfoColumn with data-bound label+value rows.
// Always rebuilds the column so bindings are fresh on every push.
void _wireRijschemaDetailPageUI(FFProject project) {
  final wc = findPage(project, name: 'RijschemaDetailPage');
  if (wc == null) return;

  FFVariable? stateVar(String stateFieldName) {
    final stateField = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((f) => f?.parameter.identifier.name == stateFieldName, orElse: () => null);
    if (stateField == null) return null;
    final v = varFromPageState(stateField.parameter.identifier.deepCopy());
    v.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    return v;
  }

  final infoColumn = findDescendants(wc.node, (n) => n.name == 'RijInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  FFNode infoRow(String label, String stateFieldName) {
    final valueText = UI.text('-', name: 'RijInfoValue_$stateFieldName', style: UITextStyle.bodyMedium);
    final v = stateVar(stateFieldName);
    if (v != null) valueText.props.text.textValue = FFStringValue(variable: v);
    return UI.container(
      name: 'RijInfoRow_$stateFieldName',
      padding: UIEdgeInsets.symmetric(vertical: 6, horizontal: 0),
      child: UI.column(
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 2,
        children: [
          UI.text(label, style: UITextStyle.labelSmall, color: UIColor.secondaryText),
          valueText,
        ],
      ),
    );
  }

  infoColumn.children.clear();
  infoColumn.children.addAll([
    UI.text('Wedstrijd details', name: 'RijTitle', style: UITextStyle.titleMedium),
    infoRow('Tegenstander', 'rijOpponent'),
    infoRow('Datum & Tijd', 'rijDatetime'),
    infoRow('Locatie',      'rijLocation'),
    infoRow('Verzamelen',   'rijArrivalTime'),
    infoRow('Notities',     'rijNotes'),
  ]);
}

// Adds ON_TAP navigation to the RijschemaPage ListView item card.
void _wireRijschemaNavigation(FFProject project) {
  final wc = findPage(project, name: 'RijschemaPage');
  if (wc == null) return;
  if (project.getWidgetClassByName('RijschemaDetailPage') == null) return;

  final container = findByKey(wc.node, 'Container_od2z9b8b');
  if (container == null) return;

  // Skip if already wired (action exists).
  final hasTap = container.triggerActions.any(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (hasTap) return;

  final idFieldId = _findStructFieldId(project, 'FootMatch', 'id');
  final matchIdVar = idFieldId != null
      ? (varFromGeneratorVariable('ListView_55kreos3')
          ..operations.add(FFVariableOperation(
            accessDataStructField: FFAccessDataStructField(
              fieldIdentifier: idFieldId.deepCopy(),
            ),
          )))
      : generatorVarField('ListView_55kreos3', 'id');

  final navigateAction = Actions.navigate(
    project,
    pageName: 'RijschemaDetailPage',
    params: {'matchId': VariableParamValue(matchIdVar)},
  );
  Actions.onTap(container, navigateAction);
}

// Binds value text nodes on BardienDetailPage to individual string state fields.
// Each field (dutyDate, dutyShift, …) was added by _wireBardienDetailPageLoad.
void _wireBardienDetailPageUI(FFProject project) {
  final wc = findPage(project, name: 'BardienDetailPage');
  if (wc == null) return;

  FFVariable? stateVar(String stateFieldName) {
    final stateField = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((f) => f?.parameter.identifier.name == stateFieldName, orElse: () => null);
    if (stateField == null) return null;
    final v = varFromPageState(stateField.parameter.identifier.deepCopy());
    v.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    return v;
  }

  final infoColumn = findDescendants(wc.node, (n) => n.name == 'DutyInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  FFNode infoRow(String label, String stateFieldName) {
    final valueText = UI.text('-', name: 'DutyInfoValue_$stateFieldName', style: UITextStyle.bodyMedium);
    final v = stateVar(stateFieldName);
    if (v != null) valueText.props.text.textValue = FFStringValue(variable: v);
    return UI.container(
      name: 'DutyInfoRow_$stateFieldName',
      padding: UIEdgeInsets.symmetric(vertical: 6, horizontal: 0),
      child: UI.column(crossAxisAlignment: UICrossAxisAlignment.start, spacing: 2, children: [
        UI.text(label, style: UITextStyle.labelSmall, color: UIColor.secondaryText),
        valueText,
      ]),
    );
  }

  infoColumn.children.clear();
  infoColumn.children.addAll([
    UI.text('Bardienst details', name: 'DutyInfoTitle', style: UITextStyle.titleMedium),
    infoRow('Datum', 'dutyDate'),
    infoRow('Dienst', 'dutyShift'),
    infoRow('Status', 'dutyStatus'),
    infoRow('Team', 'dutyTeamName'),
    infoRow('Leden', 'dutyMembers'),
    infoRow('Notities', 'dutyNotes'),
  ]);
}

// Fix the timestamp format in TeamChatPage: show 'HH:mm' instead of full datetime.
void _fixChatTimestamp(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  // Find all text nodes whose name ends with 'Timestamp' or that bind createdAt.
  // We look for nodes whose textValue variable has an 'accessDocumentField' operation
  // for 'createdAt'. When found, add a dateTimeFormat operation after it.
  final allTexts = findDescendants(wc.node, (n) => n.type == FFWidgetType.Text);
  for (final text in allTexts) {
    if (!text.props.hasText()) continue;
    final tv = text.props.text.textValue;
    if (!tv.hasVariable()) continue;
    final v = tv.variable;
    // Look for a variable that accesses createdAt on a Firestore document field.
    final hasCreatedAtOp = v.operations.any((op) =>
        op.hasAccessDocumentField() &&
        op.accessDocumentField.fieldIdentifier.name == 'createdAt');
    if (!hasCreatedAtOp) continue;
    // Already has dateTimeFormat? Skip.
    if (v.operations.any((op) => op.hasDateTimeFormat())) continue;
    // Add 'H:mm' custom format (24-hour hour:minute, no leading zero for hour).
    v.operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'Hm', isCustom: false),
    ));
  }
}

// Builds WedstrijdDetailPage — must exist before _addMatchNavigation can bind the tap action.
void _buildWedstrijdDetailPage(App app) {
  app.ensurePage(
    'WedstrijdDetailPage',
    description: 'Wedstrijddetails: info en wissel-opties.',
    route: 'wedstrijd-detail',
    params: {
      'matchId': string,
    },
    state: {
      'isLoading': bool_.withDefault(true),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Wedstrijd details'),
      body: Column(
        children: [
          ConditionalBuilder(
            children: [
              Column(
                visible: State(ff.Pages.wedstrijdDetailPage.state.isLoading),
                mainAxis: MainAxis.center,
                children: [ProgressBar.circular(size: 40, thickness: 4)],
              ),
              Column(
                name: 'MatchInfoColumn',
                visible: Not(State(ff.Pages.wedstrijdDetailPage.state.isLoading)),
                crossAxis: CrossAxis.start,
                padding: 16,
                spacing: 16,
                children: [
                  Text('Wedstrijd info', style: Styles.titleMedium),
                ],
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

// Wires the onLoad chain for WedstrijdDetailPage: calls GetMatchDetail on init.
void _wireWedstrijdDetailPageLoad(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;

  FFIdentifier? matchIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'matchId') {
      matchIdParamId = param.identifier;
      break;
    }
  }
  if (matchIdParamId == null) return;

  // Ensure individual string state fields for each displayed value.
  // setFromVariable for a single DataStruct state field does not generate
  // working code in FlutterFlow; storing individual strings is reliable.
  for (final name in const [
    'matchOpponent', 'matchDatetime', 'matchLocation',
    'matchArrivalTime', 'matchCoachName', 'matchFruitHeroName', 'matchNotes',
  ]) {
    _ensurePageStateField(wc, name, FFBaseDataType.String);
  }

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetMatchDetail',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'matchId': varFromPageParam(matchIdParamId.deepCopy()),
      },
      outputVariableName: 'matchLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) {
        // Map state field name → FootMatch struct field name.
        const fieldMap = {
          'matchOpponent':      'opponent',
          'matchDatetime':      'matchDatetime',
          'matchLocation':      'location',
          'matchArrivalTime':   'arrivalTime',
          'matchCoachName':     'coachName',
          'matchFruitHeroName': 'fruitHeroName',
          'matchNotes':         'notes',
        };
        final updates = <StateFieldUpdate>[StateFieldUpdate.set('isLoading', 'false')];
        for (final entry in fieldMap.entries) {
          final structFieldId = _findStructFieldId(project, 'FootMatch', entry.value);
          if (structFieldId == null) continue;
          final v = ctx.responseVar.deepCopy()
            ..operations.add(FFVariableOperation(
              accessDataStructField: FFAccessDataStructField(
                fieldIdentifier: structFieldId.deepCopy(),
              ),
            ));
          updates.add(StateFieldUpdate.setFromVariable(entry.key, v));
        }
        return Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: 'WedstrijdDetailPage',
            updates: updates,
          ),
        ]);
      },
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon wedstrijddetails niet laden.'),
      ]),
    ),
  );
}

// Wire WedstrijdDetailPage: add Fruitheld and Rijden swap buttons.
// They are inserted into MatchInfoColumn (the named column in the DSL-built page).
void _wireMatchSwap(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  if (project.getWidgetClassByName('WisselAanvraagPage') == null) return;

  // Find the column by name — DSL-built page uses 'MatchInfoColumn'.
  final infoColumn = findDescendants(wc.node, (n) => n.name == 'MatchInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  // Only add buttons once.
  if (infoColumn.children.any((c) => c.name == 'FruitheldWisselButton' || c.name == 'RijdenWisselButton')) {
    return;
  }

  // Find the WisselAanvraagPage key ref.
  final wisselPage = project.getWidgetClassByName('WisselAanvraagPage')!;
  final wisselKeyRef = FFNodeKeyReference(key: wisselPage.node.key);

  // Use the matchId page param (passed when navigating to this page) as targetId.
  FFIdentifier? matchIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'matchId') {
      matchIdParamId = param.identifier;
      break;
    }
  }
  if (matchIdParamId == null) return;

  // Helper to build a swap button node with a navigate action.
  FFNode buildSwapButton({required String name, required String dutyType, required String labelParam}) {
    final button = FFNode(
      key: generateRandomAlphaNumericString(),
      name: name,
    );
    final navigateAction = Actions.navigate(
      project,
      pageName: 'WisselAanvraagPage',
      params: {
        'dutyType':    StaticParamValue(dutyType),
        'targetId':    VariableParamValue(varFromPageParam(matchIdParamId!.deepCopy())),
        'targetLabel': StaticParamValue(labelParam),
      },
    );
    Actions.onTap(button, navigateAction);
    return button;
  }

  // Fruitheld swap button.
  infoColumn.children.add(buildSwapButton(
    name: 'FruitheldWisselButton',
    dutyType: 'fruitheld',
    labelParam: 'Fruitheld',
  ));

  // Rijden swap button.
  infoColumn.children.add(buildSwapButton(
    name: 'RijdenWisselButton',
    dutyType: 'rijden',
    labelParam: 'Rijden',
  ));
}

// Wires the onLoad API call for WisselAanvraagPage: loads team members by currentTeamId.
void _wireWisselAanvraagPageLoad(FFProject project) {
  final wc = findPage(project, name: 'WisselAanvraagPage');
  if (wc == null) return;

  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdId == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetTeamMembers',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'teamId': varFromAppState(currentTeamIdId.deepCopy()),
      },
      outputVariableName: 'membersLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WisselAanvraagPage',
          updates: [
            StateFieldUpdate.setFromVariable('teamMembers', ctx.responseVar),
            StateFieldUpdate.set('isLoading', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WisselAanvraagPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon teamleden niet laden.'),
      ]),
    ),
  );
}

// Wires the VraagButton in WisselAanvraagPage to POST CreateSwapRequest.
void _wireWisselAanvraagButton(FFProject project) {
  final wc = findPage(project, name: 'WisselAanvraagPage');
  if (wc == null) return;

  final listView = findDescendants(wc.node, (n) => n.name == 'TeamMembersListView').firstOrNull;
  if (listView == null || listView.children.isEmpty) return;
  final itemTemplate = listView.children.first;

  final vraagButton = findDescendants(itemTemplate, (n) => n.name == 'VraagButton').firstOrNull;
  if (vraagButton == null) return;

  FFIdentifier? dutyTypeParamId;
  FFIdentifier? targetIdParamId;
  for (final param in wc.params.values) {
    if (!param.hasIdentifier()) continue;
    switch (param.identifier.name) {
      case 'dutyType': dutyTypeParamId = param.identifier;
      case 'targetId': targetIdParamId = param.identifier;
    }
  }
  if (dutyTypeParamId == null || targetIdParamId == null) return;

  vraagButton.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  Actions.addTriggerChain(
    vraagButton,
    FFActionTriggerType.ON_TAP,
    Actions.apiCallNode(
      project,
      endpointName: 'CreateSwapRequest',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'type':         varFromPageParam(dutyTypeParamId.deepCopy()),
        'target_id':    varFromPageParam(targetIdParamId.deepCopy()),
        'requestee_id': generatorVarField(listView.key, 'id'),
      },
      outputVariableName: 'swapResult',
      nodeKey: vraagButton.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.snackBar('Wissel aangevraagd!'),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.snackBar('Versturen mislukt, probeer opnieuw.'),
      ]),
    ),
  );
}

// Wires the onLoad API call for WisselVerzoekenPage: loads incoming swap requests.
void _wireWisselVerzoekenPageLoad(FFProject project) {
  final wc = findPage(project, name: 'WisselVerzoekenPage');
  if (wc == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetSwapRequests',
      groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'requestsLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WisselVerzoekenPage',
          updates: [
            StateFieldUpdate.setFromVariable('requests', ctx.responseVar),
            StateFieldUpdate.set('isLoading', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'WisselVerzoekenPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon verzoeken niet laden.'),
      ]),
    ),
  );
}

// Wires onAccept and onDecline on SwapRequestCard instances in WisselVerzoekenPage.
void _wireWisselVerzoekenActions(FFProject project) {
  final wc = findPage(project, name: 'WisselVerzoekenPage');
  if (wc == null) return;

  final listView = findDescendants(wc.node, (n) => n.name == 'SwapRequestsListView').firstOrNull;
  if (listView == null || listView.children.isEmpty) return;
  final itemTemplate = listView.children.first;

  final componentClassKey = itemTemplate.componentClassKeyRef.key;
  final swapRequestCard = project.widgetClasses[componentClassKey];
  if (swapRequestCard == null) return;

  FFParameter? onAcceptParam;
  FFParameter? onDeclineParam;
  for (final p in swapRequestCard.params.values) {
    if (!p.hasIdentifier()) continue;
    switch (p.identifier.name) {
      case 'onAccept':  onAcceptParam = p;
      case 'onDecline': onDeclineParam = p;
    }
  }
  if (onAcceptParam == null || onDeclineParam == null) return;

  if (!itemTemplate.hasParameterValues()) {
    itemTemplate.parameterValues = FFPassedParameters(
      widgetClassNodeKeyRef: FFNodeKeyReference(key: componentClassKey),
    );
  }

  itemTemplate.parameterValues.parameterPasses[onAcceptParam.identifier.key] =
      FFParameterPass(
        paramIdentifier: onAcceptParam.identifier.deepCopy(),
        action: FFTriggerActions(
          rootAction: Actions.apiCallNode(
            project,
            endpointName: 'AcceptSwapRequest',
            groupName: 'VoetbalPlannerAPI',
            dynamicVariables: {
              'id': generatorVarField(listView.key, 'id'),
            },
            outputVariableName: 'acceptResult',
            nodeKey: itemTemplate.key,
            onSuccess: (ctx) => Actions.chain([Actions.snackBar('Wissel bevestigd!')]),
            onFailure: (ctx) => Actions.chain([Actions.snackBar('Mislukt, probeer opnieuw.')]),
          ),
        ),
      );

  itemTemplate.parameterValues.parameterPasses[onDeclineParam.identifier.key] =
      FFParameterPass(
        paramIdentifier: onDeclineParam.identifier.deepCopy(),
        action: FFTriggerActions(
          rootAction: Actions.apiCallNode(
            project,
            endpointName: 'DeclineSwapRequest',
            groupName: 'VoetbalPlannerAPI',
            dynamicVariables: {
              'id': generatorVarField(listView.key, 'id'),
            },
            outputVariableName: 'declineResult',
            nodeKey: itemTemplate.key,
            onSuccess: (ctx) => Actions.chain([Actions.snackBar('Wissel afgewezen.')]),
            onFailure: (ctx) => Actions.chain([Actions.snackBar('Mislukt, probeer opnieuw.')]),
          ),
        ),
      );
}

// Binds WedstrijdDetailPage's AppBar title to the matchId page param.
// match.opponent cannot be used here: the AppBar builds before the API call
// completes and match is null, causing a null-check crash.
void _bindWedstrijdDetailAppBarTitle(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  final titleNode = findByKey(wc.node, 'Text_hbiz91w0');
  if (titleNode == null) return;
  FFIdentifier? matchIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'matchId') {
      matchIdParamId = param.identifier;
      break;
    }
  }
  if (matchIdParamId == null) return;
  titleNode.props.text.textValue = FFStringValue(
    variable: varFromPageParam(matchIdParamId.deepCopy()),
  );
}

// Binds value text nodes on WedstrijdDetailPage (Info tab) to individual string state fields.
// Each field (matchOpponent, matchDatetime, …) was added by _wireWedstrijdDetailPageLoad.
void _bindWedstrijdDetailInfoTexts(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;

  FFVariable? stateVar(String stateFieldName) {
    final stateField = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((f) => f?.parameter.identifier.name == stateFieldName, orElse: () => null);
    if (stateField == null) return null;
    final v = varFromPageState(stateField.parameter.identifier.deepCopy());
    v.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    return v;
  }

  final infoColumn = findDescendants(wc.node, (n) => n.name == 'MatchInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  FFNode infoRow(String label, String stateFieldName) {
    final valueText = UI.text('-', name: 'MatchInfoValue_$stateFieldName', style: UITextStyle.bodyMedium);
    final v = stateVar(stateFieldName);
    if (v != null) valueText.props.text.textValue = FFStringValue(variable: v);
    return UI.container(
      name: 'MatchInfoRow_$stateFieldName',
      padding: UIEdgeInsets.symmetric(vertical: 6, horizontal: 0),
      child: UI.column(crossAxisAlignment: UICrossAxisAlignment.start, spacing: 2, children: [
        UI.text(label, style: UITextStyle.labelSmall, color: UIColor.secondaryText),
        valueText,
      ]),
    );
  }

  infoColumn.children.clear();
  infoColumn.children.addAll([
    UI.text('Wedstrijd details', name: 'MatchInfoTitle', style: UITextStyle.titleMedium),
    infoRow('Tegenstander', 'matchOpponent'),
    infoRow('Datum & Tijd', 'matchDatetime'),
    infoRow('Locatie', 'matchLocation'),
    infoRow('Verzamelen', 'matchArrivalTime'),
    infoRow('Coach', 'matchCoachName'),
    infoRow('Fruitheid', 'matchFruitHeroName'),
    infoRow('Notities', 'matchNotes'),
  ]);
}

// Builds a varFromPageState(match) + accessDataStructField(fieldName) for
// WedstrijdDetailPage. nodeKeyRef is set to the page root key, not the
// individual widget key, matching how the DSL compiler resolves State().
FFVariable? _matchStateFieldVar(
  FFProject project,
  FFWidgetClass wc,
  String fieldName,
  String _unused,
) {
  FFIdentifier? matchStateFieldId;
  for (final field in wc.classModel.stateFields) {
    if (field.parameter.identifier.name == 'match') {
      matchStateFieldId = field.parameter.identifier;
      break;
    }
  }
  if (matchStateFieldId == null) return null;
  final structFieldId = _findStructFieldId(project, 'FootMatch', fieldName);
  if (structFieldId == null) return null;
  final v = varFromPageState(matchStateFieldId.deepCopy());
  v.operations.add(FFVariableOperation(
    accessDataStructField: FFAccessDataStructField(
      fieldIdentifier: structFieldId.deepCopy(),
    ),
  ));
  v.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  return v;
}

// Applies the club's primary color (from app state) to every AppBar background
// and sets the back button + title text color to white for maximum contrast.
// Runs idempotently: overwrites on every push so color changes in the portal
// take effect after the next DSL push.
void _applyBrandingToAllAppBars(FFProject project) {
  final primaryColorId = _findAppStateFieldId(project, 'primaryColor');
  if (primaryColorId == null) return;

  final brandingBg = colorFromStringVar(varFromAppState(primaryColorId.deepCopy()));
  // White uses SECONDARY_BACKGROUND theme token (#FFFFFF in the current theme).
  final whiteColor = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND),
  );

  for (final pageName in const [
    'WedstrijdDetailPage', 'BardienDetailPage', 'RijschemaDetailPage',
    'DirectChatPage', 'DocumentatiePage', 'TeamChatPage',
  ]) {
    final wc = findPage(project, name: pageName);
    if (wc == null) continue;
    final appBarNode = getPropertyChild(wc.node, 'appBar');
    if (appBarNode == null) continue;

    final proto = appBarNode.props.appBar.deepCopy();
    proto.backgroundColorValue = brandingBg.deepCopy();
    proto.backButtonColorValue = whiteColor.deepCopy();
    appBarNode.props.appBar = proto;

    // Title text → white so it contrasts against the dark primary background.
    final titleNode = getPropertyChild(appBarNode, 'title');
    if (titleNode != null && titleNode.props.hasText()) {
      final textProto = titleNode.props.text.deepCopy();
      textProto.colorValue = whiteColor.deepCopy();
      titleNode.props.text = textProto;
    }
  }
}

/// Returns the field identifier (name + key) for a named field on a named struct,
/// or null if the struct or field is not found. Use this instead of FFIdentifier(name: x)
/// when building variable operations — the FlutterFlow codegen resolves by key, not name.
FFIdentifier? _findStructFieldId(FFProject project, String structName, String fieldName) {
  for (final struct in project.backend.dataSchemaConfig.dataStructs) {
    if (struct.identifier.name == structName) {
      for (final field in struct.fields) {
        if (field.identifier.name == fieldName) {
          return field.identifier;
        }
      }
      break;
    }
  }
  return null;
}

void _removeComponentParamIfExists(FFProject project, String componentName, String paramName) {
  final component = project.getWidgetClassByName(componentName);
  if (component == null) return;
  final key = component.params.entries
      .where((e) => e.value.hasIdentifier() && e.value.identifier.name == paramName)
      .firstOrNull?.key;
  if (key != null) component.params.remove(key);
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
