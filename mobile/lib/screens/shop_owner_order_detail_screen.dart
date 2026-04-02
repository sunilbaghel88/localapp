import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/order.dart';
import '../services/api_service.dart';

class ShopOwnerOrderDetailScreen extends StatefulWidget {
  final int orderId;

  const ShopOwnerOrderDetailScreen({super.key, required this.orderId});

  @override
  State<ShopOwnerOrderDetailScreen> createState() => _ShopOwnerOrderDetailScreenState();
}

class _ShopOwnerOrderDetailScreenState extends State<ShopOwnerOrderDetailScreen> {
  final ApiService _api = ApiService();
  Order? _order;
  bool _loading = true;
  String? _error;

  String? _status;
  String? _paymentStatus;
  bool _saving = false;

  final _statuses = const ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
  final _paymentStatuses = const ['pending', 'paid', 'failed', 'refunded'];

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final o = await _api.getShopOrder(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = o;
        _status = o.status;
        _paymentStatus = o.paymentStatus;
        _loading = false;
      });
    } catch (e) {
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

  Future<void> _update() async {
    if (_order == null || _status == null || _paymentStatus == null) return;
    setState(() => _saving = true);
    try {
      await _api.updateShopOrder(
        widget.orderId,
        status: _status!,
        paymentStatus: _paymentStatus!,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order updated')));
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Update failed: $e')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final currency = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    return Scaffold(
      appBar: AppBar(
        title: Text(order != null ? 'Order #${order.id}' : 'Order'),
      ),
      body: _loading && order == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && order == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : order == null
                  ? const Center(child: Text('Not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Order Status', style: TextStyle(fontWeight: FontWeight.w600)),
                                    Text(order.status),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                if (order.shop != null)
                                  Text('Shop: ${order.shop!.name}', style: Theme.of(context).textTheme.bodyMedium),
                                const SizedBox(height: 12),
                                Text('Payment Status: ${order.paymentStatus}', style: Theme.of(context).textTheme.bodyMedium),
                              ],
                            ),
                          ),
                        ),

                        const SizedBox(height: 16),
                        if (order.address != null) ...[
                          Text('Shipping Address', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(order.address!.fullAddress))),
                          const SizedBox(height: 16),
                        ],

                        Text('Items', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        ...order.items.map((item) {
                          return ListTile(
                            title: Text(item.name),
                            subtitle: Text('Qty: ${item.quantity}'),
                            trailing: Text(currency.format(item.total), style: const TextStyle(fontWeight: FontWeight.bold)),
                          );
                        }),

                        const Divider(height: 32),

                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Update Order', style: Theme.of(context).textTheme.titleMedium),
                                const SizedBox(height: 12),
                                DropdownMenu<String>(
                                  label: const Text('Order status'),
                                  initialSelection: _status,
                                  onSelected: (v) => setState(() => _status = v),
                                  dropdownMenuEntries: _statuses
                                      .map((s) => DropdownMenuEntry<String>(value: s, label: s))
                                      .toList(),
                                ),
                                const SizedBox(height: 12),
                                DropdownMenu<String>(
                                  label: const Text('Payment status'),
                                  initialSelection: _paymentStatus,
                                  onSelected: (v) => setState(() => _paymentStatus = v),
                                  dropdownMenuEntries: _paymentStatuses
                                      .map((s) => DropdownMenuEntry<String>(value: s, label: s))
                                      .toList(),
                                ),
                                const SizedBox(height: 16),
                                SizedBox(
                                  width: double.infinity,
                                  child: FilledButton(
                                    onPressed: _saving ? null : _update,
                                    child: _saving
                                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                        : const Text('Update'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),

                        const SizedBox(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total', style: TextStyle(fontWeight: FontWeight.w600)),
                            Text(currency.format(order.grandTotal), style: const TextStyle(fontWeight: FontWeight.w800)),
                          ],
                        ),
                      ],
                    ),
    );
  }
}

