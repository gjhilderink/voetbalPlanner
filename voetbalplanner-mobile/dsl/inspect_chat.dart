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
        _inspectChatsPage(project);
        _inspectCreateGroupPage(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectChatsPage(FFProject project) {
  print('\n=== ChatsPage ON_INIT_STATE triggers ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }

  for (final ta in wc.node.triggerActions) {
    if (!ta.hasTrigger()) continue;
    final t = ta.trigger.triggerType.name;
    print('Trigger: $t');
    if (ta.hasRootAction()) {
      _dumpActionChain(ta.rootAction, indent: '  ');
    }
  }

  print('\n--- StaffGroupChipName text binding ---');
  final staffList2 = findDescendants(wc.node, (n) => n.name == 'ChatsStaffGroupsList').firstOrNull;
  final chipName = staffList2 != null
      ? findDescendants(staffList2, (n) => n.name == 'StaffGroupChipName').firstOrNull
      : null;
  if (chipName == null) {
    print('StaffGroupChipName NOT FOUND');
  } else {
    print('textValue JSON: ${chipName.props.text.textValue.toProto3Json()}');
  }

  print('\n--- ChatsStaffGroupsList generator variable ---');
  final staffList = findDescendants(wc.node, (n) => n.name == 'ChatsStaffGroupsList').firstOrNull;
  if (staffList == null) {
    print('NOT FOUND');
  } else {
    print('hasGeneratorVariable: ${staffList.hasGeneratorVariable()}');
    if (staffList.hasGeneratorVariable()) {
      print('generatorVariable JSON: ${staffList.generatorVariable.toProto3Json()}');
    }
  }
}

void _inspectCreateGroupPage(FFProject project) {
  print('\n=== CreateGroupPage ON_INIT_STATE triggers ===');
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) { print('NOT FOUND'); return; }

  for (final ta in wc.node.triggerActions) {
    if (!ta.hasTrigger()) continue;
    final t = ta.trigger.triggerType.name;
    print('Trigger: $t');
    if (ta.hasRootAction()) {
      _dumpActionChain(ta.rootAction, indent: '  ');
    }
  }

  print('\n--- CreateGroupMemberList generator variable ---');
  final list = findDescendants(wc.node, (n) => n.name == 'CreateGroupMemberList').firstOrNull;
  if (list == null) {
    print('NOT FOUND');
  } else {
    print('hasGeneratorVariable: ${list.hasGeneratorVariable()}');
    if (list.hasGeneratorVariable()) {
      print('generatorVariable JSON: ${list.generatorVariable.toProto3Json()}');
    }
  }

  print('\n--- AddMemberButton ON_TAP ---');
  final addBtn = findDescendants(wc.node, (n) => n.name == 'AddMemberButton').firstOrNull;
  if (addBtn == null) {
    print('NOT FOUND');
  } else {
    for (final ta in addBtn.triggerActions) {
      if (!ta.hasTrigger()) continue;
      print('Trigger: ${ta.trigger.triggerType.name}');
      if (ta.hasRootAction()) _dumpActionChain(ta.rootAction, indent: '  ');
    }
  }

  print('\n--- CreateGroupSubmitButton ON_TAP (full JSON) ---');
  final btn = findDescendants(wc.node, (n) => n.name == 'CreateGroupSubmitButton').firstOrNull;
  if (btn == null) {
    print('NOT FOUND');
  } else {
    for (final ta in btn.triggerActions) {
      if (!ta.hasTrigger()) continue;
      print('Trigger: ${ta.trigger.triggerType.name}');
      if (ta.hasRootAction()) print('JSON: ${ta.rootAction.toProto3Json()}');
    }
  }
}

void _dumpActionChain(FFActionNode node, {String indent = ''}) {
  String desc = '${indent}ActionNode';
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasDatabase()) {
      if (a.database.hasApiCall()) {
        final ep = a.database.apiCall.hasEndpointIdentifier()
            ? a.database.apiCall.endpointIdentifier.name
            : '?';
        desc += ' [API: $ep, outputVar: ${a.outputVariableName}]';
      } else if (a.database.hasCreateDocument()) {
        final fields = a.database.createDocument.write.updates.keys.join(', ');
        desc += ' [FirestoreCreate fields: $fields]';
      } else {
        desc += ' [database action]';
      }
    } else if (a.hasLocalStateUpdate()) {
      desc += ' [UpdateState]';
    } else if (a.hasNavigate()) {
      desc += ' [Navigate]';
    } else if (a.hasCustomAction()) {
      desc += ' [CustomAction: ${a.customAction.customActionIdentifier.name}]';
    } else {
      desc += ' [other action]';
    }
  } else if (node.hasConditionActions()) {
    desc += ' [Condition: ${node.conditionActions.trueActions.length} true branches]';
  }
  print(desc);

  if (node.hasFollowUpAction()) {
    _dumpActionChain(node.followUpAction, indent: '$indent  → ');
  }
  if (node.hasConditionActions()) {
    for (final ta in node.conditionActions.trueActions) {
      if (ta.hasTrueAction()) {
        print('${indent}  [TRUE]:');
        _dumpActionChain(ta.trueAction, indent: '$indent    ');
      }
    }
    if (node.conditionActions.hasFalseAction()) {
      final fa = node.conditionActions.falseAction;
      if (fa.hasAction() || fa.hasConditionActions() || fa.hasFollowUpAction()) {
        print('${indent}  [FALSE]:');
        _dumpActionChain(fa, indent: '$indent    ');
      }
    }
  }
}
