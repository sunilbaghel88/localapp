import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/order.dart';
import '../services/api_service.dart';

class ShopOwnerOrderDetailScreen extends StatefulWidget {
  final int orderId;

  const ShopOwnerOrderDetailScreen({super.key, required this.orderId});

  @override
  State<ShopOwnerOrderDetailScreen> createState() =>
      _ShopOwnerOrderDetailScreenState();
}

class _ShopOwnerOrderDetailScreenState
    extends State<ShopOwnerOrderDetailScreen> {
  final ApiService _api = ApiService();
  Order? _order;
  bool _loading = true;
  String? _error;

  String? _status;
  String? _paymentStatus;
  bool _saving = false;
  bool _grantingReward = false;

  final _statuses = const [
    'pending',
    'processing',
    'shipped',
    'delivered',
    'cancelled',
  ];
  final _paymentStatuses = const ['pending', 'paid', 'failed', 'refunded'];

  static final _dateTimeFmt = DateFormat.yMMMd().add_jm();

  String _formatDateTime(DateTime? d) {
    if (d == null) return '—';
    return _dateTimeFmt.format(d.toLocal());
  }

  Widget _infoLine(BuildContext context, String label, String value) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(
              label,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: muted),
            ),
          ),
          Expanded(
            flex: 3,
            child: Text(
              value,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }

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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Order updated')));
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Update failed: $e')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _grantReward() async {
    final order = _order;
    if (order == null || order.electrician == null) return;

    final pointsController = TextEditingController();
    final notesController = TextEditingController();
    String? validationError;

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Grant Reward Points'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: pointsController,
                    keyboardType: const TextInputType.numberWithOptions(
                      signed: false,
                      decimal: false,
                    ),
                    decoration: InputDecoration(
                      labelText: 'Points to grant',
                      errorText: validationError,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: notesController,
                    minLines: 2,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Notes (optional)',
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () {
                    final points = int.tryParse(pointsController.text.trim());
                    if (points == null || points < 1) {
                      setDialogState(
                        () => validationError = 'Enter a valid number (>= 1)',
                      );
                      return;
                    }
                    Navigator.of(dialogContext).pop(true);
                  },
                  child: const Text('Grant'),
                ),
              ],
            );
          },
        );
      },
    );

    if (submitted != true || !mounted) return;

    final points = int.parse(pointsController.text.trim());
    final notes = notesController.text.trim();

    setState(() => _grantingReward = true);
    try {
      await _api.grantShopOrderReward(
        widget.orderId,
        points: points,
        notes: notes.isEmpty ? null : notes,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Granted $points reward points')));
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Grant reward failed: $e')));
    } finally {
      if (mounted) setState(() => _grantingReward = false);
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
                        Text(
                          'Order summary',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 12),
                        _infoLine(
                          context,
                          'Order date',
                          _formatDateTime(order.createdAt),
                        ),
                        _infoLine(
                          context,
                          'Last updated',
                          _formatDateTime(order.updatedAt),
                        ),
                        _infoLine(context, 'Order status', order.status),
                        _infoLine(
                          context,
                          'Payment status',
                          order.paymentStatus,
                        ),
                        if (order.shop != null)
                          _infoLine(context, 'Shop', order.shop!.name),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 16),
                Text(
                  'Customer',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: order.customer != null
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _infoLine(context, 'Name', order.customer!.name),
                              _infoLine(
                                context,
                                'Email',
                                order.customer!.email,
                              ),
                              if (order.customer!.phone != null &&
                                  order.customer!.phone!.trim().isNotEmpty)
                                _infoLine(
                                  context,
                                  'Phone',
                                  order.customer!.phone!.trim(),
                                ),
                            ],
                          )
                        : Text(
                            'No customer account linked.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                          ),
                  ),
                ),

                const SizedBox(height: 16),
                Text(
                  'Electrician',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: order.electrician != null
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _infoLine(
                                context,
                                'Name',
                                order.electrician!.name,
                              ),
                              _infoLine(
                                context,
                                'Email',
                                order.electrician!.email,
                              ),
                              if (order.electrician!.phone != null &&
                                  order.electrician!.phone!.trim().isNotEmpty)
                                _infoLine(
                                  context,
                                  'Phone',
                                  order.electrician!.phone!.trim(),
                                ),
                              const SizedBox(height: 12),
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton.icon(
                                  onPressed: _grantingReward
                                      ? null
                                      : _grantReward,
                                  icon: _grantingReward
                                      ? const SizedBox(
                                          height: 16,
                                          width: 16,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(Icons.stars_rounded),
                                  label: Text(
                                    _grantingReward
                                        ? 'Granting...'
                                        : 'Grant Reward Points',
                                  ),
                                ),
                              ),
                            ],
                          )
                        : Text(
                            'No electrician linked to this order.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                          ),
                  ),
                ),

                const SizedBox(height: 16),
                if (order.address != null) ...[
                  Text(
                    'Shipping Address',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(order.address!.fullAddress),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                Text('Items', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ...order.items.map((item) {
                  return ListTile(
                    title: Text(item.name),
                    subtitle: Text('Qty: ${item.quantity}'),
                    trailing: Text(
                      currency.format(item.total),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  );
                }),

                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Subtotal'),
                            Text(currency.format(order.subtotal)),
                          ],
                        ),
                        if (order.discountTotal > 0) ...[
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Discount'),
                              Text(
                                '- ${currency.format(order.discountTotal)}',
                                style: const TextStyle(color: Colors.green),
                              ),
                            ],
                          ),
                        ],
                        if (order.shippingTotal > 0) ...[
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Shipping'),
                              Text(currency.format(order.shippingTotal)),
                            ],
                          ),
                        ],
                        if (order.taxTotal > 0) ...[
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Tax'),
                              Text(currency.format(order.taxTotal)),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ),

                const Divider(height: 32),

                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Update Order',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 12),
                        DropdownMenu<String>(
                          label: const Text('Order status'),
                          initialSelection: _status,
                          onSelected: (v) => setState(() => _status = v),
                          dropdownMenuEntries: _statuses
                              .map(
                                (s) => DropdownMenuEntry<String>(
                                  value: s,
                                  label: s,
                                ),
                              )
                              .toList(),
                        ),
                        const SizedBox(height: 12),
                        DropdownMenu<String>(
                          label: const Text('Payment status'),
                          initialSelection: _paymentStatus,
                          onSelected: (v) => setState(() => _paymentStatus = v),
                          dropdownMenuEntries: _paymentStatuses
                              .map(
                                (s) => DropdownMenuEntry<String>(
                                  value: s,
                                  label: s,
                                ),
                              )
                              .toList(),
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: _saving ? null : _update,
                            child: _saving
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
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
                    const Text(
                      'Total',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                    Text(
                      currency.format(order.grandTotal),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ],
            ),
    );
  }
}
