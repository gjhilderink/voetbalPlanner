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
        final wc = findPage(project, name: 'ChatDetailPage');
        if (wc == null) { print('ChatDetailPage NOT FOUND'); return; }

        // TextField properties
        print('=== ChatMessageField props ===');
        final tf = findDescendants(wc.node, (n) => n.name == 'ChatMessageField').firstOrNull;
        if (tf == null) {
          print('  NOT FOUND');
        } else {
          print('  key: ${tf.key}');
          final p = tf.props;
          if (p.hasTextField()) {
            final tf2 = p.textField;
            print('  localStateValue: ${tf2.localStateValue}');
            print('  setStateOnChanged: ${tf2.setStateOnChanged}');
            if (tf2.hasInitialText()) {
              print('  initialText: ${tf2.initialText.toProto3Json()}');
            } else {
              print('  initialText: (none)');
            }
          } else {
            print('  (no textField props)');
          }
        }

        // Others' bubble column children
        print('\n=== Column_u3nr1vt9 children (others bubble) ===');
        final otherCol = findByKey(wc.node, 'Column_u3nr1vt9');
        if (otherCol == null) {
          print('  NOT FOUND');
        } else {
          print('  children count: ${otherCol.children.length}');
          for (final c in otherCol.children) {
            print('  ${c.name} (${c.type}) key=${c.key}');
            if (c.props.hasText()) {
              final tv = c.props.text.textValue;
              if (tv.hasVariable()) {
                final ops = tv.variable.operations.map((op) => op.toProto3Json().toString()).join(' | ');
                print('    textValue.variable ops: $ops');
              } else {
                print('    textValue: ${c.props.text.textValue.toProto3Json()}');
              }
            }
          }
          if (otherCol.children.isEmpty) print('  (empty)');
        }

        // Own bubble column children
        print('\n=== Column_6e4g1gje children (own bubble) ===');
        final ownCol = findByKey(wc.node, 'Column_6e4g1gje');
        if (ownCol == null) {
          print('  NOT FOUND');
        } else {
          print('  children count: ${ownCol.children.length}');
          for (final c in ownCol.children) {
            print('  ${c.name} (${c.type}) key=${c.key}');
            if (c.props.hasText()) {
              final tv = c.props.text.textValue;
              if (tv.hasVariable()) {
                final ops = tv.variable.operations.map((op) => op.toProto3Json().toString()).join(' | ');
                print('    textValue.variable ops: $ops');
              } else {
                print('    textValue: ${c.props.text.textValue.toProto3Json()}');
              }
            }
            // Row children (for OwnMsgMeta)
            for (final rc in c.children) {
              print('    child: ${rc.name} (${rc.type}) key=${rc.key}');
            }
          }
          if (ownCol.children.isEmpty) print('  (empty)');
        }

        // Also check the send button trigger for clearing TextField
        print('\n=== Send button (IconButton_nnsnoc98) full trigger ===');
        final btn = findByKey(wc.node, 'IconButton_nnsnoc98');
        if (btn == null) {
          print('  NOT FOUND');
        } else {
          for (final ta in btn.triggerActions) {
            if (ta.hasRootAction()) _dumpChain(ta.rootAction, '  ');
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

void _dumpChain(FFActionNode node, String indent) {
  if (node.hasAction()) {
    final a = node.action;
    if (a.hasLocalStateUpdate()) {
      final fields = a.localStateUpdate.updates.map((u) {
        final val = u.setValue.toProto3Json().toString();
        final short = val.length > 60 ? val.substring(0, 60) + '...' : val;
        return '${u.fieldIdentifier.name}=$short';
      }).join(', ');
      print('${indent}[SetState: $fields (${a.localStateUpdate.stateVariableType.name})]');
    } else if (a.hasCustomAction()) {
      print('${indent}[CustomAction: ${a.customAction.customActionIdentifier.name}]');
    } else if (a.hasDatabase()) {
      print('${indent}[Database: ${a.database.whichAction().name}]');
    } else {
      print('${indent}[other: ${a.whichAction().name}]');
    }
  }
  if (node.hasFollowUpAction()) _dumpChain(node.followUpAction, '$indent  → ');
  if (node.hasConditionActions()) {
    print('${indent}  [Condition] true:');
    for (final ta in node.conditionActions.trueActions) {
      if (ta.hasTrueAction()) _dumpChain(ta.trueAction, '$indent    ');
    }
    if (node.conditionActions.hasFalseAction()) {
      print('${indent}  [Condition] false:');
      _dumpChain(node.conditionActions.falseAction, '$indent    ');
    }
  }
}
