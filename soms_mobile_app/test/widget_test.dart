// Smoke test — confirms SomsApp boots without throwing.
//
// Kept intentionally minimal: AuthGate's bootstrap step reads from Flutter
// Secure Storage, which isn't available in the widget test environment.
// We only pump a single frame (not pumpAndSettle) and assert the
// bootstrapping state renders correctly, rather than waiting for auth
// to resolve. Deeper screen-level tests belong in test/screens/ once
// those screens are wired to the real API (post Phase 5).

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:soms_mobile/main.dart';

void main() {
  testWidgets('SomsApp boots and shows the bootstrapping spinner',
      (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: SomsApp()));

    // First frame only — auth bootstrap (secure storage read) hasn't
    // resolved yet, so AuthGate should be showing its loading state.
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
