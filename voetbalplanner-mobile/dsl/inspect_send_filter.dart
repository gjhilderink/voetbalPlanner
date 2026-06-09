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
        final wc = findPage(project, name: 'ChatDetailPage');
        if (wc == null) { print('ChatDetailPage NOT FOUND'); return; }

        final sendBtn = findByKey(wc.node, 'IconButton_nnsnoc98');
        if (sendBtn == null) { print('send button NOT FOUND'); return; }

        for (final ta in sendBtn.triggerActions) {
          if (!ta.hasTrigger() || ta.trigger.triggerType != FFActionTriggerType.ON_TAP) continue;
          if (!ta.hasRootAction()) continue;
          _dumpChain(ta.rootAction, '');
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
    if (a.hasDatabase() && a.database.hasFirestoreQuery()) {
      final q = a.database.firestoreQuery;
      print('${indent}[FirestoreQuery out="${a.outputVariableName}" single=${q.singleTimeQuery}]');
      print('${indent}  collection: ${q.collectionIdentifier.name}');
      if (q.hasWhere()) {
        print('${indent}  where: ${q.where.toProto3Json().toString().substring(0, 200)}');
      } else {
        print('${indent}  where: (none)');
      }
    } else if (a.hasLocalStateUpdate()) {
      for (final u in a.localStateUpdate.updates) {
        final val = u.setValue.toProto3Json().toString();
        final short = val.length > 120 ? '${val.substring(0, 120)}...' : val;
        print('${indent}[SetState: ${u.fieldIdentifier.name}] value=$short');
      }
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
    } else {
      print('${indent}[${a.whichAction().name}]');
    }
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
  if (node.hasFollowUpAction()) _dumpChain(node.followUpAction, '$indent  ');
}
