import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';
import 'router.dart';
import 'services/api_service.dart';
import 'state/app_state.dart';
import 'theme/app_theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('nl_NL');
  runApp(const VoetbalPlannerApp());
}

class VoetbalPlannerApp extends StatefulWidget {
  const VoetbalPlannerApp({super.key});

  @override
  State<VoetbalPlannerApp> createState() => _VoetbalPlannerAppState();
}

class _VoetbalPlannerAppState extends State<VoetbalPlannerApp> {
  final _api = ApiService();
  late final AppState _appState;
  late final RouterConfig<Object> _router;

  @override
  void initState() {
    super.initState();
    _appState = AppState(_api);
    _router = buildRouter(_appState);
    _appState.init();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _appState),
        Provider.value(value: _api),
      ],
      child: MaterialApp.router(
        title: 'VoetbalPlanner',
        theme: appTheme,
        routerConfig: _router,
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
