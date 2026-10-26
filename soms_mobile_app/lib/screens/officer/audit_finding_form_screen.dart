// lib/screens/officer/audit_finding_form_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/audit_models.dart';
import '../../providers/audit_providers.dart';
import '../../providers/auth_provider.dart';

/// Records a new audit finding (POST /api/v1/officer/audit/findings). It
/// only records what the Auditor found: it never changes the fine or
/// attendance record the finding is about. [initial] pre-fills the form
/// when it is opened from a discrepancy.
class AuditFindingFormScreen extends ConsumerStatefulWidget {
  const AuditFindingFormScreen({super.key, this.initial});

  final AuditFindingDraft? initial;

  @override
  ConsumerState<AuditFindingFormScreen> createState() => _AuditFindingFormScreenState();
}

class _AuditFindingFormScreenState extends ConsumerState<AuditFindingFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _issue;
  late final TextEditingController _evidence;
  late final TextEditingController _referenceId;
  late String _referenceType;
  late String _severity;

  // Disables the button while the request is in flight so a double-tap can't save twice.
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final draft = widget.initial;
    _title = TextEditingController(text: draft?.title ?? '');
    _issue = TextEditingController(text: draft?.issue ?? '');
    _evidence = TextEditingController();
    _referenceId = TextEditingController(text: draft == null ? '' : '${draft.referenceId}');
    _referenceType = draft?.referenceType ?? 'general';
    _severity = draft?.severity ?? 'medium';
  }

  @override
  void dispose() {
    _title.dispose();
    _issue.dispose();
    _evidence.dispose();
    _referenceId.dispose();
    super.dispose();
  }

  /// First validation message the server sent, or its general message.
  String _firstError(ApiException e) {
    final errors = e.errors;
    if (errors != null && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) return '${first.first}';
    }
    return e.message;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    final api = ref.read(apiClientProvider);
    final referenceId = int.tryParse(_referenceId.text.trim());
    final evidence = _evidence.text.trim();

    try {
      await api.post('/officer/audit/findings', data: {
        'reference_type': _referenceType,
        if (referenceId != null) 'reference_id': referenceId,
        'title': _title.text.trim(),
        'issue': _issue.text.trim(),
        if (evidence.isNotEmpty) 'evidence': evidence,
        'severity': _severity,
      });

      ref.invalidate(auditFindingsProvider);
      ref.invalidate(auditSummaryProvider);

      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pop(true);
      messenger.showSnackBar(const SnackBar(content: Text('Finding recorded.')));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = _firstError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('New audit finding')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'Describe what you found. This records the issue; it does not change the record itself.',
              style: TextStyle(fontSize: 12.5),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _title,
              maxLength: 160,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Title'),
              validator: (v) => (v ?? '').trim().isEmpty ? 'Enter a title.' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _issue,
              maxLength: 2000,
              minLines: 3,
              maxLines: 6,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Issue discovered', alignLabelWithHint: true),
              validator: (v) => (v ?? '').trim().isEmpty ? 'Describe the issue.' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _evidence,
              maxLength: 2000,
              minLines: 2,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Evidence / notes (optional)', alignLabelWithHint: true),
            ),
            const SizedBox(height: 8),
            Text('Severity', style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              children: [
                for (final level in auditSeverities)
                  ChoiceChip(
                    label: Text('${level[0].toUpperCase()}${level.substring(1)}'),
                    selected: _severity == level,
                    onSelected: (_) => setState(() => _severity = level),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            Text('Record involved', style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              children: [
                for (final type in auditReferenceTypes)
                  ChoiceChip(
                    label: Text('${type[0].toUpperCase()}${type.substring(1)}'),
                    selected: _referenceType == type,
                    onSelected: (_) => setState(() => _referenceType = type),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _referenceId,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Record ID (optional)'),
              validator: (v) {
                final text = (v ?? '').trim();
                if (text.isEmpty) return null;
                final id = int.tryParse(text);
                return (id == null || id < 1) ? 'Enter a whole number, or leave blank.' : null;
              },
            ),
            if (_error != null) ...[
              const SizedBox(height: 14),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Record finding'),
            ),
          ],
        ),
      ),
    );
  }
}
