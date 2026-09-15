// lib/widgets/update_dialog.dart
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/app_version.dart';
import '../providers/update_provider.dart';
import '../services/update_service.dart';

/// Call this once, after the frame that shows a home screen (student,
/// officer, or admin) has settled — e.g. from initState via
/// `WidgetsBinding.instance.addPostFrameCallback`. Silently does nothing
/// on a check failure (no connection, endpoint down, etc.) — an update
/// check should never block someone from using the app they already
/// have, except in the genuinely forced/unsupported cases below.
Future<void> checkAndShowUpdateDialog(BuildContext context, WidgetRef ref) async {
  final service = ref.read(updateServiceProvider);

  final UpdateCheckResult result;
  try {
    result = await service.checkForUpdate();
  } catch (_) {
    return;
  }

  if (result.urgency == UpdateUrgency.none || result.remote == null) return;
  if (!context.mounted) return;

  showDialog(
    context: context,
    barrierDismissible: result.urgency == UpdateUrgency.optional,
    builder: (_) => _UpdateDialog(
      version: result.remote!,
      urgency: result.urgency,
    ),
  );
}

class _UpdateDialog extends ConsumerStatefulWidget {
  const _UpdateDialog({required this.version, required this.urgency});

  final AppVersion version;
  final UpdateUrgency urgency;

  @override
  ConsumerState<_UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends ConsumerState<_UpdateDialog> {
  double? _progress; // null = not downloading yet
  bool _installing = false;
  String? _error;

  bool get _blocking =>
      widget.urgency == UpdateUrgency.forced ||
      widget.urgency == UpdateUrgency.unsupported;

  Future<void> _downloadAndInstall() async {
    final service = ref.read(updateServiceProvider);
    setState(() {
      _progress = 0;
      _error = null;
    });

    try {
      final hasPermission = await service.hasInstallPermission();
      if (!hasPermission) {
        await service.openInstallPermissionSettings();
        final grantedNow = await service.hasInstallPermission();
        if (!grantedNow) {
          setState(() {
            _progress = null;
            _error = 'Allow "Install unknown apps" for SOMS in Settings, then try again.';
          });
          return;
        }
      }

      final File apk = await service.downloadApk(
        widget.version,
        onProgress: (p) => setState(() => _progress = p),
      );

      setState(() => _installing = true);
      final opened = await service.installApk(apk);
      if (!opened && mounted) {
        setState(() {
          _installing = false;
          _error = "Couldn't open the installer. Try again, or download manually.";
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _progress = null;
        _installing = false;
        _error = "Download failed. Check your connection and try again.";
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = widget.version;
    final downloading = _progress != null && !_installing;

    return PopScope(
      canPop: !_blocking,
      child: AlertDialog(
        title: Text(
          widget.urgency == UpdateUrgency.unsupported
              ? 'Update required'
              : 'Update available',
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('SOMS ${v.versionName} is ready to install.'),
              if (widget.urgency == UpdateUrgency.unsupported) ...[
                const SizedBox(height: 8),
                Text(
                  'Your current version is no longer supported. Please update to continue.',
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              if (v.changelog != null && v.changelog!.trim().isNotEmpty) ...[
                const SizedBox(height: 12),
                Text("What's new:", style: Theme.of(context).textTheme.labelLarge),
                const SizedBox(height: 4),
                Text(v.changelog!),
              ],
              if (downloading) ...[
                const SizedBox(height: 16),
                LinearProgressIndicator(value: _progress),
                const SizedBox(height: 4),
                Text('${((_progress ?? 0) * 100).toStringAsFixed(0)}%'),
              ],
              if (_installing) ...[
                const SizedBox(height: 16),
                const LinearProgressIndicator(),
                const SizedBox(height: 4),
                const Text('Opening installer…'),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ],
            ],
          ),
        ),
        actions: [
          if (!_blocking && _progress == null)
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Later'),
            ),
          FilledButton(
            onPressed: (downloading || _installing) ? null : _downloadAndInstall,
            child: Text(_progress == null ? 'Update now' : 'Downloading…'),
          ),
        ],
      ),
    );
  }
}
