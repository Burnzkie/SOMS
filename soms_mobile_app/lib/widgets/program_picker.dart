// lib/widgets/program_picker.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../providers/auth_provider.dart';

/// Guard shared by every form that saves an event's required programs:
/// nothing ticked means EVERY program is required and fined for absences,
/// so ask before saving. Returns true when the officer confirms.
Future<bool> confirmAllProgramsRequired(BuildContext context) async {
  final proceed = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('No program selected'),
      content: const Text(
        'EVERY program will be required to attend, and all absent students will be fined.\n\nSave anyway?',
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Go back')),
        FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Save anyway')),
      ],
    ),
  );
  return proceed == true;
}

/// Expandable "Required programs" checklist, grouped by department.
/// The parent owns the selection: pass [selected] in and receive the new
/// set through [onChanged]. Programs come from the public
/// GET /academic-programs (same config the registration form uses).
class ProgramPicker extends ConsumerStatefulWidget {
  const ProgramPicker(
      {super.key, required this.selected, required this.onChanged});

  final Set<String> selected;
  final ValueChanged<Set<String>> onChanged;

  @override
  ConsumerState<ProgramPicker> createState() => _ProgramPickerState();
}

class _ProgramPickerState extends ConsumerState<ProgramPicker> {
  Map<String, List<String>> _groups = {};
  bool _loading = true;
  bool _failed = false;

  // Programs already saved on the event that the config no longer lists. Captured once at
  // start (like the web picker's "Other" group) so they stay visible and can be unticked.
  late final Set<String> _initialSelection = {...widget.selected};

  @override
  void initState() {
    super.initState();
    _loadPrograms();
  }

  Future<void> _loadPrograms() async {
    final api = ref.read(apiClientProvider);
    try {
      final res = await api.get('/academic-programs');
      final raw = res['data'];
      if (!mounted) return;
      setState(() {
        if (raw is Map<String, dynamic>) {
          _groups = raw.map(
            (department, programs) =>
                MapEntry(department, List<String>.from(programs as List)),
          );
        }
        _loading = false;
      });
    } on ApiException {
      // The form still works without the list; the confirmation guard covers the "all programs" case.
      if (mounted) {
        setState(() {
          _loading = false;
          _failed = true;
        });
      }
    }
  }

  void _toggle(String program, bool checked) {
    final next = {...widget.selected};
    if (checked) {
      next.add(program);
    } else {
      next.remove(program);
    }
    widget.onChanged(next);
  }

  /// Config groups plus an "Other" group for saved programs the config doesn't list.
  Map<String, List<String>> get _displayGroups {
    final groups = {
      for (final e in _groups.entries) e.key: [...e.value]
    };
    final listed = groups.values.expand((programs) => programs).toSet();
    final orphans = _initialSelection.difference(listed);
    if (orphans.isNotEmpty) {
      groups['Other'] =
          {...(groups['Other'] ?? <String>[]), ...orphans}.toList();
    }
    return groups;
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Padding(
          padding: EdgeInsets.symmetric(vertical: 8),
          child: LinearProgressIndicator());
    }

    final allRequired = widget.selected.isEmpty;
    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      title: const Text('Required programs (optional)'),
      subtitle: Text(
        _failed
            ? 'Couldn\'t load programs — all programs will be required'
            : (allRequired
                ? '⚠ All programs required and fined'
                : '${widget.selected.length} selected'),
        style: TextStyle(
          fontSize: 12,
          // Same accessible orange used elsewhere in this app.
          color: allRequired ? const Color(0xFFA86400) : null,
        ),
      ),
      children: [
        for (final entry in _displayGroups.entries) ...[
          Align(
            alignment: Alignment.centerLeft,
            child: Padding(
              padding: const EdgeInsets.only(top: 8, bottom: 2),
              child: Text(
                entry.key.toUpperCase(),
                style: Theme.of(context)
                    .textTheme
                    .labelSmall
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
          ),
          for (final program in entry.value)
            CheckboxListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(program),
              value: widget.selected.contains(program),
              onChanged: (checked) => _toggle(program, checked == true),
            ),
        ],
      ],
    );
  }
}
