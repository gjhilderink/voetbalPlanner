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
        _inspectChatsPageLoad(project);
        _inspectProfielPage(project);
        _inspectGroupsContainer(project);
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}

void _inspectChatsPageLoad(FFProject project) {
  print('\n=== ChatsPage state fields ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }
  for (final f in wc.classModel.stateFields) {
    print('  ${f.parameter.identifier.name} (key: ${f.parameter.identifier.key}) type: ${f.parameter.dataType.toProto3Json()}');
  }

  print('\n=== ChatsPage ON_INIT_STATE triggers ===');
  for (final ta in wc.node.triggerActions) {
    if (!ta.hasTrigger() || ta.trigger.triggerType.name != 'ON_INIT_STATE') continue;
    print('Trigger: ON_INIT_STATE');
    if (ta.hasRootAction()) _dumpChain(ta.rootAction, '  ');
  }
}

void _inspectGroupsContainer(FFProject project) {
  print('\n=== ChatsGroupsListContainer content ===');
  final wc = findPage(project, name: 'ChatsPage');
  if (wc == null) { print('NOT FOUND'); return; }
  final container = findDescendants(wc.node, (n) => n.name == 'ChatsGroupsListContainer').firstOrNull;
  if (container == null) { print('NOT FOUND'); return; }
  print('children count: ${container.children.length}');
  for (final c in container.children) {
    print('  child: ${c.name} (${c.type})');
    if (c.hasGeneratorVariable()) {
      print('    generator: ${c.generatorVariable.toProto3Json()}');
    }
  }
}

void _inspectProfielPage(FFProject project) {
  print('\n=== ProfielPage ProfielTeam widget ===');
  final wc = findPage(project, name: 'ProfielPage');
  if (wc == null) { print('NOT FOUND'); return; }

  final teamWidget = findDescendants(wc.node, (n) => n.name == 'ProfielTeam').firstOrNull;
  if (teamWidget == null) {
    print('ProfielTeam NOT FOUND');
    // Show ProfielInfoContent children names
    final info = findDescendants(wc.node, (n) => n.name == 'ProfielInfoContent').firstOrNull;
    if (info != null) {
      print('ProfielInfoContent children:');
      for (final c in info.children) print('  ${c.name}');
    }
    return;
  }
  print('Found ProfielTeam');
  print('props.hasText: ${teamWidget.props.hasText()}');
  if (teamWidget.props.hasText()) {
    print('textValue JSON: ${teamWidget.props.text.textValue.toProto3Json()}');
  }
}

void _dumpChain(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasDatabase()) {
      if (a.database.hasApiCall()) {
        final ep = a.database.apiCall.hasEndpointIdentifier()
            ? a.database.apiCall.endpointIdentifier.name : '?';
        print('${indent}[API: $ep, out=${a.outputVariableName}]');
      } else {
        print('${indent}[database: ${a.database.whichAction().name}, out=${a.outputVariableName}]');
      }
    } else if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) => u.fieldIdentifier.name).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
    } else {
      print('${indent}[other: ${a.toProto3Json().toString().substring(0, 60)}...]');
    }
  } else if (node.hasConditionActions()) {
    print('${indent}[Condition]');
  }
  if (node.hasFollowUpAction()) {
    _dumpChain(node.followUpAction, '$indent  → ');
  }
}
