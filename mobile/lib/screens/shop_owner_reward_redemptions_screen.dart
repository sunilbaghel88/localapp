import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../services/api_service.dart';

class ShopOwnerRewardRedemptionsScreen extends StatefulWidget {
  const ShopOwnerRewardRedemptionsScreen({super.key});

  @override
  State<ShopOwnerRewardRedemptionsScreen> createState() => _ShopOwnerRewardRedemptionsScreenState();
}

class _ShopOwnerRewardRedemptionsScreenState extends State<ShopOwnerRewardRedemptionsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  bool _processing = false;
  String _statusFilter = 'pending';
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final json = await _api.getShopRewardRedemptions(status: _statusFilter, perPage: 50);
      if (!mounted) return;
      setState(() {
        _rows = (json['data'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  int _parseInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  Future<void> _approve(int requestId) async {
    setState(() => _processing = true);
    try {
      final data = await _api.approveShopRewardRedemption(requestId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((data['message'] ?? 'Approved').toString())),
      );
      await _load();
    } on DioException catch (e) {
      if (!mounted) return;
      final msg = (e.response?.data is Map<String, dynamic>)
          ? ((e.response?.data['message'] ?? e.message)?.toString() ?? 'Approve failed')
          : (e.message ?? 'Approve failed');
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  Future<void> _reject(int requestId) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reject request'),
        content: TextField(
          controller: controller,
          minLines: 2,
          maxLines: 4,
          decoration: const InputDecoration(
            labelText: 'Reason',
            hintText: 'Enter rejection reason',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('Reject'),
          ),
        ],
      ),
    );

    if (!mounted || reason == null || reason.isEmpty) return;

    setState(() => _processing = true);
    try {
      final data = await _api.rejectShopRewardRedemption(requestId, reason: reason);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((data['message'] ?? 'Rejected').toString())),
      );
      await _load();
    } on DioException catch (e) {
      if (!mounted) return;
      final msg = (e.response?.data is Map<String, dynamic>)
          ? ((e.response?.data['message'] ?? e.message)?.toString() ?? 'Reject failed')
          : (e.message ?? 'Reject failed');
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Reward Redemptions'),
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
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'pending', label: Text('Pending')),
                          ButtonSegment(value: 'approved', label: Text('Approved')),
                          ButtonSegment(value: 'rejected', label: Text('Rejected')),
                        ],
                        selected: {_statusFilter},
                        onSelectionChanged: (set) {
                          final value = set.first;
                          setState(() => _statusFilter = value);
                          _load();
                        },
                      ),
                      const SizedBox(height: 12),
                      if (_rows.isEmpty)
                        const Card(child: ListTile(title: Text('No requests found.')))
                      else
                        ..._rows.map((r) {
                          final user = r['user'] as Map<String, dynamic>?;
                          final shop = r['shop'] as Map<String, dynamic>?;
                          final status = (r['status'] ?? '').toString();
                          final requestId = _parseInt(r['id']);
                          final subtitleParts = <String>[
                            if (shop != null && (shop['name']?.toString().isNotEmpty ?? false)) shop['name'].toString(),
                            '${_parseInt(r['requested_points'])} pts',
                            (r['redemption_type'] ?? '').toString().toUpperCase(),
                          ];
                          if ((r['note']?.toString() ?? '').trim().isNotEmpty) {
                            subtitleParts.add(r['note'].toString().trim());
                          }
                          if ((r['rejection_reason']?.toString() ?? '').trim().isNotEmpty) {
                            subtitleParts.add('Reason: ${r['rejection_reason']}');
                          }

                          return Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    user?['name']?.toString() ?? 'Electrician',
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(subtitleParts.join(' · ')),
                                  const SizedBox(height: 10),
                                  if (status == 'pending')
                                    Row(
                                      children: [
                                        Expanded(
                                          child: FilledButton.icon(
                                            onPressed: _processing ? null : () => _approve(requestId),
                                            icon: const Icon(Icons.check_circle_outline),
                                            label: const Text('Approve'),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: OutlinedButton.icon(
                                            onPressed: _processing ? null : () => _reject(requestId),
                                            icon: const Icon(Icons.cancel_outlined),
                                            label: const Text('Reject'),
                                          ),
                                        ),
                                      ],
                                    )
                                  else
                                    Text('Status: ${status[0].toUpperCase()}${status.substring(1)}'),
                                ],
                              ),
                            ),
                          );
                        }),
                    ],
                  ),
                ),
    );
  }
}
