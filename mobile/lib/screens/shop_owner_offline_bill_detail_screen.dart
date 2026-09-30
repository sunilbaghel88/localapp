import 'package:cached_network_image/cached_network_image.dart';
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
                                if ((bill.remarks ?? '').trim().isNotEmpty)
                                  _row('Remarks', bill.remarks!.trim()),
                                if (bill.createdAt != null)
                                  _row('Date', dateFormat.format(bill.createdAt!.toLocal())),
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
