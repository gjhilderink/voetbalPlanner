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
        _dumpSendChain(project, 'ChatDetailPage',  'IconButton_nnsnoc98');
        _dumpSendChain(project, 'GroupChatPage',   'IconButton_tgwfn8d7');
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
    commitMessage: 'inspect only',
    validationFilter: (_) => false,
  );
}

void _dumpSendChain(FFProject project, String pageName, String btnKey) {
  print('\n====== $pageName send button ($btnKey) ======');
  final wc = findPage(project, name: pageName);
  if (wc == null) { print('PAGE NOT FOUND'); return; }

  final btn = findByKey(wc.node, btnKey);
  if (btn == null) { print('BUTTON NOT FOUND'); return; }

  final tap = btn.triggerActions.where(
    (t) => t.hasTrigger() && t.trigger.triggerType == FFActionTriggerType.ON_TAP,
  ).firstOrNull;
  if (tap == null) { print('NO ON_TAP TRIGGER'); return; }
  if (!tap.hasRootAction()) { print('NO ROOT ACTION'); return; }

  _dumpActionNode(tap.rootAction, '');
}

void _dumpActionNode(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    final out = a.outputVariableName.isNotEmpty ? ' out="${a.outputVariableName}"' : '';
    if (a.hasAlertDialog()) {
      print('${indent}[AlertDialog / Wait${a.alertDialog.hasWaitAction() ? " ${a.alertDialog.waitAction.milliseconds}ms" : ""}]');
    } else if (a.hasDatabase()) {
      if (a.database.hasFirestoreQuery()) {
        final fq = a.database.firestoreQuery;
        print('${indent}[FirestoreQuery singleTime=${fq.singleTimeQuery}$out]');
      } else if (a.database.hasCreateDocument()) {
        print('${indent}[FirestoreCreate$out]');
      } else if (a.database.hasApiCall()) {
        print('${indent}[API: ${a.database.apiCall.endpointIdentifier.name}$out]');
      } else {
        print('${indent}[database: ${a.database.whichAction().name}$out]');
      }
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => u.fieldIdentifier.name).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})$out]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}$out]');
    } else if (a.hasNavigate()) {
      print('${indent}[Navigate$out]');
    } else if (a.hasScrollToPercentage()) {
      print('${indent}[ScrollTo ${a.scrollToPercentage.scrollableNodeKeyRef.key} ${a.scrollToPercentage.scrollPercentage}]');
    } else {
      print('${indent}[${a.whichAction().name}$out]');
    }
  } else {
    print('${indent}[no action]');
  }

  if (node.hasFollowUpAction()) {
    _dumpActionNode(node.followUpAction, '$indent  → ');
  }

  if (node.hasConditionActions()) {
    final ca = node.conditionActions;
    print('$indent  [Condition] trueActions(${ca.trueActions.length}):');
    for (var i = 0; i < ca.trueActions.length; i++) {
      final ta = ca.trueActions[i];
      if (ta.hasTrueAction()) {
        print('$indent    [$i]:');
        _dumpActionNode(ta.trueAction, '$indent      ');
      }
    }
    if (ca.hasFalseAction()) {
      print('$indent  [Condition] false:');
      _dumpActionNode(ca.falseAction, '$indent    ');
    }
  }
}
