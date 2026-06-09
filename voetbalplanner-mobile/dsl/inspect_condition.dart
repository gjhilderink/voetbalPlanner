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
        if (wc == null) { print('NOT FOUND'); return; }

        final sendBtn = findByKey(wc.node, 'IconButton_nnsnoc98');
        if (sendBtn == null) { print('send btn not found'); return; }

        for (final ta in sendBtn.triggerActions) {
          if (!ta.hasTrigger() || ta.trigger.triggerType != FFActionTriggerType.ON_TAP) continue;
          if (!ta.hasRootAction()) continue;
          final root = ta.rootAction;
          if (!root.hasConditionActions()) { print('no conditionActions on root'); continue; }

          print('=== Root conditionActions ===');
          for (final tca in root.conditionActions.trueActions) {
            if (tca.hasCondition()) {
              final cond = tca.condition;
              print('condition type: ${cond.whichCond().name}');
              if (cond.hasVariable()) {
                final v = cond.variable;
                print('variable source: ${v.source.name}');
                print('variable: ${v.toProto3Json()}');
              }
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
