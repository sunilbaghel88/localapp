import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../models/shop.dart';
import '../services/api_service.dart';

class ShopOwnerCreateOrderScreen extends StatefulWidget {
  const ShopOwnerCreateOrderScreen({super.key});

  @override
  State<ShopOwnerCreateOrderScreen> createState() => _ShopOwnerCreateOrderScreenState();
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

class _ShopOwnerCreateOrderScreenState extends State<ShopOwnerCreateOrderScreen> {
  final ApiService _api = ApiService();

  List<Shop> _shops = [];
  int? _shopId;

  /// Same pool as Filament `electrician_user_id` (shop type’s reward user types, active, attached).
  List<Map<String, dynamic>> _partners = [];
  int? _partnerId;
  bool _loadingPartners = false;
  List<Map<String, dynamic>> _deliveryAgents = [];
  int? _deliveryAgentId;
  bool _loadingDeliveryAgents = false;
  String _deliveryMethod = 'pickup';
  final TextEditingController _deliveryChargeController = TextEditingController(text: '0');

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
      final shops = await _api.getMyShops();
      if (!mounted) return;
      final sid = shops.isNotEmpty ? shops.first.id : null;
      setState(() {
        _shops = shops;
        _shopId = sid;
        _loadingShops = false;
      });
      await _loadPartners(sid);
      await _loadDeliveryAgents(sid);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingShops = false;
      });
    }
  }

  Future<void> _loadPartners(int? shopId, {int? preferPartnerId}) async {
    if (shopId == null) {
      if (!mounted) return;
      setState(() {
        _partners = [];
        _partnerId = null;
        _loadingPartners = false;
      });
      return;
    }
    setState(() => _loadingPartners = true);
    try {
      final rows = await _api.getShopOrderPartners(shopId);
      if (!mounted) return;
      setState(() {
        _partners = rows;
        final want = preferPartnerId;
        bool sameId(dynamic a, dynamic b) {
          if (a == b) return true;
          if (a is num && b is num) return a.toInt() == b.toInt();
          return false;
        }

        _partnerId =
            want != null && rows.any((e) => sameId(e['id'], want)) ? want : null;
        _loadingPartners = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _partners = [];
        _partnerId = null;
        _loadingPartners = false;
      });
    }
  }

  Future<void> _loadDeliveryAgents(int? shopId, {int? preferDeliveryAgentId}) async {
    if (shopId == null) {
      if (!mounted) return;
      setState(() {
        _deliveryAgents = [];
        _deliveryAgentId = null;
        _loadingDeliveryAgents = false;
      });
      return;
    }
    setState(() => _loadingDeliveryAgents = true);
    try {
      final rows = await _api.getShopOrderDeliveryAgents(shopId);
      if (!mounted) return;
      setState(() {
        _deliveryAgents = rows;
        final want = preferDeliveryAgentId;
        bool sameId(dynamic a, dynamic b) {
          if (a == b) return true;
          if (a is num && b is num) return a.toInt() == b.toInt();
          return false;
        }

        _deliveryAgentId =
            want != null && rows.any((e) => sameId(e['id'], want)) ? want : null;
        _loadingDeliveryAgents = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _deliveryAgents = [];
        _deliveryAgentId = null;
        _loadingDeliveryAgents = false;
      });
    }
  }

  @override
  void dispose() {
    _customerDebounce?.cancel();
    _productDebounce?.cancel();
    _customerSearchController.dispose();
    _aiPromptController.dispose();
    _deliveryChargeController.dispose();
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
        final rows = await _api.searchShopOrderCustomers(trimmed);
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
      final addrs = await _api.getShopOrderCustomerAddresses(id);
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
        final products = await _api.searchShopOrderProducts(shopId, trimmed);
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
      final json = await _api.shopOrderAiSuggest(shopId, prompt);
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
    if (_deliveryMethod == 'home_delivery' && _addressId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Shipping address is required for home delivery.')),
      );
      return;
    }
    if (_deliveryMethod == 'home_delivery' && _deliveryAgentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a delivery agent for home delivery.')),
      );
      return;
    }
    final deliveryCharge = double.tryParse(_deliveryChargeController.text.trim()) ?? 0;
    if (_deliveryMethod == 'home_delivery' && deliveryCharge < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Delivery charge cannot be negative.')),
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
      final order = await _api.createShopOrderOnBehalf(
        shopId: shopId,
        customerUserId: customerId,
        deliveryMethod: _deliveryMethod,
        addressId: _deliveryMethod == 'home_delivery' ? _addressId : null,
        electricianUserId: _partnerId,
        deliveryAgentUserId: _deliveryMethod == 'home_delivery' ? _deliveryAgentId : null,
        deliveryCharge: _deliveryMethod == 'home_delivery' ? deliveryCharge : 0,
        items: payloadItems,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Order #${order.id} created')),
      );
      context.go('/owner/orders/${order.id}');
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

  String _shopOrderApiError(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errs = data['errors'] as Map;
      for (final v in errs.values) {
        if (v is List && v.isNotEmpty) return v.first.toString();
        if (v != null) return v.toString();
      }
      if (data['message'] != null) return data['message'].toString();
    }
    return e.message ?? 'Request failed';
  }

  void _disposeTextControllersNextFrame(Iterable<TextEditingController> ctrls) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      for (final c in ctrls) {
        c.dispose();
      }
    });
  }

  Future<void> _showAddCustomerDialog() async {
    final name = TextEditingController();
    final email = TextEditingController();
    final phone = TextEditingController();
    final password = TextEditingController();
    final password2 = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        var dialogClosed = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              final n = name.text.trim();
              final em = email.text.trim();
              final pw = password.text;
              final p2 = password2.text;
              if (n.isEmpty || em.isEmpty || pw.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Name, email, and password are required.')),
                );
                return;
              }
              if (pw != p2) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Passwords do not match.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderCustomer(
                  name: n,
                  email: em,
                  phone: phone.text.trim().isEmpty ? null : phone.text.trim(),
                  password: pw,
                  passwordConfirmation: p2,
                );
                if (!mounted) return;
                dialogClosed = true;
                if (dialogContext.mounted) Navigator.of(dialogContext).pop();
                await _selectCustomer(row);
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Customer added.')),
                );
              } on DioException catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(_shopOrderApiError(e))),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                }
              } finally {
                if (!dialogClosed && ctx.mounted) {
                  setDlg(() => saving = false);
                }
              }
            }

            return AlertDialog(
              title: const Text('Add new customer'),
              content: SingleChildScrollView(
                child: SizedBox(
                  width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: name,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Name *', border: OutlineInputBorder()),
                        textCapitalization: TextCapitalization.words,
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: email,
                        enabled: !saving,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(labelText: 'Email *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: phone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'Phone (optional)', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Password *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password2,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Confirm password *', border: OutlineInputBorder()),
                        onSubmitted: (_) => save(),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );
    _disposeTextControllersNextFrame([name, email, phone, password, password2]);
  }

  Future<void> _showAddPartnerDialog() async {
    final shopId = _shopId;
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    final name = TextEditingController();
    final email = TextEditingController();
    final phone = TextEditingController();
    final password = TextEditingController();
    final password2 = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        var dialogClosed = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              final n = name.text.trim();
              final em = email.text.trim();
              final pw = password.text;
              final p2 = password2.text;
              if (n.isEmpty || em.isEmpty || pw.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Name, email, and password are required.')),
                );
                return;
              }
              if (pw != p2) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Passwords do not match.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderPartner(
                  shopId: shopId,
                  name: n,
                  email: em,
                  phone: phone.text.trim().isEmpty ? null : phone.text.trim(),
                  password: pw,
                  passwordConfirmation: p2,
                );
                final idVal = row['id'];
                final newId = idVal is int ? idVal : (idVal as num).toInt();
                if (!mounted) return;
                dialogClosed = true;
                if (dialogContext.mounted) Navigator.of(dialogContext).pop();
                await _loadPartners(shopId, preferPartnerId: newId);
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Partner added and linked to this shop.')),
                );
              } on DioException catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(_shopOrderApiError(e))),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                }
              } finally {
                if (!dialogClosed && ctx.mounted) {
                  setDlg(() => saving = false);
                }
              }
            }

            return AlertDialog(
              title: const Text('Add new partner'),
              content: SingleChildScrollView(
                child: SizedBox(
                  width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Creates an account with a reward-eligible type for this shop and attaches them.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Theme.of(context).colorScheme.outline,
                            ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: name,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Name *', border: OutlineInputBorder()),
                        textCapitalization: TextCapitalization.words,
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: email,
                        enabled: !saving,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(labelText: 'Email *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: phone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'Phone (optional)', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Password *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password2,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Confirm password *', border: OutlineInputBorder()),
                        onSubmitted: (_) => save(),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );
    _disposeTextControllersNextFrame([name, email, phone, password, password2]);
  }

  Future<void> _showAddDeliveryAgentDialog() async {
    final shopId = _shopId;
    if (shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop first.')),
      );
      return;
    }
    final name = TextEditingController();
    final email = TextEditingController();
    final phone = TextEditingController();
    final password = TextEditingController();
    final password2 = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        var dialogClosed = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              final n = name.text.trim();
              final em = email.text.trim();
              final pw = password.text;
              final p2 = password2.text;
              if (n.isEmpty || em.isEmpty || pw.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Name, email, and password are required.')),
                );
                return;
              }
              if (pw != p2) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Passwords do not match.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderDeliveryAgent(
                  shopId: shopId,
                  name: n,
                  email: em,
                  phone: phone.text.trim().isEmpty ? null : phone.text.trim(),
                  password: pw,
                  passwordConfirmation: p2,
                );
                final idVal = row['id'];
                final newId = idVal is int ? idVal : (idVal as num).toInt();
                if (!mounted) return;
                dialogClosed = true;
                if (dialogContext.mounted) Navigator.of(dialogContext).pop();
                await _loadDeliveryAgents(shopId, preferDeliveryAgentId: newId);
                if (!mounted) return;
                setState(() => _deliveryAgentId = newId);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Delivery agent added and linked to this shop.')),
                );
              } on DioException catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(_shopOrderApiError(e))),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                }
              } finally {
                if (!dialogClosed && ctx.mounted) {
                  setDlg(() => saving = false);
                }
              }
            }

            return AlertDialog(
              title: const Text('Add new delivery agent'),
              content: SingleChildScrollView(
                child: SizedBox(
                  width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Creates a delivery agent account and attaches it to this shop.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Theme.of(context).colorScheme.outline,
                            ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: name,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Name *', border: OutlineInputBorder()),
                        textCapitalization: TextCapitalization.words,
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: email,
                        enabled: !saving,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(labelText: 'Email *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: phone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'Phone (optional)', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Password *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: password2,
                        enabled: !saving,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Confirm password *', border: OutlineInputBorder()),
                        onSubmitted: (_) => save(),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );
    _disposeTextControllersNextFrame([name, email, phone, password, password2]);
  }

  Future<void> _showAddShippingAddressDialog() async {
    final customerId = _customerId;
    if (customerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a customer first.')),
      );
      return;
    }
    final label = TextEditingController();
    final contactName = TextEditingController();
    final contactPhone = TextEditingController();
    final line1 = TextEditingController();
    final line2 = TextEditingController();
    final city = TextEditingController();
    final state = TextEditingController();
    final country = TextEditingController(text: 'India');
    final postal = TextEditingController();
    var isDefault = false;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        var dialogClosed = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              final n = contactName.text.trim();
              final a1 = line1.text.trim();
              final c = city.text.trim();
              final s = state.text.trim();
              final co = country.text.trim();
              final pc = postal.text.trim();
              if (n.isEmpty || a1.isEmpty || c.isEmpty || s.isEmpty || co.isEmpty || pc.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Fill all required address fields.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderCustomerAddress(
                  customerId: customerId,
                  label: label.text.trim().isEmpty ? null : label.text.trim(),
                  name: n,
                  phone: contactPhone.text.trim().isEmpty ? null : contactPhone.text.trim(),
                  addressLine1: a1,
                  addressLine2: line2.text.trim().isEmpty ? null : line2.text.trim(),
                  city: c,
                  state: s,
                  country: co,
                  postalCode: pc,
                  isDefault: isDefault,
                );
                if (!mounted) return;
                dialogClosed = true;
                if (dialogContext.mounted) Navigator.of(dialogContext).pop();
                final addrIdVal = row['id'];
                final newId = addrIdVal is int ? addrIdVal : (addrIdVal as num).toInt();
                setState(() {
                  _addresses = [..._addresses, row];
                  _addressId = newId;
                });
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Address added.')),
                );
              } on DioException catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(_shopOrderApiError(e))),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                }
              } finally {
                if (!dialogClosed && ctx.mounted) {
                  setDlg(() => saving = false);
                }
              }
            }

            return AlertDialog(
              title: const Text('Add shipping address'),
              content: SingleChildScrollView(
                child: SizedBox(
                  width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: label,
                        enabled: !saving,
                        decoration: const InputDecoration(
                          labelText: 'Label (optional)',
                          hintText: 'e.g. Home, Site',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: contactName,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Contact name *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: contactPhone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'Phone (optional)', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: line1,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Address line 1 *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: line2,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Address line 2', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: city,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'City *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: state,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'State *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: country,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Country *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: postal,
                        enabled: !saving,
                        decoration: const InputDecoration(labelText: 'Postal code *', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 8),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: isDefault,
                        enabled: !saving,
                        title: const Text('Set as default for this customer'),
                        onChanged: (v) => setDlg(() => isDefault = v ?? false),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );
    _disposeTextControllersNextFrame([
      label,
      contactName,
      contactPhone,
      line1,
      line2,
      city,
      state,
      country,
      postal,
    ]);
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
            const SizedBox(height: 8),
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
              'Product search uses the shop you choose under Order details. Changing shop clears lines.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.outline,
                  ),
            ),
            const SizedBox(height: 24),
            Text('Order details', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
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
                _loadPartners(v);
                _loadDeliveryAgents(v);
              },
            ),
            if (_loadingPartners)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: LinearProgressIndicator(),
              )
            else ...[
              const SizedBox(height: 12),
              Text(
                'Partner (optional)',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 4),
              Text(
                'Linked partners for this shop type (electrician, plumber, etc.).',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.outline,
                    ),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<int?>(
                // ignore: deprecated_member_use
                value: _partnerId,
                decoration: const InputDecoration(
                  labelText: 'Partner',
                  border: OutlineInputBorder(),
                ),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('None'),
                  ),
                  ..._partners.map((e) {
                    return DropdownMenuItem<int?>(
                      value: e['id'] as int,
                      child: Text(e['label'] as String? ?? e['name'] as String? ?? ''),
                    );
                  }),
                ],
                onChanged: (v) => setState(() => _partnerId = v),
              ),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: _loadingPartners ? null : _showAddPartnerDialog,
                  icon: const Icon(Icons.person_add_alt_1_outlined, size: 20),
                  label: const Text('Add new partner'),
                ),
              ),
            ],
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _deliveryMethod,
              decoration: const InputDecoration(
                labelText: 'Delivery method *',
                border: OutlineInputBorder(),
              ),
              items: const [
                DropdownMenuItem<String>(
                  value: 'pickup',
                  child: Text('Pickup at Shop'),
                ),
                DropdownMenuItem<String>(
                  value: 'home_delivery',
                  child: Text('Home Delivery'),
                ),
              ],
              onChanged: (v) {
                if (v == null) return;
                setState(() {
                  _deliveryMethod = v;
                  if (_deliveryMethod == 'pickup') {
                    _deliveryAgentId = null;
                    _deliveryChargeController.text = '0';
                  }
                });
              },
            ),
            if (_deliveryMethod == 'home_delivery') ...[
              const SizedBox(height: 12),
              if (_loadingDeliveryAgents)
                const LinearProgressIndicator()
              else ...[
                DropdownButtonFormField<int?>(
                  initialValue: _deliveryAgentId,
                  decoration: const InputDecoration(
                    labelText: 'Delivery agent *',
                    border: OutlineInputBorder(),
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Choose delivery agent'),
                    ),
                    ..._deliveryAgents.map((e) {
                      return DropdownMenuItem<int?>(
                        value: e['id'] as int,
                        child: Text(e['label'] as String? ?? e['name'] as String? ?? ''),
                      );
                    }),
                  ],
                  onChanged: (v) => setState(() => _deliveryAgentId = v),
                ),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: _loadingDeliveryAgents ? null : _showAddDeliveryAgentDialog,
                    icon: const Icon(Icons.delivery_dining_outlined, size: 20),
                    label: const Text('Add new delivery agent'),
                  ),
                ),
              ],
              const SizedBox(height: 8),
              TextField(
                controller: _deliveryChargeController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'Delivery charge *',
                  prefixText: 'Rs ',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const Expanded(
                  child: Text('Customer *', style: TextStyle(fontWeight: FontWeight.w600)),
                ),
                TextButton.icon(
                  onPressed: _showAddCustomerDialog,
                  icon: const Icon(Icons.person_add_outlined, size: 20),
                  label: const Text('Add new'),
                ),
              ],
            ),
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
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const Expanded(
                  child: Text('Shipping address', style: TextStyle(fontWeight: FontWeight.w600)),
                ),
                if (_customerId != null)
                  TextButton.icon(
                    onPressed: _showAddShippingAddressDialog,
                    icon: const Icon(Icons.add_location_alt_outlined, size: 20),
                    label: const Text('Add new'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (_deliveryMethod == 'pickup')
              Text(
                'Not required for pickup at shop',
                style: TextStyle(color: Theme.of(context).colorScheme.outline),
              )
            else if (_customerId == null)
              Text(
                'Select a customer first',
                style: TextStyle(color: Theme.of(context).colorScheme.outline),
              )
            else if (_addresses.isEmpty)
              Text(
                'No address found. Add one to continue home delivery.',
                style: TextStyle(color: Theme.of(context).colorScheme.outline),
              )
            else
              DropdownButtonFormField<int?>(
                // ignore: deprecated_member_use
                value: _addressId,
                decoration: const InputDecoration(border: OutlineInputBorder()),
                items: [
                  if (_deliveryMethod == 'home_delivery')
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Choose shipping address'),
                    )
                  else
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
