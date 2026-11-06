import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../services/api_service.dart';

class ShopOwnerPersonFormScreen extends StatefulWidget {
  final String kind;
  final int? userId;

  const ShopOwnerPersonFormScreen({
    super.key,
    required this.kind,
    required this.userId,
  });

  @override
  State<ShopOwnerPersonFormScreen> createState() =>
      _ShopOwnerPersonFormScreenState();
}

class _ShopOwnerPersonFormScreenState extends State<ShopOwnerPersonFormScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String? _error;
  bool _isActive = true;
  String _kind = 'customer';

  List<Map<String, dynamic>> _partnerTypes = [];
  List<Map<String, dynamic>> _shops = [];
  int? _shopId;
  int? _userTypeId;
  final Set<int> _shopIds = {};
  final Set<int> _userTypeIds = {};

  bool get _isCreate => widget.userId == null;
  bool get _isPartner => _kind == 'partner';

  @override
  void initState() {
    super.initState();
    _kind = widget.kind == 'partner' ? 'partner' : 'customer';
    _init();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final meta = await _api.getShopPeopleMeta();
      _partnerTypes = ((meta['partner_types'] as List<dynamic>?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      _shops = ((meta['shops'] as List<dynamic>?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
      final partnerShops = _shops
          .where((s) => s['supports_partners'] == true)
          .toList();
      if (_isCreate && partnerShops.isNotEmpty) {
        _shopId = partnerShops.first['id'] as int?;
      }
      if (_isCreate && _partnerTypes.isNotEmpty) {
        _userTypeId = _partnerTypes.first['id'] as int?;
      }

      if (widget.userId != null) {
        final user = await _api.getShopPerson(widget.userId!);
        _nameController.text = (user['name'] ?? '').toString();
        _phoneController.text = (user['phone'] ?? '').toString();
        _isActive = user['is_active'] != false;
        _kind = (user['kind'] ?? _kind).toString();
        _userTypeIds.addAll(_idsFrom(user['user_types']));
        _shopIds.addAll(_idsFrom(user['shops']));
      }

      if (!mounted) return;
      setState(() => _loading = false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _errorText(e);
        _loading = false;
      });
    }
  }

  List<int> _idsFrom(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => e['id'])
        .whereType<int>()
        .toList();
  }

  String _errorText(Object e) {
    if (e is DioException) {
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
    return e.toString();
  }

  Future<void> _submit() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      if (_isCreate) {
        if (_isPartner) {
          if (_shopId == null) {
            throw StateError('Select a shop for this partner.');
          }
          await _api.createShopPartner(
            name: _nameController.text,
            phone: _phoneController.text,
            shopId: _shopId!,
            userTypeId: _userTypeId,
          );
        } else {
          await _api.createShopCustomer(
            name: _nameController.text,
            phone: _phoneController.text,
            shopId: _shops.isNotEmpty ? _shops.first['id'] as int? : null,
          );
        }
      } else {
        await _api.updateShopPerson(
          id: widget.userId!,
          name: _nameController.text,
          phone: _phoneController.text,
          isActive: _isActive,
          userTypeIds: _userTypeIds.toList(),
          shopIds: _shopIds.toList(),
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isCreate ? 'Saved' : 'Updated'),
        ),
      );
      context.go('/owner/people');
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = _errorText(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = _isCreate
        ? (_isPartner ? 'Add partner' : 'Add customer')
        : (_isPartner ? 'Edit partner' : 'Edit customer');

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (!_loading)
            IconButton(
              icon: const Icon(Icons.check),
              onPressed: _saving ? null : _submit,
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_error != null) ...[
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 16),
                  ],
                  if (_isCreate)
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'customer', label: Text('Customer')),
                        ButtonSegment(value: 'partner', label: Text('Partner')),
                      ],
                      selected: {_kind},
                      onSelectionChanged: (v) =>
                          setState(() => _kind = v.first),
                    ),
                  if (_isCreate) const SizedBox(height: 16),
                  TextFormField(
                    controller: _nameController,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(labelText: 'Name'),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Mobile'),
                    validator: (v) {
                      final digits = (v ?? '').replaceAll(RegExp(r'\D'), '');
                      if (digits.length < 10) return 'Enter a 10-digit mobile';
                      return null;
                    },
                  ),
                  if (!_isCreate) ...[
                    const SizedBox(height: 8),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Active'),
                      value: _isActive,
                      onChanged: (v) => setState(() => _isActive = v),
                    ),
                  ],
                  if (_isCreate && _isPartner) ...[
                    const SizedBox(height: 12),
                    DropdownMenu<int>(
                      width: MediaQuery.sizeOf(context).width - 32,
                      label: const Text('Shop'),
                      initialSelection: _shopId,
                      onSelected: (v) => setState(() => _shopId = v),
                      dropdownMenuEntries: _shops
                          .where((s) => s['supports_partners'] == true)
                          .map(
                            (s) => DropdownMenuEntry<int>(
                              value: s['id'] as int,
                              label: (s['name'] ?? '').toString(),
                            ),
                          )
                          .toList(),
                    ),
                    const SizedBox(height: 12),
                    DropdownMenu<int>(
                      width: MediaQuery.sizeOf(context).width - 32,
                      label: const Text('Partner type'),
                      initialSelection: _userTypeId,
                      onSelected: (v) => setState(() => _userTypeId = v),
                      dropdownMenuEntries: _partnerTypes
                          .map(
                            (t) => DropdownMenuEntry<int>(
                              value: t['id'] as int,
                              label: (t['name'] ?? '').toString(),
                            ),
                          )
                          .toList(),
                    ),
                  ],
                  if (!_isCreate) ...[
                    const SizedBox(height: 16),
                    Text(
                      'Partner types',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Leave empty to keep them as a customer.',
                      style: TextStyle(color: Colors.black54),
                    ),
                    ..._partnerTypes.map((type) {
                      final id = type['id'] as int?;
                      if (id == null) return const SizedBox.shrink();
                      return CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _userTypeIds.contains(id),
                        title: Text((type['name'] ?? '').toString()),
                        onChanged: (checked) {
                          setState(() {
                            if (checked == true) {
                              _userTypeIds.add(id);
                            } else {
                              _userTypeIds.remove(id);
                            }
                          });
                        },
                      );
                    }),
                    const SizedBox(height: 8),
                    Text(
                      'Attached shops',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    ..._shops.map((shop) {
                      final id = shop['id'] as int?;
                      if (id == null) return const SizedBox.shrink();
                      return CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _shopIds.contains(id),
                        title: Text((shop['name'] ?? '').toString()),
                        onChanged: (checked) {
                          setState(() {
                            if (checked == true) {
                              _shopIds.add(id);
                            } else {
                              _shopIds.remove(id);
                            }
                          });
                        },
                      );
                    }),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _saving ? null : _submit,
                    child: Text(_saving ? 'Saving…' : 'Save'),
                  ),
                ],
              ),
            ),
    );
  }
}
