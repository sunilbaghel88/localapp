import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';

/// Reward points history (order-wise grants) for the logged-in electrician.
class ElectricianRewardsScreen extends StatefulWidget {
  const ElectricianRewardsScreen({super.key});

  @override
  State<ElectricianRewardsScreen> createState() => _ElectricianRewardsScreenState();
}

class _ElectricianRewardsScreenState extends State<ElectricianRewardsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _rows = [];
  int _currentPoints = 0;
  int _totalGrantedAudit = 0;
  bool _loading = true;
  String? _error;

  static final DateFormat _dateFmt = DateFormat('d MMM yyyy, h:mm a');

  @override
  void initState() {
    super.initState();
    _load();
  }

  /// Normalizes odd API date strings and formats for display in local time.
  static String _formatDate(dynamic raw) {
    if (raw == null) return '';
    var s = raw.toString().trim();
    if (s.isEmpty) return '';
    // Fix malformed ISO-like strings (e.g. "2026-0302T..." → "2026-03-02T...")
    final bad = RegExp(r'^(\d{4})-(\d{2})(\d{2})T');
    final m = bad.firstMatch(s);
    if (m != null) {
      s = '${m[1]}-${m[2]}-${m[3]}T${s.substring(m.end)}';
    }
    final dt = DateTime.tryParse(s);
    if (dt != null) {
      return _dateFmt.format(dt.toLocal());
    }
    return s;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final json = await _api.getElectricianRewardGrants(page: 1, perPage: 50);
      if (!mounted) return;
      final list = (json['data'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
      setState(() {
        _rows = list;
        _currentPoints = _parseInt(json['current_points']);
        _totalGrantedAudit = _parseInt(json['total_granted_audit']);
        _loading = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.response?.data?.toString() ?? e.message ?? 'Failed to load';
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  static int _parseInt(dynamic v) {
    if (v == null) return 0;
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Reward points'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.canPop() ? context.pop() : context.go('/home'),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _buildSummaryCards(context),
                      const SizedBox(height: 16),
                      if (_rows.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 48),
                          child: Center(child: Text('No reward points yet.')),
                        )
                      else
                        ..._rows.map((r) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _grantTile(context, r),
                            )),
                    ],
                  ),
                ),
    );
  }

  Widget _buildSummaryCards(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        Expanded(
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Current points',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '$_currentPoints',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: scheme.primary,
                        ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Total granted (audit)',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '$_totalGrantedAudit',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _grantTile(BuildContext context, Map<String, dynamic> r) {
    final order = r['order'] as Map<String, dynamic>?;
    final shop = order != null ? order['shop'] as Map<String, dynamic>? : null;
    final grantedBy = r['granted_by'] as Map<String, dynamic>?;
    final createdRaw = r['created_at'];
    final when = _formatDate(createdRaw);

    return Card(
      child: ListTile(
        title: Text('+${r['points'] ?? 0} pts · Order #${r['order_id'] ?? '—'}'),
        subtitle: Text(
          [
            if (shop != null && (shop['name']?.toString().isNotEmpty ?? false)) shop['name'].toString(),
            if (grantedBy != null) 'By ${grantedBy['name']}',
            if (when.isNotEmpty) when,
          ].join(' · '),
        ),
        isThreeLine: true,
      ),
    );
  }
}
