library;

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart' show findCollection;
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart' show findDescendants;

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
        _inspectChatConversationsSchema(project);
        _inspectActionChainDetails(project);
        _inspectGroupsListHeight(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectChatConversationsSchema(FFProject project) {
  print('\n=== chatConversations collection schema ===');
  final coll = findCollection(project, name: 'chatConversations');
  if (coll == null) { print('NOT FOUND'); return; }
  print('id: ${coll.identifier.key}');
  print('fields:');
  for (final f in coll.fields.values) {
    print('  ${f.identifier.name} (key: ${f.identifier.key}) type: ${f.dataType.toProto3Json()}');
  }
}

void _inspectActionChainDetails(FFProject project) {
  print('\n=== ChatsPage ON_INIT_STATE full action details ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }

  void dumpNode(FFActionNode node, String indent) {
    if (node.hasAction()) {
      final a = node.action;
      if (a.hasDatabase() && a.database.hasFirestoreQuery()) {
        final q = a.database.firestoreQuery;
        print('${indent}[Firestore: ${q.collectionIdentifier.name} singleTime=${q.singleTimeQuery} out=${a.outputVariableName}]');
        if (q.hasWhere()) {
          for (final f in q.where.filters) {
            if (f.hasBaseFilter()) {
              final bf = f.baseFilter;
              print('${indent}  WHERE: ${bf.collectionFieldIdentifier.name} (key:${bf.collectionFieldIdentifier.key}) ${bf.relation.name} [appState var]');
            }
          }
        } else {
          print('${indent}  (no WHERE)');
        }
      } else if (a.hasLocalStateUpdate()) {
        final fields = a.localStateUpdate.updates.map((u) {
          final fn = u.fieldIdentifier.name;
          final fk = u.fieldIdentifier.key;
          return '$fn(key:$fk)';
        }).join(', ');
        print('${indent}[SetState: $fields type=${a.localStateUpdate.stateVariableType.name}]');
      } else if (a.hasCustomAction()) {
        print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
      } else if (a.hasDatabase() && a.database.hasApiCall()) {
        print('${indent}[API: ${a.database.apiCall.endpointIdentifier.name} out=${a.outputVariableName}]');
      } else {
        final json = a.toProto3Json().toString();
        print('${indent}[other: ${json.length > 80 ? json.substring(0, 80) : json}]');
      }
    } else if (node.hasConditionActions()) {
      print('${indent}[Condition branches: ${node.conditionActions.trueActions.length}]');
    }
    if (node.hasFollowUpAction()) dumpNode(node.followUpAction, '$indent  ');
  }

  for (final ta in wc.node.triggerActions) {
    if (!ta.hasTrigger() || ta.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) continue;
    print('ON_INIT_STATE:');
    if (ta.hasRootAction()) dumpNode(ta.rootAction, '  ');
  }
}

void _inspectGroupsListHeight(FFProject project) {
  print('\n=== ChatsGroupsListContainer and children (raw props JSON) ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) return;

  final container = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsListContainer').firstOrNull;
  if (container == null) { print('ChatsGroupsListContainer NOT FOUND'); return; }
  print('ChatsGroupsListContainer key: ${container.key}');
  print('  props JSON: ${container.props.toProto3Json().toString().substring(0, 200)}');

  for (final child in container.children) {
    print('  child: ${child.name} (${child.type})');
    print('    props JSON: ${child.props.toProto3Json().toString().substring(0, 200)}');
    print('    hasGeneratorVariable: ${child.hasGeneratorVariable()}');
  }

  // Also check the ChatsDirectStripContainer
  final directContainer = findDescendants(wc.node, (n) => n.name == 'ChatsDirectStripContainer').firstOrNull;
  if (directContainer != null) {
    print('\nChatsDirectStripContainer:');
    print('  children count: ${directContainer.children.length}');
    for (final c in directContainer.children) {
      print('  child: ${c.name} props: ${c.props.toProto3Json().toString().substring(0, 150)}');
    }
  }
}
