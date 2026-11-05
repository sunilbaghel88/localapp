import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/purchase_invoice.dart';
import '../services/api_service.dart';
import '../widgets/product_name_text.dart';

class ShopOwnerPurchaseDetailScreen extends StatefulWidget {
  final int invoiceId;

  const ShopOwnerPurchaseDetailScreen({super.key, required this.invoiceId});

  @override
  State<ShopOwnerPurchaseDetailScreen> createState() =>
      _ShopOwnerPurchaseDetailScreenState();
}

class _ShopOwnerPurchaseDetailScreenState
    extends State<ShopOwnerPurchaseDetailScreen> {
  final ApiService _api = ApiService();
  PurchaseInvoice? _invoice;
  bool _loading = true;
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
      final data = await _api.getPurchaseInvoice(widget.invoiceId);
      final raw = data['invoice'];
      if (raw is! Map) {
        throw Exception('Invoice not found');
      }
      final invoice = PurchaseInvoice.fromJson(Map<String, dynamic>.from(raw));
      if (!mounted) return;
      setState(() {
        _invoice = invoice;
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
  Widget build(BuildContext context) {
    final invoice = _invoice;
    final dateFormat = DateFormat('dd MMM yyyy');

    return Scaffold(
      appBar: AppBar(
        title: Text(invoice?.title ?? 'Purchase'),
      ),
      body: _loading && invoice == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && invoice == null
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
              : invoice == null
                  ? const Center(child: Text('Not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Text(
                          'Invoice',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              children: [
                                _DetailRow(
                                  label: 'Supplier',
                                  value: _text(invoice.supplierName),
                                ),
                                _DetailRow(
                                  label: 'GSTIN',
                                  value: _text(invoice.supplierGstin),
                                ),
                                _DetailRow(
                                  label: 'Invoice no.',
                                  value: _text(invoice.invoiceNumber),
                                ),
                                _DetailRow(
                                  label: 'Invoice date',
                                  value: invoice.invoiceDate == null
                                      ? '—'
                                      : dateFormat.format(invoice.invoiceDate!),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Line items',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        if (invoice.items.isEmpty)
                          const Text('No line items')
                        else
                          ...invoice.items.map(_itemCard),
                      ],
                    ),
    );
  }

  Widget _itemCard(PurchaseInvoiceItem item) {
    final variant = (item.variantName ?? '').trim();
    final unit = (item.unit ?? '').trim();
    final qty = unit.isEmpty ? '${item.quantity}' : '${item.quantity} $unit';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ProductNameText(
              item.name,
              hindi: item.nameHi,
              style: Theme.of(context).textTheme.titleSmall,
            ),
            if (variant.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(variant, style: Theme.of(context).textTheme.bodySmall),
            ],
            const SizedBox(height: 8),
            _DetailRow(label: 'HSN', value: _text(item.hsnCode)),
            _DetailRow(label: 'Qty', value: qty),
            _DetailRow(label: 'List price', value: _money(item.listPrice)),
            _DetailRow(
              label: 'Discount',
              value: '${item.discountPercent.toStringAsFixed(2)}%',
            ),
            _DetailRow(label: 'Last purchase', value: _money(item.costPrice)),
            _DetailRow(label: 'Sale', value: _money(item.sellingPrice)),
            _DetailRow(label: 'CGST', value: _money(item.cgstAmount)),
            _DetailRow(label: 'SGST', value: _money(item.sgstAmount)),
            _DetailRow(label: 'IGST', value: _money(item.igstAmount)),
            if (item.productId != null)
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton(
                  onPressed: () =>
                      context.push('/owner/products/${item.productId}'),
                  child: const Text('Open product'),
                ),
              ),
          ],
        ),
      ),
    );
  }

  static String _text(String? value) {
    final text = value?.trim() ?? '';
    return text.isEmpty ? '—' : text;
  }

  static String _money(double amount) => '₹${amount.toStringAsFixed(2)}';
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label, style: style?.copyWith(fontWeight: FontWeight.w600)),
          ),
          Expanded(child: Text(value, style: style)),
        ],
      ),
    );
  }
}
