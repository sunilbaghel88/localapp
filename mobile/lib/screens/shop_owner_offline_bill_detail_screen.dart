import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/offline_bill.dart';
import '../services/api_service.dart';

class ShopOwnerOfflineBillDetailScreen extends StatefulWidget {
  final int billId;

  const ShopOwnerOfflineBillDetailScreen({super.key, required this.billId});

  @override
  State<ShopOwnerOfflineBillDetailScreen> createState() =>
      _ShopOwnerOfflineBillDetailScreenState();
}

class _ShopOwnerOfflineBillDetailScreenState
    extends State<ShopOwnerOfflineBillDetailScreen> {
  final ApiService _api = ApiService();
  OfflineBill? _bill;
  bool _loading = true;
  bool _sendingReminder = false;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final bill = await _api.getOfflineBill(widget.billId);
      if (!mounted) return;
      setState(() {
        _bill = bill;
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _sendReminder() async {
    final bill = _bill;
    if (bill == null) return;
    final due = bill.closingBalance ?? 0;
    if (due <= 0) return;

    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final name = bill.customerName ?? 'this customer';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Send reminder SMS?'),
        content: Text('Send a dues reminder to $name for ${money.format(due)}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Send SMS'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _sendingReminder = true);
    try {
      final result = await _api.remindOfflineBillDues(
        shopId: bill.shopId,
        customerId: bill.customerId,
      );
      if (!mounted) return;
      final message = result['message']?.toString().trim();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((message != null && message.isNotEmpty) ? message : 'Reminder SMS sent.')),
      );
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_apiError(e))));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _sendingReminder = false);
    }
  }

  String _apiError(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errs = data['errors'] as Map;
      for (final v in errs.values) {
        if (v is List && v.isNotEmpty) return v.first.toString();
        if (v != null) return v.toString();
      }
      if (data['message'] != null) return data['message'].toString();
    }
    if (data is Map && data['message'] != null) return data['message'].toString();
    return e.message ?? 'Request failed';
  }

  @override
  Widget build(BuildContext context) {
    final bill = _bill;
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final dateFormat = DateFormat('dd MMM yyyy, hh:mm a');

    return Scaffold(
      appBar: AppBar(title: const Text('Offline bill')),
      body: _loading && bill == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && bill == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : bill == null
                  ? const Center(child: Text('Not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        if (bill.fullImageUrl != null) ...[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: CachedNetworkImage(
                              imageUrl: bill.fullImageUrl!,
                              fit: BoxFit.cover,
                              placeholder: (_, _) => const SizedBox(
                                height: 180,
                                child: Center(child: CircularProgressIndicator()),
                              ),
                              errorWidget: (_, _, _) => const SizedBox(
                                height: 180,
                                child: Center(child: Icon(Icons.broken_image_outlined, size: 48)),
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _row('Type', bill.typeLabel),
                                if (bill.paymentModeLabel != null)
                                  _row('Mode', bill.paymentModeLabel!),
                                _row('Shop', bill.shopName ?? '—'),
                                _row('Customer', bill.customerName ?? '—'),
                                if ((bill.customerPhone ?? '').isNotEmpty)
                                  _row('Customer mobile', bill.customerPhone!),
                                _row('Partner', bill.partnerName ?? '—'),
                                if (bill.type == 'debit')
                                  _row('Reward points', '${bill.rewardPoints}'),
                                _row('Amount', money.format(bill.amount)),
                                if (bill.closingBalance != null)
                                  _row('Closing balance', money.format(bill.closingBalance)),
                                if ((bill.remarks ?? '').trim().isNotEmpty)
                                  _row('Remarks', bill.remarks!.trim()),
                                if (bill.createdAt != null)
                                  _row('Date', dateFormat.format(bill.createdAt!.toLocal())),
                                if ((bill.closingBalance ?? 0) > 0) ...[
                                  const SizedBox(height: 8),
                                  SizedBox(
                                    width: double.infinity,
                                    child: FilledButton.icon(
                                      onPressed: _sendingReminder ? null : _sendReminder,
                                      icon: _sendingReminder
                                          ? const SizedBox(
                                              width: 16,
                                              height: 16,
                                              child: CircularProgressIndicator(strokeWidth: 2),
                                            )
                                          : const Icon(Icons.sms_outlined),
                                      label: const Text('Send dues reminder'),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          const SizedBox(height: 2),
          Text(value, style: Theme.of(context).textTheme.bodyLarge),
        ],
      ),
    );
  }
}
