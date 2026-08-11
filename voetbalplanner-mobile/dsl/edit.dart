library;

import 'dart:io';
import 'dart:math';
import 'dart:convert' show base64Encode;

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/client/project_error.dart' show ProjectError;
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart'
    show findCollection, findCollectionField, addCollectionField;
import 'package:flutterflow_ai/src/helpers/data_schema_helpers.dart'
    show addDataStruct, structField, findDataStruct;
import 'package:flutterflow_ai/src/helpers/project_helpers.dart'
    show addPage, addStateField, removePage;
import 'package:flutterflow_ai/src/helpers/data_type_helpers.dart'
    show dataStructType, stringType;
import 'package:flutterflow_ai/src/helpers/ensure_helpers.dart'
    show ensureDataStruct;
import 'package:flutterflow_ai/src/helpers/function_call_helpers.dart'
    show CodeExpressionArg, andConditionsVar, codeExpressionVar, colorFromStringVar, conditionVar, interpolateVar;
import 'package:flutterflow_ai/src/helpers/param_value.dart'
    show StaticParamValue, VariableParamValue;
import 'package:flutterflow_ai/src/helpers/state_update.dart'
    show StateFieldUpdate;
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show findDescendants, removeByKey, insertBeforeKey, getPropertyChild,
         unwrap, findParentByKey;
import 'package:flutterflow_ai/src/helpers/widget_helpers.dart'
    show setConditionalVisibility;
import 'package:flutterflow_ai/src/helpers/theme_helpers.dart'
    show setDarkModeEnabled;
import 'package:fixnum/fixnum.dart' show Int64;
import 'package:flutterflow_ai/src/helpers/nav_bar_helpers.dart'
    show setNavBarEnabled, addNavBarPage, removeNavBarPage, listNavBarPages, reorderNavBarPage;
import 'package:flutterflow_ai/src/helpers/widget_class_param_helpers.dart'
    show removePageParameter;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';
import 'package:flutterflow_ai/src/ui/actions.dart' show Actions;
import 'package:flutterflow_ai/src/ui/ui.dart' show UI;
import 'package:flutterflow_ai/src/ui/ui_types.dart'
    show UIBoxFit, UIColor, UITextStyle, UIMainAxisAlignment, UICrossAxisAlignment, UIEdgeInsets,
         UIProgressShape, UIKeyboardType, DynamicSource;
import 'package:voetbalplanner_mobile/flutterflow_project.dart' as ff;

// ── Snelmenu: bottom-sheet component + '+'-FAB op DashboardPage ───────────────
// FAB '+' op het dashboard opent een bottom sheet met snelle acties. Chatten gaat
// direct naar de chat-hub; wisselen gaat naar de bardienst-/rijschemalijst waar de
// bestaande wisselflow per dienst doorloopt; afmelden naar de wedstrijdenlijst.
void _buildQuickActionsSheet(App app) {
  app.component(
    'QuickActionsSheet',
    description:
        'Bottom sheet met snelle acties (chatten, wissel aanvragen, afmelden) vanaf het dashboard.',
    body: Column(
      crossAxis: CrossAxis.stretch,
      // Extra bottom-padding zodat de onderste knop op Android niet achter de
      // systeem-navigatiebalk valt (bottom sheet respecteert de safe area niet).
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: 40),
      spacing: 12,
      children: [
        Text('Snelle acties', style: Styles.titleMedium),
        Button('Chatten',
            name: 'QaChatButton',
            onTap: Navigate(ff.Pages.chatsPage),
            width: double.infinity,
            padding: 14),
        Button('Wissel bardienst',
            name: 'QaSwapBarButton',
            onTap: Navigate(ff.Pages.bardienPage),
            width: double.infinity,
            padding: 14),
        Button('Wissel rijden',
            name: 'QaSwapDriveButton',
            onTap: Navigate(ff.Pages.rijschemaPage),
            width: double.infinity,
            padding: 14),
        Button('Afmelden wedstrijd',
            name: 'QaAfmeldButton',
            onTap: Navigate(ff.Pages.wedstrijdenPage),
            width: double.infinity,
            padding: 14),
      ],
    ),
  );
}

// Bottom sheet met coach-acties op de wedstrijddetail (via de FAB). Elke optie
// zet AppState.matchActionMode en sluit de sheet; de wedstrijddetail toont dan
// de bijbehorende sectie (doelpunt toevoegen / gastspeler uitnodigen).
void _buildMatchActionsSheet(App app) {
  // Skelet; de werkelijke inhoud (menu + doelpunt-picker + gastspeler-picker)
  // wordt door _buildMatchActionsDialogBody (raw) opgebouwd, omdat we app-state-
  // gebonden dynamische lijsten + acties nodig hebben.
  app.component(
    'MatchActionsSheet',
    description: 'Dialoog met coach-acties op de wedstrijddetail: doelpunt toevoegen of gastspeler uitnodigen.',
    body: Column(
      name: 'MatchActionsRoot',
      crossAxis: CrossAxis.stretch,
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: 20),
      spacing: 12,
      children: [
        Container(name: 'MatchActionsPlaceholder'),
      ],
    ),
  );
}

void _addDashboardQuickActionsFab(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  final scaffold = wc.node;
  // Idempotent: don't add a second FAB on re-runs.
  if (scaffold.childPropertyMap.containsKey('floatingActionButton')) return;
  final fab = UI.fab(iconName: 'add', name: 'QuickActionsFab');
  Actions.onTap(
    fab,
    Actions.bottomSheet(project, componentName: 'QuickActionsSheet'),
  );
  scaffold.children.add(fab);
  scaffold.childPropertyMap['floatingActionButton'] =
      FFChildrenKeys(keyRefs: [FFNodeKeyReference(key: fab.key)]);
}

// ── Repair: collapse runaway duplicate actions in trigger action chains ───────
// Non-idempotent appenders (pre-guard) tacked the same scroll/clear/setState
// action onto chat send buttons on every push for ~150 runs, producing ~500-deep
// followUpAction chains → proto nesting >256 → server sdkValidateProject/push 500.
// This removes any action node whose serialized action equals one already kept
// earlier in the SAME chain; distinct actions are preserved in order. Runs during
// compile, so the project SENT to the server is shallow again. Idempotent.
String? _actionSig(FFActionNode n) {
  if (!n.hasAction()) return null;
  // Each duplicate carries a unique FFAction.key; strip it so structurally
  // identical actions compare equal.
  final a = n.action.deepCopy()..clearKey();
  return base64Encode(a.writeToBuffer());
}

int _dedupFromRoot(FFActionNode start) {
  var removed = 0;
  final seen = <String>{};
  void recurseBranches(FFActionNode n) {
    if (!n.hasConditionActions()) return;
    for (final te in n.conditionActions.trueActions) {
      if (te.hasTrueAction()) removed += _dedupFromRoot(te.trueAction);
    }
    if (n.conditionActions.hasFalseAction()) {
      removed += _dedupFromRoot(n.conditionActions.falseAction);
    }
  }

  var cur = start;
  final s0 = _actionSig(cur);
  if (s0 != null) seen.add(s0);
  recurseBranches(cur);
  while (cur.hasFollowUpAction()) {
    final nxt = cur.followUpAction;
    final sig = _actionSig(nxt);
    if (sig != null && seen.contains(sig)) {
      if (nxt.hasFollowUpAction()) {
        cur.followUpAction = nxt.followUpAction;
      } else {
        cur.clearFollowUpAction();
      }
      removed++;
    } else {
      if (sig != null) seen.add(sig);
      recurseBranches(nxt);
      cur = nxt;
    }
  }
  return removed;
}

int _dedupNodeTriggers(FFNode node) {
  var removed = 0;
  for (final ta in node.triggerActions) {
    if (ta.hasRootAction()) removed += _dedupFromRoot(ta.rootAction);
  }
  for (final child in node.children) {
    removed += _dedupNodeTriggers(child);
  }
  return removed;
}

int dedupRunawayActionChains(FFProject project) {
  var removed = 0;
  for (final wc in project.widgetClasses.values) {
    removed += _dedupNodeTriggers(wc.node);
  }
  return removed;
}

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
    _ensureSharedBarDutiesAppStateField(project);
    _ensureAvailableTeamsAppStateField(project);
    _setupBardienFilter(project);
    _rebindBardienListViewToAppState(project);
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
    final removed = dedupRunawayActionChains(project);
    if (removed > 0) {
      stderr.writeln('Collapsed $removed runaway duplicate action nodes.');
    }
  });
  // ── Global design: larger typography + club-brand default primary ─────────────
  // Font families preserved from the project defaults:
  //   Inter Tight → titles/headlines   Inter → body/label
  // Body text bumped +2pt for readability; titles bumped +2pt for hierarchy.
  app.typography('bodySmall',   fontFamily: 'Inter',       fontSize: 14, fontWeight: 400);
  app.typography('bodyMedium',  fontFamily: 'Inter',       fontSize: 16, fontWeight: 400);
  app.typography('bodyLarge',   fontFamily: 'Inter',       fontSize: 18, fontWeight: 400);
  app.typography('titleSmall',  fontFamily: 'Inter Tight', fontSize: 18, fontWeight: 600);
  app.typography('titleMedium', fontFamily: 'Inter Tight', fontSize: 20, fontWeight: 600);
  app.typography('titleLarge',  fontFamily: 'Inter Tight', fontSize: 22, fontWeight: 600);
  app.typography('labelLarge',  fontFamily: 'Inter',       fontSize: 17, fontWeight: 500);
  app.typography('labelMedium', fontFamily: 'Inter',       fontSize: 14, fontWeight: 500);
  // Default primary: navy matches the club fallback color (#1E3A5F).
  // NavBar selected icon, TabBar indicator, and any widget not explicitly
  // recolored by _applyBrandingToAllButtons will use this static value.
  app.themeColor('primary', 0xFF1E3A5F);

  // Force light theme. The app is designed light-only and the dark palette is
  // unconfigured (all colors transparent 0x00000000), so on a dark-mode phone
  // the UI renders unreadable. Disabling dark mode makes FlutterFlow always use
  // the branded light theme regardless of the phone's system setting.
  app.raw((project) => setDarkModeEnabled(project, enabled: false));

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

    // Remove fill color from ProfielInfoCard (visually flatter look).
    _removeProfielInfoCardFill(project);

    // Highlight the user's own name within the "Leden" (bardienst) and
    // "Rijders" (rijschema) lists on the detail pages — green pill where
    // the name appears. (_wireOwnNameHighlight runs in a later raw block,
    // AFTER _wireBardienDetailPageUI / _wireRijschemaDetailPageUI rebuild
    // their info columns — otherwise the swap is overwritten each push.)
    _removeOwnNameChip(project, 'BardienDetailPage');
    _removeOwnNameChip(project, 'RijschemaDetailPage');

    // Bardiensten: filter by member's team + update onLoad
    _ensureSharedBarDutiesAppStateField(project);
    _ensureAvailableTeamsAppStateField(project);
    _setupBardienFilter(project);
    _rebindBardienListViewToAppState(project);

    // AppBar (+ back button) on chat sub-pages, global NavBar for main pages
    _addChatPageAppBars(project);
    _setupNavBar(project);
    _addMagicLinkInfrastructure(project);
    _makeLoginPageScrollable(project);
    _fixLoginButtonBindings(project);
  });

  // Make all containers and buttons on ProfielPage full width.
  app.editPage(ff.Pages.profielPage, (page) {
    for (final key in const [
      'Container_uw9q34os', // ProfielInfoCard
      'Container_cbnepclh', // original avatar/info container
    ]) {
      try {
        page.update(page.findByKey(key), (p) => p.size(width: double.infinity));
      } catch (_) {}
    }
    // Uitloggen — HandleidingButton/BugReportButton verhuisd naar AppDrawer.
    for (final key in const [
      'Button_wvz4j2lc', // Uitloggen
    ]) {
      try {
        page.update(page.findByKey(key), (p) => p.size(width: double.infinity));
      } catch (_) {}
    }
  });

  // Biometric button on LoginPage
  _addBiometricButton(app);
  _addLedenLoginSection(app);

  // ── Chat: Firestore collections ───────────────────────────────────────────────
  // New unified schema (replaces teamChats + directMessages + groupMessages + chatGroups).
  //   chatConversations — thread metadata (type, title, lastMessage, participants)
  //   chatMessages      — all messages across all conversation types
  // Legacy collections kept in the project for data safety; no longer used by new pages.
  try {
    app.collection(
      'chatConversations',
      description: 'Gesprekken: teamchat, 1-op-1 en staffgroepen.',
      fields: {
        'conversationId': string,
        'type':           string,
        'teamId':         string,
        'title':          string,
        'participantIds': listOf(string),
        'lastMessage':    string,
        'lastMessageAt':  dateTime,
        'createdAt':      dateTime,
        'hasUnread':      bool_,
        'unreadCount':    int_,
      },
    );
  } catch (_) {}

  try {
    app.collection(
      'chatMessages',
      description: 'Alle chatberichten (teamchat, direct, staffgroepen).',
      fields: {
        'conversationId': string,
        'text':           string,
        'senderId':       string,
        'senderName':     string,
        'createdAt':      dateTime,
        'isRead':         bool_,
        'deleted':        bool_,
      },
    );
  } catch (_) {}

  // 'appUsers' collection is managed remotely — no DSL declaration here.
  // (Re-declaring via app.collection(...) raises ensureCollection-payload-mismatch
  // errors after schema drift, even when fields look identical to the typed SDK.)

  // Construct handles directly — works whether or not the typed SDK has been refreshed
  // after the first push that creates these collections.
  final chatConversations = FirestoreCollectionHandle(
    'chatConversations',
    {
      'conversationId': string,
      'type':           string,
      'teamId':         string,
      'title':          string,
      'participantIds': listOf(string),
      'lastMessage':    string,
      'lastMessageAt':  dateTime,
      'createdAt':      dateTime,
    },
    description: 'Gesprekken: teamchat, 1-op-1 en staffgroepen.',
  );
  final chatMessages = FirestoreCollectionHandle(
    'chatMessages',
    {
      'conversationId': string,
      'text':           string,
      'senderId':       string,
      'senderName':     string,
      'createdAt':      dateTime,
    },
    description: 'Alle chatberichten (teamchat, direct, staffgroepen).',
  );

  // chatGroups collection handle — collection already exists in FF project.
  // Use handle-only to avoid ensureCollection conflicts with the existing schema.
  final chatGroups = FirestoreCollectionHandle(
    'chatGroups',
    {
      'name':      string,
      'teamId':    string,
      'members':   listOf(string),
      'createdBy': string,
      'createdAt': dateTime,
    },
    description: 'Gebruikersaangemaakte chatgroepen per team.',
  );

  // ── Chat AppState fields ──────────────────────────────────────────────────────
  // These must exist at DSL compile time because _buildChatDetailPage references
  // them via UpdateAppState.set(). They are also added by _addChatInfrastructure
  // (raw phase) as a fallback for subsequent pushes.
  try { app.state('currentConversationId', string); } catch (_) {}
  try { app.state('pendingMessageText', string); } catch (_) {}
  try { app.state('pendingDirectUserId', string); } catch (_) {}
  try { app.state('pendingDirectUserName', string); } catch (_) {}
  try { app.state('groupMemberNames', listOf(string)); } catch (_) {}
  // Gedeelde lijst van gekozen bug-screenshot-paden. Móét AppState zijn: de
  // PickBugScreenshot- en SubmitBugReport-custom-actions zijn aparte gegenereerde
  // bestanden met elk hun eigen module-globals, dus een gewone Dart-global wordt
  // NIET gedeeld tussen pick en submit (screenshots gingen daardoor verloren).
  try { app.state('bugScreenshotPaths', listOf(string)); } catch (_) {}
  // Trainingen + af-/aanmeld-parameters (gestaged in AppState; custom actions
  // lezen ze zodat we geen scalar-String-args nodig hebben — die geven FF-
  // validator-issues).
  try { app.state('pendingTrainingScheduleId', string); } catch (_) {}
  try { app.state('pendingTrainingDate',       string); } catch (_) {}
  try { app.state('pendingAfmeldReason',       string); } catch (_) {}
  try { app.state('pendingMatchId',            string); } catch (_) {}
  // True als de gebruiker aan >1 team gekoppeld is (dashboard team-switcher).
  try { app.state('hasMultipleTeams',          bool_); } catch (_) {}
  // Coach-actie gekozen via de FAB op de wedstrijddetail: '' | 'goal' | 'invite'.
  // Bepaalt welke weergave de MatchActionsSheet-dialoog toont (menu/picker).
  try { app.state('matchActionMode',           string); } catch (_) {}
  // Werk-state voor de coach-dialoog (pickers lezen app-state, niet pagina-state).
  // dialogView: 'menu' | 'goal' | 'invite' — welke weergave de dialoog toont.
  try { app.state('dialogView',                string); } catch (_) {}
  try { app.state('dialogMatchId',             string); } catch (_) {}
  try { app.state('dialogScorerName',          string); } catch (_) {}
  try { app.state('dialogTeamId',              string); } catch (_) {}

  // ── Chat custom actions ───────────────────────────────────────────────────────
  // Declared at DSL level so CallCustomAction.named(...) in _buildChatDetailPage
  // and app.editPageOnLoad resolve correctly at DSL compile time.
  // _addChatInfrastructure (raw phase) later calls updateCustomAction to keep the
  // code in sync on every push.
  // These actions already exist in the project; use existingCustomAction so
  // the DSL compiler skips code-equality validation (the raw phase keeps the
  // code in sync via updateCustomAction). CallCustomAction.named resolves from
  // the project proto by name and does not need a compile-phase declaration.
  try { app.existingCustomAction('SendMessage'); } catch (_) {}
  try { app.existingCustomAction('GetOrCreateDirectConversation'); } catch (_) {}
  try { app.existingCustomAction('InitializeTeamConversation'); } catch (_) {}

  // ── Chat pages ────────────────────────────────────────────────────────────────
  // ChatDetailPage: universal chat (team / direct / staffgroep).
  // Replaces the old TeamChatPage, DirectChatPage, GroupChatPage.
  _buildChatDetailPage(app, chatMessages);

  // Add 'conversations' + 'chatGroups' state fields to ChatsPage (idempotent).
  app.editPageState(ff.Pages.chatsPage, (state) {
    state.ensureField('conversations', listOf(chatConversations));
    state.ensureField('chatGroups',    listOf(chatGroups));
  });

  // Replace ChatsPage load chain: InitializeTeamConversation → query chatConversations
  // → SetState → query chatGroups → SetState.
  // GetTeamMembers is appended afterwards by _wireChatsPageLoad (raw).
  // editPageOnLoad replaces the trigger on every push so it is always up-to-date.
  app.editPageOnLoad(ff.Pages.chatsPage, [
    CallCustomAction.named('InitializeTeamConversation', arguments: {}),
    // Abonneer op chat-push-topics (user_<email> + team_<teamId>). Idempotent:
    // FCM dedupliceert dubbele subscribeToTopic-calls. Re-subscribet ook na een
    // FCM-token-refresh van een terugkerende gebruiker.
    CallCustomAction.named('SubscribeToChatTopics', arguments: {}),
    FirestoreQuery(
      chatConversations,
      limit: 50,
      singleTimeQuery: true,
      outputAs: 'loadedConversations',
    ),
    SetState('conversations', ActionOutput('loadedConversations')),
    FirestoreQuery(
      chatGroups,
      limit: 100,
      singleTimeQuery: true,
      outputAs: 'loadedGroups',
    ),
    SetState('chatGroups', ActionOutput('loadedGroups')),
  ]);

  _buildMagicLinkVerifyPage(app);

  // Fix login page labels + AppBars on new chat pages.
  app.raw((project) {
    // Ensure GetStaffGroups API endpoint exists in the VoetbalPlannerAPI group.
    _addGetStaffGroupsEndpoint(project);
    _fixLoginPageLabels(project);
    _addDocumentatieAppBar(project);
    _wireChatBadge(project);
    _wireChatTopicSubscription(project);
  });

  // Chat navigation button on WedstrijdenPage → ChatsPage.
  _addChatButton(app);

  // Documentation page for members + handleiding button on ProfielPage.
  final documentSection = ff.Structs.documentSection;
  try {
    app.struct('DocumentSection', {
      'id':       string,
      'category': string,
      'title':    string,
      'body':     string,
    });
  } catch (_) {}
  // Fix de FlutterFlow web-deploy: twee FF-default packages compileren niet meer
  // op recente Flutter (de deploy faalde hierop). Forceer compatibele versies.
  app.raw((project) => _fixIncompatiblePubVersions(project));
  app.raw((project) => _addDocumentationEndpoint(project));
  _buildDocumentatiePage(app, documentSection);
  app.raw((project) => _wireDocumentationPageLoad(project));
  // Handleiding + Bug-melden zijn naar de AppDrawer verhuisd; ruim eventuele
  // bestaande knoppen op ProfielPage op.
  app.raw((project) => _removeProfielButton(project, 'HandleidingButton'));
  app.raw((project) => _removeProfielButton(project, 'BugReportButton'));
  app.raw((project) => _addGuardianButton(project));
  _buildBugReportPage(app);
  app.raw((project) => _ensureBugReportCustomAction(project));
  app.raw((project) => _buildBugReportPageBody(project));
  app.raw((project) => _wireBugReportTextFields(project));
  app.raw((project) => _wireBugReportSubmit(project));

  // ─── Wissel (swap) feature ────────────────────────────────────────────────
  app.raw((project) => _addSwapStructFields(project));
  // ProfielPage teamlijst — ná _addSwapStructFields zodat TeamOption.role bestaat.
  app.raw((project) => _addProfielTeamsList(project));
  app.raw((project) => _addSwapParamsToBarDutyCard(project));

  final swapMember = ff.Structs.swapMember;
  try {
    // SwapMember already exists; email field added via raw mutation below.
  } catch (_) {}

  // Ensure SwapMember has email + external_id + hasAppAccount fields. external_id
  // (lidnummer) is altijd uniek per lid en wordt gebruikt voor de directe-chat
  // conversationId zodat sender + ontvanger dezelfde id berekenen, ook als hun
  // app-login-email afwijkt van Sportlink-email. hasAppAccount markeert leden
  // die de app nog niet hebben geactiveerd.
  app.raw((project) {
    final s = findDataStruct(project, name: 'SwapMember');
    if (s == null) return;
    void addField(String name, FFBaseDataType type) {
      if (s.fields.any((f) => f.identifier.name == name)) return;
      s.fields.add(FFParameter(
        identifier: FFIdentifier(name: name, key: generateRandomAlphaNumericString()),
        dataType: FFDataTypeV2(scalarType: type),
      ));
    }
    addField('email',         FFBaseDataType.String);
    addField('externalId',    FFBaseDataType.String);
    addField('hasAppAccount', FFBaseDataType.Boolean);
  });

  // AppState intermediary for GetAppUsersAsMembers. Only declared if not yet present —
  // skip if already exists to avoid payload-mismatch on re-push.
  app.raw((project) {
    final exists = project.appState.fields.any(
      (f) => f.parameter.identifier.name == 'pendingTeamMembers',
    );
    if (exists) return;
    final swapStruct = project.backend.dataSchemaConfig.dataStructs.firstWhere(
      (s) => s.identifier.name == 'SwapMember',
      orElse: () => throw StateError('SwapMember struct not found'),
    );
    project.appState.fields.add(
      FFAppStateField(
        parameter: FFParameter(
          identifier: FFIdentifier(
            name: 'pendingTeamMembers',
            key: generateRandomAlphaNumericString(),
          ),
          dataType: FFDataTypeV2(
            listType: FFDataTypeV2(
              scalarType: FFBaseDataType.DataStruct,
              subType: FFSubType(
                dataStructIdentifier: swapStruct.identifier.deepCopy(),
              ),
            ),
          ),
        ),
      ),
    );
  });

  // StaffGroupItem: create struct and get handle for editPageState below.
  // StructHandle can be constructed by name even when the struct already exists —
  // used as a type reference in app.editPageState without touching the proto.
  final staffGroupItemHandle = StructHandle(
    'StaffGroupItem',
    {'id': string, 'name': string},
    description: generatedProjectStructDescription,
  );
  try {
    app.struct('StaffGroupItem', {'id': string, 'name': string});
  } catch (_) {}

  // TeamOption (id + name + role/functie per team) bestaat al in het project en
  // wordt niet opnieuw ge-ensure'd — na het toevoegen van 'role' verschilt de
  // payload van een verse declaratie, wat ensureDataStruct afwijst. De struct is
  // beschikbaar via ff.Structs.teamOption; het 'role'-veld wordt (idempotent)
  // geborgd door _addSwapStructFields.

  // Trainingen: structs voor de GetTrainings-response. Veldnamen = exact de
  // (snake_case) JSON-keys uit TrainingController, zodat maybeFromMap direct mapt.
  try {
    app.struct('Afmelding', {'naam': string, 'reden': string});
  } catch (_) {}
  // Doelpunt (score-beheer). Velden = JSON-keys uit GoalResource.
  try {
    app.struct('GoalItem', {
      'id': string,
      'minute': string,
      'type': string,
      'scorerName': string,
      'assistName': string,
    });
  } catch (_) {}
  // De TrainingItem-struct bestaat al op de backend (incl. de telling-velden
  // aangemeld/afgemeld). 'm hier opnieuw declareren via app.struct/ensure botst
  // op de uitgebreide payload, dus dat doen we niet. _ensureTrainingItemCountFields
  // houdt de telling-velden idempotent aanwezig.
  app.raw((project) => _ensureTrainingItemCountFields(project));
  // AppState 'trainings' = List<TrainingItem>, gevuld door GetTrainings. Via raw
  // idempotente helper (app.state-ensure botst met een al bestaand veld met
  // afwijkende payload).
  app.raw((project) => _ensureTrainingsAppStateField(project));
  // AppState 'matchGoals' = List<GoalItem> (coach-scorebeheer), gevuld door GetMatchGoals.
  app.raw((project) => _ensureMatchGoalsAppStateField(project));
  // AppState 'scoreTeamMembers' = List<SwapMember> (tikbare maker-keuze bij score).
  app.raw((project) => _ensureScoreTeamMembersField(project));
  app.raw((project) => _ensureDialogListFields(project));
  // Custom actions: trainingen ophalen + af-/aanmelden (training & wedstrijd).
  app.raw((project) => _addTrainingsCustomActions(project));
  // Native FF POST-endpoints voor af-/aanmelden (CORS-proof, i.t.t. custom http).
  app.raw((project) => _addAfmeldEndpoints(project));
  // Native endpoints voor score-beheer (doelpunten ophalen/toevoegen/verwijderen).
  app.raw((project) => _addScoreEndpoints(project));
  app.raw((project) => _addGuestInviteEndpoints(project));
  // (De dashboard-trainingen-sectie wordt verderop toegevoegd, ná _wireDashboardLoad
  // die de on-load-chain elke push opnieuw opbouwt — anders wordt GetTrainings gewist.)

  // Ensure 'staffGroups' state field exists on ChatsPage.
  // Using app.editPageState (DSL compile phase) so the struct reference is
  // properly resolved by the validator — raw-phase addStateField was rejected.
  app.editPageState(ff.Pages.chatsPage, (state) {
    state.ensureField('staffGroups', listOf(staffGroupItemHandle));
  });

  // ── ChatsPage hub ─────────────────────────────────────────────────────────
  // Declared AFTER swapMember so teamMembers listOf(swapMember) compiles.
  _buildChatsPage(app, chatConversations, swapMember);

  // Wire ChatsPage onLoad + dynamic content via app.raw() so all state fields
  // are present when rawMutations execute.
  app.raw((project) {
    _removeDeadChatsPageTrigger(project);
    _wireChatsPageLoad(project);
    _wireChatsPageConversationsList(project);
    _wireChatsPageMemberStrip(project);
    // Apply chatGroups filter BEFORE conversationsFilter so each query gets
    // the right collection field identifier (both filter on teamId == currentTeamId,
    // but the collectionFieldIdentifier must reference the correct collection schema).
    _wireChatsPageGroupsFilter(project);
    _wireConversationsFilter(project);
    // Append re-query after SendMessage so the sender's own message appears immediately.
    // Must run BEFORE _wireChatDetailFilters so the conversationId filter is applied.
    _fixChatSendRefresh(project);
    _wireChatDetailFilters(project);
    // Group creation wiring.
    _addMembersFieldToChatGroups(project);
    // Ensure selectedMemberNames state field before wiring submit (which reads it).
    _ensureCreateGroupSelectedMemberNames(project);
    _wireCreateGroupPageLoad(project);
    _wireCreateGroupMembersBinding(project);
    _upgradeCreateGroupCheckboxes(project);
    _wireCreateGroupSubmitAction(project);
    _wireChatsPageGroupsList(project);
    _wireNewGroupButton(project);
    // Staff groups (Laravel-managed) in chat — state field already ensured above.
    _wireChatsPageStaffGroupsLoad(project);
    _wireChatsPageStaffGroupsList(project);
    _makeChatsPageBodyScrollable(project);
    _fixGroupChipNameBinding(project);
    _fixChatsPageListViewShrinkWrap(project);
    _fixMemberChipStyle(project);
    _fixDirectMemberChipStyle(project);
    _removeChatsDebugBanner(project);
    // Fix stale DirectMemberChip tap (idempotent guard in _wireChatsPageMemberStrip
    // prevented updating it once the strip was built with the wrong navigate action).
    _fixMemberStripTapAction(project);
    // Make GroupChip and StaffGroupChip taller (WhatsApp-style row height).
    _makeGroupChipsTaller(project);
    // Convert DirectMember from horizontal chips to vertical list rows.
    _convertDirectMembersToList(project);
    // Switch chatGroups Firestore query to real-time (singleTimeQuery=false) so
    // the groups list updates immediately after creating a new group.
    _fixChatsPageGroupsRealtime(project);
    // Add memberNames field to chatGroups.
    _addMemberNamesFieldToChatGroups(project);
    // Build GroupMembersPage if not yet present.
    _buildGroupMembersPageRaw(project);
    // Build TeamMembersPage (lijst van team leden).
    _buildTeamMembersPage(project);
    // Voeg "Toon leden" subtitle + tap-naar-leden toe aan ChatDetailPage + TeamChatPage AppBars.
    _addTeamMembersSubtitleToChatDetail(project);
    // Rebuild GroupChatPage AppBar with the title param and delete button.
    _resetGroupChatPageAppBar(project);
    // Fix GroupChatPage: senderId authToken→userEmail, bubble visibility, and send refresh wait.
    _fixGroupChatPage(project);
    // Nuclear rebuild of GroupChatPage bubbles with left/right split (same pattern as TeamChat).
    _rebuildGroupChatBubbles(project);
    // Show group admin (createdBy) below the AppBar in GroupChatPage.
    _addGroupChatAdminDisplay(project);
    // Fix TeamChatPage: senderId authToken→userEmail, teamId widget.teamId→currentTeamId,
    // refresh query WHERE, and text widget binding.
    _fixTeamChatSendButton(project);
    _addClearMessageTextToTeamChatSend(project);
    _fixTeamChatMessageTextBinding(project);
    // Rebuild TeamChatPage bubbles with left/right split matching ChatDetailPage.
    _fixTeamChatBubbles(project);
    // Rebuild DirectChatPage bubbles with left/right split; fix senderId authToken→userEmail.
    _fixDirectChatBubbles(project);
    _fixDirectChatSendButton(project);
    // After all send buttons are wired, append BumpConversationUnread call so
    // chatConversations.unreadCount gets incremented after every team/direct/
    // group message — this is what the WatchUnreadChatCount stream sums.
    _wireBumpUnreadOnAllChatSends(project);
    // Add hasUnread + unreadCount fields to chatConversations, then show badges.
    _addUnreadFieldsToConversations(project);
    _addIsReadFieldToChatMessages(project);
    _addDeletedFieldToChatCollections(project);
    // DirectChatPage was loading ALL directMessages docs (geen WHERE clause)
    // waardoor iedereen elkaars 1-op-1 berichten zag. Scope alles via een
    // deterministische conversationId per gebruikers-paar.
    _scopeDirectChatToConversation(project);
    // DEBUG: toon live AppState.directConvId bovenaan DirectChatPage.
    _addDirectChatConvIdDebug(project);
    _addConversationBadges(project);
    // Reset unread count when user opens a conversation.
    _wireMarkConversationRead(project);
    // Nuclear rebuild of ChatDetailPage (staff chat) bubbles with left/right split + read receipts.
    _rebuildChatDetailBubbles(project);
    // Bind TextField controller to page state so SetState.clear() visually clears it.
    _fixChatTextFieldController(project);
    // Same fix for GroupChatPage: localStateValue=true removes the 2s debounce
    // so messageText is always in sync when the send button fires.
    _fixGroupChatTextField(project);
    // Same fix for TeamChatPage.
    _fixTeamChatTextField(project);
    // Same fix for DirectChatPage.
    _fixDirectChatTextField(project);
    // Hide the current user's own chip in the direct-message member strip.
    _fixDirectMemberSelfFilter(project);
    // Add "Bewaar in agenda" button to bardienst, wedstrijd and rijschema detail pages.
    _addCalendarButtons(project);
    _fixLoginKeyboard(project);
    _dedupLedenLoginSection(project);
    _fixLoginTextFieldDebounce(project);
    _addLoginValidation(project);
    // Register the GetAppUsersAsMembers custom action + keep login upserts
    // so the appUsers collection is silently populated on every login.
    // The chain replacement (_setupAppUsersRegistry) is intentionally NOT called
    // here: the appUsers collection is still empty on first push — only after all
    // team members have logged in once can we safely switch the member strip source.
    _registerGetAppUsersAction(project);
    _addChatEditDeleteFeature(project);
    _addSoftDeleteDisplay(project);
    _sortChatMessagesByCreatedAt(project);
    _addScrollToBottomAfterSend(project);
    _addClearTextFieldToAllChatSends(project);
    // Stretch item columns and fix row alignment first.
    _fixChatDetailBubbleAlignment(project);
    // Remove Row wrappers so bubbles fill the column's full width directly.
    _removeBubbleRowWrappers(project);
    _setChatBubbleWidths(project);
    _addChatMsgColPadding(project);
    _setAllButtonHeights(project);
  });

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
  app.raw((project) => _addGuardianEndpoints(project));
  // Ná _addGuardianEndpoints: het DeleteAccount-endpoint moet bestaan voordat
  // de knop-chain er naar verwijst.
  app.raw((project) => _addDeleteAccountButton(project));

  // ── Guardian pagina's ─────────────────────────────────────────────────────
  _buildGuardianPages(app);
  app.raw((project) {
    _buildGuardianPageBody(project);
    _buildGuardianCreateParentPageBody(project);
    _buildGuardianRequestPageBody(project);
    _buildGuardianSelfRegisterPageBody(project);
    _wireGuardianPageLoad(project);
    _wireGuardianRespondActions(project);
    _wireGuardianCreateParentTextFields(project);
    _wireGuardianCreateParentSubmit(project);
    _wireGuardianRequestTextFields(project);
    _wireGuardianRequestSubmit(project);
    _wireGuardianSelfRegisterTextFields(project);
    _wireGuardianSelfRegisterSubmit(project);
    _makeGuardianSelfRegisterPageScrollable(project);
    _addGuardianRegisterLinkToLoginPage(project);
    // Foutmelding-tekst wit maken (rode achtergrond) — ook op reeds gebouwde
    // pagina's, want de body-builders zijn idempotent.
    _fixErrorTextColors(project);
  });

  _buildSwapRequestCard(app, swapRequest);
  _buildWisselAanvraagPage(app, swapMember);
  _buildWisselVerzoekenPage(app, swapRequest);

  // MatchCard: add matchId + coachName params and navigate internally.
  app.editComponentParams(ff.Components.matchCard, (params) {
    params.ensureParam('matchId', string.withDefault(''), description: 'Match ID for navigation');
    params.ensureParam('coachName', string.withDefault(''), description: 'Coach name');
    params.ensureParam('opponentLogo', string.withDefault(''), description: 'Tegenstander clublogo (URL)');
    params.ensureParam('isHome', bool_.withDefault(false), description: 'Thuiswedstrijd (true) of uit (false)');
  });
  // Remove unused action params idempotently (removeParam throws if already gone).
  app.raw((project) => _removeComponentParamIfExists(project, 'MatchCard', 'onTapAction'));
  app.editComponent(ff.Components.matchCard, (c) {
    c.ensureActions(
      c.findByKey('Container_oa8ojh9i'),
      triggerType: FFActionTriggerType.ON_TAP,
      actions: [Navigate(ff.Pages.wedstrijdDetailPage, params: {'matchId': Param(ff.Components.matchCard.params.matchId)})],
    );
    c.ensureInsertedAfter(
      c.findByKey('Text_jhrh5km7'),
      Row(
        name: 'MatchCardCoachRow',
        spacing: 8,
        children: [
          Icon('sports_soccer', size: 14, color: Colors.secondaryText),
          Text(Param('coachName'),
              name: 'MatchCardCoachText', style: Styles.bodySmall),
        ],
      ),
    );

    // Tegenstander-clublogo vooraan (leading) met 5px padding rechts, bal-icoon
    // als fallback. Eén wrapper-node (i.p.v. twee losse siblings vóór dezelfde
    // anchor) houdt de insert idempotent bij herhaalde pushes.
    c.ensureInsertedBefore(
      c.findByKey('Column_s11zr5yj'),
      Container(
        name: 'MatchCardLogoWrap',
        padding: EdgeInsets.only(right: 5),
        child: Row(
          name: 'MatchCardLogoRow',
          children: [
            Image(
              Param('opponentLogo'),
              isNetwork: true,
              fit: ImageFit.cover,
              width: 40,
              height: 40,
              borderRadius: 20,
              name: 'MatchCardLogoImg',
              visible: Not(Equals(Param('opponentLogo'), '')),
            ),
            Icon('sports_soccer', size: 28, color: Colors.primary,
                name: 'MatchCardLogoFallbackIcon',
                visible: Equals(Param('opponentLogo'), '')),
          ],
        ),
      ),
    );

    // Thuis/Uit-badge boven de tegenstandernaam.
    c.ensureInsertedBefore(
      c.findByKey('Text_u4gsnnpe'),
      Row(
        name: 'MatchCardHomeAwayRow',
        spacing: 6,
        children: [
          Container(
            name: 'MatchCardThuisBadge',
            visible: Param('isHome'),
            color: Colors.primary,
            padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            borderRadius: 8,
            child: Text('Thuis', style: Styles.labelSmall, color: Colors.secondaryBackground),
          ),
          Container(
            name: 'MatchCardUitBadge',
            visible: Not(Param('isHome')),
            color: Colors.secondaryText,
            padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            borderRadius: 8,
            child: Text('Uit', style: Styles.labelSmall, color: Colors.secondaryBackground),
          ),
        ],
      ),
    );
  });

  // Ruim de vroegere losse leading-nodes (bare logo + fallback-icoon) op; die
  // zijn vervangen door de idempotente MatchCardLogoWrap-container. removeWhere
  // is veilig ongeacht of ze nog bestaan.
  app.raw((project) {
    final mc = project.getWidgetClassByName('MatchCard');
    if (mc == null) return;
    final row = findDescendants(mc.node, (n) => n.key == 'Row_bzwq8x08').firstOrNull;
    row?.children.removeWhere((c) =>
        c.name == 'MatchCardOpponentLogo' || c.name == 'MatchCardLogoFallback');
  });

  // WedstrijdDetailPage must exist before match navigation can be set up.
  _buildWedstrijdDetailPage(app);
  // Geef alle waarde-velden een default '' zodat de info-teksten (Text(_model.X!))
  // nooit op null crashen — ook niet vóór/zonder een geslaagde API-load.
  app.editPageState(ff.Pages.wedstrijdDetailPage, (state) {
    for (final f in const [
      'matchOpponent', 'matchDatetime', 'matchLocation', 'matchArrivalTime',
      'matchCoachName', 'matchFruitHeroName', 'matchVlaggerName', 'matchNotes', 'apiStatus',
      'matchStatus', 'matchMagAfmelden', 'matchMagOpstelling', 'matchGoalsSummary',
      'matchTeamId', 'selectedScorerName', 'inviteTeamId',
    ]) {
      state.ensureField(f, string.withDefault(''));
    }
    // Gastspeler-uitnodigen: club-teams + spelers van het gekozen team.
    state.ensureField('inviteTeams', listOf(ff.Structs.teamOption));
    state.ensureField('inviteGuestMembers', listOf(ff.Structs.swapMember));
  });
  // Bind matchId + coachName on the MatchCard instance via the DSL compile path.
  app.editPage(ff.Pages.wedstrijdenPage, (page) {
    page.setComponentParam(
      page.findByKey('Container_f1p12fqf'),
      'matchId',
      ItemRef()['id'],
    );
    page.setComponentParam(
      page.findByKey('Container_f1p12fqf'),
      'coachName',
      ItemRef()['coachName'],
    );
    page.setComponentParam(
      page.findByKey('Container_f1p12fqf'),
      'opponentLogo',
      ItemRef()['opponentLogo'],
    );
    page.setComponentParam(
      page.findByKey('Container_f1p12fqf'),
      'isHome',
      ItemRef()['isHome'],
    );
  });
  app.raw((project) {
    _debugStructsAndEndpoints(project);
    _wireWedstrijdDetailPageLoad(project);
    _bindWedstrijdDetailAppBarTitle(project);
    _bindWedstrijdDetailInfoTexts(project);
    _fixMatchInfoWidth(project);
    _makeWedstrijdDetailInfoTabScrollable(project);
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
    _addBardienAanmeldenButton(project);
    _addBardienWisselButton(project);
    _addBardienNavigation(project);
  });

  // RijschemaDetailPage: new page for driving assignment details.
  _buildRijschemaDetailPage(app);
  app.raw((project) {
    _wireRijschemaDetailPageLoad(project);
    _wireRijschemaDetailPageUI(project);
    // Bouw RijschemaPage body opnieuw op als die ontbreekt + vul cardColumn met
    // basis-rijen zodat _wireRijschemaCardDriverRow's `insert(2, ...)` werkt.
    _restoreRijschemaBodyIfMissing(project);
    _ensureRijschemaCardBaseRows(project);
    _wireRijschemaNavigation(project);
    _wireRijschemaCardDriverRow(project);
  });

  // Highlight eigen naam in Leden / Rijders lijst — moet na de detail-page UI
  // rebuilds lopen anders herschrijft die de gehighlighte tree weer terug.
  app.raw((project) => _wireOwnNameHighlight(project));

  // Wire WedstrijdDetailPage: add fruitheld + rijden swap buttons into MatchInfoColumn.
  app.raw((project) => _wireMatchSwap(project));
  // Af-/aanmelden met reden op WedstrijdDetailPage (ná de info-kolom + swap-knoppen).
  app.raw((project) => _wireWedstrijdAfmelden(project));
  // Coach: doelpunten/score-sectie op de wedstrijddetail (ná afmelden, zelfde kolom).
  app.raw((project) => _addWedstrijdScoreSection(project));
  app.raw((project) => _addWedstrijdGuestInviteSection(project));
  app.raw((project) => _addWedstrijdActionsFab(project));
  // De inline coach-secties zijn vervangen door de FAB-dialoog: van de pagina af.
  app.raw((project) => _removeInlineCoachSections(project));
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
  // Fix layout: outer Column was mainAxisSize.min so the ListView collapsed to zero height.
  app.raw((project) => _fixWisselAanvraagPageLayout(project));

  // ── Snelmenu: + FAB op DashboardPage met snelle acties (bottom sheet) ───────
  _buildQuickActionsSheet(app);
  app.raw((project) => _addDashboardQuickActionsFab(project));
  _buildMatchActionsSheet(app);
  app.raw((project) => _buildMatchActionsDialogBody(project));

  // ─── Banner (marketing) feature ────────────────────────────────────────────
  // Banner struct for the GetBanners API response.
  try {
    app.struct('Banner', {
      'id':       string,
      'imageUrl': string,
      'linkUrl':  string,
      'position': string,
    });
  } catch (_) {}
  app.raw((project) => _addBannerEndpoint(project));
  app.raw((project) => _addBannerToWedstrijdenPage(project));
  app.raw((project) => _addBannerToBardienPage(project));
  app.raw((project) => _addBannerToRijschemaPage(project));

  // ── Nieuwsfeed feature ────────────────────────────────────────────────────
  try {
    app.struct('NewsItem', {
      'id':            string,
      'title':         string,
      'subtitle':      string,
      'body':          string,
      'imageUrl':      string,
      'category':      string,
      'categoryLabel': string,
      'publishedAt':   string,
      'daysOld':       int_,
    });
  } catch (_) {}
  app.raw((project) => _addNewsEndpoint(project));
  _buildNewsPage(app);
  app.raw((project) {
    _ensureNewsPageNewsItemsState(project);
    _wireNewsPageLoad(project);
    _buildNewsListBody(project);
  });

  // ── Dashboard page ─────────────────────────────────────────────────────────────
  // Shows upcoming wedstrijden + bardiensten on one screen; home icon in NavBar.
  _buildDashboardPage(app, ff.Structs.footMatch, ff.Structs.barDuty);
  // Detailpagina voor een training (af-/aanmelden met reden).
  _buildTrainingDetailPage(app);
  app.raw((project) {
    _addDashboardAppBar(project);
    _buildDashboardContent(project);
    // Custom action moet bestaan vóór _wireDashboardLoad die 'm vooraan de
    // on-load chain hangt.
    _ensureRefreshCurrentTeamAction(project);
    _wireDashboardLoad(project);
    _fixDashboardListViewShrinkWrap(project);
    // Add "Rijschema" section: shows matches where the user is assigned to drive.
    _addDashboardDriveSection(project);
    // Append GetDriveSchedule to the on-load chain (must run after _wireDashboardLoad
    // which rebuilds the chain from scratch each push).
    _wireDashboardDriveScheduleLoad(project);
    // Native trainingen-endpoint (testmode-proof) + sectie/on-load (ná _wireDashboardLoad).
    _addGetTrainingsEndpoint(project);
    _addDashboardTrainingsSection(project);
    // Status-iconen (aangemeld/afgemeld) onder de trainingskaart.
    _addTrainingCardStatusIcons(project);
    // TrainingDetailPage-inhoud + kaart aantikbaar maken.
    _wireTrainingDetailPage(project);
    _wireTrainingCardNavigation(project);
    // Tap on a dashboard card → open the corresponding detail page.
    _wireDashboardCardNavigation(project);
    // Team-switcher bovenaan (alleen bij >1 team); switcht wedstrijden + trainingen.
    _addDashboardTeamSwitcher(project);
    _addDashboardGuestInvitations(project);
    _removeClubLogoDebug(project);
  });

  // StatusBadge-component ruimer maken (pill i.p.v. krappe padding).
  app.raw((project) => _makeStatusBadgeRoomier(project));

  // ── Dashboard empty states ────────────────────────────────────────────────────
  app.raw((project) => _addDashboardEmptyPlaceholders(project));

  // ── WedstrijdenPage / BardienPage / RijschemaPage: "Niets gepland" ───────────
  app.raw((project) => _addPageListEmptyPlaceholders(project));

  // Apply club primary color to all AppBar backgrounds; set back button + title to white.
  // Runs last so the AppBar nodes already exist from all the preceding wiring steps.
  app.raw((project) => _applyBrandingToAllAppBars(project));
  // Clublogo rechtsboven (sticky) in elke AppBar — ná de AppBar-branding.
  app.raw((project) => _addClubLogoToAppBars(project));

  // Apply club primary color to all buttons: fill color + white text + generous padding.
  app.raw((project) => _applyBrandingToAllButtons(project));

  // Force NavBar items LAST — after all other raw mutations that touch the scaffold.
  app.raw((project) => _forceDashboardNavBarItem(project));
  app.raw((project) => _forceChatsNavBarItem(project));

  // Hamburger menu / Drawer on every main NavBar page. Runs after all pages
  // are wired so Navigate-targets exist when the drawer tiles are built.
  app.raw((project) => _wireAppDrawerOnAllMainPages(project));

  // Rood unread-bolletje overlay boven het Chats-icoon. Wrapt elke hoofdpage's
  // body in een Stack zodat de ChatBadgeOverlay custom widget over de NavBar
  // heen positioneert.
  app.raw((project) => _wireChatBadgeOverlayOnAllMainPages(project));

  // WatchUnreadChatCount op alle hoofdpagina's zodat de Firestore-listener
  // start ongeacht waar de gebruiker cold-start of na user-switch arriveert.
  app.raw((project) {
    const pages = [
      'DashboardPage',
      'WedstrijdenPage',
      'BardienPage',
      'RijschemaPage',
      'ChatsPage',
      'ProfielPage',
    ];
    for (final p in pages) {
      _wireWatchUnreadOnPageLoad(project, p);
    }
  });

  // ProfielPage: ververs de gekoppelde teams automatisch bij het openen (zonder
  // opnieuw inloggen). Ná de WatchUnread-wiring zodat deze niet overschreven wordt.
  app.raw((project) => _wireProfielRefreshOnLoad(project));
  // Ook op het dashboard: pikt team-wijzigingen (bv. extra team als coach)
  // automatisch op zonder opnieuw inloggen, en zet de switcher-vlag goed.
  app.raw((project) => _wireProfielRefreshOnLoad(project, 'DashboardPage'));
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
      headers: ['Authorization: Bearer [token]'],
      groupIdentifier: group.identifier.deepCopy(),
      responseDataStructParam: getMatchesEp?.responseDataStructParam.deepCopy(),
    ));
  } else {
    existingUpcomingEp.url = '/matches?upcoming=1&per_page=50&team_id=[teamId]';
    // Ensure correct auth header (fix: was [bearerToken], must be [token]).
    existingUpcomingEp.headers.clear();
    existingUpcomingEp.headers.add('Authorization: Bearer [token]');
    if (!existingUpcomingEp.variables.any((v) => v.identifier.name == 'token')) {
      existingUpcomingEp.variables.add(FFApiValue(
        identifier: FFIdentifier(name: 'token', key: generateRandomAlphaNumericString()),
        type: FFBaseDataType.String,
      ));
    }
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

  final userNameId       = _findAppStateFieldId(project, 'userName');
  final userEmailId      = _findAppStateFieldId(project, 'userEmail');
  final clubNameId       = _findAppStateFieldId(project, 'clubName');
  final currentTeamNameId = _findAppStateFieldId(project, 'currentTeamName');
  final secondaryColorId  = _findAppStateFieldId(project, 'secondaryColor');

  FFNode _boundText(String name, FFIdentifier? fieldId, String fallback, UITextStyle style) {
    final node = UI.text(fallback, name: name, style: style);
    if (fieldId != null) {
      node.props.text.textValue = FFStringValue(variable: varFromAppState(fieldId.deepCopy()));
    }
    return node;
  }

  final existingCards = findDescendants(wc.node, (n) => n.name == 'ProfielInfoCard');

  final relatiecodeId      = _findAppStateFieldId(project, 'relatiecode');
  final profilePhotoUrlId  = _findAppStateFieldId(project, 'profilePhotoUrl');

  if (existingCards.isNotEmpty) {
    // Card already exists — update secondary color and inject missing fields.
    if (secondaryColorId != null) {
      _setContainerColor(existingCards.first,
          colorFromStringVar(varFromAppState(secondaryColorId.deepCopy())));
    }
    final infoContent = findDescendants(existingCards.first, (n) => n.name == 'ProfielInfoContent').firstOrNull;
    if (infoContent != null) {
      if (currentTeamNameId != null &&
          findDescendants(infoContent, (n) => n.name == 'ProfielTeam').isEmpty) {
        infoContent.children.add(_boundText('ProfielTeam', currentTeamNameId, 'Elftal', UITextStyle.bodyMedium));
      }
      if (relatiecodeId != null &&
          findDescendants(infoContent, (n) => n.name == 'ProfielRelatiecode').isEmpty) {
        infoContent.children.add(_boundText('ProfielRelatiecode', relatiecodeId, 'Lidnummer', UITextStyle.bodySmall));
      }
    }
    _setupProfielAvatar(project, wc, profilePhotoUrlId);
    return;
  }

  final infoCard = UI.container(
    name: 'ProfielInfoCard',
    padding: UIEdgeInsets.all(16),
    width: double.infinity,
    child: UI.column(
      name: 'ProfielInfoContent',
      spacing: 8,
      children: [
        _boundText('ProfielNaam',        userNameId,       'Naam',       UITextStyle.titleLarge),
        _boundText('ProfielEmail',       userEmailId,      'E-mail',     UITextStyle.bodyMedium),
        _boundText('ProfielClub',        clubNameId,       'Club',       UITextStyle.bodyMedium),
        _boundText('ProfielTeam',        currentTeamNameId,'Elftal',     UITextStyle.bodyMedium),
        _boundText('ProfielRelatiecode', relatiecodeId,    'Lidnummer',  UITextStyle.bodySmall),
      ],
    ),
  );
  if (secondaryColorId != null) {
    _setContainerColor(infoCard,
        colorFromStringVar(varFromAppState(secondaryColorId.deepCopy())));
  }

  _setupProfielAvatar(project, wc, profilePhotoUrlId);

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

// ─── ProfielPage: avatar met profielfoto + upload ────────────────────────────
//
// Voegt een CircularImage-widget toe aan de Avatar-container op de ProfielPage:
//   - Zichtbaar wanneer profilePhotoUrl niet leeg is
//   - Verbergt de initialen-tekst wanneer foto beschikbaar is
//   - Tap op de avatar opent de galerij en uploadt via UpdateProfilePhoto
// Idempotent: slaat over als ProfielPhoto al bestaat.
void _setupProfielAvatar(FFProject project, FFWidgetClass wc, FFIdentifier? profilePhotoUrlId) {
  if (profilePhotoUrlId == null) return;

  final avatarContainer = findDescendants(wc.node, (n) => n.name == 'Avatar').firstOrNull;
  if (avatarContainer == null) return;

  // Wire tap action ALTIJD opnieuw (los van idempotency body-setup).
  // Eerdere pushes hebben hier de oude uploadData+ApiCall chain gezet die
  // niet werkt; deze rewire vervangt hem door de werkende custom action.
  _wireAvatarUploadTap(project, avatarContainer);

  // Idempotent: skip body-setup if photo image already added.
  if (findDescendants(avatarContainer, (n) => n.name == 'ProfielPhoto').isNotEmpty) return;

  final scaffoldKey = wc.node.key;
  final photoUrlVar = varFromAppState(profilePhotoUrlId.deepCopy());

  // Visibility: photo visible when URL is not empty
  final hasPhotoVar = conditionVar(
    varFromAppState(profilePhotoUrlId.deepCopy()),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;

  final noPhotoVar = conditionVar(
    varFromAppState(profilePhotoUrlId.deepCopy()),
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;

  // Build a CircleImage node with dynamic URL from AppState.
  // UI.avatar only accepts static strings, so we build the FFNode directly.
  final photoImage = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.CircleImage,
    name: 'ProfielPhoto',
    props: FFWidgetProperties(
      image: FFImage(
        type:       FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
        pathValue:  FFStringValue(variable: photoUrlVar.deepCopy()),
        fit:        FFBoxFit.FF_BOX_FIT_COVER,
        cached:     true,
        dimensions: FFDimensions(
          width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 80.0)),
          height: FFDim(pixelsValue: FFDoubleValue(inputValue: 80.0)),
        ),
      ),
    ),
  );
  setConditionalVisibility(photoImage, variable: hasPhotoVar);

  // Hide the initials text when a photo is available
  final initialsText = findDescendants(avatarContainer, (n) => n.name == 'Avatar Text').firstOrNull;
  if (initialsText != null) {
    setConditionalVisibility(initialsText, variable: noPhotoVar);
  }

  avatarContainer.children.insert(0, photoImage);

  // Resize the avatar to make it more prominent
  final c = avatarContainer.props.container.deepCopy();
  final dims = c.hasDimensions() ? c.dimensions.deepCopy() : FFDimensions();
  dims.width  = FFDim(pixelsValue: FFDoubleValue(inputValue: 80.0));
  dims.height = FFDim(pixelsValue: FFDoubleValue(inputValue: 80.0));
  c.dimensions = dims;
  avatarContainer.props.container = c;
}

// Wire de tap-actie van de avatar naar de UploadProfilePhoto custom action.
// Wordt op ELKE push uitgevoerd zodat eventuele oude (kapotte) tap chains
// vervangen worden door de werkende custom action.
void _wireAvatarUploadTap(FFProject project, FFNode avatarContainer) {
  _ensureUploadProfilePhotoCustomAction(project);

  final customAction = findCustomAction(project, name: 'UploadProfilePhoto');
  if (customAction == null) return;

  avatarContainer.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  final tapChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: customAction.identifier.deepCopy(),
      ),
    ),
  );
  Actions.addTriggerChain(avatarContainer, FFActionTriggerType.ON_TAP, tapChain);
}

// Registreert / update een custom Dart action 'UploadProfilePhoto' die:
//  1. de galerij opent en de gebruiker een foto laat kiezen
//  2. de foto als multipart/form-data naar /api/v1/profile/photo POST
//  3. de geretourneerde URL in FFAppState().profilePhotoUrl zet
void _ensureUploadProfilePhotoCustomAction(FFProject project) {
  const _code = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

Future<bool> uploadProfilePhoto(BuildContext context) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 85,
    );
    if (picked == null) return false;

    final token = FFAppState().authToken;
    if (token.isEmpty) {
      messenger.showSnackBar(const SnackBar(content: Text('Niet ingelogd.')));
      return false;
    }

    final uri = Uri.parse('https://voetbalplanner.nubix.nl/api/v1/profile/photo');
    // Web-safe: lees bytes uit XFile in plaats van fromPath, want XFile.path
    // is op web een blob-URL die het filesystem niet kan lezen.
    final bytes = await picked.readAsBytes();
    final request = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer $token'
      ..headers['Accept']        = 'application/json'
      // Laravel route is PATCH — emuleer via method spoofing want multipart
      // werkt niet betrouwbaar over PATCH.
      ..fields['_method'] = 'PATCH'
      ..files.add(http.MultipartFile.fromBytes(
        'photo',
        bytes,
        filename: picked.name.isNotEmpty ? picked.name : 'profile_photo.jpg',
      ));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);

    if (response.statusCode != 200) {
      messenger.showSnackBar(SnackBar(
        content: Text('Uploaden mislukt (HTTP ${response.statusCode}).'),
      ));
      return false;
    }

    final body = jsonDecode(response.body) as Map<String, dynamic>?;
    final url = (body?['data']?['profile_photo_url'] as String?) ?? '';
    if (url.isEmpty) {
      messenger.showSnackBar(const SnackBar(
        content: Text('Onverwacht antwoord van server.'),
      ));
      return false;
    }

    FFAppState().update(() {
      FFAppState().profilePhotoUrl = url;
    });

    messenger.showSnackBar(const SnackBar(content: Text('Profielfoto bijgewerkt!')));
    return true;
  } catch (e) {
    messenger.showSnackBar(SnackBar(
      content: Text('Fout bij uploaden: $e'),
    ));
    return false;
  }
}
''';

  if (findCustomAction(project, name: 'UploadProfilePhoto') == null) {
    addCustomAction(
      project,
      name: 'UploadProfilePhoto',
      description: 'Laat de gebruiker een foto kiezen en upload die als profielfoto.',
      arguments: [],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      includeContext: true,
      code: _code,
    );
  } else {
    updateCustomAction(
      project,
      name: 'UploadProfilePhoto',
      code: _code,
      arguments: [],
      includeContext: true,
    );
  }

  // Zorg dat de gebruikte pub-dependencies aanwezig zijn.
  try { addPubDependency(project, name: 'image_picker', version: '^1.0.0'); } catch (_) {}
  try { addPubDependency(project, name: 'http',         version: '^1.2.0'); } catch (_) {}
}

// AppState field `sharedBarDuties` = List<DataStruct<BarDuty>>. Wordt door
// _wireBardienLoad + de Aanmelden-knop bijgewerkt zodat BardienPage's
// ListView altijd de actuele lijst toont.
void _ensureSharedBarDutiesAppStateField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'sharedBarDuties',
  )) return;
  final barDutyStruct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'BarDuty', orElse: () => null);
  if (barDutyStruct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(
      name: 'sharedBarDuties',
      key: generateRandomAlphaNumericString(),
    ),
    dataType: dataStructType(barDutyStruct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// AppState field `availableTeams` = List<DataStruct<TeamOption>>. Gevuld uit de
// login-response user['teams']. Gebruikt voor de teamkeuze in de teamchat
// (multi-team, bv. een ouder met kinderen in meerdere teams).
void _ensureAvailableTeamsAppStateField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'availableTeams',
  )) return;
  final teamStruct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'TeamOption', orElse: () => null);
  if (teamStruct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(
      name: 'availableTeams',
      key: generateRandomAlphaNumericString(),
    ),
    dataType: dataStructType(teamStruct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// Rebind BardienPage's ListView_tu54znnh van page-state.duties naar
// AppState.sharedBarDuties. Idempotent — checkt op al-rebound staat.
void _rebindBardienListViewToAppState(FFProject project) {
  final sharedId = _findAppStateFieldId(project, 'sharedBarDuties');
  if (sharedId == null) return;
  final wc = findPage(project, name: 'BardienPage');
  if (wc == null) return;
  final listView = findByKey(wc.node, 'ListView_tu54znnh');
  if (listView == null) return;
  if (!listView.hasGeneratorVariable()) return;

  // varFromAppState gebruikt FFVariableSource.LOCAL_STATE met
  // stateVariableType=APP_STATE; voor ListView-generators eist FF expliciet
  // een nodeKeyRef naar het bezittende scaffold ook bij AppState-bronnen.
  final newSourceVar = varFromAppState(sharedId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final gen = listView.generatorVariable.deepCopy();
  gen.variable = newSourceVar;
  listView.generatorVariable = gen;
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
      // Bardiensten zijn op persoon: geen teamId → alle teams van de gebruiker.
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
      },
      outputVariableName: 'dutiesLoad',
      nodeKey: 'Scaffold_ljui3hun',
      onSuccess: (ctx) {
        // Schrijf óók naar AppState.sharedBarDuties — de ListView is gebonden
        // aan AppState zodat updates van buiten (zoals SelfAssignBarDuty
        // success) automatisch verschijnen zonder dat de page-state update
        // hoeft te vuren.
        final actions = <FFAction>[
          Actions.updatePageState(
            project,
            widgetClassName: 'BardienPage',
            updates: [
              StateFieldUpdate.setFromVariable('duties', ctx.responseVar),
              StateFieldUpdate.set('isLoading', 'false'),
            ],
          ),
        ];
        if (_findAppStateFieldId(project, 'sharedBarDuties') != null) {
          actions.add(Actions.updateAppState(
            project,
            updates: [
              StateFieldUpdate.setFromVariable('sharedBarDuties', ctx.responseVar),
            ],
          ));
        }
        return Actions.chain(actions);
      },
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

// Adds AppBars to chat pages. ChatDetailPage uses a dynamic title from its
// 'title' param. ChatsPage is a NavBar tab — static title, no back button.
void _addChatPageAppBars(FFProject project) {
  _ensureChatAppBar(project, 'ChatDetailPage', 'title');
  _resetChatsPageAppBar(project);
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

  final titleNode = UI.text('', name: 'AppBar Title', style: UITextStyle.titleLarge);
  if (titleParamId != null) {
    titleNode.props.text.textValue = FFStringValue(variable: varFromPageParam(titleParamId));
  }

  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);

  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Adds an AppBar with a static string title and back button.
// Used for pages without a title param (e.g. CreateGroupPage).
void _ensureChatAppBarStatic(FFProject project, String pageName, String title) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  if (getPropertyChild(wc.node, 'appBar') != null) return;

  final titleNode = UI.text(title, name: 'AppBar Title', style: UITextStyle.titleLarge);
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Force-resets the ChatsPage AppBar with static title "Chats" (no back button).
// Force-reset ensures the title is always present even if an untitled AppBar
// was registered in a prior push (idempotent guard would have skipped it).
void _resetChatsPageAppBar(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');
  final titleNode = UI.text('Chats', name: 'ChatsAppBarTitle', style: UITextStyle.titleLarge);
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: false);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Force-resets the GroupChatPage AppBar with title bound to the groupName param.
// Adds a delete icon (trash) in the AppBar actions — visible to all, but the
// DeleteChatGroup custom action only executes the delete if the current user
// is the group creator (createdBy == userName check inside the action).
void _resetGroupChatPageAppBar(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;
  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');

  FFIdentifier? titleParamId;
  FFIdentifier? groupIdParamId;
  for (final param in wc.params.values) {
    if (!param.hasIdentifier()) continue;
    if (param.identifier.name == 'groupName') titleParamId  = param.identifier.deepCopy();
    if (param.identifier.name == 'groupId')   groupIdParamId = param.identifier.deepCopy();
  }

  final titleNode = UI.text('', name: 'AppBar Title', style: UITextStyle.titleLarge);
  if (titleParamId != null) {
    titleNode.props.text.textValue = FFStringValue(variable: varFromPageParam(titleParamId));
  }

  // Tapping the title navigates to GroupMembersPage.
  if (groupIdParamId != null) {
    Actions.addTriggerChain(
      titleNode,
      FFActionTriggerType.ON_TAP,
      FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.navigate(
          project,
          pageName: 'GroupMembersPage',
          params: {
            'groupId':   VariableParamValue(varFromPageParam(groupIdParamId)),
            'groupName': VariableParamValue(varFromPageParam(titleParamId ?? groupIdParamId)),
          },
        ),
      ),
    );
  }

  // Build delete button action chain:
  // ConfirmDialog → SetState(pendingGroupName) → DeleteChatGroup → Navigate(replaceRoute)
  List<FFNode> actions = [];
  final pendingGroupNameId = _findAppStateFieldId(project, 'pendingGroupName');
  final deleteAction = findCustomAction(project, name: 'DeleteChatGroup');
  if (pendingGroupNameId != null && deleteAction != null && groupIdParamId != null) {
    FFValue _lit(String s) =>
        FFValue(inputValue: FFParameterValue(serializedValue: s));

    final deleteTapChain = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        alertDialog: FFAlertDialogAction(
          confirmDialog: FFConfirmDialogAction(
            title:       _lit('Groep verwijderen'),
            message:     _lit('Weet u het zeker?'),
            confirmText: _lit('Verwijderen'),
            dismissText: _lit('Annuleren'),
          ),
        ),
      ),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          localStateUpdate: FFLocalStateUpdate(
            updates: [
              FFLocalStateFieldUpdate(
                fieldIdentifier: pendingGroupNameId.deepCopy(),
                setValue: FFValue(variable: varFromPageParam(groupIdParamId)),
              ),
            ],
            stateVariableType: FFStateVariableType.APP_STATE,
          ),
        ),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: FFAction(
            key: generateRandomAlphaNumericString(),
            customAction: FFCustomActionCall(
              customActionIdentifier: deleteAction.identifier.deepCopy(),
            ),
          ),
          followUpAction: FFActionNode(
            key: generateRandomAlphaNumericString(),
            action: Actions.navigate(
              project,
              pageName: 'ChatsPage',
              params: {},
              replaceRoute: true,
            ),
          ),
        ),
      ),
    );
    final deleteBtn = UI.iconButton(
      'delete',
      color: UIColor.secondaryBackground,
      name: 'DeleteGroupButton',
    );
    Actions.onTapChain(deleteBtn, deleteTapChain);
    actions = [deleteBtn];
  }

  final appBarNode = UI.appBar(
    titleWidget: titleNode,
    showBackButton: true,
    actions: actions.isEmpty ? null : actions,
  );
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Sets alwaysShowNavBar = true on [pageName]'s scaffold so the bottom NavBar
// remains visible when the user navigates into a sub-page.
void _setAlwaysShowNavBar(FFProject project, String pageName) {
  final page = findPage(project, name: pageName);
  if (page == null) return;
  final scaffCopy = page.node.props.scaffold.deepCopy();
  scaffCopy.ensureNavBarItem().alwaysShowNavBar = true;
  page.node.props.scaffold = scaffCopy;
}

// Force-resets TeamChatPage's AppBar every push.
// TeamChatPage is now a sub-page (NavBar = ChatsPage), so it needs a back button.
// Title is bound to the 'teamName' page param (passed from ChatsPage; default 'Teamchat').
void _resetTeamChatAppBar(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');

  // Bind title to the currentTeamName AppState field — TeamChatPage is a NavBar
  // tab so the teamName page param is never passed by NavBar navigation.
  final teamNameAppStateId = _findAppStateFieldId(project, 'currentTeamName');

  final titleNode = UI.text('Teamchat', name: 'TeamChatTitle', style: UITextStyle.titleLarge);
  if (teamNameAppStateId != null) {
    titleNode.props.text.textValue =
        FFStringValue(variable: varFromAppState(teamNameAppStateId.deepCopy()));
  }

  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Enables the global NavBar and registers the 6 main pages in canonical order.
void _setupNavBar(FFProject project) {
  setNavBarEnabled(project, enabled: true);
  // Labels uit: badge zit nu als echte rode overlay via ChatBadgeOverlay,
  // dus tekst-labels onder iconen voegen niets meer toe en geven ruis.
  project.ensureNavBar()
    ..showSelectedLabels = false
    ..showUnselectedLabels = false;
  // Remove stale TeamChatPage if still present from the old layout.
  try { removeNavBarPage(project, pageName: 'TeamChatPage'); } catch (_) {}
  // Ensure all 6 canonical pages are in the NavBar (idempotent appends, skip if present).
  addNavBarPage(project, pageName: 'DashboardPage',  iconName: 'home');
  addNavBarPage(project, pageName: 'WedstrijdenPage', iconName: 'sports');
  addNavBarPage(project, pageName: 'RijschemaPage',   iconName: 'directions_car');
  addNavBarPage(project, pageName: 'BardienPage',     iconName: 'sports_bar');
  addNavBarPage(project, pageName: 'ChatsPage',       iconName: 'chat');
  addNavBarPage(project, pageName: 'ProfielPage',     iconName: 'person');
  // Fix ordering: DashboardPage must be first; ChatsPage before ProfielPage.
  final pages = listNavBarPages(project);
  final dashIdx    = pages.indexOf('DashboardPage');
  final chatsIdx   = pages.indexOf('ChatsPage');
  final profielIdx = pages.indexOf('ProfielPage');
  if (dashIdx > 0) {
    reorderNavBarPage(project, pageName: 'DashboardPage', newIndex: 0);
  }
  if (chatsIdx > profielIdx && chatsIdx >= 0 && profielIdx >= 0) {
    reorderNavBarPage(project, pageName: 'ChatsPage', newIndex: profielIdx);
  }
  // addNavBarPage is idempotent: when ChatsPage is already in pageKeyRefOrder
  // it returns early WITHOUT setting navBarItem.show = true. Force-set it here
  // so FlutterFlow's code generator always includes ChatsPage in NavBarPage.tabs.
  final chatPage = findPage(project, name: 'ChatsPage');
  if (chatPage != null) {
    final scaffCopy = chatPage.node.props.scaffold.deepCopy();
    final navBarItem = scaffCopy.ensureNavBarItem();
    navBarItem.show = true;
    navBarItem.navIcon = FFIcon(
      iconDataValue: FFIconDataValue(
        inputValue: FFIconData(name: 'chat', family: 'MaterialIcons'),
      ),
    );
    chatPage.node.props.scaffold = scaffCopy;
  }
}

// Last-resort force: sets navBarItem.show = true + chat icon on ChatsPage AFTER
// all other raw mutations (editPageOnLoad + wire functions) have run.
// Those mutations modify the scaffold and can overwrite navBarItem set earlier.
void _forceChatsNavBarItem(FFProject project) {
  final chatPage = findPage(project, name: 'ChatsPage');
  if (chatPage == null) return;
  final scaffCopy = chatPage.node.props.scaffold.deepCopy();
  final navBarItem = scaffCopy.ensureNavBarItem();
  navBarItem.show = true;
  navBarItem.navIcon = FFIcon(
    iconDataValue: FFIconDataValue(
      inputValue: FFIconData(name: 'chat', family: 'MaterialIcons'),
    ),
  );

  // Label is statisch "Chats" — de unread-indicator komt nu als rood
  // overlay-bolletje boven het icoon via _wireChatBadgeOverlayOnAllMainPages.
  navBarItem.label = FFText(
    textValue: FFStringValue(inputValue: 'Chats'),
  );

  chatPage.node.props.scaffold = scaffCopy;
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

// ─── Chat custom-action code strings ─────────────────────────────────────────
// Defined at top-level so they can be referenced from both:
//   • app.customAction(...) in buildEditFlow (DSL compile phase)
//   • _addChatInfrastructure (raw phase, for update/idempotency)

const _kSendMessageCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> sendMessage() async {
  final conversationId = FFAppState().currentConversationId;
  final text = FFAppState().pendingMessageText.trim();
  if (conversationId.isEmpty || text.isEmpty) return;

  final db = FirebaseFirestore.instance;

  // Write the message — primary operation.
  try {
    await db.collection('chatMessages').doc().set({
      'conversationId': conversationId,
      'text': text,
      'senderId': FFAppState().userEmail,
      'senderName': FFAppState().userName,
      'createdAt': FieldValue.serverTimestamp(),
      'isRead': false,
    });
  } catch (_) {
    return; // message write failed; don't clear the text field
  }

  // Update conversation metadata — best-effort, uses merge so doc doesn't need to exist.
  try {
    await db.collection('chatConversations').doc(conversationId).set({
      'lastMessage':   text,
      'lastMessageAt': FieldValue.serverTimestamp(),
      'unreadCount':   FieldValue.increment(1),
      'hasUnread':     true,
    }, SetOptions(merge: true));
  } catch (_) {}

  FFAppState().update(() {
    FFAppState().pendingMessageText = '';
  });
}
''';

const _kGetOrCreateConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> getOrCreateDirectConversation() async {
  // Beide kanten van de chat moeten DEZELFDE convId computeren, ongeacht of
  // ze inloggen via lidnummer (sportlink-lid) of email (admin zonder relatie).
  // Strategie: "best available ID" = externalId-if-nonempty-else-email, voor beide.
  final myRelatiecode = FFAppState().relatiecode;
  final myEmail       = FFAppState().userEmail;
  final myId          = myRelatiecode.isNotEmpty ? myRelatiecode : myEmail;

  final otherExternalId = FFAppState().pendingDirectUserId;    // member.externalId
  final otherEmail      = FFAppState().pendingDirectUserEmail; // member.email fallback
  final otherId         = otherExternalId.isNotEmpty ? otherExternalId : otherEmail;
  final otherName       = FFAppState().pendingDirectUserName;
  final teamId          = FFAppState().currentTeamId;

  if (myId.isEmpty || otherId.isEmpty || teamId.isEmpty) return;

  final ids = [myId, otherId]..sort();
  final convId = '${teamId}_${ids[0]}_${ids[1]}';

  // participantIds: ALTIJD emails (WatchUnreadChatCount filtert op
  // participantIds arrayContains userEmail, en unreadByUser keys zijn emails).
  // ConvId kan via externalId/lidnummer zijn, maar participantIds moet emails
  // hebben anders krijgt geen enkele recipient z'n badge update.
  final participantEmails = <String>{};
  if (myEmail.isNotEmpty) participantEmails.add(myEmail);
  if (otherEmail.isNotEmpty) participantEmails.add(otherEmail);

  final db = FirebaseFirestore.instance;
  final docRef = db.collection('chatConversations').doc(convId);
  final doc = await docRef.get();

  if (!doc.exists) {
    await docRef.set({
      'conversationId': convId,
      'type': 'direct',
      'teamId': teamId,
      'title': otherName,
      'participantIds': participantEmails.toList(),
      'lastMessage': '',
      'lastMessageAt': FieldValue.serverTimestamp(),
      'createdAt': FieldValue.serverTimestamp(),
    });
  } else if (participantEmails.isNotEmpty) {
    // Bestaande oude docs hadden mogelijk lidnummers in participantIds —
    // arrayUnion voegt emails toe zonder bestaande entries weg te halen.
    await docRef.set({
      'participantIds': FieldValue.arrayUnion(participantEmails.toList()),
    }, SetOptions(merge: true));
  }

  FFAppState().update(() {
    FFAppState().currentConversationId = convId;
  });
}
''';

const _kInitTeamConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> initializeTeamConversation() async {
  final teamId = FFAppState().currentTeamId;
  if (teamId.isEmpty) return;

  final convId = 'team_$teamId';
  final db = FirebaseFirestore.instance;
  final docRef = db.collection('chatConversations').doc(convId);
  final doc = await docRef.get();

  if (!doc.exists) {
    await docRef.set({
      'conversationId': convId,
      'type': 'team',
      'teamId': teamId,
      'title': FFAppState().currentTeamName,
      'participantIds': <String>[],
      'lastMessage': '',
      'lastMessageAt': FieldValue.serverTimestamp(),
      'createdAt': FieldValue.serverTimestamp(),
    });
  }

  // Always add the current user to participantIds so the unread-badge stream
  // (which queries by arrayContains: userEmail) sees this conversation.
  final myEmail = FFAppState().userEmail;
  if (myEmail.isNotEmpty) {
    await docRef.set({
      'participantIds': FieldValue.arrayUnion([myEmail]),
    }, SetOptions(merge: true));
  }

  FFAppState().update(() {
    FFAppState().currentConversationId = convId;
    // Fallback: zorg dat de teamchat-keuze op ChatsPage tenminste het huidige
    // team toont, ook als availableTeams (nog) niet via de login is gevuld
    // (bv. een sessie van vóór de multi-team-update, of een backend zonder
    // teams[]). Alleen vullen als de lijst leeg is — anders niet overschrijven.
    if (FFAppState().availableTeams.isEmpty) {
      final fb = TeamOptionStruct.maybeFromMap(
          {'id': teamId, 'name': FFAppState().currentTeamName});
      if (fb != null) FFAppState().availableTeams = [fb];
    }
  });
}
''';

const _kGetOrCreateStaffGroupConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:http/http.dart' as http;

Future<void> getOrCreateStaffGroupConversation() async {
  final staffGroupId   = FFAppState().pendingStaffGroupId;
  final staffGroupName = FFAppState().pendingStaffGroupName;
  if (staffGroupId.isEmpty) return;

  final conversationId = 'staffgroup_$staffGroupId';
  final ref = FirebaseFirestore.instance
      .collection('chatConversations')
      .doc(conversationId);

  final snap = await ref.get();
  if (!snap.exists) {
    await ref.set({
      'conversationId': conversationId,
      'title': staffGroupName.isEmpty ? 'Staffgroep' : staffGroupName,
      'type': 'staffgroup',
      'participantIds': <String>[],
      'lastMessage': '',
      'updatedAt': FieldValue.serverTimestamp(),
    });
  }

  // Add the current user to participantIds so the unread-badge listener picks
  // this conversation up.
  final myEmail = FFAppState().userEmail;
  if (myEmail.isNotEmpty) {
    await ref.set({
      'participantIds': FieldValue.arrayUnion([myEmail]),
    }, SetOptions(merge: true));
  }

  // Fetch volledige ledenlijst van Laravel en seed participantIds met alle
  // staffgroup-leden (ongeacht of ze de chat al hebben geopend). Hiermee
  // toont TeamMembersPage (via FilterChatMembersByConv) de complete groep.
  try {
    final token = FFAppState().authToken;
    if (token.isNotEmpty) {
      final resp = await http.get(
        Uri.parse('https://voetbalplanner.nubix.nl/api/v1/staff-groups/$staffGroupId/members-full'),
        headers: {'Authorization': 'Bearer $token'},
      );
      if (resp.statusCode == 200) {
        final decoded = jsonDecode(resp.body);
        if (decoded is List) {
          final emails = <String>[];
          for (final m in decoded) {
            if (m is Map) {
              final e = m['email'];
              if (e is String && e.isNotEmpty) emails.add(e);
            }
          }
          if (emails.isNotEmpty) {
            await ref.set({
              'participantIds': FieldValue.arrayUnion(emails),
            }, SetOptions(merge: true));
          }
        }
      }
    }
  } catch (_) {}

  FFAppState().update(() {
    FFAppState().currentConversationId = conversationId;
  });
}
''';

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

  // Badge flag: true when there are unread team chat messages (cleared on page open).
  _ensureAppStateField(project, 'hasUnreadTeamChat', FFBaseDataType.Boolean);

  // AppState fields for chat navigation & message sending.
  // scalar String args in custom-action calls fail the FF validator, so all
  // contextual data is staged via AppState before calling custom actions.
  _ensureAppStateField(project, 'currentConversationId', FFBaseDataType.String);
  _ensureAppStateField(project, 'pendingMessageText', FFBaseDataType.String);
  _ensureAppStateField(project, 'pendingDirectUserId', FFBaseDataType.String);
  _ensureAppStateField(project, 'pendingDirectUserName', FFBaseDataType.String);
  // Legacy field — kept to avoid breaking existing project data that may reference it.
  _ensureAppStateField(project, 'pendingGroupName', FFBaseDataType.String);
  // Staff group staging fields — set before calling GetOrCreateStaffGroupConversation.
  _ensureAppStateField(project, 'pendingStaffGroupId', FFBaseDataType.String);
  _ensureAppStateField(project, 'pendingStaffGroupName', FFBaseDataType.String);

  // Aggregated unread chat count across all chatConversations (live stream).
  // Updated by WatchUnreadChatCount; bound to the Chats nav-bar label.
  _ensureAppStateField(project, 'unreadChatCount', FFBaseDataType.Integer);

  // Deterministische conversation ID voor directe 1-op-1 chats. Wordt
  // gezet door ComputeDirectConvId net voor navigatie naar DirectChatPage.
  // Format: '<currentTeamId>_<sortedEmailA>_<sortedEmailB>'.
  _ensureAppStateField(project, 'directConvId', FFBaseDataType.String);

  // Email-fallback voor direct-chat target: nodig wanneer member.externalId
  // (lidnummer) leeg is — admins zijn niet altijd sportlink-leden. Wordt
  // gezet door chip-tap chain naast pendingDirectUserId (= externalId).
  _ensureAppStateField(project, 'pendingDirectUserEmail', FFBaseDataType.String);

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
  try {
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
  } catch (_) {
    // FCM topic subscription not supported on web — skip gracefully
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

  // ── SubscribeToChatTopics ────────────────────────────────────────────────────
  // Abonneert het toestel op de FCM-topics waarvoor de ingelogde gebruiker
  // chat-push moet ontvangen:
  //   - user_<sanitize(userEmail)>  → directe + staffgroep-berichten
  //   - team_<currentTeamId>        → teamchat
  // De Cloud Function (firebase-chat-functions/index.js) pusht naar exact deze
  // topics. De sanitize() hieronder MOET identiek zijn aan die in de CF.
  // Geen argumenten: leest userEmail + currentTeamId uit FFAppState (vermijdt de
  // FF-validator-issues met scalar String custom-action args).
  const _subscribeChatTopicsCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:async';
import 'package:firebase_messaging/firebase_messaging.dart';

// Houd identiek aan sanitize() in firebase-chat-functions/index.js zodat client
// en Cloud Function exact dezelfde topicnaam produceren.
String _sanitizeTopicEmail(String email) =>
    email.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '_');

Future<void> subscribeToChatTopics() async {
  // Fire-and-forget: deze action wordt aangeroepen ín page-load chains
  // (ChatsPage, WedstrijdenPage). NOOIT blokkeren op de notificatie-permissie-
  // prompt of trage FCM-calls — anders draaien de daaropvolgende load-acties
  // (gesprekken laden, leden laden) niet meer en blijft de pagina leeg.
  // Daarom kicken we het echte werk los en keert deze functie meteen terug.
  unawaited(_doSubscribeChatTopics());
}

Future<void> _doSubscribeChatTopics() async {
  final email = FFAppState().userEmail;
  final teamId = FFAppState().currentTeamId;
  try {
    final messaging = FirebaseMessaging.instance;
    final settings = await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    if (settings.authorizationStatus != AuthorizationStatus.authorized &&
        settings.authorizationStatus != AuthorizationStatus.provisional) {
      return;
    }
    if (email.isNotEmpty) {
      await messaging.subscribeToTopic('user_${_sanitizeTopicEmail(email)}');
    }
    if (teamId.isNotEmpty) {
      await messaging.subscribeToTopic('team_$teamId');
    }
    // Globaal topic voor clubbrede push (bv. nieuwsberichten naar alle gebruikers).
    await messaging.subscribeToTopic('all_users');
  } catch (_) {
    // FCM topic subscription wordt op web niet ondersteund — sla netjes over.
  }
}
''';
  if (findCustomAction(project, name: 'SubscribeToChatTopics') == null) {
    addCustomAction(
      project,
      name: 'SubscribeToChatTopics',
      description:
          'Abonneert het toestel op user_<email> + team_<teamId> FCM-topics voor chat push-notificaties.',
      arguments: [],
      code: _subscribeChatTopicsCode,
    );
  } else {
    updateCustomAction(project, name: 'SubscribeToChatTopics', code: _subscribeChatTopicsCode);
  }

  // ── SendMessage ──────────────────────────────────────────────────────────────
  // Writes a chatMessages document and updates chatConversations.lastMessage.
  // All context read from FFAppState() to avoid FF validator issues with String
  // custom-action args. Stage currentConversationId + pendingMessageText before
  // calling this action.
  updateCustomAction(project, name: 'SendMessage', code: _kSendMessageCode);

  // ── GetOrCreateDirectConversation ────────────────────────────────────────────
  // Finds or creates a chatConversations doc for a 1-on-1 conversation.
  // Reads pendingDirectUserId + pendingDirectUserName from FFAppState().
  // Stores the resulting conversationId in FFAppState().currentConversationId.
  updateCustomAction(project, name: 'GetOrCreateDirectConversation', code: _kGetOrCreateConvCode);

  // ── InitializeTeamConversation ───────────────────────────────────────────────
  // Ensures the team chatConversation document exists and stores its ID in
  // AppState.currentConversationId. Called on ChatsPage load.
  updateCustomAction(project, name: 'InitializeTeamConversation', code: _kInitTeamConvCode);

  // ── GetOrCreateStaffGroupConversation ────────────────────────────────────────
  // Finds or creates a chatConversations doc for a staff group conversation.
  // Reads pendingStaffGroupId + pendingStaffGroupName from FFAppState().
  // Stores the resulting conversationId in FFAppState().currentConversationId.
  if (findCustomAction(project, name: 'GetOrCreateStaffGroupConversation') == null) {
    addCustomAction(
      project,
      name: 'GetOrCreateStaffGroupConversation',
      description: 'Vindt of maakt een chatConversations document voor een staffgroep gesprek.',
      arguments: [],
      code: _kGetOrCreateStaffGroupConvCode,
    );
  } else {
    updateCustomAction(project, name: 'GetOrCreateStaffGroupConversation', code: _kGetOrCreateStaffGroupConvCode);
  }

  // ── InitializeGroupConversation ──────────────────────────────────────────
  // Voor reguliere chatgroepen (CreateChatGroup → chatGroups collectie).
  // Wordt aangeroepen op GroupChatPage onLoad. Reads pendingGroupId from
  // FFAppState (staged door de chip-tap die naar deze page navigeert), of
  // valt terug op de huidige FFAppState().currentConversationId als die al
  // op group_<id> formaat staat.
  // - Ensures chatConversations/group_<groupId> bestaat met teamId +
  //   participantIds zodat de WatchUnreadChatCount streams het oppikken.
  // - Sets FFAppState().currentConversationId = 'group_<groupId>'.
  const _kInitGroupConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> initializeGroupConversation(String? groupId) async {
  final id = (groupId ?? '').trim();
  if (id.isEmpty) return;

  final convId  = 'group_$id';
  final myEmail = FFAppState().userEmail;
  final fs      = FirebaseFirestore.instance;

  // Lookup chatGroups/{id} voor teamId + members.
  String? teamId;
  List<String> members = const [];
  try {
    final snap = await fs.collection('chatGroups').doc(id).get();
    if (snap.exists) {
      final data = snap.data() ?? {};
      teamId = data['teamId'] as String?;
      final raw = data['members'];
      if (raw is List) {
        members = raw.whereType<String>().toList();
      }
    }
  } catch (_) {}

  // Build payload — write een minimal complete doc; merge:true zorgt dat
  // bestaande velden (unreadCount, lastMessage, ...) intact blijven.
  final payload = <String, dynamic>{
    'conversationId': convId,
    'type':           'group',
    if (teamId != null && teamId.isNotEmpty) 'teamId': teamId,
  };
  if (members.isNotEmpty) {
    payload['participantIds'] = FieldValue.arrayUnion(members);
  } else if (myEmail.isNotEmpty) {
    payload['participantIds'] = FieldValue.arrayUnion([myEmail]);
  }

  try {
    await fs.collection('chatConversations').doc(convId)
        .set(payload, SetOptions(merge: true));
  } catch (_) {}

  FFAppState().update(() {
    FFAppState().currentConversationId = convId;
  });
}
''';
  if (findCustomAction(project, name: 'InitializeGroupConversation') == null) {
    addCustomAction(
      project,
      name: 'InitializeGroupConversation',
      description:
          'Initialiseert de chatConversations doc voor een reguliere chatgroep en zet currentConversationId.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'groupId'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: _kInitGroupConvCode,
    );
  } else {
    updateCustomAction(project, name: 'InitializeGroupConversation', code: _kInitGroupConvCode);
  }

  // ── ComputeDirectConvId ────────────────────────────────────────────────────
  // Berekent de deterministische conversation-id voor directe 1-op-1 chats
  // en schrijft 'm naar FFAppState().directConvId. Wordt aangeroepen vóór
  // navigatie naar DirectChatPage zodat de query/create de juiste id gebruikt.
  const _kComputeDirectConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

Future<void> computeDirectConvId(String? other) async {
  // Primair: lidnummer (relatiecode/external_id) — gegarandeerd uniek + stabiel.
  // Fallback: userEmail (voor admins/beheerders zonder member-relatie zodat
  // chat tussen 2 admins óók consistent gescoped is).
  // Beide deelnemers MOETEN dezelfde key-soort gebruiken anders mismatcht convId
  // → om dat te garanderen werkt deze fallback alleen als ÓÓK de other-partij
  // geen lidnummer heeft (in dat geval is de ontvangende kant ook admin).
  final relatiecode = FFAppState().relatiecode;
  final userEmail   = FFAppState().userEmail;
  final me = relatiecode.isNotEmpty ? relatiecode : userEmail;
  final team = FFAppState().currentTeamId;
  final o = (other ?? '').trim();
  if (me.isEmpty || o.isEmpty) {
    FFAppState().update(() {
      FFAppState().directConvId = '';
    });
    return;
  }
  final ids = [me, o]..sort();
  final cid = '${team}_${ids[0]}_${ids[1]}';
  FFAppState().update(() {
    FFAppState().directConvId = cid;
  });
}
''';
  if (findCustomAction(project, name: 'ComputeDirectConvId') == null) {
    addCustomAction(
      project,
      name: 'ComputeDirectConvId',
      description:
          'Berekent FFAppState().directConvId = "<teamId>_<sortedEmailA>_<sortedEmailB>" voor directe 1-op-1 chats.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'other'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: _kComputeDirectConvCode,
    );
  } else {
    updateCustomAction(project, name: 'ComputeDirectConvId', code: _kComputeDirectConvCode);
  }

  // ── BumpConversationUnread ──────────────────────────────────────────────────
  // Increments chatConversations.unreadCount + hasUnread for the conversation
  // pointed to by FFAppState().currentConversationId. Called after a message is
  // written to teamChats / directMessages / groupMessages so the unread badge
  // updates for other participants.
  const _kBumpUnreadCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> bumpConversationUnread() async {
  final convId  = FFAppState().currentConversationId;
  if (convId.isEmpty) return;

  final text    = FFAppState().pendingMessageText;
  final myEmail = FFAppState().userEmail;
  final teamId  = FFAppState().currentTeamId;

  final fs = FirebaseFirestore.instance;
  final docRef = fs.collection('chatConversations').doc(convId);

  // Verzamel participants — gebruikers wiens unread teller verhoogd moet worden.
  final participants = <String>{};
  try {
    final snap = await docRef.get();
    if (snap.exists) {
      final data = snap.data() ?? {};
      final raw = data['participantIds'];
      if (raw is List) participants.addAll(raw.whereType<String>());
    }
  } catch (_) {}
  if (myEmail.isNotEmpty) participants.add(myEmail);

  // Reguliere chatgroepen: ook chatGroups.members meenemen.
  if (convId.startsWith('group_')) {
    final groupId = convId.substring('group_'.length);
    try {
      final snap = await fs.collection('chatGroups').doc(groupId).get();
      if (snap.exists) {
        final data = snap.data() ?? {};
        final raw = data['members'];
        if (raw is List) participants.addAll(raw.whereType<String>());
      }
    } catch (_) {}
  }

  // Stap 1: schrijf basis-metadata met set+merge (creëert doc als nodig).
  final payload = <String, dynamic>{
    if (text.isNotEmpty) 'lastMessage': text,
    'lastMessageAt': FieldValue.serverTimestamp(),
    if (teamId.isNotEmpty) 'teamId': teamId,
    if (participants.isNotEmpty) 'participantIds': FieldValue.arrayUnion(participants.toList()),
  };
  await docRef.set(payload, SetOptions(merge: true));

  // Stap 2: atomically increment unreadByUser.<email> voor elke recipient via
  // FieldPath. FieldValue.increment werkt NIET binnen een nested map bij
  // set+merge — daarom is een aparte update() met FieldPath nodig.
  final updates = <Object, Object?>{};
  for (final p in participants) {
    if (p == myEmail || p.isEmpty) continue;
    updates[FieldPath(['unreadByUser', p])] = FieldValue.increment(1);
  }
  if (updates.isNotEmpty) {
    try {
      await docRef.update(updates);
    } catch (_) {
      // Doc bestaat maar unreadByUser ontbreekt → init met literale 1's.
      final init = <String, dynamic>{};
      for (final p in participants) {
        if (p == myEmail || p.isEmpty) continue;
        init[p] = 1;
      }
      try {
        await docRef.set({'unreadByUser': init}, SetOptions(merge: true));
      } catch (_) {}
    }
  }
}
''';
  if (findCustomAction(project, name: 'BumpConversationUnread') == null) {
    addCustomAction(
      project,
      name: 'BumpConversationUnread',
      description:
          'Verhoogt chatConversations.unreadCount + hasUnread voor de huidige conversatie (na elke verzonden chat in team/direct/group).',
      arguments: [],
      code: _kBumpUnreadCode,
    );
  } else {
    updateCustomAction(project, name: 'BumpConversationUnread', code: _kBumpUnreadCode);
  }

  // ── WatchUnreadChatCount ────────────────────────────────────────────────────
  // Sets up a Firestore stream listener on chatConversations that aggregates
  // the `unreadCount` field across all conversations the user participates in,
  // and writes the total into FFAppState().unreadChatCount.
  // Top-level subscription is re-used across calls; cancels any previous listener.
  const _kWatchUnreadCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:async';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:app_badge_plus/app_badge_plus.dart';

class _UnreadChatWatcher {
  static StreamSubscription? _byParticipantsSub;
  static StreamSubscription? _byTeamSub;
  static String _lastEmail = '';
  static String _lastTeamId = '';

  // Per stream cached counts; total is sum across streams (de-duped by docId).
  static final Map<String, int> _counts = {};

  static void _publish() {
    int total = 0;
    for (final v in _counts.values) total += v;
    FFAppState().update(() {
      FFAppState().unreadChatCount = total;
    });
    // App-icon badge bijwerken (iOS + ondersteunende Android-launchers).
    // 0 = badge weg. Web/niet-ondersteund faalt stil.
    try {
      AppBadgePlus.updateBadge(total);
    } catch (_) {}
  }

  static int _readCount(Map<String, dynamic> data, String myEmail) {
    // Per-gebruiker model: unreadByUser is een Map<String,int> waar de key
    // het email-adres van de ontvanger is. Lezen we alleen onze eigen entry.
    final raw = data['unreadByUser'];
    if (raw is Map) {
      final v = raw[myEmail];
      if (v is int) return v;
      if (v is num) return v.toInt();
    }
    return 0;
  }
}

Future<void> watchUnreadChatCount() async {
  final userEmail = FFAppState().userEmail;
  final teamId    = FFAppState().currentTeamId;

  // No user → reset and bail.
  if (userEmail.isEmpty) {
    await _UnreadChatWatcher._byParticipantsSub?.cancel();
    await _UnreadChatWatcher._byTeamSub?.cancel();
    _UnreadChatWatcher._byParticipantsSub = null;
    _UnreadChatWatcher._byTeamSub = null;
    _UnreadChatWatcher._counts.clear();
    FFAppState().update(() {
      FFAppState().unreadChatCount = 0;
    });
    return;
  }

  // Already watching for this user+team → keep existing listeners alive.
  if (_UnreadChatWatcher._lastEmail == userEmail
      && _UnreadChatWatcher._lastTeamId == teamId
      && (_UnreadChatWatcher._byParticipantsSub != null
          || _UnreadChatWatcher._byTeamSub != null)) {
    return;
  }

  // User or team changed → reset and rebuild subscriptions.
  await _UnreadChatWatcher._byParticipantsSub?.cancel();
  await _UnreadChatWatcher._byTeamSub?.cancel();
  _UnreadChatWatcher._byParticipantsSub = null;
  _UnreadChatWatcher._byTeamSub = null;
  _UnreadChatWatcher._counts.clear();
  _UnreadChatWatcher._lastEmail = userEmail;
  _UnreadChatWatcher._lastTeamId = teamId;

  final fs = FirebaseFirestore.instance;

  // Stream A — conversations I explicitly participate in (direct/group/staff
  // chats once the user opens them once and gets added to participantIds).
  _UnreadChatWatcher._byParticipantsSub = fs
      .collection('chatConversations')
      .where('participantIds', arrayContains: userEmail)
      .snapshots()
      .listen((snap) {
    int total = 0;
    for (final doc in snap.docs) {
      total += _UnreadChatWatcher._readCount(doc.data(), userEmail);
    }
    _UnreadChatWatcher._counts['participants'] = total;
    _UnreadChatWatcher._publish();
  }, onError: (Object _) {});

  // Stream B — team chats for my currentTeamId (catch-all in case the user
  // is not yet in participantIds for the team conversation).
  if (teamId.isNotEmpty) {
    _UnreadChatWatcher._byTeamSub = fs
        .collection('chatConversations')
        .where('teamId', isEqualTo: teamId)
        .snapshots()
        .listen((snap) {
      int total = 0;
      for (final doc in snap.docs) {
        // Skip conversations also covered by stream A to avoid double-counting.
        final participants = doc.data()['participantIds'];
        if (participants is List && participants.contains(userEmail)) continue;
        total += _UnreadChatWatcher._readCount(doc.data(), userEmail);
      }
      _UnreadChatWatcher._counts['team'] = total;
      _UnreadChatWatcher._publish();
    }, onError: (Object _) {});
  }
}
''';
  if (findCustomAction(project, name: 'WatchUnreadChatCount') == null) {
    addCustomAction(
      project,
      name: 'WatchUnreadChatCount',
      description:
          'Start een Firestore stream listener die het totale aantal ongelezen chatberichten van de gebruiker bijhoudt in FFAppState().unreadChatCount.',
      arguments: [],
      code: _kWatchUnreadCode,
    );
  } else {
    updateCustomAction(project, name: 'WatchUnreadChatCount', code: _kWatchUnreadCode);
  }
  // App-icon badge voor ongelezen chats (gebruikt in WatchUnreadChatCount._publish).
  try { addPubDependency(project, name: 'app_badge_plus', version: '^1.3.1'); } catch (_) {}
  try { updatePubDependency(project, name: 'app_badge_plus', newVersion: '^1.3.1'); } catch (_) {}

  // Legacy: kept so existing FlutterFlow project references don't break during migration.
  const _createChatGroupCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> createChatGroup(List<String> memberIds, List<String> memberNames) async {
  final groupName = FFAppState().pendingGroupName;
  final teamId    = FFAppState().currentTeamId;
  final userName  = FFAppState().userName;
  final myId      = FFAppState().userEmail;

  if (groupName.isEmpty) return;

  // Always include the creator in members so the array-contains filter works.
  final allMembers = [...memberIds];
  final allNames   = [...memberNames];
  if (myId.isNotEmpty && !allMembers.contains(myId)) {
    allMembers.add(myId);
    allNames.add(userName);
  }

  await FirebaseFirestore.instance.collection('chatGroups').add({
    'name':        groupName,
    'teamId':      teamId,
    'members':     allMembers,
    'memberNames': allNames,
    'createdBy':   userName,
    'createdAt': FieldValue.serverTimestamp(),
  });

  FFAppState().update(() {
    FFAppState().pendingGroupName = '';
  });
}
''';
  if (findCustomAction(project, name: 'CreateChatGroup') == null) {
    addCustomAction(
      project,
      name: 'CreateChatGroup',
      description: 'Maakt een chatGroups Firestore document aan voor de nieuwe groep.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'memberIds'),
          dataType: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ),
        FFParameter(
          identifier: FFIdentifier(name: 'memberNames'),
          dataType: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ),
      ],
      code: _createChatGroupCode,
    );
  } else {
    updateCustomAction(project, name: 'CreateChatGroup', code: _createChatGroupCode);
  }

  // Ensure stable param keys for _wireCreateGroupSubmitAction to reference.
  {
    final caIdx = project.customCode.customActions
        .indexWhere((ca) => ca.identifier.name == 'CreateChatGroup');
    if (caIdx >= 0) {
      final caCopy = project.customCode.customActions[caIdx].deepCopy();
      // memberIds
      final idsIdx = caCopy.arguments.indexWhere((a) => a.identifier.name == 'memberIds');
      if (idsIdx >= 0 && caCopy.arguments[idsIdx].identifier.key != _kMemberIdsParamKey) {
        caCopy.arguments[idsIdx].identifier.key = _kMemberIdsParamKey;
      }
      // memberNames — add if missing
      final namesIdx = caCopy.arguments.indexWhere((a) => a.identifier.name == 'memberNames');
      if (namesIdx < 0) {
        caCopy.arguments.add(FFParameter(
          identifier: FFIdentifier(key: _kMemberNamesParamKey, name: 'memberNames'),
          dataType: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ));
      } else if (caCopy.arguments[namesIdx].identifier.key != _kMemberNamesParamKey) {
        caCopy.arguments[namesIdx].identifier.key = _kMemberNamesParamKey;
      }
      project.customCode.customActions[caIdx] = caCopy;
    }
  }

  // ── DeleteChatGroup ──────────────────────────────────────────────────────────
  // Deletes the chatGroups document whose 'name' field equals pendingGroupName,
  // but only if the current user (AppState.userName) is the creator.
  const _deleteChatGroupCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> deleteChatGroup() async {
  final groupName = FFAppState().pendingGroupName;
  final userName  = FFAppState().userName;

  if (groupName.isEmpty) return;

  try {
    final snapshot = await FirebaseFirestore.instance
        .collection('chatGroups')
        .where('name', isEqualTo: groupName)
        .limit(1)
        .get();

    if (snapshot.docs.isEmpty) return;

    final doc       = snapshot.docs.first;
    final createdBy = doc.data()['createdBy'] as String? ?? '';

    if (createdBy != userName) return;

    await doc.reference.delete();
  } catch (_) {}
}
''';
  if (findCustomAction(project, name: 'DeleteChatGroup') == null) {
    addCustomAction(
      project,
      name: 'DeleteChatGroup',
      description: 'Verwijdert een chatGroep als de huidige gebruiker de aanmaker is.',
      arguments: [],
      code: _deleteChatGroupCode,
    );
  } else {
    updateCustomAction(project, name: 'DeleteChatGroup', code: _deleteChatGroupCode);
  }

  // ── LoadGroupMemberNames ─────────────────────────────────────────────────────
  // Reads memberNames from chatGroups where name == pendingGroupName,
  // then stores the result in AppState.groupMemberNames.
  const _loadGroupMemberNamesCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> loadGroupMemberNames() async {
  final groupId = FFAppState().pendingGroupName;
  if (groupId.isEmpty) return;
  try {
    final snap = await FirebaseFirestore.instance
        .collection('chatGroups')
        .where('name', isEqualTo: groupId)
        .limit(1)
        .get();
    if (snap.docs.isEmpty) return;
    final names = List<String>.from(
      (snap.docs.first.data()['memberNames'] as List<dynamic>? ?? [])
          .map((e) => e.toString()),
    );
    FFAppState().update(() {
      FFAppState().groupMemberNames = names;
    });
  } catch (_) {}
}
''';
  if (findCustomAction(project, name: 'LoadGroupMemberNames') == null) {
    addCustomAction(
      project,
      name: 'LoadGroupMemberNames',
      description: 'Laadt de ledennamen van een chatgroep in AppState.groupMemberNames.',
      arguments: [],
      code: _loadGroupMemberNamesCode,
    );
  } else {
    updateCustomAction(project, name: 'LoadGroupMemberNames', code: _loadGroupMemberNamesCode);
  }

  // ── FilterChatMembersByConv ──────────────────────────────────────────────────
  // Voor staffgroup / direct / group chats: filtert AppState.sharedTeamMembers
  // (gevuld door GetTeamMembers) op chatConversations.participantIds zodat
  // TeamMembersPage alleen de relevante deelnemers toont, niet het hele team.
  // Voor team chats: no-op (behoudt volledige team-lijst).
  const _kFilterChatMembersByConvCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> filterChatMembersByConv() async {
  final convId = FFAppState().currentConversationId;
  if (convId.isEmpty) return;

  String convType = '';
  List<String> participantEmails = const [];
  try {
    final snap = await FirebaseFirestore.instance
        .collection('chatConversations').doc(convId).get();
    if (snap.exists) {
      final data = snap.data() ?? {};
      final t = data['type'];
      if (t is String) convType = t;
      final raw = data['participantIds'];
      if (raw is List) participantEmails = raw.whereType<String>().toList();
    }
  } catch (_) {}

  // Team chat → toon volledige team-lijst, geen filter.
  // Onbekende type → ook geen filter (defensive, voorkomt lege lijst).
  if (convType.isEmpty || convType == 'team') return;

  // Voor direct/staffgroup/group: filter sharedTeamMembers op participantIds.
  final current = FFAppState().sharedTeamMembers;
  final filtered = current.where((m) {
    return participantEmails.contains(m.email);
  }).toList();
  FFAppState().update(() {
    FFAppState().sharedTeamMembers = filtered;
  });
}
''';
  if (findCustomAction(project, name: 'FilterChatMembersByConv') == null) {
    addCustomAction(
      project,
      name: 'FilterChatMembersByConv',
      description:
          'Filtert AppState.sharedTeamMembers op chatConversations.participantIds voor non-team chats (staffgroup/direct/group).',
      arguments: [],
      code: _kFilterChatMembersByConvCode,
    );
  } else {
    updateCustomAction(project, name: 'FilterChatMembersByConv', code: _kFilterChatMembersByConvCode);
  }

  // ── MarkConversationRead ─────────────────────────────────────────────────────
  // Resets unreadCount to 0 and hasUnread to false for the current conversation
  // (staged in AppState.currentConversationId before calling).
  const _markConversationReadCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> markConversationRead() async {
  final conversationId = FFAppState().currentConversationId;
  final myId           = FFAppState().userEmail;
  if (conversationId.isEmpty || myId.isEmpty) return;
  try {
    final db = FirebaseFirestore.instance;

    // Mark unread messages from others as read (batch, best-effort).
    final msgs = await db
        .collection('chatMessages')
        .where('conversationId', isEqualTo: conversationId)
        .get();

    final batch = db.batch();
    for (final doc in msgs.docs) {
      final data = doc.data();
      if (data['senderId'] != myId && data['isRead'] != true) {
        batch.update(doc.reference, {'isRead': true});
      }
    }
    // Reset alleen MIJN per-user unread teller — overige deelnemers blijven hun
    // eigen tellers behouden. unreadByUser is een Map<String, int> waarvan we
    // alleen myId nullen via set(merge:true) met een geneste map.
    batch.set(
      db.collection('chatConversations').doc(conversationId),
      {'unreadByUser': {myId: 0}},
      SetOptions(merge: true),
    );
    await batch.commit();
  } catch (_) {}
}
''';
  if (findCustomAction(project, name: 'MarkConversationRead') == null) {
    addCustomAction(
      project,
      name: 'MarkConversationRead',
      description: 'Zet ongelezen teller terug naar 0 voor het huidige gesprek.',
      arguments: [],
      code: _markConversationReadCode,
    );
  } else {
    updateCustomAction(project, name: 'MarkConversationRead', code: _markConversationReadCode);
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

/// Clears a container's background fill color so it renders with no fill.
/// Idempotent: safe to call repeatedly.
void _clearContainerFill(FFNode node) {
  if (!node.props.container.hasBoxDecoration()) return;
  final bd = node.props.container.boxDecoration.deepCopy();
  bd.clearColorValue();
  node.props.container.boxDecoration = bd;
}

/// Removes the fill color from the ProfielInfoCard container on ProfielPage.
void _removeProfielInfoCardFill(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;
  final card = findDescendants(wc.node, (n) => n.name == 'ProfielInfoCard').firstOrNull;
  if (card == null) return;
  _clearContainerFill(card);
}

/// Adds a small "OwnNameChip" near the top of the given detail page body,
/// styled like the club-name chip on the profile page:
///   - background: light green (0xFFDCFCE7)
///   - text color: primary (club green)
///   - rounded 12px, padding 12/4
///   - bound to AppState.userName
/// Idempotent: skips if a chip with this name already exists on the page.
void _addOwnNameChip(FFProject project, String pageName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'OwnNameChip').isNotEmpty) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;

  // Text bound to AppState.userName, with primary-color font.
  final chipText = UI.text('', name: 'OwnNameChipText', style: UITextStyle.bodySmall);
  chipText.props.text.textValue =
      FFStringValue(variable: varFromAppState(userNameId.deepCopy()));
  final txtCopy = chipText.props.text.deepCopy();
  txtCopy.colorValue =
      FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY));
  chipText.props.text = txtCopy;

  // Container: light-green pill (0xFFDCFCE7), rounded 12, 12×4 padding.
  final chip = UI.container(
    name: 'OwnNameChip',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    borderRadius: 12,
    child: chipText,
  );
  _setContainerColor(chip, FFColorValue(
    inputValue: FFColor(value: Int64(0xFFDCFCE7)),
  ));

  // Keep the chip at its natural width — wrap in a Row aligned left so it
  // doesn't stretch full-width inside the column.
  final wrap = UI.row(
    name: 'OwnNameChipRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [chip],
  );
  final wrapCopy = wrap.props.container.deepCopy();
  // Add a small bottom margin so it doesn't stick to the next row.
  wrap.props.container = wrapCopy;

  // Insert at the top of the body column.
  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild == null) return;
  if (bodyChild.type == FFWidgetType.Column) {
    bodyChild.children.insert(0, wrap);
  } else {
    final column = UI.column(name: '${pageName}BodyColumn');
    column.children.addAll([wrap, bodyChild]);
    final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
    if (idx >= 0) wc.node.children[idx] = column;
    wc.node.childPropertyMap['body'] = FFChildrenKeys(
      keyRefs: [FFNodeKeyReference(key: column.key)],
    );
  }
}

/// Removes the OwnNameChipRow + OwnNameChip (from a previous experiment) from a
/// page. Safe to call when the chip is absent.
void _removeOwnNameChip(FFProject project, String pageName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  // Remove OwnNameChipRow (the Row wrapper) by name in the page tree.
  for (final n in findDescendants(wc.node, (n) =>
      n.name == 'OwnNameChipRow' || n.name == 'OwnNameChip')) {
    removeByKey(wc.node, n.key);
  }
}

const String _kHighlightedNameListCode = r'''
import 'package:flutter/material.dart';
import '/flutter_flow/flutter_flow_theme.dart';

class HighlightedNameList extends StatelessWidget {
  const HighlightedNameList({
    super.key,
    this.width,
    this.height,
    this.names,
    this.userName,
  });

  final double? width;
  final double? height;
  final String? names;
  final String? userName;

  @override
  Widget build(BuildContext context) {
    final theme = FlutterFlowTheme.of(context);
    final raw = (names ?? '').trim();
    if (raw.isEmpty) {
      return SizedBox(
        width: width,
        child: Text('-', style: theme.bodyMedium),
      );
    }
    final me = (userName ?? '').trim().toLowerCase();
    final parts = raw
        .split(RegExp(r'[,;]'))
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();
    if (parts.isEmpty) {
      return SizedBox(
        width: width,
        child: Text('-', style: theme.bodyMedium),
      );
    }
    return SizedBox(
      width: width,
      child: Wrap(
        spacing: 6,
        runSpacing: 6,
        children: parts.map((name) {
          final isMe = me.isNotEmpty && name.toLowerCase() == me;
          if (!isMe) {
            return Text(name, style: theme.bodyMedium);
          }
          return Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFFBBF7D0),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: theme.primary, width: 1.5),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.person, size: 14, color: theme.primary),
                const SizedBox(width: 4),
                Text(
                  name,
                  style: theme.bodyMedium.copyWith(
                    color: theme.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }
}
''';

/// Ensures the HighlightedNameList custom widget exists (idempotent).
void _ensureHighlightedNameListWidget(FFProject project) {
  if (findCustomWidget(project, name: 'HighlightedNameList') == null) {
    addCustomWidget(
      project,
      name: 'HighlightedNameList',
      description:
          'Toont een komma-gescheiden namenlijst, markeert de eigen naam met een groen pill.',
      parameters: [
        FFParameter(
          identifier: FFIdentifier(name: 'names'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
        FFParameter(
          identifier: FFIdentifier(name: 'userName'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: _kHighlightedNameListCode,
    );
  } else {
    updateCustomWidget(
      project,
      name: 'HighlightedNameList',
      code: _kHighlightedNameListCode,
    );
  }
}

/// Replaces a single Text widget that was bound to a page-state field with a
/// HighlightedNameList custom widget instance bound to the same state + the
/// current user's name. Returns true when the swap happened.
bool _swapValueTextForHighlighted({
  required FFProject project,
  required String pageName,
  required String valueNodeName,
  required String stateFieldName,
}) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return false;

  final highlightWidget =
      findCustomWidget(project, name: 'HighlightedNameList');
  if (highlightWidget == null) return false;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return false;

  final stateFieldId = _findPageStateFieldId(project, pageName, stateFieldName);
  if (stateFieldId == null) return false;

  // Idempotent: skip when the swap has already happened (a custom widget by
  // name 'NameList_<stateField>' is sitting in the tree).
  final placedName = 'NameList_$stateFieldName';
  if (findDescendants(wc.node, (n) => n.name == placedName).isNotEmpty) {
    return false;
  }

  final valueNode = findDescendants(wc.node, (n) => n.name == valueNodeName).firstOrNull;
  if (valueNode == null) return false;

  final parentResult = findParentByKey(wc.node, valueNode.key);
  if (parentResult == null) return false;
  final parent = parentResult.parent;

  final namesVar = varFromPageState(stateFieldId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final widgetNode = UI.customWidget(
    highlightWidget,
    name: placedName,
    params: {
      'names': VariableParamValue(namesVar),
      'userName': VariableParamValue(varFromAppState(userNameId.deepCopy())),
    },
  );

  final idx = parent.children.indexWhere((n) => n.key == valueNode.key);
  if (idx >= 0) {
    parent.children[idx] = widgetNode;
  } else {
    parent.children.add(widgetNode);
  }
  return true;
}

/// Wires the green-name highlight on the bardienst-detail (Leden row) and
/// rijschema-detail (Rijders row). Replaces the existing value Text widget
/// with a HighlightedNameList instance.
void _wireOwnNameHighlight(FFProject project) {
  _ensureHighlightedNameListWidget(project);

  _swapValueTextForHighlighted(
    project: project,
    pageName: 'BardienDetailPage',
    valueNodeName: 'DutyInfoValue_dutyMembers',
    stateFieldName: 'dutyMembers',
  );
  _swapValueTextForHighlighted(
    project: project,
    pageName: 'RijschemaDetailPage',
    valueNodeName: 'RijInfoValue_rijDriverNames',
    stateFieldName: 'rijDriverNames',
  );
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

FFIdentifier? _findPageStateFieldId(FFProject project, String pageName, String fieldName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return null;
  final field = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == fieldName, orElse: () => null);
  return field?.parameter.identifier;
}

// ─── ChatDetailPage (universeel: teamchat / direct / staffgroep) ─────────────

void _buildChatDetailPage(App app, FirestoreCollectionHandle chatMessages) {
  app.ensurePage(
    'ChatDetailPage',
    description: 'Universele chatpagina voor teamchat, direct en staffgroep-gesprekken.',
    route: 'chat-detail',
    params: {
      'conversationId': string.withDefault(''),
      'title': string.withDefault('Chat'),
    },
    state: {
      'chatMessages': listOf(chatMessages),
      'messageText': string,
    },
    onLoad: [
      // Load messages filtered by conversationId (filter applied by _wireChatDetailFilters).
      FirestoreQuery(
        chatMessages,
        limit: 100,
        singleTimeQuery: false,
        outputAs: 'loadedMessages',
      ),
      SetState(ff.Pages.chatDetailPage.state.chatMessages, ActionOutput('loadedMessages')),
      // Clear pending message text from previous conversation.
      UpdateAppState.set(ff.AppState.pendingMessageText, ''),
    ],
    body: Column(
      children: [
        // Messages list — realtime stream filtered by conversationId
        Expanded(
          ListView(
            source: State(ff.Pages.chatDetailPage.state.chatMessages),
            padding: 12,
            spacing: 8,
            itemBuilder: (_) => Column(
              crossAxis: CrossAxis.stretch,
              children: [
                // Others' message — left bubble with sender name
                Row(
                  mainAxis: MainAxis.start,
                  visible: Not(Equals(ItemRef()['senderId'], AppState(ff.AppState.userEmail))),
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
                          Text(ItemRef()['text'],     style: Styles.bodyMedium),
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
                // Own message — right bubble
                Row(
                  mainAxis: MainAxis.end,
                  visible: Equals(ItemRef()['senderId'], AppState(ff.AppState.userEmail)),
                  children: [
                    Container(
                      padding: 12,
                      borderRadius: 12,
                      color: Colors.primary,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        spacing: 4,
                        children: [
                          Text(ItemRef()['text'],     style: Styles.bodyMedium, color: Colors.primaryBackground),
                          Text(ItemRef()['createdAt'], style: Styles.bodySmall,  color: Colors.primaryBackground),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        // Send area — stages text in AppState, calls SendMessage custom action.
        // Pattern avoids FF validator rejection of scalar String custom-action args.
        Container(
          padding: 12,
          color: Colors.primaryBackground,
          child: Row(
            spacing: 8,
            children: [
              Expanded(
                TextField(
                  hint: 'Bericht typen...',
                  name: 'ChatMessageField',
                  maxLines: 3,
                  onChanged: [
                    SetState(ff.Pages.chatDetailPage.state.messageText, TextValue()),
                    UpdateAppState.set(ff.AppState.pendingMessageText, TextValue()),
                  ],
                ),
              ),
              IconButton(
                'send',
                color: Colors.primary,
                onTap: [
                  If(
                    Not(Equals(State(ff.Pages.chatDetailPage.state.messageText), '')),
                    then: [
                      // currentConversationId is set by navigation param wiring below.
                      CallCustomAction.named('SendMessage', arguments: {}),
                      SetState.clear(ff.Pages.chatDetailPage.state.messageText),
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
// (Niet meer gebruikt — verplaatst naar AppDrawer.)
// ignore: unused_element
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

// Voegt "Ouder / Verzorger" knop toe aan ProfielPage, onder de Handleiding-knop.
// Navigeert naar GuardianPage. Idempotent.
void _addGuardianButton(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;

  if (findPage(project, name: 'GuardianPage') == null) return;

  if (findDescendants(wc.node, (n) => n.name == 'GuardianButton').isNotEmpty) return;

  final button = UI.button(
    'Ouder / Verzorger',
    name: 'GuardianButton',
  );
  Actions.onTap(button, Actions.navigate(project, pageName: 'GuardianPage'));

  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild != null && bodyChild.type == FFWidgetType.Column) {
    bodyChild.children.add(button);
  } else {
    wc.node.children.add(button);
  }
}

// Verwijdert alle nodes met de gegeven naam van ProfielPage (idempotent).
// Gebruikt om historische Handleiding- en Bug-melden knoppen op te ruimen
// nu die in de AppDrawer staan.
void _removeProfielButton(FFProject project, String nodeName) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;
  final keysToRemove = findDescendants(wc.node, (n) => n.name == nodeName)
      .map((n) => n.key)
      .toList();
  for (final key in keysToRemove) {
    removeByKey(wc.node, key);
  }
}

// Toont op de ProfielPage alle teams waaraan de gebruiker gekoppeld is
// (AppState.availableTeams) — zo zie je meteen of je aan meerdere teams hangt.
// Read-only lijst; de data wordt automatisch ververst door RefreshCurrentTeam
// op de ProfielPage-load (geen opnieuw inloggen nodig). Idempotent.
void _addProfielTeamsList(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;

  final availTeamsId = _findAppStateFieldId(project, 'availableTeams');
  if (availTeamsId == null) return;

  // Doel: het info-content-column van de profielkaart.
  final target = findDescendants(wc.node, (n) => n.name == 'ProfielInfoContent').firstOrNull;
  if (target == null || target.type != FFWidgetType.Column) return;

  // Vers opbouwen bij elke push (zodat binding-wijzigingen meekomen): ruim een
  // eerdere label + lijst op.
  for (final name in const ['ProfielTeamsLabel', 'ProfielTeamsList']) {
    for (final n in findDescendants(wc.node, (x) => x.name == name).toList()) {
      removeByKey(wc.node, n.key);
    }
  }

  final label = UI.text(
    'Teams',
    name: 'ProfielTeamsLabel',
    style: UITextStyle.labelSmall,
    color: UIColor.secondaryText,
  );

  final teamsVar = varFromAppState(availTeamsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final list = UI.listView(
    name: 'ProfielTeamsList',
    spacing: 6,
    dynamicSource: DynamicSource(variable: teamsVar, itemName: 'team'),
  );
  final lv = list.props.listView.deepCopy();
  lv.shrinkWrapValue = FFBooleanValue(inputValue: true);
  list.props.listView = lv;

  // Toon "Team — Functie" (bv. "MO13-1 — Coach"); zonder rol alleen de teamnaam.
  final labelVar = codeExpressionVar(
    expression: "((role ?? '') != '') ? ((name ?? '') + ' — ' + (role ?? '')) : (name ?? '')",
    arguments: [
      CodeExpressionArg(
        name: 'name',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: generatorVarField(list.key, 'name')),
      ),
      CodeExpressionArg(
        name: 'role',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: generatorVarField(list.key, 'role')),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );
  final nameText = UI.text('', name: 'ProfielTeamName', style: UITextStyle.bodyMedium);
  nameText.props.text.textValue = FFStringValue(variable: labelVar);

  final tile = UI.row(
    name: 'ProfielTeamRow',
    spacing: 6,
    crossAxisAlignment: UICrossAxisAlignment.center,
    children: [
      UI.icon('groups', size: 16, color: UIColor.primary),
      nameText,
    ],
  );
  list.children.add(tile);

  target.children.add(label);
  target.children.add(list);
}

// Zet RefreshCurrentTeam vooraan de ProfielPage-onLoad, zodat de gekoppelde
// teams (availableTeams) + het huidige team automatisch verversen bij openen —
// geen opnieuw inloggen nodig. Prepend zodat het naast WatchUnreadChatCount past.
// Idempotent.
void _wireProfielRefreshOnLoad(FFProject project, [String pageName = 'ProfielPage']) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final action = findCustomAction(project, name: 'RefreshCurrentTeam');
  if (action == null) return;

  bool chainHas(FFActionNode n) {
    if (n.hasAction() &&
        n.action.hasCustomAction() &&
        n.action.customAction.customActionIdentifier.name == 'RefreshCurrentTeam') {
      return true;
    }
    return n.hasFollowUpAction() && chainHas(n.followUpAction);
  }

  FFActionNode refreshNode() => FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          customAction: FFCustomActionCall(
            customActionIdentifier: action.identifier.deepCopy(),
            argumentValues: FFFunctionCallValues(),
          ),
        ),
      );

  final idx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  if (idx < 0) {
    Actions.onPageLoadChain(wc.node, refreshNode());
    return;
  }

  final tap = wc.node.triggerActions[idx];
  if (!tap.hasRootAction()) {
    tap.rootAction = refreshNode();
    return;
  }
  if (chainHas(tap.rootAction)) return;

  // Prepend: RefreshCurrentTeam wordt de nieuwe root, oude chain als follow-up.
  final oldRoot = tap.rootAction.deepCopy();
  final node = refreshNode();
  node.followUpAction = oldRoot;
  tap.rootAction = node;
}

// Voegt "Bug melden" knop toe aan ProfielPage. Navigeert naar BugReportPage.
// (Niet meer gebruikt — verplaatst naar AppDrawer.)
// ignore: unused_element
void _addBugReportButton(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;
  if (findPage(project, name: 'BugReportPage') == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'BugReportButton').isNotEmpty) return;

  final button = UI.button('Bug melden', name: 'BugReportButton');
  Actions.onTap(button, Actions.navigate(project, pageName: 'BugReportPage'));

  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild != null && bodyChild.type == FFWidgetType.Column) {
    bodyChild.children.add(button);
  } else {
    wc.node.children.add(button);
  }
}

// Custom action: verwijdert de eigen chatdata in Firestore bij account
// verwijderen. Best-effort (per operatie in try/catch, zodat Firestore-rules
// die iets blokkeren de rest niet stoppen):
//   1. eigen berichten in alle gesprekken (senderId == eigen userEmail)
//   2. directe (1-op-1) gesprekken van de gebruiker + hun berichten
//   3. de eigen appUsers-registratie(s)
// Teamchat-/groepsberichten van ANDEREN blijven bestaan (gedeelde data); de
// eigen berichten daarin zijn via stap 1 al verwijderd.
void _ensureDeleteMyChatDataAction(FFProject project) {
  const _code = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<String> deleteMyChatData() async {
  final uid = FFAppState().userEmail.isNotEmpty
      ? FFAppState().userEmail
      : FFAppState().userName;
  if (uid.isEmpty) return '';
  final db = FirebaseFirestore.instance;

  // 1. Eigen berichten in álle gesprekken verwijderen.
  try {
    final own =
        await db.collection('chatMessages').where('senderId', isEqualTo: uid).get();
    for (final doc in own.docs) {
      await doc.reference.delete().catchError((Object _) {});
    }
  } catch (_) {}

  // 2. Directe (1-op-1) gesprekken van de gebruiker + hun berichten verwijderen.
  try {
    final convos = await db
        .collection('chatConversations')
        .where('participantIds', arrayContains: uid)
        .get();
    for (final convo in convos.docs) {
      final data = convo.data();
      final type = (data['type'] as String?) ?? '';
      // Alleen directe gesprekken volledig verwijderen; teamchats/groepen zijn
      // gedeeld en blijven bestaan (eigen berichten zijn via stap 1 al weg).
      if (type != 'direct') continue;
      final convoId = (data['conversationId'] as String?) ?? convo.id;
      try {
        final msgs = await db
            .collection('chatMessages')
            .where('conversationId', isEqualTo: convoId)
            .get();
        for (final m in msgs.docs) {
          await m.reference.delete().catchError((Object _) {});
        }
      } catch (_) {}
      await convo.reference.delete().catchError((Object _) {});
    }
  } catch (_) {}

  // 3. Eigen appUsers-registratie(s) verwijderen.
  try {
    final regs =
        await db.collection('appUsers').where('userId', isEqualTo: uid).get();
    for (final doc in regs.docs) {
      await doc.reference.delete().catchError((Object _) {});
    }
  } catch (_) {}

  return '';
}
''';

  if (findCustomAction(project, name: 'DeleteMyChatData') == null) {
    addCustomAction(
      project,
      name: 'DeleteMyChatData',
      description:
          'Verwijdert de eigen chatdata in Firestore bij account verwijderen: eigen berichten, directe gesprekken en de appUsers-registratie. Best-effort.',
      arguments: const [],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
      ),
      code: _code,
    );
  } else {
    updateCustomAction(project, name: 'DeleteMyChatData', code: _code);
  }
}

// Voegt een rode "Account verwijderen"-knop onderaan de ProfielPage toe.
// Actie-chain: bevestig-dialog → DeleteAccount API (soft-delete + tokens weg)
//   → bij succes: eigen chatdata verwijderen + auth-state wissen + naar LoginPage
//   → bij falen: snackbar.
// Idempotent: verwijdert een eerder toegevoegde knop en bouwt de chain vers op,
// zodat wijzigingen bij elke push meekomen.
void _addDeleteAccountButton(FFProject project) {
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) return;

  // Endpoint moet bestaan (aangemaakt in _addGuardianEndpoints).
  final hasEndpoint = project.backend.apiConfig.apiGroups
      .any((g) => g.endpoints.any((e) => e.identifier.name == 'DeleteAccount'));
  if (!hasEndpoint) return;

  // Custom action die de eigen chatdata in Firestore opruimt.
  _ensureDeleteMyChatDataAction(project);

  // Verse chain bij elke push: ruim een eerdere knop op.
  _removeProfielButton(project, 'DeleteAccountButton');

  FFValue _lit(String s) =>
      FFValue(inputValue: FFParameterValue(serializedValue: s));

  final button = UI.button(
    'Account verwijderen',
    name: 'DeleteAccountButton',
    width: double.infinity,
    color: UIColor.error,
    textColor: UIColor.secondaryBackground,
  );

  // Success chain:
  //   1. DeleteMyChatData — eigen chatdata verwijderen (móét vóór het wissen van
  //      userEmail draaien, want de actie leest die uit AppState).
  //   2. auth-state wissen (zoals de Uitloggen-knop doet).
  //   3. naar LoginPage.
  final successActions = <FFAction>[];
  final deleteChatAction = findCustomAction(project, name: 'DeleteMyChatData');
  if (deleteChatAction != null) {
    successActions.add(FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: deleteChatAction.identifier.deepCopy(),
        argumentValues: FFFunctionCallValues(),
      ),
    ));
  }
  successActions.add(Actions.updateAppState(
    project,
    updates: [
      StateFieldUpdate.set('authToken', ''),
      StateFieldUpdate.set('userName', ''),
      StateFieldUpdate.set('userEmail', ''),
      StateFieldUpdate.set('clubName', ''),
    ],
  ));
  successActions.add(
      Actions.navigate(project, pageName: 'LoginPage', replaceRoute: true));
  final successChain = Actions.chain(successActions);

  final apiNode = Actions.apiCallNode(
    project,
    endpointName:       'DeleteAccount',
    groupName:          'VoetbalPlannerAPI',
    outputVariableName: 'deleteAccountResult',
    nodeKey:            button.key,
    onSuccess: (ctx) => successChain,
    onFailure: (ctx) => Actions.chain([
      Actions.snackBar('Verwijderen mislukt. Probeer het later opnieuw.'),
    ]),
  );

  // Bevestig-dialog → (bij bevestigen) API-call chain.
  final confirmNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      alertDialog: FFAlertDialogAction(
        confirmDialog: FFConfirmDialogAction(
          title:       _lit('Account definitief verwijderen?'),
          message:     _lit(
              'Let op: dit kan NIET ongedaan worden gemaakt.\n\n'
              'Al uw gegevens worden permanent verwijderd, waaronder:\n'
              '• uw profiel en persoonsgegevens\n'
              '• uw volledige chatgeschiedenis\n'
              '• uw team-, wedstrijd- en bardienstgegevens\n\n'
              'Weet u zeker dat u akkoord gaat en alles wilt verwijderen?'),
          confirmText: _lit('Ja, alles verwijderen'),
          dismissText: _lit('Annuleren'),
        ),
      ),
    ),
    followUpAction: apiNode,
  );

  Actions.onTapChain(button, confirmNode);

  // De body is inmiddels een Stack; de knoppen (Uitloggen/Ouder) staan in de
  // Column daarbinnen. Zoek die Column op i.p.v. te vertrouwen op body==Column.
  FFNode? targetColumn;
  for (final c in findDescendants(wc.node, (n) => n.type == FFWidgetType.Column)) {
    if (c.children.any((ch) =>
        ch.key == 'Button_wvz4j2lc' || ch.name == 'GuardianButton')) {
      targetColumn = c;
      break;
    }
  }
  targetColumn ??= () {
    final b = getPropertyChild(wc.node, 'body');
    return (b != null && b.type == FFWidgetType.Column) ? b : null;
  }();

  if (targetColumn != null) {
    targetColumn.children.add(button);
  } else {
    wc.node.children.add(button);
  }
}

// Declareert de BugReportPage (state-velden + placeholder body).
void _buildBugReportPage(App app) {
  app.ensurePage(
    'BugReportPage',
    description: 'Formulier waarmee gebruikers bugs/meldingen versturen, eventueel met schermafbeeldingen.',
    route: 'bug-report',
    state: {
      'title':         string.withDefault(''),
      'description':   string.withDefault(''),
      'screenshotCount': int_.withDefault(0),
      'isSubmitting':  bool_.withDefault(false),
      'errorMessage':  string.withDefault(''),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Bug melden'),
      body: Column(
        name: 'BugReportRootColumn',
        children: [
          Container(name: 'BugReportBodyPlaceholder'),
        ],
      ),
    ),
  );
}

// Custom Dart action die de bug submit met multipart upload (max 5 screenshots).
void _ensureBugReportCustomAction(FFProject project) {
  const _code = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart' show MediaType;
import 'package:image_picker/image_picker.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';

// Screenshots worden gedeeld via FFAppState().bugScreenshotPaths (List<String>).
// Een module-global zou NIET gedeeld worden tussen de losse custom-action-
// bestanden (pick / clear / submit) — vandaar AppState.

String _detectPlatform() {
  if (kIsWeb) return 'web';
  try {
    if (Platform.isAndroid) return 'android';
    if (Platform.isIOS) return 'ios';
    if (Platform.isMacOS) return 'macos';
    if (Platform.isWindows) return 'windows';
    if (Platform.isLinux) return 'linux';
  } catch (_) {}
  return 'other';
}

Future<String> _detectAppVersion() async {
  try {
    final info = await PackageInfo.fromPlatform();
    final v = info.version;
    final b = info.buildNumber;
    return b.isNotEmpty ? '$v+$b' : v;
  } catch (_) {
    return '';
  }
}

Future<String> _detectDeviceInfo() async {
  try {
    final plugin = DeviceInfoPlugin();
    if (kIsWeb) {
      final wb = await plugin.webBrowserInfo;
      final ua = wb.userAgent ?? '';
      return ua.isNotEmpty ? ua.substring(0, ua.length > 240 ? 240 : ua.length) : 'web';
    }
    if (Platform.isAndroid) {
      final a = await plugin.androidInfo;
      return '${a.manufacturer} ${a.model} (Android ${a.version.release})';
    }
    if (Platform.isIOS) {
      final i = await plugin.iosInfo;
      return '${i.utsname.machine} (iOS ${i.systemVersion})';
    }
    if (Platform.isMacOS) {
      final m = await plugin.macOsInfo;
      return '${m.model} (macOS ${m.osRelease})';
    }
    if (Platform.isWindows) {
      final w = await plugin.windowsInfo;
      return '${w.computerName} (Windows ${w.displayVersion})';
    }
    if (Platform.isLinux) {
      final l = await plugin.linuxInfo;
      return '${l.prettyName.isNotEmpty ? l.prettyName : l.name}';
    }
  } catch (_) {}
  return '';
}

Future<int> pickBugScreenshot(BuildContext context) async {
  try {
    if (FFAppState().bugScreenshotPaths.length >= 5) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Maximaal 5 schermafbeeldingen.')),
      );
      return FFAppState().bugScreenshotPaths.length;
    }
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 80,
    );
    if (picked == null) return FFAppState().bugScreenshotPaths.length;
    // Reassign (niet in-place muteren) zodat de AppState-setter de wijziging
    // oppikt en submit dezelfde lijst ziet.
    final paths = List<String>.from(FFAppState().bugScreenshotPaths)..add(picked.path);
    FFAppState().bugScreenshotPaths = paths;
    return paths.length;
  } catch (e) {
    debugPrint('[BugReport pick] $e');
    return FFAppState().bugScreenshotPaths.length;
  }
}

Future<bool> clearBugScreenshots() async {
  FFAppState().bugScreenshotPaths = [];
  return true;
}

Future<bool> submitBugReport(
  BuildContext context,
  String? title,
  String? description,
) async {
  final messenger = ScaffoldMessenger.of(context);
  final t = (title ?? '').trim();
  final d = (description ?? '').trim();
  if (t.isEmpty || d.isEmpty) {
    messenger.showSnackBar(
      const SnackBar(content: Text('Vul een titel en omschrijving in.')),
    );
    return false;
  }

  final token = FFAppState().authToken;
  if (token.isEmpty) {
    messenger.showSnackBar(const SnackBar(content: Text('Niet ingelogd.')));
    return false;
  }

  // Verzamel device + app info parallel om submit niet te vertragen.
  final appVersion = await _detectAppVersion();
  final deviceInfo = await _detectDeviceInfo();

  try {
    final uri = Uri.parse('https://voetbalplanner.nubix.nl/api/v1/bug-reports');
    final req = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer $token'
      ..headers['Accept']        = 'application/json'
      ..fields['title']          = t
      ..fields['description']    = d
      ..fields['platform']       = _detectPlatform()
      ..fields['app_version']    = appVersion
      ..fields['device_info']    = deviceInfo;

    final shotPaths = FFAppState().bugScreenshotPaths;
    for (var i = 0; i < shotPaths.length; i++) {
      final bytes = await XFile(shotPaths[i]).readAsBytes();
      // image_picker re-encodeert naar JPEG (imageQuality:80), maar XFile.name
      // kan een verkeerde extensie hebben (bv. .heic op iOS). Forceer .jpg +
      // expliciete content-type zodat Laravel's mimes-validatie 'm accepteert.
      req.files.add(http.MultipartFile.fromBytes(
        'screenshots[]',
        bytes,
        filename: 'screenshot_$i.jpg',
        contentType: MediaType('image', 'jpeg'),
      ));
    }

    final streamed = await req.send();
    final response = await http.Response.fromStream(streamed);

    if (response.statusCode != 201 && response.statusCode != 200) {
      // Toon ook de body-message als die er is, zodat validation errors
      // (bijv. titel te kort, te grote screenshot) zichtbaar zijn.
      String detail = '';
      try {
        final body = jsonDecode(response.body) as Map<String, dynamic>?;
        final msg = (body?['message'] as String?) ?? '';
        if (msg.isNotEmpty) detail = ' — $msg';
      } catch (_) {}
      messenger.showSnackBar(SnackBar(
        content: Text('Versturen mislukt (HTTP ${response.statusCode})$detail'),
        duration: const Duration(seconds: 6),
      ));
      return false;
    }

    FFAppState().bugScreenshotPaths = [];
    messenger.showSnackBar(const SnackBar(
      content: Text('Melding verstuurd! Bedankt.'),
    ));
    return true;
  } catch (e) {
    final msg = e.toString();
    final hint = msg.contains('Failed to fetch') || msg.contains('XMLHttpRequest')
        ? ' (mogelijk CORS of server niet bereikbaar — controleer of /api/v1/bug-reports bestaat op de server)'
        : '';
    messenger.showSnackBar(SnackBar(
      content: Text('Fout: $msg$hint'),
      duration: const Duration(seconds: 8),
    ));
    return false;
  }
}
''';

  if (findCustomAction(project, name: 'PickBugScreenshot') == null) {
    addCustomAction(
      project,
      name: 'PickBugScreenshot',
      description: 'Voegt een schermafbeelding toe aan de bug-melding. Returnt het nieuwe totaal aantal.',
      arguments: [],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Integer),
      ),
      includeContext: true,
      code: _code,
    );
  } else {
    updateCustomAction(project, name: 'PickBugScreenshot', code: _code,
        arguments: [], includeContext: true);
  }

  if (findCustomAction(project, name: 'ClearBugScreenshots') == null) {
    addCustomAction(
      project,
      name: 'ClearBugScreenshots',
      description: 'Wist alle geselecteerde bug-screenshots.',
      arguments: [],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: _code,
    );
  } else {
    updateCustomAction(project, name: 'ClearBugScreenshots', code: _code, arguments: []);
  }

  const _kBugTitleArgKey = 'bug_title_arg';
  const _kBugDescArgKey  = 'bug_desc_arg';
  final submitArgs = [
    FFParameter(
      identifier: FFIdentifier(name: 'title', key: _kBugTitleArgKey),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    ),
    FFParameter(
      identifier: FFIdentifier(name: 'description', key: _kBugDescArgKey),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    ),
  ];

  if (findCustomAction(project, name: 'SubmitBugReport') == null) {
    addCustomAction(
      project,
      name: 'SubmitBugReport',
      description: 'Verstuurt een bug-melding inclusief screenshots naar de Laravel API.',
      arguments: submitArgs,
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      includeContext: true,
      code: _code,
    );
  } else {
    updateCustomAction(
      project,
      name: 'SubmitBugReport',
      code: _code,
      arguments: submitArgs,
      includeContext: true,
    );
  }

  // Pub dependencies.
  try { addPubDependency(project, name: 'image_picker',      version: '^1.0.0'); } catch (_) {}
  try { addPubDependency(project, name: 'http',              version: '^1.2.0'); } catch (_) {}
  try { addPubDependency(project, name: 'http_parser',       version: '^4.0.0'); } catch (_) {}
  try { addPubDependency(project, name: 'package_info_plus', version: '^8.0.0'); } catch (_) {}
  try { addPubDependency(project, name: 'device_info_plus',  version: '^11.5.0'); } catch (_) {}
  // Ook actief upgraden als hij al stond op een oudere versie (anders blokkeert build).
  try { updatePubDependency(project, name: 'device_info_plus', newVersion: '^11.5.0'); } catch (_) {}
}

// Bouwt de body van BugReportPage met titel/omschrijving veld, screenshot
// teller, "Voeg screenshot toe" knop, en verstuur-knop.
void _buildBugReportPageBody(FFProject project) {
  final wc = findPage(project, name: 'BugReportPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'BugReportSubmitButton').isNotEmpty) return;

  final scaffoldKey = wc.node.key;
  final rootCol = findDescendants(wc.node,
      (n) => n.name == 'BugReportRootColumn').firstOrNull;
  if (rootCol == null) return;
  rootCol.children.removeWhere((n) => n.name == 'BugReportBodyPlaceholder');

  // Scrollable
  if (rootCol.props.hasColumn()) {
    final c = rootCol.props.column.deepCopy();
    c.scrollable = true;
    rootCol.props.column = c;
  }

  final titleField = UI.textField(
    hintText: 'Korte titel van het probleem', name: 'BugTitleField');
  final descField = UI.textField(
    hintText: 'Beschrijf wat er mis gaat, welke stappen je doet en wat je verwacht.',
    name: 'BugDescriptionField',
    maxLines: 8,
  );

  // Screenshot teller binding
  final countId = _findPageStateFieldId(project, 'BugReportPage', 'screenshotCount');
  final countText = UI.text('0 schermafbeeldingen toegevoegd',
      name: 'BugScreenshotCountText', style: UITextStyle.bodySmall);
  if (countId != null) {
    final tc = countText.props.text.deepCopy();
    tc.textValue = interpolateVar([
      '',
      varFromPageState(countId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey),
      ' schermafbeelding(en) toegevoegd',
    ]);
    countText.props.text = tc;
  }

  final addScreenshotBtn = UI.button(
    'Schermafbeelding toevoegen',
    name: 'BugAddScreenshotButton',
    width: double.infinity,
    color: UIColor.secondaryBackground,
    textColor: UIColor.primary,
    borderRadius: 8,
  );

  final submitBtn = UI.button('Versturen',
      name: 'BugReportSubmitButton', width: double.infinity);

  // Foutcontainer
  final errorId = _findPageStateFieldId(project, 'BugReportPage', 'errorMessage');
  final errorContainer = UI.container(
    name: 'BugErrorContainer',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.error,
    child: UI.text('', name: 'BugErrorText', style: UITextStyle.bodySmall, color: UIColor.secondaryBackground),
  );
  if (errorId != null) {
    final errTxt = findDescendants(errorContainer, (n) => n.name == 'BugErrorText').firstOrNull;
    if (errTxt != null) {
      final errVar = varFromPageState(errorId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
      final copy = errTxt.props.text.deepCopy();
      copy.textValue = FFStringValue(variable: errVar.deepCopy());
      errTxt.props.text = copy;
    }
    final isErrVar = conditionVar(
      varFromPageState(errorId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
    setConditionalVisibility(errorContainer, variable: isErrVar);
  }

  final formCol = UI.column(
    name: 'BugReportFormCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 16,
    children: [
      UI.icon('bug_report', size: 48, color: UIColor.primary, name: 'BugIcon'),
      UI.text('Bug of probleem melden',
          name: 'BugReportTitle', style: UITextStyle.titleMedium),
      UI.text(
        'Hoe duidelijker je de fout omschrijft, hoe sneller we hem kunnen oplossen. '
        'Je kunt tot 5 schermafbeeldingen toevoegen.',
        name: 'BugReportIntro', style: UITextStyle.bodySmall,
      ),
      UI.container(name: 'BugDivider', padding: UIEdgeInsets.only(bottom: 4)),

      UI.column(
        name: 'BugTitleCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 4,
        children: [
          UI.text('Titel', name: 'BugTitleLabel', style: UITextStyle.labelMedium),
          titleField,
        ],
      ),
      UI.column(
        name: 'BugDescriptionCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 4,
        children: [
          UI.text('Omschrijving', name: 'BugDescriptionLabel', style: UITextStyle.labelMedium),
          descField,
        ],
      ),
      UI.column(
        name: 'BugScreenshotCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 8,
        children: [
          UI.text('Schermafbeeldingen (optioneel)',
              name: 'BugScreenshotLabel', style: UITextStyle.labelMedium),
          countText,
          addScreenshotBtn,
        ],
      ),
      errorContainer,
      submitBtn,
    ],
  );

  rootCol.children.add(
    UI.container(
      name: 'BugReportScrollContainer',
      padding: UIEdgeInsets.all(24),
      child: formCol,
    ),
  );
}

void _wireBugReportTextFields(FFProject project) {
  final wc = findPage(project, name: 'BugReportPage');
  if (wc == null) return;

  void _bind(String fieldName, String stateField) {
    final tf = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (tf == null) return;
    final stateId = _findPageStateFieldId(project, 'BugReportPage', stateField);
    if (stateId == null) return;

    tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
    tf.props.textField.localStateValue = true;
    tf.props.textField.initialText = FFText(
      textValue: FFStringValue(
        variable: varFromPageState(stateId.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      ),
    );

    // Expliciete ON_TEXTFIELD_CHANGE trigger die de page state direct
    // bijwerkt met TEXT_VALUE. localStateValue alleen is niet altijd
    // genoeg om de state up-to-date te houden vóór een knop-tap.
    tf.triggerActions.removeWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TEXTFIELD_CHANGE,
    );

    final setStateAction = FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
        updates: [
          FFLocalStateFieldUpdate(
            fieldIdentifier: stateId.deepCopy(),
            setValue: FFValue(variable: varFromTextFieldValue(tf.key)),
          ),
        ],
      ),
    );

    tf.triggerActions.add(
      FFTriggerActions(
        trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TEXTFIELD_CHANGE),
        rootAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: setStateAction,
        ),
      ),
    );
  }

  _bind('BugTitleField',       'title');
  _bind('BugDescriptionField', 'description');
}

// Koppelt de Voeg-screenshot-knop en de Verstuur-knop aan hun custom actions.
void _wireBugReportSubmit(FFProject project) {
  final wc = findPage(project, name: 'BugReportPage');
  if (wc == null) return;

  final addBtn = findDescendants(wc.node, (n) => n.name == 'BugAddScreenshotButton').firstOrNull;
  final submitBtn = findDescendants(wc.node, (n) => n.name == 'BugReportSubmitButton').firstOrNull;

  final scaffoldKey = wc.node.key;

  // ── Add screenshot button ──────────────────────────────────────────────────
  final pickAction = findCustomAction(project, name: 'PickBugScreenshot');
  if (addBtn != null && pickAction != null) {
    addBtn.triggerActions.removeWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    const _kPickOutput = 'pickedCount';
    final pickCall = FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: pickAction.identifier.deepCopy(),
      ),
    );
    pickCall.outputVariableName = _kPickOutput;

    final countId = _findPageStateFieldId(project, 'BugReportPage', 'screenshotCount');
    final pickedVar = varFromActionOutput(actionKey: pickCall.key, outputName: _kPickOutput)
      ..nodeKeyRef = FFNodeKeyReference(key: addBtn.key);

    final setStateNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: countId != null
          ? FFAction(
              key: generateRandomAlphaNumericString(),
              localStateUpdate: FFLocalStateUpdate(
                stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
                updates: [
                  FFLocalStateFieldUpdate(
                    fieldIdentifier: countId.deepCopy(),
                    setValue: FFValue(variable: pickedVar),
                  ),
                ],
              ),
            )
          : Actions.snackBar(''),
    );

    Actions.addTriggerChain(addBtn, FFActionTriggerType.ON_TAP, FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: pickCall,
      followUpAction: setStateNode,
    ));
  }

  // ── Submit button ─────────────────────────────────────────────────────────
  final submitAction = findCustomAction(project, name: 'SubmitBugReport');
  final clearAction  = findCustomAction(project, name: 'ClearBugScreenshots');
  if (submitBtn == null || submitAction == null) return;

  submitBtn.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  FFVariable _stateVar(String name) {
    final id = _findPageStateFieldId(project, 'BugReportPage', name);
    if (id == null) return varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING);
    return varFromPageState(id.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
  }

  // Submit args — keys moeten matchen met die in _ensureBugReportCustomAction.
  const _kTitleKey = 'bug_title_arg';
  const _kDescKey  = 'bug_desc_arg';
  final args = FFFunctionCallValues();
  args.arguments[_kTitleKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: _stateVar('title')),
  );
  args.arguments[_kDescKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: _stateVar('description')),
  );

  final submitCall = FFAction(
    key: generateRandomAlphaNumericString(),
    customAction: FFCustomActionCall(
      customActionIdentifier: submitAction.identifier.deepCopy(),
      argumentValues: args,
    ),
  );
  submitCall.outputVariableName = 'bugSubmitResult';

  final successVar = varFromActionOutput(
    actionKey: submitCall.key,
    outputName: 'bugSubmitResult',
  )..nodeKeyRef = FFNodeKeyReference(key: submitBtn.key);

  // Bij succes: navigate terug naar ProfielPage; bij failure: snackbar zit in action.
  final onSuccessChain = Actions.chain([
    Actions.navigate(project, pageName: 'ProfielPage'),
  ]);

  final submitNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: submitCall,
    followUpAction: Actions.conditional(
      condition: successVar,
      trueActions: onSuccessChain,
    ),
  );

  Actions.addTriggerChain(submitBtn, FFActionTriggerType.ON_TAP, submitNode);
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
                          'senderId': AppState(ff.AppState.userEmail),
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

void _addChatButton(App app) {
  app.editPage(ff.Pages.wedstrijdenPage, (page) {
    page.ensureInsertedBefore(
      page.findByKey('ConditionalBuilder_f1ph1tgg'),
      Button(
        'Chat',
        name: 'OpenTeamChatButton',
        icon: 'chat',
        width: double.infinity,
        color: Colors.secondary,
        textColor: Colors.primaryBackground,
        borderRadius: 8,
        onTap: [Navigate(ff.Pages.chatsPage)],
      ),
    );
  });
}

// ─── Direct-chat member strip ──────────────────────────────────────────────────

// Inserts a horizontal scroll of team members at the top of TeamChatPage.
// Tapping a member navigates to DirectChatPage with memberId + memberName.
// Uses app.raw() because State('teamMembers') cannot be resolved by app.editPage()
// at _compilePages time — teamMembers is only added by editPageState (rawMutations,
// which runs AFTER _compilePages). Both run in rawMutations in registration order,
// so the field is present when _addDirectChatMemberStripRaw executes.
void _addDirectChatMemberStrip(App app, StructHandle swapMember) {
  app.raw((project) => _addDirectChatMemberStripRaw(project));
}

void _addDirectChatMemberStripRaw(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  // Idempotent: skip if the strip is already present.
  if (findDescendants(wc.node, (n) => n.name == 'DirectChatMemberStrip').isNotEmpty) return;

  // teamMembers must have been added by editPageState earlier in rawMutations.
  final teamMembersId = _findPageStateFieldId(project, 'TeamChatPage', 'teamMembers');
  if (teamMembersId == null) return;

  // nodeKeyRef = page scaffold key is required for LOCAL_STATE variable resolution.
  final teamMembersVar = varFromPageState(teamMembersId.deepCopy());
  teamMembersVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  // ListView with DynamicSource — each item is one SwapMember from teamMembers state.
  final memberList = UI.listView(
    name: 'MemberStripList',
    horizontal: true,
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(
      variable: teamMembersVar,
      itemName: 'member',
    ),
  );

  // Navigate to DirectChatPage met member's externalId (lidnummer) + naam.
  // Lidnummer is altijd uniek en stabiel — zo berekenen verzender én ontvanger
  // dezelfde conversationId via ComputeDirectConvId, ongeacht email-verschillen
  // tussen Sportlink-data en app-login.
  final navigateAction = Actions.navigate(
    project,
    pageName: 'DirectChatPage',
    params: {
      'memberId':   VariableParamValue(generatorVarField(memberList.key, 'externalId')),
      'memberName': VariableParamValue(generatorVarField(memberList.key, 'name')),
    },
  );

  // Bouw chain: ComputeDirectConvId(member.externalId) → Navigate.
  // Wrapped in If(hasAppAccount == true): leden zonder app-account krijgen
  // een snackbar i.p.v. een lege convId waardoor iedereen elkaars fallback
  // ziet. We gebruiken externalId (lidnummer) i.p.v. email omdat het lidnummer
  // altijd uniek is, ook als de Sportlink-email afwijkt van de app-login email.
  final hasAccountVar = conditionVar(
    generatorVarField(memberList.key, 'hasAppAccount'),
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;

  FFActionNode buildTapChain() {
    final compute = findCustomAction(project, name: 'ComputeDirectConvId');
    final navigateNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: navigateAction,
    );
    if (compute == null) return navigateNode;
    final computeArgs = FFFunctionCallValues();
    // Map ComputeDirectConvId's 'other' param to member.externalId (lidnummer).
    for (final arg in compute.arguments) {
      if (arg.identifier.name == 'other') {
        computeArgs.arguments[arg.identifier.key] =
            FFFunctionCallValues_FFArgument(value: FFValue(
              variable: generatorVarField(memberList.key, 'externalId'),
            ));
      }
    }
    return FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: compute.identifier.deepCopy(),
          argumentValues: computeArgs,
        ),
      ),
      followUpAction: navigateNode,
    );
  }
  final tapChain = Actions.conditional(
    condition: hasAccountVar,
    trueActions: buildTapChain(),
    falseActions: Actions.chain([
      Actions.snackBar('Dit lid heeft de app nog niet geactiveerd — chatten lukt pas als ze ingelogd zijn.'),
    ]),
  );

  // Member name text bound to generator variable 'name' field.
  final nameText = UI.text('', name: 'MemberChipName', style: UITextStyle.bodySmall);
  nameText.props.text.textValue =
      FFStringValue(variable: generatorVarField(memberList.key, 'name'));

  // Avatar placeholder (40×40 circle).
  final avatar = UI.container(name: 'MemberAvatar', width: 40, height: 40, borderRadius: 20);

  // "Nog niet online" indicator — zichtbaar wanneer member.hasAppAccount false is.
  final noAccountVar = conditionVar(
    generatorVarField(memberList.key, 'hasAppAccount'),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;
  final offlineLabel = UI.text(
    'Nog niet online',
    name: 'MemberChipOfflineLabel',
    style: UITextStyle.labelSmall,
  );
  final offlineCopy = offlineLabel.props.text.deepCopy();
  offlineCopy.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
  );
  offlineCopy.italicValue = FFBooleanValue(inputValue: true);
  offlineLabel.props.text = offlineCopy;
  setConditionalVisibility(offlineLabel, variable: noAccountVar);

  // Column: avatar above name + optional offline label.
  final chipCol = UI.column(
    name: 'MemberChipColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    children: [avatar, nameText, offlineLabel],
  );

  // Chip container (60px wide) — tap eerst ComputeDirectConvId, dan Navigate.
  final chip = UI.container(name: 'MemberChip', width: 60, borderRadius: 8, child: chipCol);
  Actions.onTapChain(chip, tapChain);

  memberList.children.add(chip);

  // Outer 88px-tall strip container.
  final strip = UI.container(name: 'DirectChatMemberStrip', height: 88, child: memberList);

  insertBeforeKey(wc.node, 'ListView_9sebksf4', strip);
}

// Sets the red badge on the ChatsPage NavBar icon when hasUnreadTeamChat is true.

// Idempotent: badge is overwritten each push.
void _wireChatBadge(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final hasUnreadId = _findAppStateFieldId(project, 'hasUnreadTeamChat');
  if (hasUnreadId == null) return;
  final showVar = varFromAppState(hasUnreadId.deepCopy());
  wc.node.props.badge = FFBadge(
    showBadgeValue: FFBooleanValue(variable: showVar),
    colorValue: FFColorValue(
      inputValue: FFColor(themeColor: FFColor_ThemeColor.ERROR),
    ),
  );
}

// Wires GetTeamMembers API call on TeamChatPage load to populate the member strip.
// Uses onPageLoadChain so the existing Firestore stream triggers are preserved.
void _wireTeamMembersLoad(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;
  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdId == null) return;

  // Idempotent: skip if the GetTeamMembers call is already wired.
  final alreadyWired = wc.node.triggerActions.any((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) return false;
    // Walk the action chain looking for a GetTeamMembers API call.
    bool check(FFActionNode node) {
      if (node.hasAction() &&
          node.action.hasDatabase() &&
          node.action.database.hasApiCall() &&
          node.action.database.apiCall.hasEndpointIdentifier() &&
          node.action.database.apiCall.endpointIdentifier.name == 'GetTeamMembers') {
        return true;
      }
      if (node.hasFollowUpAction() && check(node.followUpAction)) return true;
      return false;
    }
    return t.hasRootAction() && check(t.rootAction);
  });
  if (alreadyWired) return;

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetTeamMembers',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'teamId': varFromAppState(currentTeamIdId.deepCopy()),
      },
      outputVariableName: 'chatMembersLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'TeamChatPage',
          updates: [
            StateFieldUpdate.setFromVariable('teamMembers', ctx.responseVar),
          ],
        ),
      ]),
    ),
  );
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

// [collectionName]: if provided, only patch queries targeting that collection.
void _applyFilterToActionChain(
  FFActionNode node,
  FFFirestoreWhere whereFilter, {
  String? collectionName,
}) {
  // Patch this node if it is a Firestore query without a filter (and matches the target collection).
  if (node.hasAction() &&
      node.action.hasDatabase() &&
      node.action.database.hasFirestoreQuery()) {
    final query = node.action.database.firestoreQuery;
    if (!query.hasWhere() &&
        (collectionName == null || query.collectionIdentifier.name == collectionName)) {
      query.where = whereFilter.deepCopy();
    }
  }

  // Recurse into all branches.
  if (node.hasConditionActions()) {
    for (final branch in node.conditionActions.trueActions) {
      if (branch.hasTrueAction()) {
        _applyFilterToActionChain(branch.trueAction, whereFilter, collectionName: collectionName);
      }
    }
    if (node.conditionActions.hasFalseAction()) {
      _applyFilterToActionChain(node.conditionActions.falseAction, whereFilter, collectionName: collectionName);
    }
  }
  if (node.hasLoopAction() && node.loopAction.hasAction()) {
    _applyFilterToActionChain(node.loopAction.action, whereFilter, collectionName: collectionName);
  }
  if (node.hasParallelActions()) {
    for (final branch in node.parallelActions.actions) {
      _applyFilterToActionChain(branch, whereFilter, collectionName: collectionName);
    }
  }
  if (node.hasFollowUpAction()) {
    _applyFilterToActionChain(node.followUpAction, whereFilter, collectionName: collectionName);
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
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';

Future<String> verifyMagicLink(String? token) async {
  if (token == null || token.isEmpty) return '';
  try {
    final response = await http.post(
      Uri.parse('https://voetbalplanner.nubix.nl/api/v1/auth/verify-magic-link'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'token': token}),
    ).timeout(const Duration(seconds: 20));
    if (response.statusCode != 200) return '';
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (data['success'] != true) return '';
    final responseData = (data['data'] as Map<String, dynamic>?) ?? {};
    final sanctumToken = (responseData['token'] as String?) ?? '';
    if (sanctumToken.isEmpty) return '';
    final user = (responseData['user'] as Map<String, dynamic>?) ?? {};
    final club = (user['club'] as Map<String, dynamic>?) ?? {};
    FFAppState().update(() {
      final _uName = (user['name'] as String?) ?? '';
      final _uEmail = (user['email'] as String?) ?? '';
      FFAppState().userName        = _uName;
      FFAppState().userEmail       = _uEmail.isNotEmpty ? _uEmail : _uName;
      FFAppState().clubName        = (club['name']   as String?) ?? '';
      FFAppState().currentTeamId   = (user['team_id']   as String?) ?? '';
      FFAppState().currentTeamName = (user['team_name'] as String?) ?? '';
      var _teams = ((user['teams'] as List?) ?? const [])
          .map<TeamOptionStruct?>((t) => TeamOptionStruct.maybeFromMap(t))
          .where((t) => t != null)
          .cast<TeamOptionStruct>()
          .toList();
      // Fallback (oude backend zonder teams[]): toon tenminste het huidige team.
      if (_teams.isEmpty) {
        final _tid = (user['team_id'] as String?) ?? '';
        if (_tid.isNotEmpty) {
          final _fb = TeamOptionStruct.maybeFromMap(
              {'id': _tid, 'name': (user['team_name'] as String?) ?? ''});
          if (_fb != null) _teams = [_fb];
        }
      }
      FFAppState().availableTeams = _teams;
      // Zet ook meteen de switcher-vlag; anders bleef de team-switcher op het
      // dashboard verborgen tot RefreshCurrentTeam (ProfielPage) had gedraaid.
      FFAppState().hasMultipleTeams = _teams.length > 1;
      FFAppState().primaryColor    = (club['primary_color']   as String?) ?? '#1e3a5f';
      FFAppState().secondaryColor  = (club['secondary_color'] as String?) ?? '#3b82f6';
      FFAppState().accentColor     = (club['accent_color']    as String?) ?? '#10b981';
      FFAppState().clubLogoUrl     = (club['logo_url']        as String?) ?? '';
      FFAppState().relatiecode     = (user['relatiecode']     as String?) ?? '';
      FFAppState().profilePhotoUrl = (user['profile_photo_url'] as String?) ?? '';
    });
    // Register this user so the direct-chat member strip shows only app users.
    // Fire-and-forget: previously this was awaited, but Firestore can hang
    // (especially right after a user switch when the SDK rebuilds its auth
    // context). Awaiting blocked verifyMagicLink from returning the token, so
    // the typed DSL chain never navigated away from MagicLinkVerifyPage.
    final _uId = FFAppState().userEmail.isNotEmpty
        ? FFAppState().userEmail
        : FFAppState().userName;
    if (_uId.isNotEmpty && FFAppState().currentTeamId.isNotEmpty) {
      FirebaseFirestore.instance
          .collection('appUsers')
          .doc('${FFAppState().currentTeamId}_$_uId')
          .set({
        'userId':    _uId,
        'teamId':    FFAppState().currentTeamId,
        'userName':  FFAppState().userName,
        'updatedAt': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true)).catchError((Object _) {});
    }

    // Sign in anonymously so FlutterFlow's Firebase Auth route guard (loggedIn)
    // passes. Zonder dit weigert de router de navigatie naar de NavBar-pagina's
    // en blijft de app hangen op de "Inloglink verifiëren..."-pagina.
    try {
      await FirebaseAuth.instance.signInAnonymously();
      await FirebaseAuth.instance
          .authStateChanges()
          .firstWhere((u) => u != null)
          .timeout(const Duration(seconds: 3), onTimeout: () => null);
    } catch (_) {}

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
import 'package:cloud_firestore/cloud_firestore.dart';

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
      // Parse de JSON-foutmelding van Laravel en toon die leesbaar.
      try {
        final errorBody = jsonDecode(response.body) as Map<String, dynamic>?;
        final msg = (errorBody?['message'] as String?)?.trim() ?? '';
        showError(msg.isNotEmpty ? msg : 'Inloggen mislukt. Controleer uw gegevens.');
      } catch (_) {
        showError('Inloggen mislukt. Controleer uw gegevens.');
      }
      return false;
    }

    final body = jsonDecode(response.body) as Map<String, dynamic>?;
    if (body == null || body['success'] != true) {
      showError('Inloggen mislukt. Probeer het opnieuw.');
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
      final _uName = (user['name'] as String?) ?? '';
      final _uEmail = (user['email'] as String?) ?? '';
      FFAppState().userName        = _uName;
      FFAppState().userEmail       = _uEmail.isNotEmpty ? _uEmail : _uName;
      FFAppState().clubName        = (club['name'] as String?) ?? '';
      FFAppState().currentTeamId   = firstTeamId;
      FFAppState().currentTeamName = (user['team_name'] as String?) ?? '';
      var _teams = ((user['teams'] as List?) ?? const [])
          .map<TeamOptionStruct?>((t) => TeamOptionStruct.maybeFromMap(t))
          .where((t) => t != null)
          .cast<TeamOptionStruct>()
          .toList();
      // Fallback (oude backend zonder teams[]): toon tenminste het huidige team.
      if (_teams.isEmpty && firstTeamId.isNotEmpty) {
        final _fb = TeamOptionStruct.maybeFromMap(
            {'id': firstTeamId, 'name': (user['team_name'] as String?) ?? ''});
        if (_fb != null) _teams = [_fb];
      }
      FFAppState().availableTeams = _teams;
      // Zet ook meteen de switcher-vlag; anders bleef de team-switcher op het
      // dashboard verborgen tot RefreshCurrentTeam (ProfielPage) had gedraaid.
      FFAppState().hasMultipleTeams = _teams.length > 1;
      FFAppState().primaryColor    = (club['primary_color']   as String?) ?? '#1e3a5f';
      FFAppState().secondaryColor  = (club['secondary_color'] as String?) ?? '#3b82f6';
      FFAppState().accentColor     = (club['accent_color']    as String?) ?? '#10b981';
      FFAppState().clubLogoUrl     = (club['logo_url']        as String?) ?? '';
      FFAppState().relatiecode     = (user['relatiecode']     as String?) ?? '';
      FFAppState().profilePhotoUrl = (user['profile_photo_url'] as String?) ?? '';
    });

    // Register this user so the direct-chat member strip shows only app users.
    final _uId = FFAppState().userEmail.isNotEmpty
        ? FFAppState().userEmail
        : FFAppState().userName;
    if (_uId.isNotEmpty && firstTeamId.isNotEmpty) {
      try {
        await FirebaseFirestore.instance
            .collection('appUsers')
            .doc('${firstTeamId}_$_uId')
            .set({
          'userId':    _uId,
          'teamId':    firstTeamId,
          'userName':  FFAppState().userName,
          'updatedAt': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
      } catch (_) {}
    }

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
  _ensureAppStateField(project, 'clubLogoUrl',     FFBaseDataType.String, persisted: true);
  _ensureAppStateField(project, 'currentTeamName', FFBaseDataType.String, persisted: true);

  // Ensure user-identity fields survive app restarts.
  for (final field in ['authToken', 'userName', 'userEmail', 'clubName', 'currentTeamName']) {
    _makeAppStateFieldPersisted(project, field);
  }

  // Nieuw: relatiecode (lidnummer) en profielfoto-URL.
  _ensureAppStateField(project, 'relatiecode',      FFBaseDataType.String, persisted: true);
  _ensureAppStateField(project, 'profilePhotoUrl',  FFBaseDataType.String, persisted: true);

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

  // The custom action returns bool (true = success). Store the return value so
  // we can condition the navigation on it — only navigate when login succeeded.
  const _loginOutputName = 'loginResult';

  final customAction = FFAction(
    key: actionKey,
    customAction: FFCustomActionCall(
      customActionIdentifier: loginAction.identifier.deepCopy(),
      argumentValues: argValues,
    ),
  );
  customAction.outputVariableName = _loginOutputName;

  final loginSuccessVar = varFromActionOutput(
    actionKey: actionKey,
    outputName: _loginOutputName,
  )..nodeKeyRef = FFNodeKeyReference(key: loginButton.key);

  // Only navigate when the action returned true.
  final navigateNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: Actions.navigate(project, pageName: 'WedstrijdenPage', replaceRoute: true),
  );

  final conditionalNode = Actions.conditional(
    condition: loginSuccessVar,
    trueActions: navigateNode,
  );

  final customActionNode = FFActionNode(
    key: actionNodeKey,
    action: customAction,
    followUpAction: conditionalNode,
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
    ('BarDuty',      [
      ('isAssignedToMe', FFBaseDataType.Boolean),
      ('memberCount',    FFBaseDataType.Integer),
      ('requiredCount',  FFBaseDataType.Integer),
      ('canSelfAssign',  FFBaseDataType.Boolean),
    ]),
    ('FootMatch',    [
      ('isFruitHero',  FFBaseDataType.Boolean),
      ('isDriver',     FFBaseDataType.Boolean),
      ('fruitHeroId',  FFBaseDataType.String),
      ('driverNames',  FFBaseDataType.String),
      ('opponentLogo', FFBaseDataType.String),
    ]),
    ('TeamOption',   [
      ('role', FFBaseDataType.String),
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
    name:                     'GetStaffGroupMembers',
    url:                      '/staff-groups/[staffGroupId]/members-full',
    variables:                {'staffGroupId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
    responseDataStructName:   'SwapMember',
    responseDataStructIsList: true,
  );

  addIfMissing(
    name:                     'GetSwapRequests',
    url:                      '/swap-requests/incoming',
    responseDataStructName:   'SwapRequest',
    responseDataStructIsList: true,
  );

  // Variabelen via de QUERY, niet via een JSON-body: FlutterFlow's codegen
  // interpoleert body-variabelen NIET ([var] blijft letterlijk in de body
  // staan), terwijl URL/query-variabelen wél worden ingevuld. Laravel's
  // validate()/input() leest query + body samen, dus de backend hoeft niet
  // te wijzigen.
  const createSwapUrl =
      '/swap-requests?type=[type]&target_id=[target_id]&requestee_id=[requestee_id]';
  addIfMissing(
    name:     'CreateSwapRequest',
    url:      createSwapUrl,
    method:   FFApiEndpoint_CallType.POST,
    bodyType: FFApiEndpoint_BodyType.NONE,
    variables: {
      'type':         FFDataTypeV2(scalarType: FFBaseDataType.String),
      'target_id':    FFDataTypeV2(scalarType: FFBaseDataType.String),
      'requestee_id': FFDataTypeV2(scalarType: FFBaseDataType.String),
    },
  );
  // Reeds-gepusht endpoint (addIfMissing sloeg over) forceren naar query-vorm.
  if (existing.contains('CreateSwapRequest')) {
    updateApiEndpoint(
      project,
      name:      'CreateSwapRequest',
      groupName: 'VoetbalPlannerAPI',
      url:       createSwapUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      body:      '',
    );
  }

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
    name:      'SelfAssignBarDuty',
    url:       '/bar-duties/[dutyId]/self-assign',
    method:    FFApiEndpoint_CallType.POST,
    variables: {'dutyId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
  );
  // Idempotent: als endpoint al bestond met method PATCH (vorige push) →
  // force-update naar POST.
  if (existing.contains('SelfAssignBarDuty')) {
    updateApiEndpoint(
      project,
      name:      'SelfAssignBarDuty',
      groupName: 'VoetbalPlannerAPI',
      method:    FFApiEndpoint_CallType.POST,
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

// ─── Guardian / ouder-verzorger endpoints ────────────────────────────────────
//
// Voegt DataStructs + API-endpoints toe voor de ouder/verzorger-koppeling:
//   GuardianRequest      — openstaand verzoek dat een kind/lid ziet
//   GuardianChild        — goedgekeurd gekoppeld kind voor een ouder
//   GuardianLinkSummary  — verzoekhistorie voor de ouder
//   GuardianRequestResult— response op het indienen van een verzoek
//
// Alle aanroepen zijn idempotent: structs en endpoints worden alleen
// aangemaakt als ze nog niet bestaan.
void _addGuardianEndpoints(FFProject project) {
  const groupName = 'VoetbalPlannerAPI';

  // ── 1. DataStructs ─────────────────────────────────────────────────────────

  // Openstaand koppelverzoek dat een kind/lid te zien krijgt (GET /guardian/pending)
  if (findDataStruct(project, name: 'GuardianRequest') == null) {
    addDataStruct(
      project,
      name: 'GuardianRequest',
      description: 'Openstaand ouder/verzorger koppelverzoek gericht aan dit lid.',
      fields: [
        structField('id',            stringType, description: 'Link ID'),
        structField('guardianName',  stringType, description: 'Naam van de ouder/verzorger'),
        structField('guardianEmail', stringType, description: 'E-mailadres van de ouder/verzorger'),
        structField('requestedAt',   stringType, description: 'Tijdstip van aanvraag (ISO 8601)'),
        structField('expiresAt',     stringType, description: 'Vervaldatum van het verzoek (ISO 8601)'),
      ],
    );
  }

  // Goedgekeurd gekoppeld kind voor een ouder (GET /guardian/children)
  if (findDataStruct(project, name: 'GuardianChild') == null) {
    addDataStruct(
      project,
      name: 'GuardianChild',
      description: 'Goedgekeurd gekoppeld kind/lid van een ouder/verzorger.',
      fields: [
        structField('linkId',      stringType, description: 'Koppeling ID'),
        structField('memberId',    stringType, description: 'Lid ID'),
        structField('name',        stringType, description: 'Volledige naam'),
        structField('email',       stringType, description: 'E-mailadres'),
        structField('externalId',  stringType, description: 'Lidnummer (relatiecode)'),
        structField('dateOfBirth', stringType, description: 'Geboortedatum (Y-m-d)'),
        structField('approvedAt',  stringType, description: 'Datum goedkeuring (ISO 8601)'),
      ],
    );
  }

  // Verzoekhistorie voor de ouder/verzorger (GET /guardian/my-requests)
  if (findDataStruct(project, name: 'GuardianLinkSummary') == null) {
    addDataStruct(
      project,
      name: 'GuardianLinkSummary',
      description: 'Overzicht van een ouder/verzorger koppelverzoek met alle statusinformatie.',
      fields: [
        structField('id',             stringType, description: 'Link ID'),
        structField('status',         stringType, description: 'Status: pending|approved|rejected|revoked'),
        structField('statusLabel',    stringType, description: 'Nederlandse statuslabel'),
        structField('guardianName',   stringType, description: 'Naam ouder/verzorger'),
        structField('guardianEmail',  stringType, description: 'E-mail ouder/verzorger'),
        structField('childName',      stringType, description: 'Naam kind/lid'),
        structField('childEmail',     stringType, description: 'E-mail kind/lid'),
        structField('childExternalId',stringType, description: 'Lidnummer kind'),
        structField('requestedAt',    stringType, description: 'Aanvraagtijdstip (ISO 8601)'),
        structField('expiresAt',      stringType, description: 'Vervaldatum (ISO 8601)'),
        structField('resolvedAt',     stringType, description: 'Beslissingstijdstip (ISO 8601)'),
        structField('revokedAt',      stringType, description: 'Intrekkingstijdstip (ISO 8601)'),
      ],
    );
  }

  // Resultaat bij het indienen van een verzoek (POST /guardian/request response.data)
  if (findDataStruct(project, name: 'GuardianRequestResult') == null) {
    addDataStruct(
      project,
      name: 'GuardianRequestResult',
      description: 'Bevestiging na het indienen van een ouder/verzorger koppelverzoek.',
      fields: [
        structField('id',        stringType, description: 'Nieuw aangemaakte link ID'),
        structField('status',    stringType, description: 'Status: altijd pending'),
        structField('childName', stringType, description: 'Naam van het gevonden kind/lid'),
        structField('expiresAt', stringType, description: 'Vervaldatum (ISO 8601)'),
      ],
    );
  }

  // ── 2. Bestaande endpoints bijhouden (idempotent guard) ────────────────────

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
      groupName:                groupName,
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

  // ── 3. API-endpoints ───────────────────────────────────────────────────────

  // POST /guardian/request — Ouder dient koppelverzoek in voor extra kind
  // (alleen lidnummer + achternaam — kind moet bevestigen in app)
  // Variabelen via de QUERY (zie uitleg bij CreateSwapRequest): FF interpoleert
  // body-variabelen niet, URL/query-variabelen wél. Laravel validate() leest
  // query + body samen.
  const requestUrl = '/guardian/request?lidnummer=[lidnummer]&achternaam=[achternaam]';
  if (existing.contains('RequestGuardianAccess')) {
    updateApiEndpoint(
      project,
      name:      'RequestGuardianAccess',
      groupName: groupName,
      url:       requestUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      body:      '',
    );
  } else {
    addEndpointToGroup(
      project,
      groupName: groupName,
      name:      'RequestGuardianAccess',
      url:       requestUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      variables: {
        'lidnummer':  FFDataTypeV2(scalarType: FFBaseDataType.String),
        'achternaam': FFDataTypeV2(scalarType: FFBaseDataType.String),
      },
      headers:                ['Authorization: Bearer [bearerToken]'],
      responseDataStructName: 'GuardianRequestResult',
    );
  }

  // GET /guardian/pending — Kind haalt openstaande verzoeken op
  addIfMissing(
    name:                     'GetPendingGuardianRequests',
    url:                      '/guardian/pending',
    responseDataStructName:   'GuardianRequest',
    responseDataStructIsList: true,
  );

  // POST /guardian/{linkId}/respond?action=... — Kind accepteert of weigert.
  // action via query (body-variabelen worden niet geïnterpoleerd).
  const respondUrl = '/guardian/[linkId]/respond?action=[action]';
  addIfMissing(
    name:     'RespondGuardianRequest',
    url:      respondUrl,
    method:   FFApiEndpoint_CallType.POST,
    bodyType: FFApiEndpoint_BodyType.NONE,
    variables: {
      'linkId': FFDataTypeV2(scalarType: FFBaseDataType.String),
      'action': FFDataTypeV2(scalarType: FFBaseDataType.String),
    },
  );
  if (existing.contains('RespondGuardianRequest')) {
    updateApiEndpoint(
      project,
      name:      'RespondGuardianRequest',
      groupName: groupName,
      url:       respondUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      body:      '',
    );
  }

  // DELETE /guardian/{linkId}/revoke — Koppeling intrekken
  addIfMissing(
    name:      'RevokeGuardianLink',
    url:       '/guardian/[linkId]/revoke',
    method:    FFApiEndpoint_CallType.DELETE,
    variables: {'linkId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
  );

  // GET /guardian/children — Ouder haalt zijn/haar gekoppelde kinderen op
  addIfMissing(
    name:                     'GetGuardianChildren',
    url:                      '/guardian/children',
    responseDataStructName:   'GuardianChild',
    responseDataStructIsList: true,
  );

  // GET /guardian/my-requests — Ouder ziet eigen verzoekhistorie
  addIfMissing(
    name:                     'GetMyGuardianRequests',
    url:                      '/guardian/my-requests',
    responseDataStructName:   'GuardianLinkSummary',
    responseDataStructIsList: true,
  );

  // GET /guardian/members/{memberId}/data — Ouder bekijkt kindgegevens
  addIfMissing(
    name:      'GetChildMemberData',
    url:       '/guardian/members/[memberId]/data',
    variables: {'memberId': FFDataTypeV2(scalarType: FFBaseDataType.String)},
  );

  // POST /guardian/self-register — Ouder registreert zichzelf (publiek, geen auth)
  // Existing endpoint: replace body + variables to drop geboortedatum.
  const selfRegisterUrl =
      '/guardian/self-register?naam=[naam]&email=[email]&lidnummer=[lidnummer]&achternaam=[achternaam]';
  if (existing.contains('SelfRegisterGuardian')) {
    updateApiEndpoint(
      project,
      name:      'SelfRegisterGuardian',
      groupName: groupName,
      url:       selfRegisterUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      body:      '',
    );
  } else {
    addEndpointToGroup(
      project,
      groupName: groupName,
      name:      'SelfRegisterGuardian',
      url:       selfRegisterUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      variables: {
        'naam':       FFDataTypeV2(scalarType: FFBaseDataType.String),
        'email':      FFDataTypeV2(scalarType: FFBaseDataType.String),
        'lidnummer':  FFDataTypeV2(scalarType: FFBaseDataType.String),
        'achternaam': FFDataTypeV2(scalarType: FFBaseDataType.String),
      },
    );
  }

  // POST /guardian/create-parent-account?naam=..&email=.. — Lid maakt
  // ouderaccount aan. Variabelen via query (body-vars worden niet ingevuld).
  const createParentUrl = '/guardian/create-parent-account?naam=[naam]&email=[email]';
  addIfMissing(
    name:     'CreateParentAccount',
    url:      createParentUrl,
    method:   FFApiEndpoint_CallType.POST,
    bodyType: FFApiEndpoint_BodyType.NONE,
    variables: {
      'naam':  FFDataTypeV2(scalarType: FFBaseDataType.String),
      'email': FFDataTypeV2(scalarType: FFBaseDataType.String),
    },
  );
  if (existing.contains('CreateParentAccount')) {
    updateApiEndpoint(
      project,
      name:      'CreateParentAccount',
      groupName: groupName,
      url:       createParentUrl,
      method:    FFApiEndpoint_CallType.POST,
      bodyType:  FFApiEndpoint_BodyType.NONE,
      body:      '',
    );
  }

  // PATCH /profile/photo — Profielfoto uploaden (multipart/form-data)
  if (!existing.contains('UpdateProfilePhoto')) {
    addEndpointToGroup(
      project,
      groupName:   groupName,
      name:        'UpdateProfilePhoto',
      url:         '/profile/photo',
      method:      FFApiEndpoint_CallType.PATCH,
      bodyType:    FFApiEndpoint_BodyType.MULTIPART,
      variables:   {},
      headers:     ['Authorization: Bearer [bearerToken]'],
    );
  }

  // POST /profile/delete — Account zelf verwijderen (soft-delete + tokens weg)
  addIfMissing(
    name:   'DeleteAccount',
    url:    '/profile/delete',
    method: FFApiEndpoint_CallType.POST,
  );
}

// ─── Guardian pagina's: DSL-skelet ────────────────────────────────────────────
//
// Declareert state-velden en een minimal body-container voor elke pagina.
// De werkelijke body-inhoud wordt door de _buildGuardian*Body raw-functies
// gebouwd zodat we geen typed ff.Pages.guardianPage.*-handles nodig hebben
// (die bestaan pas na de eerste push).
void _buildGuardianPages(App app) {
  // ── GuardianPage: overzicht koppelingen ──────────────────────────────────
  app.ensurePage(
    'GuardianPage',
    description: 'Overzicht ouder/verzorger koppelingen: mijn kinderen, openstaande verzoeken en verzoekhistorie.',
    route: 'guardian',
    state: {
      'children':     listOf(ff.Structs.guardianChild),
      'pendingForMe': listOf(ff.Structs.guardianRequest),
      'myRequests':   listOf(ff.Structs.guardianLinkSummary),
      'isLoading':    bool_.withDefault(true),
      'isActing':     bool_.withDefault(false),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Ouder / Verzorger'),
      body: Column(
        name: 'GuardianRootColumn',
        children: [
          Container(name: 'GuardianBodyPlaceholder'),
        ],
      ),
    ),
  );

  // ── GuardianSelfRegisterPage: ouder registreert zichzelf via 3-veld check ───
  app.ensurePage(
    'GuardianSelfRegisterPage',
    description: 'Ouder/verzorger registreert zichzelf via lidnummer + achternaam + geboortedatum van het kind.',
    route: 'guardian-self-register',
    state: {
      'isSubmitting': bool_.withDefault(false),
      'errorMessage': string.withDefault(''),
      'naam':         string.withDefault(''),
      'email':        string.withDefault(''),
      'lidnummer':    string.withDefault(''),
      'achternaam':   string.withDefault(''),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Registreren als ouder/verzorger'),
      body: Column(
        name: 'GuardianSelfRegisterRootColumn',
        children: [
          Container(name: 'GuardianSelfRegisterBodyPlaceholder'),
        ],
      ),
    ),
  );

  // ── GuardianCreateParentPage: ouder account aanmaken (door het kind) ─────
  app.ensurePage(
    'GuardianCreateParentPage',
    description: 'Lid maakt een ouder/verzorger account aan. Koppeling is direct actief.',
    route: 'guardian-create-parent',
    state: {
      'isSubmitting': bool_.withDefault(false),
      'errorMessage': string.withDefault(''),
      'naam':         string.withDefault(''),
      'email':        string.withDefault(''),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Ouder account aanmaken'),
      body: Column(
        name: 'GuardianCreateParentRootColumn',
        children: [
          Container(name: 'GuardianCreateParentBodyPlaceholder'),
        ],
      ),
    ),
  );

  // ── GuardianRequestPage: extra kind koppelen (door de ouder) ─────────────
  app.ensurePage(
    'GuardianRequestPage',
    description: 'Ouder koppelt een extra kind/lid aan zijn account.',
    route: 'guardian-request',
    state: {
      'isSubmitting': bool_.withDefault(false),
      'errorMessage': string.withDefault(''),
      'lidnummer':    string.withDefault(''),
      'achternaam':   string.withDefault(''),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Kind koppelen'),
      body: Column(
        name: 'GuardianRequestRootColumn',
        children: [
          Container(name: 'GuardianRequestBodyPlaceholder'),
        ],
      ),
    ),
  );
}

// ─── GuardianPage body ────────────────────────────────────────────────────────
//
// Vervangt de placeholder-container met de werkelijke body:
//   - loading-spinner
//   - sectie "openstaande verzoeken voor mij" (als kind/lid)
//   - sectie "mijn kinderen" (als ouder)
//   - sectie "mijn verzoeken" (als ouder)
//
// Idempotent: slaat over als de body al gebouwd is.
void _buildGuardianPageBody(FFProject project) {
  final wc = findPage(project, name: 'GuardianPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'GuardianLoadingSpinner').isNotEmpty) return;

  final scaffoldKey = wc.node.key;

  // Zoek de root Column en verwijder de placeholder
  final rootCol = findDescendants(wc.node, (n) => n.name == 'GuardianRootColumn').firstOrNull;
  if (rootCol == null) return;
  rootCol.children.removeWhere((n) => n.name == 'GuardianBodyPlaceholder');

  // ── State field IDs ──────────────────────────────────────────────────────
  final isLoadingId     = _findPageStateFieldId(project, 'GuardianPage', 'isLoading');
  final childrenId      = _findPageStateFieldId(project, 'GuardianPage', 'children');
  final pendingForMeId  = _findPageStateFieldId(project, 'GuardianPage', 'pendingForMe');
  final myRequestsId    = _findPageStateFieldId(project, 'GuardianPage', 'myRequests');
  if (isLoadingId == null || childrenId == null || pendingForMeId == null || myRequestsId == null) return;

  FFVariable _stateVar(FFIdentifier id) => varFromPageState(id.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);

  // Struct field helpers (null-safe: falls back to name-only)
  FFVariable _genField(String listKey, String structName, String fieldName) {
    final id = _findStructFieldId(project, structName, fieldName);
    if (id != null) {
      return varFromGeneratorVariable(listKey)
        ..operations.add(FFVariableOperation(
          accessDataStructField: FFAccessDataStructField(fieldIdentifier: id.deepCopy()),
        ));
    }
    return generatorVarField(listKey, fieldName);
  }

  // isLoading condition for visibility
  final isLoadingVar    = _stateVar(isLoadingId);
  final isNotLoadingVar = _stateVar(isLoadingId.deepCopy())
    ..operations.add(FFVariableOperation(negate: FFNegateBoolean()));

  // "isEmpty" heuristic via first-item field check (same pattern as dashboard)
  FFVariable _isEmptyVar(FFIdentifier listStateId, String structName, String fieldName) {
    final firstField = varFromPageState(listStateId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey)
      ..operations.add(FFVariableOperation(
        listItemAtIndex: FFListItemAtIndex(type: FFListItemAtIndex_IndexType.FIRST),
      ))
      ..operations.add(FFVariableOperation(
        accessDataStructField: FFAccessDataStructField(
          fieldIdentifier: _findStructFieldId(project, structName, fieldName)?.deepCopy()
              ?? FFIdentifier(name: fieldName),
        ),
      ));
    return conditionVar(firstField, FFCondition_Relation.EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING)).variable;
  }

  FFVariable _isNotEmptyVar(FFIdentifier listStateId, String structName, String fieldName) {
    return conditionVar(
      varFromPageState(listStateId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey)
        ..operations.add(FFVariableOperation(
          listItemAtIndex: FFListItemAtIndex(type: FFListItemAtIndex_IndexType.FIRST),
        ))
        ..operations.add(FFVariableOperation(
          accessDataStructField: FFAccessDataStructField(
            fieldIdentifier: _findStructFieldId(project, structName, fieldName)?.deepCopy()
                ?? FFIdentifier(name: fieldName),
          ),
        )),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
  }

  // ── Loading spinner ──────────────────────────────────────────────────────
  final loadingSpinner = UI.container(
    name: 'GuardianLoadingSpinner',
    padding: UIEdgeInsets.all(40),
    child: UI.column(
      name: 'GuardianLoadingCol',
      mainAxisAlignment: UIMainAxisAlignment.center,
      children: [UI.progressBar(name: 'GuardianSpinner', shape: UIProgressShape.circular, width: 40, thickness: 4)],
    ),
  );
  setConditionalVisibility(loadingSpinner, variable: isLoadingVar);

  // ── Content column (scrollable) ──────────────────────────────────────────
  final contentCol = UI.column(
    name: 'GuardianContentCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 0,
    children: [],
  );
  setConditionalVisibility(contentCol, variable: isNotLoadingVar);

  // ── 1. Sectie: Openstaande verzoeken voor mij (als kind/lid) ──────────
  // Alleen zichtbaar als pendingForMe niet leeg is
  final pendingListKey = 'PendingForMeListView_g';
  final pendingSource  = _stateVar(pendingForMeId.deepCopy());

  final pendingListView = UI.listView(
    name: 'PendingForMeListView',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 4),
    dynamicSource: DynamicSource(variable: pendingSource, itemName: 'pendingReq'),
  );
  // Set shrinkWrap so it works inside a scrollable Column
  final lvPending = pendingListView.props.listView.deepCopy();
  lvPending.shrinkWrapValue = FFBooleanValue(inputValue: true);
  pendingListView.props.listView = lvPending;

  final pendingItem = UI.container(
    name: 'PendingReqCard',
    padding: UIEdgeInsets.all(16),
    borderRadius: 12,
    color: UIColor.primaryBackground,
    child: UI.column(
      name: 'PendingReqCardCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 8,
      children: [
        UI.text('', name: 'PendingGuardianName', style: UITextStyle.titleSmall),
        UI.text('', name: 'PendingGuardianEmail', style: UITextStyle.bodySmall),
        UI.text('', name: 'PendingExpiresAt', style: UITextStyle.bodySmall),
        UI.row(
          name: 'PendingActionsRow',
          spacing: 8,
          mainAxisAlignment: UIMainAxisAlignment.end,
          children: [
            UI.button('Weigeren',  name: 'WeigerenButton',  width: 100),
            UI.button('Accepteren', name: 'AccepterenButton', width: 110),
          ],
        ),
      ],
    ),
  );

  // Bind text fields to generator variables
  void _bindText(FFNode textNode, String listKey, String structName, String fieldName) {
    final copy = textNode.props.text.deepCopy();
    copy.textValue = FFStringValue(variable: _genField(listKey, structName, fieldName));
    textNode.props.text = copy;
  }

  _bindText(findDescendants(pendingItem, (n) => n.name == 'PendingGuardianName').first,
      pendingListView.key, 'GuardianRequest', 'guardianName');
  _bindText(findDescendants(pendingItem, (n) => n.name == 'PendingGuardianEmail').first,
      pendingListView.key, 'GuardianRequest', 'guardianEmail');
  _bindText(findDescendants(pendingItem, (n) => n.name == 'PendingExpiresAt').first,
      pendingListView.key, 'GuardianRequest', 'expiresAt');

  pendingListView.children.add(pendingItem);

  final pendingSection = UI.container(
    name: 'PendingForMeSection',
    child: UI.column(
      name: 'PendingForMeSectionCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      children: [
        UI.container(
          name: 'PendingHeaderContainer',
          padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: UI.text('Verzoeken aan mij', name: 'PendingHeaderText', style: UITextStyle.titleSmall),
        ),
        pendingListView,
        UI.container(name: 'PendingSectionDivider', padding: UIEdgeInsets.only(bottom: 8)),
      ],
    ),
  );
  setConditionalVisibility(pendingSection,
      variable: _isNotEmptyVar(pendingForMeId, 'GuardianRequest', 'id'));

  // ── 2. Sectie: Mijn kinderen (als ouder/verzorger) ───────────────────
  final childrenSource   = _stateVar(childrenId.deepCopy());

  final childrenListView = UI.listView(
    name: 'GuardianChildrenListView',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 4),
    dynamicSource: DynamicSource(variable: childrenSource, itemName: 'child'),
  );
  final lvChildren = childrenListView.props.listView.deepCopy();
  lvChildren.shrinkWrapValue = FFBooleanValue(inputValue: true);
  childrenListView.props.listView = lvChildren;

  final childItem = UI.container(
    name: 'GuardianChildCard',
    padding: UIEdgeInsets.all(16),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'GuardianChildCardRow',
      mainAxisAlignment: UIMainAxisAlignment.spaceBetween,
      children: [
        UI.column(
          name: 'GuardianChildInfoCol',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 2,
          children: [
            UI.text('', name: 'GuardianChildName',       style: UITextStyle.titleSmall),
            UI.text('', name: 'GuardianChildExternalId', style: UITextStyle.bodySmall),
          ],
        ),
        UI.iconButton('link_off', color: UIColor.error, name: 'IntrekkenButton'),
      ],
    ),
  );

  _bindText(findDescendants(childItem, (n) => n.name == 'GuardianChildName').first,
      childrenListView.key, 'GuardianChild', 'name');
  _bindText(findDescendants(childItem, (n) => n.name == 'GuardianChildExternalId').first,
      childrenListView.key, 'GuardianChild', 'externalId');

  childrenListView.children.add(childItem);

  final childrenEmptyMsg = UI.container(
    name: 'ChildrenEmptyContainer',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 8),
    child: UI.text(
      'Nog geen kinderen gekoppeld. Tik op + om een koppeling aan te vragen.',
      name: 'ChildrenEmptyText',
      style: UITextStyle.bodySmall,
    ),
  );
  setConditionalVisibility(childrenEmptyMsg,
      variable: _isEmptyVar(childrenId, 'GuardianChild', 'linkId'));
  final childrenNonEmptyList = childrenListView;
  setConditionalVisibility(childrenNonEmptyList,
      variable: _isNotEmptyVar(childrenId, 'GuardianChild', 'linkId'));

  // "Ouder aanmaken" — het kind maakt een ouderaccount aan (directe koppeling)
  final addChildBtn = UI.iconButton('person_add', size: 24, color: UIColor.primary, name: 'AddChildButton');
  Actions.addTriggerChain(
    addChildBtn,
    FFActionTriggerType.ON_TAP,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.navigate(project, pageName: 'GuardianCreateParentPage'),
    ),
  );

  // "Kind koppelen" — de ouder koppelt een extra kind via lidnummer
  final linkChildBtn = UI.iconButton('link', size: 24, color: UIColor.secondaryText, name: 'LinkChildButton');
  Actions.addTriggerChain(
    linkChildBtn,
    FFActionTriggerType.ON_TAP,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.navigate(project, pageName: 'GuardianRequestPage'),
    ),
  );

  final childrenSection = UI.container(
    name: 'ChildrenSection',
    child: UI.column(
      name: 'ChildrenSectionCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      children: [
        UI.container(
          name: 'ChildrenHeaderContainer',
          padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: UI.row(
            name: 'ChildrenHeaderRow',
            mainAxisAlignment: UIMainAxisAlignment.spaceBetween,
            children: [
              UI.text('Mijn kinderen', name: 'ChildrenHeaderText', style: UITextStyle.titleSmall),
              linkChildBtn,
              addChildBtn,
            ],
          ),
        ),
        childrenEmptyMsg,
        childrenNonEmptyList,
        UI.container(name: 'ChildrenSectionDivider', padding: UIEdgeInsets.only(bottom: 8)),
      ],
    ),
  );

  // ── 3. Sectie: Mijn verzoeken (als ouder) ────────────────────────────
  final myRequestsSource   = _stateVar(myRequestsId.deepCopy());

  final myRequestsListView = UI.listView(
    name: 'MyGuardianRequestsListView',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 4),
    dynamicSource: DynamicSource(variable: myRequestsSource, itemName: 'req'),
  );
  final lvMyReq = myRequestsListView.props.listView.deepCopy();
  lvMyReq.shrinkWrapValue = FFBooleanValue(inputValue: true);
  myRequestsListView.props.listView = lvMyReq;

  final myReqItem = UI.container(
    name: 'MyGuardianReqCard',
    padding: UIEdgeInsets.all(16),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: UI.column(
      name: 'MyGuardianReqCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 4,
      children: [
        UI.row(
          name: 'MyGuardianReqTitleRow',
          mainAxisAlignment: UIMainAxisAlignment.spaceBetween,
          children: [
            UI.text('', name: 'MyReqChildName',    style: UITextStyle.titleSmall),
            UI.text('', name: 'MyReqStatusLabel',  style: UITextStyle.bodySmall),
          ],
        ),
        UI.text('', name: 'MyReqRequestedAt', style: UITextStyle.bodySmall),
      ],
    ),
  );

  _bindText(findDescendants(myReqItem, (n) => n.name == 'MyReqChildName').first,
      myRequestsListView.key, 'GuardianLinkSummary', 'childName');
  _bindText(findDescendants(myReqItem, (n) => n.name == 'MyReqStatusLabel').first,
      myRequestsListView.key, 'GuardianLinkSummary', 'statusLabel');
  _bindText(findDescendants(myReqItem, (n) => n.name == 'MyReqRequestedAt').first,
      myRequestsListView.key, 'GuardianLinkSummary', 'requestedAt');

  myRequestsListView.children.add(myReqItem);

  final myRequestsSection = UI.container(
    name: 'MyRequestsSection',
    child: UI.column(
      name: 'MyRequestsSectionCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      children: [
        UI.container(
          name: 'MyRequestsHeaderContainer',
          padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: UI.text('Mijn verzoeken', name: 'MyRequestsHeaderText', style: UITextStyle.titleSmall),
        ),
        myRequestsListView,
        UI.container(name: 'MyRequestsSectionDivider', padding: UIEdgeInsets.only(bottom: 16)),
      ],
    ),
  );

  // ── Assemble content column ──────────────────────────────────────────────
  contentCol.children.addAll([pendingSection, childrenSection, myRequestsSection]);

  // ── Replace placeholder with actual body ─────────────────────────────────
  rootCol.children.addAll([loadingSpinner, contentCol]);
}

// ─── GuardianRequestPage body ─────────────────────────────────────────────────
//
// Vervangt de placeholder-container met het formulier voor het aanvragen
// van een ouder/verzorger koppeling.
// Idempotent: slaat over als de body al gebouwd is.
// Zet de foutmelding-tekst op de guardian-/bug-pagina's op wit
// (secondaryBackground), want de container heeft een rode (error) achtergrond.
// Idempotent en werkt óók op reeds gebouwde pagina's (body-builders skippen).
void _fixErrorTextColors(FFProject project) {
  const names = ['GuardianErrorText', 'CreateParentErrorText', 'SelfRegErrorText', 'BugErrorText'];
  final white = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND),
  );
  for (final wc in project.widgetClasses.values.where((w) => w.isPage)) {
    for (final node in findDescendants(wc.node, (n) => names.contains(n.name))) {
      if (!node.props.hasText()) continue;
      final t = node.props.text.deepCopy();
      t.colorValue = white.deepCopy();
      node.props.text = t;
    }
  }
}

void _buildGuardianRequestPageBody(FFProject project) {
  final wc = findPage(project, name: 'GuardianRequestPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'SubmitGuardianButton').isNotEmpty) return;

  final scaffoldKey = wc.node.key;

  final rootCol = findDescendants(wc.node, (n) => n.name == 'GuardianRequestRootColumn').firstOrNull;
  if (rootCol == null) return;
  rootCol.children.removeWhere((n) => n.name == 'GuardianRequestBodyPlaceholder');

  final errorMsgId = _findPageStateFieldId(project, 'GuardianRequestPage', 'errorMessage');

  // Error container: visible when errorMessage != ''
  final errorContainer = UI.container(
    name: 'GuardianErrorContainer',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.error,
    child: UI.text('', name: 'GuardianErrorText', style: UITextStyle.bodySmall, color: UIColor.secondaryBackground),
  );
  if (errorMsgId != null) {
    // Bind errorMessage to text
    final errText = findDescendants(errorContainer, (n) => n.name == 'GuardianErrorText').firstOrNull;
    if (errText != null) {
      final errVar = varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
      final copy = errText.props.text.deepCopy();
      copy.textValue = FFStringValue(variable: errVar.deepCopy());
      errText.props.text = copy;
    }
    // Visibility: visible when errorMessage is not empty
    final isErrorVar = conditionVar(
      varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
    setConditionalVisibility(errorContainer, variable: isErrorVar);
  }

  // Submit button
  final submitBtn = UI.button('Koppeling aanvragen', name: 'SubmitGuardianButton', width: double.infinity);

  // Intro section
  final introIcon = UI.icon('person_add', size: 48, color: UIColor.primary, name: 'GuardianRequestIcon');
  final introTitle = UI.text('Koppeling aanvragen', name: 'GuardianRequestTitle', style: UITextStyle.titleMedium);
  final introText  = UI.text(
    'Vul het lidnummer en de achternaam in van het lid waarmee je wilt koppelen. '
    'Het lid ontvangt een verzoek en moet dit bevestigen.',
    name: 'GuardianRequestIntro',
    style: UITextStyle.bodySmall,
  );

  // Build the scrollable form body
  final formCol = UI.column(
    name: 'GuardianFormColumn',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 16,
    children: [
      // Center the icon
      UI.container(
        name: 'GuardianIconContainer',
        padding: UIEdgeInsets.symmetric(vertical: 16),
        child: UI.column(
          name: 'GuardianIconCol',
          mainAxisAlignment: UIMainAxisAlignment.center,
          children: [introIcon],
        ),
      ),
      introTitle,
      introText,
      UI.container(name: 'GuardianFormDivider', padding: UIEdgeInsets.only(bottom: 4)),
      // Veld: Lidnummer
      UI.container(
        name: 'LidnummerFieldContainer',
        child: UI.column(
          name: 'LidnummerFieldCol',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 4,
          children: [
            UI.text('Lidnummer', name: 'LidnummerLabel', style: UITextStyle.labelMedium),
            UI.textField(hintText: 'bijv. LID-00123', name: 'LidnummerField'),
          ],
        ),
      ),
      // Veld: Achternaam
      UI.container(
        name: 'AchternaamFieldContainer',
        child: UI.column(
          name: 'AchternaamFieldCol',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 4,
          children: [
            UI.text('Achternaam', name: 'AchternaamLabel', style: UITextStyle.labelMedium),
            UI.textField(hintText: 'Achternaam van het lid', name: 'AchternaamField'),
          ],
        ),
      ),
      errorContainer,
      submitBtn,
    ],
  );

  // Wrap in a scrollable container
  final scrollContainer = UI.container(
    name: 'GuardianRequestScrollContainer',
    padding: UIEdgeInsets.all(24),
    child: formCol,
  );

  rootCol.children.add(scrollContainer);
}

// ─── GuardianCreateParentPage body ───────────────────────────────────────────
//
// Formulier waarmee een lid een ouder/verzorger-account aanmaakt.
// Invulvelden: naam + e-mailadres. Na submit wordt de koppeling direct actief.
// Idempotent: slaat over als de body al gebouwd is.
void _buildGuardianCreateParentPageBody(FFProject project) {
  final wc = findPage(project, name: 'GuardianCreateParentPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'SubmitCreateParentButton').isNotEmpty) return;

  final scaffoldKey = wc.node.key;

  final rootCol = findDescendants(wc.node, (n) => n.name == 'GuardianCreateParentRootColumn').firstOrNull;
  if (rootCol == null) return;
  rootCol.children.removeWhere((n) => n.name == 'GuardianCreateParentBodyPlaceholder');

  final errorMsgId = _findPageStateFieldId(project, 'GuardianCreateParentPage', 'errorMessage');

  // Foutcontainer
  final errorContainer = UI.container(
    name: 'CreateParentErrorContainer',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.error,
    child: UI.text('', name: 'CreateParentErrorText', style: UITextStyle.bodySmall, color: UIColor.secondaryBackground),
  );
  if (errorMsgId != null) {
    final errText = findDescendants(errorContainer, (n) => n.name == 'CreateParentErrorText').firstOrNull;
    if (errText != null) {
      final errVar = varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
      final copy = errText.props.text.deepCopy();
      copy.textValue = FFStringValue(variable: errVar.deepCopy());
      errText.props.text = copy;
    }
    final isErrorVar = conditionVar(
      varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
    setConditionalVisibility(errorContainer, variable: isErrorVar);
  }

  final submitBtn = UI.button(
    'Ouder account aanmaken',
    name: 'SubmitCreateParentButton',
    width: double.infinity,
  );

  final formCol = UI.column(
    name: 'CreateParentFormColumn',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 16,
    children: [
      UI.icon('supervisor_account', size: 48, color: UIColor.primary, name: 'CreateParentIcon'),
      UI.text(
        'Maak een ouder/verzorger account aan.',
        name: 'CreateParentTitle',
        style: UITextStyle.titleMedium,
      ),
      UI.text(
        'De ouder ontvangt een e-mail en kan daarna via de magic link inloggen. '
        'De koppeling is direct actief.',
        name: 'CreateParentIntro',
        style: UITextStyle.bodySmall,
      ),
      UI.container(name: 'CreateParentDivider', padding: UIEdgeInsets.only(bottom: 4)),
      // Naam
      UI.column(
        name: 'CreateParentNaamCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 4,
        children: [
          UI.text('Naam ouder/verzorger', name: 'CreateParentNaamLabel', style: UITextStyle.labelMedium),
          UI.textField(hintText: 'Voor- en achternaam', name: 'CreateParentNaamField'),
        ],
      ),
      // E-mail
      UI.column(
        name: 'CreateParentEmailCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 4,
        children: [
          UI.text('E-mailadres', name: 'CreateParentEmailLabel', style: UITextStyle.labelMedium),
          UI.textField(
            hintText: 'ouder@voorbeeld.nl',
            name: 'CreateParentEmailField',
            keyboardType: UIKeyboardType.email,
          ),
        ],
      ),
      errorContainer,
      submitBtn,
    ],
  );

  rootCol.children.add(
    UI.container(
      name: 'CreateParentScrollContainer',
      padding: UIEdgeInsets.all(24),
      child: formCol,
    ),
  );
}

// Bindt TextFields aan page state via localStateValue (zelfde patroon als GuardianRequestPage).
void _wireGuardianCreateParentTextFields(FFProject project) {
  final wc = findPage(project, name: 'GuardianCreateParentPage');
  if (wc == null) return;

  void _bindField(String fieldName, String stateFieldName) {
    final tf = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (tf == null) return;
    final stateId = _findPageStateFieldId(project, 'GuardianCreateParentPage', stateFieldName);
    if (stateId == null) return;
    tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
    tf.props.textField.localStateValue = true;
    tf.props.textField.initialText = FFText(
      textValue: FFStringValue(
        variable: varFromPageState(stateId.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      ),
    );
  }

  _bindField('CreateParentNaamField',  'naam');
  _bindField('CreateParentEmailField', 'email');
}

// Koppelt de SubmitCreateParentButton aan CreateParentAccount API.
void _wireGuardianCreateParentSubmit(FFProject project) {
  final wc = findPage(project, name: 'GuardianCreateParentPage');
  if (wc == null) return;

  final submitBtn = findDescendants(wc.node, (n) => n.name == 'SubmitCreateParentButton').firstOrNull;
  if (submitBtn == null) return;

  submitBtn.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  // Lees waarden rechtstreeks uit de tekstvelden (widget-value).
  FFVariable _fieldVar(String fieldName) {
    final f = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (f == null) return varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING);
    return varFromTextFieldValue(f.key);
  }

  Actions.addTriggerChain(
    submitBtn,
    FFActionTriggerType.ON_TAP,
    Actions.apiCallNode(
      project,
      endpointName:       'CreateParentAccount',
      groupName:          'VoetbalPlannerAPI',
      dynamicVariables: {
        'naam':  _fieldVar('CreateParentNaamField'),
        'email': _fieldVar('CreateParentEmailField'),
      },
      outputVariableName: 'createParentResult',
      nodeKey:            submitBtn.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'GuardianCreateParentPage',
          updates: [StateFieldUpdate.set('errorMessage', '')],
        ),
        Actions.snackBar(
          'Ouder account aangemaakt! De ouder kan nu inloggen via de magic link in de app.',
        ),
        Actions.navigate(project, pageName: 'GuardianPage'),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'GuardianCreateParentPage',
          updates: [StateFieldUpdate.set(
            'errorMessage',
            'Aanmaken mislukt. Controleer de gegevens en probeer opnieuw.',
          )],
        ),
        Actions.snackBar('Aanmaken mislukt.'),
      ]),
    ),
  );
}

// ─── GuardianPage: API-wiring on load ────────────────────────────────────────
//
// Koppelt drie sequentiële API-aanroepen aan de ON_INIT_STATE trigger:
//   GetPendingGuardianRequests → GetGuardianChildren → GetMyGuardianRequests
// EERSTE actie zet isLoading=false zodat de spinner NOOIT vastloopt, ongeacht
// wat er met de API calls gebeurt. De UI updatet reactief als data binnenkomt.
void _wireGuardianPageLoad(FFProject project) {
  final wc = findPage(project, name: 'GuardianPage');
  if (wc == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  // Helper: setLoading(false) action — used in every success/failure branch.
  FFAction _stopLoading() => Actions.updatePageState(
    project,
    widgetClassName: 'GuardianPage',
    updates: [StateFieldUpdate.set('isLoading', 'false')],
  );

  // 3. GetMyGuardianRequests — LAST in chain.
  final myRequestsNode = Actions.apiCallNode(
    project,
    endpointName:       'GetMyGuardianRequests',
    groupName:          'VoetbalPlannerAPI',
    outputVariableName: 'guardianMyReqLoad',
    nodeKey:            wc.node.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'GuardianPage', updates: [
        StateFieldUpdate.setFromVariable('myRequests', ctx.responseVar),
      ]),
      _stopLoading(),
    ]),
    onFailure: (ctx) => Actions.chain([
      _stopLoading(),
    ]),
  );

  // 2. GetGuardianChildren.
  final childrenNode = Actions.apiCallNode(
    project,
    endpointName:       'GetGuardianChildren',
    groupName:          'VoetbalPlannerAPI',
    outputVariableName: 'guardianChildrenLoad',
    nodeKey:            wc.node.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'GuardianPage', updates: [
        StateFieldUpdate.setFromVariable('children', ctx.responseVar),
      ]),
      _stopLoading(),
    ]),
    onFailure: (ctx) => Actions.chain([
      _stopLoading(),
    ]),
  );

  // 1. GetPendingGuardianRequests.
  final pendingNode = Actions.apiCallNode(
    project,
    endpointName:       'GetPendingGuardianRequests',
    groupName:          'VoetbalPlannerAPI',
    outputVariableName: 'guardianPendingLoad',
    nodeKey:            wc.node.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'GuardianPage', updates: [
        StateFieldUpdate.setFromVariable('pendingForMe', ctx.responseVar),
      ]),
      _stopLoading(),
    ]),
    onFailure: (ctx) => Actions.chain([
      _stopLoading(),
    ]),
  );

  // Chain: pending → children → myRequests
  // Use explicit followUpAction at root level so the next node always fires
  // regardless of success/failure of the current one.
  var childrenTail = childrenNode;
  while (childrenTail.hasFollowUpAction()) childrenTail = childrenTail.followUpAction;
  childrenTail.followUpAction = myRequestsNode;

  var pendingTail = pendingNode;
  while (pendingTail.hasFollowUpAction()) pendingTail = pendingTail.followUpAction;
  pendingTail.followUpAction = childrenNode;

  // VANGNET: zet isLoading=false als ALLEREERSTE actie zodat de spinner
  // nooit blijft draaien, ongeacht wat de API calls daarna doen.
  final initStopLoading = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: _stopLoading(),
    followUpAction: pendingNode,
  );

  Actions.onPageLoadChain(wc.node, initStopLoading);
}

// ─── GuardianPage: Accepteren / Weigeren / Intrekken buttons ─────────────────
//
// Koppelt de actieknopen in de list-items aan de juiste API-aanroepen.
// Na elke actie: snackbar. De pagina herlaadt automatisch bij terugnavigatie
// (ON_INIT_STATE vuurt opnieuw op iOS/Android navigate-back).
void _wireGuardianRespondActions(FFProject project) {
  final wc = findPage(project, name: 'GuardianPage');
  if (wc == null) return;

  // ── AccepterenButton ──────────────────────────────────────────────────
  final pendingList = findDescendants(wc.node, (n) => n.name == 'PendingForMeListView').firstOrNull;
  if (pendingList != null && pendingList.children.isNotEmpty) {
    final itemTemplate = pendingList.children.first;

    final acceptBtn = findDescendants(itemTemplate, (n) => n.name == 'AccepterenButton').firstOrNull;
    if (acceptBtn != null) {
      acceptBtn.triggerActions.removeWhere(
        (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
      );
      final linkIdFieldId = _findStructFieldId(project, 'GuardianRequest', 'id');
      final linkIdVar = linkIdFieldId != null
          ? (varFromGeneratorVariable(pendingList.key)
              ..operations.add(FFVariableOperation(
                accessDataStructField: FFAccessDataStructField(fieldIdentifier: linkIdFieldId.deepCopy()),
              )))
          : generatorVarField(pendingList.key, 'id');

      Actions.addTriggerChain(
        acceptBtn,
        FFActionTriggerType.ON_TAP,
        Actions.apiCallNode(
          project,
          endpointName:       'RespondGuardianRequest',
          groupName:          'VoetbalPlannerAPI',
          variables:          {'action': 'approve'},
          dynamicVariables:   {'linkId': linkIdVar},
          outputVariableName: 'gApproveResult',
          nodeKey:            acceptBtn.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.snackBar('Koppeling geaccepteerd. Vernieuw de pagina om de wijziging te zien.'),
          ]),
          onFailure: (ctx) => Actions.chain([
            Actions.snackBar('Actie mislukt, probeer opnieuw.'),
          ]),
        ),
      );
    }

    // ── WeigerenButton ────────────────────────────────────────────────────
    final rejectBtn = findDescendants(itemTemplate, (n) => n.name == 'WeigerenButton').firstOrNull;
    if (rejectBtn != null) {
      rejectBtn.triggerActions.removeWhere(
        (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
      );
      final linkIdFieldId = _findStructFieldId(project, 'GuardianRequest', 'id');
      final linkIdVar = linkIdFieldId != null
          ? (varFromGeneratorVariable(pendingList.key)
              ..operations.add(FFVariableOperation(
                accessDataStructField: FFAccessDataStructField(fieldIdentifier: linkIdFieldId.deepCopy()),
              )))
          : generatorVarField(pendingList.key, 'id');

      Actions.addTriggerChain(
        rejectBtn,
        FFActionTriggerType.ON_TAP,
        Actions.apiCallNode(
          project,
          endpointName:       'RespondGuardianRequest',
          groupName:          'VoetbalPlannerAPI',
          variables:          {'action': 'reject'},
          dynamicVariables:   {'linkId': linkIdVar},
          outputVariableName: 'gRejectResult',
          nodeKey:            rejectBtn.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.snackBar('Verzoek geweigerd.'),
          ]),
          onFailure: (ctx) => Actions.chain([
            Actions.snackBar('Actie mislukt, probeer opnieuw.'),
          ]),
        ),
      );
    }
  }

  // ── IntrekkenButton ────────────────────────────────────────────────────
  final childrenList = findDescendants(wc.node, (n) => n.name == 'GuardianChildrenListView').firstOrNull;
  if (childrenList != null && childrenList.children.isNotEmpty) {
    final itemTemplate = childrenList.children.first;
    final intrekkenBtn = findDescendants(itemTemplate, (n) => n.name == 'IntrekkenButton').firstOrNull;
    if (intrekkenBtn != null) {
      intrekkenBtn.triggerActions.removeWhere(
        (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
      );
      final linkIdFieldId = _findStructFieldId(project, 'GuardianChild', 'linkId');
      final linkIdVar = linkIdFieldId != null
          ? (varFromGeneratorVariable(childrenList.key)
              ..operations.add(FFVariableOperation(
                accessDataStructField: FFAccessDataStructField(fieldIdentifier: linkIdFieldId.deepCopy()),
              )))
          : generatorVarField(childrenList.key, 'linkId');

      Actions.addTriggerChain(
        intrekkenBtn,
        FFActionTriggerType.ON_TAP,
        Actions.apiCallNode(
          project,
          endpointName:       'RevokeGuardianLink',
          groupName:          'VoetbalPlannerAPI',
          dynamicVariables:   {'linkId': linkIdVar},
          outputVariableName: 'gRevokeResult',
          nodeKey:            intrekkenBtn.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.snackBar('Koppeling ingetrokken.'),
          ]),
          onFailure: (ctx) => Actions.chain([
            Actions.snackBar('Intrekken mislukt, probeer opnieuw.'),
          ]),
        ),
      );
    }
  }
}

// ─── GuardianRequestPage: TextField → page state binding ─────────────────────
//
// Bindt elke TextField aan zijn page-state-veld via localStateValue = true.
// Hetzelfde patroon als _fixChatTextFieldDebounce: instant updates, geen debounce.
void _wireGuardianRequestTextFields(FFProject project) {
  final wc = findPage(project, name: 'GuardianRequestPage');
  if (wc == null) return;

  void _bindField(String fieldName, String stateFieldName) {
    final tf = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (tf == null) return;
    final stateId = _findPageStateFieldId(project, 'GuardianRequestPage', stateFieldName);
    if (stateId == null) return;

    tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
    tf.props.textField.localStateValue = true;
    tf.props.textField.initialText = FFText(
      textValue: FFStringValue(
        variable: varFromPageState(stateId.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      ),
    );
  }

  _bindField('LidnummerField',  'lidnummer');
  _bindField('AchternaamField', 'achternaam');

  // Verwijder oude geboortedatum-widgets uit eerdere pushes.
  for (final name in ['GeboortedatumField', 'GeboortedatumFieldCol',
                      'GeboortedatumFieldContainer', 'GeboortedatumLabel']) {
    final stale = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
    if (stale != null) {
      final res = findParentByKey(wc.node, stale.key);
      res?.parent.children.removeWhere((n) => n.key == stale.key);
    }
  }
}

// ─── GuardianRequestPage: formulier submit ────────────────────────────────────
//
// Koppelt de SubmitGuardianButton aan RequestGuardianAccess.
// Leest veldwaarden via page state (gevoed door TextField localStateValue bindings).
void _wireGuardianRequestSubmit(FFProject project) {
  final wc = findPage(project, name: 'GuardianRequestPage');
  if (wc == null) return;

  final submitBtn = findDescendants(wc.node, (n) => n.name == 'SubmitGuardianButton').firstOrNull;
  if (submitBtn == null) return;

  submitBtn.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  // Lees de waarden RECHTSTREEKS uit de tekstvelden (widget-value). De
  // page-state-velden werden niet betrouwbaar bijgewerkt bij het typen, waardoor
  // de API onterecht lege/placeholder-waarden kreeg ("[lidnummer]").
  final lidnummerField  = findDescendants(wc.node, (n) => n.name == 'LidnummerField').firstOrNull;
  final achternaamField = findDescendants(wc.node, (n) => n.name == 'AchternaamField').firstOrNull;
  if (lidnummerField == null || achternaamField == null) return;

  final submitNode = Actions.apiCallNode(
    project,
    endpointName:       'RequestGuardianAccess',
    groupName:          'VoetbalPlannerAPI',
    dynamicVariables: {
      'lidnummer':  varFromTextFieldValue(lidnummerField.key),
      'achternaam': varFromTextFieldValue(achternaamField.key),
    },
    outputVariableName: 'guardianRequestResult',
    nodeKey:            submitBtn.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'GuardianRequestPage',
          updates: [StateFieldUpdate.set('errorMessage', '')]),
      Actions.snackBar('Koppelverzoek verstuurd! Het lid wordt gevraagd dit te bevestigen.'),
      Actions.navigate(project, pageName: 'GuardianPage'),
    ]),
    // Toon de ECHTE backend-melding i.p.v. een vaste tekst. Zo ziet de gebruiker
    // het werkelijke probleem, bv. "Geen lid-profiel gevonden voor uw account"
    // (als de ingelogde gebruiker — bv. een super admin — zelf geen lid is) of
    // "Geen lid gevonden met de opgegeven gegevens".
    onFailure: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'GuardianRequestPage',
          updates: [StateFieldUpdate.setFromVariable(
              'errorMessage', _jsonBodyVar(ctx, r'$.message', submitBtn.key))]),
      Actions.snackBar('Koppelen niet gelukt — zie de melding op het scherm.'),
    ]),
  );

  Actions.addTriggerChain(submitBtn, FFActionTriggerType.ON_TAP, submitNode);
}

// ─── GuardianSelfRegisterPage body ────────────────────────────────────────────
//
// Publiek formulier waarmee een ouder zichzelf registreert via 3-veld
// verificatie van het kind (lidnummer + achternaam + geboortedatum).
void _buildGuardianSelfRegisterPageBody(FFProject project) {
  final wc = findPage(project, name: 'GuardianSelfRegisterPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'SubmitSelfRegisterButton').isNotEmpty) return;

  final scaffoldKey = wc.node.key;

  final rootCol = findDescendants(wc.node, (n) => n.name == 'GuardianSelfRegisterRootColumn').firstOrNull;
  if (rootCol == null) return;
  rootCol.children.removeWhere((n) => n.name == 'GuardianSelfRegisterBodyPlaceholder');

  final errorMsgId = _findPageStateFieldId(project, 'GuardianSelfRegisterPage', 'errorMessage');

  // Foutcontainer
  final errorContainer = UI.container(
    name: 'SelfRegErrorContainer',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.error,
    child: UI.text('', name: 'SelfRegErrorText', style: UITextStyle.bodySmall, color: UIColor.secondaryBackground),
  );
  if (errorMsgId != null) {
    final errText = findDescendants(errorContainer, (n) => n.name == 'SelfRegErrorText').firstOrNull;
    if (errText != null) {
      final errVar = varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
      final copy = errText.props.text.deepCopy();
      copy.textValue = FFStringValue(variable: errVar.deepCopy());
      errText.props.text = copy;
    }
    final isErrorVar = conditionVar(
      varFromPageState(errorMsgId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
    setConditionalVisibility(errorContainer, variable: isErrorVar);
  }

  final submitBtn = UI.button(
    'Registreren',
    name: 'SubmitSelfRegisterButton',
    width: double.infinity,
  );

  // Helper voor een label + tekstveld groep
  FFNode _fieldGroup(String label, String fieldName, String hint, {UIKeyboardType? kbd}) {
    return UI.container(
      name: '${fieldName}Container',
      child: UI.column(
        name: '${fieldName}Col',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 4,
        children: [
          UI.text(label, name: '${fieldName}Label', style: UITextStyle.labelMedium),
          UI.textField(
            hintText: hint,
            name: fieldName,
            keyboardType: kbd ?? UIKeyboardType.text,
          ),
        ],
      ),
    );
  }

  final formCol = UI.column(
    name: 'SelfRegFormColumn',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 16,
    children: [
      UI.icon('how_to_reg', size: 48, color: UIColor.primary, name: 'SelfRegIcon'),
      UI.text(
        'Registreren als ouder/verzorger',
        name: 'SelfRegTitle',
        style: UITextStyle.titleMedium,
      ),
      UI.text(
        'Vul je eigen gegevens in plus het lidnummer en de achternaam van je kind. '
        'Het kind moet de koppeling later in de app bevestigen.',
        name: 'SelfRegIntro',
        style: UITextStyle.bodySmall,
      ),
      UI.container(name: 'SelfRegDivider1', padding: UIEdgeInsets.only(bottom: 4)),

      UI.text('Jouw gegevens', name: 'SelfRegSectionParent', style: UITextStyle.labelLarge),
      _fieldGroup('Naam',  'SelfRegNaamField',  'Voor- en achternaam'),
      _fieldGroup('E-mailadres', 'SelfRegEmailField', 'ouder@voorbeeld.nl',
          kbd: UIKeyboardType.email),

      UI.container(name: 'SelfRegDivider2', padding: UIEdgeInsets.only(bottom: 4)),

      UI.text('Gegevens van het kind', name: 'SelfRegSectionChild',
          style: UITextStyle.labelLarge),
      _fieldGroup('Lidnummer',  'SelfRegLidnummerField',  'bijv. LID-00123'),
      _fieldGroup('Achternaam', 'SelfRegAchternaamField', 'Achternaam van het kind'),

      errorContainer,
      submitBtn,
    ],
  );

  rootCol.children.add(
    UI.container(
      name: 'SelfRegScrollContainer',
      padding: UIEdgeInsets.all(24),
      child: formCol,
    ),
  );
}

// Maakt de root column van GuardianSelfRegisterPage scrollbaar zodat alle
// velden bereikbaar blijven, ook met geopend toetsenbord.
// Apart van de body-build functie zodat het altijd draait, ook als de body
// al bestaat (idempotency-skip in _buildGuardianSelfRegisterPageBody).
void _makeGuardianSelfRegisterPageScrollable(FFProject project) {
  final wc = findPage(project, name: 'GuardianSelfRegisterPage');
  if (wc == null) return;

  final rootCol = findDescendants(wc.node,
      (n) => n.name == 'GuardianSelfRegisterRootColumn').firstOrNull;
  if (rootCol == null) return;
  if (!rootCol.props.hasColumn()) return;

  if (!rootCol.props.column.scrollable) {
    final colCopy = rootCol.props.column.deepCopy();
    colCopy.scrollable = true;
    rootCol.props.column = colCopy;
  }
}

// Bindt TextFields aan page state via localStateValue.
void _wireGuardianSelfRegisterTextFields(FFProject project) {
  final wc = findPage(project, name: 'GuardianSelfRegisterPage');
  if (wc == null) return;

  void _bindField(String fieldName, String stateFieldName) {
    final tf = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (tf == null) return;
    final stateId = _findPageStateFieldId(project, 'GuardianSelfRegisterPage', stateFieldName);
    if (stateId == null) return;
    tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
    tf.props.textField.localStateValue = true;
    tf.props.textField.initialText = FFText(
      textValue: FFStringValue(
        variable: varFromPageState(stateId.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      ),
    );
  }

  _bindField('SelfRegNaamField',       'naam');
  _bindField('SelfRegEmailField',      'email');
  _bindField('SelfRegLidnummerField',  'lidnummer');
  _bindField('SelfRegAchternaamField', 'achternaam');

  // Verwijder oude geboortedatum-velden uit eerdere pushes.
  for (final name in ['SelfRegGeboortedatumField',
                      'SelfRegGeboortedatumFieldCol',
                      'SelfRegGeboortedatumFieldContainer',
                      'SelfRegGeboortedatumFieldLabel']) {
    final stale = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
    if (stale != null) {
      final res = findParentByKey(wc.node, stale.key);
      res?.parent.children.removeWhere((n) => n.key == stale.key);
    }
  }
}

// Submit knop → SelfRegisterGuardian API call.
void _wireGuardianSelfRegisterSubmit(FFProject project) {
  final wc = findPage(project, name: 'GuardianSelfRegisterPage');
  if (wc == null) return;

  final submitBtn = findDescendants(wc.node, (n) => n.name == 'SubmitSelfRegisterButton').firstOrNull;
  if (submitBtn == null) return;

  submitBtn.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  // Lees waarden rechtstreeks uit de tekstvelden (widget-value); page-state werd
  // niet betrouwbaar bijgewerkt bij het typen.
  FFVariable _fieldVar(String fieldName) {
    final f = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
    if (f == null) return varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING);
    return varFromTextFieldValue(f.key);
  }

  Actions.addTriggerChain(
    submitBtn,
    FFActionTriggerType.ON_TAP,
    Actions.apiCallNode(
      project,
      endpointName:       'SelfRegisterGuardian',
      groupName:          'VoetbalPlannerAPI',
      dynamicVariables: {
        'naam':       _fieldVar('SelfRegNaamField'),
        'email':      _fieldVar('SelfRegEmailField'),
        'lidnummer':  _fieldVar('SelfRegLidnummerField'),
        'achternaam': _fieldVar('SelfRegAchternaamField'),
      },
      outputVariableName: 'selfRegResult',
      nodeKey:            submitBtn.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'GuardianSelfRegisterPage',
          updates: [StateFieldUpdate.set('errorMessage', '')],
        ),
        Actions.snackBar(
          'Registratie gelukt! Check je e-mail voor de inloglink. '
          'Het kind moet de koppeling nog bevestigen.',
        ),
        Actions.navigate(project, pageName: 'LoginPage'),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'GuardianSelfRegisterPage',
          updates: [StateFieldUpdate.set(
            'errorMessage',
            'Registratie mislukt. Controleer de gegevens en probeer opnieuw.',
          )],
        ),
        Actions.snackBar('Registratie mislukt.'),
      ]),
    ),
  );
}

// Voegt een "Registreren als ouder/verzorger" knop toe onderaan de LoginPage.
// Idempotent: slaat over als al aanwezig.
void _addGuardianRegisterLinkToLoginPage(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'GuardianRegisterButton').isNotEmpty) return;

  final btn = UI.button(
    'Registreren als ouder/verzorger',
    name: 'GuardianRegisterButton',
    width: double.infinity,
    color: UIColor.secondaryBackground,
    textColor: UIColor.primary,
    borderRadius: 8,
  );
  Actions.onTap(btn, Actions.navigate(project, pageName: 'GuardianSelfRegisterPage'));

  // Plaats onderaan de body column.
  final bodyCol = findByKey(wc.node, 'Column_agcaeg1m');
  if (bodyCol == null) return;
  bodyCol.children.add(btn);
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

// Fixes the WisselAanvraagPage layout:
// - The outer Column defaulted to mainAxisSize.min, collapsing the ListView to zero height.
// - The ConditionalBuilder needs to be Expanded so it fills remaining space.
void _fixWisselAanvraagPageLayout(FFProject project) {
  final wc = findPage(project, name: 'WisselAanvraagPage');
  if (wc == null) return;

  // Change outer Column from min → max so the body fills the screen.
  final bodyCol = findByKey(wc.node, 'Column_bfggz6nz');
  if (bodyCol != null) {
    final colCopy = bodyCol.props.column.deepCopy();
    colCopy.minSizeValue = FFBooleanValue(inputValue: false);
    bodyCol.props.column = colCopy;
  }

  // Mark the ConditionalBuilder as Expanded so it takes remaining space in the Column.
  final conditionalBuilder = findByKey(wc.node, 'ConditionalBuilder_ffuahemv');
  if (conditionalBuilder != null) {
    conditionalBuilder.props.expanded = FFExpanded(
      expandedType: FFExpanded_ExpandedType.EXPANDED,
      flexValue: FFIntegerValue(inputValue: 1),
    );
  }
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
    'apiStatus',
  ]) {
    _ensurePageStateField(wc, name, FFBaseDataType.String);
  }
  _ensurePageStateField(wc, 'dutyCanSelfAssign', FFBaseDataType.Boolean);
  _ensurePageStateField(wc, 'dutyIsAssignedToMe', FFBaseDataType.Boolean);

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
        // Map state field name → JSON path in the API response body.
        const fieldPaths = {
          'dutyDate':     r'$.date',
          'dutyShift':    r'$.shift',
          'dutyStatus':   r'$.status',
          'dutyTeamName': r'$.teamName',
          'dutyMembers':  r'$.members',
          'dutyNotes':    r'$.notes',
        };
        final updates = <StateFieldUpdate>[
          StateFieldUpdate.set('isLoading', 'false'),
          StateFieldUpdate.set('apiStatus', 'OK'),
        ];
        for (final entry in fieldPaths.entries) {
          final v = _jsonBodyVar(ctx, entry.value, wc.node.key);
          updates.add(StateFieldUpdate.setFromVariable(entry.key, v));
        }
        // canSelfAssign (bool) — drives visibility of the "Aanmelden" button.
        updates.add(StateFieldUpdate.setFromVariable(
          'dutyCanSelfAssign',
          _jsonBodyVar(ctx, r'$.canSelfAssign', wc.node.key),
        ));
        // isAssignedToMe (bool) — drives visibility of the "Wissel aanvragen" button.
        updates.add(StateFieldUpdate.setFromVariable(
          'dutyIsAssignedToMe',
          _jsonBodyVar(ctx, r'$.isAssignedToMe', wc.node.key),
        ));
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
          updates: [
            StateFieldUpdate.set('isLoading', 'false'),
            StateFieldUpdate.set('apiStatus', 'FAILED'),
          ],
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
    'rijIsHome', 'rijIsDriver', 'rijDriverNames', 'rijCoachName', 'rijTeamName',
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
        const fieldPaths = {
          'rijOpponent':    r'$.opponent',
          'rijDatetime':    r'$.matchDatetime',
          'rijLocation':    r'$.location',
          'rijArrivalTime': r'$.arrivalTime',
          'rijNotes':       r'$.notes',
          'rijIsHome':      r'$.isHome',
          'rijIsDriver':    r'$.isDriver',
          'rijDriverNames': r'$.driverNames',
          'rijCoachName':   r'$.coachName',
          'rijTeamName':    r'$.teamName',
        };
        final updates = <StateFieldUpdate>[StateFieldUpdate.set('isLoading', 'false')];
        for (final entry in fieldPaths.entries) {
          updates.add(StateFieldUpdate.setFromVariable(
            entry.key,
            _jsonBodyVar(ctx, entry.value, wc.node.key),
          ));
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
    // Bind via "x ?? ''" zodat de waarde nooit null is — anders genereert FF
    // _model.X! en crasht de pagina op een null-veld (bv. bardienst zonder notities).
    if (v != null) {
      valueText.props.text.textValue = FFStringValue(
        variable: codeExpressionVar(
          expression: "x ?? ''",
          arguments: [
            CodeExpressionArg(
              name: 'x',
              dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
              value: FFValue(variable: v),
            ),
          ],
          returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ),
      );
    }
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

  // "Thuis" / "Uit" row using a code expression on the "true"/"false" string.
  FFNode? homeAwayRow() {
    final isHomeVar = stateVar('rijIsHome');
    if (isHomeVar == null) return null;
    final displayVar = codeExpressionVar(
      expression: "isHome == 'true' ? 'Thuis' : 'Uit'",
      arguments: [
        CodeExpressionArg(
          name: 'isHome',
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: isHomeVar),
        ),
      ],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
    );
    final valueText = UI.text('-', name: 'RijInfoValue_rijIsHome', style: UITextStyle.bodyMedium);
    valueText.props.text.textValue = FFStringValue(variable: displayVar);
    return UI.container(
      name: 'RijInfoRow_rijIsHome',
      padding: UIEdgeInsets.symmetric(vertical: 6, horizontal: 0),
      child: UI.column(
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 2,
        children: [
          UI.text('Thuis/Uit', style: UITextStyle.labelSmall, color: UIColor.secondaryText),
          valueText,
        ],
      ),
    );
  }

  // "Navigeer naar locatie" button — opens Google Maps with the match location address.
  FFNode? mapsButton() {
    final locationVar = stateVar('rijLocation');
    if (locationVar == null) return null;
    final mapsUrlVar = codeExpressionVar(
      expression: "'https://maps.google.com/?q=' + Uri.encodeComponent(loc ?? '')",
      arguments: [
        CodeExpressionArg(
          name: 'loc',
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: locationVar),
        ),
      ],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
    );
    final btn = UI.button(
      'Navigeer naar locatie',
      name: 'RijNavigateButton',
      width: double.infinity,
      iconName: 'map',
      color: UIColor.secondaryBackground,
      textColor: UIColor.primaryText,
      borderRadius: 8,
    );
    Actions.onTap(
      btn,
      FFAction(launchUrl: FFLaunchUrlAction(variable: mapsUrlVar)),
    );
    return btn;
  }

  // "Wissel aanvragen" button — only visible when this user is the driver.
  FFNode? swapButton() {
    final isDriverVar = stateVar('rijIsDriver');
    if (isDriverVar == null) return null;
    if (project.getWidgetClassByName('WisselAanvraagPage') == null) return null;

    FFIdentifier? matchIdParamId;
    for (final param in wc.params.values) {
      if (param.hasIdentifier() && param.identifier.name == 'matchId') {
        matchIdParamId = param.identifier;
        break;
      }
    }
    if (matchIdParamId == null) return null;

    final isDriverBool = codeExpressionVar(
      expression: "isDriver == 'true'",
      arguments: [
        CodeExpressionArg(
          name: 'isDriver',
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: isDriverVar),
        ),
      ],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
    );

    final btn = UI.button(
      'Wissel aanvragen',
      name: 'RijWisselButton',
      width: double.infinity,
    );
    setConditionalVisibility(btn, variable: isDriverBool);
    Actions.onTap(
      btn,
      Actions.navigate(
        project,
        pageName: 'WisselAanvraagPage',
        params: {
          'dutyType':    StaticParamValue('rijschema'),
          'targetId':    VariableParamValue(varFromPageParam(matchIdParamId.deepCopy())),
          'targetLabel': StaticParamValue('Rit wissel'),
        },
      ),
    );
    return btn;
  }

  final homeAway = homeAwayRow();
  final maps = mapsButton();
  final swap = swapButton();
  infoColumn.children.clear();
  infoColumn.children.addAll([
    UI.text('Wedstrijd details', name: 'RijTitle', style: UITextStyle.titleMedium),
    infoRow('Eigen team',   'rijTeamName'),
    infoRow('Tegenstander', 'rijOpponent'),
    infoRow('Datum & Tijd', 'rijDatetime'),
    infoRow('Locatie',      'rijLocation'),
    if (maps != null) maps,
    if (homeAway != null) homeAway,
    infoRow('Verzamelen',   'rijArrivalTime'),
    infoRow('Coach',        'rijCoachName'),
    infoRow('Rijders',      'rijDriverNames'),
    infoRow('Notities',     'rijNotes'),
    if (swap != null) swap,
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

// Adds a "Locatie", "Rijders" and "Coach" row to the RijschemaPage list card.
// Runs idempotently: does nothing if the rows already exist.
void _wireRijschemaCardDriverRow(FFProject project) {
  final wc = findPage(project, name: 'RijschemaPage');
  if (wc == null) return;

  final cardColumn = findByKey(wc.node, 'Column_cx7sodso');
  if (cardColumn == null) return;

  // Replace the pre-existing unnamed row (Row_p64u9n36) that was incorrectly
  // bound to coachName with a proper location row.
  if (!cardColumn.children.any((c) => c.name == 'RijCardLocationRow')) {
    cardColumn.children.removeWhere((c) => c.key == 'Row_p64u9n36');
    final locationText = UI.text('-', name: 'RijCardLocationText', style: UITextStyle.bodySmall);
    locationText.props.text.textValue = FFStringValue(
      variable: generatorVarField('ListView_55kreos3', 'location'),
    );
    cardColumn.children.insert(2,
      UI.row(
        name: 'RijCardLocationRow',
        spacing: 8,
        children: [
          UI.icon('location_on', size: 14, color: UIColor.secondaryText),
          locationText,
        ],
      ),
    );
  }

  if (!cardColumn.children.any((c) => c.name == 'RijCardDriverRow')) {
    final driverText = UI.text('-', name: 'RijCardDriverText', style: UITextStyle.bodySmall);
    driverText.props.text.textValue = FFStringValue(
      variable: generatorVarField('ListView_55kreos3', 'driverNames'),
    );
    cardColumn.children.add(
      UI.row(
        name: 'RijCardDriverRow',
        spacing: 8,
        children: [
          UI.icon('directions_car', size: 14, color: UIColor.secondaryText),
          driverText,
        ],
      ),
    );
  }

  if (!cardColumn.children.any((c) => c.name == 'RijCardCoachRow')) {
    final coachText = UI.text('-', name: 'RijCardCoachText', style: UITextStyle.bodySmall);
    coachText.props.text.textValue = FFStringValue(
      variable: generatorVarField('ListView_55kreos3', 'coachName'),
    );
    cardColumn.children.add(
      UI.row(
        name: 'RijCardCoachRow',
        spacing: 8,
        children: [
          UI.icon('sports_soccer', size: 14, color: UIColor.secondaryText),
          coachText,
        ],
      ),
    );
  }
}

// Binds value text nodes on BardienDetailPage to individual string state fields.
// Each field (dutyDate, dutyShift, …) was added by _wireBardienDetailPageLoad.
// Also unwraps the ConditionalBuilder so DutyInfoColumn is a direct body child —
// the CB caused codegen to render only the first child of its else branch.
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

  // Unwrap ConditionalBuilder_s0sf280z from Column_v4xwu1ha, promoting
  // DutyInfoColumn (children[1]) to be a direct child of the outer body column.
  final outerColumn = findByKey(wc.node, 'Column_v4xwu1ha');
  final cb = findByKey(wc.node, 'ConditionalBuilder_s0sf280z');
  if (outerColumn != null && cb != null &&
      outerColumn.children.any((c) => c.key == cb.key)) {
    unwrap(outerColumn, cb, childIndex: 1);
  }

  final infoColumn = findDescendants(wc.node, (n) => n.name == 'DutyInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  // Clear the visible: Not(isLoading) binding so DutyInfoColumn is always rendered.
  // Without this, FlutterFlow codegen wraps it in a conditional; data rows stay hidden.
  setConditionalVisibility(infoColumn, variable: null);

  FFNode infoRow(String label, String stateFieldName) {
    final valueText = UI.text('-', name: 'DutyInfoValue_$stateFieldName', style: UITextStyle.bodyMedium);
    final v = stateVar(stateFieldName);
    // Bind via "x ?? ''" zodat de waarde nooit null is — anders genereert FF
    // _model.X! en crasht de pagina op een null-veld (bv. bardienst zonder notities).
    if (v != null) {
      valueText.props.text.textValue = FFStringValue(
        variable: codeExpressionVar(
          expression: "x ?? ''",
          arguments: [
            CodeExpressionArg(
              name: 'x',
              dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
              value: FFValue(variable: v),
            ),
          ],
          returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ),
      );
    }
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

// Voegt een "Wissel aanvragen" knop toe aan BardienDetailPage, zichtbaar wanneer
// het lid zelf is ingedeeld (dutyIsAssignedToMe == true). onTap: navigeert naar
// WisselAanvraagPage met dutyType 'bardienst', de dutyId en de datum als label —
// dezelfde flow als de knop op de BarDutyCard in de lijst.
void _addBardienWisselButton(FFProject project) {
  final wc = findPage(project, name: 'BardienDetailPage');
  if (wc == null) return;
  if (project.getWidgetClassByName('WisselAanvraagPage') == null) return;
  final infoColumn =
      findDescendants(wc.node, (n) => n.name == 'DutyInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  // Idempotent: verwijder een vorige instance.
  for (final k in findDescendants(infoColumn, (n) => n.name == 'BardienWisselButton')
      .map((n) => n.key)
      .toList()) {
    removeByKey(wc.node, k);
  }

  // dutyId page-param.
  FFIdentifier? dutyIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'dutyId') {
      dutyIdParamId = param.identifier;
      break;
    }
  }
  if (dutyIdParamId == null) return;

  // dutyIsAssignedToMe state-veld → zichtbaarheid.
  final assignedField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'dutyIsAssignedToMe',
          orElse: () => null);
  if (assignedField == null) return;
  final assignedVar =
      varFromPageState(assignedField.parameter.identifier.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final visibleVar = conditionVar(
    assignedVar,
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;

  final button = UI.button('Wissel aanvragen',
      name: 'BardienWisselButton', width: double.infinity);
  setConditionalVisibility(button, variable: visibleVar);

  // targetLabel = de datum (zoals de lijst-knop barDutyDate gebruikt).
  final dateField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'dutyDate',
          orElse: () => null);

  final targetLabel = dateField != null
      ? VariableParamValue(
          varFromPageState(dateField.parameter.identifier.deepCopy())
            ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key))
      : StaticParamValue('Bardienst');

  Actions.onTap(
    button,
    Actions.navigate(
      project,
      pageName: 'WisselAanvraagPage',
      params: {
        'dutyType': StaticParamValue('bardienst'),
        'targetId': VariableParamValue(varFromPageParam(dutyIdParamId.deepCopy())),
        'targetLabel': targetLabel,
      },
    ),
  );

  infoColumn.children.add(button);
}

// Voegt een "Aanmelden" knop toe aan BardienDetailPage die zichtbaar is wanneer
// dutyCanSelfAssign == true. onTap: roept SelfAssignBarDuty aan, herlaadt het
// detail bij succes en toont een snackbar.
void _addBardienAanmeldenButton(FFProject project) {
  final wc = findPage(project, name: 'BardienDetailPage');
  if (wc == null) return;
  final infoColumn = findDescendants(wc.node, (n) => n.name == 'DutyInfoColumn').firstOrNull;
  if (infoColumn == null) return;

  // Idempotent: verwijder vorige instance zodat de chain bij elke push fris
  // wordt opgebouwd (anders kunnen state-field keys verschuiven).
  final existingBtnKeys = findDescendants(infoColumn, (n) => n.name == 'DutyAanmeldenButton')
      .map((n) => n.key)
      .toList();
  for (final k in existingBtnKeys) {
    removeByKey(wc.node, k);
  }

  // dutyId page param
  FFIdentifier? dutyIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'dutyId') {
      dutyIdParamId = param.identifier;
      break;
    }
  }
  if (dutyIdParamId == null) return;

  // canSelfAssign state field (gezet door _wireBardienDetailPageLoad).
  final canSelfAssignField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'dutyCanSelfAssign',
          orElse: () => null);
  if (canSelfAssignField == null) return;

  final canSelfAssignVar = varFromPageState(
    canSelfAssignField.parameter.identifier.deepCopy(),
  )..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final visibleVar = conditionVar(
    canSelfAssignVar,
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;

  final button = UI.button(
    'Aanmelden',
    name: 'DutyAanmeldenButton',
    width: double.infinity,
  );
  setConditionalVisibility(button, variable: visibleVar);

  // Build a snackBar action whose textMessage is a runtime variable (so we can
  // surface the server-side error message). interpolateVar returns FFStringValue,
  // FFValue accepts an FFVariable — extract it via .variable.
  FFAction snackBarFrom(FFStringValue interpolated) => FFAction(
        key: generateRandomAlphaNumericString(),
        snackBar: FFSnackBarAction(
          textMessage: FFValue(variable: interpolated.variable),
          durationMillis: 6000,
        ),
      );

  // Reads a single API-response-field variable (status code, raw body, etc.)
  // from the running action's output for use inside a snackbar interpolation.
  FFVariable apiField(
      dynamic ctx, FFApiResponseField_ResponseField field, String nodeKey) {
    final actionKey = (ctx as dynamic).actionKey as String;
    final outputVarName = (ctx as dynamic).outputVarName as String;
    return FFVariable(
      source: FFVariableSource.ACTION_OUTPUTS,
      baseVariable: FFBaseVariable(
        actionOutput: FFActionOutputVariable(
          actionKeyRef: FFActionKeyReference(key: actionKey),
          outputVariableIdentifier: FFIdentifier(name: outputVarName),
        ),
      ),
      operations: [
        FFVariableOperation(
          apiResponseField: FFApiResponseField(responseField: field),
        ),
      ],
      nodeKeyRef: FFNodeKeyReference(key: nodeKey),
    );
  }

  // GetBarDuties refresh node — werkt AppState.sharedBarDuties bij zodat de
  // ListView op BardienPage automatisch ververst zodra de gebruiker terug
  // gaat. (BardienPage's ListView is gerebind aan AppState.sharedBarDuties.)
  final authTokenIdBd      = _findAppStateFieldId(project, 'authToken');
  final currentTeamIdIdBd  = _findAppStateFieldId(project, 'currentTeamId');
  final sharedBarDutiesId  = _findAppStateFieldId(project, 'sharedBarDuties');
  FFActionNode? listRefreshNode;
  if (authTokenIdBd != null && sharedBarDutiesId != null) {
    listRefreshNode = Actions.apiCallNode(
      project,
      endpointName: 'GetBarDuties',
      groupName: 'VoetbalPlannerAPI',
      variables: {'page': '1'},
      // Bardiensten zijn op persoon: geen teamId → alle teams van de gebruiker.
      dynamicVariables: {
        'token': varFromAppState(authTokenIdBd.deepCopy()),
      },
      outputVariableName: 'dutiesListRefresh',
      nodeKey: button.key,
      onSuccess: (lctx) => Actions.chain([
        Actions.updateAppState(
          project,
          updates: [
            StateFieldUpdate.setFromVariable('sharedBarDuties', lctx.responseVar),
          ],
        ),
      ]),
      onFailure: (_) => FFActionNode(key: generateRandomAlphaNumericString()),
    );
  }

  // Build a GetBarDutyDetail reload node — fired bij success van self-assign
  // zodat de UI direct de nieuwe member-lijst + status laat zien.
  final reloadNode = Actions.apiCallNode(
    project,
    endpointName: 'GetBarDutyDetail',
    groupName: 'VoetbalPlannerAPI',
    dynamicVariables: {
      'dutyId': varFromPageParam(dutyIdParamId.deepCopy()),
    },
    outputVariableName: 'dutyReload',
    nodeKey: button.key,
    onSuccess: (rctx) {
      const fieldPaths = {
        'dutyStatus':   r'$.status',
        'dutyMembers':  r'$.members',
      };
      final updates = <StateFieldUpdate>[];
      for (final entry in fieldPaths.entries) {
        updates.add(StateFieldUpdate.setFromVariable(
          entry.key,
          _jsonBodyVar(rctx, entry.value, button.key),
        ));
      }
      updates.add(StateFieldUpdate.setFromVariable(
        'dutyCanSelfAssign',
        _jsonBodyVar(rctx, r'$.canSelfAssign', button.key),
      ));
      return Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'BardienDetailPage',
          updates: updates,
        ),
      ]);
    },
    onFailure: (_) => Actions.chain([
      Actions.snackBar('Aangemeld, maar herladen mislukte — trek scherm naar beneden om te verversen.'),
    ]),
  );

  // Build the API call AFTER the button exists so nodeKey points at the button.
  final selfAssignNodeForBtn = Actions.apiCallNode(
    project,
    endpointName: 'SelfAssignBarDuty',
    groupName: 'VoetbalPlannerAPI',
    dynamicVariables: {
      'dutyId': varFromPageParam(dutyIdParamId.deepCopy()),
    },
    outputVariableName: 'selfAssign',
    nodeKey: button.key,
    onSuccess: (ctx) {
      final msg = interpolateVar([
        _jsonBodyVar(ctx, r'$.message', button.key),
      ]);
      // Chain: snackbar → reload detail → refresh lijst (sharedBarDuties).
      // listRefreshNode hangt nu OP HET SAMEMSTE NIVEAU als reloadNode, dus
      // ververst de hoofdlijst ook als detail-reload faalt. Hierdoor verschijnt
      // de Wissel-knop in de BardienPage list direct na aanmelden.
      final snackChain = Actions.chain([snackBarFrom(msg)]);
      var tail = snackChain;
      while (tail.hasFollowUpAction()) tail = tail.followUpAction;
      tail.followUpAction = reloadNode;
      if (listRefreshNode != null) {
        while (tail.hasFollowUpAction()) tail = tail.followUpAction;
        tail.followUpAction = listRefreshNode;
      }
      return snackChain;
    },
    onFailure: (ctx) {
      // Toon HTTP status + raw body zodat we precies zien wat de server stuurt.
      final msg = interpolateVar([
        'Aanmelden mislukt [',
        apiField(ctx, FFApiResponseField_ResponseField.STATUS_CODE, button.key),
        ']: ',
        apiField(ctx, FFApiResponseField_ResponseField.RAW_BODY_TEXT, button.key),
      ]);
      return Actions.chain([snackBarFrom(msg)]);
    },
  );
  Actions.addTriggerChain(button, FFActionTriggerType.ON_TAP, selfAssignNodeForBtn);

  // Container met padding zodat de knop wat ruimte heeft van de info-rijen.
  final wrap = UI.container(
    name: 'DutyAanmeldenWrap',
    padding: UIEdgeInsets.symmetric(vertical: 12),
    child: button,
  );

  infoColumn.children.add(wrap);
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
    'matchArrivalTime', 'matchCoachName', 'matchFruitHeroName', 'matchVlaggerName', 'matchNotes',
    'apiStatus', 'matchStatus', 'matchMagAfmelden', 'matchMagOpstelling',
    'matchGoalsSummary', 'matchTeamId', 'selectedScorerName',
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
        // Map state field name → JSON path in the API response body.
        const fieldPaths = {
          'matchOpponent':      r'$.opponent',
          'matchDatetime':      r'$.matchDatetime',
          'matchLocation':      r'$.location',
          'matchArrivalTime':   r'$.arrivalTime',
          'matchCoachName':     r'$.coachName',
          'matchFruitHeroName': r'$.fruitHeroName',
          'matchVlaggerName':   r'$.vlaggerName',
          'matchNotes':         r'$.notes',
          'matchStatus':        r'$.mijn_status',
          'matchMagAfmelden':   r'$.mag_afmelden',
          'matchMagOpstelling': r'$.mag_opstelling',
          'matchGoalsSummary':  r'$.goals_summary',
          'matchTeamId':        r'$.teamId',
        };
        final updates = <StateFieldUpdate>[
          StateFieldUpdate.set('isLoading', 'false'),
          StateFieldUpdate.set('apiStatus', 'OK'),
        ];
        for (final entry in fieldPaths.entries) {
          final v = _jsonBodyVar(ctx, entry.value, wc.node.key);
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
          updates: [
            StateFieldUpdate.set('isLoading', 'false'),
            StateFieldUpdate.set('apiStatus', 'FAILED'),
          ],
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
  // Use keyed struct field identifier for requestee_id so codegen reliably resolves it.
  // Name-only FFIdentifier is not guaranteed to resolve server-side (see _addMatchNavigation).
  final swapMemberIdFieldId = _findStructFieldId(project, 'SwapMember', 'id');
  final requesteeIdVar = swapMemberIdFieldId != null
      ? (varFromGeneratorVariable(listView.key)
          ..operations.add(FFVariableOperation(
            accessDataStructField: FFAccessDataStructField(
              fieldIdentifier: swapMemberIdFieldId.deepCopy(),
            ),
          )))
      : generatorVarField(listView.key, 'id');

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
        'requestee_id': requesteeIdVar,
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

// Maakt de StatusBadge-component tot een ruimere pill: meer horizontale, wat
// minder verticale padding op de label-tekst (was 10 rondom, tekst tegen de rand).
void _makeStatusBadgeRoomier(FFProject project) {
  final wc = findComponent(project, name: 'StatusBadge');
  if (wc == null) return;
  final textNode = findByKey(wc.node, 'Text_6z35bo4a')
      ?? findDescendants(wc.node, (n) => n.type == FFWidgetType.Text).firstOrNull;
  if (textNode == null) return;
  final props = textNode.props.deepCopy();
  props.padding = FFPadding(
    type: FFPadding_PaddingType.FF_PADDING_ONLY,
    leftValue:   FFDoubleValue(inputValue: 14.0),
    topValue:    FFDoubleValue(inputValue: 6.0),
    rightValue:  FFDoubleValue(inputValue: 14.0),
    bottomValue: FFDoubleValue(inputValue: 6.0),
  );
  textNode.props = props;
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
  // Use a static title — matchId is a raw UUID, not human-readable.
  titleNode.props.text.textValue = FFStringValue(inputValue: 'Wedstrijd details');
}

// Binds value text nodes on WedstrijdDetailPage (Info tab) to individual string state fields.
// Each field (matchOpponent, matchDatetime, …) was added by _wireWedstrijdDetailPageLoad.
// Also unwraps the ConditionalBuilder so the TabBar is a direct Scaffold body child —
// the CB caused codegen to render only the first child of its else branch.
void _bindWedstrijdDetailInfoTexts(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;

  // Unwrap ConditionalBuilder_9yl0ufo3 from the Scaffold body, promoting
  // TabBar_hy4ax11p (children[1]) to be a direct Scaffold body child.
  final cbMatch = findByKey(wc.node, 'ConditionalBuilder_9yl0ufo3');
  if (cbMatch != null) {
    final parentResult = findParentByKey(wc.node, cbMatch.key);
    if (parentResult != null) {
      unwrap(parentResult.parent, parentResult.child, childIndex: 1);
    }
  }
  // Clear the visible: Not(isLoading) binding on the TabBar so it always renders.
  final tabBar = findByKey(wc.node, 'TabBar_hy4ax11p');
  if (tabBar != null) {
    setConditionalVisibility(tabBar, variable: null);
    // Ensure tab labels are visible: primary text for selected, secondary for rest,
    // primary theme color for the indicator underline.
    final tabProto = tabBar.props.tabBar.deepCopy();
    tabProto.labelColorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_TEXT));
    tabProto.unselectedLabelColorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT));
    tabProto.indicatorColorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY));
    tabBar.props.tabBar = tabProto;
  }

  FFVariable? stateVar(String stateFieldName) {
    final stateField = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((f) => f?.parameter.identifier.name == stateFieldName, orElse: () => null);
    if (stateField == null) return null;
    final v = varFromPageState(stateField.parameter.identifier.deepCopy());
    v.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    return v;
  }

  // Bouwt een info-rij (label + waarde) met dezelfde opmaak overal. Vroeg
  // gedeclareerd zodat zowel het fallback-pad (met TabBar) als het
  // MatchInfoColumn-pad 'm kan gebruiken.
  FFNode infoRow(String label, String stateFieldName) {
    final valueText = UI.text('-', name: 'MatchInfoValue_$stateFieldName', style: UITextStyle.bodyMedium);
    final v = stateVar(stateFieldName);
    // Bind via "x ?? ''" zodat de waarde nooit null is — anders genereert FF
    // _model.X! en crasht de pagina op een null-veld (bv. bardienst zonder notities).
    if (v != null) {
      valueText.props.text.textValue = FFStringValue(
        variable: codeExpressionVar(
          expression: "x ?? ''",
          arguments: [
            CodeExpressionArg(
              name: 'x',
              dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
              value: FFValue(variable: v),
            ),
          ],
          returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
        ),
      );
    }
    return UI.container(
      name: 'MatchInfoRow_$stateFieldName',
      padding: UIEdgeInsets.symmetric(vertical: 6, horizontal: 0),
      child: UI.column(crossAxisAlignment: UICrossAxisAlignment.start, spacing: 2, children: [
        UI.text(label, style: UITextStyle.labelSmall, color: UIColor.secondaryText),
        valueText,
      ]),
    );
  }

  final infoColumn = findDescendants(wc.node, (n) => n.name == 'MatchInfoColumn').firstOrNull;
  if (infoColumn == null) {
    // Page has a TabBar; bind value nodes by name.
    const fallback = {
      'MatchInfoValue_opponent':      'matchOpponent',
      'MatchInfoValue_matchDatetime': 'matchDatetime',
      'MatchInfoValue_location':      'matchLocation',
      'MatchInfoValue_arrivalTime':   'matchArrivalTime',
      'MatchInfoValue_coachName':     'matchCoachName',
      'MatchInfoValue_fruitHeroName': 'matchFruitHeroName',
      'MatchInfoValue_notes':         'matchNotes',
    };
    var bound = 0;
    for (final entry in fallback.entries) {
      final node = findDescendants(wc.node, (n) => n.name == entry.key).firstOrNull;
      if (node == null) {
        stderr.writeln('[DEBUG WedstrijdDetailPage] fallback node ${entry.key} NOT FOUND');
        continue;
      }
      final v = stateVar(entry.value);
      if (v == null) {
        stderr.writeln('[DEBUG WedstrijdDetailPage] state field ${entry.value} NOT FOUND');
        continue;
      }
      node.props.text.textValue = FFStringValue(variable: v);
      bound++;
    }
    stderr.writeln('[DEBUG WedstrijdDetailPage] fallback bound $bound nodes');

    // Vlagger-rij ná Coach tonen.
    // Eerdere (foutieve) invoeging als tweede child ín de Fruitheld-container
    // opruimen: een FF Container rendert maar één child, waardoor de vlagger
    // onzichtbaar bleef. Ook een reeds correct ingevoegde rij verwijderen zodat
    // dit blok idempotent is bij herhaalde pushes.
    for (final stray in [
      ...findDescendants(wc.node, (n) => n.name == 'MatchInfoItemVlagger'),
      ...findDescendants(wc.node, (n) => n.name == 'MatchInfoRow_matchVlaggerName'),
    ]) {
      final sp = findParentByKey(wc.node, stray.key);
      sp?.parent.children.removeWhere((c) => identical(c, stray));
    }
    // Nieuwe rij met dezelfde opmaak als de andere info-rijen, direct ná Coach.
    final coachValue = findDescendants(wc.node, (n) => n.name == 'MatchInfoValue_coachName').firstOrNull;
    if (coachValue != null && stateVar('matchVlaggerName') != null) {
      final cp1 = findParentByKey(wc.node, coachValue.key);                       // Column
      final cp2 = cp1 != null ? findParentByKey(wc.node, cp1.parent.key) : null;  // MatchInfoRow-container
      final cp3 = cp2 != null ? findParentByKey(wc.node, cp2.parent.key) : null;  // rijenlijst
      if (cp2 != null && cp3 != null) {
        final coachRow = cp2.parent;
        final list = cp3.parent;
        final idx = list.children.indexWhere((c) => identical(c, coachRow));
        list.children.insert(idx >= 0 ? idx + 1 : list.children.length,
            infoRow('Vlagger', 'matchVlaggerName'));
      }
    }

    // De api-status ("OK"/"FAILED") was een debug-readout bovenaan de tab.
    // Verwijderen (ook eerder ingevoegde exemplaren van vorige pushes).
    final statusParent = findDescendants(wc.node, (n) =>
        n.children.any((c) => c.name == 'MatchApiStatus')).firstOrNull;
    statusParent?.children.removeWhere((c) => c.name == 'MatchApiStatus');
    return;
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

// Forces the Info tab content on WedstrijdDetailPage to be full-width.
// The outer Column and inner Column default to crossAxisAlignment:start,
// making the info rows narrow. Setting stretch + infinity width fixes it.
void _fixMatchInfoWidth(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;

  // Outer column wrapping the info tab content (Column_4kr3x84l).
  final outerCol = findByKey(wc.node, 'Column_4kr3x84l');
  if (outerCol != null) {
    final col = outerCol.props.column.deepCopy();
    col.crossAxisAlignment = FFCrossAxisAlignment.cross_axis_stretch;
    outerCol.props.column = col;
  }

  // Unnamed container that wraps the inner info column (Container_9m0z4oza).
  final wrapper = findByKey(wc.node, 'Container_9m0z4oza');
  if (wrapper != null) {
    final c = wrapper.props.container.deepCopy();
    final dims = c.hasDimensions() ? c.dimensions.deepCopy() : FFDimensions();
    dims.width = FFDim(pixelsValue: FFDoubleValue(inputValue: double.infinity));
    c.dimensions = dims;
    wrapper.props.container = c;
  }

  // Inner column that holds all MatchInfoRow_* children (Column_gj4yosa2).
  final innerCol = findByKey(wc.node, 'Column_gj4yosa2');
  if (innerCol != null) {
    final col = innerCol.props.column.deepCopy();
    col.crossAxisAlignment = FFCrossAxisAlignment.cross_axis_stretch;
    innerCol.props.column = col;
  }
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

// Applies the club primary color (from AppState.primaryColor) to every
// FFButtonWidget across all pages: fills the button with the club color,
// sets button text to white, and ensures generous inner padding.
void _applyBrandingToAllButtons(FFProject project) {
  final primaryColorId = _findAppStateFieldId(project, 'primaryColor');
  if (primaryColorId == null) return;

  final clubColor = colorFromStringVar(varFromAppState(primaryColorId.deepCopy()));
  final whiteColor = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND),
  );
  final padding = FFPadding(
    type: FFPadding_PaddingType.FF_PADDING_ONLY,
    legacyTop: 16.0,
    legacyBottom: 16.0,
    legacyLeft: 24.0,
    legacyRight: 24.0,
  );

  for (final wc in project.widgetClasses.values.where((wc) => wc.isPage)) {
    for (final btn in findDescendants(wc.node, (n) => n.props.hasButton())) {
      // Destructieve knop houdt zijn eigen (rode) kleur — niet overschrijven.
      if (btn.name == 'DeleteAccountButton') continue;
      final proto = btn.props.button.deepCopy();
      proto.fillColorValue = clubColor.deepCopy();
      proto.innerPadding = padding.deepCopy();
      if (proto.hasText()) {
        final text = proto.text.deepCopy();
        text.colorValue = whiteColor.deepCopy();
        proto.text = text;
      }
      btn.props.button = proto;
    }
  }
}

// Applies the club's primary color (from app state) to every AppBar background
// and sets the back button + title text color to white for maximum contrast.
// Itereert over ALLE pagina's met een AppBar — geen hardcoded lijst, zodat
// nieuwe pages automatisch consistent zijn met de club-branding.
// Zet het clublogo rechtsboven in elke AppBar (sticky, want AppBars staan vast
// bovenaan). Gebonden aan AppState.clubLogoUrl; alleen zichtbaar als die gevuld
// is. Idempotent: slaat over als het logo al in de AppBar staat.
void _addClubLogoToAppBars(FFProject project) {
  final logoId = _findAppStateFieldId(project, 'clubLogoUrl');
  if (logoId == null) return;

  for (final wc in project.widgetClasses.values) {
    if (!wc.hasPageRouteSettings()) continue; // alleen pagina's
    final appBarNode = getPropertyChild(wc.node, 'appBar');
    if (appBarNode == null) continue;
    // Bestaat het logo al (vorige push)? Dan de node ter plekke bijwerken naar
    // een rechthoekige Image met CONTAIN-fit i.p.v. het ronde CircleImage/COVER.
    final existingLogo =
        findDescendants(appBarNode, (n) => n.name == 'ClubLogoAppBar');
    if (existingLogo.isNotEmpty) {
      final node = existingLogo.first;
      node.type = FFWidgetType.Image;
      node.props.image.fit = FFBoxFit.FF_BOX_FIT_CONTAIN;
      node.props.image.dimensions = FFDimensions(
        width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 44.0)),
        height: FFDim(pixelsValue: FFDoubleValue(inputValue: 36.0)),
      );
      continue;
    }

    // Rechthoekige image met CONTAIN-fit: het hele logo blijft zichtbaar
    // (niet rond bijgesneden zoals bij CircleImage/COVER).
    final logoImage = FFNode(
      key: generateRandomAlphaNumericString(),
      type: FFWidgetType.Image,
      name: 'ClubLogoAppBar',
      props: FFWidgetProperties(
        image: FFImage(
          type:      FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
          pathValue: FFStringValue(
            variable: varFromAppState(logoId.deepCopy())
              ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
          ),
          fit:    FFBoxFit.FF_BOX_FIT_CONTAIN,
          cached: true,
          dimensions: FFDimensions(
            width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 44.0)),
            height: FFDim(pixelsValue: FFDoubleValue(inputValue: 36.0)),
          ),
        ),
      ),
    );

    final wrapper = UI.container(
      name: 'ClubLogoAppBarWrap',
      padding: UIEdgeInsets.only(right: 12),
      child: logoImage,
    );
    final hasLogo = conditionVar(
      varFromAppState(logoId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
    setConditionalVisibility(wrapper, variable: hasLogo);

    appBarNode.children.add(wrapper);
    final existing = appBarNode.childPropertyMap['actions'];
    if (existing != null) {
      existing.keyRefs.add(FFNodeKeyReference(key: wrapper.key));
    } else {
      appBarNode.childPropertyMap['actions'] =
          FFChildrenKeys(keyRefs: [FFNodeKeyReference(key: wrapper.key)]);
    }
  }
}

void _applyBrandingToAllAppBars(FFProject project) {
  final primaryColorId = _findAppStateFieldId(project, 'primaryColor');
  if (primaryColorId == null) return;

  final brandingBg = colorFromStringVar(varFromAppState(primaryColorId.deepCopy()));
  final whiteColor = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND),
  );

  for (final wc in project.widgetClasses.values) {
    // Skip non-pages (components) — pages hebben een pageRouteSettings veld.
    if (!wc.hasPageRouteSettings()) continue;
    final appBarNode = getPropertyChild(wc.node, 'appBar');
    if (appBarNode == null) continue;

    final proto = appBarNode.props.appBar.deepCopy();
    proto.backgroundColorValue = brandingBg.deepCopy();
    proto.backButtonColorValue = whiteColor.deepCopy();
    appBarNode.props.appBar = proto;

    // Title text → wit voor contrast tegen donkere primary-bg.
    final titleNode = getPropertyChild(appBarNode, 'title');
    if (titleNode != null && titleNode.props.hasText()) {
      final textProto = titleNode.props.text.deepCopy();
      textProto.colorValue = whiteColor.deepCopy();
      titleNode.props.text = textProto;
    }
  }
}

void _debugStructsAndEndpoints(FFProject project) {
  final endpoints = project.backend.apiConfig.apiGroups
      .expand((g) => g.endpoints)
      .map((e) => e.identifier.name)
      .toList();
  stderr.writeln('[DEBUG] API endpoints: ${endpoints.join(", ")}');
  for (final structName in ['FootMatch', 'BarDuty', 'ClubBranding']) {
    final s = project.backend.dataSchemaConfig.dataStructs
        .cast<FFDataStruct?>()
        .firstWhere((x) => x?.identifier.name == structName, orElse: () => null);
    if (s == null) {
      stderr.writeln('[DEBUG] $structName struct: NOT FOUND');
    } else {
      stderr.writeln('[DEBUG] $structName fields: ${s.fields.map((f) => f.identifier.name).join(", ")}');
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

/// Builds an FFVariable that reads a JSON path from an API call's response body.
/// Use instead of accessDataStructField when the endpoint has no response data struct.
FFVariable _jsonBodyVar(dynamic ctx, String jsonPath, String nodeKey) {
  final actionKey = (ctx as dynamic).actionKey as String;
  final outputVarName = (ctx as dynamic).outputVarName as String;
  final v = FFVariable(
    source: FFVariableSource.ACTION_OUTPUTS,
    baseVariable: FFBaseVariable(
      actionOutput: FFActionOutputVariable(
        actionKeyRef: FFActionKeyReference(key: actionKey),
        outputVariableIdentifier: FFIdentifier(name: outputVarName),
      ),
    ),
    operations: [
      FFVariableOperation(
        apiResponseField: FFApiResponseField(
          responseField: FFApiResponseField_ResponseField.JSON_BODY,
        ),
      ),
      FFVariableOperation(
        jsonPathOperation: FFJsonPathOperation(
          jsonPath: jsonPath,
          returnParameter: FFParameter(
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          ),
        ),
      ),
    ],
  );
  v.nodeKeyRef = FFNodeKeyReference(key: nodeKey);
  return v;
}

// ─── Banner feature ───────────────────────────────────────────────────────────

void _addBannerEndpoint(FFProject project) {
  const groupName    = 'VoetbalPlannerAPI';
  const endpointName = 'GetBanners';

  if (findApiEndpoint(project, name: endpointName, groupName: groupName) != null) return;

  addEndpointToGroup(
    project,
    groupName:                groupName,
    name:                     endpointName,
    url:                      '/banners?position=[position]',
    method:                   FFApiEndpoint_CallType.GET,
    bodyType:                 FFApiEndpoint_BodyType.NONE,
    headers:                  ['Authorization: Bearer [bearerToken]'],
    variables:                {'position': FFDataTypeV2(scalarType: FFBaseDataType.String)},
    responseDataStructName:   'Banner',
    responseDataStructIsList: true,
  );
}

// ─── Nieuwsfeed feature ─────────────────────────────────────────────────────

void _addNewsEndpoint(FFProject project) {
  const groupName    = 'VoetbalPlannerAPI';
  const endpointName = 'GetNews';
  if (findApiEndpoint(project, name: endpointName, groupName: groupName) != null) return;

  addEndpointToGroup(
    project,
    groupName:                groupName,
    name:                     endpointName,
    url:                      '/news',
    method:                   FFApiEndpoint_CallType.GET,
    bodyType:                 FFApiEndpoint_BodyType.NONE,
    headers:                  ['Authorization: Bearer [bearerToken]'],
    responseDataStructName:   'NewsItem',
    responseDataStructIsList: true,
  );
}

// Build NewsPage skeleton. State field 'newsItems' (List<NewsItem>) wordt via
// raw mutation toegevoegd (_ensureNewsPageNewsItemsState) zodat de eerste push
// niet afhankelijk is van een typed SDK refresh voor ff.Structs.newsItem.
void _buildNewsPage(App app) {
  app.ensurePage(
    'NewsPage',
    description: 'Nieuwsfeed van de club met titel, datum, categorie, afbeelding en tekst.',
    route: 'nieuws',
    state: {
      'isLoading': bool_.withDefault(true),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Nieuws'),
      body: Column(
        name: 'NewsRootColumn',
        children: [
          Container(name: 'NewsBodyPlaceholder'),
        ],
      ),
    ),
  );
}

// Ensures the NewsPage has a state field 'newsItems' typed as List<NewsItem>.
void _ensureNewsPageNewsItemsState(FFProject project) {
  final wc = findPage(project, name: 'NewsPage');
  if (wc == null) return;
  final exists = wc.classModel.stateFields.any(
    (f) => f.parameter.identifier.name == 'newsItems',
  );
  if (exists) return;

  final struct = findDataStruct(project, name: 'NewsItem');
  if (struct == null) return;

  final param = FFParameter(
    identifier: FFIdentifier(
      name: 'newsItems',
      key: generateRandomAlphaNumericString(),
    ),
    dataType: dataStructType(struct.identifier.deepCopy()),
  );
  param.isList = true;
  wc.classModel.stateFields.add(FFWidgetClassStateField(parameter: param));
}

// Wires GetNews onLoad: stores response in newsItems state + clears isLoading.
void _wireNewsPageLoad(FFProject project) {
  final wc = findPage(project, name: 'NewsPage');
  if (wc == null) return;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  Actions.onPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetNews',
      groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'newsLoad',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'NewsPage',
          updates: [
            StateFieldUpdate.set('isLoading', 'false'),
            StateFieldUpdate.setFromVariable('newsItems', ctx.responseVar),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'NewsPage',
          updates: [StateFieldUpdate.set('isLoading', 'false')],
        ),
        Actions.snackBar('Kon nieuws niet laden.'),
      ]),
    ),
  );
}

// Build the body of NewsPage: scrollable Column with a dynamic ListView of news
// cards (titel + dagen-oud subtitle + category-chip + image + body text).
void _buildNewsListBody(FFProject project) {
  final wc = findPage(project, name: 'NewsPage');
  if (wc == null) return;
  final rootCol = findDescendants(wc.node, (n) => n.name == 'NewsRootColumn').firstOrNull;
  if (rootCol == null) return;

  // Idempotent: skip when list already built.
  if (findDescendants(wc.node, (n) => n.name == 'NewsList').isNotEmpty) return;

  // Make the root column scrollable.
  if (!rootCol.props.column.scrollable) {
    final colCopy = rootCol.props.column.deepCopy();
    colCopy.scrollable = true;
    rootCol.props.column = colCopy;
  }

  final newsItemsId = _findPageStateFieldId(project, 'NewsPage', 'newsItems');
  if (newsItemsId == null) return;

  final newsVar = varFromPageState(newsItemsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final listView = UI.listView(
    name: 'NewsList',
    spacing: 12,
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 12),
    dynamicSource: DynamicSource(variable: newsVar, itemName: 'news'),
  );
  // shrinkWrap so it works inside a scrollable Column.
  final lvCopy = listView.props.listView.deepCopy();
  lvCopy.shrinkWrapValue = FFBooleanValue(inputValue: true);
  listView.props.listView = lvCopy;

  // Title (large, bold)
  final titleText = UI.text('', name: 'NewsCardTitle', style: UITextStyle.titleMedium);
  titleText.props.text.textValue = FFStringValue(
    variable: generatorVarField(listView.key, 'title'),
  );

  // Subtitle (X dagen geleden)
  final subtitleText = UI.text('', name: 'NewsCardSubtitle', style: UITextStyle.bodySmall);
  subtitleText.props.text.textValue = FFStringValue(
    variable: generatorVarField(listView.key, 'subtitle'),
  );
  final subTxt = subtitleText.props.text.deepCopy();
  subTxt.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
  );
  subtitleText.props.text = subTxt;

  // Category chip — small pill with categoryLabel.
  final categoryText = UI.text('', name: 'NewsCardCategoryText', style: UITextStyle.labelSmall);
  categoryText.props.text.textValue = FFStringValue(
    variable: generatorVarField(listView.key, 'categoryLabel'),
  );
  final catTxt = categoryText.props.text.deepCopy();
  catTxt.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY),
  );
  categoryText.props.text = catTxt;
  final categoryChip = UI.container(
    name: 'NewsCardCategoryChip',
    padding: UIEdgeInsets.symmetric(horizontal: 8, vertical: 2),
    borderRadius: 10,
    child: categoryText,
  );
  _setContainerColor(
    categoryChip,
    FFColorValue(inputValue: FFColor(value: Int64(0xFFDCFCE7))),
  );
  // Wrap in a Row so the chip doesn't stretch to full width.
  final categoryRow = UI.row(
    name: 'NewsCardCategoryRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [categoryChip],
  );

  // Image — visible only when imageUrl is non-empty.
  final imageNode = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Image,
    name: 'NewsCardImage',
    props: FFWidgetProperties(
      image: FFImage(
        type:       FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
        pathValue:  FFStringValue(
          variable: generatorVarField(listView.key, 'imageUrl'),
        ),
        fit:        FFBoxFit.FF_BOX_FIT_COVER,
        cached:     true,
        dimensions: FFDimensions(
          width:  FFDim(pixelsValue: FFDoubleValue(inputValue: double.infinity)),
          height: FFDim(pixelsValue: FFDoubleValue(inputValue: 180.0)),
        ),
      ),
    ),
  );
  setConditionalVisibility(
    imageNode,
    variable: conditionVar(
      generatorVarField(listView.key, 'imageUrl'),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable,
  );

  // Body text
  final bodyText = UI.text('', name: 'NewsCardBody', style: UITextStyle.bodyMedium);
  bodyText.props.text.textValue = FFStringValue(
    variable: generatorVarField(listView.key, 'body'),
  );

  // Card container — secondary background with rounded corners.
  final card = UI.container(
    name: 'NewsCard',
    padding: UIEdgeInsets.all(14),
    borderRadius: 10,
    color: UIColor.secondaryBackground,
    child: UI.column(
      name: 'NewsCardColumn',
      crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 8,
      children: [
        titleText,
        subtitleText,
        categoryRow,
        imageNode,
        bodyText,
      ],
    ),
  );

  listView.children.add(card);

  // Empty-state text — shown only when there are no items.
  final emptyText = UI.text(
    'Nog geen nieuws beschikbaar.',
    name: 'NewsEmptyText',
    style: UITextStyle.bodyMedium,
  );
  final emptyTxt = emptyText.props.text.deepCopy();
  emptyTxt.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
  );
  emptyText.props.text = emptyTxt;
  final emptyContainer = UI.container(
    name: 'NewsEmptyContainer',
    padding: UIEdgeInsets.all(24),
    child: emptyText,
  );
  final lengthVar = varFromPageState(newsItemsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)
    ..operations.add(FFVariableOperation(listNumItems: FFListNumItems()));
  final emptyVar = FFVariable(
    source: FFVariableSource.FUNCTION_CALL,
    functionCall: FFFunctionCall(
      condition: FFCondition(relation: FFCondition_Relation.EQUAL_TO),
      values: [
        FFValue(variable: lengthVar),
        FFValue(inputValue: FFParameterValue(serializedValue: '0')),
      ],
    ),
  );
  setConditionalVisibility(emptyContainer, variable: emptyVar);

  // Replace placeholder with listView + empty-state.
  final placeholder = findDescendants(rootCol, (n) => n.name == 'NewsBodyPlaceholder').firstOrNull;
  if (placeholder != null) removeByKey(rootCol, placeholder.key);
  rootCol.children.add(listView);
  rootCol.children.add(emptyContainer);
}

// Adds bannerImageUrl/bannerLinkUrl state fields to WedstrijdenPage and inserts
// a banner Image widget before WelcomeGreetingContainer. Wires the GetBanners
// API call as an additive page-load trigger so the existing matches load is unaffected.
void _addBannerToWedstrijdenPage(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;

  _ensurePageStateField(wc, 'bannerImageUrl', FFBaseDataType.String);
  _ensurePageStateField(wc, 'bannerLinkUrl',  FFBaseDataType.String);

  // Insert (or re-insert) the BannerContainer before WelcomeGreetingContainer.
  final existing = findDescendants(wc.node, (n) => n.name == 'WedstrijdenBannerContainer');
  if (existing.isEmpty) {
    final bannerNode = _buildBannerImageNode(
      project,
      wc,
      containerName: 'WedstrijdenBannerContainer',
      imageName: 'WedstrijdenBannerImage',
      imageUrlFieldName: 'bannerImageUrl',
    );
    final welcomeContainer = findDescendants(
      wc.node, (n) => n.name == 'WelcomeGreetingContainer',
    ).firstOrNull;
    if (welcomeContainer != null) {
      insertBeforeKey(wc.node, welcomeContainer.key, bannerNode);
    }
  } else {
    // Re-apply visibility so expression changes (e.g. null-safety fix) take effect
    // on already-existing containers from a prior push.
    _applyBannerContainerVisibility(wc, existing.first, 'bannerImageUrl');
  }

  _wireBannerPageLoad(project, wc, 'WedstrijdenPage', 'wedstrijden');
}

// Wraps BardienPage body in a Column (same pattern as _restructureWedstrijdenPageBody),
// then inserts a banner Image widget as the first child.
void _addBannerToBardienPage(FFProject project) {
  final wc = findPage(project, name: 'BardienPage');
  if (wc == null) return;

  _ensurePageStateField(wc, 'bannerImageUrl', FFBaseDataType.String);
  _ensurePageStateField(wc, 'bannerLinkUrl',  FFBaseDataType.String);

  // Wrap body in Column if not already done.
  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild != null && bodyChild.type != FFWidgetType.Column) {
    final bodyColumn = UI.column(name: 'BardienBodyColumn', mainAxisMin: false);
    UI.expanded(bodyChild);
    bodyColumn.children.add(bodyChild);
    final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
    if (idx >= 0) wc.node.children[idx] = bodyColumn;
    wc.node.childPropertyMap['body'] = FFChildrenKeys(
      keyRefs: [FFNodeKeyReference(key: bodyColumn.key)],
    );
  }

  // Insert (or re-insert) banner as first child of the body Column.
  final bodyCol = getPropertyChild(wc.node, 'body');
  if (bodyCol == null) return;

  final existing = findDescendants(wc.node, (n) => n.name == 'BardienBannerContainer');
  if (existing.isEmpty) {
    final bannerNode = _buildBannerImageNode(
      project,
      wc,
      containerName: 'BardienBannerContainer',
      imageName: 'BardienBannerImage',
      imageUrlFieldName: 'bannerImageUrl',
    );
    bodyCol.children.insert(0, bannerNode);
  } else {
    // Re-apply visibility so expression changes (e.g. null-safety fix) take effect
    // on already-existing containers from a prior push.
    _applyBannerContainerVisibility(wc, existing.first, 'bannerImageUrl');
  }

  _wireBannerPageLoad(project, wc, 'BardienPage', 'bardiensten');
}

// Mirror van _addBannerToBardienPage maar voor RijschemaPage.
// Voegt bannerImageUrl/bannerLinkUrl state-velden toe, wrapt de body in een
// Column als die dat nog niet is, en plaatst de banner als eerste child.
void _addBannerToRijschemaPage(FFProject project) {
  final wc = findPage(project, name: 'RijschemaPage');
  if (wc == null) return;

  _ensurePageStateField(wc, 'bannerImageUrl', FFBaseDataType.String);
  _ensurePageStateField(wc, 'bannerLinkUrl',  FFBaseDataType.String);

  // Wrap body in Column if not already done.
  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild != null && bodyChild.type != FFWidgetType.Column) {
    final bodyColumn = UI.column(name: 'RijschemaBodyColumn', mainAxisMin: false);
    UI.expanded(bodyChild);
    bodyColumn.children.add(bodyChild);
    final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
    if (idx >= 0) wc.node.children[idx] = bodyColumn;
    wc.node.childPropertyMap['body'] = FFChildrenKeys(
      keyRefs: [FFNodeKeyReference(key: bodyColumn.key)],
    );
  }

  // Insert (or re-insert) banner as first child of the body Column.
  final bodyCol = getPropertyChild(wc.node, 'body');
  if (bodyCol == null) return;

  final existing = findDescendants(wc.node, (n) => n.name == 'RijschemaBannerContainer');
  if (existing.isEmpty) {
    final bannerNode = _buildBannerImageNode(
      project,
      wc,
      containerName: 'RijschemaBannerContainer',
      imageName: 'RijschemaBannerImage',
      imageUrlFieldName: 'bannerImageUrl',
    );
    bodyCol.children.insert(0, bannerNode);
  } else {
    _applyBannerContainerVisibility(wc, existing.first, 'bannerImageUrl');
  }

  _wireBannerPageLoad(project, wc, 'RijschemaPage', 'rijschema');
}

// Re-applies null-safe conditional visibility on any banner container node.
// Separating this from _buildBannerImageNode lets the page functions call it
// on both newly-created AND already-existing containers so that expression
// updates propagate on every push, not just the first one.
void _applyBannerContainerVisibility(
  FFWidgetClass wc,
  FFNode containerNode,
  String imageUrlFieldName,
) {
  final stateField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == imageUrlFieldName,
        orElse: () => null,
      );
  if (stateField == null) return;

  final urlVar = varFromPageState(stateField.parameter.identifier.deepCopy());
  urlVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final visibleBool = codeExpressionVar(
    expression: "(url ?? '').isNotEmpty && url != 'null'",
    arguments: [
      CodeExpressionArg(
        name: 'url',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: urlVar.deepCopy()),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
  );
  setConditionalVisibility(containerNode, variable: visibleBool);
}

// Builds a Container > Image node bound to a page state string field.
// The container is conditionally visible when the imageUrl field is non-empty.
FFNode _buildBannerImageNode(
  FFProject project,
  FFWidgetClass wc, {
  required String containerName,
  required String imageName,
  required String imageUrlFieldName,
}) {
  // Find the state field identifier for the imageUrl string.
  final stateField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == imageUrlFieldName,
        orElse: () => null,
      );

  final imageNode = UI.image(
    '',
    isNetwork: true,
    width: double.infinity,
    height: 120,
    fit: UIBoxFit.cover,
    name: imageName,
  );

  if (stateField != null) {
    final urlVar = varFromPageState(stateField.parameter.identifier.deepCopy());
    urlVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    imageNode.props.image.pathValue = FFStringValue(variable: urlVar);

    // Conditionally show the container only when bannerImageUrl is not empty.
    // Use null-safe expression: state fields added dynamically may be String? in codegen.
    final visibleBool = codeExpressionVar(
      expression: "(url ?? '').isNotEmpty && url != 'null'",
      arguments: [
        CodeExpressionArg(
          name: 'url',
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: urlVar.deepCopy()),
        ),
      ],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
    );
    final container = UI.container(
      name: containerName,
      padding: UIEdgeInsets.symmetric(horizontal: 8, vertical: 8),
      width: double.infinity,
      child: imageNode,
    );
    setConditionalVisibility(container, variable: visibleBool);
    return container;
  }

  return UI.container(
    name: containerName,
    padding: UIEdgeInsets.symmetric(horizontal: 8, vertical: 8),
    width: double.infinity,
    child: imageNode,
  );
}

// Appends [actionToAppend] to the END of the first ON_INIT_STATE trigger chain.
// addTriggerChain() always adds a NEW trigger — FlutterFlow codegen only processes
// the first ON_INIT_STATE trigger and silently drops all subsequent ones.
// This helper instead walks followUpAction links to find the tail, then appends there.
void _appendToFirstPageLoadChain(FFNode node, FFActionNode actionToAppend) {
  final existingIdx = node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  if (existingIdx < 0) {
    Actions.onPageLoadChain(node, actionToAppend);
    return;
  }

  final existingTrigger = node.triggerActions[existingIdx];
  // DeepCopy so we never mutate a potentially-frozen proto.
  final chainCopy = existingTrigger.rootAction.deepCopy();

  var last = chainCopy;
  while (last.hasFollowUpAction()) {
    last = last.followUpAction;
  }
  last.followUpAction = actionToAppend;

  node.triggerActions[existingIdx] = FFTriggerActions(
    trigger: existingTrigger.trigger.deepCopy(),
    rootAction: chainCopy,
  );
}

// Forceert compatibele versies van twee FlutterFlow-default packages die niet
// meer compileren op recente Flutter (Flutter 3.44) — hierop faalde de web-deploy:
//   - font_awesome_flutter 10.7.0: extte IconData, dat nu een `final` class is
//     → 11.0.0 stopt met IconData extenden. Dit project gebruikt 0 FA-iconen,
//     dus de 11.0.0 breaking change (FaIcon vereist FaIconData) raakt niets.
//   - page_transition 2.1.0: gebruikte de verwijderde CupertinoPageTransitionsBuilder
//     → 2.2.2 gefixt. FF gebruikt alleen PageTransition/PageTransitionType (stabiel).
// Toegevoegd als custom pubspec-dependency (dependency_overrides via customCode
// kwam niet in de gegenereerde pubspec terecht; addPubDependency wél). FF merget
// custom deps over de base-versie. Idempotent: update als hij al in de custom-
// lijst staat, anders toevoegen.
void _fixIncompatiblePubVersions(FFProject project) {
  // page_transition 2.1.0 gebruikt de verwijderde CupertinoPageTransitionsBuilder
  // → 2.2.2 gefixt. FF gebruikt alleen PageTransition/PageTransitionType (stabiel).
  _ensurePubDepVersion(project, 'page_transition', '^2.2.2');
  // font_awesome_flutter NIET bumpen: 11.0.0 (de enige versie met de IconData-
  // final-fix) breekt FF's eigen base-code (FaIcon vereist dan FaIconData i.p.v.
  // IconData). Verwijder een eerder toegevoegde custom-dep zodat de FF-default
  // (10.7.0) weer geldt.
  try { removePubDependency(project, name: 'font_awesome_flutter'); } catch (_) {}
  // BELANGRIJK: verwijder ook achtergebleven dependency_OVERRIDES. Die worden
  // NIET in de lokale snapshot-pubspec gerenderd, maar WEL door FF's deploy
  // toegepast — en overrides winnen áltijd. Een eerdere addDependencyOverride
  // voor font_awesome 11.0.0 bleef hangen waardoor FF's deploy met 11.0.0 bouwde
  // (FaIconData-fout in flutter_flow_icon_button/widgets).
  try { removeDependencyOverride(project, name: 'font_awesome_flutter'); } catch (_) {}
  try { removeDependencyOverride(project, name: 'page_transition'); } catch (_) {}
}

void _ensurePubDepVersion(FFProject project, String name, String version) {
  if (findPubDependency(project, name: name) != null) {
    updatePubDependency(project, name: name, newVersion: version);
  } else {
    addPubDependency(project, name: name, version: version);
  }
}

// Hangt een SubscribeToChatTopics-call achter de WedstrijdenPage page-load chain.
// WedstrijdenPage is de universele post-login landingspagina (zowel admin-login
// als magic-link navigeren ernaartoe), dus een vers ingelogde gebruiker is meteen
// geabonneerd op zijn chat-push-topics — ook vóór hij de Chats-tab opent.
// Idempotent: skipt als de ON_INIT_STATE chain de call al bevat (anders dupliceert
// elke push de call → FF-validator-conflicten).
void _wireChatTopicSubscription(FFProject project) {
  final action = findCustomAction(project, name: 'SubscribeToChatTopics');
  if (action == null) return;
  final wc = findPage(project, name: 'WedstrijdenPage');
  if (wc == null) return;

  bool callsSubscribe(FFActionNode n) {
    if (n.hasAction() &&
        n.action.hasCustomAction() &&
        n.action.customAction.customActionIdentifier.name == action.identifier.name) {
      return true;
    }
    if (n.hasFollowUpAction() && callsSubscribe(n.followUpAction)) return true;
    if (n.hasConditionActions()) {
      for (final ta in n.conditionActions.trueActions) {
        if (ta.hasTrueAction() && callsSubscribe(ta.trueAction)) return true;
      }
      if (n.conditionActions.hasFalseAction() &&
          callsSubscribe(n.conditionActions.falseAction)) return true;
    }
    return false;
  }

  final existingIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  if (existingIdx >= 0) {
    final trig = wc.node.triggerActions[existingIdx];
    if (trig.hasRootAction() && callsSubscribe(trig.rootAction)) return;
  }

  // Bouw de node met FFCustomActionCall (customAction-veld) — consistent met de
  // rest van de codebase (login/getOrCreate) én met de callsSubscribe-check
  // hierboven. (Actions.callCustomAction zou customCodeCall produceren, waardoor
  // de idempotency-check niet matcht en elke push een duplicaat toevoegt.)
  _appendToFirstPageLoadChain(
    wc.node,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: action.identifier.deepCopy(),
        ),
      ),
    ),
  );
}

// Appends a GetBanners API call to the end of the page's existing page-load chain.
// Uses _appendToFirstPageLoadChain so it lands in the same SchedulerBinding callback
// as the existing matches/duties load, not in a second (dead) trigger.
void _wireBannerPageLoad(
  FFProject project,
  FFWidgetClass wc,
  String widgetClassName,
  String position,
) {
  // Idempotent: skip if the ON_INIT_STATE chain already contains a GetBanners
  // API call. Without this, every push appends another call → output names
  // (bannersLoad_<widget>) collide and the validator rejects the project.
  final existingIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  if (existingIdx >= 0) {
    bool hasGetBanners(FFActionNode n) {
      if (n.hasAction() &&
          n.action.hasDatabase() &&
          n.action.database.hasApiCall() &&
          n.action.database.apiCall.hasEndpointIdentifier() &&
          n.action.database.apiCall.endpointIdentifier.name == 'GetBanners') {
        return true;
      }
      if (n.hasFollowUpAction() && hasGetBanners(n.followUpAction)) return true;
      if (n.hasConditionActions()) {
        for (final ta in n.conditionActions.trueActions) {
          if (ta.hasTrueAction() && hasGetBanners(ta.trueAction)) return true;
        }
      }
      return false;
    }
    final trig = wc.node.triggerActions[existingIdx];
    if (trig.hasRootAction() && hasGetBanners(trig.rootAction)) return;
  }

  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetBanners',
      groupName: 'VoetbalPlannerAPI',
      // Page-specific output name vermijdt naam-conflicten als meerdere pages
      // (Wedstrijden/Bardien/Rijschema) banner-calls hebben.
      outputVariableName: 'bannersLoad_$widgetClassName',
      nodeKey: wc.node.key,
      variables: {'position': position},
      onSuccess: (ctx) {
        const fieldPaths = {
          'bannerImageUrl': r'$[0].imageUrl',
          'bannerLinkUrl':  r'$[0].linkUrl',
        };
        final updates = <StateFieldUpdate>[];
        for (final entry in fieldPaths.entries) {
          final v = _jsonBodyVar(ctx, entry.value, wc.node.key);
          updates.add(StateFieldUpdate.setFromVariable(entry.key, v));
        }
        return Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: widgetClassName,
            updates: updates,
          ),
        ]);
      },
    ),
  );
}

// ─── ChatsPage (chat hub) ─────────────────────────────────────────────────────

void _buildChatsPage(
  App app,
  FirestoreCollectionHandle chatConversations,
  StructHandle swapMember,
) {
  app.ensurePage(
    'ChatsPage',
    description: 'Chat hub: alle gesprekken (teamchat, direct en staffgroepen).',
    route: 'chats',
    state: {
      'conversations': listOf(chatConversations),
      'teamMembers':   listOf(swapMember),
      'isLoading':     bool_.withDefault(false),
    },
    body: Column(
      children: [
        // ── Gesprekken ────────────────────────────────────────────────────────
        Container(
          padding: 12,
          child: Text('Gesprekken', style: Styles.titleSmall),
        ),
        // Conversations list placeholder — filled by _wireChatsPageConversationsList
        Container(name: 'ChatsGroupsListContainer'),
        // ── Direct bericht sturen ─────────────────────────────────────────────
        Container(
          padding: 12,
          child: Text('Direct bericht', style: Styles.titleSmall),
        ),
        // Member strip placeholder — filled by _wireChatsPageMemberStrip via app.raw()
        Container(name: 'ChatsDirectStripContainer'),
      ],
    ),
  );
}

// Adds groups ListView to the ChatsGroupsListContainer placeholder on ChatsPage.
// Uses DynamicSource bound to 'chatGroups' state field.
void _wireChatsPageGroupsList(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final container = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsListContainer').firstOrNull;
  if (container == null) return;

  // Idempotent: skip only if the groups list is already present (not just any child).
  if (findDescendants(container, (n) => n.name == 'ChatsGroupsList').isNotEmpty) return;

  final chatGroupsId = _findPageStateFieldId(project, 'ChatsPage', 'chatGroups');
  if (chatGroupsId == null) return;

  final chatGroupsVar = varFromPageState(chatGroupsId.deepCopy());
  chatGroupsVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final groupList = UI.listView(
    name: 'ChatsGroupsList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: chatGroupsVar, itemName: 'group'),
  );

  // chatGroups items are Firestore documents — use accessDocumentField (not accessDataStructField).
  final nameFieldId = findCollectionField(project, collectionName: 'chatGroups', fieldName: 'name');

  FFVariable _docField(String fieldName) {
    final field = findCollectionField(project, collectionName: 'chatGroups', fieldName: fieldName);
    if (field != null) {
      return varFromGeneratorVariable(groupList.key)
        ..operations.add(FFVariableOperation(
          accessDocumentField: FFAccessDocumentField(
            fieldIdentifier: field.identifier.deepCopy(),
          ),
        ));
    }
    return generatorVarField(groupList.key, fieldName);
  }

  // Tap navigates to GroupChatPage with groupId = group name (used as unique key).
  final navigateAction = Actions.navigate(
    project,
    pageName: 'GroupChatPage',
    params: {
      'groupId':   VariableParamValue(_docField('name')),
      'groupName': VariableParamValue(_docField('name')),
    },
  );

  final nameText = UI.text('', name: 'GroupChipName', style: UITextStyle.bodyMedium);
  nameText.props.text.textValue = FFStringValue(variable: _docField('name'));

  final groupRow = UI.row(
    name: 'GroupChipRow',
    spacing: 12,
    children: [
      UI.icon('forum', size: 18, color: UIColor.primary),
      nameText,
      UI.icon('chevron_right', size: 18, color: UIColor.secondaryText),
    ],
  );

  final groupCard = UI.container(
    name: 'GroupChip',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: groupRow,
  );
  Actions.onTap(groupCard, navigateAction);

  groupList.children.add(groupCard);
  container.children.add(groupList);
}

// Adds a horizontal member strip to the ChatsDirectStripContainer on ChatsPage.
// Tapping a member: stages pendingDirectUserId/Name in AppState → calls
// GetOrCreateDirectConversation → navigates to ChatDetailPage.
void _wireChatsPageMemberStrip(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final container = findDescendants(wc.node, (n) => n.name == 'ChatsDirectStripContainer').firstOrNull;
  if (container == null) return;

  // Idempotent: skip if strip is already added.
  if (container.children.isNotEmpty) return;

  final teamMembersId = _findPageStateFieldId(project, 'ChatsPage', 'teamMembers');
  if (teamMembersId == null) return;

  final pendingUserIdId    = _findAppStateFieldId(project, 'pendingDirectUserId');
  final pendingUserEmailId = _findAppStateFieldId(project, 'pendingDirectUserEmail');
  final pendingUserNameId  = _findAppStateFieldId(project, 'pendingDirectUserName');
  final currentConvId      = _findAppStateFieldId(project, 'currentConversationId');
  if (pendingUserIdId == null || pendingUserEmailId == null || pendingUserNameId == null || currentConvId == null) return;

  final getOrCreate = findCustomAction(project, name: 'GetOrCreateDirectConversation');
  if (getOrCreate == null) return;

  final teamMembersVar = varFromPageState(teamMembersId.deepCopy());
  teamMembersVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final memberList = UI.listView(
    name: 'ChatsDirectMemberList',
    horizontal: true,
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: teamMembersVar, itemName: 'member'),
  );

  // Tap chain:
  //   1. UpdateAppState pendingDirectUserId    = member.externalId (lidnummer)
  //   2. UpdateAppState pendingDirectUserEmail = member.email (fallback)
  //   3. UpdateAppState pendingDirectUserName  = member.name
  //   4. CallCustomAction GetOrCreateDirectConversation (kiest best of externalId/email)
  //   5. Navigate to ChatDetailPage
  final tapChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          // Beide identifiers doorgeven — custom action kiest welke. Symmetrie:
          // chat tussen lid-met-lidnummer en admin-met-email werkt beide kanten op.
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserIdId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'externalId')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserEmailId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'email')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserNameId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'name')),
          ),
        ],
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
    followUpAction: FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: getOrCreate.identifier.deepCopy(),
        ),
      ),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.navigate(
          project,
          pageName: 'ChatDetailPage',
          params: {
            'conversationId': VariableParamValue(varFromAppState(currentConvId.deepCopy())),
            'title':          VariableParamValue(varFromAppState(pendingUserNameId.deepCopy())),
          },
        ),
      ),
    ),
  );

  final nameText = UI.text('', name: 'DirectMemberName', style: UITextStyle.bodySmall);
  nameText.props.text.textValue =
      FFStringValue(variable: generatorVarField(memberList.key, 'name'));

  final avatar = UI.container(name: 'DirectMemberAvatar', width: 40, height: 40, borderRadius: 20);

  // "Nog niet online" indicator — zichtbaar wanneer member.hasAppAccount false.
  final hasEmailVar2 = conditionVar(
    generatorVarField(memberList.key, 'hasAppAccount'),
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;
  final noEmailVar2 = conditionVar(
    generatorVarField(memberList.key, 'hasAppAccount'),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
  ).variable;
  final offlineLabel2 = UI.text(
    'Nog niet online',
    name: 'DirectMemberOfflineLabel',
    style: UITextStyle.labelSmall,
  );
  final offlineCopy2 = offlineLabel2.props.text.deepCopy();
  offlineCopy2.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
  );
  offlineCopy2.italicValue = FFBooleanValue(inputValue: true);
  offlineLabel2.props.text = offlineCopy2;
  setConditionalVisibility(offlineLabel2, variable: noEmailVar2);

  final chipCol = UI.column(
    name: 'DirectMemberColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    children: [avatar, nameText, offlineLabel2],
  );

  final chip = UI.container(name: 'DirectMemberChip', width: 60, borderRadius: 8, child: chipCol);
  // Wrap tapChain in If(email != '') zodat offline leden een snackbar krijgen
  // i.p.v. een lege conversationId te triggeren.
  Actions.onTapChain(
    chip,
    Actions.conditional(
      condition: hasEmailVar2,
      trueActions: tapChain,
      falseActions: Actions.chain([
        Actions.snackBar('Dit lid heeft de app nog niet geactiveerd — chatten lukt pas als ze ingelogd zijn.'),
      ]),
    ),
  );
  memberList.children.add(chip);

  final strip = UI.container(name: 'ChatsDirectStripInner', height: 88, child: memberList);
  container.children.add(strip);
}

// Replaces a stale ON_TAP on DirectMemberChip with the correct action chain:
//   SetAppState(pendingDirectUserId, pendingDirectUserName) →
//   CallCustomAction(GetOrCreateDirectConversation) →
//   Navigate(ChatDetailPage, conversationId, title)
// Needed because _wireChatsPageMemberStrip is idempotent (guards on strip being
// empty) and cannot update the chip once it already exists in the project.
void _fixMemberStripTapAction(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final chip = findDescendants(wc.node, (n) => n.name == 'DirectMemberChip').firstOrNull;
  if (chip == null) return;

  final memberList = findDescendants(wc.node, (n) => n.name == 'ChatsDirectMemberList').firstOrNull;
  if (memberList == null) return;

  // Always rebuild the tap chain so we can correct the pendingDirectUserId field
  // from member UUID to member EMAIL (fix for "only see own messages" bug where
  // myId=email but otherId=uuid gave different conversationIds per direction).

  final pendingUserIdId    = _findAppStateFieldId(project, 'pendingDirectUserId');
  final pendingUserEmailId = _findAppStateFieldId(project, 'pendingDirectUserEmail');
  final pendingUserNameId  = _findAppStateFieldId(project, 'pendingDirectUserName');
  final currentConvId      = _findAppStateFieldId(project, 'currentConversationId');
  if (pendingUserIdId == null || pendingUserEmailId == null || pendingUserNameId == null || currentConvId == null) return;

  final getOrCreate = findCustomAction(project, name: 'GetOrCreateDirectConversation');
  if (getOrCreate == null) return;

  chip.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  final tapChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          // Stuur beide identifiers door — custom action kiest "best of".
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserIdId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'externalId')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserEmailId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'email')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserNameId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'name')),
          ),
        ],
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
    followUpAction: FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: getOrCreate.identifier.deepCopy(),
        ),
      ),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.navigate(
          project,
          pageName: 'ChatDetailPage',
          params: {
            'conversationId': VariableParamValue(varFromAppState(currentConvId.deepCopy())),
            'title':          VariableParamValue(varFromAppState(pendingUserNameId.deepCopy())),
          },
        ),
      ),
    ),
  );

  Actions.addTriggerChain(chip, FFActionTriggerType.ON_TAP, tapChain);
}

// Appends GetTeamMembers to the ChatsPage load chain.
// The initial chain (InitializeTeamConversation + chatConversations query + SetState)
// is set by app.editPageOnLoad() in buildEditFlow. This function appends the API call
// for the member strip that sits below the conversations list.
void _wireChatsPageLoad(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

  // Idempotent: skip if GetTeamMembers is already in the FIRST ON_INIT_STATE trigger.
  final firstTriggerIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  bool checkForTeamMembers(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasDatabase() &&
        node.action.database.hasApiCall() &&
        node.action.database.apiCall.hasEndpointIdentifier() &&
        node.action.database.apiCall.endpointIdentifier.name == 'GetTeamMembers') return true;
    if (node.hasFollowUpAction() && checkForTeamMembers(node.followUpAction)) return true;
    return false;
  }
  final alreadyWired = firstTriggerIdx >= 0 &&
      wc.node.triggerActions[firstTriggerIdx].hasRootAction() &&
      checkForTeamMembers(wc.node.triggerActions[firstTriggerIdx].rootAction);
  if (alreadyWired) return;

  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetTeamMembers',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'teamId': varFromAppState(currentTeamIdFieldId.deepCopy()),
      },
      outputVariableName: 'chatsMembers',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'ChatsPage',
          updates: [StateFieldUpdate.setFromVariable('teamMembers', ctx.responseVar)],
        ),
      ]),
    ),
  );
}

// ─── GET /api/staff-groups endpoint ──────────────────────────────────────────

// Adds a GET /api/staff-groups endpoint to the VoetbalPlannerAPI group.
// Staff groups are managed in Laravel (not in-app). This endpoint returns all
// groups the current user belongs to so ChatsPage can show them.
void _addGetStaffGroupsEndpoint(FFProject project) {
  const groupName    = 'VoetbalPlannerAPI';
  const endpointName = 'GetStaffGroups';

  if (findApiEndpoint(project, name: endpointName, groupName: groupName) == null) {
    final group = findApiGroup(project, name: groupName);
    if (group == null) return;

    addEndpointToGroup(
      project,
      groupName:                groupName,
      name:                     endpointName,
      url:                      '/staff-groups',
      method:                   FFApiEndpoint_CallType.GET,
      bodyType:                 FFApiEndpoint_BodyType.NONE,
      headers:                  ['Authorization: Bearer [bearerToken]'],
      responseDataStructName:   'StaffGroupItem',
      responseDataStructIsList: true,
    );
  } else {
    // Endpoint exists from a prior push without response struct — update it.
    updateApiEndpoint(
      project,
      name:                     endpointName,
      groupName:                groupName,
      responseDataStructName:   'StaffGroupItem',
      responseDataStructIsList: true,
    );
  }
}

// ─── ChatsPage conversations list ────────────────────────────────────────────

// Fills ChatsGroupsListContainer on ChatsPage with a ListView bound to the
// 'conversations' state field (chatConversations documents).
// Tapping a conversation navigates to ChatDetailPage with conversationId + title.
void _wireChatsPageConversationsList(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final container = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsListContainer').firstOrNull;
  if (container == null) return;

  // Idempotent: skip if conversations list already added.
  if (findDescendants(container, (n) => n.name == 'ChatsConversationsList').isNotEmpty) return;

  final conversationsId = _findPageStateFieldId(project, 'ChatsPage', 'conversations');
  if (conversationsId == null) return;

  final conversationsVar = varFromPageState(conversationsId.deepCopy());
  conversationsVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final convList = UI.listView(
    name: 'ChatsConversationsList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: conversationsVar, itemName: 'conversation'),
  );

  // Navigate to ChatDetailPage with conversationId + title from the doc.
  FFVariable _docField(String fieldName) {
    final field = findCollectionField(project, collectionName: 'chatConversations', fieldName: fieldName);
    if (field != null) {
      return varFromGeneratorVariable(convList.key)
        ..operations.add(FFVariableOperation(
          accessDocumentField: FFAccessDocumentField(
            fieldIdentifier: field.identifier.deepCopy(),
          ),
        ));
    }
    return generatorVarField(convList.key, fieldName);
  }

  final navigateAction = Actions.navigate(
    project,
    pageName: 'ChatDetailPage',
    params: {
      'conversationId': VariableParamValue(_docField('conversationId')),
      'title':          VariableParamValue(_docField('title')),
    },
  );

  final titleText = UI.text('', name: 'ConvTitle', style: UITextStyle.bodyMedium);
  titleText.props.text.textValue = FFStringValue(variable: _docField('title'));

  final lastMsgText = UI.text('', name: 'ConvLastMsg', style: UITextStyle.bodySmall);
  lastMsgText.props.text.textValue = FFStringValue(variable: _docField('lastMessage'));

  final textCol = UI.column(
    name: 'ConvTextColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    children: [titleText, lastMsgText],
  );

  final icon = UI.icon('forum', size: 24, color: UIColor.primary);

  final convRow = UI.row(
    name: 'ConvRow',
    spacing: 12,
    children: [
      icon,
      textCol,
      UI.icon('chevron_right', size: 18, color: UIColor.secondaryText),
    ],
  );

  final convCard = UI.container(
    name: 'ConvCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: convRow,
  );
  Actions.onTap(convCard, navigateAction);

  convList.children.add(convCard);
  container.children.add(convList);
}

// ─── Filter: chatConversations by teamId ──────────────────────────────────────

// Applies a teamId == AppState.currentTeamId filter to all Firestore queries on
// ChatsPage that target the chatConversations collection (and have no filter yet).
void _wireConversationsFilter(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

  final teamIdField = findCollectionField(
    project,
    collectionName: 'chatConversations',
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

  final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
  for (final node in allNodes) {
    for (final trigger in node.triggerActions) {
      if (trigger.hasRootAction()) {
        _applyFilterToActionChain(trigger.rootAction, whereFilter, collectionName: 'chatConversations');
      }
    }
  }
}

// ─── Filter: chatMessages by conversationId on ChatDetailPage ────────────────

// Applies a conversationId == Param('conversationId') filter to all Firestore queries
// on ChatDetailPage and adds an onLoad action to cache the conversationId in AppState
// (so SendMessage custom action can read it without a String custom-action arg).
void _wireChatDetailFilters(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;

  // ── 1. Apply conversationId filter to chatMessages queries ──────────────────
  FFIdentifier? convParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'conversationId') {
      convParamId = param.identifier.deepCopy();
      break;
    }
  }
  if (convParamId == null) return;

  final convIdField = findCollectionField(
    project,
    collectionName: 'chatMessages',
    fieldName: 'conversationId',
  );
  if (convIdField == null) return;

  final whereFilter = FFFirestoreWhere(
    isAnd: true,
    filters: [
      FFFirestoreWhere_NestedFilter(
        baseFilter: FFFirestoreFilter(
          collectionFieldIdentifier: convIdField.identifier.deepCopy(),
          relation: FFFirestoreFilter_Relation.EQUAL_TO,
          variable: varFromPageParam(convParamId),
        ),
      ),
    ],
  );

  final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
  for (final node in allNodes) {
    for (final trigger in node.triggerActions) {
      if (trigger.hasRootAction()) {
        _applyFilterToActionChain(trigger.rootAction, whereFilter);
      }
    }
  }

  // ── 2. Stage conversationId in AppState on page load ────────────────────────
  // SendMessage reads FFAppState().currentConversationId; stage it from the page param.
  final currentConvIdId = _findAppStateFieldId(project, 'currentConversationId');
  if (currentConvIdId == null) return;

  // Idempotent: skip if already in load chain.
  bool checkForConvIdStage(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasLocalStateUpdate() &&
        node.action.localStateUpdate.stateVariableType == FFStateVariableType.APP_STATE) {
      final updates = node.action.localStateUpdate.updates;
      if (updates.any((u) => u.fieldIdentifier.name == 'currentConversationId')) return true;
    }
    if (node.hasFollowUpAction()) return checkForConvIdStage(node.followUpAction);
    return false;
  }
  final alreadyStaged = wc.node.triggerActions.any((t) =>
    t.hasTrigger() &&
    t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE &&
    t.hasRootAction() &&
    checkForConvIdStage(t.rootAction));
  if (alreadyStaged) return;

  _appendToFirstPageLoadChain(
    wc.node,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        localStateUpdate: FFLocalStateUpdate(
          updates: [
            FFLocalStateFieldUpdate(
              fieldIdentifier: currentConvIdId.deepCopy(),
              setValue: FFValue(variable: varFromPageParam(convParamId)),
            ),
          ],
          stateVariableType: FFStateVariableType.APP_STATE,
        ),
      ),
    ),
  );
}

// Removes all ON_INIT_STATE triggers from ChatsPage EXCEPT the first one.
// editPageOnLoad was previously used for ChatsPage but adds instead of replaces,
// creating a dead second trigger with a duplicate 'loadedGroups' output variable.
// This mutation removes those dead duplicates, leaving only the live first trigger.
void _removeDeadChatsPageTrigger(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  bool foundFirst = false;
  wc.node.triggerActions.removeWhere((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) return false;
    if (!foundFirst) {
      foundFirst = true;
      return false; // keep the first ON_INIT_STATE trigger
    }
    return true; // remove all subsequent ON_INIT_STATE triggers
  });
}

// Forces singleTimeQuery = false on the chatGroups Firestore query in ChatsPage's
// FIRST ON_INIT_STATE trigger so the groups list refreshes after creation.
// Patches the live first trigger directly (no dead second trigger after _removeDeadChatsPageTrigger).
void _fixChatsPageGroupsRealtime(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final firstTriggerIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  if (firstTriggerIdx < 0) return;

  void walkAndFix(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasDatabase() &&
        node.action.database.hasFirestoreQuery() &&
        node.action.database.firestoreQuery.collectionIdentifier.name == 'chatGroups') {
      node.action.database.firestoreQuery.singleTimeQuery = false;
    }
    if (node.hasFollowUpAction()) walkAndFix(node.followUpAction);
  }

  final firstTrigger = wc.node.triggerActions[firstTriggerIdx];
  if (firstTrigger.hasRootAction()) {
    walkAndFix(firstTrigger.rootAction);
  }
}

// ─── GroupChatPage ────────────────────────────────────────────────────────────

void _buildGroupChatPage(App app, FirestoreCollectionHandle groupMessages) {
  app.ensurePage(
    'GroupChatPage',
    description: 'Groepschat voor een specifieke chatgroep.',
    route: 'group-chat',
    params: {
      'groupId':   string.withDefault(''),
      'groupName': string.withDefault('Groep'),
    },
    state: {
      'chatMessages': listOf(groupMessages),
      'messageText':  string,
    },
    onLoad: [
      // Messages loaded without filter here; groupId filter applied by _wireGroupChatFilters.
      FirestoreQuery(groupMessages, limit: 100, singleTimeQuery: false, outputAs: 'loadedMessages'),
      SetState(ff.Pages.groupChatPage.state.chatMessages, ActionOutput('loadedMessages')),
    ],
    body: Column(
      children: [
        // Messages list
        Expanded(
          ListView(
            source: State(ff.Pages.groupChatPage.state.chatMessages),
            padding: 12,
            spacing: 8,
            itemBuilder: (_) => Column(
              crossAxis: CrossAxis.stretch,
              children: [
                // Others' message — left-aligned, sender name visible
                Row(
                  mainAxis: MainAxis.start,
                  visible: Not(Equals(ItemRef()['senderId'], AppState(ff.AppState.userEmail))),
                  children: [
                    Container(
                      padding: 12,
                      borderRadius: 12,
                      color: Colors.secondaryBackground,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        spacing: 4,
                        children: [
                          Text(ItemRef()['senderName'], style: Styles.labelMedium, color: Colors.primary),
                          Text(ItemRef()['text'],        style: Styles.bodyMedium),
                        ],
                      ),
                    ),
                  ],
                ),
                // Own message — right-aligned
                Row(
                  mainAxis: MainAxis.end,
                  visible: Equals(ItemRef()['senderId'], AppState(ff.AppState.userEmail)),
                  children: [
                    Container(
                      padding: 12,
                      borderRadius: 12,
                      color: Colors.primary,
                      child: Column(
                        crossAxis: CrossAxis.start,
                        children: [
                          Text(ItemRef()['text'], style: Styles.bodyMedium, color: Colors.primaryBackground),
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
                  name: 'GroupMessageField',
                  maxLines: 3,
                  onChanged: [SetState(ff.Pages.groupChatPage.state.messageText, TextValue())],
                ),
              ),
              IconButton(
                'send',
                color: Colors.primary,
                onTap: [
                  If(
                    Not(Equals(State(ff.Pages.groupChatPage.state.messageText), '')),
                    then: [
                      FirestoreCreate(
                        groupMessages,
                        fields: {
                          'text':       State(ff.Pages.groupChatPage.state.messageText),
                          'senderId':   AppState(ff.AppState.userEmail),
                          'senderName': AppState(ff.AppState.userName),
                          'groupId':    Param(ff.Pages.groupChatPage.params.groupId),
                          'createdAt':  Global(GlobalProperty.currentTimestamp),
                        },
                      ),
                      SetState.clear(ff.Pages.groupChatPage.state.messageText),
                      FirestoreQuery(groupMessages, limit: 100, singleTimeQuery: true, outputAs: 'refreshed'),
                      SetState(ff.Pages.groupChatPage.state.chatMessages, ActionOutput('refreshed')),
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

// Applies a groupId == Param('groupId') filter to all Firestore queries on GroupChatPage.
// Same approach as _addTeamChatFilters but uses a page param instead of AppState.
void _wireGroupChatFilters(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;

  // Find the groupId param identifier on GroupChatPage.
  FFIdentifier? groupIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'groupId') {
      groupIdParamId = param.identifier.deepCopy();
      break;
    }
  }
  if (groupIdParamId == null) return;

  final groupIdField = findCollectionField(project, collectionName: 'groupMessages', fieldName: 'groupId');
  if (groupIdField == null) return;

  final whereFilter = FFFirestoreWhere(
    isAnd: true,
    filters: [
      FFFirestoreWhere_NestedFilter(
        baseFilter: FFFirestoreFilter(
          collectionFieldIdentifier: groupIdField.identifier.deepCopy(),
          relation: FFFirestoreFilter_Relation.EQUAL_TO,
          variable: varFromPageParam(groupIdParamId),
        ),
      ),
    ],
  );

  final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
  for (final node in allNodes) {
    for (final trigger in node.triggerActions) {
      if (trigger.hasRootAction()) {
        _applyFilterToActionChain(trigger.rootAction, whereFilter);
      }
    }
  }
}

// Applies members array-contains authToken filter to chatGroups queries on ChatsPage.
// Shows only groups where the current user is a member (creator is always added to members).
void _wireChatsPageGroupsFilter(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  // Use userEmail (stable across logins) instead of authToken (ephemeral Sanctum token).
  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;

  final membersField = findCollectionField(
    project,
    collectionName: 'chatGroups',
    fieldName: 'members',
  );
  if (membersField == null) return;

  final whereFilter = FFFirestoreWhere(
    isAnd: true,
    filters: [
      FFFirestoreWhere_NestedFilter(
        baseFilter: FFFirestoreFilter(
          collectionFieldIdentifier: membersField.identifier.deepCopy(),
          relation: FFFirestoreFilter_Relation.ARRAY_CONTAINS,
          variable: varFromAppState(userEmailId.deepCopy()),
        ),
      ),
    ],
  );

  final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
  for (final node in allNodes) {
    for (final trigger in node.triggerActions) {
      if (trigger.hasRootAction()) {
        _applyFilterToActionChain(trigger.rootAction, whereFilter, collectionName: 'chatGroups');
      }
    }
  }
}

// ─── CreateGroupPage helpers ──────────────────────────────────────────────────

// Idempotently adds the 'members' field (list of strings) to the chatGroups
// Firestore collection so groups can store invited member IDs.
void _addMembersFieldToChatGroups(FFProject project) {
  final coll = findCollection(project, name: 'chatGroups');
  if (coll == null) return;
  final alreadyExists = coll.fields.values.any((f) => f.identifier.name == 'members');
  if (alreadyExists) return;
  addCollectionField(
    project,
    collectionName: 'chatGroups',
    fieldName: 'members',
    type: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );
}

// Adds 'hasUnread' (bool) and 'unreadCount' (int) fields to chatConversations.
// Idempotent — skips fields that already exist.
void _addUnreadFieldsToConversations(FFProject project) {
  void _ensureField(String fieldName, FFDataTypeV2 type) {
    final coll = findCollection(project, name: 'chatConversations');
    if (coll == null) return;
    if (coll.fields.values.any((f) => f.identifier.name == fieldName)) return;
    addCollectionField(project, collectionName: 'chatConversations', fieldName: fieldName, type: type);
  }
  _ensureField('hasUnread',   FFDataTypeV2(scalarType: FFBaseDataType.Boolean));
  _ensureField('unreadCount', FFDataTypeV2(scalarType: FFBaseDataType.Integer));
}

// Ensures the isRead field exists on chatMessages collection (idempotent).
void _addIsReadFieldToChatMessages(FFProject project) {
  final coll = findCollection(project, name: 'chatMessages');
  if (coll == null) return;
  if (coll.fields.values.any((f) => f.identifier.name == 'isRead')) return;
  addCollectionField(project, collectionName: 'chatMessages', fieldName: 'isRead',
      type: FFDataTypeV2(scalarType: FFBaseDataType.Boolean));
}

// Ensures the 'deleted' field exists on all chat-message collections (idempotent).
// Used for soft-delete: the document blijft staan, maar de UI toont
// "Bericht verwijderd" + grijze bubble + geen actieknoppen.
void _addDeletedFieldToChatCollections(FFProject project) {
  const collections = ['chatMessages', 'teamChats', 'directMessages', 'groupMessages'];
  for (final name in collections) {
    final coll = findCollection(project, name: name);
    if (coll == null) continue;
    if (coll.fields.values.any((f) => f.identifier.name == 'deleted')) continue;
    addCollectionField(project, collectionName: name, fieldName: 'deleted',
        type: FFDataTypeV2(scalarType: FFBaseDataType.Boolean));
  }
}

// Verwijdert eerder geïnjecteerde debug-marker bovenaan DirectChatPage.body.
// Idempotent: no-op zodra er niets meer te verwijderen valt. Was eerder een
// gele/rode debug-balk om convId-bug te diagnostiseren; nu gefixt, dus weg.
void _addDirectChatConvIdDebug(FFProject project) {
  final wc = findPage(project, name: 'DirectChatPage');
  if (wc == null) return;
  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild == null || bodyChild.type != FFWidgetType.Column) return;
  bodyChild.children.removeWhere((n) =>
    n.name == 'DirectChatConvIdDebugWrap' ||
    n.name == 'DirectChatConvIdMarker',
  );
}

// Scopes DirectChatPage's directMessages query/create aan een deterministische
// conversationId per gebruikers-paar zodat 1-op-1 chats niet meer iedereen
// elkaars berichten laten zien.
//
//   conversationId = "<teamId>_<sortedEmailA>_<sortedEmailB>"
//
// Stappen:
//  1. Add `conversationId` veld aan directMessages collection (idempotent)
//  2. Bouw codeExpressionVar die de convId computeert uit AppState.currentTeamId,
//     AppState.userEmail en PageParam.memberId
//  3. FirestoreCreate op send-button: voeg conversationId-veld toe
//  4. FirestoreQuery on page load (loadedMessages, single=false): voeg
//     WHERE conversationId == ... toe
//  5. FirestoreQuery op send-refresh (single=true): zelfde WHERE
void _scopeDirectChatToConversation(FFProject project) {
  // ── 1. Ensure conversationId field exists on directMessages ─────────────
  final coll = findCollection(project, name: 'directMessages');
  if (coll == null) return;
  if (!coll.fields.values.any((f) => f.identifier.name == 'conversationId')) {
    addCollectionField(
      project,
      collectionName: 'directMessages',
      fieldName: 'conversationId',
      type: FFDataTypeV2(scalarType: FFBaseDataType.String),
    );
  }
  final convIdField = findCollectionField(
    project, collectionName: 'directMessages', fieldName: 'conversationId');
  if (convIdField == null) return;

  final wc = findPage(project, name: 'DirectChatPage');
  if (wc == null) return;

  // ── 2. Variable die de pre-computed AppState.directConvId leest ──────────
  // Gebruikers schrijven directConvId in AppState via ComputeDirectConvId
  // VOORDAT ze navigeren. De query en create lezen die hier. Bewust geen
  // codeExpressionVar — die wordt niet betrouwbaar geaccepteerd als Firestore
  // filter value door FF codegen.
  final directConvIdId = _findAppStateFieldId(project, 'directConvId');
  if (directConvIdId == null) return;

  FFVariable buildConvIdVar() => varFromAppState(directConvIdId.deepCopy());

  // ── 3. Add conversationId field to send-button FirestoreCreate ──────────
  final sendBtn = findByKey(wc.node, 'IconButton_y4orjomc');
  if (sendBtn == null) return;
  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;
  final root = tap.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueEntry = root.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  final createNode = trueEntry.trueAction;
  if (createNode.hasAction() &&
      createNode.action.hasDatabase() &&
      createNode.action.database.hasCreateDocument()) {
    final create = createNode.action.database.createDocument;
    if (create.hasWrite()) {
      // Voeg conversationId entry toe (idempotent — overschrijft elke push).
      create.write.updates[convIdField.identifier.key] = FFFieldUpdate(
        fieldIdentifier: convIdField.identifier.deepCopy(),
        variable: buildConvIdVar(),
      );
    }
  }

  // ── 4 + 5. Add WHERE conversationId == ... to every FirestoreQuery ─────
  void addConvIdWhere(FFFirestoreQuery query) {
    query.where = FFFirestoreWhere(
      isAnd: true,
      filters: [
        FFFirestoreWhere_NestedFilter(
          baseFilter: FFFirestoreFilter(
            collectionFieldIdentifier: convIdField.identifier.deepCopy(),
            relation: FFFirestoreFilter_Relation.EQUAL_TO,
            variable: buildConvIdVar(),
          ),
        ),
      ],
    );
  }

  // Scope onLoad chain (singleTimeQuery: false stream).
  void scopeChain(FFActionNode? n) {
    if (n == null) return;
    if (n.hasAction() &&
        n.action.hasDatabase() &&
        n.action.database.hasFirestoreQuery()) {
      addConvIdWhere(n.action.database.firestoreQuery);
    }
    if (n.hasFollowUpAction()) scopeChain(n.followUpAction);
    if (n.hasConditionActions()) {
      for (final ta in n.conditionActions.trueActions) {
        if (ta.hasTrueAction()) scopeChain(ta.trueAction);
      }
      if (n.conditionActions.hasFalseAction()) {
        scopeChain(n.conditionActions.falseAction);
      }
    }
  }

  // ON_INIT_STATE chain has the loadedMessages query.
  for (final t in wc.node.triggerActions) {
    if (t.hasTrigger() &&
        t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE &&
        t.hasRootAction()) {
      scopeChain(t.rootAction);
    }
  }
  // Send-button ON_TAP chain has the refresh query.
  scopeChain(trueEntry.trueAction);
}

// Adds a count badge to each conversation row in ChatsPage's ChatsConversationsList.
// Badge is visible when hasUnread == true; shows the unreadCount value.
// Idempotent: skips if ConvUnreadBadge already present.
void _addConversationBadges(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'ConvUnreadBadge').isNotEmpty) return;

  final convRow = findDescendants(wc.node, (n) => n.name == 'ConvRow').firstOrNull;
  if (convRow == null) return;

  final convList = findDescendants(wc.node, (n) => n.name == 'ChatsConversationsList').firstOrNull;
  if (convList == null) return;

  final hasUnreadField    = findCollectionField(project, collectionName: 'chatConversations', fieldName: 'hasUnread');
  final unreadCountField  = findCollectionField(project, collectionName: 'chatConversations', fieldName: 'unreadCount');
  if (hasUnreadField == null || unreadCountField == null) return;

  // Variable that reads a document field from the current list item.
  FFVariable _docField(FFParameter field) {
    return varFromGeneratorVariable(convList.key)
      ..operations.add(FFVariableOperation(
        accessDocumentField: FFAccessDocumentField(
          fieldIdentifier: field.identifier.deepCopy(),
        ),
      ));
  }

  // Cast int → string for the badge label.
  final countVar = _docField(unreadCountField)
    ..operations.add(FFVariableOperation(
      typeCastOperation: FFTypeCastOperation(
        originalType:    FFBaseDataType.Integer,
        destinationType: FFBaseDataType.String,
      ),
    ));

  // Badge wrapping an empty spacer — sits to the right of the text column.
  final badgeNode = UI.badge(
    content: '',   // overridden below with dynamic variable
    color: UIColor.error,
    name: 'ConvUnreadBadge',
  );
  // Override badge label with the unreadCount variable.
  badgeNode.props.badge.text = FFText(
    textValue: FFStringValue(variable: countVar),
    themeStyle: FFText_ThemeStyle.TITLE_SMALL,
    colorValue: FFColorValue(
      inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND),
    ),
  );
  // Show badge only when hasUnread is true.
  badgeNode.props.badge.showBadgeValue = FFBooleanValue(variable: _docField(hasUnreadField));

  // Insert the badge before the last child (chevron) in ConvRow.
  // ConvRow children: [icon, textCol, chevron] → [icon, textCol, badge, chevron]
  final chevron = convRow.children.last;
  convRow.children.removeLast();
  convRow.children.addAll([badgeNode, chevron]);
}

// Appends a MarkConversationRead call to ChatDetailPage's ON_INIT_STATE chain,
// after currentConversationId has been staged in AppState.
void _wireMarkConversationRead(FFProject project) {
  // ChatDetailPage: currentConversationId is al gezet via de conversatie-tap.
  _wireMarkReadOnPage(project, 'ChatDetailPage', reinitTeamConv: false);
  // TeamChatPage: opent als sub-pagina; markConversationRead werd hier nooit
  // aangeroepen (bug: ongelezen teamchat bleef staan na openen). Eerst
  // InitializeTeamConversation zodat currentConversationId = team_<currentTeamId>
  // (ook correct bij multi-team), dan pas markeren als gelezen.
  _wireMarkReadOnPage(project, 'TeamChatPage', reinitTeamConv: true);
}

void _wireMarkReadOnPage(FFProject project, String pageName, {required bool reinitTeamConv}) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;

  final markRead = findCustomAction(project, name: 'MarkConversationRead');
  if (markRead == null) return;
  final initConv =
      reinitTeamConv ? findCustomAction(project, name: 'InitializeTeamConversation') : null;

  // Idempotent: skip if MarkConversationRead is already in the load chain.
  bool checkForMarkRead(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasCustomAction() &&
        node.action.customAction.customActionIdentifier.name == 'MarkConversationRead') return true;
    if (node.hasFollowUpAction()) return checkForMarkRead(node.followUpAction);
    return false;
  }
  final alreadyWired = wc.node.triggerActions.any((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) return false;
    return t.hasRootAction() && checkForMarkRead(t.rootAction);
  });
  if (alreadyWired) return;

  FFActionNode customNode(FFCustomAction action) => FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          customAction: FFCustomActionCall(
            customActionIdentifier: action.identifier.deepCopy(),
          ),
        ),
      );

  final markNode = customNode(markRead);
  FFActionNode rootNode;
  if (initConv != null) {
    rootNode = customNode(initConv);
    rootNode.followUpAction = markNode;
  } else {
    rootNode = markNode;
  }

  _appendToFirstPageLoadChain(wc.node, rootNode);
}

// Rebuilds the inner content of ChatDetailPage's message bubbles on every push:
//   - Others' bubble: senderName + message text + formatted time (HH:mm)
//   - Own bubble:     message text + row(formatted time + read receipt icons)
// Read receipts: single checkmark (grey) = sent; double checkmark (blue) = read by recipient.
// Uses fixed node keys from the initial ensurePage-created tree.
void _rebuildChatMessageBubbles(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;

  // Node keys are stable (from the initial ensurePage tree).
  const listKey     = 'ListView_ws05qhut';
  const otherColKey = 'Column_u3nr1vt9';   // inner Col of others' bubble
  const ownColKey   = 'Column_6e4g1gje';   // inner Col of own bubble

  final otherCol = findByKey(wc.node, otherColKey);
  final ownCol   = findByKey(wc.node, ownColKey);
  if (otherCol == null || ownCol == null) return;

  // Idempotent: skip if already fully rebuilt (both OtherMsgTime and OwnSenderName present).
  final hasOtherTime    = otherCol.children.any((n) => n.name == 'OtherMsgTime');
  final hasOwnSender    = ownCol.children.any((n) => n.name == 'OwnSenderName');
  if (hasOtherTime && hasOwnSender) return;

  // chatMessages field keys (from inspect).
  const kText        = '4ezq3smy';
  const kSenderName  = 'kyzfo8ov';
  const kCreatedAt   = 'p94w5qdd';
  final isReadField  = findCollectionField(project, collectionName: 'chatMessages', fieldName: 'isRead');

  // Variable helpers — access a field from the list generator.
  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  // Formatted timestamp: access createdAt then apply HH:mm format.
  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  // Boolean visible: isRead value (true = recipient has read the message).
  FFVariable? _isReadVar;
  FFVariable? _notIsReadVar;
  if (isReadField != null) {
    _isReadVar = _field(isReadField.identifier.key, 'isRead');
    _notIsReadVar = varFromGeneratorVariable(listKey)
      ..operations.add(FFVariableOperation(
        accessDocumentField: FFAccessDocumentField(
          fieldIdentifier: FFIdentifier(key: isReadField.identifier.key, name: 'isRead'),
        ),
      ))
      ..operations.add(FFVariableOperation(negate: FFNegateBoolean()));
  }

  // Helper: make a text node with a variable binding.
  FFNode _txt(String nodeName, String fieldKey, String fieldName, {
    FFText_ThemeStyle style = FFText_ThemeStyle.BODY_MEDIUM,
    FFColor_ThemeColor? color,
  }) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodyMedium);
    final txtCopy = node.props.text.deepCopy();
    txtCopy.themeStyle = style;
    txtCopy.textValue  = FFStringValue(variable: _field(fieldKey, fieldName));
    if (color != null) {
      txtCopy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    }
    node.props.text = txtCopy;
    return node;
  }

  // Helper: time text node.
  FFNode _timeTxt(String nodeName, {FFColor_ThemeColor color = FFColor_ThemeColor.SECONDARY_TEXT}) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodySmall);
    final txtCopy = node.props.text.deepCopy();
    txtCopy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    txtCopy.textValue  = FFStringValue(variable: _formattedTime());
    txtCopy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = txtCopy;
    return node;
  }

  // ── Others' bubble (left, secondaryBackground) ──────────────────────────────
  otherCol.children.clear();
  // Sender name
  otherCol.children.add(_txt('OtherSenderName', kSenderName, 'senderName',
    style: FFText_ThemeStyle.LABEL_MEDIUM,
    color: FFColor_ThemeColor.PRIMARY,
  ));
  // Message text
  otherCol.children.add(_txt('OtherMsgText', kText, 'text',
    style: FFText_ThemeStyle.BODY_MEDIUM,
  ));
  // Time
  otherCol.children.add(_timeTxt('OtherMsgTime',
    color: FFColor_ThemeColor.SECONDARY_TEXT,
  ));

  // ── Own bubble (right, primary) ──────────────────────────────────────────────
  ownCol.children.clear();
  // Sender name (own bubble — shown so receiver also sees it in group context)
  ownCol.children.add(_txt('OwnSenderName', kSenderName, 'senderName',
    style: FFText_ThemeStyle.LABEL_MEDIUM,
    color: FFColor_ThemeColor.PRIMARY_BACKGROUND,
  ));
  // Message text
  ownCol.children.add(_txt('OwnMsgText', kText, 'text',
    style: FFText_ThemeStyle.BODY_MEDIUM,
    color: FFColor_ThemeColor.PRIMARY_BACKGROUND,
  ));
  // Bottom row: time + read receipt
  final metaRow = UI.row(name: 'OwnMsgMeta', mainAxisAlignment: UIMainAxisAlignment.end, spacing: 4);

  // Timestamp
  metaRow.children.add(_timeTxt('OwnMsgTime',
    color: FFColor_ThemeColor.PRIMARY_BACKGROUND,
  ));

  // Single check (sent, not yet read) — visible when isRead = false
  final sentIcon = UI.icon('done', size: 14, color: UIColor.primaryBackground, name: 'ReadReceiptSent');
  if (_notIsReadVar != null) setConditionalVisibility(sentIcon, variable: _notIsReadVar);
  metaRow.children.add(sentIcon);

  // Double check (read) — visible when isRead = true
  final readIcon = UI.icon('done_all', size: 14, color: UIColor.primaryBackground, name: 'ReadReceiptRead');
  if (_isReadVar != null) setConditionalVisibility(readIcon, variable: _isReadVar);
  metaRow.children.add(readIcon);

  ownCol.children.add(metaRow);
}

// Nuclear rebuild of ChatDetailPage (staff chat) message bubbles with left/right
// alignment and read receipts. Clears Column_e6dtwbzq and replaces with:
//   OtherBubbleRow (visible: senderId != userEmail, mainAxis: start)
//   OwnBubbleRow   (visible: senderId == userEmail, mainAxis: end)
// Non-idempotent by design: always rebuilds so UI.row() rows replace the legacy
// FlutterFlow rows that had minSizeValue=true and ignored mainAxisAlignment.
void _rebuildChatDetailBubbles(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;

  final innerCol = findByKey(wc.node, 'Column_e6dtwbzq');
  if (innerCol == null) return;

  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;

  const listKey     = 'ListView_ws05qhut';
  const kSenderId   = '9hloj348'; // chatMessages.senderId
  const kSenderName = 'kyzfo8ov'; // chatMessages.senderName
  const kText       = '4ezq3smy'; // chatMessages.text
  const kCreatedAt  = 'p94w5qdd'; // chatMessages.createdAt
  final isReadField = findCollectionField(project, collectionName: 'chatMessages', fieldName: 'isRead');

  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  FFNode _txt(String nodeName, String fieldKey, String fieldName, {
    FFText_ThemeStyle style = FFText_ThemeStyle.BODY_MEDIUM,
    FFColor_ThemeColor? color,
  }) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodyMedium);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = style;
    copy.textValue  = FFStringValue(variable: _field(fieldKey, fieldName));
    if (color != null) copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  FFNode _timeTxt(String nodeName, {FFColor_ThemeColor color = FFColor_ThemeColor.SECONDARY_TEXT}) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodySmall);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  innerCol.children.clear();

  // ── Others' bubble (left) ──────────────────────────────────────────────────
  final otherMsgCol = UI.column(
    name: 'OtherMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OtherSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY),
      _txt('OtherMsgText', kText, 'text'),
      _timeTxt('OtherMsgTime'),
    ],
  );

  final otherBubble = UI.container(
    name: 'OtherBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: otherMsgCol,
  );

  final otherRow = UI.row(
    name: 'OtherBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [otherBubble],
  );
  setConditionalVisibility(
    otherRow,
    variable: conditionVar(
      _field(kSenderId, 'senderId'),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromAppState(userEmailId.deepCopy()),
    ).variable,
  );

  // ── Own bubble (right) ────────────────────────────────────────────────────
  FFVariable? isReadVar;
  FFVariable? notIsReadVar;
  if (isReadField != null) {
    isReadVar = _field(isReadField.identifier.key, 'isRead');
    notIsReadVar = varFromGeneratorVariable(listKey)
      ..operations.add(FFVariableOperation(
        accessDocumentField: FFAccessDocumentField(
          fieldIdentifier: FFIdentifier(key: isReadField.identifier.key, name: 'isRead'),
        ),
      ))
      ..operations.add(FFVariableOperation(negate: FFNegateBoolean()));
  }

  final ownMsgMeta = UI.row(
    name: 'OwnMsgMeta',
    mainAxisAlignment: UIMainAxisAlignment.end,
    spacing: 4,
    children: [_timeTxt('OwnMsgTime', color: FFColor_ThemeColor.PRIMARY_BACKGROUND)],
  );

  final sentIcon = UI.icon('done', size: 14, color: UIColor.primaryBackground, name: 'ReadReceiptSent');
  if (notIsReadVar != null) setConditionalVisibility(sentIcon, variable: notIsReadVar);
  ownMsgMeta.children.add(sentIcon);

  final readIcon = UI.icon('done_all', size: 14, color: UIColor.primaryBackground, name: 'ReadReceiptRead');
  if (isReadVar != null) setConditionalVisibility(readIcon, variable: isReadVar);
  ownMsgMeta.children.add(readIcon);

  final ownMsgCol = UI.column(
    name: 'OwnMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OwnSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      _txt('OwnMsgText', kText, 'text', color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      ownMsgMeta,
    ],
  );

  final ownBubble = UI.container(
    name: 'OwnBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.primary,
    child: ownMsgCol,
  );

  final ownRow = UI.row(
    name: 'OwnBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.end,
    children: [ownBubble],
  );
  setConditionalVisibility(
    ownRow,
    variable: conditionVar(
      _field(kSenderId, 'senderId'),
      FFCondition_Relation.EQUAL_TO,
      varFromAppState(userEmailId.deepCopy()),
    ).variable,
  );

  innerCol.children.add(otherRow);
  innerCol.children.add(ownRow);
}

// Sets debounceTimeValue=0 on the chat TextField so that onChange fires
// immediately without the default 2000ms EasyDebounce delay. This ensures
// _model.messageText is always in sync when the send button's condition fires.
// Also applies localStateValue=true so SetState.clear() visually clears the field.
void _fixChatTextFieldDebounce(FFProject project, String pageName, String fieldName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final tf = findDescendants(wc.node, (n) => n.name == fieldName).firstOrNull;
  if (tf == null) return;
  // debounceTimeValue = 0 → codegen emits Duration(milliseconds: 0) → instant update.
  tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
  // localStateValue = true → bidirectional binding; SetState.clear() also clears display.
  tf.props.textField.localStateValue = true;
  final msgTextId = _findPageStateFieldId(project, pageName, 'messageText');
  if (msgTextId == null) return;
  tf.props.textField.initialText = FFText(
    textValue: FFStringValue(variable: varFromPageState(msgTextId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)),
  );
}

void _fixChatTextFieldController(FFProject project) =>
    _fixChatTextFieldDebounce(project, 'ChatDetailPage', 'ChatMessageField');

void _fixGroupChatTextField(FFProject project) =>
    _fixChatTextFieldDebounce(project, 'GroupChatPage', 'GroupMessageField');

// Applies the same debounce-free TextField fix to TeamChatPage.
void _fixTeamChatTextField(FFProject project) =>
    _fixChatTextFieldDebounce(project, 'TeamChatPage', 'MessageField');

// Hides the current user's own chip in the ChatsPage direct-message member strip
// so that the user cannot accidentally open a conversation with themselves.
// Uses name comparison: member.name != FFAppState().userName.
void _fixDirectMemberSelfFilter(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final chip = findDescendants(wc.node, (n) => n.name == 'DirectMemberChip').firstOrNull;
  if (chip == null) return;

  // Idempotent: skip if a visibility condition is already set.
  if (chip.props.hasVisibility() &&
      chip.props.visibility.hasVisibleValue() &&
      chip.props.visibility.visibleValue.hasVariable()) return;

  final memberList = findDescendants(wc.node, (n) => n.name == 'ChatsDirectMemberList').firstOrNull;
  if (memberList == null) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;

  final memberNameVar = generatorVarField(memberList.key, 'name');
  final userNameVar   = varFromAppState(userNameId.deepCopy());

  setConditionalVisibility(
    chip,
    variable: conditionVar(
      memberNameVar,
      FFCondition_Relation.NOT_EQUAL_TO,
      userNameVar,
    ).variable,
  );
}

// Wires GetTeamMembers on CreateGroupPage load so the member-selection list is
// populated before the user taps "Aanmaken".
void _wireCreateGroupPageLoad(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;

  // Idempotent: skip if GetTeamMembers already in any ON_INIT_STATE chain.
  final alreadyWired = wc.node.triggerActions.any((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) return false;
    bool check(FFActionNode node) {
      if (node.hasAction() &&
          node.action.hasDatabase() &&
          node.action.database.hasApiCall() &&
          node.action.database.apiCall.hasEndpointIdentifier() &&
          node.action.database.apiCall.endpointIdentifier.name == 'GetTeamMembers') return true;
      if (node.hasFollowUpAction() && check(node.followUpAction)) return true;
      return false;
    }
    return t.hasRootAction() && check(t.rootAction);
  });
  if (alreadyWired) return;

  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetTeamMembers',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'teamId': varFromAppState(currentTeamIdFieldId.deepCopy()),
      },
      outputVariableName: 'createGroupMembers',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'CreateGroupPage',
          updates: [
            StateFieldUpdate.setFromVariable('teamMembers', ctx.responseVar),
            StateFieldUpdate.set('isLoadingMembers', 'false'),
          ],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'CreateGroupPage',
          updates: [StateFieldUpdate.set('isLoadingMembers', 'false')],
        ),
      ]),
    ),
  );
}

// Inserts a team-member selection list into CreateGroupPage body, between
// the GroupNameField text input and the CreateGroupSubmitButton.
void _wireCreateGroupMembersUI(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;

  // Idempotent: skip if already added.
  if (findDescendants(wc.node, (n) => n.name == 'CreateGroupMemberList').isNotEmpty) return;

  final teamMembersId = _findPageStateFieldId(project, 'CreateGroupPage', 'teamMembers');
  if (teamMembersId == null) return;

  final selectedMemberIdsId = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberIds');
  if (selectedMemberIdsId == null) return;

  final submitBtn = findDescendants(wc.node, (n) => n.name == 'CreateGroupSubmitButton').firstOrNull;
  if (submitBtn == null) return;

  final parentResult = findParentByKey(wc.node, submitBtn.key);
  if (parentResult == null) return;
  final parent = parentResult.parent;

  final btnIndex = parent.children.indexWhere((c) => c.key == submitBtn.key);
  if (btnIndex < 0) return;

  // Build team members list with per-item "+" icon button.
  final teamMembersVar = varFromPageState(teamMembersId.deepCopy());
  teamMembersVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final memberList = UI.listView(
    name: 'CreateGroupMemberList',
    spacing: 4,
    shrinkWrap: true,
    dynamicSource: DynamicSource(variable: teamMembersVar, itemName: 'cgMember'),
  );

  // Member ID variable from the generator (struct field for reliable resolution).
  final swapMemberIdFieldId = _findStructFieldId(project, 'SwapMember', 'id');
  final memberIdVar = swapMemberIdFieldId != null
      ? (varFromGeneratorVariable(memberList.key)
          ..operations.add(FFVariableOperation(
            accessDataStructField: FFAccessDataStructField(
              fieldIdentifier: swapMemberIdFieldId.deepCopy(),
            ),
          )))
      : generatorVarField(memberList.key, 'id');

  // "+" button: tapping adds this member's ID to selectedMemberIds.
  final addBtn = UI.iconButton(
    'add_circle_outline',
    name: 'AddMemberButton',
    size: 20,
    color: UIColor.primary,
  );
  Actions.addTriggerChain(
    addBtn,
    FFActionTriggerType.ON_TAP,
    Actions.chain([
      Actions.updatePageState(
        project,
        widgetClassName: 'CreateGroupPage',
        updates: [StateFieldUpdate.addToListFromVariable('selectedMemberIds', memberIdVar)],
      ),
    ]),
  );

  final memberNameText = UI.text(
    '',
    name: 'CreateGroupMemberName',
    style: UITextStyle.bodyMedium,
  );
  memberNameText.props.text.textValue =
      FFStringValue(variable: generatorVarField(memberList.key, 'name'));

  final memberRow = UI.row(
    name: 'CreateGroupMemberRow',
    mainAxisAlignment: UIMainAxisAlignment.spaceBetween,
    children: [memberNameText, addBtn],
  );

  final memberCard = UI.container(
    name: 'CreateGroupMemberCard',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 8),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: memberRow,
  );
  memberList.children.add(memberCard);

  // Section label above the member list.
  final sectionLabel = UI.text(
    'Leden uitnodigen',
    name: 'CreateGroupMembersLabel',
    style: UITextStyle.labelSmall,
    color: UIColor.secondaryText,
  );

  // Insert label + list before the submit button.
  parent.children.insert(btnIndex, memberList);
  parent.children.insert(btnIndex, sectionLabel);
}

/// Stable keys for the CreateChatGroup custom action parameters.
const _kMemberIdsParamKey   = 'cgmembids1';
const _kMemberNamesParamKey = 'cgmembnames1';

// Replaces CreateGroupSubmitButton's ON_TAP chain so it reliably creates a
// chatGroups document regardless of what was wired in a previous push.
// Chain: stage AppState.pendingGroupName (from page state) →
//        call CreateChatGroup(memberIds = selectedMemberIds) →
//        navigate to ChatsPage.
void _wireCreateGroupSubmitAction(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;

  final submitBtn = findDescendants(wc.node, (n) => n.name == 'CreateGroupSubmitButton').firstOrNull;
  if (submitBtn == null) return;

  final groupNameId           = _findPageStateFieldId(project, 'CreateGroupPage', 'groupName');
  final selectedMemberIdsId   = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberIds');
  final selectedMemberNamesId = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberNames');
  final pendingGroupNameId    = _findAppStateFieldId(project, 'pendingGroupName');
  if (groupNameId == null || selectedMemberIdsId == null || pendingGroupNameId == null) return;

  final createChatGroup = findCustomAction(project, name: 'CreateChatGroup');
  if (createChatGroup == null) return;

  // Idempotent: already wired when ON_TAP calls CreateChatGroup with the stable key.
  final alreadyWired = submitBtn.triggerActions.any((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_TAP) return false;
    if (!t.hasRootAction()) return false;
    bool hasCA(FFActionNode n) {
      if (n.hasAction() && n.action.hasCustomAction() &&
          n.action.customAction.customActionIdentifier.name == 'CreateChatGroup' &&
          n.action.customAction.hasArgumentValues() &&
          n.action.customAction.argumentValues.arguments.containsKey(_kMemberIdsParamKey) &&
          n.action.customAction.argumentValues.arguments.containsKey(_kMemberNamesParamKey)) {
        return true;
      }
      if (n.hasFollowUpAction() && hasCA(n.followUpAction)) return true;
      if (n.hasConditionActions()) {
        for (final ta in n.conditionActions.trueActions) {
          if (ta.hasTrueAction() && hasCA(ta.trueAction)) return true;
        }
      }
      return false;
    }
    return hasCA(t.rootAction);
  });
  if (alreadyWired) return;

  final groupNameVar = varFromPageState(groupNameId.deepCopy());
  groupNameVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final selectedMemberIdsVar = varFromPageState(selectedMemberIdsId.deepCopy());
  selectedMemberIdsVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  // Build argumentValues for CreateChatGroup: memberIds + memberNames.
  final argValues = FFFunctionCallValues();
  argValues.arguments[_kMemberIdsParamKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: selectedMemberIdsVar),
  );
  if (selectedMemberNamesId != null) {
    final selectedMemberNamesVar = varFromPageState(selectedMemberNamesId.deepCopy());
    selectedMemberNamesVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    argValues.arguments[_kMemberNamesParamKey] = FFFunctionCallValues_FFArgument(
      value: FFValue(variable: selectedMemberNamesVar),
    );
  }

  // Replace ALL existing ON_TAP triggers with the corrected chain.
  submitBtn.triggerActions.removeWhere((t) =>
    t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  // Stage pendingGroupName → call CreateChatGroup(memberIds) → navigate to ChatsPage.
  Actions.addTriggerChain(
    submitBtn,
    FFActionTriggerType.ON_TAP,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        localStateUpdate: FFLocalStateUpdate(
          updates: [
            FFLocalStateFieldUpdate(
              fieldIdentifier: pendingGroupNameId.deepCopy(),
              setValue: FFValue(variable: groupNameVar),
            ),
          ],
          stateVariableType: FFStateVariableType.APP_STATE,
        ),
      ),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          customAction: FFCustomActionCall(
            customActionIdentifier: createChatGroup.identifier.deepCopy(),
            argumentValues: argValues,
          ),
        ),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: Actions.navigate(project, pageName: 'ChatsPage', params: {}),
        ),
      ),
    ),
  );
}

// Binds DynamicSource + text/action wiring to the EXISTING CreateGroupMemberList.
// Called when _wireCreateGroupMembersUI was skipped because the list already exists
// but has no generator variable bound (i.e. the list is not yet dynamic).
void _wireCreateGroupMembersBinding(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;

  final list = findDescendants(wc.node, (n) => n.name == 'CreateGroupMemberList').firstOrNull;
  if (list == null) return;

  // Idempotent: skip if DynamicSource generator already set.
  if (list.hasGeneratorVariable()) return;

  final teamMembersId = _findPageStateFieldId(project, 'CreateGroupPage', 'teamMembers');
  if (teamMembersId == null) return;

  final selectedMemberIdsId = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberIds');
  if (selectedMemberIdsId == null) return;

  // Bind DynamicSource: iterate over teamMembers page state.
  final teamMembersVar = varFromPageState(teamMembersId.deepCopy());
  teamMembersVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  list.generatorVariable = DynamicSource(
    variable: teamMembersVar,
    itemName: 'cgMember',
  ).toGeneratorVariable(list.key);

  // Bind member name text.
  final nameText = findDescendants(list, (n) => n.name == 'CreateGroupMemberName').firstOrNull;
  if (nameText != null) {
    nameText.props.text.textValue = FFStringValue(
      variable: generatorVarField(list.key, 'name'),
    );
  }

  // Wire AddMemberButton → add member ID to selectedMemberIds.
  // Use generatorVarField (name-based) rather than accessDataStructField because
  // the teamMembers state field has type List<DataStruct<?>> (unresolved struct type)
  // and struct field identifier access would fail the validator.
  final addBtn = findDescendants(list, (n) => n.name == 'AddMemberButton').firstOrNull;
  if (addBtn != null) {
    final alreadyWired = addBtn.triggerActions.any((t) =>
      t.hasTrigger() &&
      t.trigger.triggerType == FFActionTriggerType.ON_TAP &&
      t.hasRootAction(),
    );
    if (!alreadyWired) {
      final memberIdVar = generatorVarField(list.key, 'id');

      Actions.addTriggerChain(
        addBtn,
        FFActionTriggerType.ON_TAP,
        Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: 'CreateGroupPage',
            updates: [StateFieldUpdate.addToListFromVariable('selectedMemberIds', memberIdVar)],
          ),
        ]),
      );
    }
  }
}

// Wires the NewGroupButton (IconButton_kcjzqtd6) ON_TAP to navigate to CreateGroupPage.
void _wireNewGroupButton(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final btn = findByKey(wc.node, 'IconButton_kcjzqtd6');
  if (btn == null) return;

  // Idempotent: skip if already wired with a root action.
  final alreadyWired = btn.triggerActions.any((t) =>
    t.hasTrigger() &&
    t.trigger.triggerType == FFActionTriggerType.ON_TAP &&
    t.hasRootAction(),
  );
  if (alreadyWired) return;

  Actions.addTriggerChain(
    btn,
    FFActionTriggerType.ON_TAP,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.navigate(project, pageName: 'CreateGroupPage', params: {}),
    ),
  );
}

// Adds 'staffGroups' state field (List<StaffGroupItem>) to ChatsPage if missing.
void _wireChatsPageStaffGroupsState(FFProject project) {
  final exists = _findPageStateFieldId(project, 'ChatsPage', 'staffGroups') != null;
  if (exists) return;

  final struct = findDataStruct(project, name: 'StaffGroupItem');
  if (struct == null) return;

  addStateField(
    project,
    widgetClassName: 'ChatsPage',
    fieldName: 'staffGroups',
    type: FFDataTypeV2(listType: dataStructType(struct.identifier.deepCopy())),
  );
}

// Appends GetStaffGroups API call to the ChatsPage load chain.
// onSuccess: set staffGroups page state from the response.
void _wireChatsPageStaffGroupsLoad(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  // Idempotent: skip if GetStaffGroups already in any ON_INIT_STATE chain.
  bool checkForStaffGroups(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasDatabase() &&
        node.action.database.hasApiCall() &&
        node.action.database.apiCall.hasEndpointIdentifier() &&
        node.action.database.apiCall.endpointIdentifier.name == 'GetStaffGroups') return true;
    if (node.hasFollowUpAction() && checkForStaffGroups(node.followUpAction)) return true;
    return false;
  }
  final alreadyWired = wc.node.triggerActions.any((t) {
    if (!t.hasTrigger() || t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) return false;
    return t.hasRootAction() && checkForStaffGroups(t.rootAction);
  });
  if (alreadyWired) return;

  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetStaffGroups',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {},
      outputVariableName: 'chatsStaffGroups',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'ChatsPage',
          updates: [StateFieldUpdate.setFromVariable('staffGroups', ctx.responseVar)],
        ),
      ]),
    ),
  );
}

void _removeChatsDebugBanner(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  for (final n in findDescendants(wc.node, (n) => n.name == 'DebugTeamIdText')) {
    removeByKey(wc.node, n.key);
  }
}

// Ensures the ChatsPage body column is scrollable so all sections
// (Staffgroepen, Direct) remain reachable on small screens.
void _makeChatsPageBodyScrollable(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final bodyCol = findByKey(wc.node, 'Column_97jfu72d');
  if (bodyCol == null) return;

  if (!bodyCol.props.column.scrollable) {
    final colCopy = bodyCol.props.column.deepCopy();
    colCopy.scrollable = true;
    bodyCol.props.column = colCopy;
  }
}

// Improves TeamChatPage member chip appearance:
//   MemberChipName: body_small → body_medium (more readable)
//   MemberAvatar: empty container → grey circle with person icon
void _fixMemberChipStyle(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final nameText = findDescendants(wc.node, (n) => n.name == 'MemberChipName').firstOrNull;
  if (nameText != null && nameText.props.hasText()) {
    final copy = nameText.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_MEDIUM;
    nameText.props.text = copy;
  }

  final avatar = findDescendants(wc.node, (n) => n.name == 'MemberAvatar').firstOrNull;
  if (avatar != null) {
    _setContainerColor(
      avatar,
      FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.ALTERNATE)),
    );
    if (avatar.children.isEmpty) {
      avatar.children.add(UI.icon('person', size: 20, color: UIColor.primary));
    }
  }
}

// Improves ChatsPage DirectMemberChip appearance (runs every push to patch
// whatever structure is current after _convertDirectMembersToList):
//   DirectMemberName: ensure label_medium style
//   DirectMemberAvatar: ensure primary-colored circle with person icon
void _fixDirectMemberChipStyle(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final nameText = findDescendants(wc.node, (n) => n.name == 'DirectMemberName').firstOrNull;
  if (nameText != null && nameText.props.hasText()) {
    final copy = nameText.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.LABEL_MEDIUM;
    nameText.props.text = copy;
  }

  final avatar = findDescendants(wc.node, (n) => n.name == 'DirectMemberAvatar').firstOrNull;
  if (avatar != null) {
    _setContainerColor(
      avatar,
      FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY)),
    );
    if (avatar.children.isEmpty) {
      avatar.children.add(UI.icon('person', size: 22, color: UIColor.primaryBackground));
    }
  }
}

// Sets height=40 on every Button widget across all pages and components.
// IconButton widgets are intentionally excluded (different widget type).
void _setAllButtonHeights(FFProject project) {
  final allWidgetClasses = [
    for (final key in project.pageKeys)
      if (project.widgetClasses[key] case final wc?) wc,
    ...project.getComponents(),
  ];

  for (final wc in allWidgetClasses) {
    final buttons = [
      if (wc.node.type == FFWidgetType.Button) wc.node,
      ...findDescendants(wc.node, (n) => n.type == FFWidgetType.Button),
    ];
    for (final btn in buttons) {
      final tmpDims = UI.button('', height: 40).props.button.dimensions.deepCopy();
      if (!btn.props.button.hasDimensions()) {
        btn.props.button.dimensions = tmpDims;
      } else {
        final dims = btn.props.button.dimensions.deepCopy();
        dims.height = tmpDims.height.deepCopy();
        btn.props.button.dimensions = dims;
      }
    }
  }
}

// Adds orderBy createdAt ascending to every Firestore message query on all chat
// pages so messages always arrive oldest-first (newest at the bottom of the list).
// Covers TeamChatPage (teamChats), GroupChatPage (groupMessages),
// ChatDetailPage (chatMessages), and DirectChatPage (directMessages).
void _sortChatMessagesByCreatedAt(FFProject project) {
  const targets = [
    ('TeamChatPage',   'teamChats'),
    ('GroupChatPage',  'groupMessages'),
    ('ChatDetailPage', 'chatMessages'),
    ('DirectChatPage', 'directMessages'),
  ];

  for (final (pageName, collName) in targets) {
    final wc = findPage(project, name: pageName);
    if (wc == null) continue;

    final createdAtField = findCollectionField(
      project,
      collectionName: collName,
      fieldName: 'createdAt',
    );
    if (createdAtField == null) continue;

    final allNodes = [wc.node, ...findDescendants(wc.node, (_) => true)];
    for (final node in allNodes) {
      for (final trigger in node.triggerActions) {
        if (trigger.hasRootAction()) {
          _applyOrderByToActionChain(
            trigger.rootAction,
            createdAtField.identifier,
            collectionName: collName,
          );
        }
      }
    }
  }
}

void _applyOrderByToActionChain(
  FFActionNode node,
  FFIdentifier createdAtFieldId, {
  String? collectionName,
}) {
  if (node.hasAction() &&
      node.action.hasDatabase() &&
      node.action.database.hasFirestoreQuery()) {
    final query = node.action.database.firestoreQuery;
    if ((collectionName == null ||
            query.collectionIdentifier.name == collectionName) &&
        query.orderBy.isEmpty) {
      query.orderBy.add(
        FFFirestoreOrderBy(
          collectionFieldIdentifier: createdAtFieldId.deepCopy(),
          descending: false,
        ),
      );
    }
  }

  if (node.hasConditionActions()) {
    for (final branch in node.conditionActions.trueActions) {
      if (branch.hasTrueAction()) {
        _applyOrderByToActionChain(
          branch.trueAction,
          createdAtFieldId,
          collectionName: collectionName,
        );
      }
    }
    if (node.conditionActions.hasFalseAction()) {
      _applyOrderByToActionChain(
        node.conditionActions.falseAction,
        createdAtFieldId,
        collectionName: collectionName,
      );
    }
  }
  if (node.hasLoopAction() && node.loopAction.hasAction()) {
    _applyOrderByToActionChain(
      node.loopAction.action,
      createdAtFieldId,
      collectionName: collectionName,
    );
  }
  if (node.hasParallelActions()) {
    for (final branch in node.parallelActions.actions) {
      _applyOrderByToActionChain(
        branch,
        createdAtFieldId,
        collectionName: collectionName,
      );
    }
  }
  if (node.hasFollowUpAction()) {
    _applyOrderByToActionChain(
      node.followUpAction,
      createdAtFieldId,
      collectionName: collectionName,
    );
  }
}

// Increases the row height of GroupChip and StaffGroupChip by setting taller
// vertical padding — matches WhatsApp-style list item height (~56px).
void _makeGroupChipsTaller(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  for (final name in ['GroupChip', 'StaffGroupChip']) {
    final chip = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
    if (chip == null) continue;
    final tmpChip = UI.container(padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 18));
    chip.props.padding = tmpChip.props.padding;
  }
}

// Converts the DirectMember horizontal chip strip to a full-width vertical list
// matching the GroupChip/StaffGroupChip row style. Non-idempotent: always rebuilds
// the chip layout so it stays consistent with changes to group/staff chip styles.
void _convertDirectMembersToList(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final memberList = findDescendants(wc.node, (n) => n.name == 'ChatsDirectMemberList').firstOrNull;
  if (memberList != null) {
    final lvCopy = memberList.props.listView.deepCopy();
    lvCopy.axis = FFAxis.FF_AXIS_VERTICAL;
    lvCopy.shrinkWrapValue = FFBooleanValue(inputValue: true);
    memberList.props.listView = lvCopy;
  }

  // Remove fixed height from the strip wrapper so it can grow with the list.
  final strip = findDescendants(wc.node, (n) => n.name == 'ChatsDirectStripInner').firstOrNull;
  if (strip != null && strip.props.container.hasDimensions()) {
    final c = strip.props.container.deepCopy();
    c.dimensions.clearHeight();
    strip.props.container = c;
  }

  // Rebuild DirectMemberChip as a full-width row (avatar | name | chevron).
  final chip = findDescendants(wc.node, (n) => n.name == 'DirectMemberChip').firstOrNull;
  if (chip == null) return;

  // Remove fixed width so the chip fills the list item width.
  if (chip.props.container.hasDimensions()) {
    final c = chip.props.container.deepCopy();
    c.dimensions.clearWidth();
    chip.props.container = c;
  }
  final tmpChipPad = UI.container(padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 14));
  chip.props.padding = tmpChipPad.props.padding;

  // Rebuild internals: column (avatar + name) → row (avatar | name | chevron).
  chip.children.clear();

  final avatar = UI.container(name: 'DirectMemberAvatar', width: 40, height: 40, borderRadius: 20);
  _setContainerColor(avatar, FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY)));
  avatar.children.add(UI.icon('person', size: 22, color: UIColor.primaryBackground));

  final nameText = UI.text('', name: 'DirectMemberName', style: UITextStyle.labelMedium);
  if (memberList != null) {
    nameText.props.text.textValue = FFStringValue(
      variable: generatorVarField(memberList.key, 'name'),
    );
  }

  chip.children.add(UI.row(
    name: 'DirectMemberRow',
    spacing: 12,
    children: [
      avatar,
      nameText,
      UI.icon('chevron_right', size: 18, color: UIColor.secondaryText),
    ],
  ));
}

// Adds a "Staffgroepen" section to ChatsPage body — a ListView bound to the
// 'staffGroups' state field. Tapping a staff group: stages pendingStaffGroupId/Name
// → calls GetOrCreateStaffGroupConversation → navigates to ChatDetailPage.
void _wireChatsPageStaffGroupsList(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  // Idempotent: skip if staff groups container already present.
  if (findDescendants(wc.node, (n) => n.name == 'ChatsStaffGroupsContainer').isNotEmpty) return;

  final staffGroupsId = _findPageStateFieldId(project, 'ChatsPage', 'staffGroups');
  if (staffGroupsId == null) return;

  final pendingGroupIdId   = _findAppStateFieldId(project, 'pendingStaffGroupId');
  final pendingGroupNameId = _findAppStateFieldId(project, 'pendingStaffGroupName');
  final currentConvId      = _findAppStateFieldId(project, 'currentConversationId');
  if (pendingGroupIdId == null || pendingGroupNameId == null || currentConvId == null) return;

  final getOrCreate = findCustomAction(project, name: 'GetOrCreateStaffGroupConversation');
  if (getOrCreate == null) return;

  final bodyCol = findByKey(wc.node, 'Column_97jfu72d');
  if (bodyCol == null) return;

  // Build staff groups ListView with DynamicSource.
  final staffGroupsVar = varFromPageState(staffGroupsId.deepCopy());
  staffGroupsVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final staffList = UI.listView(
    name: 'ChatsStaffGroupsList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: staffGroupsVar, itemName: 'staffGroup'),
  );

  // Use generatorVarField (name-based) — avoids struct field ID resolution
  // issues on the new StaffGroupItem struct in the same push.
  final idVar   = generatorVarField(staffList.key, 'id');
  final nameVar = generatorVarField(staffList.key, 'name');

  // Tap chain: stage AppState → call GetOrCreateStaffGroupConversation → navigate.
  final tapChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingGroupIdId.deepCopy(),
            setValue: FFValue(variable: idVar),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingGroupNameId.deepCopy(),
            setValue: FFValue(variable: nameVar),
          ),
        ],
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
    followUpAction: FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: getOrCreate.identifier.deepCopy(),
        ),
      ),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.navigate(
          project,
          pageName: 'ChatDetailPage',
          params: {
            'conversationId': VariableParamValue(varFromAppState(currentConvId.deepCopy())),
            'title': VariableParamValue(nameVar),
          },
        ),
      ),
    ),
  );

  final nameText = UI.text('', name: 'StaffGroupChipName', style: UITextStyle.bodyMedium);
  nameText.props.text.textValue = FFStringValue(variable: nameVar);

  final chipRow = UI.row(
    name: 'StaffGroupChipRow',
    spacing: 12,
    children: [
      UI.icon('groups', size: 18, color: UIColor.primary),
      nameText,
      UI.icon('chevron_right', size: 18, color: UIColor.secondaryText),
    ],
  );

  final chip = UI.container(
    name: 'StaffGroupChip',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: chipRow,
  );
  Actions.onTapChain(chip, tapChain);
  staffList.children.add(chip);

  final sectionLabelContainer = UI.container(
    name: 'ChatsStaffGroupsLabelContainer',
    padding: UIEdgeInsets.all(12),
    child: UI.text('Staffgroepen', style: UITextStyle.titleSmall),
  );
  final staffContainer = UI.container(name: 'ChatsStaffGroupsContainer', child: staffList);

  // Insert after ChatsGroupsListContainer.
  final groupsIdx = bodyCol.children.indexWhere((c) => c.name == 'ChatsGroupsListContainer');
  final insertIdx = groupsIdx >= 0 ? groupsIdx + 1 : bodyCol.children.length;
  bodyCol.children.insert(insertIdx, staffContainer);
  bodyCol.children.insert(insertIdx, sectionLabelContainer);
}

// ─── CreateGroupPage ──────────────────────────────────────────────────────────

void _buildCreateGroupPage(App app, FirestoreCollectionHandle chatGroups) {
  app.ensurePage(
    'CreateGroupPage',
    description: 'Maak een nieuwe chatgroep aan voor je team.',
    route: 'create-group',
    state: {
      'groupName': string,
    },
    body: Column(
      padding: 16,
      spacing: 16,
      children: [
        TextField(
          hint: 'Groepsnaam',
          name: 'GroupNameField',
          onChanged: [SetState(ff.Pages.createGroupPage.state.groupName, TextValue())],
        ),
        Button(
          'Aanmaken',
          name: 'CreateGroupSubmitButton',
          width: double.infinity,
          onTap: [
            If(
              Not(Equals(State(ff.Pages.createGroupPage.state.groupName), '')),
              then: [
                FirestoreCreate(
                  chatGroups,
                  fields: {
                    'name':      State(ff.Pages.createGroupPage.state.groupName),
                    'teamId':    AppState(ff.AppState.currentTeamId),
                    'createdBy': AppState(ff.AppState.userName),
                    'createdAt': Global(GlobalProperty.currentTimestamp),
                    'members':   State(ff.Pages.createGroupPage.state.selectedMemberIds),
                  },
                ),
                NavigateBack(),
              ],
            ),
          ],
        ),
      ],
    ),
  );
}

// ─── Dashboard page ────────────────────────────────────────────────────────────

void _buildDashboardPage(App app, StructHandle footMatch, StructHandle barDuty) {
  app.ensurePage(
    'DashboardPage',
    description: 'Dashboard met komende wedstrijden en bardiensten.',
    route: 'dashboard',
    state: {
      'matches': listOf(footMatch),
      'duties':  listOf(barDuty),
    },
    body: Column(
      children: [
        Container(
          padding: 16,
          child: Text('Komende activiteiten', style: Styles.titleMedium),
        ),
        Container(
          padding: 12,
          child: Text('Wedstrijden', style: Styles.titleSmall),
        ),
        Container(name: 'DashboardMatchesContainer'),
        Container(
          padding: 12,
          child: Text('Bardiensten', style: Styles.titleSmall),
        ),
        Container(name: 'DashboardDutiesContainer'),
      ],
    ),
  );
}

void _addDashboardAppBar(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  if (getPropertyChild(wc.node, 'appBar') != null) return;
  final titleNode = UI.text('Dashboard', name: 'DashboardAppBarTitle', style: UITextStyle.titleLarge);
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: false);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

void _buildDashboardContent(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  final bodyCol = getPropertyChild(wc.node, 'body');
  if (bodyCol != null && bodyCol.type == FFWidgetType.Column && !bodyCol.props.column.scrollable) {
    final colCopy = bodyCol.props.column.deepCopy();
    colCopy.scrollable = true;
    bodyCol.props.column = colCopy;
  }

  _buildDashboardMatchesList(project, wc);
  _buildDashboardDutiesList(project, wc);
}

void _buildDashboardMatchesList(FFProject project, FFWidgetClass wc) {
  final container = findDescendants(wc.node, (n) => n.name == 'DashboardMatchesContainer').firstOrNull;
  if (container == null) return;
  // Vers opbouwen bij elke push zodat layout-wijzigingen meekomen.
  container.children.clear();

  final matchesId = _findPageStateFieldId(project, 'DashboardPage', 'matches');
  if (matchesId == null) return;

  final matchesVar = varFromPageState(matchesId.deepCopy());
  matchesVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final listView = UI.listView(
    name: 'DashboardMatchesList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    dynamicSource: DynamicSource(variable: matchesVar, itemName: 'match'),
  );

  // Tegenstander (titel).
  final opponentText = UI.text('', name: 'DashboardMatchOpponent', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'opponent'));

  // Thuis/Uit-badge: reken de bool isHome om naar 'Thuis'/'Uit'.
  final homeAwayVar = codeExpressionVar(
    expression: "(isHome ?? false) ? 'Thuis' : 'Uit'",
    arguments: [
      CodeExpressionArg(
        name: 'isHome',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
        value: FFValue(variable: generatorVarField(listView.key, 'isHome')),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );
  final homeAwayText = UI.text('', name: 'DashboardMatchHomeAway',
      style: UITextStyle.labelSmall, color: UIColor.secondaryBackground);
  homeAwayText.props.text.textValue = FFStringValue(variable: homeAwayVar);
  homeAwayText.props.padding =
      FFPadding(type: FFPadding_PaddingType.FF_PADDING_ALL, allValue: FFDoubleValue(inputValue: 5.0));
  final homeAwayBadge = UI.container(
    name: 'DashboardMatchHomeAwayBadge',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 6),
    borderRadius: 8,
    color: UIColor.primary,
    child: homeAwayText,
  );

  // Datum + tijd.
  final dateText = UI.text('', name: 'DashboardMatchDate',
      style: UITextStyle.bodySmall, color: UIColor.secondaryText);
  dateText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'matchDatetime'));

  final metaRow = UI.row(
    name: 'DashboardMatchMetaRow',
    spacing: 8,
    crossAxisAlignment: UICrossAxisAlignment.center,
    children: [homeAwayBadge, dateText],
  );

  // Locatie (alleen tonen als gevuld).
  final locationText = UI.text('', name: 'DashboardMatchLocation',
      style: UITextStyle.bodySmall, color: UIColor.secondaryText);
  locationText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'location'));
  final locationRow = UI.row(
    name: 'DashboardMatchLocationRow',
    spacing: 4,
    crossAxisAlignment: UICrossAxisAlignment.center,
    children: [
      UI.icon('place', size: 14, color: UIColor.secondaryText),
      locationText,
    ],
  );
  final locVisibleVar = conditionVar(
    generatorVarField(listView.key, 'location'),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;
  setConditionalVisibility(locationRow, variable: locVisibleVar);

  final infoColumn = UI.column(
    name: 'DashboardMatchInfo',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [opponentText, metaRow, locationRow],
  );

  // Leading: tegenstander-logo (indien bekend), anders de bal-icoon als fallback.
  final logoImage = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.CircleImage,
    name: 'DashboardMatchOpponentLogo',
    props: FFWidgetProperties(
      image: FFImage(
        type:       FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
        pathValue:  FFStringValue(variable: generatorVarField(listView.key, 'opponentLogo')),
        fit:        FFBoxFit.FF_BOX_FIT_COVER,
        cached:     true,
        dimensions: FFDimensions(
          width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 32.0)),
          height: FFDim(pixelsValue: FFDoubleValue(inputValue: 32.0)),
        ),
      ),
    ),
  );
  final hasLogoVar = conditionVar(
    generatorVarField(listView.key, 'opponentLogo'),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;
  setConditionalVisibility(logoImage, variable: hasLogoVar);

  final fallbackIcon = UI.icon('sports_soccer', size: 28, color: UIColor.primary);
  final noLogoVar = conditionVar(
    generatorVarField(listView.key, 'opponentLogo'),
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;
  setConditionalVisibility(fallbackIcon, variable: noLogoVar);

  final card = UI.container(
    name: 'DashboardMatchCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'DashboardMatchRow',
      spacing: 12,
      crossAxisAlignment: UICrossAxisAlignment.center,
      children: [
        logoImage,
        fallbackIcon,
        UI.expanded(infoColumn),
      ],
    ),
  );

  listView.children.add(card);
  container.children.add(listView);
}

// Team-switcher bovenaan het dashboard: horizontale chips van alle gekoppelde
// teams (AppState.availableTeams). Alleen zichtbaar bij >1 team
// (AppState.hasMultipleTeams). Tik op een team → zet currentTeamId/Name en
// herlaad "mijn wedstrijden" + "mijn trainingen" voor dat team. Bardiensten en
// rijschema blijven ongemoeid (op persoon). Idempotent (rebuild elke push).
void _addDashboardTeamSwitcher(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  final availTeamsId = _findAppStateFieldId(project, 'availableTeams');
  final teamIdId     = _findAppStateFieldId(project, 'currentTeamId');
  final teamNameId   = _findAppStateFieldId(project, 'currentTeamName');
  final authTokenId  = _findAppStateFieldId(project, 'authToken');
  final multiId      = _findAppStateFieldId(project, 'hasMultipleTeams');
  final matchesId    = _findPageStateFieldId(project, 'DashboardPage', 'matches');
  final trainingsId  = _findAppStateFieldId(project, 'trainings');
  if (availTeamsId == null || teamIdId == null || teamNameId == null ||
      authTokenId == null || multiId == null) return;

  // Rebuild fresh each push.
  for (final n in findDescendants(wc.node, (x) => x.name == 'DashboardTeamSwitcher').toList()) {
    removeByKey(wc.node, n.key);
  }

  // Body-column = parent van DashboardMatchesContainer.
  final matchesContainer =
      findDescendants(wc.node, (n) => n.name == 'DashboardMatchesContainer').firstOrNull;
  if (matchesContainer == null) return;
  final bodyCol = findDescendants(wc.node, (_) => true)
      .where((n) => n.children.any((c) => identical(c, matchesContainer)))
      .firstOrNull;
  if (bodyCol == null) return;

  // Horizontale teamlijst.
  final teamsVar = varFromAppState(availTeamsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final list = UI.listView(
    name: 'DashboardTeamSwitcherList',
    horizontal: true,
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: teamsVar, itemName: 'team'),
  );

  // Actief team = team.id == currentTeamId. We tonen per item twee chips: een
  // gemarkeerde (clubkleur) voor het actieve team en een grijze voor de rest,
  // elk met conditionele zichtbaarheid.
  final activeVar = conditionVar(
    generatorVarField(list.key, 'id'),
    FFCondition_Relation.EQUAL_TO,
    varFromAppState(teamIdId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
  ).variable;
  final inactiveVar = conditionVar(
    generatorVarField(list.key, 'id'),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromAppState(teamIdId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
  ).variable;

  // Actieve chip (clubkleur + witte tekst).
  final activeText = UI.text('', name: 'DashboardTeamSwitcherActiveName',
      style: UITextStyle.bodyMedium, color: UIColor.secondaryBackground);
  activeText.props.text.textValue = FFStringValue(variable: generatorVarField(list.key, 'name'));
  activeText.props.padding =
      FFPadding(type: FFPadding_PaddingType.FF_PADDING_ALL, allValue: FFDoubleValue(inputValue: 5.0));
  final activeChip = UI.container(
    name: 'DashboardTeamSwitcherActiveChip',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 10),
    borderRadius: 20,
    color: UIColor.primary,
    child: activeText,
  );
  setConditionalVisibility(activeChip, variable: activeVar);

  // Inactieve chip (grijs).
  final chipText = UI.text('', name: 'DashboardTeamSwitcherName',
      style: UITextStyle.bodyMedium, color: UIColor.primaryText);
  chipText.props.text.textValue = FFStringValue(variable: generatorVarField(list.key, 'name'));
  final inactiveChip = UI.container(
    name: 'DashboardTeamSwitcherChip',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 10),
    borderRadius: 20,
    color: UIColor.secondaryBackground,
    child: chipText,
  );
  setConditionalVisibility(inactiveChip, variable: inactiveVar);

  // Item-wrapper (bevat beide chips; tik hierop).
  final chip = UI.row(
    name: 'DashboardTeamSwitcherItem',
    mainAxisMin: true,
    children: [activeChip, inactiveChip],
  );

  // Tap-chain: currentTeam zetten → wedstrijden herladen → trainingen herladen.
  final setStateNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          FFLocalStateFieldUpdate(
            fieldIdentifier: teamIdId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(list.key, 'id')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: teamNameId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(list.key, 'name')),
          ),
        ],
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
  );

  final matchesNode = Actions.apiCallNode(
    project,
    endpointName: 'GetUpcomingMatches',
    groupName: 'VoetbalPlannerAPI',
    dynamicVariables: {
      'token':  varFromAppState(authTokenId.deepCopy()),
      'teamId': generatorVarField(list.key, 'id'),
    },
    outputVariableName: 'switchMatches',
    nodeKey: chip.key,
    onSuccess: (ctx) => Actions.chain([
      if (matchesId != null)
        Actions.updatePageState(project, widgetClassName: 'DashboardPage', updates: [
          StateFieldUpdate.setFromVariable('matches', ctx.responseVar),
        ]),
    ]),
  );
  setStateNode.followUpAction = matchesNode;

  // Trainingen herladen (indien endpoint + state aanwezig).
  final hasTrainings =
      findApiEndpoint(project, name: 'GetTrainingsList', groupName: 'VoetbalPlannerAPI') != null;
  if (hasTrainings && trainingsId != null) {
    final trainingsNode = Actions.apiCallNode(
      project,
      endpointName: 'GetTrainingsList',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token':  varFromAppState(authTokenId.deepCopy()),
        'teamId': generatorVarField(list.key, 'id'),
      },
      outputVariableName: 'switchTrainings',
      nodeKey: chip.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updateAppState(project, updates: [
          StateFieldUpdate.setFromVariable('trainings', ctx.responseVar),
        ]),
      ]),
    );
    var tail = matchesNode;
    while (tail.hasFollowUpAction()) tail = tail.followUpAction;
    tail.followUpAction = trainingsNode;
  }

  Actions.onTapChain(chip, setStateNode);
  list.children.add(chip);

  final switcher = UI.container(
    name: 'DashboardTeamSwitcher',
    height: 48,
    child: list,
  );
  // Alleen tonen bij >1 team.
  setConditionalVisibility(
    switcher,
    variable: varFromAppState(multiId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
  );

  bodyCol.children.insert(0, switcher);
}

// Verwijdert de tijdelijke clubLogoUrl-debug-readout (indien nog aanwezig).
void _removeClubLogoDebug(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  for (final n in findDescendants(wc.node, (x) => x.name == 'ClubLogoDebug').toList()) {
    removeByKey(wc.node, n.key);
  }
}

void _buildDashboardDutiesList(FFProject project, FFWidgetClass wc) {
  final container = findDescendants(wc.node, (n) => n.name == 'DashboardDutiesContainer').firstOrNull;
  if (container == null || container.children.isNotEmpty) return;

  final dutiesId = _findPageStateFieldId(project, 'DashboardPage', 'duties');
  if (dutiesId == null) return;

  final dutiesVar = varFromPageState(dutiesId.deepCopy());
  dutiesVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final listView = UI.listView(
    name: 'DashboardDutiesList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    dynamicSource: DynamicSource(variable: dutiesVar, itemName: 'duty'),
  );

  final shiftText = UI.text('', name: 'DashboardDutyShift', style: UITextStyle.bodyMedium);
  shiftText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'shift'));

  final dateText = UI.text('', name: 'DashboardDutyDate', style: UITextStyle.bodySmall);
  dateText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'date'));

  final card = UI.container(
    name: 'DashboardDutyCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'DashboardDutyRow',
      spacing: 12,
      children: [
        UI.icon('sports_bar', size: 24, color: UIColor.primary),
        UI.column(
          name: 'DashboardDutyInfo',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 4,
          children: [shiftText, dateText],
        ),
      ],
    ),
  );

  listView.children.add(card);
  container.children.add(listView);
}

// Custom action: ververst het huidige team vanuit /auth/me zonder opnieuw in
// te loggen. Werkt availableTeams bij en schakelt naar het standaard-team als
// het huidige team niet meer toegankelijk is (na een teamwissel via Sportlink
// of een handmatige herkoppeling). Draait bij elke Dashboard-load.
void _ensureRefreshCurrentTeamAction(FFProject project) {
  const _code = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<String> refreshCurrentTeam() async {
  final token = FFAppState().authToken;
  if (token.isEmpty) return '';
  try {
    final response = await http.get(
      Uri.parse('https://voetbalplanner.nubix.nl/api/v1/auth/me'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode != 200) return FFAppState().currentTeamId;
    final body = jsonDecode(response.body) as Map<String, dynamic>?;
    if (body == null || body['success'] != true) {
      return FFAppState().currentTeamId;
    }

    final user = (body['data'] as Map<String, dynamic>?) ?? {};
    final club = (user['club'] as Map<String, dynamic>?) ?? {};

    final defaultTeamId = (user['team_id'] as String?) ?? '';
    final defaultTeamName = (user['team_name'] as String?) ?? '';

    final rawTeams = ((user['teams'] as List?) ?? const [])
        .whereType<Map>()
        .map((t) => {
              'id': (t['id']?.toString() ?? ''),
              'name': (t['name']?.toString() ?? ''),
              'role': (t['role']?.toString() ?? ''),
            })
        .where((t) => (t['id'] ?? '').isNotEmpty)
        .toList();

    var teams = rawTeams
        .map<TeamOptionStruct?>((t) => TeamOptionStruct.maybeFromMap(t))
        .where((t) => t != null)
        .cast<TeamOptionStruct>()
        .toList();
    // Fallback (oude backend zonder teams[]): toon tenminste het huidige team.
    if (teams.isEmpty && defaultTeamId.isNotEmpty) {
      final fb = TeamOptionStruct.maybeFromMap(
          {'id': defaultTeamId, 'name': defaultTeamName});
      if (fb != null) teams = [fb];
    }

    final currentId = FFAppState().currentTeamId;
    final currentStillListed =
        currentId.isNotEmpty && rawTeams.any((t) => t['id'] == currentId);
    final currentName = currentStillListed
        ? (rawTeams.firstWhere((t) => t['id'] == currentId)['name'] ?? '')
        : '';

    FFAppState().update(() {
      FFAppState().availableTeams = teams;
      FFAppState().hasMultipleTeams = teams.length > 1;
      final _club = (club['name'] as String?) ?? '';
      if (_club.isNotEmpty) FFAppState().clubName = _club;
      final _logo = (club['logo_url'] as String?) ?? '';
      if (_logo.isNotEmpty) FFAppState().clubLogoUrl = _logo;
      FFAppState().relatiecode = (user['relatiecode'] as String?) ?? '';
      FFAppState().profilePhotoUrl =
          (user['profile_photo_url'] as String?) ?? '';
      if (!currentStillListed) {
        // Huidige team niet meer toegankelijk → schakel naar standaard-team.
        FFAppState().currentTeamId = defaultTeamId;
        FFAppState().currentTeamName = defaultTeamName;
      } else if (currentName.isNotEmpty) {
        // Naam kan gewijzigd zijn; sync de naam van het huidige team.
        FFAppState().currentTeamName = currentName;
      }
    });
    return FFAppState().currentTeamId;
  } catch (_) {
    return FFAppState().currentTeamId;
  }
}
''';

  if (findCustomAction(project, name: 'RefreshCurrentTeam') == null) {
    addCustomAction(
      project,
      name: 'RefreshCurrentTeam',
      description:
          'Ververst huidig team + availableTeams vanuit /auth/me. Schakelt naar het standaard-team als het huidige team niet meer toegankelijk is (teamwissel/herkoppeling). Draait bij Dashboard-load.',
      arguments: const [],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
      ),
      code: _code,
    );
  } else {
    updateCustomAction(project, name: 'RefreshCurrentTeam', code: _code);
  }
}

void _wireDashboardLoad(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  final authTokenId = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'authToken', orElse: () => null)
      ?.parameter.identifier;
  if (authTokenId == null) return;

  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  final scaffoldKey = wc.node.key;

  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  // Build GetBarDuties node.
  final dutiesNode = Actions.apiCallNode(
    project,
    endpointName: 'GetBarDuties',
    groupName: 'VoetbalPlannerAPI',
    variables: {'page': '1'},
    // Bardiensten zijn op persoon: GEEN teamId meegeven zodat alle teams van de
    // gebruiker getoond worden (niet beïnvloed door de dashboard team-switcher).
    dynamicVariables: {
      'token': varFromAppState(authTokenId.deepCopy()),
    },
    outputVariableName: 'dashDuties',
    nodeKey: scaffoldKey,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(
        project,
        widgetClassName: 'DashboardPage',
        updates: [StateFieldUpdate.setFromVariable('duties', ctx.responseVar)],
      ),
    ]),
    onFailure: (ctx) => Actions.chain([
      Actions.snackBar('Kon bardiensten niet laden.'),
    ]),
  );

  // Build GetUpcomingMatches node and chain dutiesNode after it.
  final matchesNode = Actions.apiCallNode(
    project,
    endpointName: 'GetUpcomingMatches',
    groupName: 'VoetbalPlannerAPI',
    dynamicVariables: {
      'token': varFromAppState(authTokenId.deepCopy()),
      if (currentTeamIdId != null) 'teamId': varFromAppState(currentTeamIdId.deepCopy()),
    },
    outputVariableName: 'dashMatches',
    nodeKey: scaffoldKey,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(
        project,
        widgetClassName: 'DashboardPage',
        updates: [StateFieldUpdate.setFromVariable('matches', ctx.responseVar)],
      ),
    ]),
    onFailure: (ctx) => Actions.chain([
      Actions.snackBar('Kon wedstrijden niet laden.'),
    ]),
  );
  // Append duties after the matches chain's tail.
  var tail = matchesNode;
  while (tail.hasFollowUpAction()) tail = tail.followUpAction;
  tail.followUpAction = dutiesNode;

  // Append WatchUnreadChatCount at the end of the chain so the unread badge
  // stream kicks in once the dashboard data is loaded. Use the `customAction`
  // / `FFCustomActionCall` proto field (NOT `customCodeCall`) so FF codegen
  // emits a proper `await actions.watchUnreadChatCount()` call with the
  // `actions` import wired in automatically.
  final watchUnread = findCustomAction(project, name: 'WatchUnreadChatCount');
  if (watchUnread != null) {
    final watchAction = FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: watchUnread.identifier.deepCopy(),
        argumentValues: FFFunctionCallValues(),
      ),
    );
    final watchNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: watchAction,
    );
    var t = dutiesNode;
    while (t.hasFollowUpAction()) t = t.followUpAction;
    t.followUpAction = watchNode;
  }

  // Prepend RefreshCurrentTeam zodat het huidige team is ververst vóórdat de
  // data-calls currentTeamId lezen. Detecteert een teamwissel (Sportlink of
  // handmatige herkoppeling) zonder dat de gebruiker opnieuw hoeft in te loggen.
  final refreshTeam = findCustomAction(project, name: 'RefreshCurrentTeam');
  FFActionNode rootNode = matchesNode;
  if (refreshTeam != null) {
    rootNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: refreshTeam.identifier.deepCopy(),
          argumentValues: FFFunctionCallValues(),
        ),
      ),
      followUpAction: matchesNode,
    );
  }

  // Wire directly (no auth guard): DashboardPage requires authentication, so authToken
  // is always present when this fires. onFailure handlers handle any 401 gracefully.
  Actions.onPageLoadChain(wc.node, rootNode);
}

// Patch DashboardMatchesList and DashboardDutiesList to shrinkWrap: true.
// Both ListViews live inside a scrollable Column (unbounded vertical space), so
// without shrinkWrap the viewport has no height constraint and renders nothing.
void _fixDashboardListViewShrinkWrap(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  for (final name in ['DashboardMatchesList', 'DashboardDutiesList']) {
    final node = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
    if (node == null) continue;
    if (node.props.listView.shrinkWrapValue.inputValue) continue;
    final lvCopy = node.props.listView.deepCopy();
    lvCopy.shrinkWrapValue = FFBooleanValue(inputValue: true);
    node.props.listView = lvCopy;
  }
}

// Adds a "Rijschema" section to the DashboardPage body:
//   - driveMatches state field (List<FootMatch>)
//   - Section header "Rijschema" + DashboardDriveContainer with DashboardDriveList
//   - Card shows opponent + matchDatetime (same style as DashboardMatchCard)
// Idempotent: skips if DashboardDriveContainer already present.
void _addDashboardDriveSection(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  // Skip if section already built.
  if (findDescendants(wc.node, (n) => n.name == 'DashboardDriveContainer').isNotEmpty) return;

  // Ensure driveMatches state field exists.
  // Use isList=true on the parameter (not listType wrapping) — correct serialization
  // for DataStruct list fields per R15 diagnostic guidance.
  final fieldExists = wc.classModel.stateFields.any(
    (f) => f.parameter.identifier.name == 'driveMatches',
  );
  if (!fieldExists) {
    final struct = findDataStruct(project, name: 'FootMatch');
    if (struct == null) return;
    final fieldId = FFIdentifier(
      name: 'driveMatches',
      key: generateRandomAlphaNumericString(),
    );
    final param = FFParameter(
      identifier: fieldId,
      dataType: dataStructType(struct.identifier.deepCopy()),
    );
    param.isList = true;
    wc.classModel.stateFields.add(FFWidgetClassStateField(parameter: param));
  }

  final driveMatchesId = _findPageStateFieldId(project, 'DashboardPage', 'driveMatches');
  if (driveMatchesId == null) return;

  final bodyCol = getPropertyChild(wc.node, 'body');
  if (bodyCol == null) return;

  // Section label.
  final labelContainer = UI.container(
    name: 'DashboardDriveLabelContainer',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    child: UI.text('Rijschema', name: 'DashboardDriveLabel', style: UITextStyle.titleSmall),
  );

  // Content container.
  final driveMatchesVar = varFromPageState(driveMatchesId.deepCopy());
  driveMatchesVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final listView = UI.listView(
    name: 'DashboardDriveList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    dynamicSource: DynamicSource(variable: driveMatchesVar, itemName: 'driveMatch'),
  );

  // shrinkWrap — same fix as DashboardMatchesList/DashboardDutiesList.
  final lvCopy = listView.props.listView.deepCopy();
  lvCopy.shrinkWrapValue = FFBooleanValue(inputValue: true);
  listView.props.listView = lvCopy;

  final opponentText = UI.text('', name: 'DashboardDriveOpponent', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'opponent'));

  final dateText = UI.text('', name: 'DashboardDriveDate', style: UITextStyle.bodySmall);
  dateText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'matchDatetime'));

  final card = UI.container(
    name: 'DashboardDriveCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'DashboardDriveRow',
      spacing: 12,
      children: [
        UI.icon('directions_car', size: 24, color: UIColor.primary),
        UI.column(
          name: 'DashboardDriveInfo',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 4,
          children: [opponentText, dateText],
        ),
      ],
    ),
  );

  listView.children.add(card);

  final driveContainer = UI.container(name: 'DashboardDriveContainer', child: listView);

  bodyCol.children.add(labelContainer);
  bodyCol.children.add(driveContainer);
}

// Adds ON_TAP navigation to the three dashboard cards (matches, duties, drive)
// so they open the matching detail page with the right id parameter.
// Idempotent: skips a card if an ON_TAP trigger already exists.
void _wireDashboardCardNavigation(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  void wire({
    required String cardName,
    required String listName,
    required String detailPage,
    required String detailParam,
    required String structName,
  }) {
    if (project.getWidgetClassByName(detailPage) == null) return;

    final card = findDescendants(wc.node, (n) => n.name == cardName).firstOrNull;
    if (card == null) return;

    final hasTap = card.triggerActions.any(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    if (hasTap) return;

    final list = findDescendants(wc.node, (n) => n.name == listName).firstOrNull;
    if (list == null) return;

    final idFieldId = _findStructFieldId(project, structName, 'id');
    final idVar = idFieldId != null
        ? (varFromGeneratorVariable(list.key)
            ..operations.add(FFVariableOperation(
              accessDataStructField: FFAccessDataStructField(
                fieldIdentifier: idFieldId.deepCopy(),
              ),
            )))
        : generatorVarField(list.key, 'id');

    final navigateAction = Actions.navigate(
      project,
      pageName: detailPage,
      params: {detailParam: VariableParamValue(idVar)},
    );
    Actions.onTap(card, navigateAction);
  }

  wire(
    cardName: 'DashboardMatchCard',
    listName: 'DashboardMatchesList',
    detailPage: 'WedstrijdDetailPage',
    detailParam: 'matchId',
    structName: 'FootMatch',
  );
  wire(
    cardName: 'DashboardDutyCard',
    listName: 'DashboardDutiesList',
    detailPage: 'BardienDetailPage',
    detailParam: 'dutyId',
    structName: 'BarDuty',
  );
  wire(
    cardName: 'DashboardDriveCard',
    listName: 'DashboardDriveList',
    detailPage: 'RijschemaDetailPage',
    detailParam: 'matchId',
    structName: 'FootMatch',
  );
}

// Adds "Niets gepland" placeholder text below each dashboard ListView.
// Visibility uses a "first item field == empty" heuristic:
//   - empty list  → first?.field returns null/""  → EQUAL_TO EMPTY_STRING → show placeholder
//   - non-empty   → first?.field has value         → NOT equal → hide placeholder
// Idempotent: skips if placeholder already present.
void _addDashboardEmptyPlaceholders(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  final scaffoldKey = wc.node.key;

  // Builds a boolean variable: true when the list state field is empty.
  // Strategy: listNumItems → integer length, compared to literal 0 via
  // FFValue.inputValue (literal parameter value, not a constant variable).
  FFVariable _emptyVarForStruct(FFIdentifier stateFieldId, String _structName) {
    final lengthVar = varFromPageState(stateFieldId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey)
      ..operations.add(FFVariableOperation(listNumItems: FFListNumItems()));
    return FFVariable(
      source: FFVariableSource.FUNCTION_CALL,
      functionCall: FFFunctionCall(
        condition: FFCondition(relation: FFCondition_Relation.EQUAL_TO),
        values: [
          FFValue(variable: lengthVar),
          FFValue(inputValue: FFParameterValue(serializedValue: '0')),
        ],
      ),
    );
  }

  // Builds a placeholder Text node with conditional visibility.
  FFNode _placeholder(String nodeName, FFVariable isEmptyVar) {
    final text = UI.text('Niets gepland', name: nodeName, style: UITextStyle.bodySmall);
    final copy = text.props.text.deepCopy();
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT));
    text.props.text = copy;
    final padRef = UI.container(padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 8));
    text.props.padding = padRef.props.padding.deepCopy();
    setConditionalVisibility(text, variable: isEmptyVar);
    return text;
  }

  // Configs: [containerName, stateName, placeholderName, structName]
  const configs = [
    ('DashboardMatchesContainer', 'matches',      'DashboardMatchesEmpty', 'FootMatch'),
    ('DashboardDutiesContainer',  'duties',       'DashboardDutiesEmpty',  'BarDuty'),
    ('DashboardDriveContainer',   'driveMatches', 'DashboardDriveEmpty',   'FootMatch'),
  ];

  for (final (containerName, stateName, placeholderName, structName) in configs) {
    final container = findDescendants(wc.node, (n) => n.name == containerName).firstOrNull;
    if (container == null) continue;

    final stateFieldId = _findPageStateFieldId(project, 'DashboardPage', stateName);
    if (stateFieldId == null) continue;

    final isEmptyVar = _emptyVarForStruct(stateFieldId, structName);

    final existing = findDescendants(container, (n) => n.name == placeholderName).firstOrNull;
    if (existing != null) {
      setConditionalVisibility(existing, variable: isEmptyVar);
      continue;
    }

    final placeholder = _placeholder(placeholderName, isEmptyVar);

    if (container.children.isEmpty) continue;
    final listView = container.children.first;
    final col = UI.column(
      name: '${containerName}Col',
      children: [listView, placeholder],
    );
    container.children.clear();
    container.children.add(col);
  }
}

// ─── WedstrijdenPage / BardienPage / RijschemaPage: "Niets gepland" ─────────
//
// Voegt een "Niets gepland" placeholder toe als sibling van de ConditionalBuilder
// in de pagina-body kolom. De ConditionalBuilder zelf blijft ONGEWIJZIGD —
// geen hoogte-constraint problemen in de ListView.
// Idempotent: slaat over als de placeholder al aanwezig is.
void _addPageListEmptyPlaceholders(FFProject project) {

  FFIdentifier? _structFieldId(String structName, String fieldName) {
    final s = findDataStruct(project, name: structName);
    if (s == null) return null;
    return s.fields
        .cast<FFParameter?>()
        .firstWhere((f) => f?.identifier.name == fieldName, orElse: () => null)
        ?.identifier;
  }

  // isEmptyVar: true when list is empty (first item field is empty/null).
  FFVariable? _isEmptyVar(
    String pageName, String scaffoldKey,
    String stateFieldName, String structName, String structFieldName,
  ) {
    final stateId = _findPageStateFieldId(project, pageName, stateFieldName);
    if (stateId == null) return null;
    final fieldId = _structFieldId(structName, structFieldName);

    final firstFieldVar = varFromPageState(stateId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey)
      ..operations.add(FFVariableOperation(
        listItemAtIndex: FFListItemAtIndex(type: FFListItemAtIndex_IndexType.FIRST),
      ))
      ..operations.add(FFVariableOperation(
        accessDataStructField: FFAccessDataStructField(
          fieldIdentifier: fieldId?.deepCopy() ?? FFIdentifier(name: structFieldName),
        ),
      ));

    return conditionVar(
      firstFieldVar,
      FFCondition_Relation.EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
    ).variable;
  }

  // Adds a placeholder text as sibling AFTER the ConditionalBuilder in the body column.
  // Does NOT touch the ConditionalBuilder's internal structure.
  void _addPlaceholder({
    required String pageName,
    required String scaffoldKey,
    required String bodyColKey,          // key of the Column that contains the CB
    required String conditionalBuilderKey,
    required String stateFieldName,
    required String structName,
    required String structFieldName,
    required String placeholderName,
  }) {
    final pageWc = findPage(project, name: pageName);
    if (pageWc == null) return;

    if (findDescendants(pageWc.node, (n) => n.name == placeholderName).isNotEmpty) return;

    final isEmptyVar = _isEmptyVar(
      pageName, scaffoldKey, stateFieldName, structName, structFieldName,
    );
    if (isEmptyVar == null) return;

    final text = UI.text(
      'Geen activiteiten gepland',
      name: placeholderName,
      style: UITextStyle.bodyMedium,
    );
    final textCopy = text.props.text.deepCopy();
    textCopy.colorValue = FFColorValue(
      inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
    );
    text.props.text = textCopy;
    text.props.padding = (UI.container(
      padding: UIEdgeInsets.symmetric(horizontal: 24, vertical: 16),
    )).props.padding.deepCopy();
    setConditionalVisibility(text, variable: isEmptyVar);

    // Insert placeholder after the ConditionalBuilder in the body column.
    final bodyCol = findByKey(pageWc.node, bodyColKey);
    if (bodyCol == null) return;

    final cbIdx = bodyCol.children
        .indexWhere((n) => n.key == conditionalBuilderKey);

    if (cbIdx >= 0 && cbIdx < bodyCol.children.length - 1) {
      bodyCol.children.insert(cbIdx + 1, text);
    } else {
      bodyCol.children.add(text);
    }
  }

  // ── Herstel: RijschemaPage body opnieuw opbouwen als die leeg is ────────────
  // Eerdere opruiming heeft de body verwijderd; we bouwen hem opnieuw op met
  // de originele widget-keys zodat bestaande wire-functies blijven werken.
  _restoreRijschemaBodyIfMissing(project);
  // Vul cardColumn (Column_cx7sodso) met basis-rijen als die leeg is.
  _ensureRijschemaCardBaseRows(project);

  // ── Herstel: ListView direct als kind van ListViewWrapper plaatsen ────────────
  //
  // Vorige pushes hebben de ListView gewrapped in een Column INSIDE de
  // ListViewWrapper-Container. Container heeft maar één kind dat hoogte krijgt
  // van de Container zelf; binnen een Column zonder hoogte-constraint krijgt
  // de ListView geen ruimte → items niet zichtbaar.
  //
  // Fix: zoek het kind van elke ListViewWrapper. Als het een Column is met
  // naam '${pageName}EmptyCol', vervang die door zijn eerste kind (de ListView).
  final _wrapperKeys = {
    'WedstrijdenPage': 'Container_k5bkzn1l',
    'BardienPage':     'Container_2u86md6h',
    'RijschemaPage':   'Container_aznkrhkc',
  };
  final _expectedListViewKeys = {
    'WedstrijdenPage': 'ListView_erdckv6e',
    'BardienPage':     'ListView_tu54znnh',
    'RijschemaPage':   'ListView_55kreos3',
  };

  for (final entry in _wrapperKeys.entries) {
    final pageName   = entry.key;
    final wrapperKey = entry.value;
    final pageWc     = findPage(project, name: pageName);
    if (pageWc == null) continue;

    final wrapper = findByKey(pageWc.node, wrapperKey);
    if (wrapper == null || wrapper.children.isEmpty) continue;

    final expectedLvKey = _expectedListViewKeys[pageName]!;

    // Als kind[0] al de originele ListView is, niets doen.
    if (wrapper.children.first.key == expectedLvKey) continue;

    // Anders: zoek de originele ListView ERGENS in de wrapper-boom en zet
    // hem terug als direct kind.
    final listView = findDescendants(wrapper, (n) => n.key == expectedLvKey).firstOrNull;
    if (listView == null) continue;

    wrapper.children
      ..clear()
      ..add(listView);
  }

  // Verwijder alleen stale WRAPPER nodes uit eerdere mislukte pushes — niet
  // de placeholders zelf (die voegen we hieronder netjes toe / vernieuwen we).
  // RijschemaBodyCol blijft staan: wordt door de nieuwe code hergebruikt.
  const _staleNames = [
    'WedstrijdenPageContentCol', 'BardienPageContentCol', 'RijschemaPageContentCol',
    'WedstrijdenPageEmptyCol', 'BardienPageEmptyCol', 'RijschemaPageEmptyCol',
  ];

  for (final pageName in _wrapperKeys.keys) {
    final pageWc = findPage(project, name: pageName);
    if (pageWc == null) continue;

    for (final name in _staleNames) {
      while (true) {
        final node = findDescendants(pageWc.node, (n) => n.name == name).firstOrNull;
        if (node == null) break;
        final res = findParentByKey(pageWc.node, node.key);
        if (res == null) break;
        res.parent.children.removeWhere((n) => n.key == node.key);
      }
    }
  }

  // ── Voeg "Niets gepland" placeholders toe (zichtbaar als lijst leeg is) ────
  // Plaatsing: BUITEN de ConditionalBuilder, ALS extra kind in de body-kolom.
  // De ConditionalBuilder en zijn ListView blijven ongewijzigd.
  // Visibility: list == EMPTY_LIST.

  FFVariable _emptyListVar(String pageName, String scaffoldKey, String stateField, String _structName) {
    final stateId = _findPageStateFieldId(project, pageName, stateField);
    if (stateId == null) return varFromConstant(FFConstantsVariable_ConstantValue.FALSE);
    final lengthVar = varFromPageState(stateId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey)
      ..operations.add(FFVariableOperation(listNumItems: FFListNumItems()));
    return FFVariable(
      source: FFVariableSource.FUNCTION_CALL,
      functionCall: FFFunctionCall(
        condition: FFCondition(relation: FFCondition_Relation.EQUAL_TO),
        values: [
          FFValue(variable: lengthVar),
          FFValue(inputValue: FFParameterValue(serializedValue: '0')),
        ],
      ),
    );
  }

  void _addBelowCB({
    required String pageName,
    required String scaffoldKey,
    required String bodyColKey,
    required String conditionalBuilderKey,
    required String stateFieldName,
    required String structName,
    required String placeholderName,
  }) {
    final pageWc = findPage(project, name: pageName);
    if (pageWc == null) return;

    final visVar = _emptyListVar(pageName, scaffoldKey, stateFieldName, structName);

    // Update visibility op bestaande placeholder en stop.
    final existing = findDescendants(pageWc.node,
        (n) => n.name == placeholderName).firstOrNull;
    if (existing != null) {
      setConditionalVisibility(existing, variable: visVar);
      return;
    }

    final text = UI.text('Niets gepland',
        name: placeholderName, style: UITextStyle.bodyMedium);
    final textCopy = text.props.text.deepCopy();
    textCopy.colorValue = FFColorValue(
        inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT));
    text.props.text = textCopy;
    text.props.padding = (UI.container(
        padding: UIEdgeInsets.symmetric(horizontal: 24, vertical: 16)))
        .props.padding.deepCopy();
    setConditionalVisibility(text, variable: visVar);

    final bodyCol = findByKey(pageWc.node, bodyColKey);
    if (bodyCol == null) return;

    final cbIdx = bodyCol.children.indexWhere(
        (n) => n.key == conditionalBuilderKey);
    if (cbIdx >= 0 && cbIdx < bodyCol.children.length - 1) {
      bodyCol.children.insert(cbIdx + 1, text);
    } else {
      bodyCol.children.add(text);
    }
  }

  _addBelowCB(
    pageName:              'WedstrijdenPage',
    scaffoldKey:           'Scaffold_xjabl8lh',
    bodyColKey:            'Column_1mwzov65',
    conditionalBuilderKey: 'ConditionalBuilder_f1ph1tgg',
    stateFieldName:        'matches',
    structName:            'FootMatch',
    placeholderName:       'WedstrijdenNietGepland',
  );
  _addBelowCB(
    pageName:              'BardienPage',
    scaffoldKey:           'Scaffold_ljui3hun',
    bodyColKey:            'Column_mkqeztja',
    conditionalBuilderKey: 'ConditionalBuilder_fwgqn2js',
    stateFieldName:        'duties',
    structName:            'BarDuty',
    placeholderName:       'BardienNietGepland',
  );

  // RijschemaPage's body IS de ConditionalBuilder; voeg placeholder direct
  // toe als tweede child in de Scaffold body via een wrapping column.
  {
    const pageName = 'RijschemaPage';
    final pageWc = findPage(project, name: pageName);
    if (pageWc != null) {
      final visVar = _emptyListVar(pageName, 'Scaffold_g8lilfvp', 'driveMatches', 'FootMatch');

      final existing = findDescendants(pageWc.node,
          (n) => n.name == 'RijschemaNietGepland').firstOrNull;
      if (existing != null) {
        setConditionalVisibility(existing, variable: visVar);
      } else {
        final text = UI.text('Niets gepland',
            name: 'RijschemaNietGepland', style: UITextStyle.bodyMedium);
        final textCopy = text.props.text.deepCopy();
        textCopy.colorValue = FFColorValue(
            inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT));
        text.props.text = textCopy;
        text.props.padding = (UI.container(
            padding: UIEdgeInsets.symmetric(horizontal: 24, vertical: 16)))
            .props.padding.deepCopy();
        setConditionalVisibility(text, variable: visVar);

        final bodyChild = getPropertyChild(pageWc.node, 'body');
        if (bodyChild != null && bodyChild.key == 'ConditionalBuilder_ko9hyhog') {
          UI.expanded(bodyChild);
          final newBodyCol = UI.column(
            name: 'RijschemaBodyCol',
            mainAxisMin: false,
            children: [bodyChild, text],
          );
          pageWc.node.children.remove(bodyChild);
          pageWc.node.children.add(newBodyCol);
          pageWc.node.childPropertyMap['body'] = FFChildrenKeys(
            keyRefs: [FFNodeKeyReference(key: newBodyCol.key)],
          );
        }
      }

      // Belangrijk: zorg ALTIJD dat de ConditionalBuilder Expanded is binnen
      // de wrapping RijschemaBodyCol. Zonder Expanded krijgt de ListView
      // geen hoogte en blijft de pagina visueel leeg.
      final cb = findByKey(pageWc.node, 'ConditionalBuilder_ko9hyhog');
      final parent = cb != null ? findParentByKey(pageWc.node, cb.key) : null;
      if (cb != null && parent != null && parent.parent.key.startsWith('Column_')) {
        if (!cb.props.hasExpanded()) UI.expanded(cb);
      }
    }
  }
}

// Vult Column_cx7sodso (cardColumn) van RijschemaPage met de basis-rijen
// (opponent + datum) als die leeg is. Vereist door _wireRijschemaCardDriverRow
// dat `insert(2, ...)` doet en dus minimaal 2 kinderen verwacht.
void _ensureRijschemaCardBaseRows(FFProject project) {
  final wc = findPage(project, name: 'RijschemaPage');
  if (wc == null) return;
  final cardColumn = findByKey(wc.node, 'Column_cx7sodso');
  if (cardColumn == null) return;
  if (cardColumn.children.length >= 2) return; // al gevuld

  final opponentText = UI.text('-', name: 'Text', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(
    variable: generatorVarField('ListView_55kreos3', 'opponent'),
  );
  final opponentRow = UI.row(
    name: 'Row',
    spacing: 8,
    children: [
      UI.icon('sports_soccer', size: 14, color: UIColor.secondaryText),
      opponentText,
    ],
  );

  final dateText = UI.text('-', name: 'Text', style: UITextStyle.bodySmall);
  dateText.props.text.textValue = FFStringValue(
    variable: generatorVarField('ListView_55kreos3', 'matchDatetime'),
  );
  final dateRow = UI.row(
    name: 'Row',
    spacing: 8,
    children: [
      UI.icon('calendar_today', size: 14, color: UIColor.secondaryText),
      dateText,
    ],
  );

  // Behoud bestaande kinderen, vul aan tot er minimaal 2 zijn.
  while (cardColumn.children.length < 2) {
    cardColumn.children.add(
      cardColumn.children.isEmpty ? opponentRow : dateRow,
    );
  }
}

// ─── Herstel RijschemaPage body als die leeg is ───────────────────────────────
//
// Eerdere opruim-acties hebben mogelijk de body van de RijschemaPage volledig
// verwijderd (alleen AppBar blijft over). Deze functie detecteert dat en
// bouwt de body opnieuw op met de originele widget-keys zodat alle bestaande
// wire-functies (navigatie, cardrows, onLoad) blijven werken.
void _restoreRijschemaBodyIfMissing(FFProject project) {
  final wc = findPage(project, name: 'RijschemaPage');
  if (wc == null) return;

  // Skip als de ConditionalBuilder al bestaat — body is intact.
  if (findByKey(wc.node, 'ConditionalBuilder_ko9hyhog') != null) return;

  // State velden ophalen
  final driveMatchesId = _findPageStateFieldId(project, 'RijschemaPage', 'driveMatches');
  final isLoadingId    = _findPageStateFieldId(project, 'RijschemaPage', 'isLoading');
  if (driveMatchesId == null || isLoadingId == null) return;

  final driveMatchesVar = varFromPageState(driveMatchesId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final isLoadingVar = varFromPageState(isLoadingId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final isNotLoadingVar = varFromPageState(isLoadingId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)
    ..operations.add(FFVariableOperation(negate: FFNegateBoolean()));

  // Card column waar _wireRijschemaCardDriverRow rows aan toevoegt.
  // De originele FlutterFlow-pagina had hier 2 basis-rijen (opponent + datum)
  // die nodig zijn omdat _wireRijschemaCardDriverRow `insert(2, ...)` doet.
  final opponentText = UI.text('-', name: 'Text', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(
    variable: generatorVarField('ListView_55kreos3', 'opponent'),
  );
  final opponentRow = UI.row(
    name: 'Row',
    spacing: 8,
    children: [
      UI.icon('sports_soccer', size: 14, color: UIColor.secondaryText),
      opponentText,
    ],
  );

  final dateText = UI.text('-', name: 'Text', style: UITextStyle.bodySmall);
  dateText.props.text.textValue = FFStringValue(
    variable: generatorVarField('ListView_55kreos3', 'matchDatetime'),
  );
  final dateRow = UI.row(
    name: 'Row',
    spacing: 8,
    children: [
      UI.icon('calendar_today', size: 14, color: UIColor.secondaryText),
      dateText,
    ],
  );

  final cardColumn = UI.column(
    name: 'Column',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 6,
    children: [opponentRow, dateRow],
  );
  cardColumn.key = 'Column_cx7sodso';

  // Card container (tappable)
  final cardContainer = UI.container(
    name: 'Container',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: cardColumn,
  );
  cardContainer.key = 'Container_od2z9b8b';

  // ListView gebonden aan driveMatches
  final listView = UI.listView(
    name: 'ListView',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 8),
    spacing: 8,
    dynamicSource: DynamicSource(variable: driveMatchesVar, itemName: 'driveMatch'),
  );
  listView.key = 'ListView_55kreos3';
  listView.children.add(cardContainer);

  // ListViewWrapper (Expanded zodat ListView ruimte krijgt)
  final wrapper = UI.container(name: 'ListViewWrapper', child: listView);
  wrapper.key = 'Container_aznkrhkc';
  UI.expanded(wrapper);
  setConditionalVisibility(wrapper, variable: isNotLoadingVar);

  // Loading column met spinner
  final spinner = UI.progressBar(name: 'ProgressBar',
      shape: UIProgressShape.circular, width: 40, thickness: 4);
  spinner.key = 'ProgressBar_swmm4iru';
  final loadingCol = UI.column(name: 'Column',
      mainAxisAlignment: UIMainAxisAlignment.center, children: [spinner]);
  loadingCol.key = 'Column_r8o5hqpo';
  setConditionalVisibility(loadingCol, variable: isLoadingVar);

  // ConditionalBuilder (loading vs content)
  final conditionalBuilder = FFNode(
    key: 'ConditionalBuilder_ko9hyhog',
    type: FFWidgetType.ConditionalBuilder,
    name: 'ConditionalBuilder',
    props: FFWidgetProperties(conditionalBuilder: FFConditionalBuilder()),
    children: [loadingCol, wrapper],
  );

  // Verwijder bestaande body-children en plaats nieuwe body
  final existingBodyKeys = wc.node.childPropertyMap['body']?.keyRefs
      .map((r) => r.key).toSet() ?? <String>{};
  wc.node.children.removeWhere((n) => existingBodyKeys.contains(n.key));

  wc.node.children.add(conditionalBuilder);
  wc.node.childPropertyMap['body'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: conditionalBuilder.key)],
  );
}

// Appends a GetDriveSchedule API call to the DashboardPage ON_INIT_STATE chain.
// Must be called AFTER _wireDashboardLoad (which rebuilds the chain from scratch)
// so it always lands at the tail: matches → duties → driveSchedule.
void _wireDashboardDriveScheduleLoad(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  final authTokenId = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'authToken', orElse: () => null)
      ?.parameter.identifier;
  if (authTokenId == null) return;

  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetDriveSchedule',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
      },
      outputVariableName: 'dashDrive',
      nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(
          project,
          widgetClassName: 'DashboardPage',
          updates: [StateFieldUpdate.setFromVariable('driveMatches', ctx.responseVar)],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.snackBar('Kon rijschema niet laden.'),
      ]),
    ),
  );
}

// Rebinds GroupChipName to use accessDocumentField with only the field name
// (no schema key). Some FlutterFlow runtime environments can't resolve field
// values via schema key; a name-only fieldIdentifier falls back to a direct
// Firestore field path lookup and works across all environments.
void _fixGroupChipNameBinding(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final groupList = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsList').firstOrNull;
  if (groupList == null) return;

  final nameNode = findDescendants(wc.node, (n) => n.name == 'GroupChipName').firstOrNull;
  if (nameNode == null) return;

  // Use name-only fieldIdentifier (no key) so test mode can resolve it.
  final nameVar = varFromGeneratorVariable(groupList.key)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(name: 'name'),
      ),
    ));
  final textProto = nameNode.props.text.deepCopy();
  textProto.textValue = FFStringValue(variable: nameVar);
  nameNode.props.text = textProto;
}

// Patch ChatsPage ListViews to shrinkWrap: true so they render inside the
// min-size Column body without an unbounded height constraint.
void _fixChatsPageListViewShrinkWrap(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  for (final name in ['ChatsGroupsList', 'ChatsConversationsList', 'ChatsStaffGroupsList']) {
    final node = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
    if (node == null) continue;
    if (node.props.listView.shrinkWrapValue.inputValue) continue;
    final lvCopy = node.props.listView.deepCopy();
    lvCopy.shrinkWrapValue = FFBooleanValue(inputValue: true);
    node.props.listView = lvCopy;
  }
}

void _forceDashboardNavBarItem(FFProject project) {
  final page = findPage(project, name: 'DashboardPage');
  if (page == null) return;
  final scaffCopy = page.node.props.scaffold.deepCopy();
  final navBarItem = scaffCopy.ensureNavBarItem();
  navBarItem.show = true;
  navBarItem.navIcon = FFIcon(
    iconDataValue: FFIconDataValue(
      inputValue: FFIconData(name: 'home', family: 'MaterialIcons'),
    ),
  );
  page.node.props.scaffold = scaffCopy;
}

// Adds memberNames (list of strings) to the chatGroups collection. Idempotent.
void _addMemberNamesFieldToChatGroups(FFProject project) {
  final coll = findCollection(project, name: 'chatGroups');
  if (coll == null) return;
  if (coll.fields.values.any((f) => f.identifier.name == 'memberNames')) return;
  addCollectionField(
    project,
    collectionName: 'chatGroups',
    fieldName: 'memberNames',
    type: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );
}

// Adds selectedMemberNames state field to CreateGroupPage. Idempotent.
void _ensureCreateGroupSelectedMemberNames(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;
  final exists = wc.classModel.stateFields.any((f) => f.parameter.identifier.name == 'selectedMemberNames');
  if (exists) return;
  addStateField(
    project,
    widgetClassName: 'CreateGroupPage',
    fieldName: 'selectedMemberNames',
    type: FFDataTypeV2(listType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );
}

// Replaces the "+" AddMemberButton with checkbox-style icons.
// Checked icon visible when member ID is in selectedMemberIds.
// Unchecked icon visible otherwise.
// Tapping checked → removes from both selectedMemberIds and selectedMemberNames.
// Tapping unchecked → adds to both.
// Idempotent: skips if CreateGroupMemberCheckboxChecked already present.
void _upgradeCreateGroupCheckboxes(FFProject project) {
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) return;

  // Skip if already upgraded.
  if (findDescendants(wc.node, (n) => n.name == 'CreateGroupMemberCheckboxChecked').isNotEmpty) return;

  final selectedMemberIdsId   = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberIds');
  final selectedMemberNamesId = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberNames');
  if (selectedMemberIdsId == null) return;

  // Find CreateGroupMemberList ListView.
  final memberList = findDescendants(wc.node, (n) => n.name == 'CreateGroupMemberList').firstOrNull;
  if (memberList == null) return;

  // Find AddMemberButton inside the list item.
  final addBtn = findDescendants(memberList, (n) => n.name == 'AddMemberButton').firstOrNull;
  if (addBtn == null) return;

  // Find parent row.
  final rowResult = findParentByKey(memberList, addBtn.key);
  if (rowResult == null) return;
  final memberRow = rowResult.parent;

  // Build member ID variable from the generator.
  final swapMemberIdFieldId = _findStructFieldId(project, 'SwapMember', 'id');
  final memberIdVar = swapMemberIdFieldId != null
      ? (varFromGeneratorVariable(memberList.key)
          ..operations.add(FFVariableOperation(
            accessDataStructField: FFAccessDataStructField(
              fieldIdentifier: swapMemberIdFieldId.deepCopy(),
            ),
          )))
      : generatorVarField(memberList.key, 'id');

  final swapMemberNameFieldId = _findStructFieldId(project, 'SwapMember', 'name');
  final memberNameVar = swapMemberNameFieldId != null
      ? (varFromGeneratorVariable(memberList.key)
          ..operations.add(FFVariableOperation(
            accessDataStructField: FFAccessDataStructField(
              fieldIdentifier: swapMemberNameFieldId.deepCopy(),
            ),
          )))
      : generatorVarField(memberList.key, 'name');

  // selectedMemberIds.contains(memberId) → isSelected boolean variable.
  final selectedIdsVar = varFromPageState(selectedMemberIdsId.deepCopy());
  selectedIdsVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final isSelectedVar = selectedIdsVar.deepCopy()
    ..operations.add(FFVariableOperation(listContains: FFListContains(element: memberIdVar)));
  final isNotSelectedVar = isSelectedVar.deepCopy()
    ..operations.add(FFVariableOperation(negate: FFNegateBoolean()));

  // Checked icon: visible when isSelected, tap → remove from lists.
  final checkedIcon = UI.icon('check_box', size: 24, color: UIColor.primary, name: 'CreateGroupMemberCheckboxChecked');
  setConditionalVisibility(checkedIcon, variable: isSelectedVar);

  final removeUpdates = <StateFieldUpdate>[
    StateFieldUpdate.removeFromListFromVariable('selectedMemberIds', memberIdVar),
  ];
  if (selectedMemberNamesId != null) {
    removeUpdates.add(StateFieldUpdate.removeFromListFromVariable('selectedMemberNames', memberNameVar));
  }
  Actions.addTriggerChain(
    checkedIcon,
    FFActionTriggerType.ON_TAP,
    Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'CreateGroupPage', updates: removeUpdates),
    ]),
  );

  // Unchecked icon: visible when !isSelected, tap → add to lists.
  final uncheckedIcon = UI.icon('check_box_outline_blank', size: 24, color: UIColor.secondaryText, name: 'CreateGroupMemberCheckboxUnchecked');
  setConditionalVisibility(uncheckedIcon, variable: isNotSelectedVar);

  final addUpdates = <StateFieldUpdate>[
    StateFieldUpdate.addToListFromVariable('selectedMemberIds', memberIdVar),
  ];
  if (selectedMemberNamesId != null) {
    addUpdates.add(StateFieldUpdate.addToListFromVariable('selectedMemberNames', memberNameVar));
  }
  Actions.addTriggerChain(
    uncheckedIcon,
    FFActionTriggerType.ON_TAP,
    Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'CreateGroupPage', updates: addUpdates),
    ]),
  );

  // Replace AddMemberButton with the two checkbox icons.
  final btnIdx = memberRow.children.indexWhere((c) => c.key == addBtn.key);
  if (btnIdx >= 0) {
    memberRow.children.removeAt(btnIdx);
    memberRow.children.insert(btnIdx, uncheckedIcon);
    memberRow.children.insert(btnIdx, checkedIcon);
  }
}

// Creates GroupMembersPage showing the member names of a chatGroup.
// Idempotent: skips if the page already exists.
void _buildGroupMembersPageRaw(FFProject project) {
  if (findPage(project, name: 'GroupMembersPage') != null) return;

  final groupMemberNamesId = _findAppStateFieldId(project, 'groupMemberNames');

  // Content nodes.
  final nameTxt = UI.text('', name: 'GroupMemberNameText', style: UITextStyle.bodyMedium);
  final memberNameList = UI.listView(name: 'GroupMemberNameList', spacing: 4, shrinkWrap: true);
  final memberCard = UI.container(
    name: 'GroupMemberCard',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 10),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: nameTxt,
  );
  memberNameList.children.add(memberCard);

  final scrollCol = UI.column(
    name: 'GroupMembersBodyCol',
    padding: UIEdgeInsets.all(16),
    spacing: 8,
  );
  scrollCol.children.add(memberNameList);

  // Create the page — addPage wraps the body column in a Scaffold.
  addPage(
    project,
    name: 'GroupMembersPage',
    route: 'group-members',
    description: 'Shows the members of a chat group.',
    body: scrollCol,
    params: {
      'groupId':   FFDataTypeV2(scalarType: FFBaseDataType.String),
      'groupName': FFDataTypeV2(scalarType: FFBaseDataType.String),
    },
  );

  final wc = findPage(project, name: 'GroupMembersPage');
  if (wc == null) return;
  final scaffold = wc.node;

  // Add AppBar.
  final titleNode = UI.text('Leden', name: 'GroupMembersTitle', style: UITextStyle.titleLarge);
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  scaffold.children.add(appBarNode);
  scaffold.childPropertyMap['appBar'] = FFChildrenKeys(keyRefs: [FFNodeKeyReference(key: appBarNode.key)]);

  // Wire ListView source to AppState.groupMemberNames.
  if (groupMemberNamesId != null) {
    final sourceVar = varFromAppState(groupMemberNamesId.deepCopy());
    final listNode = findDescendants(scaffold, (n) => n.name == 'GroupMemberNameList').firstOrNull;
    if (listNode != null) {
      listNode.generatorVariable = DynamicSource(
        variable: sourceVar,
        itemName: 'groupMember',
      ).toGeneratorVariable(listNode.key);
      final txtNode = findDescendants(scaffold, (n) => n.name == 'GroupMemberNameText').firstOrNull;
      if (txtNode != null) {
        txtNode.props.text.textValue = FFStringValue(
          variable: varFromGeneratorVariable(listNode.key),
        );
      }
    }
  }

  // Wire ON_INIT_STATE: set AppState.pendingGroupName = groupId param → call LoadGroupMemberNames.
  final loadGroupMembersCA = findCustomAction(project, name: 'LoadGroupMemberNames');
  final pendingGroupNameId = _findAppStateFieldId(project, 'pendingGroupName');

  if (loadGroupMembersCA != null && pendingGroupNameId != null) {
    FFIdentifier? groupIdParamId;
    for (final p in wc.params.values) {
      if (p.hasIdentifier() && p.identifier.name == 'groupId') {
        groupIdParamId = p.identifier.deepCopy();
        break;
      }
    }
    if (groupIdParamId != null) {
      final loadChain = FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          localStateUpdate: FFLocalStateUpdate(
            updates: [
              FFLocalStateFieldUpdate(
                fieldIdentifier: pendingGroupNameId.deepCopy(),
                setValue: FFValue(variable: varFromPageParam(groupIdParamId)),
              ),
            ],
            stateVariableType: FFStateVariableType.APP_STATE,
          ),
        ),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: FFAction(
            key: generateRandomAlphaNumericString(),
            customAction: FFCustomActionCall(
              customActionIdentifier: loadGroupMembersCA.identifier.deepCopy(),
            ),
          ),
        ),
      );
      scaffold.triggerActions.add(FFTriggerActions(
        trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_INIT_STATE),
        rootAction: loadChain,
      ));
    }
  }
}

// ─── TeamMembersPage ──────────────────────────────────────────────────────────

// AppState field `sharedTeamMembers` = List<DataStruct<SwapMember>>. Wordt door
// TeamMembersPage onInit gevuld; TeamMembersPage ListView binds direct hieraan.
void _ensureSharedTeamMembersAppStateField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'sharedTeamMembers',
  )) return;
  final memberStruct = findDataStruct(project, name: 'SwapMember');
  if (memberStruct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(
      name: 'sharedTeamMembers',
      key: generateRandomAlphaNumericString(),
    ),
    dataType: dataStructType(memberStruct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// Creates TeamMembersPage: shows all team members with name + "Nog niet online"
// indicator. Loaded via GetTeamMembers → AppState.sharedTeamMembers.
// Reached vanuit ChatDetailPage AppBar (tap op subtitle → naar deze page).
void _buildTeamMembersPage(FFProject project) {
  _ensureSharedTeamMembersAppStateField(project);
  if (findPage(project, name: 'TeamMembersPage') != null) return;

  final sharedId = _findAppStateFieldId(project, 'sharedTeamMembers');
  if (sharedId == null) return;

  // Content nodes.
  final nameTxt = UI.text('', name: 'TeamMemberNameText', style: UITextStyle.bodyMedium);
  final offlineLabel = UI.text(
    'Nog niet online',
    name: 'TeamMemberOfflineLabel',
    style: UITextStyle.labelSmall,
  );
  final offlineCopy = offlineLabel.props.text.deepCopy();
  offlineCopy.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
  );
  offlineCopy.italicValue = FFBooleanValue(inputValue: true);
  offlineLabel.props.text = offlineCopy;

  final col = UI.column(name: 'TeamMemberCol', spacing: 2, children: [nameTxt, offlineLabel]);
  final memberCard = UI.container(
    name: 'TeamMemberCard',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 10),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: col,
  );
  final memberList = UI.listView(
    name: 'TeamMemberList',
    spacing: 6,
    shrinkWrap: true,
  );
  memberList.children.add(memberCard);

  final scrollCol = UI.column(
    name: 'TeamMembersBodyCol',
    padding: UIEdgeInsets.all(16),
    spacing: 8,
  );
  scrollCol.children.add(memberList);

  addPage(
    project,
    name: 'TeamMembersPage',
    route: 'team-members',
    description: 'Toont de leden van het huidige team.',
    body: scrollCol,
  );

  final wc = findPage(project, name: 'TeamMembersPage');
  if (wc == null) return;
  final scaffold = wc.node;

  // AppBar.
  final titleNode = UI.text('Teamleden', name: 'TeamMembersTitle', style: UITextStyle.titleLarge);
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  scaffold.children.add(appBarNode);
  scaffold.childPropertyMap['appBar'] = FFChildrenKeys(keyRefs: [FFNodeKeyReference(key: appBarNode.key)]);

  // Wire ListView source naar AppState.sharedTeamMembers.
  // varFromAppState gebruikt FFVariableSource.LOCAL_STATE; ListView-generators
  // eisen nodeKeyRef naar het scaffold OOK voor AppState-bronnen, anders breekt codegen.
  final listNode = findDescendants(scaffold, (n) => n.name == 'TeamMemberList').firstOrNull;
  if (listNode != null) {
    final sourceVar = varFromAppState(sharedId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffold.key);
    listNode.generatorVariable = DynamicSource(
      variable: sourceVar,
      itemName: 'teamMember',
    ).toGeneratorVariable(listNode.key);
    final txtNode = findDescendants(scaffold, (n) => n.name == 'TeamMemberNameText').firstOrNull;
    if (txtNode != null) {
      txtNode.props.text.textValue = FFStringValue(
        variable: generatorVarField(listNode.key, 'name'),
      );
    }
    final offlineNode = findDescendants(scaffold, (n) => n.name == 'TeamMemberOfflineLabel').firstOrNull;
    if (offlineNode != null) {
      final noAppVar = conditionVar(
        generatorVarField(listNode.key, 'hasAppAccount'),
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
      ).variable;
      setConditionalVisibility(offlineNode, variable: noAppVar);
    }
  }

  // ON_INIT_STATE: GetTeamMembers(currentTeamId) → AppState.sharedTeamMembers.
  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

  final filterAction = findCustomAction(project, name: 'FilterChatMembersByConv');

  _appendToFirstPageLoadChain(
    scaffold,
    Actions.apiCallNode(
      project,
      endpointName: 'GetTeamMembers',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'teamId': varFromAppState(currentTeamIdFieldId.deepCopy()),
      },
      outputVariableName: 'teamMembersResp',
      nodeKey: scaffold.key,
      onSuccess: (ctx) {
        final actions = <FFAction>[
          Actions.updateAppState(
            project,
            updates: [StateFieldUpdate.setFromVariable('sharedTeamMembers', ctx.responseVar)],
          ),
        ];
        if (filterAction != null) {
          actions.add(Actions.callCustomAction(
            identifier: filterAction.identifier.deepCopy(),
          ));
        }
        return Actions.chain(actions);
      },
    ),
  );
}

// Force-reset ChatDetailPage AppBar naar simpele text-title (titel param).
// Wist eerder geïnjecteerde Column-with-subtitle die de build brak.
void _resetChatDetailAppBarSimple(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;
  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');

  FFIdentifier? titleParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'title') {
      titleParamId = param.identifier.deepCopy();
      break;
    }
  }
  final titleNode = UI.text('Chat', name: 'AppBar Title', style: UITextStyle.titleLarge);
  if (titleParamId != null) {
    titleNode.props.text.textValue = FFStringValue(variable: varFromPageParam(titleParamId));
  }
  final appBarNode = UI.appBar(titleWidget: titleNode, showBackButton: true);
  wc.node.children.add(appBarNode);
  wc.node.childPropertyMap['appBar'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: appBarNode.key)],
  );
}

// Voegt een tappable subtitle "Toon leden" onder de title toe in de
// ChatDetailPage AppBar. Tap navigeert naar TeamMembersPage.
void _addTeamMembersSubtitleToChatDetail(FFProject project) {
  _addTeamMembersSubtitleToChatPage(
    project,
    pageName: 'ChatDetailPage',
    subtitleName: 'ChatDetailMembersSubtitle',
    titleName: 'ChatDetailTitleText',
    colName: 'ChatDetailAppBarTitleCol',
    titleParamName: 'title',
  );
  // TeamChatPage gebruikt geen page-param maar AppState.currentTeamName.
  _addTeamMembersSubtitleToTeamChat(project);
  // Verwijder verouderde MemberStripList op TeamChatPage — Toon leden vervangt 'm.
  _removeTeamChatMemberStrip(project);
}

void _addTeamMembersSubtitleToTeamChat(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'TeamChatMembersSubtitle').isNotEmpty) return;

  final appBarNode = getPropertyChild(wc.node, 'appBar');
  if (appBarNode == null) return;
  final titleNode = getPropertyChild(appBarNode, 'title');
  if (titleNode == null) return;

  final currentTeamNameId = _findAppStateFieldId(project, 'currentTeamName');

  final newTitle = UI.text('Teamchat', name: 'TeamChatTitleText', style: UITextStyle.titleLarge);
  if (currentTeamNameId != null) {
    newTitle.props.text.textValue =
        FFStringValue(variable: varFromAppState(currentTeamNameId.deepCopy()));
  }

  final subtitle = UI.text('Toon leden', name: 'TeamChatMembersSubtitle', style: UITextStyle.labelSmall);
  final subCopy = subtitle.props.text.deepCopy();
  subCopy.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND),
  );
  subtitle.props.text = subCopy;

  final col = UI.column(
    name: 'TeamChatAppBarTitleCol',
    mainAxisAlignment: UIMainAxisAlignment.center,
    crossAxisAlignment: UICrossAxisAlignment.start,
    children: [newTitle, subtitle],
  );

  Actions.onTapChain(
    col,
    Actions.chain([
      Actions.navigate(project, pageName: 'TeamMembersPage'),
    ]),
  );

  removeByKey(appBarNode, titleNode.key);
  appBarNode.children.add(col);
  appBarNode.childPropertyMap['title'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: col.key)],
  );
}

void _removeTeamChatMemberStrip(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;
  // Verwijder de hele wrapper container — meegenomen: MemberStripList, alle chips.
  for (final n in findDescendants(wc.node, (n) => n.name == 'DirectChatMemberStrip')) {
    removeByKey(wc.node, n.key);
  }
}

void _addTeamMembersSubtitleToChatPage(
  FFProject project, {
  required String pageName,
  required String subtitleName,
  required String titleName,
  required String colName,
  required String titleParamName,
}) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;

  final appBarNode = getPropertyChild(wc.node, 'appBar');
  if (appBarNode == null) return;
  final titleNode = getPropertyChild(appBarNode, 'title');
  if (titleNode == null) return;

  // Niet idempotent skippen — wist eerder geïnjecteerde Col-with-subtitle uit
  // zodat de visibility-binding altijd vers wordt geapplied. Daarna bouwen
  // we de title fresh op.
  final existingCol = findDescendants(appBarNode, (n) => n.name == colName).firstOrNull;
  if (existingCol != null) {
    removeByKey(appBarNode, existingCol.key);
    appBarNode.childPropertyMap.remove('title');
  }

  FFIdentifier? titleParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == titleParamName) {
      titleParamId = param.identifier.deepCopy();
      break;
    }
  }
  if (titleParamId == null) return;

  final newTitle = UI.text('', name: titleName, style: UITextStyle.titleLarge);
  newTitle.props.text.textValue = FFStringValue(variable: varFromPageParam(titleParamId));

  final subtitle = UI.text('Toon leden', name: subtitleName, style: UITextStyle.labelSmall);
  final subCopy = subtitle.props.text.deepCopy();
  subCopy.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND),
  );
  subtitle.props.text = subCopy;

  // Verberg subtitle op direct chats (convId zonder team_/staffgroup_/group_ prefix
  // = 1-op-1 paar, geen ledenlijst nodig).
  FFIdentifier? convIdParamId;
  for (final p in wc.params.values) {
    if (p.hasIdentifier() && p.identifier.name == 'conversationId') {
      convIdParamId = p.identifier.deepCopy();
      break;
    }
  }
  if (convIdParamId != null) {
    final showVar = codeExpressionVar(
      expression: r'(convId ?? "").startsWith("team_") || (convId ?? "").startsWith("staffgroup_") || (convId ?? "").startsWith("group_")',
      arguments: [
        CodeExpressionArg(
          name: 'convId',
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: varFromPageParam(convIdParamId)),
        ),
      ],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
    );
    setConditionalVisibility(subtitle, variable: showVar);
  }

  final col = UI.column(
    name: colName,
    mainAxisAlignment: UIMainAxisAlignment.center,
    crossAxisAlignment: UICrossAxisAlignment.start,
    children: [newTitle, subtitle],
  );

  Actions.onTapChain(
    col,
    Actions.chain([
      Actions.navigate(project, pageName: 'TeamMembersPage'),
    ]),
  );

  removeByKey(appBarNode, titleNode.key);
  appBarNode.children.add(col);
  appBarNode.childPropertyMap['title'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: col.key)],
  );
}

// ─── Chat send refresh ────────────────────────────────────────────────────────

// After SendMessage, re-queries chatMessages so the sender's own message
// appears immediately without leaving and re-entering the screen.
// The conversationId filter is applied afterwards by _wireChatDetailFilters.
// Idempotent: guarded by output variable name 'refreshedMessages'.
void _fixChatSendRefresh(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;

  final chatMsgColl = findCollection(project, name: 'chatMessages');
  if (chatMsgColl == null) return;

  final chatMsgStateId = _findPageStateFieldId(project, 'ChatDetailPage', 'chatMessages');
  if (chatMsgStateId == null) return;

  // Find the send button by key.
  final sendBtn = findByKey(wc.node, 'IconButton_nnsnoc98');
  if (sendBtn == null) return;

  // Find the ON_TAP trigger.
  final tapTriggerIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapTriggerIdx < 0) return;
  final tapTrigger = sendBtn.triggerActions[tapTriggerIdx];
  if (!tapTrigger.hasRootAction()) return;

  // Idempotent: only skip if BOTH re-query AND scroll-to-bottom are already wired.
  bool hasRefresh(FFActionNode n) {
    if (n.hasAction() && n.action.outputVariableName == 'refreshedMessages') return true;
    if (n.hasFollowUpAction() && hasRefresh(n.followUpAction)) return true;
    if (n.hasConditionActions()) {
      for (final ta in n.conditionActions.trueActions) {
        if (ta.hasTrueAction() && hasRefresh(ta.trueAction)) return true;
      }
    }
    return false;
  }
  bool hasScroll(FFActionNode n) {
    if (n.hasAction() && n.action.hasScrollToPercentage() &&
        n.action.scrollToPercentage.scrollableNodeKeyRef.key == 'ListView_ws05qhut') return true;
    if (n.hasFollowUpAction() && hasScroll(n.followUpAction)) return true;
    if (n.hasConditionActions()) {
      for (final ta in n.conditionActions.trueActions) {
        if (ta.hasTrueAction() && hasScroll(ta.trueAction)) return true;
      }
    }
    return false;
  }
  if (hasRefresh(tapTrigger.rootAction) && hasScroll(tapTrigger.rootAction)) return;

  // Walk into condition's trueAction chain to find the tail node.
  // Chain: rootAction(condition) → trueAction: SendMessage → SetState.clear → [tail here]
  final root = tapTrigger.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueActionEntry = root.conditionActions.trueActions.first;
  if (!trueActionEntry.hasTrueAction()) return;

  FFActionNode tail = trueActionEntry.trueAction;
  while (tail.hasFollowUpAction()) tail = tail.followUpAction;

  // Scroll-to-bottom node — appended after SetState(chatMessages).
  const _kChatListKey = 'ListView_ws05qhut';
  final scrollNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: Actions.scrollTo(widgetKey: _kChatListKey, percentage: 1.0, durationMillis: 150),
  );

  // If the re-query was already wired (from a previous push), just append scroll to its tail.
  if (hasRefresh(tapTrigger.rootAction)) {
    FFActionNode refreshTail = trueActionEntry.trueAction;
    while (refreshTail.hasFollowUpAction()) refreshTail = refreshTail.followUpAction;
    refreshTail.followUpAction = scrollNode;
    return;
  }

  // Build re-query node.
  final queryNodeKey = generateRandomAlphaNumericString();
  final queryAction = Actions.firestoreQuery(
    collectionIdentifier: chatMsgColl.identifier.deepCopy(),
    limit: 100,
    singleTimeQuery: true,
  );
  queryAction.outputVariableName = 'refreshedMessages';

  // Build SetState node that updates chatMessages from the query result.
  // actionKey must be the FFAction key (not the FFActionNode key).
  // nodeKeyRef points to the trigger node (send button) as the compiler does.
  final refreshedVar = varFromActionOutput(
    actionKey: queryAction.key,
    outputName: 'refreshedMessages',
  )..nodeKeyRef = FFNodeKeyReference(key: sendBtn.key);
  final setStateNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: Actions.updatePageState(
      project,
      widgetClassName: 'ChatDetailPage',
      updates: [StateFieldUpdate.setFromVariable('chatMessages', refreshedVar)],
    ),
    followUpAction: scrollNode,
  );

  // Small wait before re-query so Firestore server-confirmed write is visible.
  final waitNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: Actions.wait(300),
    followUpAction: FFActionNode(
      key: queryNodeKey,
      action: queryAction,
      followUpAction: setStateNode,
    ),
  );

  // Append the wait → re-query → setState → scroll chain to the tail.
  tail.followUpAction = waitNode;
}

// ─── GroupChatPage fixes ──────────────────────────────────────────────────────
// Fixes three issues in the GroupChatPage that was built in an older push:
// 1. Bubble visibility: changes authToken → userEmail for "own" vs "other" row.
// 2. senderId in FirestoreCreate: changes authToken → userEmail.
// 3. Send refresh: inserts wait(300) before the singleTimeQuery re-query.
// All three are idempotent.
void _fixGroupChatPage(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;

  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;

  // ── Fix send button: senderId + refresh wait ─────────────────────────────
  // Bubble layout and visibility are handled by _rebuildGroupChatBubbles.
  final sendBtn = findByKey(wc.node, 'IconButton_tgwfn8d7');
  if (sendBtn == null) return;

  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;

  final root = tap.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueEntry = root.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  // Fix senderId in FirestoreCreate (first action in chain).
  final createNode = trueEntry.trueAction;
  if (createNode.hasAction() &&
      createNode.action.hasDatabase() &&
      createNode.action.database.hasCreateDocument()) {
    final create = createNode.action.database.createDocument;
    if (create.hasWrite()) {
      const kSenderIdFieldKey = '5nh0jzir';
      final entry = create.write.updates[kSenderIdFieldKey];
      if (entry != null) {
        entry.variable = varFromAppState(userEmailId.deepCopy());
      }
    }
  }

  // Insert wait(300) before the singleTimeQuery refresh (idempotent).
  FFActionNode prev = trueEntry.trueAction;
  while (prev.hasFollowUpAction()) {
    final curr = prev.followUpAction;
    if (curr.hasAction() &&
        curr.action.hasDatabase() &&
        curr.action.database.hasFirestoreQuery() &&
        curr.action.database.firestoreQuery.singleTimeQuery) {
      // Idempotent: if prev has no database/setState/customAction/navigate it's already a wait.
      if (prev.hasAction() &&
          !prev.action.hasDatabase() &&
          !prev.action.hasLocalStateUpdate() &&
          !prev.action.hasCustomAction() &&
          !prev.action.hasNavigate()) {
        return;
      }
      prev.followUpAction = FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.wait(300),
        followUpAction: curr,
      );
      return;
    }
    prev = curr;
  }
}

// Adds formatted timestamps and senderName to GroupChatPage message bubbles to
// match ChatDetailPage layout:
//   - Others' bubble (Column_x195x8zz): append OtherMsgTime (HH:mm, secondaryText)
//   - Own bubble     (Column_qsknd353):  prepend OwnSenderName, insert OwnMsgMeta row
//     (with OwnMsgTime) before OwnActionsRow.
// Idempotent: each part is guarded by a name-based presence check.
void _fixGroupChatBubbles(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;

  const listKey     = 'ListView_6t0r29c1';
  const kSenderName = 'opya0tqd'; // groupMessages.senderName
  const kCreatedAt  = 'a4h76kji'; // groupMessages.createdAt

  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  // ── Others' bubble ──────────────────────────────────────────────────────────
  final otherCol = findByKey(wc.node, 'Column_x195x8zz');
  if (otherCol != null && !otherCol.children.any((n) => n.name == 'OtherMsgTime')) {
    final timeNode = UI.text('', name: 'OtherMsgTime', style: UITextStyle.bodySmall);
    final copy = timeNode.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT));
    timeNode.props.text = copy;
    otherCol.children.add(timeNode);
  }

  // ── Own bubble ──────────────────────────────────────────────────────────────
  final ownCol = findByKey(wc.node, 'Column_qsknd353');
  if (ownCol == null) return;

  if (!ownCol.children.any((n) => n.name == 'OwnSenderName')) {
    final senderNode = UI.text('', name: 'OwnSenderName', style: UITextStyle.labelMedium);
    final copy = senderNode.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.LABEL_MEDIUM;
    copy.textValue  = FFStringValue(variable: _field(kSenderName, 'senderName'));
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND));
    senderNode.props.text = copy;
    ownCol.children.insert(0, senderNode);
  }

  if (!ownCol.children.any((n) => n.name == 'OwnMsgMeta')) {
    final timeNode = UI.text('', name: 'OwnMsgTime', style: UITextStyle.bodySmall);
    final copy = timeNode.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND));
    timeNode.props.text = copy;

    final metaRow = UI.row(
      name: 'OwnMsgMeta',
      mainAxisAlignment: UIMainAxisAlignment.end,
      spacing: 4,
      children: [timeNode],
    );

    final ownActionsIdx = ownCol.children.indexWhere((n) => n.name == 'OwnActionsRow');
    if (ownActionsIdx >= 0) {
      ownCol.children.insert(ownActionsIdx, metaRow);
    } else {
      ownCol.children.add(metaRow);
    }
  }
}

// Nuclear rebuild of GroupChatPage message bubbles with left/right alignment.
// Clears Column_dk9brcp8 (list item column) and replaces with:
//   OtherBubbleRow (visible: senderId != userEmail, mainAxis: start)
//   OwnBubbleRow   (visible: senderId == userEmail, mainAxis: end)
// Non-idempotent by design: always rebuilds so UI.row() rows replace the legacy
// FlutterFlow rows that had minSizeValue=true and ignored mainAxisAlignment.
void _rebuildGroupChatBubbles(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;

  final innerCol = findByKey(wc.node, 'Column_dk9brcp8');
  if (innerCol == null) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;

  const listKey     = 'ListView_6t0r29c1';
  const kSenderName = 'opya0tqd'; // groupMessages.senderName
  const kText       = 'l46qea8h'; // groupMessages.text
  const kCreatedAt  = 'a4h76kji'; // groupMessages.createdAt

  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  FFNode _txt(String nodeName, String fieldKey, String fieldName, {
    FFText_ThemeStyle style = FFText_ThemeStyle.BODY_MEDIUM,
    FFColor_ThemeColor? color,
  }) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodyMedium);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = style;
    copy.textValue  = FFStringValue(variable: _field(fieldKey, fieldName));
    if (color != null) copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  FFNode _timeTxt(String nodeName, {FFColor_ThemeColor color = FFColor_ThemeColor.SECONDARY_TEXT}) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodySmall);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  innerCol.children.clear();

  // ── Others' bubble (left) ──────────────────────────────────────────────────
  final otherMsgCol = UI.column(
    name: 'OtherMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OtherSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY),
      _txt('OtherMsgText', kText, 'text'),
      _timeTxt('OtherMsgTime'),
    ],
  );

  final otherBubble = UI.container(
    name: 'OtherBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: otherMsgCol,
  );

  final otherRow = UI.row(
    name: 'OtherBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [otherBubble],
  );
  setConditionalVisibility(
    otherRow,
    variable: conditionVar(
      _field(kSenderName, 'senderName'),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromAppState(userNameId.deepCopy()),
    ).variable,
  );

  // ── Own bubble (right) ────────────────────────────────────────────────────
  final ownMsgMeta = UI.row(
    name: 'OwnMsgMeta',
    mainAxisAlignment: UIMainAxisAlignment.end,
    spacing: 4,
    children: [_timeTxt('OwnMsgTime', color: FFColor_ThemeColor.PRIMARY_BACKGROUND)],
  );

  final ownMsgCol = UI.column(
    name: 'OwnMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OwnSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      _txt('OwnMsgText', kText, 'text', color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      ownMsgMeta,
    ],
  );

  final ownBubble = UI.container(
    name: 'OwnBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.primary,
    child: ownMsgCol,
  );

  final ownRow = UI.row(
    name: 'OwnBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.end,
    children: [ownBubble],
  );
  setConditionalVisibility(
    ownRow,
    variable: conditionVar(
      _field(kSenderName, 'senderName'),
      FFCondition_Relation.EQUAL_TO,
      varFromAppState(userNameId.deepCopy()),
    ).variable,
  );

  innerCol.children.add(otherRow);
  innerCol.children.add(ownRow);
}

// ─── TeamChatPage bubble fixes ───────────────────────────────────────────────

// Rebuilds TeamChatPage message items with left/right bubble layout matching
// ChatDetailPage / DirectChatPage:
//   - Clears Container_00rk30lc background + padding.
//   - Replaces Column_thoxlcla children with two conditional Rows:
//       OtherBubbleRow (visible: senderName != userName, mainAxis: start)
//         → Container OtherBubble (secondaryBackground, 12px padding+radius)
//           → Column OtherMsgCol: [OtherSenderName (primary), OtherMsgText, OtherMsgTime (HH:mm)]
//       OwnBubbleRow (visible: senderName == userName, mainAxis: end)
//         → Container OwnBubble (primary, 12px padding+radius)
//           → Column OwnMsgCol: [OwnSenderName (primaryBackground), OwnMsgText, OwnMsgMeta row]
// Uses senderName == userName (not senderId) so old messages (authToken senderId) still
// appear in the correct own bubble.
// Idempotent: skips if OtherBubbleRow is already a child of Column_thoxlcla.
void _fixTeamChatBubbles(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final col = findByKey(wc.node, 'Column_thoxlcla');
  if (col == null) return;

  if (col.children.any((n) => n.name == 'OtherBubbleRow')) return;

  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;

  const listKey     = 'ListView_9sebksf4';
  const kSenderName = '766twey2'; // teamChats.senderName
  const kText       = 'c6cfne01'; // teamChats.text
  const kCreatedAt  = 'iyd2epsz'; // teamChats.createdAt

  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  FFNode _txt(String nodeName, String fieldKey, String fieldName, {
    FFText_ThemeStyle style = FFText_ThemeStyle.BODY_MEDIUM,
    FFColor_ThemeColor? color,
  }) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodyMedium);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = style;
    copy.textValue  = FFStringValue(variable: _field(fieldKey, fieldName));
    if (color != null) copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  FFNode _timeTxt(String nodeName, {FFColor_ThemeColor color = FFColor_ThemeColor.SECONDARY_TEXT}) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodySmall);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  // Clear background + padding on outer Container_00rk30lc.
  final outerContainer = findByKey(wc.node, 'Container_00rk30lc');
  if (outerContainer != null) {
    if (outerContainer.props.container.hasBoxDecoration()) {
      outerContainer.props.container.boxDecoration.clearColorValue();
    }
    if (outerContainer.props.hasPadding()) {
      outerContainer.props.clearPadding();
    }
  }

  col.children.clear();

  // ── Others' bubble (left) ──────────────────────────────────────────────────
  final otherMsgCol = UI.column(
    name: 'OtherMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OtherSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY),
      _txt('OtherMsgText', kText, 'text'),
      _timeTxt('OtherMsgTime'),
    ],
  );

  final otherBubble = UI.container(
    name: 'OtherBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: otherMsgCol,
  );

  final otherRow = UI.row(
    name: 'OtherBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [otherBubble],
  );
  setConditionalVisibility(
    otherRow,
    variable: conditionVar(
      _field(kSenderName, 'senderName'),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromAppState(userNameId.deepCopy()),
    ).variable,
  );

  // ── Own bubble (right) ────────────────────────────────────────────────────
  final ownMsgMeta = UI.row(
    name: 'OwnMsgMeta',
    mainAxisAlignment: UIMainAxisAlignment.end,
    spacing: 4,
    children: [_timeTxt('OwnMsgTime', color: FFColor_ThemeColor.PRIMARY_BACKGROUND)],
  );

  final ownMsgCol = UI.column(
    name: 'OwnMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OwnSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM, color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      _txt('OwnMsgText', kText, 'text', color: FFColor_ThemeColor.PRIMARY_BACKGROUND),
      ownMsgMeta,
    ],
  );

  final ownBubble = UI.container(
    name: 'OwnBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.primary,
    child: ownMsgCol,
  );

  final ownRow = UI.row(
    name: 'OwnBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.end,
    children: [ownBubble],
  );
  setConditionalVisibility(
    ownRow,
    variable: conditionVar(
      _field(kSenderName, 'senderName'),
      FFCondition_Relation.EQUAL_TO,
      varFromAppState(userNameId.deepCopy()),
    ).variable,
  );

  col.children.add(otherRow);
  col.children.add(ownRow);
}

// Shows "Beheerder: <createdBy>" strip at the top of GroupChatPage body.
// Adds a 'groupCreatedBy' state field and appends a Firestore query on init
// to fetch the creator's name from chatGroups where name == Param('groupId').
// Idempotent: skips if GroupAdminStrip already present.
void _addGroupChatAdminDisplay(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == 'GroupAdminStrip').isNotEmpty) return;

  // Add 'groupCreatedBy' state field if not present.
  final fieldAlreadyExists = wc.classModel.stateFields.any(
    (f) => f.parameter.identifier.name == 'groupCreatedBy',
  );
  if (!fieldAlreadyExists) {
    final param = FFParameter(
      identifier: FFIdentifier(name: 'groupCreatedBy', key: generateRandomAlphaNumericString()),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    );
    wc.classModel.stateFields.add(FFWidgetClassStateField(parameter: param));
  }

  final bodyCol = getPropertyChild(wc.node, 'body');
  if (bodyCol == null) return;

  final createdByFieldId = _findPageStateFieldId(project, 'GroupChatPage', 'groupCreatedBy');
  if (createdByFieldId == null) return;

  final createdByVar = varFromPageState(createdByFieldId.deepCopy());
  createdByVar.nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final createdByText = UI.text('', name: 'GroupAdminValue', style: UITextStyle.bodySmall);
  createdByText.props.text.textValue = FFStringValue(variable: createdByVar);

  final adminStrip = UI.container(
    name: 'GroupAdminStrip',
    padding: UIEdgeInsets.symmetric(horizontal: 16, vertical: 6),
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'GroupAdminRow',
      spacing: 6,
      children: [
        UI.text('Beheerder:', name: 'GroupAdminLabel', style: UITextStyle.labelSmall,
            color: UIColor.secondaryText),
        createdByText,
      ],
    ),
  );

  bodyCol.children.insert(0, adminStrip);

  // Append Firestore query to init chain to populate groupCreatedBy.
  final chatGroupsColl = findCollection(project, name: 'chatGroups');
  if (chatGroupsColl == null) return;

  FFIdentifier? groupIdParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'groupId') {
      groupIdParamId = param.identifier.deepCopy();
      break;
    }
  }
  if (groupIdParamId == null) return;

  final nameField = findCollectionField(project, collectionName: 'chatGroups', fieldName: 'name');
  final createdByCollField = findCollectionField(project, collectionName: 'chatGroups', fieldName: 'createdBy');
  if (nameField == null || createdByCollField == null) return;

  final queryAction = Actions.firestoreQuery(
    collectionIdentifier: chatGroupsColl.identifier.deepCopy(),
    limit: 1,
    singleTimeQuery: true,
  );
  queryAction.outputVariableName = 'groupInfo';
  queryAction.database.firestoreQuery.where = FFFirestoreWhere(
    isAnd: true,
    filters: [
      FFFirestoreWhere_NestedFilter(
        baseFilter: FFFirestoreFilter(
          collectionFieldIdentifier: nameField.identifier.deepCopy(),
          relation: FFFirestoreFilter_Relation.EQUAL_TO,
          variable: varFromPageParam(groupIdParamId),
        ),
      ),
    ],
  );

  final createdByFromQuery = varFromActionOutput(
    actionKey: queryAction.key,
    outputName: 'groupInfo',
  )
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)
    ..operations.add(FFVariableOperation(
      listItemAtIndex: FFListItemAtIndex(type: FFListItemAtIndex_IndexType.FIRST),
    ))
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: createdByCollField.identifier.deepCopy(),
      ),
    ));

  _appendToFirstPageLoadChain(
    wc.node,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: queryAction,
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.updatePageState(
          project,
          widgetClassName: 'GroupChatPage',
          updates: [StateFieldUpdate.setFromVariable('groupCreatedBy', createdByFromQuery)],
        ),
      ),
    ),
  );
}

// ─── DirectChatPage bubble fixes ─────────────────────────────────────────────

// Rebuilds DirectChatPage's inner message column with left/right bubble layout
// matching ChatDetailPage:
//   - Clears the outer Container_oulzkkz8 background so bubbles control their own color.
//   - Replaces Column_hz6ofrah children with two conditional Rows:
//       OtherBubbleRow (visible: senderId != userEmail, mainAxis: start)
//         → Container OtherBubble (secondaryBackground, 12px padding+radius)
//           → Column OtherMsgCol: [OtherSenderName, OtherMsgText, OtherMsgTime]
//       OwnBubbleRow (visible: senderId == userEmail, mainAxis: end)
//         → Container OwnBubble (primary, 12px padding+radius)
//           → Column OwnMsgCol: [OwnSenderName, OwnMsgText, OwnMsgMeta row]
// Idempotent: skips if OtherBubbleRow already present.
void _fixDirectChatBubbles(FFProject project) {
  final wc = findPage(project, name: 'DirectChatPage');
  if (wc == null) return;

  final innerCol = findByKey(wc.node, 'Column_hz6ofrah');
  if (innerCol == null) return;

  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;

  const listKey     = 'ListView_z624qee2';
  const kSenderId   = 'e252z61j'; // directMessages.senderId
  const kSenderName = 'h0m9j6mm'; // directMessages.senderName
  const kText       = 'hj046bgs'; // directMessages.text
  const kCreatedAt  = '14dp9wer'; // directMessages.createdAt

  FFVariable _field(String key, String name) => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: key, name: name),
      ),
    ));

  FFVariable _formattedTime() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: kCreatedAt, name: 'createdAt'),
      ),
    ))
    ..operations.add(FFVariableOperation(
      dateTimeFormat: FFDateTimeFormat(format: 'HH:mm', isCustom: true),
    ));

  FFNode _txt(String nodeName, String fieldKey, String fieldName, {
    FFText_ThemeStyle style = FFText_ThemeStyle.BODY_MEDIUM,
    FFColor_ThemeColor? color,
  }) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodyMedium);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = style;
    copy.textValue  = FFStringValue(variable: _field(fieldKey, fieldName));
    if (color != null) {
      copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    }
    node.props.text = copy;
    return node;
  }

  FFNode _timeTxt(String nodeName, {FFColor_ThemeColor color = FFColor_ThemeColor.SECONDARY_TEXT}) {
    final node = UI.text('', name: nodeName, style: UITextStyle.bodySmall);
    final copy = node.props.text.deepCopy();
    copy.themeStyle = FFText_ThemeStyle.BODY_SMALL;
    copy.textValue  = FFStringValue(variable: _formattedTime());
    copy.colorValue = FFColorValue(inputValue: FFColor(themeColor: color));
    node.props.text = copy;
    return node;
  }

  // Clear background and padding on outer wrapper so bubbles style themselves.
  final outerContainer = findByKey(wc.node, 'Container_oulzkkz8');
  if (outerContainer != null) {
    if (outerContainer.props.container.hasBoxDecoration()) {
      outerContainer.props.container.boxDecoration.clearColorValue();
    }
    if (outerContainer.props.hasPadding()) {
      outerContainer.props.clearPadding();
    }
  }

  innerCol.children.clear();

  // ── Others' bubble (left) ──────────────────────────────────────────────────
  final otherMsgCol = UI.column(
    name: 'OtherMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OtherSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM,
        color: FFColor_ThemeColor.PRIMARY,
      ),
      _txt('OtherMsgText', kText, 'text'),
      _timeTxt('OtherMsgTime'),
    ],
  );

  final otherBubble = UI.container(
    name: 'OtherBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.secondaryBackground,
    child: otherMsgCol,
  );

  final otherRow = UI.row(
    name: 'OtherBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.start,
    children: [otherBubble],
  );
  setConditionalVisibility(
    otherRow,
    variable: conditionVar(
      _field(kSenderId, 'senderId'),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromAppState(userEmailId.deepCopy()),
    ).variable,
  );

  // ── Own bubble (right) ────────────────────────────────────────────────────
  final ownMsgMeta = UI.row(
    name: 'OwnMsgMeta',
    mainAxisAlignment: UIMainAxisAlignment.end,
    spacing: 4,
    children: [_timeTxt('OwnMsgTime', color: FFColor_ThemeColor.PRIMARY_BACKGROUND)],
  );

  final ownMsgCol = UI.column(
    name: 'OwnMsgCol',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 4,
    children: [
      _txt('OwnSenderName', kSenderName, 'senderName',
        style: FFText_ThemeStyle.LABEL_MEDIUM,
        color: FFColor_ThemeColor.PRIMARY_BACKGROUND,
      ),
      _txt('OwnMsgText', kText, 'text',
        color: FFColor_ThemeColor.PRIMARY_BACKGROUND,
      ),
      ownMsgMeta,
    ],
  );

  final ownBubble = UI.container(
    name: 'OwnBubble',
    padding: UIEdgeInsets.all(12),
    borderRadius: 12,
    color: UIColor.primary,
    child: ownMsgCol,
  );

  final ownRow = UI.row(
    name: 'OwnBubbleRow',
    mainAxisAlignment: UIMainAxisAlignment.end,
    children: [ownBubble],
  );
  setConditionalVisibility(
    ownRow,
    variable: conditionVar(
      _field(kSenderId, 'senderId'),
      FFCondition_Relation.EQUAL_TO,
      varFromAppState(userEmailId.deepCopy()),
    ).variable,
  );

  innerCol.children.add(otherRow);
  innerCol.children.add(ownRow);
}

// Fixes DirectChatPage send button: senderId authToken→userEmail AppState,
// and inserts wait(300) before the singleTimeQuery refresh for Firestore propagation.
void _fixDirectChatSendButton(FFProject project) {
  final wc = findPage(project, name: 'DirectChatPage');
  if (wc == null) return;

  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;

  final sendBtn = findByKey(wc.node, 'IconButton_y4orjomc');
  if (sendBtn == null) return;

  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;

  final root = tap.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueEntry = root.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  // Fix senderId in FirestoreCreate.
  final createNode = trueEntry.trueAction;
  if (createNode.hasAction() &&
      createNode.action.hasDatabase() &&
      createNode.action.database.hasCreateDocument()) {
    final create = createNode.action.database.createDocument;
    if (create.hasWrite()) {
      const kSenderIdKey = 'e252z61j'; // directMessages.senderId
      final entry = create.write.updates[kSenderIdKey];
      if (entry != null) {
        entry.variable = varFromAppState(userEmailId.deepCopy());
      }
    }
  }

  // Insert wait(300) before the singleTimeQuery refresh (idempotent).
  FFActionNode prev = trueEntry.trueAction;
  while (prev.hasFollowUpAction()) {
    final curr = prev.followUpAction;
    if (curr.hasAction() &&
        curr.action.hasDatabase() &&
        curr.action.database.hasFirestoreQuery() &&
        curr.action.database.firestoreQuery.singleTimeQuery) {
      if (prev.hasAction() &&
          !prev.action.hasDatabase() &&
          !prev.action.hasLocalStateUpdate() &&
          !prev.action.hasCustomAction() &&
          !prev.action.hasNavigate()) {
        return; // wait already present
      }
      prev.followUpAction = FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.wait(300),
        followUpAction: curr,
      );
      return;
    }
    prev = curr;
  }
}

void _fixDirectChatTextField(FFProject project) =>
    _fixChatTextFieldDebounce(project, 'DirectChatPage', 'DirectMessageField');

void _addEditDeleteToDirectChatPage(FFProject project) {
  final wc = findPage(project, name: 'DirectChatPage');
  if (wc == null) return;
  final ownCol = findDescendants(wc.node, (n) => n.name == 'OwnMsgCol').firstOrNull;
  if (ownCol == null) return;
  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;
  _addOwnActionsRow(
    ownCol, project, 'DirectChatPage', 'Scaffold_29a0eu74', 'ListView_z624qee2', 'hj046bgs',
    visibilityVar: _ownMessageVisibility('ListView_z624qee2', 'e252z61j', 'senderId', userEmailId),
    collectionName: 'directMessages',
  );
}

// ─── TeamChatPage send-button fixes ──────────────────────────────────────────
// Fixes three issues in the TeamChatPage send button (mirror of _fixGroupChatPage):
// 1. senderId in FirestoreCreate: authToken → userEmail
// 2. teamId  in FirestoreCreate: widget.teamId (empty for NavBar) → currentTeamId AppState
// 3. Refresh query WHERE clause: widget.teamId → currentTeamId AppState
//    Also inserts a wait(300) before the refresh query for Firestore propagation.
void _fixTeamChatSendButton(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final userEmailId    = _findAppStateFieldId(project, 'userEmail');
  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  if (userEmailId == null || currentTeamIdId == null) return;

  final sendBtn = findByKey(wc.node, 'IconButton_l68mmxn6');
  if (sendBtn == null) return;

  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;

  final root = tap.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueEntry = root.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  // Fix 1 & 2: senderId → userEmail, teamId → currentTeamId
  final createNode = trueEntry.trueAction;
  if (createNode.hasAction() &&
      createNode.action.hasDatabase() &&
      createNode.action.database.hasCreateDocument()) {
    final create = createNode.action.database.createDocument;
    if (create.hasWrite()) {
      const kSenderIdKey = 'o5z3acsh';
      const kTeamIdKey   = 'nx78p9te';
      final senderEntry = create.write.updates[kSenderIdKey];
      if (senderEntry != null) {
        senderEntry.variable = varFromAppState(userEmailId.deepCopy());
      }
      final teamIdEntry = create.write.updates[kTeamIdKey];
      if (teamIdEntry != null) {
        teamIdEntry.variable = varFromAppState(currentTeamIdId.deepCopy());
      }
    }
  }

  // Fix 3: replace the refresh query's WHERE with currentTeamId, add wait(300).
  final teamIdField = findCollectionField(
    project, collectionName: 'teamChats', fieldName: 'teamId');
  if (teamIdField == null) return;

  FFActionNode prev = trueEntry.trueAction;
  while (prev.hasFollowUpAction()) {
    final curr = prev.followUpAction;
    if (curr.hasAction() &&
        curr.action.hasDatabase() &&
        curr.action.database.hasFirestoreQuery() &&
        curr.action.database.firestoreQuery.singleTimeQuery) {
      // Fix WHERE clause to use currentTeamId AppState.
      curr.action.database.firestoreQuery.where = FFFirestoreWhere(
        isAnd: true,
        filters: [
          FFFirestoreWhere_NestedFilter(
            baseFilter: FFFirestoreFilter(
              collectionFieldIdentifier: teamIdField.identifier.deepCopy(),
              relation: FFFirestoreFilter_Relation.EQUAL_TO,
              variable: varFromAppState(currentTeamIdId.deepCopy()),
            ),
          ),
        ],
      );
      // Idempotent wait: if prev is already a wait node, skip.
      if (prev.hasAction() &&
          !prev.action.hasDatabase() &&
          !prev.action.hasLocalStateUpdate() &&
          !prev.action.hasCustomAction() &&
          !prev.action.hasNavigate()) {
        return;
      }
      prev.followUpAction = FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.wait(300),
        followUpAction: curr,
      );
      return;
    }
    prev = curr;
  }
}

// Wist het messageText veld in TeamChatPage state nadat de send-knop is geklikt.
// De andere 3 chat-pagina's (ChatDetail, Direct, Group) hebben dit al in de
// DSL via SetState.clear, maar TeamChatPage's send-chain is via raw mutatie
// opgebouwd en mist deze clear-actie.
//
// Plaatst de clear-actie direct ná de Firestore create (vóór de wait + refresh),
// zodat het tekstveld onmiddellijk leeg lijkt voor de gebruiker.
// Idempotent: skipt als er al een localStateUpdate voor messageText in de keten zit.
void _addClearMessageTextToTeamChatSend(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  final messageTextId = _findPageStateFieldId(project, 'TeamChatPage', 'messageText');
  if (messageTextId == null) return;

  final sendBtn = findByKey(wc.node, 'IconButton_l68mmxn6');
  if (sendBtn == null) return;

  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;
  if (!tap.rootAction.hasConditionActions()) return;
  if (tap.rootAction.conditionActions.trueActions.isEmpty) return;
  final trueEntry = tap.rootAction.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  // Idempotent: skip als al ergens in de keten een page state update voor
  // messageText zit.
  bool _alreadyClears(FFActionNode node) {
    if (node.hasAction() && node.action.hasLocalStateUpdate()) {
      final lsu = node.action.localStateUpdate;
      if (lsu.stateVariableType == FFStateVariableType.WIDGET_CLASS_STATE &&
          lsu.updates.any((u) => u.fieldIdentifier.name == 'messageText')) {
        return true;
      }
    }
    if (node.hasFollowUpAction() && _alreadyClears(node.followUpAction)) return true;
    return false;
  }
  if (_alreadyClears(tap.rootAction)) return;

  // Vind de Firestore create-document node (eerste actie in true-branch) en
  // voeg de clear-actie toe als directe followUp.
  final createNode = trueEntry.trueAction;
  if (!createNode.hasAction() ||
      !createNode.action.hasDatabase() ||
      !createNode.action.database.hasCreateDocument()) {
    return;
  }

  final clearAction = FFAction(
    key: generateRandomAlphaNumericString(),
    localStateUpdate: FFLocalStateUpdate(
      updates: [
        FFLocalStateFieldUpdate(
          fieldIdentifier: messageTextId.deepCopy(),
          setValue: FFValue(variable: varFromConstant(
              FFConstantsVariable_ConstantValue.EMPTY_STRING)),
        ),
      ],
      stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
    ),
  );

  // Wrap: createNode → clear → (huidige createNode.followUpAction)
  final originalFollowUp = createNode.hasFollowUpAction()
      ? createNode.followUpAction
      : null;

  final clearNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: clearAction,
    followUpAction: originalFollowUp,
  );
  createNode.followUpAction = clearNode;
}

// Ensures Text_t5hhwvox (message text widget in team chat list item) is explicitly
// bound to the teamChats.text field from the list's generator variable.
void _fixTeamChatMessageTextBinding(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;

  const listKey = 'ListView_9sebksf4';
  const textKey = 'c6cfne01';

  final textNode = findByKey(wc.node, 'Text_t5hhwvox');
  if (textNode == null) return;

  final textVar = varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: textKey, name: 'text'),
      ),
    ));

  final txtCopy = textNode.props.text.deepCopy();
  txtCopy.textValue = FFStringValue(variable: textVar);
  textNode.props.text = txtCopy;
}


// ─── Calendar / agenda buttons ────────────────────────────────────────────────

const _kCalTitleKey    = 'cal_p_title';
const _kCalDateKey     = 'cal_p_date';
const _kCalLocationKey = 'cal_p_location';
const _kCalNotesKey    = 'cal_p_notes';

const _kAddEventToCalendarCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:add_2_calendar/add_2_calendar.dart';

Future<void> addEventToCalendar(
  String title,
  String? dateStr,
  String? location,
  String? notes,
) async {
  if (dateStr == null || dateStr.isEmpty) return;
  DateTime? start;

  // Try ISO-8601 and common European formats.
  final formats = [
    'yyyy-MM-ddTHH:mm:ss',
    'yyyy-MM-dd HH:mm:ss',
    'yyyy-MM-dd HH:mm',
    'dd-MM-yyyy HH:mm',
    'dd-MM-yyyy',
    'yyyy-MM-dd',
  ];

  for (final fmt in formats) {
    try {
      start = DateFormat(fmt).parseLoose(dateStr.trim());
      break;
    } catch (_) {}
  }

  if (start == null) return;

  final event = Event(
    title: title,
    description: notes ?? '',
    location: location ?? '',
    startDate: start,
    endDate: start.add(const Duration(hours: 2)),
    allDay: false,
  );

  await Add2Calendar.addEvent2Cal(event);
}
''';

// Adds a calendar icon button to the AppBar of the three detail pages.
// Idempotent: guarded by widget name check on each page.
void _addCalendarButtons(FFProject project) {
  // Ensure the pub dependency and custom action are present.
  if (findPubDependency(project, name: 'add_2_calendar') == null) {
    addPubDependency(project, name: 'add_2_calendar', version: '^3.0.1');
  }

  if (findCustomAction(project, name: 'AddEventToCalendar') == null) {
    addCustomAction(
      project,
      name: 'AddEventToCalendar',
      description: 'Voegt een evenement toe aan de apparaatkalender.',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(key: _kCalTitleKey, name: 'title'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
        FFParameter(
          identifier: FFIdentifier(key: _kCalDateKey, name: 'dateStr'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
        FFParameter(
          identifier: FFIdentifier(key: _kCalLocationKey, name: 'location'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
        FFParameter(
          identifier: FFIdentifier(key: _kCalNotesKey, name: 'notes'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: _kAddEventToCalendarCode,
    );
  } else {
    updateCustomAction(project, name: 'AddEventToCalendar', code: _kAddEventToCalendarCode);
  }

  final ca = findCustomAction(project, name: 'AddEventToCalendar');
  if (ca == null) return;

  _addCalendarButton_Bardienst(project, ca);
  _addCalendarButton_Wedstrijd(project, ca);
  _addCalendarButton_Rijschema(project, ca);
}

FFValue _lit(String s) =>
    FFValue(inputValue: FFParameterValue(serializedValue: s));

FFValue _pageStateVar(FFProject project, String pageName, String fieldName, String scaffoldKey) {
  final id = _findPageStateFieldId(project, pageName, fieldName);
  if (id == null) return FFValue(inputValue: FFParameterValue(serializedValue: ''));
  final v = varFromPageState(id.deepCopy());
  v.nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
  return FFValue(variable: v);
}

void _addCalendarAppBarButton(
  FFProject project,
  String pageName,
  String btnName,
  FFCustomAction ca,
  FFFunctionCallValues argValues,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  if (findDescendants(wc.node, (n) => n.name == btnName).isNotEmpty) return;

  final appBar = getPropertyChild(wc.node, 'appBar');
  if (appBar == null) return;

  final calBtn = UI.iconButton(
    'calendar_month',
    color: UIColor.primaryText,
    name: btnName,
  );

  Actions.addTriggerChain(
    calBtn,
    FFActionTriggerType.ON_TAP,
    FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: FFAction(
        key: generateRandomAlphaNumericString(),
        customAction: FFCustomActionCall(
          customActionIdentifier: ca.identifier.deepCopy(),
          argumentValues: argValues,
        ),
      ),
    ),
  );

  appBar.children.add(calBtn);
  final existingKeys = (appBar.childPropertyMap['actions']?.keyRefs ?? [])
      .map((r) => r.key)
      .toList();
  existingKeys.add(calBtn.key);
  appBar.childPropertyMap['actions'] = FFChildrenKeys(
    keyRefs: existingKeys.map((k) => FFNodeKeyReference(key: k)).toList(),
  );
}

void _addCalendarButton_Bardienst(FFProject project, FFCustomAction ca) {
  final wc = findPage(project, name: 'BardienDetailPage');
  if (wc == null) return;
  final scaffoldKey = wc.node.key;

  final args = FFFunctionCallValues();
  args.arguments[_kCalTitleKey]    = FFFunctionCallValues_FFArgument(value: _lit('Bardienst'));
  args.arguments[_kCalDateKey]     = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'BardienDetailPage', 'dutyDate', scaffoldKey),
  );
  args.arguments[_kCalLocationKey] = FFFunctionCallValues_FFArgument(value: _lit(''));
  args.arguments[_kCalNotesKey]    = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'BardienDetailPage', 'dutyShift', scaffoldKey),
  );

  _addCalendarAppBarButton(project, 'BardienDetailPage', 'BardienCalendarButton', ca, args);
}

void _addCalendarButton_Wedstrijd(FFProject project, FFCustomAction ca) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  final scaffoldKey = wc.node.key;

  final args = FFFunctionCallValues();
  args.arguments[_kCalTitleKey]    = FFFunctionCallValues_FFArgument(value: _lit('Wedstrijd'));
  args.arguments[_kCalDateKey]     = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'WedstrijdDetailPage', 'matchDatetime', scaffoldKey),
  );
  args.arguments[_kCalLocationKey] = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'WedstrijdDetailPage', 'matchLocation', scaffoldKey),
  );
  args.arguments[_kCalNotesKey]    = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'WedstrijdDetailPage', 'matchOpponent', scaffoldKey),
  );

  _addCalendarAppBarButton(project, 'WedstrijdDetailPage', 'WedstrijdCalendarButton', ca, args);
}

void _addCalendarButton_Rijschema(FFProject project, FFCustomAction ca) {
  final wc = findPage(project, name: 'RijschemaDetailPage');
  if (wc == null) return;
  final scaffoldKey = wc.node.key;

  final args = FFFunctionCallValues();
  args.arguments[_kCalTitleKey]    = FFFunctionCallValues_FFArgument(value: _lit('Rijschema'));
  args.arguments[_kCalDateKey]     = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'RijschemaDetailPage', 'rijDatetime', scaffoldKey),
  );
  args.arguments[_kCalLocationKey] = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'RijschemaDetailPage', 'rijLocation', scaffoldKey),
  );
  args.arguments[_kCalNotesKey]    = FFFunctionCallValues_FFArgument(
    value: _pageStateVar(project, 'RijschemaDetailPage', 'rijOpponent', scaffoldKey),
  );

  _addCalendarAppBarButton(project, 'RijschemaDetailPage', 'RijschemaCalendarButton', ca, args);
}

// ─────────────────────────────────────────────────────────────────────────────

// Ensures the "Stuur inloglink" button is visible above the keyboard:
//   1. Remove bottom padding from the outer scrollable Column (gains 32px viewport).
//   2. ON_FOCUS_CHANGE on MagicLinkEmailField → wait 350ms (keyboard animation) →
//      scroll the Column to bottom so the button appears above the keyboard.
void _fixLoginKeyboard(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  const _kOuterColKey = 'Column_agcaeg1m';
  const _kMagicFieldKey = 'TextField_lkhg582z';

  // ── Step 1: Change outer column padding from all:32 to LRTB(32,32,32,0) ──
  final outerCol = findByKey(wc.node, _kOuterColKey);
  if (outerCol != null && outerCol.props.hasPadding()) {
    final p = outerCol.props.padding;
    final alreadyFixed = p.type == FFPadding_PaddingType.FF_PADDING_ONLY &&
        p.hasBottomValue() && p.bottomValue.inputValue == 0.0;
    if (!alreadyFixed) {
      double sideVal = 32.0;
      if (p.hasAllValue()) {
        sideVal = p.allValue.inputValue;
      } else if (p.legacyAll != 0) {
        sideVal = p.legacyAll;
      }
      outerCol.props.padding = FFPadding(
        type: FFPadding_PaddingType.FF_PADDING_ONLY,
        topValue:    FFDoubleValue(inputValue: sideVal),
        leftValue:   FFDoubleValue(inputValue: sideVal),
        rightValue:  FFDoubleValue(inputValue: sideVal),
        bottomValue: FFDoubleValue(inputValue: 0),
      );
    }
  }

  // ── Step 2: ON_FOCUS_CHANGE → wait 350ms → scroll column to bottom ──
  final magicField = findByKey(wc.node, _kMagicFieldKey);
  if (magicField == null) return;

  if (magicField.triggerActions.any(
        (t) => t.hasTrigger() &&
               t.trigger.triggerType == FFActionTriggerType.ON_FOCUS_CHANGE,
      )) return;

  magicField.triggerActions.add(
    FFTriggerActions(
      trigger: FFActionTrigger(
        triggerType: FFActionTriggerType.ON_FOCUS_CHANGE,
      ),
      rootAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.wait(350),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: Actions.scrollTo(
            widgetKey: _kOuterColKey,
            percentage: 1.0,
            durationMillis: 200,
          ),
        ),
      ),
    ),
  );
}

// Verwijdert dubbele LedenLoginSection Column-nodes uit de LoginPage body.
// Eerdere pushes hebben de sectie meerdere keren ingevoegd (zonder
// idempotency-check op de wrapper Column zelf). De juiste section heeft
// een SendMagicLinkButton; oudere duplicates missen die soms.
//
// Strategie:
//   1. Verzamel alle Columns met naam 'LedenLoginSection'
//   2. Prefereer de section die een SendMagicLinkButton bevat — die houden we
//   3. Verwijder alle andere uit hun parent
//
// Idempotent: doet niets als er hooguit één LedenLoginSection bestaat.
void _dedupLedenLoginSection(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  final sections = findDescendants(wc.node, (n) => n.name == 'LedenLoginSection');
  if (sections.length <= 1) return;

  // Vind de section die een werkende SendMagicLinkButton bevat.
  FFNode? keeper;
  for (final s in sections) {
    if (findDescendants(s, (n) => n.name == 'SendMagicLinkButton').isNotEmpty) {
      keeper = s;
      break;
    }
  }
  // Fallback: behoud de laatste (meest recent toegevoegde) als er geen
  // werkende SendMagicLinkButton in zit.
  keeper ??= sections.last;

  for (final s in sections) {
    if (identical(s, keeper)) continue;
    final res = findParentByKey(wc.node, s.key);
    res?.parent.children.removeWhere((n) => n.key == s.key);
  }
}

// Zet debounce op 0 + bi-directional state binding voor alle LoginPage
// TextFields zodat één tik op de inlog/magic-link knop direct werkt.
//
// Standaard heeft FlutterFlow's onChanged trigger een 2-seconden debounce —
// als je tekst typt en meteen op de knop tikt, leest de validatie nog een
// lege state field. Bij een tweede tik (na de debounce) gaat het goed.
void _fixLoginTextFieldDebounce(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  // [TextFieldKey, pageStateFieldName]
  const fields = [
    ('TextField_73irroiw', 'emailInput'),     // beheerder e-mail
    ('TextField_v1ycg741', 'passwordInput'),  // beheerder wachtwoord
  ];

  for (final (tfKey, stateName) in fields) {
    final tf = findByKey(wc.node, tfKey);
    if (tf == null) continue;
    final stateId = _findPageStateFieldId(project, 'LoginPage', stateName);
    if (stateId == null) continue;

    tf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
    tf.props.textField.localStateValue = true;
    tf.props.textField.initialText = FFText(
      textValue: FFStringValue(
        variable: varFromPageState(stateId.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      ),
    );
  }

  // MagicLinkEmailField (kan andere key hebben na dedup) — zoek op naam.
  final magicTf = findDescendants(wc.node, (n) => n.name == 'MagicLinkEmailField').firstOrNull;
  if (magicTf != null) {
    final magicId = _findPageStateFieldId(project, 'LoginPage', 'magicLinkEmail');
    if (magicId != null) {
      magicTf.props.ensureTextField().debounceTimeValue = FFDoubleValue(inputValue: 0.0);
      magicTf.props.textField.localStateValue = true;
      magicTf.props.textField.initialText = FFText(
        textValue: FFStringValue(
          variable: varFromPageState(magicId.deepCopy())
            ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
        ),
      );
    }
  }
}

// ─── Login button input validation ───────────────────────────────────────────

void _addLoginValidation(FFProject project) {
  final wc = findPage(project, name: 'LoginPage');
  if (wc == null) return;

  const _kScaffoldKey = 'Scaffold_ykwav4b7';

  // Build "field != ''" boolean variable from a LoginPage state field.
  FFVariable _notEmpty(String fieldName) {
    final id = _findPageStateFieldId(project, 'LoginPage', fieldName);
    final fieldVar = varFromPageState(
        (id ?? FFIdentifier(name: fieldName)).deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: _kScaffoldKey);
    return FFVariable(
      source: FFVariableSource.FUNCTION_CALL,
      functionCall: FFFunctionCall(
        condition: FFCondition(relation: FFCondition_Relation.NOT_EQUAL_TO),
        values: [
          FFValue(variable: fieldVar),
          FFValue(variable: varFromConstant(
              FFConstantsVariable_ConstantValue.EMPTY_STRING)),
        ],
      ),
    );
  }

  // Wraps the existing ON_TAP root action with a validation condition.
  // Idempotent: skips if root is already a pure condition node (no direct action).
  void _wrapWithValidation({
    required String btnKey,
    required FFVariable condition,
    required String errorMessage,
  }) {
    final btn = findByKey(wc.node, btnKey);
    if (btn == null) return;
    final tapIdx = btn.triggerActions.indexWhere(
      (t) => t.hasTrigger() &&
             t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    if (tapIdx < 0) return;
    final tap = btn.triggerActions[tapIdx];
    if (!tap.hasRootAction()) return;
    if (tap.rootAction.hasConditionActions() && !tap.rootAction.hasAction()) {
      return; // already wrapped
    }
    final existing = tap.rootAction.deepCopy();
    tap.rootAction = Actions.conditional(
      condition: condition,
      trueActions: existing,
      falseActions: Actions.chain([Actions.snackBar(errorMessage)]),
    );
  }

  // Admin login (Button_bg6zh5x9): emailInput AND passwordInput must be filled.
  final adminCond = FFVariable(
    source: FFVariableSource.FUNCTION_CALL,
    functionCall: FFFunctionCall(
      combineConditions: FFCombineConditions(
        operator: FFCombineConditions_LogicalOperator.AND_OP,
      ),
      values: [
        FFValue(variable: _notEmpty('emailInput')),
        FFValue(variable: _notEmpty('passwordInput')),
      ],
    ),
  );
  _wrapWithValidation(
    btnKey: 'Button_bg6zh5x9',
    condition: adminCond,
    errorMessage: 'Vul email en wachtwoord in',
  );

  // Magic link button: find by NAME so the hard-coded key never goes stale.
  // There may be duplicate SendMagicLinkButton widgets if ensureInsertedAfter
  // re-ran. Remove all but the LAST one to avoid outputVariableName conflicts.
  final allMagicBtns = findDescendants(wc.node, (n) => n.name == 'SendMagicLinkButton');
  if (allMagicBtns.length > 1) {
    // Keep the last-created button (highest index) and remove others from parents.
    for (var i = 0; i < allMagicBtns.length - 1; i++) {
      final stale = allMagicBtns[i];
      final result = findParentByKey(wc.node, stale.key);
      result?.parent.children.removeWhere((n) => n.key == stale.key);
    }
  }
  final magicBtn = findDescendants(wc.node, (n) => n.name == 'SendMagicLinkButton').firstOrNull;
  if (magicBtn != null) {
    _wrapWithValidation(
      btnKey: magicBtn.key,
      condition: _notEmpty('magicLinkEmail'),
      errorMessage: 'Vul een e-mailadres in',
    );
  }
}

// ─── appUsers registry ────────────────────────────────────────────────────────
// Only registers the custom action. The actual chain switch is in
// _setupAppUsersRegistry (kept for later, not called from buildEditFlow).
void _registerGetAppUsersAction(FFProject project) {
  if (findCustomAction(project, name: 'GetAppUsersAsMembers') == null) {
    addCustomAction(
      project,
      name: 'GetAppUsersAsMembers',
      description: 'Laadt geregistreerde app-gebruikers van het huidige team uit Firestore en slaat ze op in pendingTeamMembers.',
      arguments: [],
      code: _kGetAppUsersMembersCode,
    );
  } else {
    updateCustomAction(project, name: 'GetAppUsersAsMembers', code: _kGetAppUsersMembersCode);
  }
}



// GetAppUsersAsMembers: reads the appUsers Firestore collection for the current
// team and writes the result into AppState.pendingTeamMembers.
const _kGetAppUsersMembersCode = r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:cloud_firestore/cloud_firestore.dart';

Future<void> getAppUsersAsMembers() async {
  final teamId = FFAppState().currentTeamId;
  if (teamId.isEmpty) return;

  try {
    final snap = await FirebaseFirestore.instance
        .collection('appUsers')
        .where('teamId', isEqualTo: teamId)
        .get();

    final members = snap.docs
        .map((d) {
          final data = d.data();
          return SwapMemberStruct(
            id:   (data['userId']   as String?) ?? '',
            name: (data['userName'] as String?) ?? '',
          );
        })
        .where((m) => m.id.isNotEmpty && m.name.isNotEmpty)
        .toList();

    FFAppState().update(() {
      FFAppState().pendingTeamMembers = members;
    });
  } catch (e) {
    debugPrint('[GetAppUsersAsMembers] error: $e');
  }
}
''';

// Registers the GetAppUsersAsMembers custom action and rewires the ChatsPage
// ON_INIT_STATE chain so the direct-chat member strip is populated from Firestore
// (appUsers) instead of the backend GetTeamMembers API.
//
// Before (set by _wireChatsPageLoad):
//   … → [API: GetTeamMembers] → [SetState(teamMembers)] → [API: GetStaffGroups] → …
//
// After:
//   … → [CustomAction: GetAppUsersAsMembers] → [SetState(teamMembers)] → [API: GetStaffGroups] → …
void _setupAppUsersRegistry(FFProject project) {
  // 1. Register (or update) the custom action.
  if (findCustomAction(project, name: 'GetAppUsersAsMembers') == null) {
    addCustomAction(
      project,
      name: 'GetAppUsersAsMembers',
      description: 'Laadt geregistreerde app-gebruikers van het huidige team uit Firestore en slaat ze op in pendingTeamMembers.',
      arguments: [],
      code: _kGetAppUsersMembersCode,
    );
  } else {
    updateCustomAction(project, name: 'GetAppUsersAsMembers', code: _kGetAppUsersMembersCode);
  }

  // 2. Find ChatsPage and its first ON_INIT_STATE trigger.
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final triggerIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  if (triggerIdx < 0) return;
  final trigger = wc.node.triggerActions[triggerIdx];
  if (!trigger.hasRootAction()) return;

  // 3. Walk the chain to find the GetTeamMembers node.
  //    We need a reference to its *parent* so we can splice it out.
  FFActionNode? parent;
  FFActionNode? getTeamMembersNode;

  void walk(FFActionNode node, FFActionNode? p) {
    if (getTeamMembersNode != null) return;
    if (node.hasAction() &&
        node.action.hasDatabase() &&
        node.action.database.hasApiCall() &&
        node.action.database.apiCall.hasEndpointIdentifier() &&
        node.action.database.apiCall.endpointIdentifier.name == 'GetTeamMembers') {
      parent = p;
      getTeamMembersNode = node;
      return;
    }
    if (node.hasFollowUpAction()) walk(node.followUpAction, node);
  }
  walk(trigger.rootAction, null);

  if (getTeamMembersNode == null) return; // already replaced or not wired

  // 4. Find the pendingTeamMembers AppState field id.
  final pendingId = _findAppStateFieldId(project, 'pendingTeamMembers');
  if (pendingId == null) return;

  final getAppUsersAction = findCustomAction(project, name: 'GetAppUsersAsMembers');
  if (getAppUsersAction == null) return;

  // 5. Build SetState(teamMembers = pendingTeamMembers) preserving the existing
  //    followUpAction chain that was attached to GetTeamMembers' onSuccess.
  //    The old GetTeamMembers node had an onSuccess → SetState(teamMembers).
  //    We now call GetAppUsersAsMembers (void, writes pendingTeamMembers), then
  //    immediately SetState(teamMembers = pendingTeamMembers), then continue with
  //    whatever was after GetTeamMembers.
  final oldFollowUp = getTeamMembersNode!.hasFollowUpAction()
      ? getTeamMembersNode!.followUpAction
      : null;

  // The old chain was: [GetTeamMembers onSuccess→SetState(teamMembers)] → followUp
  // Collect the real follow-up after the SetState(teamMembers) node.
  //
  // Because GetTeamMembers was built by _wireChatsPageLoad with an onSuccess block,
  // the condition/followUp wrapping may vary.  Walk the old node's followUp chain
  // to find the GetStaffGroups node if present.
  FFActionNode? staffGroupsNode;
  if (oldFollowUp != null) {
    void findStaff(FFActionNode n) {
      if (n.hasAction() &&
          n.action.hasDatabase() &&
          n.action.database.hasApiCall() &&
          n.action.database.apiCall.endpointIdentifier.name == 'GetStaffGroups') {
        staffGroupsNode = n;
        return;
      }
      if (n.hasFollowUpAction()) findStaff(n.followUpAction);
    }
    findStaff(oldFollowUp);
  }

  // 6. Build the replacement node:
  //    [GetAppUsersAsMembers] → [SetState(teamMembers)] → [GetStaffGroups chain if present]
  final setStateNode = Actions.updatePageState(
    project,
    widgetClassName: 'ChatsPage',
    updates: [
      StateFieldUpdate.setFromVariable(
        'teamMembers',
        varFromAppState(pendingId.deepCopy()),
      ),
    ],
  );

  // Wrap SetState in an FFActionNode; attach GetStaffGroups as followUp if present.
  final setStateActionNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: setStateNode,
    followUpAction: staffGroupsNode?.deepCopy(),
  );

  final replacementNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: getAppUsersAction.identifier.deepCopy(),
      ),
    ),
    followUpAction: setStateActionNode,
  );

  // 7. Splice the replacement into the chain where GetTeamMembers was.
  if (parent == null) {
    // GetTeamMembers was the root action — replace it directly.
    trigger.rootAction = replacementNode;
  } else {
    parent!.followUpAction = replacementNode;
  }
}

// ─── Chat: edit / delete own messages ────────────────────────────────────────

void _addChatEditDeleteFeature(FFProject project) {
  _ensureAppStateField(project, 'editingChatDocPath', FFBaseDataType.String);
  // Remove stale custom actions from previous iterations.
  if (findCustomAction(project, name: 'UpdateChatMessage') != null) {
    removeCustomAction(project, name: 'UpdateChatMessage');
  }
  if (findCustomAction(project, name: 'StartChatEdit') != null) {
    removeCustomAction(project, name: 'StartChatEdit');
  }
  _addEditDeleteToChatDetailPage(project);
  _addEditDeleteToGroupChatPage(project);
  _addEditDeleteToTeamChatPage(project);
  _addEditDeleteToDirectChatPage(project);
  // Re-apply conversationId filter so the new delete/save refresh queries on
  // ChatDetailPage also get filtered (they were added after the initial run).
  _wireChatDetailFilters(project);
}

// ─── Soft-delete UI ──────────────────────────────────────────────────────────
//
// Voor elke chat pagina:
//  - Voegt een 'DeletedBubble' node toe als sibling van OwnBubble + OtherBubble
//    in de item-column. Grijze achtergrond, cursief "Bericht verwijderd" tekst.
//    Visibility: deleted == true.
//  - Wijzigt de visibility van bestaande OwnBubble + OtherBubble om AND !deleted
//    toe te voegen, zodat ze verbergen wanneer het bericht verwijderd is.
//  - Wijzigt OwnActionsRow visibility eveneens om AND !deleted toe te voegen.
//
// Idempotent: skipt wanneer DeletedBubble al bestaat in een item-column.
void _addSoftDeleteDisplay(FFProject project) {
  // Config per chat-pagina: listView key, message-collectie, deleted-veld key,
  // item column key (kinderen daarvan zijn de bubbles).
  const configs = [
    (page: 'ChatDetailPage', listKey: 'ListView_ws05qhut', coll: 'chatMessages',
     itemColKey: 'Column_e6dtwbzq'),
    (page: 'DirectChatPage', listKey: 'ListView_z624qee2', coll: 'directMessages',
     itemColKey: 'Column_hz6ofrah'),
    (page: 'GroupChatPage',  listKey: 'ListView_6t0r29c1', coll: 'groupMessages',
     itemColKey: 'Column_dk9brcp8'),
    (page: 'TeamChatPage',   listKey: 'ListView_9sebksf4', coll: 'teamChats',
     itemColKey: 'Column_thoxlcla'),
  ];

  for (final cfg in configs) {
    final wc = findPage(project, name: cfg.page);
    if (wc == null) continue;

    final itemCol = findByKey(wc.node, cfg.itemColKey);
    if (itemCol == null) continue;

    final deletedField = findCollectionField(
      project, collectionName: cfg.coll, fieldName: 'deleted',
    );
    if (deletedField == null) continue;

    // Variable: list-item.deleted == true   (geeft true bij verwijderd)
    FFVariable _deletedVar() {
      return conditionVar(
        varFromGeneratorVariable(cfg.listKey)
          ..operations.add(FFVariableOperation(
            accessDocumentField: FFAccessDocumentField(
              fieldIdentifier: deletedField.identifier.deepCopy(),
            ),
          )),
        FFCondition_Relation.EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
      ).variable;
    }

    // Variable: list-item.deleted != true   (geeft true bij NIET verwijderd of veld leeg)
    FFVariable _notDeletedVar() {
      return conditionVar(
        varFromGeneratorVariable(cfg.listKey)
          ..operations.add(FFVariableOperation(
            accessDocumentField: FFAccessDocumentField(
              fieldIdentifier: deletedField.identifier.deepCopy(),
            ),
          )),
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
      ).variable;
    }

    // ── Wijzig bestaande visibility: combine met AND !deleted ─────────────
    void _andNotDeleted(FFNode node) {
      if (!node.props.hasVisibility() ||
          !node.props.visibility.hasVisibleValue()) {
        // Nog geen visibility — zet alleen !deleted
        setConditionalVisibility(node, variable: _notDeletedVar());
        return;
      }
      // Skip als al een AND_OP combine (idempotent).
      final existing = node.props.visibility.visibleValue;
      if (existing.hasVariable() &&
          existing.variable.hasFunctionCall() &&
          existing.variable.functionCall.hasCombineConditions() &&
          existing.variable.functionCall.combineConditions.operator ==
              FFCombineConditions_LogicalOperator.AND_OP) {
        return;
      }
      // Combine existing met !deleted via AND.
      final existingVar = existing.variable.deepCopy();
      final combined = andConditionsVar([existingVar, _notDeletedVar()]).variable;
      setConditionalVisibility(node, variable: combined);
    }

    // OwnBubbleRow + OtherBubbleRow: hide hele rij (incl. bubble + binnen-tekst)
    // als bericht verwijderd. Belangrijk: voeg ook AND !deleted toe op de binnen-
    // containers EN op de message-text nodes zodat zelfs als één visibility-laag
    // niet door codegen wordt opgepikt, het bericht alsnog onzichtbaar blijft.
    for (final node in findDescendants(itemCol,
        (n) => n.name == 'OwnBubbleRow' ||
               n.name == 'OtherBubbleRow' ||
               n.name == 'OwnBubble' ||
               n.name == 'OtherBubble' ||
               n.name == 'OwnMsgText' ||
               n.name == 'OtherMsgText' ||
               n.name == 'OwnMsgCol' ||
               n.name == 'OtherMsgCol')) {
      _andNotDeleted(node);
    }
    // OwnActionsRow: hide as deleted (combined met bestaande "own message" check)
    for (final node in findDescendants(itemCol, (n) => n.name == 'OwnActionsRow')) {
      _andNotDeleted(node);
    }

    // ── Voeg DeletedBubble toe (idempotent) ───────────────────────────────
    if (findDescendants(itemCol, (n) => n.name == 'DeletedBubble').isNotEmpty) continue;

    final deletedText = UI.text('Bericht verwijderd',
        name: 'DeletedBubbleText', style: UITextStyle.bodyMedium);
    final txtCopy = deletedText.props.text.deepCopy();
    txtCopy.colorValue = FFColorValue(
      inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_TEXT),
    );
    txtCopy.italicValue = FFBooleanValue(inputValue: true);
    deletedText.props.text = txtCopy;

    final deletedBubble = UI.container(
      name: 'DeletedBubble',
      padding: UIEdgeInsets.all(16),
      borderRadius: 12,
      color: UIColor.secondaryBackground,
      width: double.infinity,
      child: deletedText,
    );
    setConditionalVisibility(deletedBubble, variable: _deletedVar());

    itemCol.children.add(deletedBubble);
  }
}

// ─── BumpConversationUnread wiring ───────────────────────────────────────────

// Appends a BumpConversationUnread custom-action call at the end of the send
// button's true-action chain on a chat page. Idempotent.
void _appendBumpUnreadToChatSend({
  required FFProject project,
  required String pageName,
  required String sendBtnKey,
}) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final sendBtn = findByKey(wc.node, sendBtnKey);
  if (sendBtn == null) return;

  final bumpAction = findCustomAction(project, name: 'BumpConversationUnread');
  if (bumpAction == null) return;

  final tapIdx = sendBtn.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );
  if (tapIdx < 0) return;
  final tap = sendBtn.triggerActions[tapIdx];
  if (!tap.hasRootAction()) return;
  final root = tap.rootAction;
  if (!root.hasConditionActions()) return;
  if (root.conditionActions.trueActions.isEmpty) return;
  final trueEntry = root.conditionActions.trueActions.first;
  if (!trueEntry.hasTrueAction()) return;

  // Idempotent: walk chain, return if BumpConversationUnread already present.
  bool hasBump(FFActionNode n) {
    if (n.hasAction() &&
        n.action.hasCustomAction() &&
        n.action.customAction.customActionIdentifier.name ==
            'BumpConversationUnread') {
      return true;
    }
    if (n.hasFollowUpAction() && hasBump(n.followUpAction)) return true;
    return false;
  }
  if (hasBump(trueEntry.trueAction)) return;

  // Walk to chain tail and append the bump call.
  FFActionNode tail = trueEntry.trueAction;
  while (tail.hasFollowUpAction()) tail = tail.followUpAction;

  final bumpNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      customAction: FFCustomActionCall(
        customActionIdentifier: bumpAction.identifier.deepCopy(),
        argumentValues: FFFunctionCallValues(),
      ),
    ),
  );
  tail.followUpAction = bumpNode;
}

// Ensures the WatchUnreadChatCount custom action fires on a page's onLoad
// (appended to an existing chain if present, otherwise as a single-node chain).
// Idempotent: skips if the custom action is already invoked in the page's
// ON_INIT_STATE chain.
void _wireWatchUnreadOnPageLoad(FFProject project, String pageName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;

  final watch = findCustomAction(project, name: 'WatchUnreadChatCount');
  if (watch == null) return;

  bool chainHasWatch(FFActionNode n) {
    if (n.hasAction() &&
        n.action.hasCustomAction() &&
        n.action.customAction.customActionIdentifier.name ==
            'WatchUnreadChatCount') {
      return true;
    }
    if (n.hasFollowUpAction() && chainHasWatch(n.followUpAction)) return true;
    return false;
  }

  // Find existing ON_INIT_STATE trigger.
  final initTriggerIdx = wc.node.triggerActions.indexWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );

  FFActionNode watchNode() => FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: FFAction(
          key: generateRandomAlphaNumericString(),
          customAction: FFCustomActionCall(
            customActionIdentifier: watch.identifier.deepCopy(),
            argumentValues: FFFunctionCallValues(),
          ),
        ),
      );

  if (initTriggerIdx < 0) {
    Actions.onPageLoadChain(wc.node, watchNode());
    return;
  }

  final tap = wc.node.triggerActions[initTriggerIdx];
  if (!tap.hasRootAction()) return;
  if (chainHasWatch(tap.rootAction)) return;

  // Append at the tail of the existing chain.
  FFActionNode tail = tap.rootAction;
  while (tail.hasFollowUpAction()) tail = tail.followUpAction;
  tail.followUpAction = watchNode();
}

void _wireBumpUnreadOnAllChatSends(FFProject project) {
  _appendBumpUnreadToChatSend(
    project: project,
    pageName: 'TeamChatPage',
    sendBtnKey: 'IconButton_l68mmxn6',
  );
  _appendBumpUnreadToChatSend(
    project: project,
    pageName: 'DirectChatPage',
    sendBtnKey: 'IconButton_y4orjomc',
  );
  // ChatDetailPage = universele chat-pagina (team / direct / staffgroep). Was
  // vergeten — zonder deze wiring werd unreadByUser nooit verhoogd → geen badges.
  _appendBumpUnreadToChatSend(
    project: project,
    pageName: 'ChatDetailPage',
    sendBtnKey: 'IconButton_nnsnoc98',
  );
  _appendBumpUnreadToChatSend(
    project: project,
    pageName: 'GroupChatPage',
    sendBtnKey: 'IconButton_tgwfn8d7',
  );
}

// ─── Chat badge overlay (rood bolletje boven Chats nav-icoon) ────────────────

const String _kChatBadgeOverlayCode = r'''
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '/auth/firebase_auth/auth_util.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/app_state.dart';

class ChatBadgeOverlay extends StatelessWidget {
  const ChatBadgeOverlay({
    super.key,
    this.width,
    this.height,
    this.count,
  });

  final double? width;
  final double? height;
  final int? count;

  @override
  Widget build(BuildContext context) {
    // Self-subscribe to FFAppState so we re-render zonder afhankelijk te zijn
    // van of de parent page `context.watch<FFAppState>()` heeft. Belangrijk:
    // dit overschrijft de `count` param waarde — we lezen altijd live uit
    // AppState.
    final state = context.watch<FFAppState>();
    return _buildBadge(context, state.unreadChatCount);
  }

  Widget _buildBadge(BuildContext context, int liveCount) {
    if (liveCount <= 0) return const SizedBox.shrink();

    final label = liveCount > 99 ? '99+' : '$liveCount';
    final mq = MediaQuery.of(context);
    final screenWidth = mq.size.width;
    // 6 NavBar tabs: Dashboard, Wedstrijden, Rijschema, Bardienst, Chats, Profiel.
    // Chats is index 4 (0-based). Tab center = (idx + 0.5) * tabWidth.
    final tabWidth = screenWidth / 6.0;
    final chatTabCenterX = tabWidth * 4.5;

    return IgnorePointer(
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            left: chatTabCenterX + 4,
            bottom: 4,
            child: Container(
              constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: const Color(0xFFEF4444),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.white, width: 1.5),
                boxShadow: const [
                  BoxShadow(color: Colors.black26, blurRadius: 3, offset: Offset(0, 1)),
                ],
              ),
              alignment: Alignment.center,
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  height: 1.1,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
''';

// ─── Per-conversation unread badge (rode bolletje met N per chat-item) ───────

const String _kConvUnreadBadgeCode = r'''
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import '/auth/firebase_auth/auth_util.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/app_state.dart';

class ConvUnreadBadge extends StatefulWidget {
  const ConvUnreadBadge({
    super.key,
    this.width,
    this.height,
    this.conversationId,
  });

  final double? width;
  final double? height;
  final String? conversationId;

  @override
  State<ConvUnreadBadge> createState() => _ConvUnreadBadgeState();
}

class _ConvUnreadBadgeState extends State<ConvUnreadBadge> {
  StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>? _sub;
  int _count = 0;
  String _watching = '';

  @override
  void initState() {
    super.initState();
    _subscribe();
  }

  @override
  void didUpdateWidget(covariant ConvUnreadBadge oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.conversationId != widget.conversationId) {
      _subscribe();
    }
  }

  void _subscribe() {
    final convId = widget.conversationId ?? '';
    if (convId == _watching) return;
    _sub?.cancel();
    _watching = convId;
    if (convId.isEmpty) {
      if (mounted) setState(() => _count = 0);
      return;
    }
    _sub = FirebaseFirestore.instance
        .collection('chatConversations').doc(convId)
        .snapshots()
        .listen((snap) {
      int c = 0;
      if (snap.exists) {
        final data = snap.data() ?? {};
        final raw = data['unreadByUser'];
        if (raw is Map) {
          final myEmail = FFAppState().userEmail;
          final v = raw[myEmail];
          if (v is int) c = v;
          else if (v is num) c = v.toInt();
        }
      }
      if (mounted) setState(() => _count = c);
    }, onError: (Object _) {});
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Watch FFAppState so dat veranderingen aan userEmail (login wissel) ook
    // herevalueren — _count update via stream gebeurt automatisch maar resync
    // moet wel triggeren bij identity change.
    context.watch<FFAppState>();
    if (_count <= 0) return const SizedBox.shrink();
    final label = _count > 99 ? '99+' : '$_count';
    return Container(
      constraints: const BoxConstraints(minWidth: 22, minHeight: 22),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: const Color(0xFFEF4444),
        borderRadius: BorderRadius.circular(12),
      ),
      alignment: Alignment.center,
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.bold,
          height: 1.1,
        ),
      ),
    );
  }
}
''';

void _ensureConvUnreadBadgeWidget(FFProject project) {
  if (findCustomWidget(project, name: 'ConvUnreadBadge') == null) {
    addCustomWidget(
      project,
      name: 'ConvUnreadBadge',
      description:
          'Rode badge met N ongelezen voor een specifieke conversationId (per-user). Streamt chatConversations/<id>.unreadByUser[myEmail].',
      parameters: [
        FFParameter(
          identifier: FFIdentifier(name: 'conversationId'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      code: _kConvUnreadBadgeCode,
    );
  } else {
    updateCustomWidget(
      project,
      name: 'ConvUnreadBadge',
      code: _kConvUnreadBadgeCode,
    );
  }
}

void _ensureChatBadgeOverlayWidget(FFProject project) {
  if (findCustomWidget(project, name: 'ChatBadgeOverlay') == null) {
    addCustomWidget(
      project,
      name: 'ChatBadgeOverlay',
      description:
          'Rood unread-count bolletje boven het Chats-icoontje van de NavBar (overlay op page body via Stack).',
      parameters: [
        FFParameter(
          identifier: FFIdentifier(name: 'count'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.Integer),
        ),
      ],
      code: _kChatBadgeOverlayCode,
    );
  } else {
    updateCustomWidget(
      project,
      name: 'ChatBadgeOverlay',
      code: _kChatBadgeOverlayCode,
    );
  }
}

// Wraps the page body in a Stack with the badge overlay as the second child.
// Idempotent: skips if the overlay is already mounted on the page.
void _addChatBadgeOverlayToPage(FFProject project, String pageName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;

  if (findDescendants(wc.node, (n) => n.name == 'ChatBadgeOverlay').isNotEmpty) {
    return;
  }

  final widget = findCustomWidget(project, name: 'ChatBadgeOverlay');
  if (widget == null) return;

  final unreadId = _findAppStateFieldId(project, 'unreadChatCount');
  if (unreadId == null) return;

  final bodyChild = getPropertyChild(wc.node, 'body');
  if (bodyChild == null) return;

  final overlay = UI.customWidget(
    widget,
    name: 'ChatBadgeOverlay',
    params: {
      'count': VariableParamValue(varFromAppState(unreadId.deepCopy())),
    },
  );

  // Als de body al een Stack is → gewoon toevoegen. Anders een wrappende
  // Stack bouwen met [originele body, overlay].
  if (bodyChild.type == FFWidgetType.Stack) {
    bodyChild.children.add(overlay);
    return;
  }

  final stack = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Stack,
    name: '${pageName}BodyStack',
    props: FFWidgetProperties(),
    children: [bodyChild, overlay],
  );

  final idx = wc.node.children.indexWhere((n) => n.key == bodyChild.key);
  if (idx >= 0) {
    wc.node.children[idx] = stack;
  } else {
    wc.node.children.add(stack);
  }
  wc.node.childPropertyMap['body'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: stack.key)],
  );
}

void _wireChatBadgeOverlayOnAllMainPages(FFProject project) {
  _ensureChatBadgeOverlayWidget(project);
  _ensureConvUnreadBadgeWidget(project);
  const pages = [
    'DashboardPage',
    'WedstrijdenPage',
    'BardienPage',
    'RijschemaPage',
    'ChatsPage',
    'ProfielPage',
  ];
  for (final p in pages) {
    _addChatBadgeOverlayToPage(project, p);
  }
  // Per-conversation badges in ChatsPage list sections.
  _addConvBadgeToTeamchatTile(project);
  _addConvBadgeToGroupChip(project);
  _addConvBadgeToStaffGroupChip(project);
  _addConvBadgeToDirectMemberChip(project);
  // Teamchat-ingang: dynamische teamkeuze (multi-team) + verbergen zonder team.
  _wireChatsPageTeamchatPicker(project);
}

// Teamchat-ingang op ChatsPage. Vervangt de enkele "Teamchat"-knop door een
// dynamische lijst van teams (uit AppState.availableTeams): bij één team één
// item (zoals voorheen), bij meerdere teams (bv. een ouder met kinderen in
// verschillende teams) toont het ze allemaal — zo zie je de keuze meteen op de
// chat-pagina. Tik op een team → zet currentTeamId/currentTeamName op dat team
// en open de teamchat. De "Teamchat"-header is alleen zichtbaar als er een team
// is. Idempotent: skipt zodra de lijst er al staat.
void _wireChatsPageTeamchatPicker(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final teamIdId     = _findAppStateFieldId(project, 'currentTeamId');
  final teamNameId   = _findAppStateFieldId(project, 'currentTeamName');
  final availTeamsId = _findAppStateFieldId(project, 'availableTeams');
  if (teamIdId == null || teamNameId == null || availTeamsId == null) return;

  // "Teamchat"-header zichtbaar zolang de gebruiker een team heeft.
  final hasTeam = conditionVar(
    varFromAppState(teamIdId.deepCopy()),
    FFCondition_Relation.NOT_EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;

  // Klim van de Teamchat-header-Row naar de container die directe child is van
  // de body-Column.
  final headerRow = findByKey(wc.node, 'Row_mcdmlgr2');
  if (headerRow == null) return;
  FFNode? headerContainer = headerRow;
  for (var i = 0; i < 12; i++) {
    final hit = findParentByKey(wc.node, headerContainer!.key);
    if (hit == null) { headerContainer = null; break; }
    if (hit.parent.props.hasColumn()) { headerContainer = hit.child; break; }
    headerContainer = hit.parent;
  }
  if (headerContainer == null) return;
  final hdr = headerContainer;
  setConditionalVisibility(hdr, variable: hasTeam);

  final colHit = findParentByKey(wc.node, hdr.key);
  if (colHit == null) return;
  final col = colHit.parent;
  final idx = col.children.indexWhere((c) => c.key == hdr.key);
  if (idx < 0) return;

  // Idempotent: lijst al aanwezig → zorg dat shrinkWrap aanstaat en stop.
  // (Een verticale ListView in een scrollbare Column MOET shrinkWrap hebben,
  // anders krijgt-ie onbegrensde hoogte → render-exception → leeg scherm.)
  final existingList =
      findDescendants(wc.node, (n) => n.name == 'TeamchatTeamList').firstOrNull;
  if (existingList != null) {
    final lv = existingList.props.listView.deepCopy();
    lv.shrinkWrapValue = FFBooleanValue(inputValue: true);
    existingList.props.listView = lv;
    // Geef de bestaande tegel ook een achtergrond (knop-uiterlijk).
    final existingTile =
        findDescendants(wc.node, (n) => n.name == 'TeamchatTeamTile').firstOrNull;
    if (existingTile != null) {
      _setContainerColor(
        existingTile,
        FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND)),
      );
    }
    return;
  }

  // Verwijder de oude enkele "Teamchat"-knop (volgende sibling van de header).
  if (idx + 1 < col.children.length) {
    col.children.removeAt(idx + 1);
  }

  // Dynamische teamlijst gebonden aan AppState.availableTeams.
  final teamsVar = varFromAppState(availTeamsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final teamList = UI.listView(
    name: 'TeamchatTeamList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12),
    dynamicSource: DynamicSource(variable: teamsVar, itemName: 'team'),
  );
  // shrinkWrap zodat de verticale ListView in de scrollbare body-Column werkt
  // (anders onbegrensde hoogte → render-exception → leeg scherm).
  final teamLv = teamList.props.listView.deepCopy();
  teamLv.shrinkWrapValue = FFBooleanValue(inputValue: true);
  teamList.props.listView = teamLv;

  final nameText = UI.text('', name: 'TeamchatTeamName', style: UITextStyle.bodyMedium);
  nameText.props.text.textValue =
      FFStringValue(variable: generatorVarField(teamList.key, 'name'));

  final tile = UI.container(
    name: 'TeamchatTeamTile',
    height: 44,
    borderRadius: 8,
    child: nameText,
  );
  // Achtergrond zodat de tegel duidelijk als aantikbare knop oogt.
  _setContainerColor(
    tile,
    FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.SECONDARY_BACKGROUND)),
  );

  // Tap: zet currentTeamId/Name op het gekozen team, dan open TeamChatPage.
  final tap = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          FFLocalStateFieldUpdate(
            fieldIdentifier: teamIdId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(teamList.key, 'id')),
          ),
          FFLocalStateFieldUpdate(
            fieldIdentifier: teamNameId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(teamList.key, 'name')),
          ),
        ],
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
    followUpAction: FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.navigate(
        project,
        pageName: 'TeamChatPage',
        params: {
          'teamId':   VariableParamValue(generatorVarField(teamList.key, 'id')),
          'teamName': VariableParamValue(generatorVarField(teamList.key, 'name')),
        },
      ),
    ),
  );
  Actions.onTapChain(tile, tap);

  teamList.children.add(tile);
  final listContainer = UI.container(name: 'TeamchatTeamListContainer', child: teamList);
  col.children.insert(idx + 1, listContainer);
}

// Plaatst ConvUnreadBadge naast de "Teamchat" header op ChatsPage.
// ConvId = 'team_' + currentTeamId.
void _addConvBadgeToTeamchatTile(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  // De "Teamchat" tekst-Row staat op pad ChatsPage.body[0].children[0].children[0].children[0]
  // (key Row_mcdmlgr2). Voeg badge daar als 2e child.
  final row = findByKey(wc.node, 'Row_mcdmlgr2');
  if (row == null) return;
  if (findDescendants(row, (n) => n.name == 'TeamchatConvUnreadBadge').isNotEmpty) return;

  final widget = findCustomWidget(project, name: 'ConvUnreadBadge');
  if (widget == null) return;

  final teamIdField = _findAppStateFieldId(project, 'currentTeamId');
  if (teamIdField == null) return;

  final convIdVar = codeExpressionVar(
    expression: "'team_' + (t ?? '')",
    arguments: [
      CodeExpressionArg(
        name: 't',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: varFromAppState(teamIdField.deepCopy())),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );

  final badge = UI.customWidget(
    widget,
    name: 'TeamchatConvUnreadBadge',
    params: {
      'conversationId': VariableParamValue(convIdVar),
    },
  );
  row.children.add(badge);
}

// Plaatst een ConvUnreadBadge in elke groep-tile op ChatsPage. ConvId =
// 'group_<groupId>'. Idempotent: skipt als badge er al in zit.
void _addConvBadgeToGroupChip(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final convList = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsList').firstOrNull;
  if (convList == null) return;
  final row = findDescendants(wc.node, (n) => n.name == 'GroupChipRow').firstOrNull;
  if (row == null) return;
  if (findDescendants(row, (n) => n.name == 'GroupConvUnreadBadge').isNotEmpty) return;

  final widget = findCustomWidget(project, name: 'ConvUnreadBadge');
  if (widget == null) return;

  // ConvId via codeExpression: 'group_' + group.id (de Firestore doc id staat
  // niet in een veld; we gebruiken de doc-ref-id via _docField). Eenvoudiger:
  // we lezen 'conversationId' veld op chatGroups (als die bestaat) of fallback
  // op naam (huidige convention in code).
  final groupNameField = findCollectionField(project, collectionName: 'chatGroups', fieldName: 'name');
  if (groupNameField == null) return;
  final nameVar = varFromGeneratorVariable(convList.key)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: groupNameField.identifier.deepCopy(),
      ),
    ));

  final convIdVar = codeExpressionVar(
    expression: "'group_' + (n ?? '')",
    arguments: [
      CodeExpressionArg(
        name: 'n',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: nameVar),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );

  final badge = UI.customWidget(
    widget,
    name: 'GroupConvUnreadBadge',
    params: {
      'conversationId': VariableParamValue(convIdVar),
    },
  );
  // Insert vóór de chevron (laatste child).
  final lastIdx = row.children.length - 1;
  row.children.insert(lastIdx, badge);
}

void _addConvBadgeToStaffGroupChip(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final convList = findDescendants(wc.node, (n) => n.name == 'ChatsStaffGroupsList').firstOrNull;
  if (convList == null) return;
  final row = findDescendants(wc.node, (n) => n.name == 'StaffGroupChipRow').firstOrNull;
  if (row == null) return;
  if (findDescendants(row, (n) => n.name == 'StaffGroupConvUnreadBadge').isNotEmpty) return;

  final widget = findCustomWidget(project, name: 'ConvUnreadBadge');
  if (widget == null) return;

  // staffGroup.id is een StaffGroupItem struct field.
  final idVar = generatorVarField(convList.key, 'id');

  final convIdVar = codeExpressionVar(
    expression: "'staffgroup_' + (i ?? '')",
    arguments: [
      CodeExpressionArg(
        name: 'i',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: idVar),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );

  final badge = UI.customWidget(
    widget,
    name: 'StaffGroupConvUnreadBadge',
    params: {
      'conversationId': VariableParamValue(convIdVar),
    },
  );
  final lastIdx = row.children.length - 1;
  row.children.insert(lastIdx, badge);
}

void _addConvBadgeToDirectMemberChip(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;
  final convList = findDescendants(wc.node, (n) => n.name == 'ChatsDirectMemberList').firstOrNull;
  if (convList == null) return;
  final row = findDescendants(wc.node, (n) => n.name == 'DirectMemberRow').firstOrNull;
  if (row == null) return;
  if (findDescendants(row, (n) => n.name == 'DirectConvUnreadBadge').isNotEmpty) return;

  final widget = findCustomWidget(project, name: 'ConvUnreadBadge');
  if (widget == null) return;

  final teamIdField    = _findAppStateFieldId(project, 'currentTeamId');
  final relatieField   = _findAppStateFieldId(project, 'relatiecode');
  final emailField     = _findAppStateFieldId(project, 'userEmail');
  if (teamIdField == null || relatieField == null || emailField == null) return;

  final externalIdVar = generatorVarField(convList.key, 'externalId');
  final emailVar      = generatorVarField(convList.key, 'email');

  final convIdVar = codeExpressionVar(
    expression: r"() { final my = ((rc ?? '').isNotEmpty ? (rc ?? '') : (me ?? '')); final ot = ((ex ?? '').isNotEmpty ? (ex ?? '') : (em ?? '')); final ids = [my, ot]..sort(); return (t ?? '') + '_' + ids[0] + '_' + ids[1]; }()",
    arguments: [
      CodeExpressionArg(
        name: 't',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: varFromAppState(teamIdField.deepCopy())),
      ),
      CodeExpressionArg(
        name: 'rc',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: varFromAppState(relatieField.deepCopy())),
      ),
      CodeExpressionArg(
        name: 'me',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: varFromAppState(emailField.deepCopy())),
      ),
      CodeExpressionArg(
        name: 'ex',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: externalIdVar),
      ),
      CodeExpressionArg(
        name: 'em',
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        value: FFValue(variable: emailVar),
      ),
    ],
    returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
  );

  final badge = UI.customWidget(
    widget,
    name: 'DirectConvUnreadBadge',
    params: {
      'conversationId': VariableParamValue(convIdVar),
    },
  );
  final lastIdx = row.children.length - 1;
  row.children.insert(lastIdx, badge);
}

// ─── Hamburger menu / App Drawer ─────────────────────────────────────────────

// Builds a fresh Drawer node with:
//   - Header (avatar + userName + userEmail) on club-primary background
//   - ListTiles for Home, Handleiding, Profiel, Bug melden
// Each tile gets an ON_TAP Navigate action via Actions.onTap.
FFNode _buildAppDrawerNode(FFProject project) {
  final userNameId        = _findAppStateFieldId(project, 'userName');
  final userEmailId       = _findAppStateFieldId(project, 'userEmail');
  final profilePhotoUrlId = _findAppStateFieldId(project, 'profilePhotoUrl');

  // ── Header section ─────────────────────────────────────────────────────────
  // Person-icon fallback shown only when no profile photo URL is set.
  final fallbackIcon = UI.icon('person', size: 28, color: UIColor.primaryBackground);
  if (profilePhotoUrlId != null) {
    setConditionalVisibility(
      fallbackIcon,
      variable: conditionVar(
        varFromAppState(profilePhotoUrlId.deepCopy()),
        FFCondition_Relation.EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
      ).variable,
    );
  }

  final avatar = UI.container(
    name: 'DrawerHeaderAvatar',
    width: 56,
    height: 56,
    borderRadius: 28,
    clipContent: true,
    child: fallbackIcon,
  );
  _setContainerColor(
    avatar,
    FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND)),
  );
  // Use a lighter alpha overlay on the primary header
  final avatarBd = avatar.props.container.boxDecoration.deepCopy();
  avatarBd.colorValue = FFColorValue(
    inputValue: FFColor(value: Int64(0x33FFFFFF)),
  );
  avatar.props.container.boxDecoration = avatarBd;

  // CircleImage bound to AppState.profilePhotoUrl, visible when URL is set.
  if (profilePhotoUrlId != null) {
    final urlVar = varFromAppState(profilePhotoUrlId.deepCopy());
    final photoNode = FFNode(
      key: generateRandomAlphaNumericString(),
      type: FFWidgetType.CircleImage,
      name: 'DrawerHeaderPhoto',
      props: FFWidgetProperties(
        image: FFImage(
          type:       FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
          pathValue:  FFStringValue(variable: urlVar.deepCopy()),
          fit:        FFBoxFit.FF_BOX_FIT_COVER,
          cached:     true,
          dimensions: FFDimensions(
            width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 56.0)),
            height: FFDim(pixelsValue: FFDoubleValue(inputValue: 56.0)),
          ),
        ),
      ),
    );
    setConditionalVisibility(
      photoNode,
      variable: conditionVar(
        urlVar.deepCopy(),
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
      ).variable,
    );
    avatar.children.insert(0, photoNode);
  }

  final nameText = UI.text('Naam', name: 'DrawerHeaderName', style: UITextStyle.titleMedium);
  if (userNameId != null) {
    nameText.props.text.textValue =
        FFStringValue(variable: varFromAppState(userNameId.deepCopy()));
  }
  final nameTxt = nameText.props.text.deepCopy();
  nameTxt.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND),
  );
  nameText.props.text = nameTxt;

  final emailText = UI.text('E-mail', name: 'DrawerHeaderEmail', style: UITextStyle.bodySmall);
  if (userEmailId != null) {
    emailText.props.text.textValue =
        FFStringValue(variable: varFromAppState(userEmailId.deepCopy()));
  }
  final emailTxt = emailText.props.text.deepCopy();
  emailTxt.colorValue = FFColorValue(
    inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_BACKGROUND),
  );
  emailText.props.text = emailTxt;

  final headerColumn = UI.column(
    name: 'DrawerHeaderColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    crossAxisAlignment: UICrossAxisAlignment.center,
    mainAxisMin: true,
    spacing: 8,
    padding: UIEdgeInsets.all(5),
    children: [avatar, nameText, emailText],
  );

  final header = UI.container(
    name: 'DrawerHeader',
    width: double.infinity,
    child: headerColumn,
  );
  // Hoogte = 30% van de schermhoogte (zoals in de FlutterFlow-editor ingesteld).
  header.props.container.dimensions.height =
      FFDim(percentOfScreenSizeValue: FFDoubleValue(inputValue: 30.0));
  // Achtergrond = club-primaryColor via color-from-string (AppState), net als de
  // AppBar-branding. Fallback op de theme-PRIMARY-token als het veld ontbreekt.
  final drawerPrimaryId = _findAppStateFieldId(project, 'primaryColor');
  _setContainerColor(
    header,
    drawerPrimaryId != null
        ? colorFromStringVar(varFromAppState(drawerPrimaryId.deepCopy()))
        : FFColorValue(inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY)),
  );

  // ── Menu tiles ─────────────────────────────────────────────────────────────
  FFNode tile(String name, String label, String icon, String pageName) {
    final t = UI.listTile(
      name: name,
      title: label,
      leadingIconName: icon,
    );
    if (project.getWidgetClassByName(pageName) != null) {
      Actions.onTap(
        t,
        Actions.navigate(project, pageName: pageName, replaceRoute: true),
      );
    }
    return t;
  }

  final homeTile = tile('DrawerTileHome', 'Home', 'home', 'DashboardPage');
  final newsTile = tile('DrawerTileNews', 'Nieuws', 'newspaper', 'NewsPage');
  final docsTile = tile('DrawerTileDocs', 'Handleiding', 'menu_book', 'DocumentatiePage');
  final profileTile = tile('DrawerTileProfiel', 'Profiel', 'person', 'ProfielPage');
  final bugTile = tile('DrawerTileBug', 'Bug melden', 'bug_report', 'BugReportPage');

  // ── Footer: clublogo onderaan de drawer ─────────────────────────────────────
  // Een Expanded-spacer duwt het logo naar de onderkant; het logo is een
  // rechthoekige Image (CONTAIN) gebonden aan AppState.clubLogoUrl en alleen
  // zichtbaar als die gevuld is.
  final clubLogoId = _findAppStateFieldId(project, 'clubLogoUrl');
  final List<FFNode> footerChildren = [];
  if (clubLogoId != null) {
    final logoVar = varFromAppState(clubLogoId.deepCopy());
    final footerLogo = FFNode(
      key: generateRandomAlphaNumericString(),
      type: FFWidgetType.Image,
      name: 'DrawerFooterLogo',
      props: FFWidgetProperties(
        image: FFImage(
          type:       FFImage_FFImageType.FF_IMAGE_TYPE_NETWORK,
          pathValue:  FFStringValue(variable: logoVar.deepCopy()),
          fit:        FFBoxFit.FF_BOX_FIT_CONTAIN,
          cached:     true,
          dimensions: FFDimensions(
            width:  FFDim(pixelsValue: FFDoubleValue(inputValue: 120.0)),
            height: FFDim(pixelsValue: FFDoubleValue(inputValue: 64.0)),
          ),
        ),
      ),
    );
    final footerRow = UI.row(
      name: 'DrawerFooterLogoRow',
      mainAxisAlignment: UIMainAxisAlignment.center,
      children: [footerLogo],
    );
    final footerWrap = UI.container(
      name: 'DrawerFooterLogoWrap',
      width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 20),
      child: footerRow,
    );
    setConditionalVisibility(
      footerWrap,
      variable: conditionVar(
        logoVar.deepCopy(),
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
      ).variable,
    );
    footerChildren.add(UI.expanded(UI.container(name: 'DrawerFooterSpacer')));
    footerChildren.add(footerWrap);
  }

  final menuColumn = UI.column(
    name: 'DrawerMenuColumn',
    spacing: 0,
    crossAxisAlignment: UICrossAxisAlignment.stretch,
    children: [
      header,
      homeTile,
      newsTile,
      docsTile,
      profileTile,
      bugTile,
      ...footerChildren,
    ],
  );

  return UI.drawer(name: 'AppDrawer', child: menuColumn);
}

// Attaches the app drawer to a Scaffold page. Force-rebuilds on every push so
// header changes (profile photo, name binding etc.) are picked up. Also flips
// the page's AppBar `defaultBackButtonValue` to true so Flutter's
// automaticallyImplyLeading renders the hamburger icon (NavBar pages have no
// pop-route, so no back arrow appears — the drawer takes precedence).
void _addAppDrawerToPage(FFProject project, String pageName) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;

  // Ensure the AppBar has automaticallyImplyLeading enabled so the hamburger
  // is rendered. Runs on every push so it stays correct even if the AppBar
  // is rebuilt later.
  final appBar = getPropertyChild(wc.node, 'appBar');
  if (appBar != null && appBar.props.hasAppBar()) {
    final appBarCopy = appBar.props.appBar.deepCopy();
    appBarCopy.defaultBackButtonValue = FFBooleanValue(inputValue: true);
    appBar.props.appBar = appBarCopy;
  }

  // Remove any previously attached drawer node(s) so the rebuilt instance with
  // the latest header structure (incl. profile photo) replaces the old one.
  // Copy keys first to avoid concurrent-modification during removeByKey.
  final existingRefs = wc.node.childPropertyMap['drawer'];
  if (existingRefs != null) {
    final keysToRemove = existingRefs.keyRefs.map((r) => r.key).toList();
    wc.node.childPropertyMap.remove('drawer');
    for (final key in keysToRemove) {
      removeByKey(wc.node, key);
    }
  }

  final drawer = _buildAppDrawerNode(project);
  wc.node.children.add(drawer);
  wc.node.childPropertyMap['drawer'] = FFChildrenKeys(
    keyRefs: [FFNodeKeyReference(key: drawer.key)],
  );
}

void _wireAppDrawerOnAllMainPages(FFProject project) {
  const pages = [
    'DashboardPage',
    'WedstrijdenPage',
    'BardienPage',
    'RijschemaPage',
    'ChatsPage',
    'ProfielPage',
  ];
  for (final p in pages) {
    _addAppDrawerToPage(project, p);
  }
}

// Returns the generator variable's DocumentReference.
FFVariable _docRefVar(String listKey) => varFromGeneratorVariable(listKey)
  ..operations.add(FFVariableOperation(
    accessDocumentField: FFAccessDocumentField(
      documentProperty: FFAccessDocumentField_DocumentProperty.REFERENCE,
    ),
  ));

// Appends an OwnActionsRow to a message bubble column with edit, save, cancel,
// and delete buttons. Edit/delete are visible when not editing; save/cancel are
// visible only for the specific message being edited (matched by text content).
// All save/update operations are performed inline inside the ListView so the
// generator variable's DocumentReference is always in scope.
void _addOwnActionsRow(
  FFNode ownCol,
  FFProject project,
  String pageName,
  String scaffoldKey,
  String listKey,
  String textFieldKey,
  { FFVariable? visibilityVar, String collectionName = '' }
) {
  ownCol.children.removeWhere((n) => n.name == 'OwnActionsRow');

  // Builds wait(300) → firestoreQuery → setState(chatMessages) to refresh after delete/save.
  // outputName must be unique per page — use 'postDeleteRefresh' or 'postSaveRefresh'.
  FFActionNode? buildRefresh(String btnKey, {required String outputName}) {
    if (collectionName.isEmpty) return null;
    final coll = findCollection(project, name: collectionName);
    if (coll == null) return null;
    final queryAction = Actions.firestoreQuery(
      collectionIdentifier: coll.identifier.deepCopy(),
      limit: 100,
      singleTimeQuery: true,
    );
    queryAction.outputVariableName = outputName;
    final refreshedVar = varFromActionOutput(
      actionKey: queryAction.key,
      outputName: outputName,
    )..nodeKeyRef = FFNodeKeyReference(key: btnKey);
    final setStateNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.updatePageState(
        project,
        widgetClassName: pageName,
        updates: [StateFieldUpdate.setFromVariable('chatMessages', refreshedVar)],
      ),
    );
    return FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.wait(300),
      followUpAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: queryAction,
        followUpAction: setStateNode,
      ),
    );
  }

  FFVariable textVar() => varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: textFieldKey, name: 'text'),
      ),
    ));

  final editingDocPathId = _findAppStateFieldId(project, 'editingChatDocPath');
  final msgTextId        = _findPageStateFieldId(project, pageName, 'messageText');

  // Condition: not currently editing any message.
  FFVariable notEditingCond() => conditionVar(
    varFromAppState(editingDocPathId!.deepCopy()),
    FFCondition_Relation.EQUAL_TO,
    varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
  ).variable;

  // Condition: currently editing THIS message (text matches stored identifier).
  FFVariable editingThisCond() => conditionVar(
    textVar(),
    FFCondition_Relation.EQUAL_TO,
    varFromAppState(editingDocPathId!.deepCopy()),
  ).variable;

  // ── Delete button (visible when not editing) ───────────────────────────────
  // Soft-delete: zet 'deleted' field op true via firestoreUpdate i.p.v. het
  // document echt verwijderen. UI toont vervolgens "Bericht verwijderd" in
  // een grijze bubble zonder actieknoppen.
  final deleteBtn = UI.iconButton('delete', color: UIColor.secondaryText, name: 'OwnDeleteBtn');
  if (editingDocPathId != null) setConditionalVisibility(deleteBtn, variable: notEditingCond());

  // Zoek het 'deleted' veld in de huidige collectie.
  final deletedFieldEntry = collectionName.isEmpty
      ? null
      : findCollectionField(
          project, collectionName: collectionName, fieldName: 'deleted',
        );
  final FFAction deleteAction;
  if (deletedFieldEntry != null) {
    // Soft-delete via firestoreUpdate
    deleteAction = Actions.firestoreUpdate(
      reference: _docRefVar(listKey),
      fieldUpdates: {
        deletedFieldEntry.identifier.key: FFFieldUpdate(
          fieldIdentifier: deletedFieldEntry.identifier.deepCopy(),
          variable: varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
        ),
      },
    );
  } else {
    // Fallback: hard-delete als 'deleted' veld nog niet bestaat.
    deleteAction = Actions.firestoreDelete(reference: _docRefVar(listKey));
  }
  final deleteFirestoreNode = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: deleteAction,
  );
  final deleteRefresh = buildRefresh(deleteBtn.key, outputName: 'postDeleteRefresh');
  if (deleteRefresh != null) deleteFirestoreNode.followUpAction = deleteRefresh;
  final deleteChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      alertDialog: FFAlertDialogAction(
        confirmDialog: FFConfirmDialogAction(
          title:       FFValue(inputValue: FFParameterValue(serializedValue: 'Bericht verwijderen')),
          message:     FFValue(inputValue: FFParameterValue(serializedValue: 'Weet je zeker dat je dit bericht wilt verwijderen?')),
          confirmText: FFValue(inputValue: FFParameterValue(serializedValue: 'Verwijderen')),
          dismissText: FFValue(inputValue: FFParameterValue(serializedValue: 'Annuleren')),
        ),
      ),
    ),
    followUpAction: deleteFirestoreNode,
  );
  Actions.addTriggerChain(deleteBtn, FFActionTriggerType.ON_TAP, deleteChain);

  // ── Edit button (visible when not editing) ────────────────────────────────
  final editBtn = UI.iconButton('edit', color: UIColor.secondaryText, name: 'OwnEditBtn');
  if (editingDocPathId != null) setConditionalVisibility(editBtn, variable: notEditingCond());
  // Store message text as identifier in AppState, pre-fill messageText for editing.
  final List<FFAction> editActions = [];
  if (editingDocPathId != null) {
    editActions.add(Actions.updateAppState(project, updates: [
      StateFieldUpdate.setFromVariable('editingChatDocPath', textVar()),
    ]));
  }
  if (msgTextId != null) {
    editActions.add(Actions.updatePageState(project, widgetClassName: pageName, updates: [
      StateFieldUpdate.setFromVariable('messageText', textVar()),
    ]));
  }
  if (editActions.isNotEmpty) {
    Actions.addTriggerChain(editBtn, FFActionTriggerType.ON_TAP, Actions.chain(editActions));
  }

  // ── Save button (visible when editing THIS message) ───────────────────────
  // Uses generator variable REFERENCE directly — inside ListView so always valid.
  final saveBtn = UI.iconButton('check', color: UIColor.secondaryText, name: 'OwnSaveBtn');
  if (editingDocPathId != null) setConditionalVisibility(saveBtn, variable: editingThisCond());
  if (msgTextId != null) {
    final msgTextVar = varFromPageState(msgTextId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: scaffoldKey);
    final List<FFAction> saveActions = [
      Actions.firestoreUpdate(
        reference: _docRefVar(listKey),
        fieldUpdates: {
          textFieldKey: FFFieldUpdate(
            fieldIdentifier: FFIdentifier(key: textFieldKey, name: 'text'),
            variable: msgTextVar,
          ),
        },
      ),
      if (editingDocPathId != null)
        Actions.updateAppState(project, updates: [StateFieldUpdate.clear('editingChatDocPath')]),
      Actions.updatePageState(project, widgetClassName: pageName,
          updates: [StateFieldUpdate.clear('messageText')]),
    ];
    final saveTriggerRoot = Actions.chain(saveActions);
    final saveRefresh = buildRefresh(saveBtn.key, outputName: 'postSaveRefresh');
    if (saveRefresh != null) {
      FFActionNode saveTail = saveTriggerRoot;
      while (saveTail.hasFollowUpAction()) saveTail = saveTail.followUpAction;
      saveTail.followUpAction = saveRefresh;
    }
    Actions.addTriggerChain(saveBtn, FFActionTriggerType.ON_TAP, saveTriggerRoot);
  }

  // ── Cancel button (visible when editing THIS message) ─────────────────────
  final cancelBtn = UI.iconButton('close', color: UIColor.secondaryText, name: 'OwnCancelBtn');
  if (editingDocPathId != null) setConditionalVisibility(cancelBtn, variable: editingThisCond());
  final List<FFAction> cancelActions = [];
  if (editingDocPathId != null) {
    cancelActions.add(Actions.updateAppState(project, updates: [
      StateFieldUpdate.clear('editingChatDocPath'),
    ]));
  }
  if (msgTextId != null) {
    cancelActions.add(Actions.updatePageState(project, widgetClassName: pageName,
        updates: [StateFieldUpdate.clear('messageText')]));
  }
  if (cancelActions.isNotEmpty) {
    Actions.addTriggerChain(cancelBtn, FFActionTriggerType.ON_TAP, Actions.chain(cancelActions));
  }

  // ── Row ────────────────────────────────────────────────────────────────────
  final actionsRow = UI.row(
    name: 'OwnActionsRow',
    mainAxisAlignment: UIMainAxisAlignment.end,
    spacing: 4,
    children: [editBtn, saveBtn, cancelBtn, deleteBtn],
  );
  if (visibilityVar != null) setConditionalVisibility(actionsRow, variable: visibilityVar);
  ownCol.children.add(actionsRow);
}

// Builds a visibility condition: senderField == currentUser (AppState).
// Ensures OwnActionsRow never renders on messages from other users.
FFVariable _ownMessageVisibility(
  String listKey,
  String senderFieldKey,
  String senderFieldName,
  FFIdentifier currentUserStateId,
) {
  final senderVar = varFromGeneratorVariable(listKey)
    ..operations.add(FFVariableOperation(
      accessDocumentField: FFAccessDocumentField(
        fieldIdentifier: FFIdentifier(key: senderFieldKey, name: senderFieldName),
      ),
    ));
  return conditionVar(
    senderVar,
    FFCondition_Relation.EQUAL_TO,
    varFromAppState(currentUserStateId.deepCopy()),
  ).variable;
}

void _addEditDeleteToChatDetailPage(FFProject project) {
  final wc = findPage(project, name: 'ChatDetailPage');
  if (wc == null) return;
  final ownCol = findDescendants(wc.node, (n) => n.name == 'OwnMsgCol').firstOrNull;
  if (ownCol == null) return;
  final userEmailId = _findAppStateFieldId(project, 'userEmail');
  if (userEmailId == null) return;
  _addOwnActionsRow(
    ownCol, project, 'ChatDetailPage', 'Scaffold_pvzwjd3v', 'ListView_ws05qhut', '4ezq3smy',
    visibilityVar: _ownMessageVisibility('ListView_ws05qhut', '9hloj348', 'senderId', userEmailId),
    collectionName: 'chatMessages',
  );
}

void _addEditDeleteToGroupChatPage(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;
  final ownCol = findDescendants(wc.node, (n) => n.name == 'OwnMsgCol').firstOrNull;
  if (ownCol == null) return;
  // No visibilityVar needed: parent OwnBubbleRow already has senderId==userEmail
  // visibility set by _rebuildGroupChatBubbles.
  _addOwnActionsRow(
    ownCol, project, 'GroupChatPage', 'Scaffold_rr8bdjs8', 'ListView_6t0r29c1', 'l46qea8h',
    collectionName: 'groupMessages',
  );
}

void _addEditDeleteToTeamChatPage(FFProject project) {
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) return;
  final ownCol = findDescendants(wc.node, (n) => n.name == 'OwnMsgCol').firstOrNull;
  if (ownCol == null) return;
  // TeamChat stores senderName (not senderId) — compare against AppState.userName.
  final userNameId = _findAppStateFieldId(project, 'userName');
  if (userNameId == null) return;
  _addOwnActionsRow(
    ownCol, project, 'TeamChatPage', 'Scaffold_tc1ashmu', 'ListView_9sebksf4', 'c6cfne01',
    visibilityVar: _ownMessageVisibility('ListView_9sebksf4', '766twey2', 'senderName', userNameId),
    collectionName: 'teamChats',
  );
}

// ─────────────────────────────────────────────────────────────────────────────

// Ensures own-message rows are right-aligned across all chat pages by:
//   1. Setting crossAxisAlignment:stretch on the list-item column so Row children
//      are forced to full width (otherwise collapsed rows ignore mainAxisAlignment).
//   2. Replacing row props with a fresh FFRow(mainAxisAlignment:end/start) — no
//      legacy minSize baggage from the original FlutterFlow-built rows.
void _fixChatDetailBubbleAlignment(FFProject project) {
  void _stretchCol(FFNode col) {
    final c = col.props.hasColumn() ? col.props.column.deepCopy() : FFColumn();
    c.crossAxisAlignment = FFCrossAxisAlignment.cross_axis_stretch;
    col.props.column = c;
  }

  void _alignRow(FFNode row, FFMainAxisAlignment alignment) {
    row.props.row = FFRow(mainAxisAlignment: alignment);
  }

  // ChatDetailPage — fixed keys from initial ensurePage build.
  final chatWc = findPage(project, name: 'ChatDetailPage');
  if (chatWc != null) {
    final itemCol = findByKey(chatWc.node, 'Column_e6dtwbzq');
    if (itemCol != null) _stretchCol(itemCol);
    final otherRow = findByKey(chatWc.node, 'Row_gor1ialq');
    if (otherRow != null) _alignRow(otherRow, FFMainAxisAlignment.main_axis_start);
    final ownRow = findByKey(chatWc.node, 'Row_hwrm4seh');
    if (ownRow != null) _alignRow(ownRow, FFMainAxisAlignment.main_axis_end);
  }

  // DirectChatPage — rebuilt rows named OtherBubbleRow / OwnBubbleRow.
  final directWc = findPage(project, name: 'DirectChatPage');
  if (directWc != null) {
    final itemCol = findByKey(directWc.node, 'Column_hz6ofrah');
    if (itemCol != null) _stretchCol(itemCol);
    final otherRow = findDescendants(directWc.node, (n) => n.name == 'OtherBubbleRow').firstOrNull;
    if (otherRow != null) _alignRow(otherRow, FFMainAxisAlignment.main_axis_start);
    final ownRow = findDescendants(directWc.node, (n) => n.name == 'OwnBubbleRow').firstOrNull;
    if (ownRow != null) _alignRow(ownRow, FFMainAxisAlignment.main_axis_end);
  }

  // TeamChatPage — rebuilt by _fixTeamChatBubbles.
  final teamWc = findPage(project, name: 'TeamChatPage');
  if (teamWc != null) {
    final itemCol = findByKey(teamWc.node, 'Column_thoxlcla');
    if (itemCol != null) _stretchCol(itemCol);
    final otherRow = findDescendants(teamWc.node, (n) => n.name == 'OtherBubbleRow').firstOrNull;
    if (otherRow != null) _alignRow(otherRow, FFMainAxisAlignment.main_axis_start);
    final ownRow = findDescendants(teamWc.node, (n) => n.name == 'OwnBubbleRow').firstOrNull;
    if (ownRow != null) _alignRow(ownRow, FFMainAxisAlignment.main_axis_end);
  }

  // GroupChatPage — rebuilt by _rebuildGroupChatBubbles.
  final groupWc = findPage(project, name: 'GroupChatPage');
  if (groupWc != null) {
    final itemCol = findByKey(groupWc.node, 'Column_dk9brcp8');
    if (itemCol != null) _stretchCol(itemCol);
    final otherRow = findDescendants(groupWc.node, (n) => n.name == 'OtherBubbleRow').firstOrNull;
    if (otherRow != null) _alignRow(otherRow, FFMainAxisAlignment.main_axis_start);
    final ownRow = findDescendants(groupWc.node, (n) => n.name == 'OwnBubbleRow').firstOrNull;
    if (ownRow != null) _alignRow(ownRow, FFMainAxisAlignment.main_axis_end);
  }
}

// Sets bubble container width across all chat pages.
// Appends a scroll-to-bottom action at the tail of the send-button action chain
// for GroupChatPage, TeamChatPage, and DirectChatPage.
// ChatDetailPage already handles this in _wireRefreshAfterSend.
void _addScrollToBottomAfterSend(FFProject project) {
  const configs = [
    (page: 'ChatDetailPage', btnKey: 'IconButton_nnsnoc98', listKey: 'ListView_ws05qhut'),
    (page: 'GroupChatPage',  btnKey: 'IconButton_tgwfn8d7', listKey: 'ListView_6t0r29c1'),
    (page: 'TeamChatPage',   btnKey: 'IconButton_l68mmxn6',  listKey: 'ListView_9sebksf4'),
    (page: 'DirectChatPage', btnKey: 'IconButton_y4orjomc',  listKey: 'ListView_z624qee2'),
  ];

  for (final cfg in configs) {
    final wc = findPage(project, name: cfg.page);
    if (wc == null) continue;

    final sendBtn = findByKey(wc.node, cfg.btnKey);
    if (sendBtn == null) continue;

    final tapIdx = sendBtn.triggerActions.indexWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    if (tapIdx < 0) continue;
    final tap = sendBtn.triggerActions[tapIdx];
    if (!tap.hasRootAction()) continue;

    final root = tap.rootAction;
    if (!root.hasConditionActions()) continue;
    if (root.conditionActions.trueActions.isEmpty) continue;
    final trueEntry = root.conditionActions.trueActions.first;
    if (!trueEntry.hasTrueAction()) continue;

    bool hasScroll(FFActionNode n) {
      if (n.hasAction() &&
          n.action.hasScrollToPercentage() &&
          n.action.scrollToPercentage.scrollableNodeKeyRef.key == cfg.listKey) return true;
      if (n.hasFollowUpAction()) return hasScroll(n.followUpAction);
      return false;
    }
    if (hasScroll(tap.rootAction)) continue;

    FFActionNode tail = trueEntry.trueAction;
    while (tail.hasFollowUpAction()) tail = tail.followUpAction;

    tail.followUpAction = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.scrollTo(widgetKey: cfg.listKey, percentage: 1.0, durationMillis: 150),
    );
  }
}

// Voegt een Actions.clearTextField toe aan de send-button keten van elke chat pagina.
// De bestaande SetState.clear/localStateUpdate wist de page state, maar de TextField
// controller behoudt soms zijn waarde — clearTextField reset de controller direct.
// Plaatst de clear-actie meteen NA de Firestore create (vóór wait+refresh).
// Idempotent: detecteert bestaande clearTextFieldAction op de juiste field.
void _addClearTextFieldToAllChatSends(FFProject project) {
  const configs = [
    (page: 'ChatDetailPage', sendBtnKey: 'IconButton_nnsnoc98', fieldName: 'ChatMessageField'),
    (page: 'GroupChatPage',  sendBtnKey: 'IconButton_tgwfn8d7', fieldName: 'GroupMessageField'),
    (page: 'TeamChatPage',   sendBtnKey: 'IconButton_l68mmxn6', fieldName: 'MessageField'),
    (page: 'DirectChatPage', sendBtnKey: 'IconButton_y4orjomc', fieldName: 'DirectMessageField'),
  ];

  for (final cfg in configs) {
    final wc = findPage(project, name: cfg.page);
    if (wc == null) continue;

    final tf = findDescendants(wc.node, (n) => n.name == cfg.fieldName).firstOrNull;
    if (tf == null) continue;

    final sendBtn = findByKey(wc.node, cfg.sendBtnKey);
    if (sendBtn == null) continue;

    final tapIdx = sendBtn.triggerActions.indexWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    if (tapIdx < 0) continue;
    final tap = sendBtn.triggerActions[tapIdx];
    if (!tap.hasRootAction()) continue;
    if (!tap.rootAction.hasConditionActions()) continue;
    if (tap.rootAction.conditionActions.trueActions.isEmpty) continue;
    final trueEntry = tap.rootAction.conditionActions.trueActions.first;
    if (!trueEntry.hasTrueAction()) continue;

    // Idempotent: skip if a clearTextFieldAction for this field key already exists.
    bool _hasClear(FFActionNode n) {
      if (n.hasAction() && n.action.hasClearTextFieldAction()) {
        for (final p in n.action.clearTextFieldAction.nodeKeyPaths) {
          for (final r in p.keyPath) {
            if (r.key == tf.key) return true;
          }
        }
      }
      if (n.hasFollowUpAction() && _hasClear(n.followUpAction)) return true;
      return false;
    }
    if (_hasClear(tap.rootAction)) continue;

    // Build the clear action node.
    final clearNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.clearTextField(widgetKey: tf.key),
    );

    // Vind de eerste actie in de true-branche (meestal Firestore create) en
    // voeg de clear-actie in als directe followUp.
    final firstAction = trueEntry.trueAction;
    if (firstAction.hasFollowUpAction()) {
      clearNode.followUpAction = firstAction.followUpAction;
    }
    firstAction.followUpAction = clearNode;
  }
}

// Removes OtherBubbleRow/OwnBubbleRow Row wrappers from all chat page item columns.
// After removal, bubble Containers sit directly in the item Column which has
// crossAxisAlignment:stretch (set by _fixChatDetailBubbleAlignment), so they fill
// the full width automatically. Visibility conditions are copied from Row to bubble.
// OwnMsgCol gets crossAxisAlignment:end so own-message text is right-aligned.
void _removeBubbleRowWrappers(FFProject project) {
  const configs = [
    (page: 'ChatDetailPage', colKey: 'Column_e6dtwbzq'),
    (page: 'DirectChatPage', colKey: 'Column_hz6ofrah'),
    (page: 'TeamChatPage',   colKey: 'Column_thoxlcla'),
    (page: 'GroupChatPage',  colKey: 'Column_dk9brcp8'),
  ];

  for (final cfg in configs) {
    final wc = findPage(project, name: cfg.page);
    if (wc == null) continue;
    final itemCol = findByKey(wc.node, cfg.colKey);
    if (itemCol == null) continue;

    final newChildren = <FFNode>[];
    for (final child in itemCol.children) {
      if (child.name == 'OtherBubbleRow' || child.name == 'OwnBubbleRow') {
        if (child.children.isEmpty) { newChildren.add(child); continue; }
        final bubble = child.children.first;
        // Copy visibility condition from Row → bubble.
        if (child.props.hasVisibility() && child.props.visibility.hasVisibleValue()) {
          final vis = bubble.props.visibility.deepCopy();
          vis.visibleValue = child.props.visibility.visibleValue.deepCopy();
          bubble.props.visibility = vis;
        }
        // Own messages: keep crossAxisAlignment start (left-aligned text inside bubble).
        // No extra column changes needed; padding on the container provides the spacing.
        newChildren.add(bubble);
      } else {
        newChildren.add(child);
      }
    }
    itemCol.children..clear()..addAll(newChildren);
  }
}

// Sets bubble container width to fill available space and ensures 16px inner padding.
// After _removeBubbleRowWrappers the bubbles are direct children of the stretched
// item Column, so double.infinity = fill the column's full width.
void _setChatBubbleWidths(FFProject project) {
  // Temporary container used solely to get the serialized 16px padding value.
  final _padRef = UI.container(padding: UIEdgeInsets.all(16));

  void applyStyle(FFNode node) {
    // Width: fill available.
    final c = node.props.container.deepCopy();
    final dims = c.hasDimensions() ? c.dimensions.deepCopy() : FFDimensions();
    dims.width = FFDim(pixelsValue: FFDoubleValue(inputValue: double.infinity));
    c.dimensions = dims;
    node.props.container = c;
    // Padding: 16px on all sides so text never touches bubble edges.
    node.props.padding = _padRef.props.padding.deepCopy();
  }

  for (final pageName in ['TeamChatPage', 'DirectChatPage', 'GroupChatPage', 'ChatDetailPage']) {
    final wc = findPage(project, name: pageName);
    if (wc == null) continue;
    for (final node in findDescendants(wc.node, (n) => n.name == 'OtherBubble' || n.name == 'OwnBubble')) {
      applyStyle(node);
    }
  }
}

// Voegt 5px padding toe aan alle OwnMsgCol en OtherMsgCol nodes op de chat-pagina's.
// Zorgt voor wat ademruimte tussen de tekst en de bubble-rand.
void _addChatMsgColPadding(FFProject project) {
  final _padRef = UI.container(padding: UIEdgeInsets.all(5));

  for (final pageName in ['TeamChatPage', 'DirectChatPage', 'GroupChatPage', 'ChatDetailPage']) {
    final wc = findPage(project, name: pageName);
    if (wc == null) continue;
    for (final node in findDescendants(wc.node,
        (n) => n.name == 'OwnMsgCol' || n.name == 'OtherMsgCol')) {
      node.props.padding = _padRef.props.padding.deepCopy();
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────

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

// ─── Trainingen: custom actions (ophalen + af-/aanmelden) ────────────────────

const _kTrainingsActionHeader = '''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:http/http.dart' as http;
import 'dart:convert';

const _kApiBase = 'https://voetbalplanner.nubix.nl/api/v1';

// Herlaadt FFAppState().trainings (dezelfde lijst als het dashboard). Wordt na
// af-/aanmelden aangeroepen zodat de status/aantallen direct kloppen.
Future<void> _refreshTrainings() async {
  final token  = FFAppState().authToken;
  final teamId = FFAppState().currentTeamId;
  if (token.isEmpty || teamId.isEmpty) return;
  try {
    final uri  = Uri.parse('\$_kApiBase/trainings?team_id=\$teamId&days=21&limit=2');
    final resp = await http.get(uri, headers: {
      'Authorization': 'Bearer \$token',
      'Accept': 'application/json',
    });
    if (resp.statusCode != 200) return;
    final decoded = jsonDecode(resp.body);
    final items = <TrainingItemStruct>[];
    if (decoded is List) {
      for (final m in decoded) {
        final t = TrainingItemStruct.maybeFromMap(m);
        if (t != null) items.add(t);
      }
    }
    FFAppState().update(() => FFAppState().trainings = items);
  } catch (_) {}
}
''';

const _kGetTrainingsCode = '''
$_kTrainingsActionHeader

Future<bool> getTrainings() async {
  final token  = FFAppState().authToken;
  final teamId = FFAppState().currentTeamId;
  if (token.isEmpty || teamId.isEmpty) {
    FFAppState().update(() => FFAppState().trainings = []);
    return false;
  }
  try {
    final uri  = Uri.parse('\$_kApiBase/trainings?team_id=\$teamId&days=21');
    final resp = await http.get(uri, headers: {
      'Authorization': 'Bearer \$token',
      'Accept': 'application/json',
    });
    if (resp.statusCode != 200) return false;
    final decoded = jsonDecode(resp.body);
    final items = <TrainingItemStruct>[];
    if (decoded is List) {
      for (final m in decoded) {
        final t = TrainingItemStruct.maybeFromMap(m);
        if (t != null) items.add(t);
      }
    }
    // Alleen de eerstvolgende 2 trainingen op het dashboard (lijst is al op
    // datum/tijd gesorteerd door de backend).
    final next = items.take(2).toList();
    // update() i.p.v. plain assignment: de FFAppState-setter notificeert niet,
    // dus zonder update() herbouwt het dashboard (context.watch) niet.
    FFAppState().update(() => FFAppState().trainings = next);
    return true;
  } catch (e) {
    debugPrint('[GetTrainings] \$e');
    return false;
  }
}
''';

const _kAfmeldenTrainingCode = '''
$_kTrainingsActionHeader

Future<bool> afmeldenTraining() async {
  final token  = FFAppState().authToken;
  final sid    = FFAppState().pendingTrainingScheduleId;
  final date   = FFAppState().pendingTrainingDate;
  final reason = FFAppState().pendingAfmeldReason;
  if (token.isEmpty || sid.isEmpty || date.isEmpty) return false;
  try {
    final uri  = Uri.parse('\$_kApiBase/trainings/\$sid/\$date/afmelden');
    final resp = await http.post(uri,
      headers: {
        'Authorization': 'Bearer \$token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'reason': reason}));
    final ok = resp.statusCode == 200;
    if (ok) await _refreshTrainings();
    return ok;
  } catch (e) {
    debugPrint('[AfmeldenTraining] \$e');
    return false;
  }
}
''';

const _kAanmeldenTrainingCode = '''
$_kTrainingsActionHeader

Future<bool> aanmeldenTraining() async {
  final token = FFAppState().authToken;
  final sid   = FFAppState().pendingTrainingScheduleId;
  final date  = FFAppState().pendingTrainingDate;
  if (token.isEmpty || sid.isEmpty || date.isEmpty) return false;
  try {
    final uri  = Uri.parse('\$_kApiBase/trainings/\$sid/\$date/aanmelden');
    final resp = await http.post(uri, headers: {
      'Authorization': 'Bearer \$token',
      'Accept': 'application/json',
    });
    final ok = resp.statusCode == 200;
    if (ok) await _refreshTrainings();
    return ok;
  } catch (e) {
    debugPrint('[AanmeldenTraining] \$e');
    return false;
  }
}
''';

const _kAfmeldenMatchCode = '''
$_kTrainingsActionHeader

Future<bool> afmeldenMatch() async {
  final token  = FFAppState().authToken;
  final mid    = FFAppState().pendingMatchId;
  final reason = FFAppState().pendingAfmeldReason;
  if (token.isEmpty || mid.isEmpty) return false;
  try {
    final uri  = Uri.parse('\$_kApiBase/matches/\$mid/afmelden');
    final resp = await http.post(uri,
      headers: {
        'Authorization': 'Bearer \$token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'reason': reason}));
    return resp.statusCode == 200;
  } catch (e) {
    debugPrint('[AfmeldenMatch] \$e');
    return false;
  }
}
''';

const _kAanmeldenMatchCode = '''
$_kTrainingsActionHeader

Future<bool> aanmeldenMatch() async {
  final token = FFAppState().authToken;
  final mid   = FFAppState().pendingMatchId;
  if (token.isEmpty || mid.isEmpty) return false;
  try {
    final uri  = Uri.parse('\$_kApiBase/matches/\$mid/aanmelden');
    final resp = await http.post(uri, headers: {
      'Authorization': 'Bearer \$token',
      'Accept': 'application/json',
    });
    return resp.statusCode == 200;
  } catch (e) {
    debugPrint('[AanmeldenMatch] \$e');
    return false;
  }
}
''';

void _addTrainingsCustomActions(FFProject project) {
  FFParameter boolReturn() => FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      );

  void ensure(String name, String description, String code) {
    if (findCustomAction(project, name: name) == null) {
      addCustomAction(
        project,
        name: name,
        description: description,
        arguments: const [],
        returnParameter: boolReturn(),
        includeContext: false,
        code: code,
      );
    } else {
      updateCustomAction(
        project,
        name: name,
        code: code,
        arguments: const [],
        includeContext: false,
      );
    }
  }

  ensure('GetTrainings', 'Haalt de komende trainingen voor het huidige team op in AppState.trainings.', _kGetTrainingsCode);
  ensure('AfmeldenTraining', 'Meldt het ingelogde lid af voor een training (pending* AppState-velden + reden).', _kAfmeldenTrainingCode);
  ensure('AanmeldenTraining', 'Meldt het ingelogde lid weer aan voor een training.', _kAanmeldenTrainingCode);
  ensure('AfmeldenMatch', 'Meldt het ingelogde lid af voor een wedstrijd (pendingMatchId + reden).', _kAfmeldenMatchCode);
  ensure('AanmeldenMatch', 'Meldt het ingelogde lid weer aan voor een wedstrijd.', _kAanmeldenMatchCode);

  try { addPubDependency(project, name: 'http', version: '^1.2.0'); } catch (_) {}
}

// AppState-field 'trainings' = List<TrainingItem>. Idempotent (skip als aanwezig).
void _ensureTrainingsAppStateField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'trainings',
  )) return;
  final struct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'TrainingItem', orElse: () => null);
  if (struct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(name: 'trainings', key: generateRandomAlphaNumericString()),
    dataType: dataStructType(struct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// AppState 'matchGoals' = List<GoalItem>, gevuld door GetMatchGoals (scorebeheer).
void _ensureMatchGoalsAppStateField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'matchGoals',
  )) return;
  final struct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'GoalItem', orElse: () => null);
  if (struct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(name: 'matchGoals', key: generateRandomAlphaNumericString()),
    dataType: dataStructType(struct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// AppState 'scoreTeamMembers' = List<SwapMember>, gevuld door GetTeamMembers op de
// wedstrijddetail (tikbare maker-keuze).
void _ensureScoreTeamMembersField(FFProject project) {
  if (project.appState.fields.any(
    (f) => f.parameter.identifier.name == 'scoreTeamMembers',
  )) return;
  final struct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'SwapMember', orElse: () => null);
  if (struct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(name: 'scoreTeamMembers', key: generateRandomAlphaNumericString()),
    dataType: dataStructType(struct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// Voegt de telling-velden (aangemeld/afgemeld, string) toe aan de bestaande
// TrainingItem-struct. Raw, idempotent — er is geen addDataStructField-helper en
// app.struct/ensure botst op een gewijzigde payload.
void _ensureTrainingItemCountFields(FFProject project) {
  final ds = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'TrainingItem', orElse: () => null);
  if (ds == null) return;
  for (final fieldName in const ['aangemeld', 'afgemeld', 'dressing_room']) {
    if (ds.fields.any((f) => f.identifier.name == fieldName)) continue;
    ds.fields.add(FFParameter(
      identifier: FFIdentifier(name: fieldName, key: generateRandomAlphaNumericString()),
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    ));
  }
}

// Native FF API-endpoint voor trainingen (werkt in testmode, i.t.t. een custom
// http-actie). Response -> List<TrainingItem>. limit=2 voor het dashboard.
void _addGetTrainingsEndpoint(FFProject project) {
  const groupName    = 'VoetbalPlannerAPI';
  const endpointName = 'GetTrainingsList';

  if (findApiEndpoint(project, name: endpointName, groupName: groupName) == null) {
    if (findApiGroup(project, name: groupName) == null) return;
    addEndpointToGroup(
      project,
      groupName:                groupName,
      name:                     endpointName,
      url:                      '/trainings?team_id=[teamId]&days=21&limit=2',
      method:                   FFApiEndpoint_CallType.GET,
      bodyType:                 FFApiEndpoint_BodyType.NONE,
      variables: {
        'token':  FFDataTypeV2(scalarType: FFBaseDataType.String),
        'teamId': FFDataTypeV2(scalarType: FFBaseDataType.String),
      },
      headers:                  ['Authorization: Bearer [token]'],
      responseDataStructName:   'TrainingItem',
      responseDataStructIsList: true,
    );
  } else {
    updateApiEndpoint(
      project,
      name:                     endpointName,
      groupName:                groupName,
      responseDataStructName:   'TrainingItem',
      responseDataStructIsList: true,
    );
  }
}

// Native FF POST-endpoints voor af-/aanmelden (training + wedstrijd). Native i.p.v.
// een custom http-actie omdat die laatste in FF-test/run-mode op browser-CORS
// stuit; native endpoints worden server-side geproxied.
void _addAfmeldEndpoints(FFProject project) {
  final group = findApiGroup(project, name: 'VoetbalPlannerAPI');
  if (group == null) return;

  FFApiValue mkVar(String name) => FFApiValue(
        identifier: FFIdentifier(name: name, key: generateRandomAlphaNumericString()),
        type: FFBaseDataType.String,
      );

  // FF interpoleert [var] alleen in de URL (niet in de JSON-body), dus de reden
  // gaat als query-param mee. Laravel's validate() leest query-params ook.
  void ensure(String name, String url, List<String> varNames) {
    final existing = findApiEndpoint(project, name: name, groupName: 'VoetbalPlannerAPI');
    if (existing != null) {
      existing.url = url;
      existing.body = '';
      existing.bodyType = FFApiEndpoint_BodyType.NONE;
      for (final vn in varNames) {
        if (!existing.variables.any((v) => v.hasIdentifier() && v.identifier.name == vn)) {
          existing.variables.add(mkVar(vn));
        }
      }
      return;
    }
    group.endpoints.add(FFApiEndpoint(
      identifier: FFIdentifier(name: name, key: generateRandomAlphaNumericString()),
      url: url,
      callType: FFApiEndpoint_CallType.POST,
      bodyType: FFApiEndpoint_BodyType.NONE,
      body: '',
      variables: varNames.map(mkVar).toList(),
      headers: ['Authorization: Bearer [token]'],
      groupIdentifier: group.identifier.deepCopy(),
    ));
  }

  ensure('AfmeldenTrainingApi', '/trainings/[scheduleId]/[date]/afmelden?reason=[reason]',
      ['token', 'scheduleId', 'date', 'reason']);
  ensure('AanmeldenTrainingApi', '/trainings/[scheduleId]/[date]/aanmelden',
      ['token', 'scheduleId', 'date']);
  ensure('AfmeldenMatchApi', '/matches/[matchId]/afmelden?reason=[reason]',
      ['token', 'matchId', 'reason']);
  ensure('AanmeldenMatchApi', '/matches/[matchId]/aanmelden',
      ['token', 'matchId']);
}

// Native endpoints voor score-beheer (coach): doelpunten ophalen (struct-list),
// toevoegen (POST met query-params — FF interpoleert geen body-vars) en
// verwijderen (DELETE).
void _addScoreEndpoints(FFProject project) {
  bool has(String n) => findApiEndpoint(project, name: n, groupName: 'VoetbalPlannerAPI') != null;
  FFDataTypeV2 str() => FFDataTypeV2(scalarType: FFBaseDataType.String);

  if (!has('GetMatchGoals')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'GetMatchGoals',
      url: '/matches/[matchId]/goals',
      method: FFApiEndpoint_CallType.GET,
      variables: {'token': str(), 'matchId': str()},
      headers: ['Authorization: Bearer [token]'],
      responseDataStructName: 'GoalItem',
      responseDataStructIsList: true,
    );
  }
  // Levert MatchGoal-structs (zoals de bestaande Doelpunten-tab _model.goals gebruikt).
  if (!has('GetMatchGoalsList')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'GetMatchGoalsList',
      url: '/matches/[matchId]/goals',
      method: FFApiEndpoint_CallType.GET,
      variables: {'token': str(), 'matchId': str()},
      headers: ['Authorization: Bearer [token]'],
      responseDataStructName: 'MatchGoal',
      responseDataStructIsList: true,
    );
  }
  // Maker via naam (query-param). Vers endpoint (de oude AddGoal met scorer_id
  // niet muteren — variables.clear brak de structuur).
  if (!has('AddGoalV2')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'AddGoalV2',
      url: '/matches/[matchId]/goals?scorer_name=[scorerName]&minute=[minute]',
      method: FFApiEndpoint_CallType.POST,
      variables: {'token': str(), 'matchId': str(), 'scorerName': str(), 'minute': str()},
      headers: ['Authorization: Bearer [token]'],
    );
  }
  if (!has('DeleteGoal')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'DeleteGoal',
      url: '/matches/[matchId]/goals/[goalId]',
      method: FFApiEndpoint_CallType.DELETE,
      variables: {'token': str(), 'matchId': str(), 'goalId': str()},
      headers: ['Authorization: Bearer [token]'],
    );
  }
  if (!has('DeleteLastGoal')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'DeleteLastGoal',
      url: '/matches/[matchId]/goals/delete-last',
      method: FFApiEndpoint_CallType.POST,
      variables: {'token': str(), 'matchId': str()},
      headers: ['Authorization: Bearer [token]'],
    );
  }
  // Spelerlijst voor de doelpunt-maker: álle teamleden INCLUSIEF de ingelogde
  // gebruiker (?include_self=1). Het gedeelde GetTeamMembers sluit jezelf uit
  // (bedoeld voor swap/chat: niet met jezelf), waardoor je in de maker-keuze
  // niet iedereen zag. Zelfde SwapMember-vorm, aparte URL.
  if (!has('GetScorerMembers')) {
    addEndpointToGroup(
      project,
      groupName: 'VoetbalPlannerAPI',
      name: 'GetScorerMembers',
      url: '/teams/[teamId]/members?include_self=1',
      method: FFApiEndpoint_CallType.GET,
      variables: {'teamId': str()},
      headers: ['Authorization: Bearer [bearerToken]'],
      responseDataStructName: 'SwapMember',
      responseDataStructIsList: true,
    );
  }
}

// ─── Gastspeler uitnodigen ────────────────────────────────────────────────────

// Endpoints + GuestInvitation-struct voor het uitnodigen van gastspelers.
void _addGuestInviteEndpoints(FFProject project) {
  const groupName = 'VoetbalPlannerAPI';
  bool has(String n) => findApiEndpoint(project, name: n, groupName: groupName) != null;
  FFDataTypeV2 str() => FFDataTypeV2(scalarType: FFBaseDataType.String);

  if (findDataStruct(project, name: 'GuestInvitation') == null) {
    addDataStruct(
      project,
      name: 'GuestInvitation',
      description: 'Gastspeler-uitnodiging voor een wedstrijd (weergavevelden voor de app).',
      fields: [
        structField('id',            stringType, description: 'Uitnodiging ID'),
        structField('matchId',       stringType, description: 'Wedstrijd ID'),
        structField('opponent',      stringType, description: 'Tegenstander'),
        structField('opponentLogo',  stringType, description: 'Logo tegenstander (URL)'),
        structField('matchDatetime', stringType, description: 'Datum + tijd'),
        structField('location',      stringType, description: 'Locatie'),
        structField('isHome',        stringType, description: "'true' bij thuiswedstrijd"),
        structField('teamName',      stringType, description: 'Naam van het team'),
        structField('invitedByName', stringType, description: 'Naam van de uitnodigende coach'),
      ],
    );
  }

  // POST via query-params (FF interpoleert geen body-variabelen).
  const inviteUrl = '/matches/[matchId]/guest-invite?teamId=[teamId]&memberId=[memberId]';
  if (has('InviteGuestToMatch')) {
    updateApiEndpoint(project, name: 'InviteGuestToMatch', groupName: groupName,
        url: inviteUrl, method: FFApiEndpoint_CallType.POST,
        bodyType: FFApiEndpoint_BodyType.NONE, body: '');
  } else {
    addEndpointToGroup(project, groupName: groupName, name: 'InviteGuestToMatch',
        url: inviteUrl, method: FFApiEndpoint_CallType.POST, bodyType: FFApiEndpoint_BodyType.NONE,
        variables: {'matchId': str(), 'teamId': str(), 'memberId': str()},
        headers: ['Authorization: Bearer [bearerToken]']);
  }

  // POST /matches/[matchId]/vlagger?memberId=.. — coach kiest de vlagger.
  const vlaggerUrl = '/matches/[matchId]/vlagger?memberId=[memberId]';
  if (has('SetMatchFlagger')) {
    updateApiEndpoint(project, name: 'SetMatchFlagger', groupName: groupName,
        url: vlaggerUrl, method: FFApiEndpoint_CallType.POST,
        bodyType: FFApiEndpoint_BodyType.NONE, body: '');
  } else {
    addEndpointToGroup(project, groupName: groupName, name: 'SetMatchFlagger',
        url: vlaggerUrl, method: FFApiEndpoint_CallType.POST, bodyType: FFApiEndpoint_BodyType.NONE,
        variables: {'matchId': str(), 'memberId': str()},
        headers: ['Authorization: Bearer [bearerToken]']);
  }

  if (!has('GetGuestInviteTeams')) {
    addEndpointToGroup(project, groupName: groupName, name: 'GetGuestInviteTeams',
        url: '/guest-invite/teams', method: FFApiEndpoint_CallType.GET,
        bodyType: FFApiEndpoint_BodyType.NONE, variables: {},
        headers: ['Authorization: Bearer [bearerToken]'],
        responseDataStructName: 'TeamOption', responseDataStructIsList: true);
  }

  if (!has('GetMyGuestInvitations')) {
    addEndpointToGroup(project, groupName: groupName, name: 'GetMyGuestInvitations',
        url: '/guest-invitations', method: FFApiEndpoint_CallType.GET,
        bodyType: FFApiEndpoint_BodyType.NONE, variables: {},
        headers: ['Authorization: Bearer [bearerToken]'],
        responseDataStructName: 'GuestInvitation', responseDataStructIsList: true);
  } else {
    updateApiEndpoint(project, name: 'GetMyGuestInvitations', groupName: groupName,
        responseDataStructName: 'GuestInvitation', responseDataStructIsList: true);
  }

  // Nu de GuestInvitation-struct bestaat: het app-state lijstveld aanmaken.
  _ensureGuestInvitationsField(project);
}

// AppState 'guestInvitations' = List<GuestInvitation> (dashboard-uitnodigingen).
void _ensureGuestInvitationsField(FFProject project) {
  if (project.appState.fields.any((f) => f.parameter.identifier.name == 'guestInvitations')) return;
  final struct = project.backend.dataSchemaConfig.dataStructs
      .cast<FFDataStruct?>()
      .firstWhere((s) => s?.identifier.name == 'GuestInvitation', orElse: () => null);
  if (struct == null) return;
  final param = FFParameter(
    identifier: FFIdentifier(name: 'guestInvitations', key: generateRandomAlphaNumericString()),
    dataType: dataStructType(struct.identifier.deepCopy()),
  );
  param.isList = true;
  project.appState.fields.add(FFAppStateField(parameter: param));
}

// Coach-sectie op de WedstrijdDetailPage: kies een club-team en dan een speler
// om als gastspeler uit te nodigen. Alleen zichtbaar met beheerrechten
// (matchMagOpstelling == 'true'). Rebuild elke push.
void _addWedstrijdGuestInviteSection(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;

  FFVariable? stateVar(String name) {
    final f = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((x) => x?.parameter.identifier.name == name, orElse: () => null);
    if (f == null) return null;
    return varFromPageState(f.parameter.identifier.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  }

  final magVar          = stateVar('matchMagOpstelling');
  final inviteTeamsId   = _findPageStateFieldId(project, 'WedstrijdDetailPage', 'inviteTeams');
  final inviteMembersId = _findPageStateFieldId(project, 'WedstrijdDetailPage', 'inviteGuestMembers');
  final authTokenId     = _findAppStateFieldId(project, 'authToken');
  final matchIdParam    = wc.params.values.cast<FFParameter?>().firstWhere(
      (p) => p?.hasIdentifier() == true && p?.identifier.name == 'matchId', orElse: () => null);
  final hasInviteEp  = findApiEndpoint(project, name: 'InviteGuestToMatch', groupName: 'VoetbalPlannerAPI') != null;
  final hasTeamsEp   = findApiEndpoint(project, name: 'GetGuestInviteTeams', groupName: 'VoetbalPlannerAPI') != null;
  final hasMembersEp = findApiEndpoint(project, name: 'GetScorerMembers', groupName: 'VoetbalPlannerAPI') != null;
  if (magVar == null || inviteTeamsId == null || inviteMembersId == null ||
      authTokenId == null || matchIdParam == null || !hasInviteEp || !hasTeamsEp || !hasMembersEp) {
    return;
  }

  // 1. onLoad: GetGuestInviteTeams -> inviteTeams (idempotent).
  bool hasTeamsLoad(FFActionNode n) {
    if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
        n.action.database.apiCall.hasEndpointIdentifier() &&
        n.action.database.apiCall.endpointIdentifier.name == 'GetGuestInviteTeams') return true;
    return n.hasFollowUpAction() && hasTeamsLoad(n.followUpAction);
  }
  if (!wc.node.triggerActions.any((t) => t.hasRootAction() && hasTeamsLoad(t.rootAction))) {
    // Bij elke page-load: reset de dialoog-weergave, zet dialogMatchId uit de
    // page-param en laad de club-teams -> AppState.dialogTeams (de coach-dialoog
    // leest app-state). matchActionMode='' houdt de oude inline secties verborgen.
    final resetNode = FFActionNode(
      key: generateRandomAlphaNumericString(),
      action: Actions.updateAppState(project, updates: [
        StateFieldUpdate.set('matchActionMode', ''),
        StateFieldUpdate.set('dialogView', 'menu'),
        StateFieldUpdate.setFromVariable('dialogMatchId',
            varFromPageParam(matchIdParam.identifier.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)),
      ]),
    );
    resetNode.followUpAction = Actions.apiCallNode(
      project, endpointName: 'GetGuestInviteTeams', groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'guestTeamsLoad', nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updateAppState(project,
            updates: [StateFieldUpdate.setFromVariable('dialogTeams', ctx.responseVar)]),
      ]),
    );
    _appendToFirstPageLoadChain(wc.node, resetNode);
  }

  // 2. Rebuild de sectie fris.
  for (final n in findDescendants(wc.node, (x) => x.name == 'GuestInviteSectionContainer').toList()) {
    removeByKey(wc.node, n.key);
  }

  // Team-lijst (tik → kies team + laad spelers).
  final teamsVar = varFromPageState(inviteTeamsId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final teamsList = UI.listView(name: 'GuestInviteTeamList', shrinkWrap: true, spacing: 2,
      dynamicSource: DynamicSource(variable: teamsVar, itemName: 'gteam'));
  final teamNameText = UI.text('', name: 'GuestInviteTeamName', style: UITextStyle.bodyMedium);
  teamNameText.props.text.textValue = FFStringValue(variable: generatorVarField(teamsList.key, 'name'));
  final teamRow = UI.container(name: 'GuestInviteTeamRow', width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: teamNameText);
  final setTeamNode = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.updatePageState(project, widgetClassName: 'WedstrijdDetailPage',
      updates: [StateFieldUpdate.setFromVariable('inviteTeamId', generatorVarField(teamsList.key, 'id'))]));
  setTeamNode.followUpAction = Actions.apiCallNode(project, endpointName: 'GetScorerMembers',
    groupName: 'VoetbalPlannerAPI', dynamicVariables: {'teamId': generatorVarField(teamsList.key, 'id')},
    outputVariableName: 'guestMembersLoad', nodeKey: teamRow.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updatePageState(project, widgetClassName: 'WedstrijdDetailPage',
          updates: [StateFieldUpdate.setFromVariable('inviteGuestMembers', ctx.responseVar)]),
    ]));
  teamRow.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP), rootAction: setTeamNode));
  teamsList.children.add(teamRow);

  // Speler-lijst (tik → uitnodigen). Zichtbaar zodra een team gekozen is.
  final membersVar = varFromPageState(inviteMembersId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final membersList = UI.listView(name: 'GuestInviteMemberList', shrinkWrap: true, spacing: 2,
      dynamicSource: DynamicSource(variable: membersVar, itemName: 'gmember'));
  final memberNameText = UI.text('', name: 'GuestInviteMemberName', style: UITextStyle.bodyMedium);
  memberNameText.props.text.textValue = FFStringValue(variable: generatorVarField(membersList.key, 'name'));
  final memberRow = UI.container(name: 'GuestInviteMemberRow', width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: memberNameText);
  memberRow.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
    rootAction: Actions.apiCallNode(project, endpointName: 'InviteGuestToMatch', groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
        'teamId': stateVar('inviteTeamId')!,
        'memberId': generatorVarField(membersList.key, 'id'),
      },
      outputVariableName: 'guestInviteOut', nodeKey: memberRow.key,
      onSuccess: (ctx) => Actions.chain([Actions.snackBar('Gastspeler uitgenodigd.')]),
      onFailure: (ctx) => Actions.chain([Actions.snackBar('Uitnodigen mislukt — controleer je rechten.')]))));
  membersList.children.add(memberRow);

  final memberSection = UI.column(name: 'GuestInviteMemberSection', crossAxisAlignment: UICrossAxisAlignment.stretch,
      spacing: 4, children: [
        UI.text('Kies de gastspeler:', name: 'GuestInviteMemberLabel', style: UITextStyle.labelMedium, color: UIColor.secondaryText),
        membersList,
      ]);
  setConditionalVisibility(memberSection, variable: conditionVar(
      stateVar('inviteTeamId')!, FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING)).variable);

  final container = UI.column(name: 'GuestInviteSectionContainer', crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 6, children: [
        UI.text('Gastspeler uitnodigen', name: 'GuestInviteHeader', style: UITextStyle.titleMedium),
        UI.text('Kies eerst het team van de gastspeler:', name: 'GuestInviteTeamLabel',
            style: UITextStyle.labelMedium, color: UIColor.secondaryText),
        teamsList,
        memberSection,
      ]);
  // Alleen zichtbaar met beheerrechten én als via de FAB 'Gastspeler uitnodigen'
  // is gekozen (matchActionMode == 'invite').
  final giActionModeId = _findAppStateFieldId(project, 'matchActionMode');
  setConditionalVisibility(container, variable: giActionModeId == null
      ? codeExpressionVar(
          expression: "m == 'true'",
          arguments: [CodeExpressionArg(name: 'm', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
              value: FFValue(variable: magVar))],
          returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)))
      : codeExpressionVar(
          expression: "m == 'true' && a == 'invite'",
          arguments: [
            CodeExpressionArg(name: 'm', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                value: FFValue(variable: magVar)),
            CodeExpressionArg(name: 'a', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                value: FFValue(variable: varFromAppState(giActionModeId.deepCopy())
                  ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key))),
          ],
          returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean))));

  // Plaats onderaan dezelfde kolom als de score-sectie / afmeld-knop.
  final afmeldBtn = findDescendants(wc.node, (n) => n.name == 'MatchAfmeldButton').firstOrNull;
  final parentCol = afmeldBtn == null ? null : findDescendants(wc.node, (_) => true)
      .where((n) => n.children.any((c) => identical(c, afmeldBtn))).firstOrNull;
  if (parentCol == null) return;
  parentCol.children.add(container);
}

// Dashboard-sectie "Uitnodigingen": toont de wedstrijden waarvoor je als
// gastspeler bent uitgenodigd. Tik → wedstrijddetail. Alleen zichtbaar als er
// uitnodigingen zijn. Rebuild elke push.
void _addDashboardGuestInvitations(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  final authTokenId = _findAppStateFieldId(project, 'authToken');
  final invId = _findAppStateFieldId(project, 'guestInvitations');
  final hasEp = findApiEndpoint(project, name: 'GetMyGuestInvitations', groupName: 'VoetbalPlannerAPI') != null;
  if (authTokenId == null || invId == null || !hasEp) return;

  // 1. onLoad: GetMyGuestInvitations -> AppState.guestInvitations (idempotent).
  bool hasLoad(FFActionNode n) {
    if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
        n.action.database.apiCall.hasEndpointIdentifier() &&
        n.action.database.apiCall.endpointIdentifier.name == 'GetMyGuestInvitations') return true;
    return n.hasFollowUpAction() && hasLoad(n.followUpAction);
  }
  if (!wc.node.triggerActions.any((t) => t.hasRootAction() && hasLoad(t.rootAction))) {
    _appendToFirstPageLoadChain(wc.node, Actions.apiCallNode(
      project, endpointName: 'GetMyGuestInvitations', groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'guestInvLoad', nodeKey: wc.node.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updateAppState(project, updates: [
          StateFieldUpdate.setFromVariable('guestInvitations', ctx.responseVar)]),
      ]),
    ));
  }

  // 2. Rebuild de sectie fris.
  for (final n in findDescendants(wc.node, (x) => x.name == 'GuestInvitationsContainer').toList()) {
    removeByKey(wc.node, n.key);
  }
  final anchor = findDescendants(wc.node, (n) => n.name == 'DashboardMatchesContainer').firstOrNull;
  if (anchor == null) return;
  final bodyCol = findDescendants(wc.node, (_) => true)
      .where((n) => n.children.any((c) => identical(c, anchor))).firstOrNull;
  if (bodyCol == null) return;

  final invVar = varFromAppState(invId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  final listView = UI.listView(name: 'GuestInvitationsList', shrinkWrap: true, spacing: 8,
      padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
      dynamicSource: DynamicSource(variable: invVar, itemName: 'inv'));

  final tag = UI.text('Uitgenodigd als gastspeler', name: 'GuestInvTag', style: UITextStyle.labelSmall, color: UIColor.primary);
  final opponentText = UI.text('', name: 'GuestInvOpponent', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'opponent'));

  final homeAwayVar = codeExpressionVar(
      expression: "(h ?? '') == 'true' ? 'Thuis' : 'Uit'",
      arguments: [CodeExpressionArg(name: 'h', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: generatorVarField(listView.key, 'isHome')))],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)));
  final homeAwayText = UI.text('', name: 'GuestInvHomeAway', style: UITextStyle.labelSmall, color: UIColor.secondaryBackground);
  homeAwayText.props.text.textValue = FFStringValue(variable: homeAwayVar);
  final homeAwayBadge = UI.container(name: 'GuestInvHomeAwayBadge',
      padding: UIEdgeInsets.all(5), borderRadius: 8,
      color: UIColor.primary, child: homeAwayText);
  final dateText = UI.text('', name: 'GuestInvDate', style: UITextStyle.bodySmall, color: UIColor.secondaryText);
  dateText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'matchDatetime'));
  final metaRow = UI.row(name: 'GuestInvMetaRow', spacing: 8, crossAxisAlignment: UICrossAxisAlignment.center,
      children: [homeAwayBadge, dateText]);

  final infoCol = UI.column(name: 'GuestInvInfo', crossAxisAlignment: UICrossAxisAlignment.start, spacing: 4,
      children: [tag, opponentText, metaRow]);
  final card = UI.container(name: 'GuestInvCard', width: double.infinity, padding: UIEdgeInsets.all(12),
      borderRadius: 8, color: UIColor.secondaryBackground, child: infoCol);
  card.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
    rootAction: FFActionNode(key: generateRandomAlphaNumericString(),
      action: Actions.navigate(project, pageName: 'WedstrijdDetailPage',
        params: {'matchId': VariableParamValue(generatorVarField(listView.key, 'matchId'))}))));
  listView.children.add(card);

  final container = UI.column(name: 'GuestInvitationsContainer', crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 6, children: [
        // 5px links van de titel.
        UI.container(name: 'GuestInvHeaderWrap', padding: UIEdgeInsets.only(left: 5),
            child: UI.text('Mijn uitnodigingen', name: 'GuestInvHeader', style: UITextStyle.titleMedium)),
        listView,
      ]);
  // Alleen tonen als er ten minste één uitnodiging is (eerste item heeft opponent).
  setConditionalVisibility(container, variable: conditionVar(
      varFromAppState(invId.deepCopy())
        ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key)
        ..operations.add(FFVariableOperation(listItemAtIndex: FFListItemAtIndex(type: FFListItemAtIndex_IndexType.FIRST)))
        ..operations.add(FFVariableOperation(accessDataStructField: FFAccessDataStructField(
            fieldIdentifier: _findStructFieldId(project, 'GuestInvitation', 'opponent')?.deepCopy() ?? FFIdentifier(name: 'opponent')))),
      FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING)).variable);

  // Onderaan de dashboard-kolom plaatsen.
  bodyCol.children.add(container);
}

// App-state lijstvelden voor de coach-dialoog (club-teams + spelers van het
// gekozen team). Mirror van _ensureScoreTeamMembersField.
void _ensureDialogListFields(FFProject project) {
  void ensure(String name, String structName) {
    if (project.appState.fields.any((f) => f.parameter.identifier.name == name)) return;
    final struct = project.backend.dataSchemaConfig.dataStructs
        .cast<FFDataStruct?>()
        .firstWhere((s) => s?.identifier.name == structName, orElse: () => null);
    if (struct == null) return;
    final param = FFParameter(
      identifier: FFIdentifier(name: name, key: generateRandomAlphaNumericString()),
      dataType: dataStructType(struct.identifier.deepCopy()),
    );
    param.isList = true;
    project.appState.fields.add(FFAppStateField(parameter: param));
  }
  ensure('dialogTeams', 'TeamOption');
  ensure('dialogMembers', 'SwapMember');
}

// Bouwt de inhoud van de MatchActionsSheet-dialoog: een menu + een doelpunt-
// picker + een gastspeler-picker, geschakeld via AppState.dialogView. Rebuild
// elke push. Alle data komt uit app-state (component kan geen pagina-state lezen).
void _buildMatchActionsDialogBody(FFProject project) {
  final wc = project.getWidgetClassByName('MatchActionsSheet');
  if (wc == null) return;
  final root = findDescendants(wc.node, (n) => n.name == 'MatchActionsRoot').firstOrNull;
  if (root == null) return;

  final viewId    = _findAppStateFieldId(project, 'dialogView');
  final matchIdId = _findAppStateFieldId(project, 'dialogMatchId');
  final scorerId  = _findAppStateFieldId(project, 'dialogScorerName');
  final teamIdId  = _findAppStateFieldId(project, 'dialogTeamId');
  final membersScoreId = _findAppStateFieldId(project, 'scoreTeamMembers');
  final teamsId   = _findAppStateFieldId(project, 'dialogTeams');
  final membersId = _findAppStateFieldId(project, 'dialogMembers');
  final authId    = _findAppStateFieldId(project, 'authToken');
  if ([viewId, matchIdId, scorerId, teamIdId, membersScoreId, teamsId, membersId, authId]
      .any((x) => x == null)) return;
  final hasAdd    = findApiEndpoint(project, name: 'AddGoalV2', groupName: 'VoetbalPlannerAPI') != null;
  final hasInvite = findApiEndpoint(project, name: 'InviteGuestToMatch', groupName: 'VoetbalPlannerAPI') != null;
  final hasMbrs   = findApiEndpoint(project, name: 'GetScorerMembers', groupName: 'VoetbalPlannerAPI') != null;
  final hasFlag   = findApiEndpoint(project, name: 'SetMatchFlagger', groupName: 'VoetbalPlannerAPI') != null;
  if (!hasAdd || !hasInvite || !hasMbrs) return;

  final k = wc.node.key;
  FFVariable appVar(FFIdentifier id) => varFromAppState(id.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: k);
  FFDataTypeV2 str() => FFDataTypeV2(scalarType: FFBaseDataType.String);
  FFDataTypeV2 boolT() => FFDataTypeV2(scalarType: FFBaseDataType.Boolean);
  FFVariable viewIs(String v) => codeExpressionVar(
      expression: "(x ?? '') == '$v'",
      arguments: [CodeExpressionArg(name: 'x', dataType: str(), value: FFValue(variable: appVar(viewId!)))],
      returnType: FFParameter(dataType: boolT()));
  FFActionNode setViewNode(String v) => FFActionNode(key: generateRandomAlphaNumericString(),
      action: Actions.updateAppState(project, updates: [StateFieldUpdate.set('dialogView', v)]));

  root.children.clear();
  final title = UI.text('Wedstrijd-actie', name: 'MaTitle', style: UITextStyle.titleMedium);
  root.children.add(title);

  // ── Menu ──
  FFNode menuBtn(String label, String v) {
    final b = UI.button(label, name: 'MaMenuBtn_$v', width: double.infinity);
    b.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP), rootAction: setViewNode(v)));
    return b;
  }
  final menuView = UI.column(name: 'MaMenuView', crossAxisAlignment: UICrossAxisAlignment.stretch, spacing: 10,
      children: [
        menuBtn('Doelpunt toevoegen', 'goal'),
        menuBtn('Vlagger kiezen', 'flag'),
        menuBtn('Gastspeler uitnodigen', 'invite'),
      ]);
  setConditionalVisibility(menuView, variable: viewIs('menu'));
  root.children.add(menuView);

  FFNode backBtn() {
    final b = UI.button('Terug', name: 'MaBackBtn_${generateRandomAlphaNumericString()}', width: double.infinity);
    b.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP), rootAction: setViewNode('menu')));
    return b;
  }

  // ── Doelpunt-picker ──
  final scorerListVar = appVar(membersScoreId!);
  final scorerList = UI.listView(name: 'MaScorerList', shrinkWrap: true, spacing: 2,
      dynamicSource: DynamicSource(variable: scorerListVar, itemName: 'sm'));
  final scorerName = UI.text('', name: 'MaScorerName', style: UITextStyle.bodyMedium);
  scorerName.props.text.textValue = FFStringValue(
      variable: codeExpressionVar(
        expression: "((s ?? '') != '' && (s ?? '') == (n ?? '')) ? ('✓  ' + (n ?? '')) : (n ?? '')",
        arguments: [
          CodeExpressionArg(name: 's', dataType: str(), value: FFValue(variable: appVar(scorerId!))),
          CodeExpressionArg(name: 'n', dataType: str(), value: FFValue(variable: generatorVarField(scorerList.key, 'name'))),
        ], returnType: FFParameter(dataType: str())));
  final scorerRow = UI.container(name: 'MaScorerRow', width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: scorerName);
  scorerRow.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
    rootAction: FFActionNode(key: generateRandomAlphaNumericString(),
      action: Actions.updateAppState(project, updates: [
        StateFieldUpdate.setFromVariable('dialogScorerName', generatorVarField(scorerList.key, 'name'))]))));
  scorerList.children.add(scorerRow);
  final scorerScroll = UI.container(name: 'MaScorerScroll', height: 200, clipContent: true, child: scorerList);
  final minuteField = UI.textField(name: 'MaMinuteField', labelText: 'Minuut (optioneel)');
  final placeBtn = UI.button('Doelpunt plaatsen', name: 'MaPlaceBtn', width: double.infinity);
  placeBtn.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
    rootAction: Actions.apiCallNode(project, endpointName: 'AddGoalV2', groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': appVar(authId!), 'matchId': appVar(matchIdId!),
        'scorerName': appVar(scorerId), 'minute': varFromTextFieldValue(minuteField.key),
      },
      outputVariableName: 'maAddGoal', nodeKey: placeBtn.key,
      onSuccess: (ctx) => FFActionNode(key: generateRandomAlphaNumericString(),
        action: Actions.snackBar('Doelpunt toegevoegd.'),
        followUpAction: FFActionNode(key: generateRandomAlphaNumericString(),
          action: Actions.updateAppState(project, updates: [StateFieldUpdate.set('dialogScorerName', '')]))),
      onFailure: (ctx) => Actions.chain([Actions.snackBar('Opslaan mislukt — controleer je rechten.')]))));
  final goalView = UI.column(name: 'MaGoalView', crossAxisAlignment: UICrossAxisAlignment.stretch, spacing: 8,
      children: [
        UI.text('Kies de speler en plaats:', name: 'MaGoalLabel', style: UITextStyle.labelMedium, color: UIColor.secondaryText),
        scorerScroll, minuteField, placeBtn, backBtn(),
      ]);
  setConditionalVisibility(goalView, variable: viewIs('goal'));
  root.children.add(goalView);

  // ── Vlagger-picker (iedereen uit het team van de wedstrijd) ──
  if (hasFlag) {
    final flagVar = appVar(membersScoreId!);
    final flagList = UI.listView(name: 'MaFlagList', shrinkWrap: true, spacing: 2,
        dynamicSource: DynamicSource(variable: flagVar, itemName: 'fm'));
    final flagName = UI.text('', name: 'MaFlagName', style: UITextStyle.bodyMedium);
    flagName.props.text.textValue = FFStringValue(variable: generatorVarField(flagList.key, 'name'));
    final flagRow = UI.container(name: 'MaFlagRow', width: double.infinity,
        padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: flagName);
    flagRow.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: Actions.apiCallNode(project, endpointName: 'SetMatchFlagger', groupName: 'VoetbalPlannerAPI',
        dynamicVariables: {'matchId': appVar(matchIdId!), 'memberId': generatorVarField(flagList.key, 'id')},
        outputVariableName: 'maSetFlag', nodeKey: flagRow.key,
        onSuccess: (ctx) => FFActionNode(key: generateRandomAlphaNumericString(),
          action: Actions.snackBar('Vlagger opgeslagen.'),
          followUpAction: FFActionNode(key: generateRandomAlphaNumericString(),
            action: Actions.updateAppState(project, updates: [StateFieldUpdate.set('dialogView', 'menu')]))),
        onFailure: (ctx) => Actions.chain([Actions.snackBar('Opslaan mislukt — controleer je rechten.')]))));
    flagList.children.add(flagRow);
    final flagScroll = UI.container(name: 'MaFlagScroll', height: 200, clipContent: true, child: flagList);

    // "Geen vlagger" — wist de vlagger (lege memberId).
    final clearFlagBtn = UI.button('Geen vlagger', name: 'MaClearFlagBtn', width: double.infinity);
    clearFlagBtn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: Actions.apiCallNode(project, endpointName: 'SetMatchFlagger', groupName: 'VoetbalPlannerAPI',
        dynamicVariables: {'matchId': appVar(matchIdId!), 'memberId': varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING)},
        outputVariableName: 'maClearFlag', nodeKey: clearFlagBtn.key,
        onSuccess: (ctx) => FFActionNode(key: generateRandomAlphaNumericString(),
          action: Actions.snackBar('Vlagger verwijderd.'),
          followUpAction: FFActionNode(key: generateRandomAlphaNumericString(),
            action: Actions.updateAppState(project, updates: [StateFieldUpdate.set('dialogView', 'menu')]))),
        onFailure: (ctx) => Actions.chain([Actions.snackBar('Mislukt — controleer je rechten.')]))));

    final flagView = UI.column(name: 'MaFlagView', crossAxisAlignment: UICrossAxisAlignment.stretch, spacing: 8,
        children: [
          UI.text('Kies de vlagger (uit het team):', name: 'MaFlagLabel', style: UITextStyle.labelMedium, color: UIColor.secondaryText),
          flagScroll, clearFlagBtn, backBtn(),
        ]);
    setConditionalVisibility(flagView, variable: viewIs('flag'));
    root.children.add(flagView);
  }

  // ── Gastspeler-picker ──
  final teamsVar = appVar(teamsId!);
  final teamsList = UI.listView(name: 'MaTeamsList', shrinkWrap: true, spacing: 2,
      dynamicSource: DynamicSource(variable: teamsVar, itemName: 'tm'));
  final teamName = UI.text('', name: 'MaTeamName', style: UITextStyle.bodyMedium);
  teamName.props.text.textValue = FFStringValue(variable: generatorVarField(teamsList.key, 'name'));
  final teamRow = UI.container(name: 'MaTeamRow', width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: teamName);
  final setTeam = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.updateAppState(project, updates: [
      StateFieldUpdate.setFromVariable('dialogTeamId', generatorVarField(teamsList.key, 'id'))]));
  setTeam.followUpAction = Actions.apiCallNode(project, endpointName: 'GetScorerMembers', groupName: 'VoetbalPlannerAPI',
    dynamicVariables: {'teamId': generatorVarField(teamsList.key, 'id')},
    outputVariableName: 'maTeamMbrs', nodeKey: teamRow.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updateAppState(project, updates: [StateFieldUpdate.setFromVariable('dialogMembers', ctx.responseVar)])]));
  teamRow.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP), rootAction: setTeam));
  teamsList.children.add(teamRow);
  final teamsScroll = UI.container(name: 'MaTeamsScroll', height: 150, clipContent: true, child: teamsList);

  final gMembersVar = appVar(membersId!);
  final gMembersList = UI.listView(name: 'MaGuestList', shrinkWrap: true, spacing: 2,
      dynamicSource: DynamicSource(variable: gMembersVar, itemName: 'gm'));
  final gName = UI.text('', name: 'MaGuestName', style: UITextStyle.bodyMedium);
  gName.props.text.textValue = FFStringValue(variable: generatorVarField(gMembersList.key, 'name'));
  final gRow = UI.container(name: 'MaGuestRow', width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 10, horizontal: 12), child: gName);
  gRow.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
    rootAction: Actions.apiCallNode(project, endpointName: 'InviteGuestToMatch', groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'matchId': appVar(matchIdId), 'teamId': appVar(teamIdId!),
        'memberId': generatorVarField(gMembersList.key, 'id'),
      },
      outputVariableName: 'maInvite', nodeKey: gRow.key,
      onSuccess: (ctx) => Actions.chain([Actions.snackBar('Gastspeler uitgenodigd.')]),
      onFailure: (ctx) => Actions.chain([Actions.snackBar('Uitnodigen mislukt — controleer je rechten.')]))));
  gMembersList.children.add(gRow);
  final gMembersScroll = UI.container(name: 'MaGuestScroll', height: 180, clipContent: true, child: gMembersList);
  final gMembersSection = UI.column(name: 'MaGuestSection', crossAxisAlignment: UICrossAxisAlignment.stretch, spacing: 4,
      children: [UI.text('Kies de gastspeler:', name: 'MaGuestLabel', style: UITextStyle.labelMedium, color: UIColor.secondaryText), gMembersScroll]);
  setConditionalVisibility(gMembersSection, variable: conditionVar(
      appVar(teamIdId), FFCondition_Relation.NOT_EQUAL_TO,
      varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING)).variable);

  final inviteView = UI.column(name: 'MaInviteView', crossAxisAlignment: UICrossAxisAlignment.stretch, spacing: 8,
      children: [
        UI.text('Kies eerst het team:', name: 'MaInviteLabel', style: UITextStyle.labelMedium, color: UIColor.secondaryText),
        teamsScroll, gMembersSection, backBtn(),
      ]);
  setConditionalVisibility(inviteView, variable: viewIs('invite'));
  root.children.add(inviteView);
}

// Op de wedstrijddetail-load: zet dialogMatchId + laad de club-teams (dialogTeams)
// zodat de gastspeler-picker in de dialoog data heeft. Idempotent.
void _wireMatchActionsDialogLoad(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  final matchIdParam = wc.params.values.cast<FFParameter?>().firstWhere(
      (p) => p?.hasIdentifier() == true && p?.identifier.name == 'matchId', orElse: () => null);
  final hasTeamsEp = findApiEndpoint(project, name: 'GetGuestInviteTeams', groupName: 'VoetbalPlannerAPI') != null;
  if (matchIdParam == null || !hasTeamsEp) return;

  bool hasDlgLoad(FFActionNode n) {
    if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
        n.action.database.apiCall.hasEndpointIdentifier() &&
        n.action.database.apiCall.endpointIdentifier.name == 'GetGuestInviteTeams') return true;
    return n.hasFollowUpAction() && hasDlgLoad(n.followUpAction);
  }
  if (wc.node.triggerActions.any((t) => t.hasRootAction() && hasDlgLoad(t.rootAction))) return;

  // Reset de dialoog-weergave/actiemodus + zet dialogMatchId uit de page-param,
  // dan laad de club-teams -> dialogTeams.
  final resetMode = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.updateAppState(project, updates: [
      StateFieldUpdate.set('matchActionMode', ''),
      StateFieldUpdate.set('dialogView', 'menu'),
    ]));
  final setMatchId = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.updateAppState(project, updates: [
      StateFieldUpdate.setFromVariable('dialogMatchId',
          varFromPageParam(matchIdParam.identifier.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key))]));
  resetMode.followUpAction = setMatchId;
  setMatchId.followUpAction = Actions.apiCallNode(project, endpointName: 'GetGuestInviteTeams',
    groupName: 'VoetbalPlannerAPI', outputVariableName: 'dlgTeamsLoad', nodeKey: wc.node.key,
    onSuccess: (ctx) => Actions.chain([
      Actions.updateAppState(project, updates: [StateFieldUpdate.setFromVariable('dialogTeams', ctx.responseVar)])]));
  _appendToFirstPageLoadChain(wc.node, resetMode);
}

// Coach-FAB op de wedstrijddetail: opent de MatchActionsSheet (doelpunt
// toevoegen / gastspeler uitnodigen). Alleen zichtbaar met beheerrechten
// (matchMagOpstelling == 'true'). Rebuild elke push.
void _addWedstrijdActionsFab(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  if (project.getWidgetClassByName('MatchActionsSheet') == null) return;

  // Bestaande FAB verwijderen zodat we hem vers opbouwen (met juiste visibility).
  final existing = wc.node.childPropertyMap['floatingActionButton'];
  if (existing != null) {
    for (final ref in existing.keyRefs.toList()) {
      removeByKey(wc.node, ref.key);
    }
    wc.node.childPropertyMap.remove('floatingActionButton');
  }

  final fab = UI.fab(iconName: 'add', name: 'MatchActionsFab');
  // Zelfde opbouw als de dashboard-FAB (geen Visibility-wrapper) → uitlijning
  // rechtsonder identiek; de acties in het menu zijn server-side afgeschermd.
  // Chain: reset naar menu-weergave → open dialog → ná sluiten de doelpuntenlijst
  // verversen (een dialoog kan pagina-state niet zelf bijwerken).
  final resetNode = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.updateAppState(project, updates: [StateFieldUpdate.set('dialogView', 'menu')]));
  final openNode = FFActionNode(key: generateRandomAlphaNumericString(),
    action: Actions.bottomSheet(project, componentName: 'MatchActionsSheet'));
  resetNode.followUpAction = openNode;

  final authTokenId = _findAppStateFieldId(project, 'authToken');
  final matchIdParam = wc.params.values.cast<FFParameter?>().firstWhere(
      (p) => p?.hasIdentifier() == true && p?.identifier.name == 'matchId', orElse: () => null);
  final hasGoalsList = findApiEndpoint(project, name: 'GetMatchGoalsList', groupName: 'VoetbalPlannerAPI') != null;
  if (authTokenId != null && matchIdParam != null && hasGoalsList) {
    openNode.followUpAction = Actions.apiCallNode(project, endpointName: 'GetMatchGoalsList',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      },
      outputVariableName: 'fabGoalsRefresh', nodeKey: fab.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.updatePageState(project, widgetClassName: 'WedstrijdDetailPage',
            updates: [StateFieldUpdate.setFromVariable('goals', ctx.responseVar)]),
      ]));
  }
  fab.triggerActions.add(FFTriggerActions(
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP), rootAction: resetNode));

  // Alleen zichtbaar voor coaches (matchMagOpstelling == 'true'). De scaffold-FAB-
  // slot regelt de rechtsonder-positie; de Visibility toont/verbergt alleen.
  final magField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((x) => x?.parameter.identifier.name == 'matchMagOpstelling', orElse: () => null);
  if (magField != null) {
    final magVar = varFromPageState(magField.parameter.identifier.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    setConditionalVisibility(fab, variable: codeExpressionVar(
      expression: "m == 'true'",
      arguments: [CodeExpressionArg(name: 'm', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
          value: FFValue(variable: magVar))],
      returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean))));
  }

  wc.node.children.add(fab);
  wc.node.childPropertyMap['floatingActionButton'] =
      FFChildrenKeys(keyRefs: [FFNodeKeyReference(key: fab.key)]);
}

// Verwijdert de oude inline coach-secties (doelpunt- en gastspeler-sectie) van
// de wedstrijddetail; die zijn vervangen door de MatchActionsSheet-dialoog (FAB).
// De data-loads (scoreTeamMembers, goals, dialogTeams) worden door de sectie-
// functies gedaan en blijven behouden; alleen de UI-containers gaan weg.
void _removeInlineCoachSections(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  for (final name in const ['ScoreSectionContainer', 'GuestInviteSectionContainer']) {
    for (final n in findDescendants(wc.node, (x) => x.name == name).toList()) {
      removeByKey(wc.node, n.key);
    }
  }
}

// Maakt de Info-tab-content-kolom scrollbaar zodat lange inhoud (info + coach-
// score-sectie met spelerlijst + af-/aanmelden) volledig bereikbaar is en er
// niets meer onder de spelers buiten beeld valt.
void _makeWedstrijdDetailInfoTabScrollable(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  final col = findDescendants(wc.node, (n) => n.key == 'Column_gj4yosa2').firstOrNull;
  if (col == null || col.type != FFWidgetType.Column) return;
  if (!col.props.column.scrollable) {
    final colCopy = col.props.column.deepCopy();
    colCopy.scrollable = true;
    col.props.column = colCopy;
  }
}

// Coach-sectie op de WedstrijdDetailPage met de doelpunten-samenvatting (uit
// $.goals_summary). Alleen zichtbaar als matchMagOpstelling == 'true'. Een
// samenvatting-string i.p.v. een dynamische ListView houdt de binding robuust
// (toevoegen/verwijderen van losse doelpunten volgt als interactief increment).
void _addWedstrijdScoreSection(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  final existingContainer =
      findDescendants(wc.node, (n) => n.name == 'ScoreSectionContainer').firstOrNull;

  FFVariable? stateVar(String name) {
    final f = wc.classModel.stateFields
        .cast<FFWidgetClassStateField?>()
        .firstWhere((x) => x?.parameter.identifier.name == name, orElse: () => null);
    if (f == null) return null;
    return varFromPageState(f.parameter.identifier.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  }

  final magVar     = stateVar('matchMagOpstelling');
  final summaryVar  = stateVar('matchGoalsSummary');
  if (magVar == null || summaryVar == null) return;

  final authTokenId = _findAppStateFieldId(project, 'authToken');
  final matchIdParam = wc.params.values
      .cast<FFParameter?>()
      .firstWhere((p) => p?.hasIdentifier() == true && p?.identifier.name == 'matchId',
          orElse: () => null);
  final hasDeleteEp = findApiEndpoint(
      project, name: 'DeleteLastGoal', groupName: 'VoetbalPlannerAPI') != null;
  final hasAddEp = findApiEndpoint(
      project, name: 'AddGoalV2', groupName: 'VoetbalPlannerAPI') != null;

  // Teamleden laden (van het TEAM VAN DE WEDSTRIJD, niet het current team) ->
  // AppState.scoreTeamMembers voor de maker-keuze. matchTeamId is gezet door
  // GetMatchDetail (eerder in de page-load-chain).
  final scoreMembersId = _findAppStateFieldId(project, 'scoreTeamMembers');
  final matchTeamIdVar = stateVar('matchTeamId');
  final scorerEp = findApiEndpoint(project, name: 'GetScorerMembers', groupName: 'VoetbalPlannerAPI');
  // Bestaande score-load van vorige pushes wees naar GetTeamMembers (dat jezelf
  // uitsluit). Buig zulke nodes op deze pagina om naar GetScorerMembers zodat de
  // maker-keuze álle teamleden toont. GetTeamMembers wordt op deze pagina nergens
  // anders gebruikt, dus dit is veilig.
  if (scorerEp != null) {
    void retarget(FFActionNode n) {
      if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
          n.action.database.apiCall.hasEndpointIdentifier() &&
          n.action.database.apiCall.endpointIdentifier.name == 'GetTeamMembers') {
        n.action.database.apiCall.endpointIdentifier = scorerEp.identifier.deepCopy();
      }
      if (n.hasFollowUpAction()) retarget(n.followUpAction);
    }
    for (final t in wc.node.triggerActions) {
      if (t.hasRootAction()) retarget(t.rootAction);
    }
  }
  if (authTokenId != null && matchTeamIdVar != null && scoreMembersId != null &&
      scorerEp != null) {
    bool hasLoad(FFActionNode n) {
      if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
          n.action.database.apiCall.hasEndpointIdentifier() &&
          n.action.database.apiCall.endpointIdentifier.name == 'GetScorerMembers') return true;
      if (n.hasFollowUpAction() && hasLoad(n.followUpAction)) return true;
      return false;
    }
    final already = wc.node.triggerActions.any((t) => t.hasRootAction() && hasLoad(t.rootAction));
    if (!already) {
      _appendToFirstPageLoadChain(
        wc.node,
        Actions.apiCallNode(
          project,
          endpointName: 'GetScorerMembers',
          groupName: 'VoetbalPlannerAPI',
          dynamicVariables: {'teamId': stateVar('matchTeamId')!},
          outputVariableName: 'scoreMembersLoad',
          nodeKey: wc.node.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.updateAppState(project, updates: [
              StateFieldUpdate.setFromVariable('scoreTeamMembers', ctx.responseVar),
            ]),
          ]),
        ),
      );
    }
  }

  // Doelpunten laden -> page-state 'goals' (de bestaande Doelpunten-tab toont die,
  // een ListView gebonden aan _model.goals: List<MatchGoal>).
  if (authTokenId != null && matchIdParam != null &&
      findApiEndpoint(project, name: 'GetMatchGoalsList', groupName: 'VoetbalPlannerAPI') != null) {
    bool hasGoalsLoad(FFActionNode n) {
      if (n.hasAction() && n.action.hasDatabase() && n.action.database.hasApiCall() &&
          n.action.database.apiCall.hasEndpointIdentifier() &&
          n.action.database.apiCall.endpointIdentifier.name == 'GetMatchGoalsList') return true;
      if (n.hasFollowUpAction() && hasGoalsLoad(n.followUpAction)) return true;
      return false;
    }
    final already = wc.node.triggerActions.any((t) => t.hasRootAction() && hasGoalsLoad(t.rootAction));
    if (!already) {
      _appendToFirstPageLoadChain(
        wc.node,
        Actions.apiCallNode(
          project,
          endpointName: 'GetMatchGoalsList',
          groupName: 'VoetbalPlannerAPI',
          dynamicVariables: {
            'token': varFromAppState(authTokenId.deepCopy()),
            'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())
              ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
          },
          outputVariableName: 'matchGoalsListLoad',
          nodeKey: wc.node.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.updatePageState(
              project,
              widgetClassName: 'WedstrijdDetailPage',
              updates: [StateFieldUpdate.setFromVariable('goals', ctx.responseVar)],
            ),
          ]),
        ),
      );
    }
  }

  // Herlaadt de doelpunten (GetMatchGoalsList) -> page-state 'goals', zodat de
  // Doelpunten-tab na toevoegen/verwijderen direct klopt.
  FFActionNode refreshGoals(String outName, String nodeKey) => Actions.apiCallNode(
        project,
        endpointName: 'GetMatchGoalsList',
        groupName: 'VoetbalPlannerAPI',
        dynamicVariables: {
          'token': varFromAppState(authTokenId!.deepCopy()),
          'matchId': varFromPageParam(matchIdParam!.identifier.deepCopy())
            ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
        },
        outputVariableName: outName,
        nodeKey: nodeKey,
        onSuccess: (ctx) => Actions.chain([
          Actions.updatePageState(
            project,
            widgetClassName: 'WedstrijdDetailPage',
            updates: [StateFieldUpdate.setFromVariable('goals', ctx.responseVar)],
          ),
        ]),
      );

  // ── Verwijderknop per doelpunt op de Doelpunten-tab ─────────────────────────
  // Alleen zichtbaar met beheerrechten (matchMagOpstelling == 'true'). Tik →
  // DeleteGoal(goalId van het item) → refreshGoals herlaadt de lijst, zodat de
  // Doelpunten-tab meteen bijwerkt. Idempotent: skip als de knop er al staat.
  final hasDeleteGoalEp = findApiEndpoint(
      project, name: 'DeleteGoal', groupName: 'VoetbalPlannerAPI') != null;
  if (authTokenId != null && matchIdParam != null && hasDeleteGoalEp) {
    final goalsList =
        findDescendants(wc.node, (n) => n.key == 'ListView_ueutzh5d').firstOrNull;
    final goalRow = goalsList == null
        ? null
        : findDescendants(goalsList, (n) => n.key == 'Row_xazcvw5v').firstOrNull;
    if (goalRow != null &&
        findDescendants(goalRow, (n) => n.name == 'GoalDeleteButton').isEmpty) {
      final delGoalBtn = UI.container(
        name: 'GoalDeleteButton',
        padding: UIEdgeInsets.all(6),
        child: UI.icon('delete', size: 22, color: UIColor.error),
      );
      final delGoalApi = Actions.apiCallNode(
        project,
        endpointName: 'DeleteGoal',
        groupName: 'VoetbalPlannerAPI',
        dynamicVariables: {
          'token': varFromAppState(authTokenId.deepCopy()),
          'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())
            ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
          'goalId': generatorVarField('ListView_ueutzh5d', 'id'),
        },
        outputVariableName: 'delGoalRowOut',
        nodeKey: delGoalBtn.key,
        onSuccess: (ctx) => FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: Actions.snackBar('Doelpunt verwijderd.'),
          followUpAction: refreshGoals('delGoalRowRefresh', delGoalBtn.key),
        ),
        onFailure: (ctx) => Actions.chain([
          Actions.snackBar('Verwijderen mislukt — alleen de coach mag dit.'),
        ]),
      );
      delGoalBtn.triggerActions.add(FFTriggerActions(
        trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
        rootAction: delGoalApi,
      ));
      final magForDel = stateVar('matchMagOpstelling');
      if (magForDel != null) {
        setConditionalVisibility(
          delGoalBtn,
          variable: codeExpressionVar(
            expression: "m == 'true'",
            arguments: [
              CodeExpressionArg(
                name: 'm',
                dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                value: FFValue(variable: magForDel),
              ),
            ],
            returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
          ),
        );
      }
      goalRow.children.add(delGoalBtn);
    }
  }

  // Bouwt de "Laatste doelpunt verwijderen"-knop (coach-actie) -> DeleteLastGoal
  // -> samenvatting bijwerken uit de response (geen re-fetch nodig). Null als de
  // benodigde token/param/endpoint ontbreekt.
  FFNode? buildDelBtn() {
    if (authTokenId == null || matchIdParam == null || !hasDeleteEp) return null;
    final delBtn = UI.button('Laatste doelpunt verwijderen',
        name: 'ScoreDeleteLastButton', width: double.infinity);
    final apiNode = Actions.apiCallNode(
      project,
      endpointName: 'DeleteLastGoal',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
      },
      outputVariableName: 'delLastGoalOut',
      nodeKey: delBtn.key,
      onSuccess: (ctx) => FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [
            StateFieldUpdate.setFromVariable(
                'matchGoalsSummary', _jsonBodyVar(ctx, r'$.goals_summary', delBtn.key)),
          ],
        ),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: Actions.snackBar('Laatste doelpunt verwijderd.'),
          followUpAction: refreshGoals('delGoalsRefresh', delBtn.key),
        ),
      ),
      onFailure: (ctx) => Actions.chain([
        Actions.snackBar('Verwijderen mislukt — alleen de coach mag dit.'),
      ]),
    );
    delBtn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: apiNode,
    ));
    return delBtn;
  }

  // Toevoeg-form: minuut + label + tikbare ledenlijst. Tik een speler -> AddGoalV2
  // (scorer_name = de naam van het lid, dus geen typefouten) -> samenvatting
  // bijwerken uit de response. Leeg als iets ontbreekt.
  List<FFNode> buildAddForm() {
    if (authTokenId == null || matchIdParam == null || !hasAddEp || scoreMembersId == null) {
      return <FFNode>[];
    }
    final selVar = stateVar('selectedScorerName');
    if (selVar == null) return <FFNode>[];

    final label = UI.text('Kies de speler, vul de minuut en plaats:', name: 'ScoreAddLabel',
        style: UITextStyle.labelMedium, color: UIColor.secondaryText);

    // Tikbare ledenlijst: tik = speler kiezen (SetState selectedScorerName).
    final membersVar = varFromAppState(scoreMembersId.deepCopy())
      ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
    final listView = UI.listView(
      name: 'ScoreMembersList',
      shrinkWrap: true,
      spacing: 2,
      dynamicSource: DynamicSource(variable: membersVar, itemName: 'lid'),
    );
    final nameText = UI.text('', name: 'ScoreMemberName', style: UITextStyle.bodyMedium);
    // Vinkje vóór de gekozen speler → duidelijke selectie-feedback.
    nameText.props.text.textValue = FFStringValue(
      variable: codeExpressionVar(
        expression: "((sel ?? '') != '' && (sel ?? '') == (nm ?? '')) ? ('✓  ' + (nm ?? '')) : (nm ?? '')",
        arguments: [
          CodeExpressionArg(
            name: 'sel',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: stateVar('selectedScorerName')!),
          ),
          CodeExpressionArg(
            name: 'nm',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: generatorVarField(listView.key, 'name')),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
      ),
    );
    // Volledige breedte + rand: de HELE rij is nu aantikbaar (voorheen alleen de
    // smalle tekstbreedte, waardoor selecteren vaak mislukte) en ziet er tikbaar uit.
    // Volledige breedte + padding: de hele rij blijft aantikbaar (geen rand meer).
    final itemRow = UI.container(
      name: 'ScoreMemberRow',
      width: double.infinity,
      padding: UIEdgeInsets.symmetric(vertical: 12, horizontal: 12),
      child: nameText,
    );
    itemRow.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [
            StateFieldUpdate.setFromVariable(
                'selectedScorerName', generatorVarField(listView.key, 'name')),
          ],
        ),
      ),
    ));
    listView.children.add(itemRow);

    // "Gekozen: X" indicator.
    final selText = UI.text('-', name: 'ScoreSelectedText', style: UITextStyle.bodyMedium);
    selText.props.text.textValue = FFStringValue(
      variable: codeExpressionVar(
        expression: "(s ?? '') == '' ? '(nog geen speler gekozen)' : 'Gekozen: ' + (s ?? '')",
        arguments: [
          CodeExpressionArg(
            name: 's',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: stateVar('selectedScorerName')!),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
      ),
    );

    final minuteField = UI.textField(name: 'ScoreMinuteField', labelText: 'Minuut (optioneel)');

    // "Doelpunt plaatsen": AddGoalV2(scorer_name = gekozen speler) -> refresh.
    final placeBtn = UI.button('Doelpunt plaatsen', name: 'ScoreAddButton', width: double.infinity);
    final apiNode = Actions.apiCallNode(
      project,
      endpointName: 'AddGoalV2',
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: {
        'token': varFromAppState(authTokenId.deepCopy()),
        'matchId': varFromPageParam(matchIdParam.identifier.deepCopy())
          ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
        'scorerName': stateVar('selectedScorerName')!,
        'minute': varFromTextFieldValue(minuteField.key),
      },
      outputVariableName: 'addGoalOut',
      nodeKey: placeBtn.key,
      // Samenvatting (Info-tab) bijwerken uit de response -> snackbar -> Doelpunten-lijst herladen.
      onSuccess: (ctx) => FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [
            StateFieldUpdate.setFromVariable(
                'matchGoalsSummary', _jsonBodyVar(ctx, r'$.goals_summary', placeBtn.key)),
          ],
        ),
        followUpAction: FFActionNode(
          key: generateRandomAlphaNumericString(),
          action: Actions.snackBar('Doelpunt toegevoegd.'),
          followUpAction: refreshGoals('addGoalsRefresh', placeBtn.key),
        ),
      ),
      onFailure: (ctx) => Actions.chain([
        // Toon de echte servermelding in de samenvatting-tekst (boven de knoppen).
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [
            StateFieldUpdate.setFromVariable(
                'matchGoalsSummary', _jsonBodyVar(ctx, r'$.message', placeBtn.key)),
          ],
        ),
        Actions.snackBar('Opslaan mislukt — zie de melding boven de knoppen.'),
      ]),
    );
    placeBtn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: apiNode,
    ));

    // Begrens de spelerlijst tot een vaste hoogte met eigen scroll (voorkomt
    // bottom overflow bij een lang team).
    final membersScroll = UI.container(
      name: 'ScoreMembersScroll',
      height: 200,
      clipContent: true,
      child: listView,
    );

    // Zodra een speler gekozen is, verschijnen de controls (Gekozen + minuut +
    // Opslaan) BOVEN de lijst en dus direct in beeld — voorheen vielen ze onder
    // de lijst buiten het scherm. Werkt als een in-line pop-up: kies speler →
    // vul minuut → opslaan.
    // "Annuleren" wist de keuze (verkeerde speler aangetikt) → het controls-blok
    // verdwijnt weer en je kunt opnieuw kiezen.
    final cancelBtn = UI.button(
      'Annuleren',
      name: 'ScoreCancelButton',
      width: double.infinity,
    );
    cancelBtn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: FFActionNode(
        key: generateRandomAlphaNumericString(),
        action: Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [StateFieldUpdate.set('selectedScorerName', '')],
        ),
      ),
    ));

    final controlsBox = UI.container(
      name: 'ScoreSelectedControls',
      width: double.infinity,
      padding: UIEdgeInsets.all(12),
      borderRadius: 8,
      color: UIColor.secondaryBackground,
      child: UI.column(
        name: 'ScoreSelectedControlsCol',
        crossAxisAlignment: UICrossAxisAlignment.stretch,
        spacing: 8,
        children: [selText, minuteField, placeBtn, cancelBtn],
      ),
    );
    setConditionalVisibility(
      controlsBox,
      variable: conditionVar(
        stateVar('selectedScorerName')!,
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
      ).variable,
    );

    return [label, controlsBox, membersScroll];
  }

  // Plaatst ontbrekende coach-controls (toevoeg-form + verwijder-knop) in de sectie.
  void ensureControls(FFNode container) {
    // Samenvatting-tekst (null-safe nu matchGoalsSummary default '' heeft), net na de kop.
    if (findDescendants(container, (n) => n.name == 'ScoreSummaryText').isEmpty) {
      final sv = stateVar('matchGoalsSummary');
      if (sv != null) {
        final summaryText = UI.text('-', name: 'ScoreSummaryText', style: UITextStyle.bodyMedium);
        summaryText.props.text.textValue = FFStringValue(variable: sv);
        final hIdx = container.children.indexWhere((n) => n.name == 'ScoreSectionHeader');
        container.children.insert(hIdx >= 0 ? hIdx + 1 : 0, summaryText);
      }
    }
    if (findDescendants(container, (n) => n.name == 'ScoreMembersList').isEmpty) {
      container.children.addAll(buildAddForm());
    }
    if (findDescendants(container, (n) => n.name == 'ScoreDeleteLastButton').isEmpty) {
      final b = buildDelBtn();
      if (b != null) container.children.add(b);
    }
  }

  // Sectie bestaat al -> oude add-form-widgets (naamveld/knop) opruimen en de
  // (nieuwe) tikbare ledenlijst + ontbrekende onderdelen bijplaatsen.
  if (existingContainer != null) {
    existingContainer.children.removeWhere((n) =>
        n.name == 'ScoreScorerField' || n.name == 'ScoreMinuteField' ||
        n.name == 'ScoreAddButton' || n.name == 'ScoreAddLabel' ||
        n.name == 'ScoreSelectedText' || n.name == 'ScoreMembersList' ||
        n.name == 'ScoreMembersScroll' || n.name == 'ScoreSelectedControls');
    ensureControls(existingContainer);
    return;
  }

  // Nieuwe sectie bouwen (zelfde kolom als de Afmelden-knop).
  final afmeldBtn = findDescendants(wc.node, (n) => n.name == 'MatchAfmeldButton').firstOrNull;
  if (afmeldBtn == null) return;
  final parentCol = findDescendants(wc.node, (_) => true)
      .where((n) => n.children.any((c) => identical(c, afmeldBtn)))
      .firstOrNull;
  if (parentCol == null) return;

  final header = UI.text('Doelpunten', name: 'ScoreSectionHeader', style: UITextStyle.titleMedium);

  final container = UI.column(
    name: 'ScoreSectionContainer',
    crossAxisAlignment: UICrossAxisAlignment.start,
    spacing: 6,
    children: [header],
  );
  ensureControls(container);
  // Alleen zichtbaar met beheerrechten én als via de FAB 'Doelpunt toevoegen'
  // is gekozen (matchActionMode == 'goal').
  final scActionModeId = _findAppStateFieldId(project, 'matchActionMode');
  setConditionalVisibility(
    container,
    variable: scActionModeId == null
        ? codeExpressionVar(
            expression: "m == 'true'",
            arguments: [
              CodeExpressionArg(name: 'm', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                  value: FFValue(variable: magVar)),
            ],
            returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)))
        : codeExpressionVar(
            expression: "m == 'true' && a == 'goal'",
            arguments: [
              CodeExpressionArg(name: 'm', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                  value: FFValue(variable: magVar)),
              CodeExpressionArg(name: 'a', dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
                  value: FFValue(variable: varFromAppState(scActionModeId.deepCopy())
                    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key))),
            ],
            returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean))),
  );
  parentCol.children.add(container);
}

// ─── Dashboard: trainingen-sectie ────────────────────────────────────────────
// Voegt een "Trainingen"-sectie toe onderaan de dashboard-kolom met een ListView
// gebonden aan AppState.trainings, en laadt ze via het native GetTrainingsList-
// endpoint op page-load.
void _addDashboardTrainingsSection(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;

  // 1. onLoad: laad trainingen via het native FF-endpoint GetTrainingsList
  //    (werkt in testmode, i.t.t. een custom http-actie) -> AppState.trainings.
  final authTokenId     = _findAppStateFieldId(project, 'authToken');
  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  final hasEndpoint = findApiEndpoint(
        project, name: 'GetTrainingsList', groupName: 'VoetbalPlannerAPI') != null;
  if (authTokenId != null && currentTeamIdId != null && hasEndpoint) {
    bool hasLoad(FFActionNode node) {
      if (node.hasAction() &&
          node.action.hasDatabase() &&
          node.action.database.hasApiCall() &&
          node.action.database.apiCall.hasEndpointIdentifier() &&
          node.action.database.apiCall.endpointIdentifier.name == 'GetTrainingsList') {
        return true;
      }
      if (node.hasFollowUpAction() && hasLoad(node.followUpAction)) return true;
      return false;
    }

    final already = wc.node.triggerActions
        .any((t) => t.hasRootAction() && hasLoad(t.rootAction));
    if (!already) {
      _appendToFirstPageLoadChain(
        wc.node,
        Actions.apiCallNode(
          project,
          endpointName: 'GetTrainingsList',
          groupName: 'VoetbalPlannerAPI',
          dynamicVariables: {
            'token':  varFromAppState(authTokenId.deepCopy()),
            'teamId': varFromAppState(currentTeamIdId.deepCopy()),
          },
          outputVariableName: 'trainingsLoad',
          nodeKey: wc.node.key,
          onSuccess: (ctx) => Actions.chain([
            Actions.updateAppState(project, updates: [
              StateFieldUpdate.setFromVariable('trainings', ctx.responseVar),
            ]),
          ]),
        ),
      );
    }
  }

  // 2. Sectie-UI (idempotent).
  if (findDescendants(wc.node, (n) => n.name == 'DashboardTrainingsContainer').isNotEmpty) {
    return;
  }

  final matchesContainer =
      findDescendants(wc.node, (n) => n.name == 'DashboardMatchesContainer').firstOrNull;
  if (matchesContainer == null) return;

  final parentCol = findDescendants(wc.node, (_) => true)
      .where((n) => n.children.any((c) => identical(c, matchesContainer)))
      .firstOrNull;
  if (parentCol == null) return;

  final trainingsId = _findAppStateFieldId(project, 'trainings');
  if (trainingsId == null) return;
  final trainingsVar = varFromAppState(trainingsId.deepCopy())
    ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final header = UI.text('Trainingen',
      name: 'DashboardTrainingsHeader', style: UITextStyle.titleMedium);

  final listView = UI.listView(
    name: 'DashboardTrainingsList',
    spacing: 8,
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 4),
    dynamicSource: DynamicSource(variable: trainingsVar, itemName: 'training'),
  );
  listView.props.listView.shrinkWrapValue = FFBooleanValue(inputValue: true);

  FFNode _field(String name, String field, UITextStyle style) {
    final t = UI.text('', name: name, style: style);
    t.props.text.textValue =
        FFStringValue(variable: generatorVarField(listView.key, field));
    return t;
  }

  final dayText    = _field('DashTrainDay', 'day_label', UITextStyle.bodyMedium);
  final dateText   = _field('DashTrainDate', 'date', UITextStyle.bodySmall);
  final timeText   = _field('DashTrainTime', 'start_time', UITextStyle.bodySmall);
  final locText    = _field('DashTrainLoc', 'location', UITextStyle.bodySmall);
  final statusText = _field('DashTrainStatus', 'mijn_status', UITextStyle.bodySmall);

  final card = UI.container(
    name: 'DashboardTrainingCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'DashTrainRow',
      spacing: 12,
      children: [
        UI.icon('fitness_center', size: 24, color: UIColor.primary),
        UI.column(
          name: 'DashTrainInfo',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 2,
          children: [
            dayText,
            UI.row(name: 'DashTrainDT', spacing: 8, children: [dateText, timeText]),
            locText,
            statusText,
          ],
        ),
      ],
    ),
  );
  listView.children.add(card);

  final section = UI.container(
    name: 'DashboardTrainingsContainer',
    padding: UIEdgeInsets.symmetric(horizontal: 12, vertical: 8),
    child: UI.column(
      name: 'DashboardTrainingsCol',
      crossAxisAlignment: UICrossAxisAlignment.start,
      spacing: 8,
      children: [header, listView],
    ),
  );

  parentCol.children.add(section);
}

// Voegt onder elke trainingskaart een rij met aanmeld-status-iconen toe:
// groen vinkje + aantal aangemeld, rood kruisje + aantal afgemeld. Aparte patch
// (de sectie-build hierboven is idempotent en wordt niet herbouwd).
void _addTrainingCardStatusIcons(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  final listView =
      findDescendants(wc.node, (n) => n.name == 'DashboardTrainingsList').firstOrNull;
  if (listView == null) return;
  final info = findDescendants(wc.node, (n) => n.name == 'DashTrainInfo').firstOrNull;
  if (info == null) return;
  // Idempotent.
  if (findDescendants(info, (n) => n.name == 'DashTrainStatusRow').isNotEmpty) return;

  FFNode countText(String name, String field, UIColor color) {
    final t = UI.text('0', name: name, style: UITextStyle.bodySmall, color: color);
    t.props.text.textValue =
        FFStringValue(variable: generatorVarField(listView.key, field));
    return t;
  }

  final row = UI.row(
    name: 'DashTrainStatusRow',
    spacing: 6,
    children: [
      UI.icon('check_circle', size: 16, color: UIColor.success),
      countText('DashTrainAangemeld', 'aangemeld', UIColor.success),
      UI.icon('cancel', size: 16, color: UIColor.error),
      countText('DashTrainAfgemeld', 'afgemeld', UIColor.error),
    ],
  );

  info.children.add(row);
}

// ─── TrainingDetailPage: af-/aanmelden met reden ─────────────────────────────
void _buildTrainingDetailPage(App app) {
  final afmeldingHandle = StructHandle(
    'Afmelding',
    {'naam': string, 'reden': string},
    description: generatedProjectStructDescription,
  );
  app.ensurePage(
    'TrainingDetailPage',
    description: 'Trainingdetail: af- en aanmelden met reden.',
    route: 'training-detail',
    params: {
      'scheduleId':  string.withDefault(''),
      'date':        string.withDefault(''),
      'dayLabel':    string.withDefault(''),
      'startTime':   string.withDefault(''),
      'location':    string.withDefault(''),
      'kleedkamer':  string.withDefault(''),
      'mijnStatus':  string.withDefault('aangemeld'),
      'afmeldingen': listOf(afmeldingHandle),
    },
    state: {
      'localStatus': string.withDefault('aangemeld'),
    },
    body: Scaffold(
      appBar: AppBar(title: 'Training'),
      body: Column(
        name: 'TrainingDetailColumn',
        children: [Container(name: 'TrainingDetailPlaceholder')],
      ),
    ),
  );
}

void _wireTrainingDetailPage(FFProject project) {
  final wc = findPage(project, name: 'TrainingDetailPage');
  if (wc == null) return;
  final col = findDescendants(wc.node, (n) => n.name == 'TrainingDetailColumn').firstOrNull;
  if (col == null) return;
  final afmeldAction  = findCustomAction(project, name: 'AfmeldenTraining');
  final aanmeldAction = findCustomAction(project, name: 'AanmeldenTraining');
  if (afmeldAction == null || aanmeldAction == null) return;

  // Voeg de afmeldingen-param (List<Afmelding>) idempotent toe — ensurePage doet
  // dat niet voor een al bestaande pagina.
  if (!wc.params.values.any((p) => p.hasIdentifier() && p.identifier.name == 'afmeldingen')) {
    final afmStruct = project.backend.dataSchemaConfig.dataStructs
        .cast<FFDataStruct?>()
        .firstWhere((s) => s?.identifier.name == 'Afmelding', orElse: () => null);
    if (afmStruct != null) {
      final id = FFIdentifier(name: 'afmeldingen', key: generateRandomAlphaNumericString());
      final p = FFParameter(
        identifier: id,
        dataType: dataStructType(afmStruct.identifier.deepCopy()),
      );
      p.isList = true;
      wc.params[id.key] = p;
    }
  }

  // kleedkamer-param (string) idempotent toevoegen — ensurePage doet dat niet
  // voor een al bestaande pagina.
  if (!wc.params.values.any((p) => p.hasIdentifier() && p.identifier.name == 'kleedkamer')) {
    final id = FFIdentifier(name: 'kleedkamer', key: generateRandomAlphaNumericString());
    wc.params[id.key] = FFParameter(
      identifier: id,
      dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
    );
  }

  FFIdentifier? paramId(String name) {
    for (final p in wc.params.values) {
      if (p.hasIdentifier() && p.identifier.name == name) return p.identifier;
    }
    return null;
  }
  final schedIdP    = paramId('scheduleId');
  final dateP       = paramId('date');
  final dayLabelP   = paramId('dayLabel');
  final startTimeP  = paramId('startTime');
  final locationP   = paramId('location');
  final mijnStatusP  = paramId('mijnStatus');
  final afmeldingenP = paramId('afmeldingen');
  if (schedIdP == null || dateP == null || mijnStatusP == null ||
      dayLabelP == null || startTimeP == null || locationP == null) return;

  final localStatusField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'localStatus', orElse: () => null);
  if (localStatusField == null) return;
  final localStatusId = localStatusField.parameter.identifier;

  final authTokenId     = _findAppStateFieldId(project, 'authToken');
  final currentTeamIdId = _findAppStateFieldId(project, 'currentTeamId');
  if (authTokenId == null || currentTeamIdId == null) return;

  String gen() => generateRandomAlphaNumericString();
  FFVariable pParam(FFIdentifier id) =>
      varFromPageParam(id.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  FFVariable pState(FFIdentifier id) =>
      varFromPageState(id.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  // onLoad: localStatus = mijnStatus-param.
  wc.node.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  Actions.onPageLoadChain(
    wc.node,
    FFActionNode(
      key: gen(),
      action: Actions.updatePageState(
        project,
        widgetClassName: 'TrainingDetailPage',
        updates: [StateFieldUpdate.setFromVariable('localStatus', pParam(mijnStatusP))],
      ),
    ),
  );

  // Content (clear + rebuild elke push).
  col.children.clear();

  FFNode infoText(FFIdentifier pId, String name, UITextStyle style) {
    final t = UI.text('', name: name, style: style);
    t.props.text.textValue = FFStringValue(variable: pParam(pId));
    return t;
  }

  final reasonField =
      UI.textField(name: 'TrainReasonField', labelText: 'Reden (bij afmelden)', maxLines: 2);

  final statusText = UI.text('', name: 'TrainStatusText', style: UITextStyle.titleSmall);
  statusText.props.text.textValue = FFStringValue(variable: pState(localStatusId));

  FFVariable showWhen(String status) => codeExpressionVar(
        expression: "s == '$status'",
        arguments: [
          CodeExpressionArg(
            name: 's',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: pState(localStatusId)),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
      );

  final afmeldBtn  = UI.button('Afmelden', name: 'TrainAfmeldButton', width: double.infinity);
  final aanmeldBtn = UI.button('Aanmelden', name: 'TrainAanmeldButton', width: double.infinity);
  setConditionalVisibility(afmeldBtn, variable: showWhen('aangemeld'));
  setConditionalVisibility(aanmeldBtn, variable: showWhen('afgemeld'));

  void wireButton(FFNode btn, String endpoint, String okMsg, {required bool needsReason}) {
    // Via een NATIVE FF-endpoint (server-side geproxied -> geen browser-CORS).
    // Bij succes: melding + pagina sluiten. Bij mislukken: foutmelding, blijven.
    final dynVars = <String, FFVariable>{
      'token':      varFromAppState(authTokenId.deepCopy()),
      'scheduleId': pParam(schedIdP),
      'date':       pParam(dateP),
    };
    if (needsReason) {
      dynVars['reason'] = varFromTextFieldValue(reasonField.key);
    }

    final apiNode = Actions.apiCallNode(
      project,
      endpointName: endpoint,
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: dynVars,
      outputVariableName: '${btn.name}Out',
      nodeKey: btn.key,
      // Succes: melding -> trainingen herladen (zodat dashboard-teller/namen
      // direct kloppen) -> pagina sluiten. Mislukt: foutmelding, blijven.
      onSuccess: (ctx) => FFActionNode(
        key: gen(),
        action: Actions.snackBar(okMsg),
        followUpAction: Actions.apiCallNode(
          project,
          endpointName: 'GetTrainingsList',
          groupName: 'VoetbalPlannerAPI',
          dynamicVariables: {
            'token':  varFromAppState(authTokenId.deepCopy()),
            'teamId': varFromAppState(currentTeamIdId.deepCopy()),
          },
          outputVariableName: '${btn.name}Refresh',
          nodeKey: btn.key,
          onSuccess: (ctx2) => Actions.chain([
            Actions.updateAppState(project, updates: [
              StateFieldUpdate.setFromVariable('trainings', ctx2.responseVar),
            ]),
            Actions.navigateBack(),
          ]),
          onFailure: (ctx2) => Actions.chain([Actions.navigateBack()]),
        ),
      ),
      onFailure: (ctx) => Actions.chain([
        Actions.snackBar('Er ging iets mis — probeer het opnieuw of controleer je verbinding.'),
      ]),
    );

    FFActionNode rootNode;
    if (needsReason) {
      // Reden verplicht: leeg -> melding tonen en niet versturen.
      final reasonEmpty = codeExpressionVar(
        expression: "(r ?? '').trim().isEmpty",
        arguments: [
          CodeExpressionArg(
            name: 'r',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: varFromTextFieldValue(reasonField.key)),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
      );
      rootNode = Actions.conditional(
        condition: reasonEmpty,
        trueActions: FFActionNode(
          key: gen(),
          action: Actions.snackBar('Vul een reden in om je af te melden.'),
        ),
        falseActions: apiNode,
      );
    } else {
      rootNode = apiNode;
    }

    btn.triggerActions.removeWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    btn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: rootNode,
    ));
  }

  wireButton(afmeldBtn, 'AfmeldenTrainingApi', 'Je bent afgemeld voor deze training.', needsReason: true);
  wireButton(aanmeldBtn, 'AanmeldenTrainingApi', 'Je bent weer aangemeld voor deze training.', needsReason: false);

  // Afmeldlijst (naam + reden) — gevuld vanuit de afmeldingen-param.
  final afmeldChildren = <FFNode>[];
  if (afmeldingenP != null) {
    final afmLv = UI.listView(
      name: 'TrainAfmeldList',
      spacing: 4,
      dynamicSource: DynamicSource(variable: pParam(afmeldingenP), itemName: 'afm'),
    );
    afmLv.props.listView.shrinkWrapValue = FFBooleanValue(inputValue: true);

    final naamText = UI.text('', name: 'TrainAfmNaam', style: UITextStyle.bodyMedium);
    naamText.props.text.textValue =
        FFStringValue(variable: generatorVarField(afmLv.key, 'naam'));
    final redenText = UI.text('', name: 'TrainAfmReden',
        style: UITextStyle.bodySmall, color: UIColor.secondaryText);
    redenText.props.text.textValue =
        FFStringValue(variable: generatorVarField(afmLv.key, 'reden'));

    afmLv.children.add(UI.container(
      name: 'TrainAfmItem',
      padding: UIEdgeInsets.symmetric(vertical: 4),
      child: UI.column(
        name: 'TrainAfmItemCol',
        crossAxisAlignment: UICrossAxisAlignment.start,
        spacing: 1,
        children: [naamText, redenText],
      ),
    ));

    afmeldChildren.add(
        UI.text('Afmeldingen', name: 'TrainAfmHeader', style: UITextStyle.titleSmall));
    afmeldChildren.add(afmLv);
  }

  // Kleedkamer-rij (icoon + "Kleedkamer: X"), alleen zichtbaar als gevuld.
  final kleedkamerP = paramId('kleedkamer');
  FFNode? kleedkamerRow;
  if (kleedkamerP != null) {
    final kkText = UI.text('', name: 'TrainDetailKleedkamer',
        style: UITextStyle.bodyMedium);
    kkText.props.text.textValue = FFStringValue(
      variable: codeExpressionVar(
        expression: "((k ?? '') != '') ? ('Kleedkamer: ' + (k ?? '')) : ''",
        arguments: [
          CodeExpressionArg(
            name: 'k',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: pParam(kleedkamerP)),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.String)),
      ),
    );
    final kkRow = UI.row(
      name: 'TrainDetailKleedkamerRow',
      spacing: 6,
      crossAxisAlignment: UICrossAxisAlignment.center,
      children: [
        UI.icon('meeting_room', size: 16, color: UIColor.secondaryText),
        kkText,
      ],
    );
    setConditionalVisibility(
      kkRow,
      variable: conditionVar(
        pParam(kleedkamerP),
        FFCondition_Relation.NOT_EQUAL_TO,
        varFromConstant(FFConstantsVariable_ConstantValue.EMPTY_STRING),
      ).variable,
    );
    kleedkamerRow = kkRow;
  }

  col.children.addAll([
    infoText(dayLabelP, 'TrainDetailDay', UITextStyle.titleLarge),
    infoText(dateP, 'TrainDetailDate', UITextStyle.bodyMedium),
    infoText(startTimeP, 'TrainDetailTime', UITextStyle.bodyMedium),
    infoText(locationP, 'TrainDetailLoc', UITextStyle.bodyMedium),
    if (kleedkamerRow != null) kleedkamerRow,
    statusText,
    reasonField,
    afmeldBtn,
    aanmeldBtn,
    ...afmeldChildren,
  ]);
}

// Maakt de dashboard-trainingskaart aantikbaar → TrainingDetailPage met params.
void _wireTrainingCardNavigation(FFProject project) {
  final wc = findPage(project, name: 'DashboardPage');
  if (wc == null) return;
  if (project.getWidgetClassByName('TrainingDetailPage') == null) return;
  final listView =
      findDescendants(wc.node, (n) => n.name == 'DashboardTrainingsList').firstOrNull;
  if (listView == null) return;
  final card =
      findDescendants(wc.node, (n) => n.name == 'DashboardTrainingCard').firstOrNull;
  if (card == null) return;
  // Herbouw de onTap elke push (zodat nieuwe nav-params zoals 'afmeldingen' mee
  // komen i.p.v. dat we skippen omdat er al een onTap staat).
  card.triggerActions.removeWhere(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  );

  Actions.onTap(
    card,
    Actions.navigate(project, pageName: 'TrainingDetailPage', params: {
      'scheduleId': VariableParamValue(generatorVarField(listView.key, 'schedule_id')),
      'date':       VariableParamValue(generatorVarField(listView.key, 'date')),
      'dayLabel':   VariableParamValue(generatorVarField(listView.key, 'day_label')),
      'startTime':  VariableParamValue(generatorVarField(listView.key, 'start_time')),
      'location':    VariableParamValue(generatorVarField(listView.key, 'location')),
      'kleedkamer':  VariableParamValue(generatorVarField(listView.key, 'dressing_room')),
      'mijnStatus':  VariableParamValue(generatorVarField(listView.key, 'mijn_status')),
      'afmeldingen': VariableParamValue(generatorVarField(listView.key, 'afmeldingen')),
    }),
  );
}

// ─── WedstrijdDetailPage: af-/aanmelden met reden ────────────────────────────
// Voegt reden-veld + Afmelden/Aanmelden knoppen toe aan MatchInfoColumn. Status
// komt uit matchStatus (gemapt uit $.mijn_status); knoppen wisselen erop.
void _wireWedstrijdAfmelden(FFProject project) {
  final wc = findPage(project, name: 'WedstrijdDetailPage');
  if (wc == null) return;
  // MatchInfoColumn verliest z'n naam na de ConditionalBuilder-unwrap in
  // _bindWedstrijdDetailInfoTexts; val daarom terug op de kolom die de
  // opponent-infoRow bevat (zelfde fallback als die functie gebruikt).
  var infoColumn =
      findDescendants(wc.node, (n) => n.name == 'MatchInfoColumn').firstOrNull;
  infoColumn ??= findDescendants(
    wc.node,
    (n) => n.children.any((c) => c.name == 'MatchInfoRow_opponent'),
  ).firstOrNull;
  if (infoColumn == null) return;
  final afmeldAction  = findCustomAction(project, name: 'AfmeldenMatch');
  final aanmeldAction = findCustomAction(project, name: 'AanmeldenMatch');
  if (afmeldAction == null || aanmeldAction == null) return;

  // Idempotent: verwijder vorige instances.
  for (final k in findDescendants(
    infoColumn,
    (n) => const {
      'MatchAfmeldButton', 'MatchAanmeldButton', 'MatchReasonField', 'MatchAfmeldHeader',
    }.contains(n.name),
  ).map((n) => n.key).toList()) {
    removeByKey(wc.node, k);
  }

  FFIdentifier? matchIdP;
  for (final p in wc.params.values) {
    if (p.hasIdentifier() && p.identifier.name == 'matchId') {
      matchIdP = p.identifier;
      break;
    }
  }
  if (matchIdP == null) return;
  final matchId = matchIdP;

  final statusField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'matchStatus', orElse: () => null);
  if (statusField == null) return;
  final statusId = statusField.parameter.identifier;

  // matchMagAfmelden ('true'/'false'): is de gebruiker als lid/ouder aan het
  // team van deze wedstrijd gekoppeld? Knoppen alleen tonen als 'true'.
  _ensurePageStateField(wc, 'matchMagAfmelden', FFBaseDataType.String);
  final magField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'matchMagAfmelden', orElse: () => null);
  if (magField == null) return;
  final magId = magField.parameter.identifier;

  final authTokenId = _findAppStateFieldId(project, 'authToken');
  if (authTokenId == null) return;

  String gen() => generateRandomAlphaNumericString();
  FFVariable statusVar() =>
      varFromPageState(statusId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
  FFVariable magVar() =>
      varFromPageState(magId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  // Ook coach/beheerder (mag_opstelling) mag zich af-/aanmelden, niet alleen
  // het gekoppelde lid/ouder (mag_afmelden).
  _ensurePageStateField(wc, 'matchMagOpstelling', FFBaseDataType.String);
  final magOpsField = wc.classModel.stateFields
      .cast<FFWidgetClassStateField?>()
      .firstWhere((f) => f?.parameter.identifier.name == 'matchMagOpstelling', orElse: () => null);
  final magOpsId = magOpsField!.parameter.identifier;
  FFVariable magOpsVar() =>
      varFromPageState(magOpsId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);

  final reasonField =
      UI.textField(name: 'MatchReasonField', labelText: 'Reden (bij afmelden)', maxLines: 2);

  FFVariable showWhen(String status) => codeExpressionVar(
        expression: "s == '$status' && (m == 'true' || o == 'true')",
        arguments: [
          CodeExpressionArg(
            name: 's',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: statusVar()),
          ),
          CodeExpressionArg(
            name: 'o',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: magOpsVar()),
          ),
          CodeExpressionArg(
            name: 'm',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: magVar()),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
      );

  final afmeldBtn  = UI.button('Afmelden', name: 'MatchAfmeldButton', width: double.infinity);
  final aanmeldBtn = UI.button('Aanmelden', name: 'MatchAanmeldButton', width: double.infinity);
  setConditionalVisibility(afmeldBtn, variable: showWhen('aangemeld'));
  setConditionalVisibility(aanmeldBtn, variable: showWhen('afgemeld'));

  void wireButton(FFNode btn, String endpoint, String newStatus, String okMsg, {required bool needsReason}) {
    // Via een NATIVE FF-endpoint (server-side geproxied -> geen browser-CORS).
    // Bij succes: melding + status bijwerken; bij mislukken: foutmelding, status blijft.
    final dynVars = <String, FFVariable>{
      'token':   varFromAppState(authTokenId.deepCopy()),
      'matchId': varFromPageParam(matchId.deepCopy())..nodeKeyRef = FFNodeKeyReference(key: wc.node.key),
    };
    if (needsReason) {
      dynVars['reason'] = varFromTextFieldValue(reasonField.key);
    }

    final apiNode = Actions.apiCallNode(
      project,
      endpointName: endpoint,
      groupName: 'VoetbalPlannerAPI',
      dynamicVariables: dynVars,
      outputVariableName: '${btn.name}Out',
      nodeKey: btn.key,
      onSuccess: (ctx) => Actions.chain([
        Actions.snackBar(okMsg),
        Actions.updatePageState(
          project,
          widgetClassName: 'WedstrijdDetailPage',
          updates: [StateFieldUpdate.set('matchStatus', newStatus)],
        ),
      ]),
      onFailure: (ctx) => Actions.chain([
        Actions.snackBar('Er ging iets mis — probeer het opnieuw of controleer je verbinding.'),
      ]),
    );

    FFActionNode rootNode;
    if (needsReason) {
      final reasonEmpty = codeExpressionVar(
        expression: "(r ?? '').trim().isEmpty",
        arguments: [
          CodeExpressionArg(
            name: 'r',
            dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
            value: FFValue(variable: varFromTextFieldValue(reasonField.key)),
          ),
        ],
        returnType: FFParameter(dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean)),
      );
      rootNode = Actions.conditional(
        condition: reasonEmpty,
        trueActions: FFActionNode(
          key: gen(),
          action: Actions.snackBar('Vul een reden in om je af te melden.'),
        ),
        falseActions: apiNode,
      );
    } else {
      rootNode = apiNode;
    }

    btn.triggerActions.removeWhere(
      (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    );
    btn.triggerActions.add(FFTriggerActions(
      trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
      rootAction: rootNode,
    ));
  }

  wireButton(afmeldBtn, 'AfmeldenMatchApi', 'afgemeld', 'Je bent afgemeld voor deze wedstrijd.', needsReason: true);
  wireButton(aanmeldBtn, 'AanmeldenMatchApi', 'aangemeld', 'Je bent weer aangemeld voor deze wedstrijd.', needsReason: false);

  infoColumn.children.addAll([
    UI.text('Af-/aanmelden', name: 'MatchAfmeldHeader', style: UITextStyle.titleSmall),
    reasonField,
    afmeldBtn,
    aanmeldBtn,
  ]);
}
