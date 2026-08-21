import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';

/// Reward points history (order-wise grants) for the logged-in partner.
class PartnerRewardsScreen extends StatefulWidget {
  const PartnerRewardsScreen({super.key});

  @override
  State<PartnerRewardsScreen> createState() => _PartnerRewardsScreenState();
}

class _PartnerRewardsScreenState extends State<PartnerRewardsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _rows = [];
  List<Map<String, dynamic>> _redemptions = [];
  List<Map<String, dynamic>> _shops = [];
  int _currentPoints = 0;
  int _totalGrantedAudit = 0;
  bool _loading = true;
  bool _submittingRedeem = false;
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
      final grantsJson = await _api.getPartnerRewardGrants(page: 1, perPage: 50);
      final redemptionJson = await _api.getPartnerRewardRedemptions(page: 1, perPage: 20);
      final shops = await _api.getPartnerShops();
      if (!mounted) return;
      final list = (grantsJson['data'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
      final redemptionList = (redemptionJson['data'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
      setState(() {
        _rows = list;
        _redemptions = redemptionList;
        _shops = shops.map((s) => {'id': s.id, 'name': s.name}).toList();
        _currentPoints = _parseInt(grantsJson['current_points']);
        _totalGrantedAudit = _parseInt(grantsJson['total_granted_audit']);
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
                      _buildRedeemCard(context),
                      const SizedBox(height: 16),
                      Text('Redemption requests', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      if (_redemptions.isEmpty)
                        const Card(child: ListTile(title: Text('No redemption requests yet.')))
                      else
                        ..._redemptions.map((r) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _redemptionTile(context, r),
                            )),
                      const SizedBox(height: 16),
                      Text('Reward grants history', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
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

  Widget _buildRedeemCard(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Redeem points', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 6),
            const Text('Send request to shop owner for cash or gift.'),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _shops.isEmpty || _submittingRedeem ? null : _openRedeemDialog,
                icon: const Icon(Icons.redeem),
                label: Text(_shops.isEmpty ? 'No linked shop found' : 'Request redemption'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openRedeemDialog() async {
    if (_shops.isEmpty) return;
    final formKey = GlobalKey<FormState>();
    final pointsController = TextEditingController();
    final noteController = TextEditingController();
    int selectedShopId = _parseInt(_shops.first['id']);
    String redemptionType = 'cash';

    final shouldSubmit = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Request redemption'),
            content: Form(
              key: formKey,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    DropdownButtonFormField<int>(
                      initialValue: selectedShopId,
                      decoration: const InputDecoration(labelText: 'Shop'),
                      items: _shops
                          .map((s) => DropdownMenuItem<int>(
                                value: _parseInt(s['id']),
                                child: Text((s['name'] ?? '').toString()),
                              ))
                          .toList(),
                      onChanged: (v) => selectedShopId = v ?? selectedShopId,
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: pointsController,
                      decoration: const InputDecoration(labelText: 'Points'),
                      keyboardType: TextInputType.number,
                      validator: (v) {
                        final n = int.tryParse(v ?? '');
                        if (n == null || n <= 0) return 'Enter valid points';
                        if (n > _currentPoints) return 'Exceeds current balance';
                        return null;
                      },
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: redemptionType,
                      decoration: const InputDecoration(labelText: 'Type'),
                      items: const [
                        DropdownMenuItem(value: 'cash', child: Text('Cash')),
                        DropdownMenuItem(value: 'gift', child: Text('Gift')),
                      ],
                      onChanged: (v) => redemptionType = v ?? 'cash',
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: noteController,
                      decoration: const InputDecoration(labelText: 'Note (optional)'),
                      minLines: 1,
                      maxLines: 3,
                    ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              FilledButton(
                onPressed: () {
                  if (formKey.currentState?.validate() ?? false) {
                    Navigator.pop(ctx, true);
                  }
                },
                child: const Text('Submit'),
              ),
            ],
          ),
        ) ??
        false;

    if (!shouldSubmit || !mounted) return;

    setState(() => _submittingRedeem = true);
    try {
      await _api.createPartnerRewardRedemption(
        shopId: selectedShopId,
        requestedPoints: int.parse(pointsController.text.trim()),
        redemptionType: redemptionType,
        note: noteController.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Redemption request submitted.')),
      );
      await _load();
    } on DioException catch (e) {
      if (!mounted) return;
      final msg = (e.response?.data is Map<String, dynamic>)
          ? ((e.response?.data['message'] ?? e.message)?.toString() ?? 'Request failed')
          : (e.message ?? 'Request failed');
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } finally {
      if (mounted) {
        setState(() => _submittingRedeem = false);
      }
    }
  }

  Widget _redemptionTile(BuildContext context, Map<String, dynamic> r) {
    final shop = r['shop'] as Map<String, dynamic>?;
    final status = (r['status'] ?? 'pending').toString();
    final note = (status == 'rejected'
            ? (r['rejection_reason']?.toString() ?? '')
            : (r['note']?.toString() ?? ''))
        .trim();
    final created = _formatDate(r['created_at']);

    Color chipBg;
    Color chipFg;
    switch (status) {
      case 'approved':
        chipBg = Colors.green.shade100;
        chipFg = Colors.green.shade900;
        break;
      case 'rejected':
        chipBg = Colors.red.shade100;
        chipFg = Colors.red.shade900;
        break;
      default:
        chipBg = Colors.amber.shade100;
        chipFg = Colors.amber.shade900;
    }

    return Card(
      child: ListTile(
        title: Text('-${_parseInt(r['requested_points'])} pts · ${(r['redemption_type'] ?? '').toString().toUpperCase()}'),
        subtitle: Text(
          [
            if (shop != null && (shop['name']?.toString().isNotEmpty ?? false)) shop['name'].toString(),
            if (created.isNotEmpty) created,
            if (note.isNotEmpty) note,
          ].join(' · '),
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: chipBg, borderRadius: BorderRadius.circular(999)),
          child: Text(
            status[0].toUpperCase() + status.substring(1),
            style: TextStyle(color: chipFg, fontWeight: FontWeight.w600, fontSize: 12),
          ),
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
                    'Available points',
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
                    'Total points Earned',
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
