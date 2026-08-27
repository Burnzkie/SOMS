import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../core/secure_storage.dart';
import '../models/queued_scan.dart';
import 'auth_provider.dart';

/// True when the device currently has *some* network path — mirrors what
/// ScanScreen needs to decide "try live" vs "queue it", not full internet
/// reachability. A live call can still fail (server down, captive portal);
/// that's handled by falling back to the queue on any connection-type
/// ApiException, not by trusting this stream alone.
final connectivityProvider = StreamProvider<bool>((ref) {
  final connectivity = Connectivity();
  return connectivity.onConnectivityChanged.map(
    (results) => !results.contains(ConnectivityResult.none),
  );
});

/// Result of syncing one queued scan, for the pending-queue UI.
class ScanSyncOutcome {
  ScanSyncOutcome({required this.localId, required this.success, this.status, this.reason});

  final String localId;
  final bool success;
  final String? status; // present | absent | already_marked | flagged_for_review | rejected
  final String? reason;
}

class ScanQueueController extends StateNotifier<List<QueuedScan>> {
  ScanQueueController(this._api) : super([]) {
    _load();
  }

  final ApiClient _api;
  bool _syncing = false;

  static const _uuidChars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  static String _newLocalId() {
    final rnd = Random();
    return List.generate(12, (_) => _uuidChars[rnd.nextInt(_uuidChars.length)]).join();
  }

  Future<void> _load() async {
    final raw = await SecureStorage.instance.readOfflineQueue();
    if (raw == null || raw.isEmpty) return;
    try {
      final list = (jsonDecode(raw) as List<dynamic>)
          .map((e) => QueuedScan.fromJson(e as Map<String, dynamic>))
          .toList();
      state = list;
    } catch (_) {
      // Corrupt/stale queue payload — don't crash the scan screen over it.
      await SecureStorage.instance.clearOfflineQueue();
    }
  }

  Future<void> _persist() async {
    await SecureStorage.instance.writeOfflineQueue(
      jsonEncode(state.map((s) => s.toJson()).toList()),
    );
  }

  Future<void> enqueue(QueuedScan scan) async {
    state = [...state, scan];
    await _persist();
  }

  /// Sends every queued scan to scan-batch (max 200 per the backend
  /// validation, so chunk if the queue somehow grows past that) and
  /// removes whatever the server accepted. Scans it couldn't process
  /// (e.g. still no connection) stay queued for the next attempt.
  Future<List<ScanSyncOutcome>> sync() async {
    if (_syncing || state.isEmpty) return [];
    _syncing = true;
    final outcomes = <ScanSyncOutcome>[];

    try {
      final batch = state.take(200).toList();

      final res = await _api.post('/officer/attendance/scan-batch', data: {
        'scans': batch.map((s) => s.toScanBatchItem()).toList(),
      });

      final results = (res['data'] as List<dynamic>? ?? []);
      final synced = <String>{};

      for (var i = 0; i < batch.length && i < results.length; i++) {
        final r = results[i] as Map<String, dynamic>;
        final status = r['status'] as String?;
        synced.add(batch[i].localId);
        outcomes.add(ScanSyncOutcome(
          localId: batch[i].localId,
          success: status != null,
          status: status,
          reason: r['reason'] as String?,
        ));
      }

      state = state.where((s) => !synced.contains(s.localId)).toList();
      await _persist();
    } on ApiException catch (e) {
      // Still offline (or server unreachable) — leave the queue as-is,
      // the next connectivity event or manual "Sync now" will retry.
      outcomes.add(ScanSyncOutcome(localId: '', success: false, reason: e.message));
    } finally {
      _syncing = false;
    }

    return outcomes;
  }

  static String newLocalId() => _newLocalId();
}

final scanQueueProvider = StateNotifierProvider<ScanQueueController, List<QueuedScan>>((ref) {
  final api = ref.watch(apiClientProvider);
  final controller = ScanQueueController(api);

  // Auto-sync the moment connectivity comes back, in addition to the
  // manual "Sync now" button on ScanScreen.
  ref.listen<AsyncValue<bool>>(connectivityProvider, (previous, next) {
    final wasOffline = previous?.value == false;
    final nowOnline = next.value == true;
    if (wasOffline && nowOnline) {
      controller.sync();
    }
  });

  return controller;
});
