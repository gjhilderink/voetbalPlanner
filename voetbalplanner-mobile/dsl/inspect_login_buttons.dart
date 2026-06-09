library;

import 'package:flutterflow_ai/flutterflow_ai.dart';

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
        final wc = findPage(project, name: 'LoginPage');
        if (wc == null) { print('LoginPage NOT FOUND'); return; }

        // Debug: dump all button-type nodes on LoginPage
        print('=== All Button nodes ===');
        _findAllButtons(wc.node, '');

        for (final buttonKey in ['Button_bg6zh5x9', 'Button_s0q1isbo']) {
          final btn = findByKey(wc.node, buttonKey);
          if (btn == null) { print('$buttonKey NOT FOUND'); continue; }
          print('\n=== $buttonKey (${btn.name}) ===');
          for (final ta in btn.triggerActions) {
            if (!ta.hasTrigger()) continue;
            print('  trigger: ${ta.trigger.triggerType.name}');
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '  ');
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

void _findAllButtons(FFNode node, String indent) {
  if (node.props.hasButton()) {
    print('$indent${node.name} (${node.type}) key=${node.key} triggerCount=${node.triggerActions.length}');
  }
  for (final c in node.children) {
    _findAllButtons(c, '$indent  ');
  }
}

void _dumpChain(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}] out="${a.outputVariableName}"');
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => u.fieldIdentifier.name).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})]');
    } else {
      print('${indent}[${a.whichAction().name}]');
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
