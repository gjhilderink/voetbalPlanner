library;

import 'dart:io';
import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart' show findDescendants, getPropertyChild;

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
        final wc = findPage(project, name: 'DashboardPage');
        if (wc == null) { print('DashboardPage NOT FOUND'); return; }

        // 1. State fields
        print('=== DashboardPage state fields ===');
        for (final f in wc.classModel.stateFields) {
          print('  ${f.parameter.identifier.name} key:${f.parameter.identifier.key} type:${f.parameter.dataType}');
        }

        // 2. onLoad triggers
        print('\n=== DashboardPage onLoad triggers ===');
        for (final ta in wc.node.triggerActions) {
          final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
          print('  Trigger: $t');
          if (ta.hasRootAction()) {
            print('  JSON: ${ta.rootAction.toProto3Json()}');
          }
        }
        if (wc.node.triggerActions.isEmpty) print('  (none)');

        // 3. DashboardMatchesList
        print('\n=== DashboardMatchesList ===');
        final lv = findDescendants(wc.node, (n) => n.name == 'DashboardMatchesList').firstOrNull;
        if (lv == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${lv.key}');
          print('  type: ${lv.type}');
          print('  props JSON: ${lv.props.toProto3Json()}');
          print('  children count: ${lv.children.length}');
        }

        // 4. DashboardMatchesContainer
        print('\n=== DashboardMatchesContainer ===');
        final mc = findDescendants(wc.node, (n) => n.name == 'DashboardMatchesContainer').firstOrNull;
        if (mc == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${mc.key}');
          print('  children count: ${mc.children.length}');
          print('  props JSON: ${mc.props.toProto3Json()}');
        }

        // 5. Body Column props
        print('\n=== Body Column props ===');
        final bodyCol = getPropertyChild(wc.node, 'body');
        if (bodyCol == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${bodyCol.key}, type: ${bodyCol.type}');
          print('  props JSON: ${bodyCol.props.toProto3Json()}');
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
