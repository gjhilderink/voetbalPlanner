library;

import 'dart:io';
import 'dart:math';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/api_helpers.dart';
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart';

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  try {
    await flutterFlowAI(
      buildEditFlow,
      apiKey: options.apiKey,
      baseUrl: options.baseUrl,
      projectName: options.projectName,
      projectId: options.projectId,
      findOrCreate: options.findOrCreate,
      allowNewProject: options.allowNewProject,
      dryRun: options.dryRun,
      commitMessage: options.commitMessage,
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void buildEditFlow(App app) {
  app.raw((project) {
    // Fix ListView codegen bugs (item names + visibility wrapping)
    _fixItemName(project, 'WedstrijdenPage', 'ListView_erdckv6e', 'match');
    _fixItemName(project, 'RijschemaPage', 'ListView_55kreos3', 'driveMatch');
    _fixItemName(project, 'BardienPage', 'ListView_tu54znnh', 'duty');

    _wrapListViewVisibility(project, 'WedstrijdenPage', 'ListView_erdckv6e');
    _wrapListViewVisibility(project, 'RijschemaPage', 'ListView_55kreos3');
    _wrapListViewVisibility(project, 'BardienPage', 'ListView_tu54znnh');

    // Move API auth token to group-level so every endpoint sends the header
    _fixApiGroupAuth(project);

    // Add biometric login infrastructure
    _addBiometricInfrastructure(project);
  });

  // Add biometric button to LoginPage
  _addBiometricButton(app);
}

// ─── API group auth fix ───────────────────────────────────────────────────────

/// Moves the Authorization header from per-endpoint headers to the API group's
/// sharedHeaders + sharedVariables, bound to the AppState authToken field.
///
/// FlutterFlow substitutes sharedVariables into sharedHeaders at call time,
/// so `Authorization: Bearer [token]` at group level is equivalent to — but
/// more reliable than — per-endpoint `Bearer <token>` substitution.
void _fixApiGroupAuth(FFProject project) {
  final group = findApiGroup(project, name: 'VoetbalPlannerAPI');
  if (group == null) return;

  // Find authToken AppState field
  final authField = project.appState.fields
      .cast<FFAppStateField?>()
      .firstWhere(
        (f) => f?.parameter.identifier.name == 'authToken',
        orElse: () => null,
      );
  if (authField == null) return;
  final authTokenId = authField.parameter.identifier.deepCopy();

  // Add group-level Authorization header (idempotent).
  // Use name "bearerToken" to avoid conflicting with per-endpoint "token" variables.
  if (!group.sharedHeaders.any((h) => h.startsWith('Authorization:'))) {
    group.sharedHeaders.add('Authorization: Bearer [bearerToken]');
  }

  // Add group-level bearerToken variable bound to AppState (idempotent)
  if (!group.sharedVariables.any((v) => v.identifier.name == 'bearerToken')) {
    group.sharedVariables.add(
      FFApiValue(
        identifier: FFIdentifier(
          name: 'bearerToken',
          key: generateRandomAlphaNumericString(),
        ),
        type: FFBaseDataType.String,
        value: FFValue(variable: varFromAppState(authTokenId)),
      ),
    );
  }

  // Remove per-endpoint Authorization headers (now handled at group level).
  // Keep per-endpoint "token" variables — page actions still pass them.
  for (final endpoint in group.endpoints) {
    endpoint.headers.removeWhere((h) => h.startsWith('Authorization:'));
  }
}

// ─── Biometric login infrastructure ─────────────────────────────────────────

void _addBiometricInfrastructure(FFProject project) {
  // local_auth pub dependency
  if (findPubDependency(project, name: 'local_auth') == null) {
    addPubDependency(project, name: 'local_auth', version: '^2.3.0');
  }

  // Custom action: AuthenticateBiometric
  // Returns true when the user passes biometric (face/fingerprint) or device PIN.
  if (findCustomAction(project, name: 'AuthenticateBiometric') == null) {
    addCustomAction(
      project,
      name: 'AuthenticateBiometric',
      description: 'Authenticate with biometrics (face, fingerprint) or device PIN. Returns true on success.',
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: r'''
// Automatic FlutterFlow imports
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:local_auth/local_auth.dart';

Future<bool> authenticateBiometric() async {
  final auth = LocalAuthentication();

  final canCheck = await auth.canCheckBiometrics;
  final isDeviceSupported = await auth.isDeviceSupported();
  if (!canCheck && !isDeviceSupported) return false;

  try {
    return await auth.authenticate(
      localizedReason: 'Inloggen met biometrie of pincode',
      options: const AuthenticationOptions(
        biometricOnly: false,
        stickyAuth: true,
      ),
    );
  } catch (_) {
    return false;
  }
}
''',
    );
  }

  // Custom function: hasStoredToken — used to show/hide the biometric button
  if (findCustomFunction(project, name: 'hasStoredToken') == null) {
    addCustomFunction(
      project,
      name: 'hasStoredToken',
      description: 'Returns true when a non-empty auth token is stored (biometric login available).',
      arguments: [
        FFParameter(
          identifier: FFIdentifier(name: 'token'),
          dataType: FFDataTypeV2(scalarType: FFBaseDataType.String),
        ),
      ],
      returnParameter: FFParameter(
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Boolean),
      ),
      code: 'return token != null && token.isNotEmpty;',
    );
  }
}

// ─── Biometric button on LoginPage ───────────────────────────────────────────

void _addBiometricButton(App app) {
  app.editPage('LoginPage', (page) {
    // Target the login button by its stable widget key
    final loginButton = page.findByKey('Button_bg6zh5x9');

    // Insert biometric button directly below the login button
    page.ensureInsertedAfter(
      loginButton,
      Button(
        'Inloggen met biometrie',
        name: 'BiometricLoginButton',
        icon: 'fingerprint',
        width: double.infinity,
        color: Colors.secondaryBackground,
        textColor: Colors.primaryText,
        borderRadius: 8,
        onTap: [
          CallCustomAction.named(
            'AuthenticateBiometric',
            returnType: bool_,
            outputAs: 'biometricResult',
          ),
          If(
            ActionOutput('biometricResult'),
            then: Navigate('WedstrijdenPage', replaceRoute: true),
            orElse: Snackbar('Biometrische verificatie mislukt'),
          ),
        ],
      ),
    );
  });
}

// ─── ListView codegen fixes ───────────────────────────────────────────────────

void _fixItemName(
  FFProject project,
  String pageName,
  String listViewKey,
  String itemName,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final listView = findByKey(wc.node, listViewKey);
  if (listView != null && listView.hasGeneratorVariable()) {
    listView.generatorVariable.identifier.name = itemName;
  }
}

/// Removes `visible:` from [listViewKey] and moves it to a new transparent
/// Container wrapper. This prevents FlutterFlow's code generator from hoisting
/// the ListView builder-local variables (e.g. matchIndex / matchItem) to class
/// scope, which causes compile errors when they are later referenced inside
/// the builder callback.
void _wrapListViewVisibility(
  FFProject project,
  String pageName,
  String listViewKey,
) {
  final wc = findPage(project, name: pageName);
  if (wc == null) return;
  final listView = findByKey(wc.node, listViewKey);
  if (listView == null || !listView.props.hasVisibility()) return;

  // Deep-copy the visibility condition before clearing it from the ListView.
  final boolVal = FFBooleanValue()
    ..mergeFromMessage(listView.props.visibility.visibleValue);
  listView.props.clearVisibility();

  // Transparent Container that carries only the visibility condition.
  final wrapper = FFNode(
    key: 'Container_${_randomSuffix()}',
    type: FFWidgetType.Container,
    name: 'ListViewWrapper',
    props: FFWidgetProperties(container: FFContainer()),
    children: [listView],
  );
  wrapper.props.ensureVisibility().visibleValue = boolVal;

  // Replace the ListView's former position in the tree with the Container.
  replaceByKey(wc.node, listViewKey, wrapper);
}

final _rng = Random();
String _randomSuffix() {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  return List.generate(8, (_) => chars[_rng.nextInt(chars.length)]).join();
}

// ─── CLI boilerplate ─────────────────────────────────────────────────────────

final class _CliOptions {
  const _CliOptions({
    this.apiKey,
    this.baseUrl,
    this.projectName,
    this.projectId,
    this.findOrCreate = false,
    this.allowNewProject = false,
    this.dryRun = false,
    this.commitMessage,
  });

  final String? apiKey;
  final String? baseUrl;
  final String? projectName;
  final String? projectId;
  final bool findOrCreate;
  final bool allowNewProject;
  final bool dryRun;
  final String? commitMessage;
}

_CliOptions _parseCliOptions(List<String> args) {
  String? apiKey;
  String? baseUrl;
  String? projectName;
  String? projectId;
  String? commitMessage;
  var findOrCreate = false;
  var allowNewProject = false;
  var dryRun = false;

  for (var i = 0; i < args.length; i++) {
    final arg = args[i];
    switch (arg) {
      case '--help':
      case '-h':
        _printUsage();
        exit(0);
      case '--api-key':
        apiKey = _requireValue(args, ++i, '--api-key');
      case '--base-url':
        baseUrl = _requireValue(args, ++i, '--base-url');
      case '--project-name':
        projectName = _requireValue(args, ++i, '--project-name');
      case '--project-id':
        projectId = _requireValue(args, ++i, '--project-id');
      case '--commit-message':
        commitMessage = _requireValue(args, ++i, '--commit-message');
      case '--find-or-create':
        findOrCreate = true;
      case '--allow-new-project':
        allowNewProject = true;
      case '--dry-run':
        dryRun = true;
      default:
        stderr.writeln('Unknown option: $arg');
        _printUsage();
        exit(64);
    }
  }

  return _CliOptions(
    apiKey: apiKey,
    baseUrl: baseUrl,
    projectName: projectName,
    projectId: projectId,
    findOrCreate: findOrCreate,
    allowNewProject: allowNewProject,
    dryRun: dryRun,
    commitMessage: commitMessage,
  );
}

String _requireValue(List<String> args, int index, String flag) {
  if (index >= args.length) {
    stderr.writeln('Missing value for $flag.');
    _printUsage();
    exit(64);
  }
  return args[index];
}

void _printUsage() {
  stdout.writeln('''
Fix FlutterFlow codegen bugs, group-level API auth, and biometric login.

Usage:
  dart run dsl/edit.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-id <id>         Target project ID (required).
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --help, -h                Show this help.
''');
}
