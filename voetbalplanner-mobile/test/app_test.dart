import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:test/test.dart';

import '../dsl/create.dart' as starter;

void main() {
  test('starter DSL app compiles', () {
    final app = buildApp(starter.buildVoetbalPlannerApp);
    final project = compileApp(app).project;

    final loginPage = findPage(project, name: 'LoginPage');
    expect(loginPage, isNotNull);
    expect(loginPage!.node.type, FFWidgetType.Scaffold);
  });
}
