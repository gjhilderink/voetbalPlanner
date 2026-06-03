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

        final loginButton = findByKey(wc.node, 'Button_bg6zh5x9');
        if (loginButton == null) { print('Login button not found'); return; }

        print('=== Login button trigger actions ===');
        for (final ta in loginButton.triggerActions) {
          final triggerType = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
          print('Trigger: $triggerType');
          if (ta.hasRootAction()) {
            _dumpActionChain(ta.rootAction, '  ');
          }
        }

        print('\n=== LoginPage state fields ===');
        for (final f in wc.classModel.stateFields) {
          print('  ${f.parameter.identifier.name} (key: ${f.parameter.identifier.key})');
        }

        print('\n=== Email TextField trigger actions (full JSON) ===');
        final emailField = findByKey(wc.node, 'TextField_73irroiw');
        if (emailField != null) {
          for (final ta in emailField.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) {
              print('  rootAction JSON:');
              print(ta.rootAction.toProto3Json());
            }
          }
        } else {
          print('  Email TextField not found by key');
        }

        print('\n=== Full ON_TAP action chain (JSON) ===');
        for (final ta in loginButton.triggerActions) {
          if (ta.hasTrigger() && ta.trigger.triggerType == FFActionTriggerType.ON_TAP && ta.hasRootAction()) {
            print(ta.rootAction.toProto3Json());
          }
        }

        print('\n=== AppState fields ===');
        for (final f in project.appState.fields) {
          print('  ${f.parameter.identifier.name} (key: ${f.parameter.identifier.key})');
        }

        print('\n=== Page route settings ===');
        for (final wc in project.widgetClasses.values) {
          if (!wc.hasPageRouteSettings()) continue;
          final prs = wc.pageRouteSettings;
          print('  ${wc.name}: route=${prs.routePath} onlyAuth=${prs.onlyAuthenticated}');
        }

        print('\n=== WedstrijdenPage params + node key ===');
        final wPage = findPage(project, name: 'WedstrijdenPage');
        if (wPage != null) {
          print('  node.key=${wPage.node.key}');
          final paramList = wPage.params.entries.map((e) => '${e.key}: ${e.value.identifier.name}(${e.value.identifier.key})').join(', ');
          print('  params: [${paramList.isEmpty ? "none" : paramList}]');
          // Show ON_TAP JSON of login button after our push for comparison
        } else {
          print('  WedstrijdenPage not found');
        }

        print('\n=== WedstrijdenPage onLoad trigger actions (JSON) ===');
        final wPage2 = findPage(project, name: 'WedstrijdenPage');
        if (wPage2 != null) {
          for (final ta in wPage2.node.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) {
              print('  rootAction JSON: ${ta.rootAction.toProto3Json()}');
            }
          }
          if (wPage2.node.triggerActions.isEmpty) print('  (no triggers on root node)');
        }

        print('\n=== Login button ON_TAP chain (JSON) ===');
        for (final ta in loginButton.triggerActions) {
          if (ta.hasTrigger() && ta.trigger.triggerType == FFActionTriggerType.ON_TAP && ta.hasRootAction()) {
            print(ta.rootAction.toProto3Json());
          }
        }

        print('\n=== BiometricLoginButton ON_TAP chain (JSON) ===');
        final bioButton = findDescendants(wc.node, (n) => n.name == 'BiometricLoginButton').firstOrNull
            ?? findByKey(wc.node, 'Button_m4ywcdm3');
        if (bioButton != null) {
          for (final ta in bioButton.triggerActions) {
            if (ta.hasTrigger() && ta.trigger.triggerType == FFActionTriggerType.ON_TAP && ta.hasRootAction()) {
              print(ta.rootAction.toProto3Json());
            }
          }
        } else {
          print('  BiometricLoginButton not found');
        }

        print('\n=== Password TextField trigger actions (full JSON) ===');
        final passwordField = findByKey(wc.node, 'TextField_v1ycg741');
        if (passwordField != null) {
          for (final ta in passwordField.triggerActions) {
            final t = ta.hasTrigger() ? ta.trigger.triggerType.name : 'NO_TRIGGER';
            print('  Trigger: $t');
            if (ta.hasRootAction()) {
              print('  rootAction JSON:');
              print(ta.rootAction.toProto3Json());
            }
          }
        } else {
          print('  Password TextField not found by key');
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

void _dumpActionChain(FFActionNode node, String indent) {
  final actionType = node.hasAction() ? _describeAction(node.action) : 'NO_ACTION';
  print('${indent}ActionNode(key=${node.key}) → $actionType');
  if (node.hasFollowUpAction()) {
    _dumpActionChain(node.followUpAction, '$indent  ');
  }
  if (node.hasConditionActions()) {
    final ca = node.conditionActions;
    print('${indent}  TRUE branches: ${ca.trueActions.length}');
    for (final branch in ca.trueActions) {
      if (branch.hasTrueAction()) {
        _dumpActionChain(branch.trueAction, '$indent    ');
      }
    }
    if (ca.hasFalseAction()) {
      print('${indent}  FALSE:');
      _dumpActionChain(ca.falseAction, '$indent    ');
    }
  }
}

String _describeAction(FFAction action) {
  if (action.hasDatabase()) {
    final db = action.database;
    if (db.hasApiCall()) {
      final apiCall = db.apiCall;
      final vars = apiCall.variables.map((v) {
        final varInfo = v.hasVariable() ? 'source=${v.variable.source.name}' : 'NO_VAR';
        return '${v.variableIdentifier.name}=$varInfo';
      }).join(', ');
      return 'ApiCall(vars=[$vars])';
    }
    return 'Database(other=${db.runtimeType})';
  }
  return 'Action(${action.runtimeType})';
}
