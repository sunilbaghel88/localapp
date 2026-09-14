import 'dart:typed_data';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../models/shop.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ShopOwnerShopFormScreen extends StatefulWidget {
  final int? shopId;

  const ShopOwnerShopFormScreen({super.key, required this.shopId});

  @override
  State<ShopOwnerShopFormScreen> createState() =>
      _ShopOwnerShopFormScreenState();
}

class _ShopOwnerShopFormScreenState extends State<ShopOwnerShopFormScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();

  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _phoneController = TextEditingController();
  final _alternatePhoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _address1Controller = TextEditingController();
  final _address2Controller = TextEditingController();
  final _cityController = TextEditingController();
  final _countryController = TextEditingController(text: 'India');
  final _postalController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String? _error;

  List<ShopTypeOption> _shopTypes = [];
  List<String> _states = [];
  int? _shopTypeId;
  bool _shopTypeLocked = false;
  String? _state;
  String _status = 'on';
  Shop? _shop;
  final Map<String, bool> _uploadingDocs = {};
  final Map<String, _PendingDoc> _pendingDocs = {};
  List<ShopPartner> _partners = [];
  bool _loadingPartners = false;

  bool get _isCreate => widget.shopId == null;

  ShopTypeOption? get _selectedShopType {
    if (_shopTypeId == null) return _shop?.shopType;
    return _shopTypes.where((t) => t.id == _shopTypeId).firstOrNull ??
        _shop?.shopType;
  }

  bool get _supportsPartners => _selectedShopType?.supportsPartners ?? false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final types = await _api.getShopTypes();
      final states = await _api.getShopStates();
      Shop? shop;
      if (widget.shopId != null) {
        shop = await _api.getMyShop(widget.shopId!);
      }
      if (!mounted) return;

      if (shop != null &&
          shop.shopType != null &&
          !types.any((t) => t.id == shop!.shopType!.id)) {
        types.insert(0, shop.shopType!);
      }

      setState(() {
        _shopTypes = types;
        _states = states;
        _shop = shop;
        if (shop != null) {
          _applyShop(shop);
        } else if (types.isNotEmpty) {
          _shopTypeId = types.first.id;
        }
        _loading = false;
      });
      if (shop != null && (shop.supportsPartners)) {
        await _loadPartners(shop.id);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _applyShop(Shop shop) {
    _nameController.text = shop.name;
    _descriptionController.text = shop.description ?? '';
    _phoneController.text = shop.phone ?? '';
    _alternatePhoneController.text = shop.alternatePhone ?? '';
    _emailController.text = shop.email ?? '';
    _address1Controller.text = shop.addressLine1 ?? '';
    _address2Controller.text = shop.addressLine2 ?? '';
    _cityController.text = shop.city ?? '';
    _countryController.text =
        (shop.country ?? '').trim().isEmpty ? 'India' : shop.country!;
    _postalController.text = shop.postalCode ?? '';
    _shopTypeId = shop.shopTypeId;
    _shopTypeLocked = shop.shopTypeId != null;
    _state = shop.state;
    _status = shop.status == 'off' ? 'off' : 'on';
    if (_state != null &&
        _state!.isNotEmpty &&
        !_states.contains(_state)) {
      _states = [..._states, _state!];
    }
  }

  Future<void> _loadPartners(int shopId) async {
    setState(() => _loadingPartners = true);
    try {
      final partners = await _api.getShopPartners(shopId);
      if (!mounted) return;
      setState(() {
        _partners = partners;
        _loadingPartners = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadingPartners = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not load partners: $e')),
      );
    }
  }

  Future<void> _detachPartner(ShopPartner partner) async {
    final shopId = _shop?.id;
    if (shopId == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Detach partner'),
        content: Text(
          'Remove ${partner.displayName} from this shop? Their account stays active.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Detach'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await _api.detachShopPartner(shopId, partner.id);
      if (!mounted) return;
      setState(() {
        _partners = _partners.where((p) => p.id != partner.id).toList();
      });
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_apiError(e))),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _openAttachSheet() async {
    final shopId = _shop?.id;
    if (shopId == null) return;
    final messenger = ScaffoldMessenger.of(context);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        return _AttachPartnerSheet(
          shopId: shopId,
          api: _api,
          apiError: _apiError,
          onAttached: (partner) {
            setState(() {
              if (!_partners.any((p) => p.id == partner.id)) {
                _partners = [..._partners, partner];
              }
            });
            messenger.showSnackBar(
              SnackBar(content: Text('${partner.displayName} attached')),
            );
          },
        );
      },
    );
  }

  Map<String, dynamic> _payload() {
    return {
      'shop_type_id': _shopTypeId,
      'name': _nameController.text.trim(),
      'description': _descriptionController.text.trim().isEmpty
          ? null
          : _descriptionController.text.trim(),
      'phone': _phoneController.text.trim().isEmpty
          ? null
          : _phoneController.text.trim(),
      'alternate_phone': _alternatePhoneController.text.trim().isEmpty
          ? null
          : _alternatePhoneController.text.trim(),
      'email': _emailController.text.trim().isEmpty
          ? null
          : _emailController.text.trim(),
      'address_line1': _address1Controller.text.trim().isEmpty
          ? null
          : _address1Controller.text.trim(),
      'address_line2': _address2Controller.text.trim().isEmpty
          ? null
          : _address2Controller.text.trim(),
      'city': _cityController.text.trim().isEmpty
          ? null
          : _cityController.text.trim(),
      'state': _state,
      'country': _countryController.text.trim().isEmpty
          ? 'India'
          : _countryController.text.trim(),
      'postal_code': _postalController.text.trim().isEmpty
          ? null
          : _postalController.text.trim(),
      'status': _status,
    };
  }

  Future<void> _submit() async {
    if (_saving || _loading) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_shopTypeId == null) {
      setState(() => _error = 'Please select a shop type.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      Shop saved;
      if (_isCreate) {
        saved = await _api.createShop(_payload());
        for (final entry in _pendingDocs.entries) {
          saved = await _api.uploadShopDocumentBytes(
            saved.id,
            field: entry.key,
            bytes: entry.value.bytes,
            filename: entry.value.filename,
          );
        }
        _pendingDocs.clear();
      } else {
        saved = await _api.updateShop(widget.shopId!, _payload());
      }
      if (!mounted) return;
      setState(() => _shop = saved);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isCreate ? 'Shop created' : 'Shop updated'),
        ),
      );
      context.go('/owner/shops');
    } on DioException catch (e) {
      if (!mounted) return;
      final message = _apiError(e);
      setState(() => _error = message);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Save failed: $message')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Save failed: $e')),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
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

  Future<void> _pickDocument(_ShopDocField field) async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined),
                title: const Text('Take photo'),
                onTap: () => Navigator.pop(context, 'camera'),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Choose photo'),
                onTap: () => Navigator.pop(context, 'gallery'),
              ),
              if (!field.imageOnly)
                ListTile(
                  leading: const Icon(Icons.attach_file),
                  title: const Text('Choose file (image or PDF)'),
                  onTap: () => Navigator.pop(context, 'file'),
                ),
            ],
          ),
        );
      },
    );
    if (choice == null || !mounted) return;

    Uint8List? bytes;
    String? filename;

    if (choice == 'file') {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        withData: true,
      );
      final file = result?.files.single;
      bytes = file?.bytes;
      filename = file?.name;
    } else {
      final picker = ImagePicker();
      final x = await picker.pickImage(
        source: choice == 'camera' ? ImageSource.camera : ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 2048,
      );
      if (x != null) {
        bytes = await x.readAsBytes();
        filename = x.name.isNotEmpty ? x.name : '${field.field}.jpg';
      }
    }

    if (bytes == null || filename == null || filename.isEmpty || !mounted) {
      return;
    }

    if (_isCreate || _shop == null) {
      setState(() {
        _pendingDocs[field.field] = _PendingDoc(
          bytes: bytes!,
          filename: filename!,
        );
      });
      return;
    }

    setState(() => _uploadingDocs[field.field] = true);
    try {
      final updated = await _api.uploadShopDocumentBytes(
        _shop!.id,
        field: field.field,
        bytes: bytes,
        filename: filename,
      );
      if (!mounted) return;
      setState(() => _shop = updated);
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Upload failed: ${_apiError(e)}')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Upload failed: $e')),
      );
    } finally {
      if (mounted) setState(() => _uploadingDocs[field.field] = false);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _phoneController.dispose();
    _alternatePhoneController.dispose();
    _emailController.dispose();
    _address1Controller.dispose();
    _address2Controller.dispose();
    _cityController.dispose();
    _countryController.dispose();
    _postalController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final permissions =
        context.watch<AuthProvider>().user?.permissions ?? const [];
    final canSave = _isCreate
        ? permissions.contains('create_shop')
        : permissions.contains('update_shop');
    final width = MediaQuery.sizeOf(context).width - 32;

    return Scaffold(
      appBar: AppBar(
        title: Text(_isCreate ? 'Create Shop' : 'Edit Shop'),
        actions: [
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else if (canSave)
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
                  if (_shopTypes.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(bottom: 12),
                      child: Text(
                        'No shop types are available. Ask an admin to add one first.',
                      ),
                    )
                  else
                    DropdownMenu<int>(
                      width: width,
                      enabled: !_shopTypeLocked,
                      label: const Text('Shop type'),
                      helperText: _shopTypeLocked
                          ? 'Shop type cannot be changed after it is set.'
                          : 'Once a shop type is selected, it cannot be changed.',
                      initialSelection: _shopTypeId,
                      onSelected: _shopTypeLocked
                          ? null
                          : (v) => setState(() => _shopTypeId = v),
                      dropdownMenuEntries: _shopTypes
                          .map(
                            (t) => DropdownMenuEntry<int>(
                              value: t.id,
                              label: t.name,
                            ),
                          )
                          .toList(),
                    ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(labelText: 'Shop name'),
                    textCapitalization: TextCapitalization.words,
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _descriptionController,
                    decoration: const InputDecoration(
                      labelText: 'Description (optional)',
                    ),
                    maxLines: 4,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    decoration: const InputDecoration(labelText: 'Phone'),
                    keyboardType: TextInputType.phone,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _alternatePhoneController,
                    decoration: const InputDecoration(
                      labelText: 'Alternate number',
                    ),
                    keyboardType: TextInputType.phone,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _emailController,
                    decoration: const InputDecoration(labelText: 'Email'),
                    keyboardType: TextInputType.emailAddress,
                  ),
                  const Divider(height: 32),
                  Text(
                    'Address',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _address1Controller,
                    decoration: const InputDecoration(
                      labelText: 'Address line 1',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _address2Controller,
                    decoration: const InputDecoration(
                      labelText: 'Address line 2',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _cityController,
                    decoration: const InputDecoration(labelText: 'City'),
                  ),
                  const SizedBox(height: 12),
                  if (_states.isNotEmpty)
                    DropdownMenu<String>(
                      width: width,
                      enableFilter: true,
                      requestFocusOnTap: true,
                      label: const Text('State'),
                      initialSelection: _state,
                      onSelected: (v) => setState(() => _state = v),
                      dropdownMenuEntries: _states
                          .map(
                            (s) => DropdownMenuEntry<String>(
                              value: s,
                              label: s,
                            ),
                          )
                          .toList(),
                    )
                  else
                    TextFormField(
                      initialValue: _state,
                      decoration: const InputDecoration(labelText: 'State'),
                      onChanged: (v) => _state = v.trim().isEmpty ? null : v,
                    ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _countryController,
                    decoration: const InputDecoration(labelText: 'Country'),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _postalController,
                    decoration: const InputDecoration(labelText: 'Postal code'),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Shop is On'),
                    subtitle: const Text(
                      'Only products from shops set to On are visible on the dashboard.',
                    ),
                    value: _status == 'on',
                    onChanged: (v) =>
                        setState(() => _status = v ? 'on' : 'off'),
                  ),
                  const Divider(height: 32),
                  Text(
                    'Photos & documents',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Upload a front photo, owner photo, and shop documents. Images or PDFs are accepted for certificates.',
                    style: TextStyle(color: Colors.black54),
                  ),
                  const SizedBox(height: 12),
                  ..._shopDocFields.map(_documentTile),
                  if (_supportsPartners) ...[
                    const Divider(height: 32),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Partners',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                        ),
                        if (!_isCreate && canSave)
                          FilledButton.tonalIcon(
                            onPressed: _openAttachSheet,
                            icon: const Icon(Icons.person_add_outlined),
                            label: const Text('Attach'),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _isCreate
                          ? 'Save the shop first, then you can attach electricians, plumbers, or other reward-eligible partners.'
                          : 'Partners attached here can be selected on orders and earn reward points.',
                      style: const TextStyle(color: Colors.black54),
                    ),
                    if (!_isCreate) ...[
                      const SizedBox(height: 12),
                      if (_loadingPartners)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 16),
                          child: Center(
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      else if (_partners.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: Text('No partners attached yet.'),
                        )
                      else
                        ..._partners.map((p) => _partnerTile(p, canSave: canSave)),
                    ],
                  ],
                  const SizedBox(height: 24),
                  if (canSave)
                    FilledButton(
                      onPressed: _saving ? null : _submit,
                      child: Text(
                        _saving
                            ? 'Saving…'
                            : (_isCreate ? 'Create shop' : 'Save changes'),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _documentTile(_ShopDocField field) {
    final uploading = _uploadingDocs[field.field] == true;
    final pending = _pendingDocs[field.field];
    final path = _shop?.documentPath(field.field);
    final isPdf = (pending?.filename ?? path ?? '')
        .toLowerCase()
        .endsWith('.pdf');

    Widget preview;
    if (uploading) {
      preview = const Center(child: CircularProgressIndicator(strokeWidth: 2));
    } else if (pending != null && !isPdf) {
      preview = Image.memory(pending.bytes, fit: BoxFit.cover);
    } else if (pending != null || isPdf) {
      preview = Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.picture_as_pdf_outlined),
          if (pending != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: Text(
                pending.filename,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11),
              ),
            ),
        ],
      );
    } else if (path != null && path.isNotEmpty && !path.toLowerCase().endsWith('.pdf')) {
      preview = CachedNetworkImage(
        imageUrl: ApiClient.imageUrl(path),
        fit: BoxFit.cover,
        placeholder: (context, url) =>
            const Center(child: CircularProgressIndicator(strokeWidth: 2)),
        errorWidget: (context, url, error) =>
            const Icon(Icons.broken_image_outlined),
      );
    } else if (path != null && path.isNotEmpty) {
      preview = const Icon(Icons.picture_as_pdf_outlined);
    } else {
      preview = const Icon(Icons.add_a_photo_outlined);
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        contentPadding: const EdgeInsets.all(12),
        leading: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(width: 56, height: 56, child: preview),
        ),
        title: Text(field.label),
        subtitle: Text(
          pending != null
              ? 'Ready to upload on save'
              : (path != null && path.isNotEmpty ? 'Uploaded' : 'Not uploaded'),
        ),
        trailing: uploading
            ? const SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.upload_outlined),
        onTap: uploading ? null : () => _pickDocument(field),
      ),
    );
  }

  Widget _partnerTile(ShopPartner partner, {required bool canSave}) {
    final types = partner.userTypes.join(', ');
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          child: Text(
            partner.name.isNotEmpty ? partner.name[0].toUpperCase() : '?',
          ),
        ),
        title: Text(partner.name),
        subtitle: Text(
          [
            if ((partner.phone ?? '').isNotEmpty) partner.phone!,
            if ((partner.email ?? '').isNotEmpty) partner.email!,
            if (types.isNotEmpty) types,
            '${partner.rewardPoints} pts',
          ].join(' • '),
        ),
        isThreeLine: true,
        trailing: canSave
            ? IconButton(
                tooltip: 'Detach',
                icon: const Icon(Icons.link_off),
                onPressed: () => _detachPartner(partner),
              )
            : null,
      ),
    );
  }
}

