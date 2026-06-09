library;

import 'dart:io';
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
        print('=== Custom actions ===');
        for (final ca in project.customCode.customActions) {
          print('  ${ca.identifier.name}');
        }
        print('\n=== CreateChatGroup code ===');
        for (final ca in project.customCode.customActions) {
          if (ca.identifier.name == 'CreateChatGroup') {
            print(ca.code);
          }
        }
      });
    },
    apiKey: apiKey,
    projectId: projectId,
    dryRun: true,
  );
}
