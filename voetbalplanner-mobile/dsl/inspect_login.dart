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
        final wc = findPage(project, name: 'LoginPage');
        if (wc == null) { print('LoginPage not found'); return; }

        print('=== LoginPage scaffold props ===');
        if (wc.node.props.hasScaffold()) {
          print(wc.node.props.scaffold.toProto3Json());
        }

        print('\n=== Full tree (depth ≤ 7) ===');
        _dumpTree(wc.node, '', 0, 7);
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
  final extra = _nodeExtra(node);
  print('$indent${name} (${node.type}) key=${node.key}$extra');
  for (final c in node.children) {
    _dumpTree(c, '$indent  ', depth + 1, maxDepth);
  }
}

String _nodeExtra(FFNode node) {
  if (node.props.hasColumn()) {
    final col = node.props.column;
    return ' [col main=${col.mainAxisAlignment.name} cross=${col.crossAxisAlignment.name}]';
  }
  if (node.props.hasScaffold()) return ' [scaffold]';
  if (node.props.hasButton()) return ' [button]';
  if (node.props.hasTextField()) return ' [textField]';
  if (node.props.hasText()) return ' [text]';
  if (node.props.hasContainer()) return ' [container]';
  return ' props=${node.props.toProto3Json().toString().substring(0, 40)}';
}
