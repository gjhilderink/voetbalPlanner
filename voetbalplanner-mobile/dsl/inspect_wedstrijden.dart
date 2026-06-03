library;

import 'dart:io';
import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
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
        // 1. Dump all API groups and their endpoints
        print('=== API Groups ===');
        for (final group in project.backend.apiConfig.apiGroups) {
          print('Group: ${group.identifier.name} (key: ${group.identifier.key})');
          for (final ep in group.endpoints) {
            print('  Endpoint: ${ep.identifier.name} (key: ${ep.identifier.key})');
            print('    JSON: ${ep.toProto3Json()}');
          }
        }

        // 2. Dump WedstrijdenPage trigger actions (page-level onLoad)
        print('\n=== WedstrijdenPage onLoad triggers ===');
        final wc = findPage(project, name: 'WedstrijdenPage');
        if (wc == null) { print('NOT FOUND'); return; }
        for (final ta in wc.node.triggerActions) {
          final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
          print('  Trigger: $t');
          if (ta.hasRootAction()) {
            print('  JSON: ${ta.rootAction.toProto3Json()}');
          }
        }
        if (wc.node.triggerActions.isEmpty) print('  (none)');

        // 3. Dump ConditionalBuilder trigger actions
        print('\n=== ConditionalBuilder_f1ph1tgg triggers ===');
        final cb = findByKey(wc.node, 'ConditionalBuilder_f1ph1tgg');
        if (cb == null) { print('NOT FOUND'); }
        else {
          print('  type: ${cb.type}');
          print('  props JSON: ${cb.props.toProto3Json()}');
          for (final ta in cb.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) print('  JSON: ${ta.rootAction.toProto3Json()}');
          }

          // 4. ShowAllMatchesRow
          print('\n=== ShowAllMatchesRow ===');
          final toggleRow = findDescendants(wc.node, (n) => n.name == 'ShowAllMatchesRow').firstOrNull;
          if (toggleRow == null) { print('  NOT FOUND'); }
          else {
            print('  key: ${toggleRow.key}');
            final toggle = findDescendants(toggleRow, (n) => n.name == 'ShowAllMatchesToggle').firstOrNull;
            if (toggle != null) {
              for (final ta in toggle.triggerActions) {
                print('  Toggle trigger: ${ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER'}');
                if (ta.hasRootAction()) print('  JSON: ${ta.rootAction.toProto3Json()}');
              }
            }
          }

          // 5. ListView item template visibility
          print('\n=== ListView item template visibility ===');
          final lv = findByKey(wc.node, 'ListView_erdckv6e');
          if (lv == null) { print('  ListView NOT FOUND'); }
          else if (lv.children.isEmpty) { print('  no children'); }
          else {
            final item = lv.children.first;
            print('  item key: ${item.key}, name: ${item.name}');
            print('  hasVisibility: ${item.props.hasVisibility()}');
            if (item.props.hasVisibility()) {
              print('  visibility JSON: ${item.props.visibility.toProto3Json()}');
            }
          }
        }

        // 6. WedstrijdenPage state fields
        print('\n=== WedstrijdenPage state fields ===');
        for (final f in wc.classModel.stateFields) {
          print('  ${f.parameter.identifier.name} (key: ${f.parameter.identifier.key}) type: ${f.parameter.dataType}');
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
