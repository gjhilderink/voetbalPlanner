library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/client/project_error.dart' show ProjectError;
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart'
    show findCollection, findCollectionField, addCollectionField;
import 'package:flutterflow_ai/src/helpers/data_schema_helpers.dart'
    show addDataStruct, structField, findDataStruct;
import 'package:flutterflow_ai/src/helpers/project_helpers.dart'
    show addStateField;
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
    show findDescendants, removeByKey, insertBeforeKey, getPropertyChild,
         unwrap, findParentByKey;
import 'package:flutterflow_ai/src/helpers/widget_helpers.dart'
    show setConditionalVisibility;
import 'package:flutterflow_ai/src/helpers/nav_bar_helpers.dart'
    show setNavBarEnabled, addNavBarPage, removeNavBarPage, listNavBarPages, reorderNavBarPage;
import 'package:flutterflow_ai/src/helpers/widget_class_param_helpers.dart'
    show removePageParameter;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';
import 'package:flutterflow_ai/src/ui/actions.dart' show Actions;
import 'package:flutterflow_ai/src/ui/ui.dart' show UI;
import 'package:flutterflow_ai/src/ui/ui_types.dart'
    show UIBoxFit, UIColor, UITextStyle, UIMainAxisAlignment, UICrossAxisAlignment, UIEdgeInsets,
         DynamicSource;
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
    for (final key in const [
      'Button_wvz4j2lc', // Uitloggen
      'Button_6scqjj2p', // HandleidingButton
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
      },
    );
  } catch (_) {}

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

  // ── Chat custom actions ───────────────────────────────────────────────────────
  // Declared at DSL level so CallCustomAction.named(...) in _buildChatDetailPage
  // and app.editPageOnLoad resolve correctly at DSL compile time.
  // _addChatInfrastructure (raw phase) later calls updateCustomAction to keep the
  // code in sync on every push.
  try {
    app.customAction('SendMessage', args: {}, code: _kSendMessageCode,
        description: 'Send a chat message and update conversation last-message metadata.');
  } catch (_) {}
  try {
    app.customAction('GetOrCreateDirectConversation', args: {}, code: _kGetOrCreateConvCode,
        description: 'Find or create a direct (1-on-1) chatConversations document.');
  } catch (_) {}
  try {
    app.customAction('InitializeTeamConversation', args: {}, code: _kInitTeamConvCode,
        description: 'Ensure team chatConversation exists and cache its ID in AppState.');
  } catch (_) {}

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
  app.raw((project) => _addDocumentationEndpoint(project));
  _buildDocumentatiePage(app, documentSection);
  app.raw((project) => _wireDocumentationPageLoad(project));
  app.raw((project) => _addHandleidingButton(project));

  // ─── Wissel (swap) feature ────────────────────────────────────────────────
  app.raw((project) => _addSwapStructFields(project));
  app.raw((project) => _addSwapParamsToBarDutyCard(project));

  final swapMember = ff.Structs.swapMember;
  try {
    app.struct('SwapMember', {
      'id':   string,
      'name': string,
    });
  } catch (_) {}

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
    _wireChatDetailFilters(project);
    // Group creation wiring.
    _addMembersFieldToChatGroups(project);
    _wireCreateGroupPageLoad(project);
    _wireCreateGroupMembersBinding(project);
    _wireCreateGroupSubmitAction(project);
    _wireChatsPageGroupsList(project);
    _wireNewGroupButton(project);
    // Staff groups (Laravel-managed) in chat — state field already ensured above.
    _wireChatsPageStaffGroupsLoad(project);
    _wireChatsPageStaffGroupsList(project);
    _makeChatsPageBodyScrollable(project);
    _fixMemberChipStyle(project);
    _removeChatsDebugBanner(project);
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
  _buildSwapRequestCard(app, swapRequest);
  _buildWisselAanvraagPage(app, swapMember);
  _buildWisselVerzoekenPage(app, swapRequest);

  // MatchCard: add matchId + coachName params and navigate internally.
  app.editComponentParams(ff.Components.matchCard, (params) {
    params.ensureParam('matchId', string.withDefault(''), description: 'Match ID for navigation');
    params.ensureParam('coachName', string.withDefault(''), description: 'Coach name');
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
  });

  // WedstrijdDetailPage must exist before match navigation can be set up.
  _buildWedstrijdDetailPage(app);
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
  });
  app.raw((project) {
    _debugStructsAndEndpoints(project);
    _wireWedstrijdDetailPageLoad(project);
    _bindWedstrijdDetailAppBarTitle(project);
    _bindWedstrijdDetailInfoTexts(project);
    _fixMatchInfoWidth(project);
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
    _wireRijschemaCardDriverRow(project);
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
  // Fix layout: outer Column was mainAxisSize.min so the ListView collapsed to zero height.
  app.raw((project) => _fixWisselAanvraagPageLayout(project));

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

  // ── Dashboard page ─────────────────────────────────────────────────────────────
  // Shows upcoming wedstrijden + bardiensten on one screen; home icon in NavBar.
  _buildDashboardPage(app, ff.Structs.footMatch, ff.Structs.barDuty);
  app.raw((project) {
    _addDashboardAppBar(project);
    _buildDashboardContent(project);
    _wireDashboardLoad(project);
  });

  // Apply club primary color to all AppBar backgrounds; set back button + title to white.
  // Runs last so the AppBar nodes already exist from all the preceding wiring steps.
  app.raw((project) => _applyBrandingToAllAppBars(project));

  // Apply club primary color to all buttons: fill color + white text + generous padding.
  app.raw((project) => _applyBrandingToAllButtons(project));

  // Force NavBar items LAST — after all other raw mutations that touch the scaffold.
  app.raw((project) => _forceDashboardNavBarItem(project));
  app.raw((project) => _forceChatsNavBarItem(project));
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

  if (existingCards.isNotEmpty) {
    // Card already exists — update secondary color and inject ProfielTeam if missing.
    if (secondaryColorId != null) {
      _setContainerColor(existingCards.first,
          colorFromStringVar(varFromAppState(secondaryColorId.deepCopy())));
    }
    final infoContent = findDescendants(existingCards.first, (n) => n.name == 'ProfielInfoContent').firstOrNull;
    if (infoContent != null && currentTeamNameId != null) {
      final alreadyHasTeam = findDescendants(infoContent, (n) => n.name == 'ProfielTeam').isNotEmpty;
      if (!alreadyHasTeam) {
        infoContent.children.add(_boundText('ProfielTeam', currentTeamNameId, 'Elftal', UITextStyle.bodyMedium));
      }
    }
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
        _boundText('ProfielNaam',  userNameId,       'Naam',        UITextStyle.titleLarge),
        _boundText('ProfielEmail', userEmailId,      'E-mailadres', UITextStyle.bodyMedium),
        _boundText('ProfielClub',  clubNameId,       'Club',        UITextStyle.bodyMedium),
        _boundText('ProfielTeam',  currentTeamNameId, 'Elftal',     UITextStyle.bodyMedium),
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
void _resetGroupChatPageAppBar(FFProject project) {
  final wc = findPage(project, name: 'GroupChatPage');
  if (wc == null) return;
  final existing = getPropertyChild(wc.node, 'appBar');
  if (existing != null) removeByKey(wc.node, existing.key);
  wc.node.childPropertyMap.remove('appBar');
  FFIdentifier? titleParamId;
  for (final param in wc.params.values) {
    if (param.hasIdentifier() && param.identifier.name == 'groupName') {
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
  final batch = db.batch();

  final msgRef = db.collection('chatMessages').doc();
  batch.set(msgRef, {
    'conversationId': conversationId,
    'text': text,
    'senderId': FFAppState().authToken,
    'senderName': FFAppState().userName,
    'createdAt': FieldValue.serverTimestamp(),
  });

  final convRef = db.collection('chatConversations').doc(conversationId);
  batch.update(convRef, {
    'lastMessage': text,
    'lastMessageAt': FieldValue.serverTimestamp(),
  });

  await batch.commit();

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
  final myId    = FFAppState().authToken;
  final otherId = FFAppState().pendingDirectUserId;
  final otherName = FFAppState().pendingDirectUserName;
  final teamId  = FFAppState().currentTeamId;

  if (myId.isEmpty || otherId.isEmpty || teamId.isEmpty) return;

  // Canonical ID: sorted user IDs + teamId for uniqueness across teams.
  final ids = [myId, otherId]..sort();
  final convId = '${teamId}_${ids[0]}_${ids[1]}';

  final db = FirebaseFirestore.instance;
  final docRef = db.collection('chatConversations').doc(convId);
  final doc = await docRef.get();

  if (!doc.exists) {
    await docRef.set({
      'conversationId': convId,
      'type': 'direct',
      'teamId': teamId,
      'title': otherName,
      'participantIds': [myId, otherId],
      'lastMessage': '',
      'lastMessageAt': FieldValue.serverTimestamp(),
      'createdAt': FieldValue.serverTimestamp(),
    });
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
      'participantIds': [],
      'lastMessage': '',
      'lastMessageAt': FieldValue.serverTimestamp(),
      'createdAt': FieldValue.serverTimestamp(),
    });
  }

  FFAppState().update(() {
    FFAppState().currentConversationId = convId;
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

import 'package:cloud_firestore/cloud_firestore.dart';

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
      'lastMessage': '',
      'updatedAt': FieldValue.serverTimestamp(),
    });
  }

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

Future<void> createChatGroup(List<String> memberIds) async {
  final groupName = FFAppState().pendingGroupName;
  final teamId    = FFAppState().currentTeamId;
  final userName  = FFAppState().userName;

  if (groupName.isEmpty) return;

  await FirebaseFirestore.instance.collection('chatGroups').add({
    'name':      groupName,
    'teamId':    teamId,
    'members':   memberIds,
    'createdBy': userName,
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
      ],
      code: _createChatGroupCode,
    );
  } else {
    updateCustomAction(project, name: 'CreateChatGroup', code: _createChatGroupCode);
  }

  // Ensure the memberIds parameter has the stable key _kMemberIdsParamKey so
  // _wireCreateGroupSubmitAction can reference it by key in actionParameters.
  // (If addCustomAction set an empty key, this upgrades it idempotently.)
  {
    final caIdx = project.customCode.customActions
        .indexWhere((ca) => ca.identifier.name == 'CreateChatGroup');
    if (caIdx >= 0) {
      final ca = project.customCode.customActions[caIdx];
      final argIdx = ca.arguments
          .indexWhere((a) => a.identifier.name == 'memberIds');
      if (argIdx >= 0 && ca.arguments[argIdx].identifier.key != _kMemberIdsParamKey) {
        final caCopy = ca.deepCopy();
        caCopy.arguments[argIdx].identifier.key = _kMemberIdsParamKey;
        project.customCode.customActions[caIdx] = caCopy;
      }
    }
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

  // Navigate to DirectChatPage with member id + name from the list generator variable.
  final navigateAction = Actions.navigate(
    project,
    pageName: 'DirectChatPage',
    params: {
      'memberId':   VariableParamValue(generatorVarField(memberList.key, 'id')),
      'memberName': VariableParamValue(generatorVarField(memberList.key, 'name')),
    },
  );

  // Member name text bound to generator variable 'name' field.
  final nameText = UI.text('', name: 'MemberChipName', style: UITextStyle.bodySmall);
  nameText.props.text.textValue =
      FFStringValue(variable: generatorVarField(memberList.key, 'name'));

  // Avatar placeholder (40×40 circle).
  final avatar = UI.container(name: 'MemberAvatar', width: 40, height: 40, borderRadius: 20);

  // Column: avatar above name, vertically centered.
  final chipCol = UI.column(
    name: 'MemberChipColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    children: [avatar, nameText],
  );

  // Chip container (60px wide) — tap navigates to DirectChatPage.
  final chip = UI.container(name: 'MemberChip', width: 60, borderRadius: 8, child: chipCol);
  Actions.onTap(chip, navigateAction);

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
      ('isFruitHero',  FFBaseDataType.Boolean),
      ('isDriver',     FFBaseDataType.Boolean),
      ('fruitHeroId',  FFBaseDataType.String),
      ('driverNames',  FFBaseDataType.String),
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
    'apiStatus',
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
          'matchNotes':         r'$.notes',
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
    // Find the inner column that owns the MatchInfoRow_* children and prepend status text.
    final innerColumn = findDescendants(wc.node, (n) =>
        n.children.any((c) => c.name == 'MatchInfoRow_opponent')).firstOrNull;
    if (innerColumn != null) {
      final apiStatusNode = UI.text('api:?', name: 'MatchApiStatus', style: UITextStyle.bodySmall, color: UIColor.secondaryText);
      final apiStatusVar = stateVar('apiStatus');
      if (apiStatusVar != null) apiStatusNode.props.text.textValue = FFStringValue(variable: apiStatusVar);
      if (!innerColumn.children.any((c) => c.name == 'MatchApiStatus')) {
        innerColumn.children.insert(0, apiStatusNode);
      }
    }
    return;
  }

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
    // Main NavBar pages (have their own AppBar with page title)
    'DashboardPage', 'WedstrijdenPage', 'BardienPage', 'RijschemaPage', 'ProfielPage',
    // Detail / sub-pages (back button + dynamic title)
    'WedstrijdDetailPage', 'BardienDetailPage', 'RijschemaDetailPage',
    'DirectChatPage', 'DocumentatiePage', 'TeamChatPage',
    'ChatsPage', 'GroupChatPage', 'CreateGroupPage',
    'WisselAanvraagPage',
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

// Appends a GetBanners API call to the end of the page's existing page-load chain.
// Uses _appendToFirstPageLoadChain so it lands in the same SchedulerBinding callback
// as the existing matches/duties load, not in a second (dead) trigger.
void _wireBannerPageLoad(
  FFProject project,
  FFWidgetClass wc,
  String widgetClassName,
  String position,
) {
  _appendToFirstPageLoadChain(
    wc.node,
    Actions.apiCallNode(
      project,
      endpointName: 'GetBanners',
      groupName: 'VoetbalPlannerAPI',
      outputVariableName: 'bannersLoad',
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
  final pendingUserNameId  = _findAppStateFieldId(project, 'pendingDirectUserName');
  final currentConvId      = _findAppStateFieldId(project, 'currentConversationId');
  if (pendingUserIdId == null || pendingUserNameId == null || currentConvId == null) return;

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
  //   1. UpdateAppState pendingDirectUserId = member.id
  //   2. UpdateAppState pendingDirectUserName = member.name
  //   3. CallCustomAction GetOrCreateDirectConversation
  //   4. Navigate to ChatDetailPage
  final tapChain = FFActionNode(
    key: generateRandomAlphaNumericString(),
    action: FFAction(
      key: generateRandomAlphaNumericString(),
      localStateUpdate: FFLocalStateUpdate(
        updates: [
          FFLocalStateFieldUpdate(
            fieldIdentifier: pendingUserIdId.deepCopy(),
            setValue: FFValue(variable: generatorVarField(memberList.key, 'id')),
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
  final chipCol = UI.column(
    name: 'DirectMemberColumn',
    mainAxisAlignment: UIMainAxisAlignment.center,
    children: [avatar, nameText],
  );

  final chip = UI.container(name: 'DirectMemberChip', width: 60, borderRadius: 8, child: chipCol);
  Actions.onTapChain(chip, tapChain);
  memberList.children.add(chip);

  final strip = UI.container(name: 'ChatsDirectStripInner', height: 88, child: memberList);
  container.children.add(strip);
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
                  visible: Equals(ItemRef()['senderId'], AppState(ff.AppState.authToken)),
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
                          'senderId':   AppState(ff.AppState.authToken),
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

// Applies teamId == AppState.currentTeamId filter to all Firestore queries on ChatsPage.
// Called after app.editPageOnLoad has added the chatGroups query.
void _wireChatsPageGroupsFilter(FFProject project) {
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final currentTeamIdFieldId = _findAppStateFieldId(project, 'currentTeamId');
  if (currentTeamIdFieldId == null) return;

  final teamIdField = findCollectionField(
    project,
    collectionName: 'chatGroups',
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

/// Stable key for the CreateChatGroup custom action's memberIds parameter.
/// Written by _addChatInfrastructure and read by _wireCreateGroupSubmitAction.
const _kMemberIdsParamKey = 'cgmembids1';

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

  final groupNameId         = _findPageStateFieldId(project, 'CreateGroupPage', 'groupName');
  final selectedMemberIdsId = _findPageStateFieldId(project, 'CreateGroupPage', 'selectedMemberIds');
  final pendingGroupNameId  = _findAppStateFieldId(project, 'pendingGroupName');
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
          n.action.customAction.argumentValues.arguments.containsKey(_kMemberIdsParamKey)) {
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

  // Build argumentValues for CreateChatGroup: memberIds = selectedMemberIds.
  final argValues = FFFunctionCallValues();
  argValues.arguments[_kMemberIdsParamKey] = FFFunctionCallValues_FFArgument(
    value: FFValue(variable: selectedMemberIdsVar),
  );

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
  if (container == null || container.children.isNotEmpty) return;

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

  final opponentText = UI.text('', name: 'DashboardMatchOpponent', style: UITextStyle.bodyMedium);
  opponentText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'opponent'));

  final dateText = UI.text('', name: 'DashboardMatchDate', style: UITextStyle.bodySmall);
  dateText.props.text.textValue = FFStringValue(variable: generatorVarField(listView.key, 'matchDatetime'));

  final card = UI.container(
    name: 'DashboardMatchCard',
    padding: UIEdgeInsets.all(12),
    borderRadius: 8,
    color: UIColor.secondaryBackground,
    child: UI.row(
      name: 'DashboardMatchRow',
      spacing: 12,
      children: [
        UI.icon('sports_soccer', size: 24, color: UIColor.primary),
        UI.column(
          name: 'DashboardMatchInfo',
          crossAxisAlignment: UICrossAxisAlignment.start,
          spacing: 4,
          children: [opponentText, dateText],
        ),
      ],
    ),
  );

  listView.children.add(card);
  container.children.add(listView);
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
    dynamicVariables: {
      'token': varFromAppState(authTokenId.deepCopy()),
      if (currentTeamIdId != null) 'teamId': varFromAppState(currentTeamIdId.deepCopy()),
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

  // Wire directly (no auth guard): DashboardPage requires authentication, so authToken
  // is always present when this fires. onFailure handlers handle any 401 gracefully.
  Actions.onPageLoadChain(wc.node, matchesNode);
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
