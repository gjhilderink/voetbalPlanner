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
        final wc = findPage(project, name: 'GroupChatPage');
        if (wc == null) { print('GroupChatPage NOT FOUND'); return; }

        // 1. AppBar presence + params
        print('=== GroupChatPage params ===');
        for (final p in wc.params.values) {
          if (p.hasIdentifier()) print('  ${p.identifier.name} (key: ${p.identifier.key})');
        }

        print('\n=== AppBar ===');
        final appBarSlot = wc.node.childPropertyMap['appBar'];
        if (appBarSlot == null || appBarSlot.keyRefs.isEmpty) {
          print('  NO APPBAR');
        } else {
          final appBarKey = appBarSlot.keyRefs.first.key;
          final appBar = findByKey(wc.node, appBarKey);
          if (appBar == null) { print('  AppBar key $appBarKey not found in tree'); }
          else {
            print('  key: ${appBar.key}');
            print('  children count: ${appBar.children.length}');
            for (final c in appBar.children) {
              print('  child: ${c.name} (${c.type}) key=${c.key}');
            }
            final actionsSlot = appBar.childPropertyMap['actions'];
            if (actionsSlot == null) {
              print('  actions slot: EMPTY');
            } else {
              print('  actions keys: ${actionsSlot.keyRefs.map((r) => r.key).join(', ')}');
            }
          }
        }

        // 2. DeleteGroupButton tap triggers
        print('\n=== DeleteGroupButton triggers ===');
        final btn = findDescendants(wc.node, (n) => n.name == 'DeleteGroupButton').firstOrNull;
        if (btn == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${btn.key}');
          print('  triggers: ${btn.triggerActions.length}');
          for (final ta in btn.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '    ');
          }
        }

        // 3. AppState pendingGroupName field
        print('\n=== AppState pendingGroupName ===');
        for (final f in project.appState.fields) {
          if (f.parameter.identifier.name == 'pendingGroupName') {
            print('  key: ${f.parameter.identifier.key}');
            print('  type: ${f.parameter.dataType}');
          }
        }

        // 4. DeleteChatGroup custom action
        print('\n=== DeleteChatGroup custom action ===');
        final ca = findCustomAction(project, name: 'DeleteChatGroup');
        if (ca == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${ca.identifier.key}');
          print('  args: ${ca.arguments.map((a) => a.identifier.name).join(', ')}');
          print('  code snippet: ${ca.code.substring(0, 100)}...');
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

void _dumpChain(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasDatabase()) {
      if (a.database.hasApiCall()) {
        print('${indent}[API: ${a.database.apiCall.endpointIdentifier.name}]');
      } else if (a.database.hasFirestoreQuery()) {
        print('${indent}[FirestoreQuery: ${a.database.firestoreQuery.collectionIdentifier.name}]');
      } else {
        print('${indent}[database: ${a.database.whichAction().name}]');
      }
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => '${u.fieldIdentifier.name}=${u.setValue.toProto3Json()}').join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
    } else if (a.hasNavigate()) {
      print('${indent}[Navigate: ${a.navigate.toProto3Json().toString().substring(0, 100)}]');
    } else if (a.hasAlertDialog()) {
      print('${indent}[AlertDialog: ${a.alertDialog.toProto3Json()}]');
    } else {
      print('${indent}[other: ${a.whichAction().name}]');
    }
  }
  if (node.hasFollowUpAction()) _dumpChain(node.followUpAction, '$indent  → ');
  if (node.hasConditionActions()) {
    print('${indent}  [Condition] true:');
    for (final ta in node.conditionActions.trueActions) {
      if (ta.hasTrueAction()) _dumpChain(ta.trueAction, '$indent    ');
    }
    if (node.conditionActions.hasFalseAction()) {
      print('${indent}  [Condition] false:');
      _dumpChain(node.conditionActions.falseAction, '$indent    ');
    }
  }
}
