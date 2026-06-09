library;

import 'dart:io';
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
        _inspectCreateGroup(project);
        _inspectTeamChatMembers(project);
        _inspectStaffGroups(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectCreateGroup(FFProject project) {
  print('\n=== GroupNameField ON_TEXTFIELD_CHANGE ===');
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) { print('NOT FOUND'); return; }
  final tf = findDescendants(wc.node, (n) => n.name == 'GroupNameField').firstOrNull;
  if (tf == null) { print('GroupNameField NOT FOUND'); return; }
  for (final ta in tf.triggerActions) {
    if (!ta.hasTrigger()) continue;
    print('Trigger: ${ta.trigger.triggerType.name}');
    if (ta.hasRootAction()) print('JSON: ${ta.rootAction.toProto3Json()}');
  }
  if (tf.triggerActions.isEmpty) print('(no triggers)');
}

void _inspectTeamChatMembers(FFProject project) {
  print('\n=== TeamChatPage MemberChipName binding ===');
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) { print('NOT FOUND'); return; }
  final nameText = findDescendants(wc.node, (n) => n.name == 'MemberChipName').firstOrNull;
  if (nameText == null) { print('MemberChipName NOT FOUND'); return; }
  print('props.text JSON: ${nameText.props.text.toProto3Json()}');

  print('\n=== MemberStripList generator variable ===');
  final list = findDescendants(wc.node, (n) => n.name == 'MemberStripList').firstOrNull;
  if (list == null) { print('MemberStripList NOT FOUND'); return; }
  print('hasGeneratorVariable: ${list.hasGeneratorVariable()}');
  if (list.hasGeneratorVariable()) {
    print('generatorVariable JSON: ${list.generatorVariable.toProto3Json()}');
  }
}

void _inspectStaffGroups(FFProject project) {
  print('\n=== StaffGroupItem struct ===');
  for (final s in project.backend.dataSchemaConfig.dataStructs) {
    if (s.identifier.name == 'StaffGroupItem') {
      print('Fields:');
      for (final f in s.fields) {
        print('  ${f.identifier.name} (key: ${f.identifier.key})');
      }
    }
  }

  print('\n=== GetStaffGroups endpoint responseDataStructParam ===');
  for (final group in project.backend.apiConfig.apiGroups) {
    for (final ep in group.endpoints) {
      if (ep.identifier.name == 'GetStaffGroups') {
        print('hasResponseDataStructParam: ${ep.hasResponseDataStructParam()}');
        if (ep.hasResponseDataStructParam()) {
          print('responseDataStructParam JSON: ${ep.responseDataStructParam.toProto3Json()}');
        }
      }
    }
  }
}
