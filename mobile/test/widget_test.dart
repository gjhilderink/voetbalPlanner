import 'package:flutter_test/flutter_test.dart';
import 'package:voetbal_planner/main.dart';

void main() {
  testWidgets('App launches', (WidgetTester tester) async {
    await tester.pumpWidget(const VoetbalPlannerApp());
    await tester.pump();
  });
}
