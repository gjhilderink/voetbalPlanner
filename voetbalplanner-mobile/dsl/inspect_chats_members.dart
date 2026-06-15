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

        // 1. ON_INIT_STATE chains
        print('=== ChatsPage ON_INIT_STATE triggers ===');
        final inits = wc.node.triggerActions
            .where((t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE)
            .toList();
        print('Count: ${inits.length}');
        for (var i = 0; i < inits.length; i++) {
          print('--- trigger $i ---');
          if (inits[i].hasRootAction()) _dumpChain(inits[i].rootAction, '  ');
        }

        // 2. DirectMemberChip visibility
        print('\n=== DirectMemberChip visibility condition ===');
        final chip = findDescendants(wc.node, (n) => n.name == 'DirectMemberChip').firstOrNull;
        if (chip == null) { print('DirectMemberChip NOT FOUND'); }
        else {
          if (chip.props.hasVisibility() && chip.props.visibility.hasVisibleValue()) {
            print('  visibilitySet: true');
            print('  visibleValue: ${chip.props.visibility.visibleValue.toProto3Json()}');
          } else {
            print('  visibilitySet: false (always visible)');
          }
        }

        // 3. ChatsDirectMemberList — dump listView props as JSON
        print('\n=== ChatsDirectMemberList node ===');
        final list = findDescendants(wc.node, (n) => n.name == 'ChatsDirectMemberList').firstOrNull;
        if (list == null) { print('NOT FOUND'); }
        else {
          print('  key: ${list.key}  type: ${list.type}');
          final j = list.props.toProto3Json().toString();
          print('  props(200): ${j.length > 200 ? j.substring(0, 200) : j}');
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
    final out = a.outputVariableName.isNotEmpty ? ' out="${a.outputVariableName}"' : '';
    if (a.hasDatabase()) {
      if (a.database.hasApiCall()) {
        final ep = a.database.apiCall.endpointIdentifier.name;
        final _j = a.database.apiCall.toProto3Json().toString(); final vars = _j.substring(0, _j.length < 80 ? _j.length : 80);
        print('${indent}[API: $ep json=$vars$out]');
      } else if (a.database.hasFirestoreQuery()) {
        print('${indent}[FirestoreQuery singleTime=${a.database.firestoreQuery.singleTimeQuery}$out]');
      } else {
        print('${indent}[database: ${a.database.whichAction().name}$out]');
      }
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => u.fieldIdentifier.name).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})$out]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}$out]');
    } else {
      print('${indent}[${a.whichAction().name}$out]');
    }
  }
  if (node.hasFollowUpAction()) _dumpChain(node.followUpAction, '$indent  → ');
  if (node.hasConditionActions()) {
    print('$indent  [Condition] true:');
    for (final ta in node.conditionActions.trueActions) {
      if (ta.hasTrueAction()) _dumpChain(ta.trueAction, '$indent    ');
    }
    if (node.conditionActions.hasFalseAction()) {
      print('$indent  [Condition] false:');
      _dumpChain(node.conditionActions.falseAction, '$indent    ');
    }
  }
}
