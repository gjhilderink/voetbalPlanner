library;

import 'package:flutterflow_ai/flutterflow_ai.dart';
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
        final wc = findPage(project, name: 'ChatsPage');
        if (wc == null) { print('ChatsPage NOT FOUND'); return; }

        // 1. ChatsDirectStripContainer
        print('=== ChatsDirectStripContainer ===');
        final stripContainer = findDescendants(wc.node, (n) => n.name == 'ChatsDirectStripContainer').firstOrNull;
        if (stripContainer == null) {
          print('  NOT FOUND');
        } else {
          print('  children count: ${stripContainer.children.length}');
          _dumpTree(stripContainer, '  ');
        }

        // 2. ListViews shrinkWrap status
        print('\n=== ListViews shrinkWrap ===');
        for (final name in ['ChatsGroupsList', 'ChatsConversationsList', 'ChatsStaffGroupsList', 'ChatsDirectMemberList']) {
          final node = findDescendants(wc.node, (n) => n.name == name).firstOrNull;
          if (node == null) {
            print('  $name: NOT FOUND');
          } else {
            print('  $name: shrinkWrap=${node.props.listView.shrinkWrapValue.inputValue}');
            print('  $name: props JSON=${node.props.listView.toProto3Json()}');
          }
        }

        // 3. chatGroups Firestore query in ON_INIT_STATE
        print('\n=== chatGroups query in trigger ===');
        for (final ta in wc.node.triggerActions) {
          if (!ta.hasTrigger() || ta.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) continue;
          if (!ta.hasRootAction()) continue;
          _findFirestoreQueryNodes(ta.rootAction, 'chatGroups');
        }

        // 4. SwapMember struct fields
        print('\n=== SwapMember struct fields ===');
        for (final s in project.backend.dataSchemaConfig.dataStructs) {
          if (s.identifier.name == 'SwapMember') {
            for (final f in s.fields) {
              print('  ${f.identifier.name} (key: ${f.identifier.key})');
            }
          }
        }
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
    commitMessage: 'inspect only',
    validationFilter: (_) => false,
  );
}

void _dumpTree(FFNode node, String indent) {
  print('${indent}${node.name} (${node.type}) key=${node.key}');
  if (node.type == FFWidgetType.ListView) {
    print('${indent}  shrinkWrap=${node.props.listView.shrinkWrapValue.inputValue}');
    if (node.hasGeneratorVariable()) {
      print('${indent}  generator=${node.generatorVariable.toProto3Json()}');
    }
    print('${indent}  children: ${node.children.length}');
  }
  for (final c in node.children) {
    _dumpTree(c, '$indent  ');
  }
}

void _findFirestoreQueryNodes(FFActionNode node, String collectionName) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasDatabase()) {
      final db = a.database;
      if (db.hasFirestoreQuery()) {
        final colName = db.firestoreQuery.hasCollectionIdentifier()
            ? db.firestoreQuery.collectionIdentifier.name : '?';
        if (colName == collectionName) {
          print('  Found query (out=${a.outputVariableName}): ${db.firestoreQuery.toProto3Json()}');
        }
      }
    }
  }
  if (node.hasFollowUpAction()) _findFirestoreQueryNodes(node.followUpAction, collectionName);
  if (node.hasConditionActions()) {
    if (node.conditionActions.hasFalseAction()) {
      _findFirestoreQueryNodes(node.conditionActions.falseAction, collectionName);
    }
  }
}
