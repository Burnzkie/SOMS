import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/api_client.dart';
import '../../models/event.dart';
import '../../models/queued_scan.dart';
import '../../providers/auth_provider.dart';
import '../../providers/scan_providers.dart';

/// Officer camera-based attendance scan for one session. Mirrors the web
/// scan flow (officer/attendance/scan.blade.php) but adds the offline
/// queue: a scan attempted with no connectivity is stored via
/// ScanQueueController instead of being lost, and gets sent through
/// POST .../attendance/scan-batch once the device is back online.
///
/// QR payload format is "<user_id>:<token>" — see QrData.qrPayload and
/// StudentApiController::qrCurrent on the backend.
class OfficerScanScreen extends ConsumerStatefulWidget {
  const OfficerScanScreen({super.key, required this.session, required this.eventTitle});

  final EventSession session;
  final String eventTitle;

  @override
  ConsumerState<OfficerScanScreen> createState() => _OfficerScanScreenState();
}

class _ScanFeedEntry {
  _ScanFeedEntry({required this.label, required this.detail, required this.color, required this.icon});
  final String label;
  final String detail;
  final Color color;
  final IconData icon;
}

class _OfficerScanScreenState extends ConsumerState<OfficerScanScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  final List<_ScanFeedEntry> _feed = [];
  bool _busy = false;
  DateTime? _lastDetectionAt;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final queue = ref.watch(scanQueueProvider);
    final pendingForThisSession = queue.where((q) => q.sessionId == widget.session.id).length;
    final online = ref.watch(connectivityProvider).value ?? true;

    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.eventTitle} · ${_titleCase(widget.session.sessionType)}'),
        actions: [
          IconButton(
            icon: ValueListenableBuilder(
              valueListenable: _controller,
              builder: (context, state, child) => Icon(state.torchState == TorchState.on
                  ? Icons.flash_on
                  : Icons.flash_off),
            ),
            onPressed: () => _controller.toggleTorch(),
            tooltip: 'Toggle flashlight',
          ),
        ],
      ),
      body: Column(
        children: [
          _StatusBar(online: online, pending: queue.length),
          Expanded(
            flex: 3,
            child: Stack(
              fit: StackFit.expand,
              children: [
                MobileScanner(controller: _controller, onDetect: _onDetect),
                if (_busy)
                  const ColoredBox(
                    color: Colors.black38,
                    child: Center(child: CircularProgressIndicator()),
                  ),
              ],
            ),
          ),
          if (pendingForThisSession > 0)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              child: Row(
                children: [
                  Icon(Icons.cloud_off, size: 16, color: Theme.of(context).colorScheme.tertiary),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '$pendingForThisSession scan(s) from this session queued offline.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                  TextButton(onPressed: _manualSync, child: const Text('Sync now')),
                ],
              ),
            ),
          Expanded(
            flex: 2,
            child: _feed.isEmpty
                ? const Center(child: Text('Scan results will appear here.'))
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    reverse: true,
                    itemCount: _feed.length,
                    itemBuilder: (context, i) {
                      final entry = _feed[_feed.length - 1 - i];
                      return ListTile(
                        dense: true,
                        leading: Icon(entry.icon, color: entry.color),
                        title: Text(entry.label),
                        subtitle: Text(entry.detail),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;

    // mobile_scanner keeps firing while the same code is in frame even
    // with detectionSpeed: noDuplicates in some edge cases — a small
    // cooldown on top of that stops accidental double-processing.
    final now = DateTime.now();
    if (_lastDetectionAt != null && now.difference(_lastDetectionAt!) < const Duration(milliseconds: 800)) {
      return;
    }
    _lastDetectionAt = now;

    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null) return;

    final parts = raw.split(':');
    if (parts.length != 2) {
      _pushFeed('Unrecognized code', raw, Colors.grey, Icons.qr_code_2_outlined);
      return;
    }

    final userId = int.tryParse(parts[0]);
    final token = parts[1];
    if (userId == null || token.isEmpty) {
      _pushFeed('Unrecognized code', raw, Colors.grey, Icons.qr_code_2_outlined);
      return;
    }

    setState(() => _busy = true);
    try {
      await _processScan(userId, token);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _processScan(int userId, String token) async {
    final api = ref.read(apiClientProvider);
    final online = ref.read(connectivityProvider).value ?? true;

    if (!online) {
      await _queueScan(userId, token);
      return;
    }

    try {
      final res = await api.post('/officer/attendance/scan', data: {
        'session_id': widget.session.id,
        'user_id': userId,
        'token': token,
      });
      final data = res['data'] as Map<String, dynamic>? ?? {};
      _renderResult(data);
    } on ApiException catch (e) {
      if (e.statusCode == null) {
        // No status code from a DioException almost always means the
        // request never reached the server (no connection / timeout) —
        // exactly the case the offline queue exists for.
        await _queueScan(userId, token);
      } else {
        _pushFeed('Scan failed', e.message, Colors.red, Icons.error_outline);
      }
    }
  }

  Future<void> _queueScan(int userId, String token) async {
    await ref.read(scanQueueProvider.notifier).enqueue(QueuedScan(
          localId: ScanQueueController.newLocalId(),
          sessionId: widget.session.id,
          userId: userId,
          token: token,
          deviceScannedAt: DateTime.now(),
          eventTitleHint: widget.eventTitle,
        ));
    _pushFeed('Queued (offline)', 'Student #$userId — will sync automatically', Colors.orange, Icons.cloud_off);
  }

  void _renderResult(Map<String, dynamic> data) {
    final status = data['status'] as String?;
    final name = data['student_name'] as String? ?? 'Student #${data['user_id']}';
    switch (status) {
      case 'present':
        _pushFeed(name, 'Marked present', Colors.green, Icons.check_circle_outline);
        break;
      case 'absent':
        _pushFeed(name, 'Recorded as absent (outside window)', Colors.orange, Icons.warning_amber_outlined);
        break;
      case 'already_marked':
        _pushFeed(name, 'Already scanned for this session', Colors.blueGrey, Icons.info_outline);
        break;
      case 'flagged_for_review':
        _pushFeed(name, 'Flagged for review (clock drift)', Colors.deepOrange, Icons.flag_outlined);
        break;
      case 'rejected':
        _pushFeed(name, (data['reason'] as String?) ?? 'Rejected', Colors.red, Icons.cancel_outlined);
        break;
      default:
        _pushFeed(name, 'Unknown response', Colors.grey, Icons.help_outline);
    }
  }

  void _pushFeed(String label, String detail, Color color, IconData icon) {
    setState(() => _feed.add(_ScanFeedEntry(label: label, detail: detail, color: color, icon: icon)));
  }

  Future<void> _manualSync() async {
    final outcomes = await ref.read(scanQueueProvider.notifier).sync();
    if (!mounted) return;
    if (outcomes.isEmpty) return;

    final failed = outcomes.where((o) => !o.success).length;
    final synced = outcomes.length - failed;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(
        synced > 0 ? 'Synced $synced queued scan(s).' : 'Still offline — will retry automatically.',
      )),
    );
  }

  String _titleCase(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}

class _StatusBar extends StatelessWidget {
  const _StatusBar({required this.online, required this.pending});

  final bool online;
  final int pending;

  @override
  Widget build(BuildContext context) {
    final color = online ? Colors.green : Colors.orange;
    return Container(
      width: double.infinity,
      color: color.withValues(alpha: 0.12),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: Row(
        children: [
          Icon(online ? Icons.wifi : Icons.wifi_off, size: 16, color: color),
          const SizedBox(width: 6),
          Text(
            online ? 'Online — scans sync live' : 'Offline — scans are being queued on this device',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: color),
          ),
          const Spacer(),
          if (pending > 0) Text('$pending pending total', style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
