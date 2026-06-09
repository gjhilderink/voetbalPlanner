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
        final wc = findPage(project, name: 'ChatDetailPage');
        if (wc == null) { print('ChatDetailPage NOT FOUND'); return; }

        // 1. Page params
        print('=== ChatDetailPage params ===');
        for (final p in wc.params.values) {
          if (p.hasIdentifier()) print('  ${p.identifier.name} (key: ${p.identifier.key})');
        }

        // 2. ON_INIT_STATE trigger chains
        print('\n=== ON_INIT_STATE chains ===');
        final initTriggers = wc.node.triggerActions
            .where((t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE)
            .toList();
        print('  Count: ${initTriggers.length}');
        for (var i = 0; i < initTriggers.length; i++) {
          print('  --- trigger $i ---');
          if (initTriggers[i].hasRootAction()) _dumpChain(initTriggers[i].rootAction, '    ');
        }

        // 3. TextField ChatMessageField onChange
        print('\n=== ChatMessageField onChange triggers ===');
        final tf = findDescendants(wc.node, (n) => n.name == 'ChatMessageField').firstOrNull;
        if (tf == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${tf.key}');
          for (final ta in tf.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '    ');
          }
          if (tf.triggerActions.isEmpty) print('  (no triggers)');
        }

        // 4. All IconButtons on ChatDetailPage
        print('\n=== IconButtons on ChatDetailPage ===');
        final iconBtns = findDescendants(wc.node, (n) => n.type == FFWidgetType.IconButton);
        for (final btn in iconBtns) {
          print('  btn: ${btn.name} (key: ${btn.key})');
          for (final ta in btn.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('    Trigger: $t');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '      ');
          }
          if (btn.triggerActions.isEmpty) print('    (no triggers)');
        }
        if (iconBtns.isEmpty) print('  NONE FOUND');

        // 5. AppState currentConversationId
        print('\n=== AppState currentConversationId ===');
        for (final f in project.appState.fields) {
          if (f.parameter.identifier.name == 'currentConversationId') {
            print('  key: ${f.parameter.identifier.key}');
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

void _dumpChain(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasDatabase()) {
      if (a.database.hasApiCall()) {
        print('${indent}[API: ${a.database.apiCall.endpointIdentifier.name}]');
      } else if (a.database.hasFirestoreQuery()) {
        print('${indent}[FirestoreQuery out=${a.outputVariableName}]');
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
