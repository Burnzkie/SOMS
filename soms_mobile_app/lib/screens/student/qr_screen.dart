// lib/screens/student/qr_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/api_client.dart';
import '../../core/app_theme.dart';
import '../../models/qr_data.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';

/// The backend issues a live rotating HMAC token (~60s validity) rather
/// than a static stored QR (see QrData doc comment) — so this screen
/// must actively re-fetch, not render once and forget.
///
/// The countdown you see is a REAL local ticker (updates every second),
/// seeded from the server's `expires_in` on each successful fetch. When
/// it reaches 0, that's what triggers the next fetch — not a fixed
/// interval — so the refresh always lines up with the actual token
/// window instead of an arbitrary guess at when it might expire.
class QrScreen extends ConsumerStatefulWidget {
  const QrScreen({super.key});

  @override
  ConsumerState<QrScreen> createState() => _QrScreenState();
}

class _QrScreenState extends ConsumerState<QrScreen> {
  Timer? _ticker;
  int? _secondsLeft;
  int? _seededForServerTime; // avoids re-seeding the ticker on every rebuild

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  /// (Re)seeds the local countdown whenever a genuinely new QrData comes
  /// in — guarded by server_time so this doesn't restart the ticker on
  /// every unrelated rebuild (e.g. RefreshIndicator's own frame).
  void _seedTicker(QrData data) {
    if (_seededForServerTime == data.serverTime) return;
    _seededForServerTime = data.serverTime;

    _ticker?.cancel();
    setState(() => _secondsLeft = data.expiresIn.clamp(1, 60));

    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      final next = (_secondsLeft ?? 1) - 1;
      if (next <= 0) {
        ref.invalidate(
            studentQrProvider); // fetch the next token; will re-seed via build()
      } else {
        setState(() => _secondsLeft = next);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final qr = ref.watch(studentQrProvider);
    final user = ref.watch(authProvider).user;

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentQrProvider),
      child: qr.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) {
          if (e is ApiException && e.statusCode == 409) {
            return EmptyStateView(
                message: e.message, icon: Icons.hourglass_empty);
          }
          return ErrorRetryView(
              message: '$e', onRetry: () => ref.invalidate(studentQrProvider));
        },
        data: (data) {
          _seedTicker(data);
          final seconds = _secondsLeft ?? data.expiresIn.clamp(1, 60);
          // Turns red for the last 10 seconds, same threshold as before.
          final urgent = seconds <= 10;
          final accent = urgent ? AppColors.roseText : AppColors.primary;

          return ListView(
            padding: const EdgeInsets.all(24),
            children: [
              Text(
                'My attendance QR',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.2,
                      color: AppColors.text,
                    ),
              ),
              if (user != null) ...[
                const SizedBox(height: 4),
                Text(
                  '${user.name} · ${user.studentId}',
                  textAlign: TextAlign.center,
                  style:
                      const TextStyle(fontSize: 13, color: AppColors.textMuted),
                ),
              ],
              const SizedBox(height: 22),
              Center(
                child: Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(AppTheme.radiusLg),
                    border: Border.all(color: AppColors.border),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.navy.withValues(alpha: 0.10),
                        blurRadius: 28,
                        offset: const Offset(0, 12),
                      ),
                    ],
                  ),
                  child: QrImageView(
                    data: data.qrPayload,
                    size: 240,
                    backgroundColor: Colors.white,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              // Countdown: a thin bar that drains over the 60s window, plus the seconds label.
              Center(
                child: SizedBox(
                  width: 240,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(999),
                    child: LinearProgressIndicator(
                      value: seconds / 60,
                      minHeight: 6,
                      backgroundColor: AppColors.primary.withValues(alpha: .12),
                      valueColor: AlwaysStoppedAnimation(accent),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Center(
                child: Text(
                  '${seconds}s',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        fontFeatures: const [FontFeature.tabularFigures()],
                        color: accent,
                      ),
                ),
              ),
              const SizedBox(height: 14),
              const Center(
                child: Text(
                  'Your QR code refreshes automatically every 60 seconds — just hold steady at the scan '
                  'station. A screenshot won\'t work.',
                  style: TextStyle(fontSize: 12.5, color: AppColors.textMuted),
                  textAlign: TextAlign.center,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
