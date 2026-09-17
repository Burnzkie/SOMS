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
  // autoStart: false — we call _startCamera() ourselves below so a real
  // failure (permission denied, camera in use by another app, no camera
  // hardware, etc.) surfaces as actual text on screen instead of
  // mobile_scanner's default error widget, which gives no indication of
  // *why* it failed. That default widget is almost certainly what was
  // showing before this fix: a black frame with a plain error icon and
  // no message.
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    autoStart: false,
  );

  final List<_ScanFeedEntry> _feed = [];
  bool _busy = false;
  DateTime? _lastDetectionAt;

  bool _cameraReady = false;
  String? _cameraError;

  @override
  void initState() {
    super.initState();
    _startCamera();
  }

  Future<void> _startCamera() async {
    setState(() => _cameraError = null);
    try {
      await _controller.start();
      if (mounted) setState(() => _cameraReady = true);
    } on MobileScannerException catch (e) {
      if (!mounted) return;
      setState(() {
        _cameraReady = false;
        _cameraError = _describeError(e);
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _cameraReady = false;
        _cameraError = 'Camera failed to start: $e';
      });
    }
  }

  /// Maps mobile_scanner's error codes to something an officer can
  /// actually act on, rather than a raw exception string.
  String _describeError(MobileScannerException e) {
    switch (e.errorCode) {
      case MobileScannerErrorCode.permissionDenied:
        return 'Camera permission was denied. Go to Settings → Apps → '
            'SOMS → Permissions → Camera, and allow it, then come back '
            'and tap Retry.';
      case MobileScannerErrorCode.unsupported:
        return "This device's camera isn't supported for scanning.";
      default:
        // Includes genericError and anything not explicitly handled
        // above — e.errorDetails often has the underlying platform
        // message, which is worth showing verbatim while diagnosing.
        return 'Camera error: ${e.errorDetails?.message ?? e.errorCode.name}';
    }
  }

  /// Shared error UI for both failure paths: the initial controller.start()
  /// try/catch, and MobileScanner's own errorBuilder for failures that
  /// happen after a successful start. Same look either way, so an officer
  /// can't tell which internal path caught it — they just see what's wrong
  /// and a way to retry.
  Widget _buildCameraError(String message, {required VoidCallback onRetry}) {
    return ColoredBox(
      color: Colors.black,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.videocam_off_outlined, color: Colors.white70, size: 40),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70)),
              const SizedBox(height: 16),
              FilledButton(onPressed: onRetry, child: const Text('Retry')),
            ],
          ),
        ),
      ),
    );
  }

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
          IconButton(
            icon: const Icon(Icons.edit_note_outlined),
            onPressed: _openManualOverride,
            tooltip: 'Manual override',
          ),
        ],
      ),
      body: Column(
        children: [
          _StatusBar(online: online, pending: queue.length),
          Expanded(
            flex: 3,
            child: _cameraError != null
                ? _buildCameraError(_cameraError!, onRetry: _startCamera)
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      MobileScanner(
                        controller: _controller,
                        onDetect: _onDetect,
                        // Without this, a failure that happens *after*
                        // the initial controller.start() succeeds (camera
                        // taken by another app, a native hiccup mid-
                        // session, etc.) falls through to mobile_scanner's
                        // own built-in error widget — a bare icon with no
                        // text — bypassing our error UI entirely. This is
                        // almost certainly what was still happening: the
                        // try/catch around start() only covers the first
                        // failure mode, not this one.
                        errorBuilder: (context, error) =>
                            _buildCameraError(_describeError(error), onRetry: _startCamera),
                      ),
                      if (!_cameraReady)
                        const ColoredBox(
                          color: Colors.black,
                          child: Center(child: CircularProgressIndicator()),
                        ),
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

  /// Fallback for a student without a working QR (dead phone, broken
  /// screen) — mirrors the web scan station's "Manual override" panel
  /// (officer/attendance/scan.blade.php). Requires live password
  /// re-auth server-side, so this is online-only: there's no offline
  /// queue path for it, same as the web version.
  Future<void> _openManualOverride() async {
    final online = ref.read(connectivityProvider).value ?? true;
    if (!online) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Manual override needs a connection (live password re-auth).')),
      );
      return;
    }

    final studentIdCtrl = TextEditingController();
    final reasonCtrl = TextEditingController();
    final passwordCtrl = TextEditingController();
    String scanType = 'time_in';
    final formKey = GlobalKey<FormState>();

    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(ctx).viewInsets.bottom + 20),
          child: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Manual override', style: Theme.of(ctx).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(
                  'Use only when a student\'s QR can\'t be scanned. Requires your password.',
                  style: Theme.of(ctx).textTheme.bodySmall,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: studentIdCtrl,
                  decoration: const InputDecoration(labelText: 'Student ID'),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: scanType,
                  decoration: const InputDecoration(labelText: 'Scan type'),
                  items: const [
                    DropdownMenuItem(value: 'time_in', child: Text('Time in')),
                    DropdownMenuItem(value: 'time_out', child: Text('Time out')),
                  ],
                  onChanged: (v) => setSheetState(() => scanType = v ?? 'time_in'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: reasonCtrl,
                  decoration: const InputDecoration(labelText: 'Reason for manual override'),
                  minLines: 1,
                  maxLines: 3,
                  validator: (v) => (v == null || v.trim().length < 5) ? 'At least 5 characters' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: passwordCtrl,
                  decoration: const InputDecoration(labelText: 'Your password (re-auth)'),
                  obscureText: true,
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () {
                    if (formKey.currentState?.validate() ?? false) Navigator.pop(ctx, true);
                  },
                  child: const Text('Record override'),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (submitted != true || !mounted) return;

    final api = ref.read(apiClientProvider);
    try {
      final res = await api.post('/officer/attendance/sessions/${widget.session.id}/override', data: {
        'student_id': studentIdCtrl.text.trim(),
        'scan_type': scanType,
        'override_reason': reasonCtrl.text.trim(),
        'password': passwordCtrl.text,
      });
      final data = res['data'] as Map<String, dynamic>? ?? {};
      final name = data['student_name'] as String? ?? studentIdCtrl.text.trim();
      _pushFeed('$name — override', _titleCase(scanType.replaceAll('_', ' ')), Colors.teal, Icons.edit_note_outlined);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Manual override recorded for $name.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}

class _StatusBar extends StatelessWidget {
  const _StatusBar({required this.online, required this.pending});

  final bool online;
  final int pending;

  @override
  Widget build(BuildContext context) {
    final color = online ? Colors.green : Colors.orange;
    // Accessibility fix (Sep 2026) — `color` drives real status text here
    // ("Online"/"Offline — ..."), not just the wifi icon. Colors.orange
    // measured 2.16:1 on white, under WCAG AA's 4.5:1.
    final textColor = online ? const Color(0xFF39843C) : const Color(0xFFA86400);
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
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: textColor),
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
