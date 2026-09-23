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
  bool _saving = false;
  String? _error;

  final _supplierController = TextEditingController();
  final _gstinController = TextEditingController();
  final _numberController = TextEditingController();
  final _dateController = TextEditingController();
  final _cgstController = TextEditingController();
  final _sgstController = TextEditingController();
  final _igstController = TextEditingController();
  final List<_EditableItem> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _supplierController.dispose();
    _gstinController.dispose();
    _numberController.dispose();
    _dateController.dispose();
    _cgstController.dispose();
    _sgstController.dispose();
    _igstController.dispose();
    for (final item in _items) {
      item.dispose();
    }
    super.dispose();
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
      _bind(invoice);
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

  void _bind(PurchaseInvoice invoice) {
    _supplierController.text = invoice.supplierName ?? '';
    _gstinController.text = invoice.supplierGstin ?? '';
    _numberController.text = invoice.invoiceNumber ?? '';
    _dateController.text = invoice.invoiceDate == null
        ? ''
        : DateFormat('yyyy-MM-dd').format(invoice.invoiceDate!);
    _cgstController.text = invoice.cgstAmount.toStringAsFixed(2);
    _sgstController.text = invoice.sgstAmount.toStringAsFixed(2);
    _igstController.text = invoice.igstAmount.toStringAsFixed(2);
    for (final item in _items) {
      item.dispose();
    }
    _items
      ..clear()
      ..addAll(invoice.items.map(_EditableItem.fromItem));
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = await _api.updatePurchaseInvoice(widget.invoiceId, {
        'supplier_name': _nullable(_supplierController.text),
        'supplier_gstin': _nullable(_gstinController.text),
        'invoice_number': _nullable(_numberController.text),
        'invoice_date': _nullable(_dateController.text),
        'cgst_amount': double.tryParse(_cgstController.text.trim()) ?? 0,
        'sgst_amount': double.tryParse(_sgstController.text.trim()) ?? 0,
        'igst_amount': double.tryParse(_igstController.text.trim()) ?? 0,
        'items': _items.map((item) => item.toPayload()).toList(),
      });
      final raw = data['invoice'];
      if (raw is Map) {
        final invoice = PurchaseInvoice.fromJson(Map<String, dynamic>.from(raw));
        _bind(invoice);
        _invoice = invoice;
      }
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Purchase invoice saved.')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _saving = false;
      });
    }
  }

  static String? _nullable(String value) {
    final text = value.trim();
    return text.isEmpty ? null : text;
  }

  @override
  Widget build(BuildContext context) {
    final invoice = _invoice;
    return Scaffold(
      appBar: AppBar(
        title: Text(invoice?.title ?? 'Purchase'),
        actions: [
          if (invoice != null)
            IconButton(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.check),
            ),
        ],
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
                        if (_error != null) ...[
                          Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        Text(
                          'Invoice details stay editable so you can fix AI mistakes. Changing HSN or net rate also updates the linked product.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        const SizedBox(height: 16),
                        TextField(
                          controller: _supplierController,
                          decoration: const InputDecoration(
                            labelText: 'Supplier name',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _gstinController,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: 'Supplier GSTIN',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _numberController,
                          decoration: const InputDecoration(
                            labelText: 'Invoice no.',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _dateController,
                          decoration: const InputDecoration(
                            labelText: 'Invoice date (YYYY-MM-DD)',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _cgstController,
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                  decimal: true,
                                ),
                                decoration: const InputDecoration(
                                  labelText: 'CGST total',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _sgstController,
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                  decimal: true,
                                ),
                                decoration: const InputDecoration(
                                  labelText: 'SGST total',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _igstController,
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                  decimal: true,
                                ),
                                decoration: const InputDecoration(
                                  labelText: 'IGST total',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Line items',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        ..._items.map(_itemCard),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _saving ? null : _save,
                          child: const Text('Save purchase invoice'),
                        ),
                      ],
                    ),
    );
  }

  Widget _itemCard(_EditableItem item) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ProductNameText(
              item.name,
              style: Theme.of(context).textTheme.titleSmall,
            ),
            if (item.variantName.isNotEmpty)
              Text(
                item.variantName,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            if (item.productId != null)
              TextButton(
                onPressed: () =>
                    context.push('/owner/products/${item.productId}'),
                child: const Text('Open product'),
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: item.hsnController,
                    decoration: const InputDecoration(
                      labelText: 'HSN',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: item.qtyController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Qty',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: item.listController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'List price',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: item.discountController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'Discount %',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: item.costController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'Net rate',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: item.priceController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'Selling price',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: item.cgstController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'CGST',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: item.sgstController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'SGST',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: item.igstController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'IGST',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EditableItem {
  _EditableItem({
    required this.id,
    required this.productId,
    required this.name,
    required this.variantName,
    required this.hsnController,
    required this.qtyController,
    required this.listController,
    required this.discountController,
    required this.costController,
    required this.priceController,
    required this.cgstController,
    required this.sgstController,
    required this.igstController,
  });

  factory _EditableItem.fromItem(PurchaseInvoiceItem item) {
    final variant = (item.variantName ?? '').trim();
    return _EditableItem(
      id: item.id,
      productId: item.productId,
      name: item.name,
      variantName: variant,
      hsnController: TextEditingController(text: item.hsnCode ?? ''),
      qtyController: TextEditingController(text: item.quantity.toString()),
      listController:
          TextEditingController(text: item.listPrice.toStringAsFixed(2)),
      discountController:
          TextEditingController(text: item.discountPercent.toStringAsFixed(2)),
      costController:
          TextEditingController(text: item.costPrice.toStringAsFixed(2)),
      priceController:
          TextEditingController(text: item.sellingPrice.toStringAsFixed(2)),
      cgstController:
          TextEditingController(text: item.cgstAmount.toStringAsFixed(2)),
      sgstController:
          TextEditingController(text: item.sgstAmount.toStringAsFixed(2)),
      igstController:
          TextEditingController(text: item.igstAmount.toStringAsFixed(2)),
    );
  }

  final int id;
  final int? productId;
  final String name;
  final String variantName;
  final TextEditingController hsnController;
  final TextEditingController qtyController;
  final TextEditingController listController;
  final TextEditingController discountController;
  final TextEditingController costController;
  final TextEditingController priceController;
  final TextEditingController cgstController;
  final TextEditingController sgstController;
  final TextEditingController igstController;

  Map<String, dynamic> toPayload() {
    return {
      'id': id,
      'hsn_code': hsnController.text.trim().isEmpty
          ? null
          : hsnController.text.trim(),
      'quantity': int.tryParse(qtyController.text.trim()) ?? 0,
      'list_price': double.tryParse(listController.text.trim()) ?? 0,
      'discount_percent': double.tryParse(discountController.text.trim()) ?? 0,
      'cost_price': double.tryParse(costController.text.trim()) ?? 0,
      'selling_price': double.tryParse(priceController.text.trim()) ?? 0,
      'cgst_amount': double.tryParse(cgstController.text.trim()) ?? 0,
      'sgst_amount': double.tryParse(sgstController.text.trim()) ?? 0,
      'igst_amount': double.tryParse(igstController.text.trim()) ?? 0,
    };
  }

  void dispose() {
    hsnController.dispose();
    qtyController.dispose();
    listController.dispose();
    discountController.dispose();
    costController.dispose();
    priceController.dispose();
    cgstController.dispose();
    sgstController.dispose();
    igstController.dispose();
  }
}
