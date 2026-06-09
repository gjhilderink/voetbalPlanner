library;

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/collection_helpers.dart' show findCollection;
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
        final wc = findPage(project, name: 'ChatDetailPage');
        if (wc == null) { print('ChatDetailPage NOT FOUND'); return; }

        print('=== childPropertyMap keys ===');
        for (final k in wc.node.childPropertyMap.keys) {
          print('  slot: $k  refs: ${wc.node.childPropertyMap[k]!.keyRefs.map((r) => r.key).join(', ')}');
        }

        print('\n=== top-level children ===');
        for (final c in wc.node.children) {
          print('  ${c.name} (${c.type}) key=${c.key}');
        }

        print('\n=== full tree (depth ≤ 6) ===');
        _dumpTree(wc.node, '', 0, 6);

        // chatMessages collection fields
        print('\n=== chatMessages collection fields ===');
        final chatMsgCol = findCollection(project, name: 'chatMessages');
        if (chatMsgCol != null) {
          for (final f in chatMsgCol.fields.values) {
            print('  ${f.identifier.name} (key: ${f.identifier.key}) type: ${f.dataType}');
          }
        } else {
          print('  NOT FOUND');
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

void _dumpTree(FFNode node, String indent, int depth, int maxDepth) {
  if (depth > maxDepth) return;
  final name = node.name.isEmpty ? '(unnamed)' : node.name;
  print('$indent${name} (${node.type}) key=${node.key}');
  for (final c in node.children) {
    _dumpTree(c, '$indent  ', depth + 1, maxDepth);
  }
}
