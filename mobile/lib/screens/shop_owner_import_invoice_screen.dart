import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../models/category.dart';
import '../models/shop.dart';
import '../services/api_service.dart';

class ShopOwnerImportInvoiceScreen extends StatefulWidget {
  const ShopOwnerImportInvoiceScreen({super.key});

  @override
  State<ShopOwnerImportInvoiceScreen> createState() =>
      _ShopOwnerImportInvoiceScreenState();
}

class _ShopOwnerImportInvoiceScreenState
    extends State<ShopOwnerImportInvoiceScreen> {
  final ApiService _api = ApiService();

  List<Shop> _shops = [];
  List<Category> _categories = [];
  int? _shopId;
  int? _parentCategoryId;
  int? _categoryId;
  String _status = 'published';

  String? _fileName;
  Uint8List? _fileBytes;

  bool _loadingMeta = true;
  bool _extracting = false;
  bool _saving = false;
  String? _error;

  final List<_InvoiceProduct> _lines = [];

  final TextEditingController _globalMarginController =
      TextEditingController(text: '20');
  final TextEditingController _supplierController = TextEditingController();
  final TextEditingController _gstinController = TextEditingController();
  final TextEditingController _invoiceNumberController = TextEditingController();
  final TextEditingController _invoiceDateController = TextEditingController();
  final TextEditingController _cgstTotalController = TextEditingController();
  final TextEditingController _sgstTotalController = TextEditingController();
  final TextEditingController _igstTotalController = TextEditingController();

  List<Category> get _subcategories {
    final parentId = _parentCategoryId;
    if (parentId == null) return const [];
    final parent = _categories.where((c) => c.id == parentId).firstOrNull;
    return parent?.children ?? const [];
  }

  double get _globalMargin {
    return double.tryParse(_globalMarginController.text.trim()) ?? 0;
  }

  @override
  void initState() {
    super.initState();
    _loadMeta();
  }

  @override
  void dispose() {
    _globalMarginController.dispose();
    _supplierController.dispose();
    _gstinController.dispose();
    _invoiceNumberController.dispose();
    _invoiceDateController.dispose();
    _cgstTotalController.dispose();
    _sgstTotalController.dispose();
    _igstTotalController.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _loadMeta() async {
    setState(() {
      _loadingMeta = true;
      _error = null;
    });
    try {
      final shops = await _api.getMyShops();
      final categories = await _api.getShopCategories();
      if (!mounted) return;
      setState(() {
        _shops = shops;
        _categories = categories;
        _shopId ??= shops.isNotEmpty ? shops.first.id : null;
        _parentCategoryId ??=
            categories.isNotEmpty ? categories.first.id : null;
        _categoryId ??= categories.isNotEmpty &&
                categories.first.children.isNotEmpty
            ? categories.first.children.first.id
            : null;
        _loadingMeta = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingMeta = false;
      });
    }
  }

  Future<void> _pickPdf() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.single;
    if (file.bytes == null || file.bytes!.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not read that PDF. Try another file.')),
      );
      return;
    }
    setState(() {
      _fileName = file.name;
      _fileBytes = file.bytes;
      _error = null;
    });
  }

  Future<void> _extract() async {
    final shopId = _shopId;
    final bytes = _fileBytes;
    final name = _fileName ?? 'invoice.pdf';
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    if (bytes == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Upload a purchase invoice PDF first.')),
      );
      return;
    }

    setState(() {
      _extracting = true;
      _error = null;
    });
    try {
      final data = await _api.extractPurchaseInvoice(
        shopId: shopId,
        bytes: bytes,
        filename: name.endsWith('.pdf') ? name : '$name.pdf',
      );
      if (!mounted) return;
      for (final line in _lines) {
        line.dispose();
      }
      _lines.clear();
      final items = (data['products'] ?? data['items']) as List<dynamic>? ?? [];
      final margin = _globalMargin;
      for (final raw in items) {
        Map<String, dynamic>? map;
        if (raw is Map<String, dynamic>) {
          map = raw;
        } else if (raw is Map) {
          map = Map<String, dynamic>.from(raw);
        }
        if (map != null) {
          _lines.add(_InvoiceProduct.fromJson(map, margin));
        }
      }
      _fillHeader(data);
      setState(() {
        _extracting = false;
      });
      if (_lines.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No products were found on this invoice.')),
        );
      }
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _apiError(e);
        _extracting = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _extracting = false;
      });
    }
  }

  void _applyGlobalMargin() {
    final margin = _globalMargin;
    setState(() {
      for (final line in _lines) {
        if (!line.include) continue;
        line.applyMargin(margin);
      }
    });
  }

  Future<void> _createProducts() async {
    final shopId = _shopId;
    final categoryId = _categoryId;
    final selected = _lines
        .where((l) => l.include && l.selectedVariants.isNotEmpty)
        .toList();
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    if (categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a category for the new products.')),
      );
      return;
    }
    if (selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one product to create.')),
      );
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final result = await _api.bulkCreateProductsFromInvoice(
        shopId: shopId,
        categoryId: categoryId,
        status: _status,
        items: selected.map((l) => l.toPayload()).toList(),
        invoice: {
          'supplier_name': _nullable(_supplierController.text),
          'supplier_gstin': _nullable(_gstinController.text),
          'invoice_number': _nullable(_invoiceNumberController.text),
          'invoice_date': _nullable(_invoiceDateController.text),
          'cgst_amount': double.tryParse(_cgstTotalController.text.trim()) ?? 0,
          'sgst_amount': double.tryParse(_sgstTotalController.text.trim()) ?? 0,
          'igst_amount': double.tryParse(_igstTotalController.text.trim()) ?? 0,
          'source_filename': _fileName,
        },
      );
      if (!mounted) return;
      final created = result['created_count'] as int? ?? 0;
      final skipped = result['skipped_count'] as int? ?? 0;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            skipped > 0
                ? 'Created $created products. Skipped $skipped duplicates.'
                : 'Created $created products.',
          ),
        ),
      );
      final invoice = result['purchase_invoice'];
      final invoiceId = invoice is Map ? invoice['id'] : null;
      if (invoiceId is num) {
        context.go('/owner/purchases/${invoiceId.toInt()}');
      } else {
        context.go('/owner/products');
      }
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _apiError(e);
        _saving = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _saving = false;
      });
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
    }
    if (data is Map && data['message'] != null) {
      return data['message'].toString();
    }
    return e.message ?? 'Request failed';
  }

  void _fillHeader(Map<String, dynamic> data) {
    _supplierController.text =
        (data['supplier_name'] ?? data['supplier'] ?? '').toString();
    _gstinController.text = (data['supplier_gstin'] ?? '').toString();
    _invoiceNumberController.text = (data['invoice_number'] ?? '').toString();
    _invoiceDateController.text = (data['invoice_date'] ?? '').toString();
    _cgstTotalController.text =
        _moneyText(data['cgst_amount']);
    _sgstTotalController.text =
        _moneyText(data['sgst_amount']);
    _igstTotalController.text =
        _moneyText(data['igst_amount']);
  }

  static String _moneyText(dynamic value) {
    if (value is num) return value.toStringAsFixed(2);
    return double.tryParse(value?.toString() ?? '')?.toStringAsFixed(2) ?? '0.00';
  }

  static String? _nullable(String value) {
    final text = value.trim();
    return text.isEmpty ? null : text;
  }

  Future<void> _pickInvoiceDate() async {
    final parsed = DateTime.tryParse(_invoiceDateController.text.trim());
    final picked = await showDatePicker(
      context: context,
      initialDate: parsed ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (picked == null) return;
    setState(() {
      _invoiceDateController.text =
          '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Import from invoice'),
      ),
      body: _loadingMeta
          ? const Center(child: CircularProgressIndicator())
            : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_shops.isEmpty)
                  const Text(
                    'No shop found for this account. Create a shop in admin first.',
                  ),
                Text(
                  'Upload a purchase invoice PDF. Matching sizes of the same item are grouped as one product with variants. Set selling prices, then create them together.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
                const SizedBox(height: 16),
                if (_shops.length > 1)
                  DropdownButtonFormField<int>(
                    // ignore: deprecated_member_use
                    value: _shopId,
                    decoration: const InputDecoration(
                      labelText: 'Shop',
                      border: OutlineInputBorder(),
                    ),
                    items: _shops
                        .map(
                          (s) => DropdownMenuItem(
                            value: s.id,
                            child: Text(s.name),
                          ),
                        )
                        .toList(),
                    onChanged: _extracting
                        ? null
                        : (v) => setState(() => _shopId = v),
                  )
                else if (_shops.isNotEmpty)
                  Text('Shop: ${_shops.first.name}'),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _extracting ? null : _pickPdf,
                  icon: const Icon(Icons.picture_as_pdf_outlined),
                  label: Text(_fileName ?? 'Choose PDF invoice'),
                ),
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: _extracting ? null : _extract,
                  icon: _extracting
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.auto_awesome),
                  label: Text(_extracting ? 'Reading invoice…' : 'Extract products'),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ],
                if (_lines.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text(
                    'Invoice details',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Correct anything the AI misread. These stay on the purchase record, not on the storefront product.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 12),
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
                    controller: _invoiceNumberController,
                    decoration: const InputDecoration(
                      labelText: 'Invoice no.',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _invoiceDateController,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Invoice date',
                      border: OutlineInputBorder(),
                      suffixIcon: Icon(Icons.calendar_today_outlined),
                    ),
                    onTap: _pickInvoiceDate,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _cgstTotalController,
                          keyboardType: const TextInputType.numberWithOptions(
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
                          controller: _sgstTotalController,
                          keyboardType: const TextInputType.numberWithOptions(
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
                          controller: _igstTotalController,
                          keyboardType: const TextInputType.numberWithOptions(
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
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    // ignore: deprecated_member_use
                    value: _parentCategoryId,
                    decoration: const InputDecoration(
                      labelText: 'Category',
                      border: OutlineInputBorder(),
                    ),
                    items: _categories
                        .map(
                          (c) => DropdownMenuItem(
                            value: c.id,
                            child: Text(c.name),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      setState(() {
                        _parentCategoryId = v;
                        final children = _subcategories;
                        _categoryId =
                            children.isNotEmpty ? children.first.id : v;
                      });
                    },
                  ),
                  if (_subcategories.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      // ignore: deprecated_member_use
                      value: _categoryId,
                      decoration: const InputDecoration(
                        labelText: 'Subcategory',
                        border: OutlineInputBorder(),
                      ),
                      items: _subcategories
                          .map(
                            (c) => DropdownMenuItem(
                              value: c.id,
                              child: Text(c.name),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setState(() => _categoryId = v),
                    ),
                  ],
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    // ignore: deprecated_member_use
                    value: _status,
                    decoration: const InputDecoration(
                      labelText: 'Status',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'published', child: Text('Published')),
                      DropdownMenuItem(value: 'draft', child: Text('Draft')),
                    ],
                    onChanged: (v) {
                      if (v != null) setState(() => _status = v);
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _globalMarginController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'Margin % for all products',
                      helperText:
                          'Selling price = purchase rate + this margin. You can still edit any row.',
                      border: OutlineInputBorder(),
                      suffixText: '%',
                    ),
                    onChanged: (_) => _applyGlobalMargin(),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${_lines.fold<int>(0, (sum, line) => sum + line.variants.length)} invoice rows, grouped into ${_lines.length} products',
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${_lines.where((l) => l.include && l.selectedVariants.isNotEmpty).length} of ${_lines.length} products selected',
                  ),
                  const SizedBox(height: 8),
                  ..._lines.map(_lineCard),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: _saving ? null : _createProducts,
                    child: _saving
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(
                            'Create ${_lines.where((l) => l.include && l.selectedVariants.isNotEmpty).length} products',
                          ),
                  ),
                ],
              ],
            ),
    );
  }

  Widget _lineCard(_InvoiceProduct line) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 4, 12, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Checkbox(
                  value: line.include,
                  onChanged: (v) => setState(() {
                    line.include = v ?? false;
                    for (final variant in line.variants) {
                      variant.include = line.include;
                    }
                  }),
                ),
                Expanded(
                  child: TextField(
                    controller: line.nameController,
                    decoration: const InputDecoration(
                      labelText: 'Product name',
                      border: InputBorder.none,
                    ),
                  ),
                ),
              ],
            ),
            if (line.duplicate)
              Padding(
                padding: const EdgeInsets.only(left: 12, bottom: 8),
                child: Text(
                  line.duplicateMatch == 'goods_description'
                      ? 'Already imported: ${line.duplicateProductName ?? 'existing product'}'
                      : line.duplicateMatch == 'exact'
                      ? 'Already in catalog: ${line.duplicateProductName ?? 'existing product'}'
                      : 'Looks similar to: ${line.duplicateProductName ?? 'an existing product'}',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontSize: 12,
                  ),
                ),
              ),
            if ((line.brand ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(left: 12, bottom: 4),
                child: Text(
                  'Brand: ${line.brand}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            if (line.variants.length > 1)
              Padding(
                padding: const EdgeInsets.only(left: 12, bottom: 4),
                child: Text(
                  '${line.variants.length} variants',
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ),
            ...line.variants.map((variant) => _variantRow(line, variant)),
          ],
        ),
      ),
    );
  }

  Widget _variantRow(_InvoiceProduct product, _InvoiceVariant variant) {
    return Padding(
      padding: const EdgeInsets.only(left: 8, top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Checkbox(
                value: variant.include,
                onChanged: product.include
                    ? (v) => setState(() => variant.include = v ?? false)
                    : null,
              ),
              Expanded(
                child: TextField(
                  controller: variant.nameController,
                  decoration: InputDecoration(
                    labelText: 'Variant / size',
                    helperText: (variant.goodsDescription ?? '').isEmpty
                        ? null
                        : variant.goodsDescription,
                    border: const OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.only(left: 40),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: variant.hsnController,
                        decoration: const InputDecoration(
                          labelText: 'HSN code',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: variant.qtyController,
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
                        controller: variant.listController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'List price',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                        onChanged: (_) => _recalcVariant(variant),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: variant.discountController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Discount %',
                          border: OutlineInputBorder(),
                          isDense: true,
                          suffixText: '%',
                        ),
                        onChanged: (_) => _recalcVariant(variant),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: variant.costController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Net rate',
                          helperText: 'After discount',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                        onChanged: (_) {
                          variant.costManual = true;
                          if (!variant.priceManual) {
                            variant.applyMargin(_globalMargin);
                            setState(() {});
                          }
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: variant.priceController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Selling price',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                        onChanged: (_) => variant.priceManual = true,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: variant.cgstController,
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
                        controller: variant.sgstController,
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
                        controller: variant.igstController,
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
        ],
      ),
    );
  }

  void _recalcVariant(_InvoiceVariant variant) {
    if (!variant.costManual) {
      variant.recomputeNetRate();
    }
    if (!variant.priceManual) {
      variant.applyMargin(_globalMargin);
    }
    setState(() {});
  }
}

extension _FirstOrNullExt<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}

class _InvoiceProduct {
  _InvoiceProduct({
    required this.nameController,
    required this.variants,
    this.brand,
    this.include = true,
    this.duplicate = false,
    this.duplicateMatch,
    this.duplicateProductId,
    this.duplicateProductName,
  });

  factory _InvoiceProduct.fromJson(Map<String, dynamic> json, double margin) {
    final duplicate = json['duplicate'] == true;
    final rawVariants = json['variants'];
    final variants = <_InvoiceVariant>[];
    if (rawVariants is List && rawVariants.isNotEmpty) {
      for (final raw in rawVariants) {
        if (raw is Map) {
          variants.add(
            _InvoiceVariant.fromJson(Map<String, dynamic>.from(raw), margin),
          );
        }
      }
    }
    if (variants.isEmpty) {
      variants.add(_InvoiceVariant.fromJson(json, margin));
    }
    return _InvoiceProduct(
      nameController: TextEditingController(text: (json['name'] ?? '').toString()),
      variants: variants,
      brand: _nullableText(json['brand']),
      include: json['include'] == true || !duplicate,
      duplicate: duplicate,
      duplicateMatch: json['duplicate_match'] as String?,
      duplicateProductId: json['duplicate_product_id'] as int?,
      duplicateProductName: json['duplicate_product_name'] as String?,
    );
  }

  final TextEditingController nameController;
  final List<_InvoiceVariant> variants;
  final String? brand;
  bool include;
  final bool duplicate;
  final String? duplicateMatch;
  final int? duplicateProductId;
  final String? duplicateProductName;

  List<_InvoiceVariant> get selectedVariants =>
      variants.where((v) => v.include).toList();

  void applyMargin(double margin) {
    for (final variant in variants) {
      if (!variant.priceManual) {
        variant.applyMargin(margin);
      }
    }
  }

  Map<String, dynamic> toPayload() {
    final selected = selectedVariants;
    String? hsn;
    for (final variant in selected) {
      final value = variant.hsnController.text.trim();
      if (value.isNotEmpty) {
        hsn = value;
        break;
      }
    }
    return {
      'name': nameController.text.trim(),
      'brand': brand,
      'hsn_code': hsn,
      'skip_if_duplicate': false,
      'variants': selected.map((v) => v.toPayload()).toList(),
    };
  }

  void dispose() {
    nameController.dispose();
    for (final variant in variants) {
      variant.dispose();
    }
  }

  static String? _nullableText(dynamic value) {
    if (value == null) return null;
    final text = value.toString().trim();
    if (text.isEmpty || text.toLowerCase() == 'null') return null;
    return text;
  }
}

class _InvoiceVariant {
  _InvoiceVariant({
    required this.nameController,
    required this.hsnController,
    required this.qtyController,
    required this.listController,
    required this.discountController,
    required this.costController,
    required this.priceController,
    required this.cgstController,
    required this.sgstController,
    required this.igstController,
    this.sku,
    this.unit,
    this.goodsDescription,
    this.attributes = const {},
    this.costManual = false,
  });

  factory _InvoiceVariant.fromJson(Map<String, dynamic> json, double margin) {
    final list = _toDouble(json['list_price']);
    final discount = _toDouble(json['discount_percent']);
    var cost = _toDouble(json['cost_price']);
    final computed = list > 0
        ? double.parse((list * (1 - discount / 100)).toStringAsFixed(2))
        : cost;
    if (cost <= 0) {
      cost = computed;
    }
    final qty = _toInt(json['quantity']);
    final attributesRaw = json['attributes'];
    final attributes = <String, String>{};
    if (attributesRaw is Map) {
      attributesRaw.forEach((key, value) {
        if (value != null) {
          attributes[key.toString()] = value.toString();
        }
      });
    }
    return _InvoiceVariant(
      nameController: TextEditingController(
        text: (json['name'] ?? json['spec'] ?? '').toString(),
      ),
      hsnController: TextEditingController(
        text: (json['hsn_code'] ?? '').toString(),
      ),
      qtyController: TextEditingController(text: qty.toString()),
      listController: TextEditingController(
        text: (list > 0 ? list : cost).toStringAsFixed(2),
      ),
      discountController: TextEditingController(text: discount.toStringAsFixed(2)),
      costController: TextEditingController(text: cost.toStringAsFixed(2)),
      priceController: TextEditingController(
        text: _sellingFrom(cost, margin).toStringAsFixed(2),
      ),
      cgstController: TextEditingController(
        text: _toDouble(json['cgst_amount']).toStringAsFixed(2),
      ),
      sgstController: TextEditingController(
        text: _toDouble(json['sgst_amount']).toStringAsFixed(2),
      ),
      igstController: TextEditingController(
        text: _toDouble(json['igst_amount']).toStringAsFixed(2),
      ),
      sku: json['sku']?.toString(),
      unit: json['unit']?.toString(),
      goodsDescription: _InvoiceProduct._nullableText(json['goods_description']),
      attributes: attributes,
      costManual: (cost - computed).abs() > 0.05,
    );
  }

  final TextEditingController nameController;
  final TextEditingController hsnController;
  final TextEditingController qtyController;
  final TextEditingController listController;
  final TextEditingController discountController;
  final TextEditingController costController;
  final TextEditingController priceController;
  final TextEditingController cgstController;
  final TextEditingController sgstController;
  final TextEditingController igstController;
  final String? sku;
  final String? unit;
  final String? goodsDescription;
  final Map<String, String> attributes;
  bool include = true;
  bool priceManual = false;
  bool costManual;

  void recomputeNetRate() {
    final list = double.tryParse(listController.text.trim()) ?? 0;
    final discount = double.tryParse(discountController.text.trim()) ?? 0;
    costController.text =
        (list * (1 - discount / 100)).toStringAsFixed(2);
  }

  void applyMargin(double margin) {
    final cost = double.tryParse(costController.text.trim()) ?? 0;
    priceController.text = _sellingFrom(cost, margin).toStringAsFixed(2);
  }

  Map<String, dynamic> toPayload() {
    final attrs = Map<String, String>.from(attributes);
    final unit = this.unit?.trim();
    if (unit != null && unit.isNotEmpty) {
      attrs.putIfAbsent('unit', () => unit);
    }
    final hsn = hsnController.text.trim();
    return {
      'name': nameController.text.trim().isEmpty
          ? null
          : nameController.text.trim(),
      'goods_description': (goodsDescription ?? '').trim().isEmpty
          ? null
          : goodsDescription!.trim(),
      'quantity': int.tryParse(qtyController.text.trim()) ?? 0,
      'hsn_code': hsn.isEmpty ? null : hsn,
      'list_price': double.tryParse(listController.text.trim()) ?? 0,
      'discount_percent': double.tryParse(discountController.text.trim()) ?? 0,
      'cost_price': double.tryParse(costController.text.trim()) ?? 0,
      'selling_price': double.tryParse(priceController.text.trim()) ?? 0,
      'cgst_amount': double.tryParse(cgstController.text.trim()) ?? 0,
      'sgst_amount': double.tryParse(sgstController.text.trim()) ?? 0,
      'igst_amount': double.tryParse(igstController.text.trim()) ?? 0,
      'sku': sku,
      'unit': unit,
      'attributes': attrs,
    };
  }

  void dispose() {
    nameController.dispose();
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

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 1;
  }

  static double _sellingFrom(double cost, double margin) {
    return double.parse((cost * (1 + margin / 100)).toStringAsFixed(2));
  }
}