class _AttachPartnerSheet extends StatefulWidget {
  final int shopId;
  final ApiService api;
  final String Function(DioException e) apiError;
  final void Function(ShopPartner partner) onAttached;

  const _AttachPartnerSheet({
    required this.shopId,
    required this.api,
    required this.apiError,
    required this.onAttached,
  });

  @override
  State<_AttachPartnerSheet> createState() => _AttachPartnerSheetState();
}

class _AttachPartnerSheetState extends State<_AttachPartnerSheet> {
  final _searchController = TextEditingController();
  List<ShopPartner> _results = [];
  bool _loading = true;
  String? _error;
  int? _attachingId;
  int _searchGen = 0;

  @override
  void initState() {
    super.initState();
    _search(immediate: true);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _search({String q = '', bool immediate = false}) async {
    final gen = ++_searchGen;
    if (!immediate) {
      await Future<void>.delayed(const Duration(milliseconds: 300));
      if (!mounted || gen != _searchGen) return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await widget.api.searchShopPartners(widget.shopId, q: q);
      if (!mounted || gen != _searchGen) return;
      setState(() {
        _results = rows;
        _loading = false;
      });
    } on DioException catch (e) {
      if (!mounted || gen != _searchGen) return;
      setState(() {
        _error = widget.apiError(e);
        _loading = false;
      });
    } catch (e) {
      if (!mounted || gen != _searchGen) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _attach(ShopPartner partner) async {
    if (_attachingId != null) return;
    setState(() => _attachingId = partner.id);
    try {
      final attached = await widget.api.attachShopPartner(
        widget.shopId,
        partner.id,
      );
      if (!mounted) return;
      widget.onAttached(attached);
      Navigator.pop(context);
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() => _attachingId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.apiError(e))),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _attachingId = null);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: SafeArea(
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.7,
          child: Column(
            children: [
              const SizedBox(height: 8),
              Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.black26,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(
                  'Attach partner',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  controller: _searchController,
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    hintText: 'Search name, phone, or email',
                  ),
                  onChanged: (v) => _search(q: v),
                  textInputAction: TextInputAction.search,
                  onSubmitted: (v) => _search(q: v, immediate: true),
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                        ? Center(child: Text(_error!))
                        : _results.isEmpty
                            ? const Center(
                                child: Text('No matching partners found.'),
                              )
                            : ListView.builder(
                                itemCount: _results.length,
                                itemBuilder: (context, i) {
                                  final partner = _results[i];
                                  final attaching = _attachingId == partner.id;
                                  return ListTile(
                                    title: Text(partner.displayName),
                                    subtitle: Text(
                                      [
                                        if ((partner.email ?? '').isNotEmpty)
                                          partner.email!,
                                        ...partner.userTypes,
                                      ].join(' • '),
                                    ),
                                    trailing: attaching
                                        ? const SizedBox(
                                            width: 24,
                                            height: 24,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : const Icon(Icons.add),
                                    onTap: attaching
                                        ? null
                                        : () => _attach(partner),
                                  );
                                },
                              ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PendingDoc {
  final Uint8List bytes;
  final String filename;

  _PendingDoc({required this.bytes, required this.filename});
}

class _ShopDocField {
  final String field;
  final String label;
  final bool imageOnly;

  const _ShopDocField(this.field, this.label, {this.imageOnly = false});
}

const _shopDocFields = [
  _ShopDocField('shop_front_photo', 'Shop front photo', imageOnly: true),
  _ShopDocField('owner_photo', "Owner's photo", imageOnly: true),
  _ShopDocField('aadhar_card', 'Aadhar Card'),
  _ShopDocField('shop_license', 'Shop License'),
  _ShopDocField('gst_certificate', 'GST Certificate'),
  _ShopDocField('electricity_bill', 'Electricity Bill'),
];
