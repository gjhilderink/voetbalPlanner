import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'screens/login_screen.dart';
import 'screens/matches_screen.dart';
import 'screens/match_detail_screen.dart';
import 'screens/drive_schedule_screen.dart';
import 'screens/bar_duties_screen.dart';
import 'screens/profile_screen.dart';
import 'state/app_state.dart';

final _rootNavKey = GlobalKey<NavigatorState>();
final _shellNavKey = GlobalKey<NavigatorState>();

GoRouter buildRouter(AppState appState) {
  return GoRouter(
    navigatorKey: _rootNavKey,
    refreshListenable: appState,
    redirect: (context, state) {
      if (!appState.initialized) return null;
      final loggedIn = appState.isLoggedIn;
      final onLogin = state.matchedLocation == '/login';
      if (!loggedIn && !onLogin) return '/login';
      if (loggedIn && onLogin) return '/matches';
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (_, __) => const LoginScreen(),
      ),
      ShellRoute(
        navigatorKey: _shellNavKey,
        builder: (context, state, child) => _Shell(child: child),
        routes: [
          GoRoute(path: '/matches', builder: (_, __) => const MatchesScreen()),
          GoRoute(
            path: '/matches/:id',
            parentNavigatorKey: _rootNavKey,
            builder: (_, state) => MatchDetailScreen(matchId: state.pathParameters['id']!),
          ),
          GoRoute(path: '/rijschema', builder: (_, __) => const DriveScheduleScreen()),
          GoRoute(path: '/bardiensten', builder: (_, __) => const BarDutiesScreen()),
          GoRoute(path: '/profiel', builder: (_, __) => const ProfileScreen()),
        ],
      ),
    ],
    initialLocation: '/matches',
  );
}

class _Shell extends StatelessWidget {
  final Widget child;
  const _Shell({required this.child});

  int _locationToIndex(String location) {
    if (location.startsWith('/matches')) return 0;
    if (location.startsWith('/rijschema')) return 1;
    if (location.startsWith('/bardiensten')) return 2;
    if (location.startsWith('/profiel')) return 3;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final idx = _locationToIndex(location);

    return Scaffold(
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: idx,
        backgroundColor: Colors.white,
        indicatorColor: const Color(0xFFdcfce7),
        onDestinationSelected: (i) {
          switch (i) {
            case 0: context.go('/matches');
            case 1: context.go('/rijschema');
            case 2: context.go('/bardiensten');
            case 3: context.go('/profiel');
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.sports_soccer), label: 'Wedstrijden'),
          NavigationDestination(icon: Icon(Icons.directions_car), label: 'Rijschema'),
          NavigationDestination(icon: Icon(Icons.local_bar), label: 'Bardiensten'),
          NavigationDestination(icon: Icon(Icons.person), label: 'Profiel'),
        ],
      ),
    );
  }
}
