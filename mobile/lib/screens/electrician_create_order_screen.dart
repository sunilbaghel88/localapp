import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../models/shop.dart';
import '../services/api_service.dart';

/// Create an order on behalf of a customer — same flow as the electrician web page
/// (shop, customer search, address, product lines with search, AI prompt).
class ElectricianCreateOrderScreen extends StatefulWidget {
  const ElectricianCreateOrderScreen({super.key});

  @override
  State<ElectricianCreateOrderScreen> createState() => _ElectricianCreateOrderScreenState();
}

class _OrderLineEditor {
  _OrderLineEditor() {
    qtyController.text = '1';
  }

  factory _OrderLineEditor.fromAi(Map<String, dynamic> item) {
    final line = _OrderLineEditor();
    line.productId = item['product_id'] as int?;
    final name = item['product_name'] as String? ?? '';
    final brand = item['brand'] as String?;
    line.productSearchController.text =
        brand != null && brand.isNotEmpty ? '$name ($brand)' : name;
    line.qtyController.text = '${item['quantity'] ?? 1}';
    final vars = (item['variants'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
    line.variants = vars;
    line.selectedProduct = {'id': line.productId, 'name': name, 'variants': vars};
    final vid = item['variant_id'];
    if (vid != null) {
      line.variantId = vid as int;
    } else if (vars.length == 1) {
      line.variantId = vars.first['id'] as int;
    }
    return line;
  }

  int? productId;
  int? variantId;
  final TextEditingController productSearchController = TextEditingController();
  final TextEditingController qtyController = TextEditingController();
  List<Map<String, dynamic>> variants = [];
  List<Map<String, dynamic>> productSearchResults = [];
  Map<String, dynamic>? selectedProduct;

  void dispose() {
    productSearchController.dispose();
    qtyController.dispose();
  }

  bool get isEmptyRow {
    final q = qtyController.text.trim();
    return productId == null &&
        productSearchController.text.trim().isEmpty &&
        (q.isEmpty || q == '1') &&
        variantId == null;
  }
}

class _ElectricianCreateOrderScreenState extends State<ElectricianCreateOrderScreen> {
  final ApiService _api = ApiService();

  List<Shop> _shops = [];
  int? _shopId;

  final TextEditingController _customerSearchController = TextEditingController();
  int? _customerId;
  String? _customerLabel;
  List<Map<String, dynamic>> _customerResults = [];
  Timer? _customerDebounce;

  List<Map<String, dynamic>> _addresses = [];
  int? _addressId;

  final List<_OrderLineEditor> _lines = [];
  final TextEditingController _aiPromptController = TextEditingController();

  Timer? _productDebounce;
  int? _productSearchLineIndex;

  bool _loadingShops = true;
  bool _submitting = false;
  bool _aiBusy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _lines.add(_OrderLineEditor());
    _loadShops();
  }

  Future<void> _loadShops() async {
    setState(() {
      _loadingShops = true;
      _error = null;
    });
    try {
      final shops = await _api.getElectricianShops();
      if (!mounted) return;
      final sid = shops.isNotEmpty ? shops.first.id : null;
      setState(() {
        _shops = shops;
        _shopId = sid;
        _loadingShops = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingShops = false;
      });
    }
  }

  @override
  void dispose() {
    _customerDebounce?.cancel();
    _productDebounce?.cancel();
    _customerSearchController.dispose();
    _aiPromptController.dispose();
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  void _onCustomerQueryChanged(String q) {
    _customerDebounce?.cancel();
    final trimmed = q.trim();
    if (trimmed.length < 2) {
      setState(() => _customerResults = []);
      return;
    }
    _customerDebounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final rows = await _api.searchElectricianOrderCustomers(trimmed);
        if (!mounted) return;
        setState(() => _customerResults = rows);
      } catch (_) {
        if (!mounted) return;
        setState(() => _customerResults = []);
      }
    });
  }

  Future<void> _selectCustomer(Map<String, dynamic> row) async {
    final id = row['id'] as int;
    setState(() {
      _customerId = id;
      _customerLabel = row['label'] as String? ?? '${row['name']}';
      _customerSearchController.text = _customerLabel ?? '';
      _customerResults = [];
      _addressId = null;
      _addresses = [];
    });
    try {
      final addrs = await _api.getElectricianOrderCustomerAddresses(id);
      if (!mounted) return;
      setState(() {
        _addresses = addrs;
        if (addrs.isNotEmpty) {
          _addressId = addrs.first['id'] as int;
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _addresses = []);
    }
  }

  void _onProductQueryChanged(int lineIndex, String q) {
    _productDebounce?.cancel();
    final shopId = _shopId;
    if (shopId == null) return;

    final line = _lines[lineIndex];
    line.productId = null;
    line.variantId = null;
    line.variants = [];
    line.selectedProduct = null;
    line.productSearchResults = [];

    final trimmed = q.trim();
    if (trimmed.length < 2) {
      setState(() {});
      return;
    }

    _productSearchLineIndex = lineIndex;
    _productDebounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final products = await _api.searchElectricianOrderProducts(shopId, trimmed);
        if (!mounted || _productSearchLineIndex != lineIndex) return;
        setState(() {
          _lines[lineIndex].productSearchResults = products;
        });
      } catch (_) {
        if (!mounted || _productSearchLineIndex != lineIndex) return;
        setState(() {
          _lines[lineIndex].productSearchResults = [];
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not search products.')),
          );
        }
      }
    });
  }

  void _applyProductToLine(int lineIndex, Map<String, dynamic> p) {
    final line = _lines[lineIndex];
    final id = p['id'] as int;
    final rawVariants = (p['variants'] as List<dynamic>? ?? [])
        .map((e) => e as Map<String, dynamic>)
        .toList();

    line.productId = id;
    line.selectedProduct = p;
    line.variants = rawVariants;
    line.productSearchResults = [];
    line.productSearchController.text = p['name'] as String? ?? '';

    if (rawVariants.length == 1) {
      line.variantId = rawVariants.first['id'] as int;
    } else {
      line.variantId = null;
    }
    setState(() {});
  }

  void _addLine() {
    if (_shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    setState(() => _lines.add(_OrderLineEditor()));
  }

  void _removeLine(int i) {
    if (_lines.length <= 1) {
      setState(() {
        _lines[0].productId = null;
        _lines[0].variantId = null;
        _lines[0].variants = [];
        _lines[0].selectedProduct = null;
        _lines[0].productSearchController.clear();
        _lines[0].qtyController.text = '1';
      });
      return;
    }
    setState(() {
      _lines[i].dispose();
      _lines.removeAt(i);
    });
  }

  void _removeEmptyLinesForAi() {
    final kept = <_OrderLineEditor>[];
    for (final l in _lines) {
      if (!l.isEmptyRow) {
        kept.add(l);
      } else {
        l.dispose();
      }
    }
    _lines.clear();
    _lines.addAll(kept);
  }

  Future<void> _applyAi() async {
    final shopId = _shopId;
    final prompt = _aiPromptController.text.trim();
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    if (prompt.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Type what you want to add.')),
      );
      return;
    }

    setState(() => _aiBusy = true);
    try {
      final json = await _api.electricianOrderAiSuggest(shopId, prompt);
      final items = (json['data'] as List<dynamic>? ?? []).map((e) => e as Map<String, dynamic>).toList();
      final missing = (json['missing'] as List<dynamic>? ?? []).map((e) => e.toString()).toList();

      if (!mounted) return;
      if (items.isNotEmpty) {
        _removeEmptyLinesForAi();
        final newLines = items.map(_OrderLineEditor.fromAi).toList();
        setState(() {
          _lines.addAll(newLines);
        });
      }

      var msg = items.isEmpty
          ? 'No products matched your request.'
          : 'Added ${items.length} item(s).';
      if (missing.isNotEmpty) {
        msg += ' Not found: ${missing.join(', ')}';
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } on DioException catch (e) {
      final data = e.response?.data;
      String msg = 'Could not process AI input.';
      if (data is Map) {
        final errs = data['errors'];
        if (errs is Map && errs['prompt'] is List && (errs['prompt'] as List).isNotEmpty) {
          msg = '${(errs['prompt'] as List).first}';
        } else if (data['message'] != null) {
          msg = data['message'].toString();
        }
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('AI request failed: $e')),
      );
    } finally {
      if (mounted) setState(() => _aiBusy = false);
    }
  }

  Future<void> _submit() async {
    final shopId = _shopId;
    final customerId = _customerId;
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop.')),
      );
      return;
    }
    if (customerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a customer.')),
      );
      return;
    }

    final payloadItems = <Map<String, dynamic>>[];
    for (final line in _lines) {
      final pid = line.productId;
      final vid = line.variantId;
      final qty = int.tryParse(line.qtyController.text.trim()) ?? 0;
      if (pid == null && line.isEmptyRow) continue;
      if (pid == null || vid == null || qty < 1) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Each line needs a product, variant, and quantity.')),
        );
        return;
      }
      payloadItems.add({
        'product_id': pid,
        'product_variant_id': vid,
        'quantity': qty,
      });
    }

    if (payloadItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add at least one product line.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final order = await _api.createElectricianOrderOnBehalf(
        shopId: shopId,
        customerUserId: customerId,
        addressId: _addressId,
        items: payloadItems,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Order #${order.id} created')),
      );
      context.go('/home');
    } on DioException catch (e) {
      final data = e.response?.data;
      if (!mounted) return;
      if (data is Map && data['errors'] != null) {
        final errs = data['errors'];
        if (errs is Map) {
          final first = errs.values.expand((v) => v is List ? v : [v]).cast<String?>().firstWhere(
                (x) => x != null && x.isNotEmpty,
                orElse: () => e.message,
              );
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(first ?? 'Save failed')));
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Save failed: ${e.message}')));
        }
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Save failed: $e')));
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Save failed: $e')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loadingShops) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_shops.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Create order')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              _error ?? 'You have no shops yet.',
              textAlign: TextAlign.center,
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Create order')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            DropdownButtonFormField<int>(
              // ignore: deprecated_member_use
              value: _shopId,
              decoration: const InputDecoration(labelText: 'Shop *'),
              items: _shops
                  .map((s) => DropdownMenuItem(value: s.id, child: Text(s.name)))
                  .toList(),
              onChanged: (v) {
                setState(() {
                  _shopId = v;
                  for (final l in _lines) {
                    l.productId = null;
                    l.variantId = null;
                    l.variants = [];
                    l.selectedProduct = null;
                    l.productSearchResults = [];
                    l.productSearchController.clear();
                  }
                });
              },
            ),
            const SizedBox(height: 16),
            const Text('Customer *', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            TextField(
              controller: _customerSearchController,
              decoration: const InputDecoration(
                hintText: 'Name, email, or phone…',
                border: OutlineInputBorder(),
              ),
              onChanged: _onCustomerQueryChanged,
            ),
            if (_customerId != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Selected: $_customerLabel',
                  style: TextStyle(color: Theme.of(context).colorScheme.primary),
                ),
              ),
            if (_customerResults.isNotEmpty)
              Card(
                margin: const EdgeInsets.only(top: 8),
                child: Column(
                  children: _customerResults.map((row) {
                    return ListTile(
                      title: Text(row['label'] as String? ?? ''),
                      onTap: () => _selectCustomer(row),
                    );
                  }).toList(),
                ),
              ),
            const SizedBox(height: 16),
            const Text('Shipping address', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            if (_customerId == null)
              Text(
                'Select a customer first',
                style: TextStyle(color: Theme.of(context).colorScheme.outline),
              )
            else if (_addresses.isEmpty)
              Text(
                'No address (optional)',
                style: TextStyle(color: Theme.of(context).colorScheme.outline),
              )
            else
              DropdownButtonFormField<int?>(
                // ignore: deprecated_member_use
                value: _addressId,
                decoration: const InputDecoration(border: OutlineInputBorder()),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('No address (optional)'),
                  ),
                  ..._addresses.map((a) {
                    final id = a['id'] as int;
                    final label = a['label'] as String? ?? '';
                    final line = a['line'] as String? ?? '';
                    return DropdownMenuItem<int?>(
                      value: id,
                      child: Text(label.isEmpty ? line : '$label — $line'),
                    );
                  }),
                ],
                onChanged: (v) => setState(() => _addressId = v),
              ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Products', style: Theme.of(context).textTheme.titleMedium),
                TextButton.icon(
                  onPressed: _addLine,
                  icon: const Icon(Icons.add),
                  label: const Text('Add line'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Search & add products using AI',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            const SizedBox(height: 4),
            Text(
              'Example: 2 Havells 5A MCB and 1 Finolex 1.5mm wire',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.outline,
                  ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _aiPromptController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Type what you want to add',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _aiBusy ? null : _applyAi,
              icon: _aiBusy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.auto_awesome),
              label: Text(_aiBusy ? 'Searching…' : 'Search & add'),
            ),
            const SizedBox(height: 20),
            ...List.generate(_lines.length, (i) {
              final line = _lines[i];
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Line ${i + 1}', style: Theme.of(context).textTheme.titleSmall),
                          IconButton(
                            onPressed: () => _removeLine(i),
                            icon: const Icon(Icons.delete_outline),
                          ),
                        ],
                      ),
                      TextField(
                        controller: line.productSearchController,
                        decoration: const InputDecoration(
                          labelText: 'Search product…',
                          border: OutlineInputBorder(),
                        ),
                        onChanged: (v) => _onProductQueryChanged(i, v),
                      ),
                      if (line.productSearchResults.isNotEmpty)
                        Card(
                          margin: const EdgeInsets.only(top: 8),
                          child: Column(
                            children: line.productSearchResults.map((p) {
                              return ListTile(
                                title: Text(p['name'] as String? ?? ''),
                                subtitle: p['brand'] != null ? Text(p['brand'] as String) : null,
                                onTap: () => _applyProductToLine(i, p),
                              );
                            }).toList(),
                          ),
                        ),
                      if (line.variants.length > 1) ...[
                        const SizedBox(height: 8),
                        DropdownButtonFormField<int?>(
                          // ignore: deprecated_member_use
                          value: line.variantId,
                          decoration: const InputDecoration(labelText: 'Variant *'),
                          items: [
                            const DropdownMenuItem<int?>(
                              value: null,
                              child: Text('Choose variant'),
                            ),
                            ...line.variants.map(
                              (v) => DropdownMenuItem<int?>(
                                value: v['id'] as int,
                                child: Text(
                                  '${v['label']} — ₹${v['price']} (stock ${v['stock']})',
                                ),
                              ),
                            ),
                          ],
                          onChanged: (v) => setState(() => line.variantId = v),
                        ),
                      ] else if (line.variants.length == 1)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text(
                            'Variant: ${line.variants.first['label']} — ₹${line.variants.first['price']}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: line.qtyController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Qty *',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
            const SizedBox(height: 8),
            Text(
              'Products are limited to the shop you selected.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.outline,
                  ),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Create order'),
            ),
          ],
        ),
      ),
    );
  }
}
