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
        _inspectMemberChipStyle(project);
        _inspectCreateGroupSubmit(project);
        _inspectCreateChatGroupAction(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectMemberChipStyle(FFProject project) {
  print('\n=== TeamChatPage MemberChipName style ===');
  final wc = findPage(project, name: 'TeamChatPage');
  if (wc == null) { print('NOT FOUND'); return; }

  final nameText = findDescendants(wc.node, (n) => n.name == 'MemberChipName').firstOrNull;
  if (nameText == null) { print('MemberChipName NOT FOUND'); return; }
  print('props.hasText: ${nameText.props.hasText()}');
  if (nameText.props.hasText()) {
    print('themeStyle: ${nameText.props.text.themeStyle}');
    print('themeStyle name: ${nameText.props.text.themeStyle.name}');
  }

  print('\n=== MemberAvatar children ===');
  final avatar = findDescendants(wc.node, (n) => n.name == 'MemberAvatar').firstOrNull;
  if (avatar == null) { print('MemberAvatar NOT FOUND'); return; }
  print('children count: ${avatar.children.length}');
  for (final c in avatar.children) {
    print('  child: ${c.name} (${c.type})');
  }
}

void _inspectCreateGroupSubmit(FFProject project) {
  print('\n=== CreateGroupSubmitButton ON_TAP ===');
  final wc = findPage(project, name: 'CreateGroupPage');
  if (wc == null) { print('NOT FOUND'); return; }

  final btn = findDescendants(wc.node, (n) => n.name == 'CreateGroupSubmitButton').firstOrNull;
  if (btn == null) { print('NOT FOUND'); return; }

  for (final ta in btn.triggerActions) {
    if (!ta.hasTrigger()) continue;
    print('Trigger: ${ta.trigger.triggerType.name}');
    if (ta.hasRootAction()) {
      print('JSON: ${ta.rootAction.toProto3Json()}');
    }
  }
  if (btn.triggerActions.isEmpty) print('(no triggers)');
}

void _inspectCreateChatGroupAction(FFProject project) {
  print('\n=== CreateChatGroup custom action ===');
  for (final ca in project.customCode.customActions) {
    if (ca.identifier.name == 'CreateChatGroup') {
      print('Found. Arguments:');
      for (final arg in ca.arguments) {
        print('  name=${arg.identifier.name} key=${arg.identifier.key}');
      }
    }
  }
}
