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

        // DirectMemberChip tap triggers
        print('=== DirectMemberChip triggers ===');
        final chip = findDescendants(wc.node, (n) => n.name == 'DirectMemberChip').firstOrNull;
        if (chip == null) {
          print('  NOT FOUND');
        } else {
          for (final ta in chip.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '    ');
          }
          if (chip.triggerActions.isEmpty) print('  (no triggers)');
        }

        // GroupChip tap triggers
        print('\n=== GroupChip triggers ===');
        final groupChip = findDescendants(wc.node, (n) => n.name == 'GroupChip').firstOrNull;
        if (groupChip == null) {
          print('  NOT FOUND');
        } else {
          for (final ta in groupChip.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '    ');
          }
          if (groupChip.triggerActions.isEmpty) print('  (no triggers)');
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
        print('${indent}[API: ${a.database.apiCall.endpointIdentifier.name}, out=${a.outputVariableName}]');
      } else if (a.database.hasFirestoreQuery()) {
        print('${indent}[FirestoreQuery: ${a.database.firestoreQuery.collectionIdentifier.name}]');
      } else {
        print('${indent}[database: ${a.database.whichAction().name}]');
      }
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => u.fieldIdentifier.name).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
    } else if (a.hasNavigate()) {
      print('${indent}[Navigate: ${a.navigate.toProto3Json().toString().substring(0, 80)}]');
    } else {
      print('${indent}[other: ${a.whichAction().name}]');
    }
  }
  if (node.hasFollowUpAction()) {
    _dumpChain(node.followUpAction, '$indent  → ');
  }
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
