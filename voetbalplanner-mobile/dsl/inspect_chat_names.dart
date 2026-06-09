library;

import 'dart:io';
import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart' show findCollectionField, findCollection;
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
        // 1. chatGroups collection fields
        print('=== chatGroups collection fields ===');
        final chatGroupsCol = findCollection(project, name: 'chatGroups');
        if (chatGroupsCol != null) {
          for (final f in chatGroupsCol.fields.values) {
            print('  ${f.identifier.name} (key: ${f.identifier.key}) type: ${f.dataType}');
          }
        } else {
          print('  collection NOT FOUND');
        }

        final wc = findPage(project, name: 'ChatsPage');
        if (wc == null) { print('ChatsPage NOT FOUND'); return; }

        // 2. GroupChipName text binding
        print('\n=== GroupChipName text binding ===');
        final nameText = findDescendants(wc.node, (n) => n.name == 'GroupChipName').firstOrNull;
        if (nameText != null) {
          print('  textValue JSON: ${nameText.props.text.textValue.toProto3Json()}');
          print('  themeStyle: ${nameText.props.text.themeStyle}');
        } else {
          print('  NOT FOUND');
        }

        // 3. StaffGroupChipName text binding
        print('\n=== StaffGroupChipName text binding ===');
        final staffName = findDescendants(wc.node, (n) => n.name == 'StaffGroupChipName').firstOrNull;
        if (staffName != null) {
          print('  textValue JSON: ${staffName.props.text.textValue.toProto3Json()}');
          print('  themeStyle: ${staffName.props.text.themeStyle}');
        } else {
          print('  NOT FOUND');
        }

        // 4. ChatsStaffGroupsList structure
        print('\n=== ChatsStaffGroupsList ===');
        final staffList = findDescendants(wc.node, (n) => n.name == 'ChatsStaffGroupsList').firstOrNull;
        if (staffList != null) {
          print('  key: ${staffList.key}');
          print('  children count: ${staffList.children.length}');
          if (staffList.children.isNotEmpty) {
            final item = staffList.children.first;
            print('  first child: ${item.name} (${item.type})');
            // Recurse to find all text nodes
            final texts = findDescendants(item, (n) => n.type == FFWidgetType.Text);
            for (final t in texts) {
              print('  text node: ${t.name} themeStyle=${t.props.text.themeStyle} textValue=${t.props.text.textValue.toProto3Json()}');
            }
          }
        } else {
          print('  NOT FOUND');
        }

        // 5. StaffGroupItem struct fields
        print('\n=== StaffGroupItem struct fields ===');
        for (final s in project.backend.dataSchemaConfig.dataStructs) {
          if (s.identifier.name == 'StaffGroupItem') {
            for (final f in s.fields) {
              print('  ${f.identifier.name} (key: ${f.identifier.key})');
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
