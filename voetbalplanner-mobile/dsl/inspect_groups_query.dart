library;

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart' show findDescendants;
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart' show findCollection;

Future<void> main(List<String> args) async {
  String? apiKey;
  String? projectId;
  for (var i = 0; i < args.length; i++) {
    if (args[i] == '--api-key') apiKey = args[++i];
    if (args[i] == '--project-id') projectId = args[++i];
  }

  await flutterFlowAI(
    (app) {
      app.raw((project) {
        _inspectChatGroupsCollection(project);
        _inspectChatGroupsQuery(project);
        _inspectGroupsListUI(project);
        _inspectAppStateFields(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectChatGroupsCollection(FFProject project) {
  print('\n=== chatGroups Firestore collection schema ===');
  final coll = findCollection(project, name: 'chatGroups');
  if (coll == null) { print('NOT FOUND'); return; }
  print('id: ${coll.identifier.key}');
  print('fields:');
  for (final f in coll.fields.values) {
    print('  ${f.identifier.name} (key: ${f.identifier.key}) type: ${f.dataType.toProto3Json()}');
  }
}

void _inspectChatGroupsQuery(FFProject project) {
  print('\n=== chatGroups Firestore query in ChatsPage onLoad ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }

  void walkChain(FFActionNode node, String indent) {
    if (node.hasAction()) {
      final a = node.action;
      if (a.hasDatabase() && a.database.hasFirestoreQuery()) {
        final q = a.database.firestoreQuery;
        print('${indent}FirestoreQuery:');
        print('${indent}  collectionId: ${q.hasCollectionIdentifier() ? q.collectionIdentifier.name + " (key: " + q.collectionIdentifier.key + ")" : "NONE"}');
        print('${indent}  singleTimeQuery: ${q.singleTimeQuery}');
        print('${indent}  limit: ${q.limit}');
        print('${indent}  hasWhere: ${q.hasWhere()}');
        if (q.hasWhere()) {
          print('${indent}  where JSON: ${q.where.toProto3Json()}');
        }
        print('${indent}  outputVar: ${a.outputVariableName}');
      }
    }
    if (node.hasFollowUpAction()) walkChain(node.followUpAction, indent);
    if (node.hasConditionActions()) {
      for (final ta in node.conditionActions.trueActions) {
        if (ta.hasTrueAction()) walkChain(ta.trueAction, '$indent  [true] ');
      }
    }
  }

  for (final ta in wc.node.triggerActions) {
    if (!ta.hasTrigger() || ta.trigger.triggerType.name != 'ON_INIT_STATE') continue;
    if (ta.hasRootAction()) walkChain(ta.rootAction, '  ');
  }
}

void _inspectGroupsListUI(FFProject project) {
  print('\n=== ChatsGroupsList generator + visibility ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }

  final list = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsList').firstOrNull;
  if (list == null) { print('ChatsGroupsList NOT FOUND'); return; }
  print('key: ${list.key}');
  print('hasGeneratorVariable: ${list.hasGeneratorVariable()}');
  if (list.hasGeneratorVariable()) {
    print('generator JSON: ${list.generatorVariable.toProto3Json()}');
  }
  print('hasVisibility: ${list.props.hasVisibility()}');
  if (list.props.hasVisibility()) {
    print('visibility JSON: ${list.props.visibility.toProto3Json()}');
  }
  print('children count: ${list.children.length}');
  for (final c in list.children) {
    print('  child: ${c.name} (${c.type})');
  }

  // Also check the container
  final container = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsListContainer').firstOrNull;
  if (container != null) {
    print('\nChatsGroupsListContainer:');
    print('  hasVisibility: ${container.props.hasVisibility()}');
    print('  children: ${container.children.map((c) => c.name).join(", ")}');
  }
}

void _inspectAppStateFields(FFProject project) {
  print('\n=== AppState: currentTeamId + currentTeamName ===');
  for (final f in project.appState.fields) {
    final name = f.parameter.identifier.name;
    if (name == 'currentTeamId' || name == 'currentTeamName') {
      print('  $name (key: ${f.parameter.identifier.key}) persisted: ${f.persisted}');
    }
  }
}
