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

        print('=== ListView / GridView nodes ===');
        final allNodes = findDescendants(wc.node, (n) =>
            n.type == FFWidgetType.ListView || n.type == FFWidgetType.GridView);
        for (final n in allNodes) {
          print('  ${n.name} (${n.type}) key=${n.key}');
          print('  generatorVariable set: ${n.hasGeneratorVariable()}');
          print('  children count: ${n.children.length}');
        }
        if (allNodes.isEmpty) print('  NONE FOUND');
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
    commitMessage: 'inspect only',
    validationFilter: (_) => false,
  );
}

List<FFNode> findDescendants(FFNode node, bool Function(FFNode) predicate) {
  final result = <FFNode>[];
  for (final child in node.children) {
    if (predicate(child)) result.add(child);
    result.addAll(findDescendants(child, predicate));
  }
  return result;
}
