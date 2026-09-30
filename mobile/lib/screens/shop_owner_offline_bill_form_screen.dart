import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../models/shop.dart';
import '../services/api_service.dart';

class ShopOwnerOfflineBillFormScreen extends StatefulWidget {
  const ShopOwnerOfflineBillFormScreen({super.key});

  @override
  State<ShopOwnerOfflineBillFormScreen> createState() =>
      _ShopOwnerOfflineBillFormScreenState();
}

class _ShopOwnerOfflineBillFormScreenState
    extends State<ShopOwnerOfflineBillFormScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();

  List<Shop> _shops = [];
  int? _shopId;
  bool _loadingShops = true;

  String _type = 'debit';
  String? _paymentMode;

  final TextEditingController _customerSearchController = TextEditingController();
  int? _customerId;
  String? _customerLabel;
  List<Map<String, dynamic>> _customerResults = [];
  Timer? _customerDebounce;

  List<Map<String, dynamic>> _partners = [];
  int? _partnerId;
  String? _partnerLabel;
  bool _loadingPartners = false;
  final TextEditingController _partnerSearchController = TextEditingController();
  List<Map<String, dynamic>> _partnerResults = [];

  final TextEditingController _pointsController = TextEditingController();
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _remarksController = TextEditingController();

  Uint8List? _imageBytes;
  String? _imageFilename;

  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadShops();
  }

  @override
  void dispose() {
    _customerDebounce?.cancel();
    _customerSearchController.dispose();
    _partnerSearchController.dispose();
    _pointsController.dispose();
    _amountController.dispose();
    _remarksController.dispose();
    super.dispose();
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
        _partnerLabel = null;
        _partnerResults = [];
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
        Map<String, dynamic>? match;
        if (want != null) {
          for (final e in rows) {
            if (e['id'] == want || (e['id'] is num && (e['id'] as num).toInt() == want)) {
              match = e;
              break;
            }
          }
        }
        if (match != null) {
          _partnerId = want;
          _partnerLabel = match['label'] as String? ?? match['name'] as String?;
          _partnerSearchController.text = _partnerLabel ?? '';
        } else {
          _partnerId = null;
          _partnerLabel = null;
          _partnerSearchController.clear();
        }
        _partnerResults = [];
        _loadingPartners = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _partners = [];
        _partnerId = null;
        _partnerLabel = null;
        _partnerResults = [];
        _loadingPartners = false;
      });
    }
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

  void _selectCustomer(Map<String, dynamic> row) {
    final id = row['id'] as int;
    setState(() {
      _customerId = id;
      _customerLabel = row['label'] as String? ?? '${row['name']}';
      _customerSearchController.text = _customerLabel ?? '';
      _customerResults = [];
    });
  }

  void _onPartnerQueryChanged(String q) {
    final trimmed = q.trim().toLowerCase();
    if (trimmed.isEmpty) {
      setState(() => _partnerResults = []);
      return;
    }
    setState(() {
      _partnerId = null;
      _partnerLabel = null;
      _partnerResults = _partners.where((e) {
        final label = (e['label'] as String? ?? '').toLowerCase();
        final name = (e['name'] as String? ?? '').toLowerCase();
        final phone = (e['phone'] as String? ?? '').toLowerCase();
        final email = (e['email'] as String? ?? '').toLowerCase();
        return label.contains(trimmed) ||
            name.contains(trimmed) ||
            phone.contains(trimmed) ||
            email.contains(trimmed);
      }).toList();
    });
  }

  void _selectPartner(Map<String, dynamic> row) {
    setState(() {
      _partnerId = row['id'] as int;
      _partnerLabel = row['label'] as String? ?? row['name'] as String?;
      _partnerSearchController.text = _partnerLabel ?? '';
      _partnerResults = [];
    });
  }

  String _apiError(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errs = data['errors'] as Map;
      for (final v in errs.values) {
        if (v is List && v.isNotEmpty) return v.first.toString();
        if (v != null) return v.toString();
      }
      if (data['message'] != null) return data['message'].toString();
    }
    if (data is Map && data['message'] != null) return data['message'].toString();
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
    final shopId = _shopId;
    final name = TextEditingController();
    final phone = TextEditingController();
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
              final mobile = phone.text.trim();
              if (n.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Name is required.')),
                );
                return;
              }
              if (mobile.length < 10) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Enter a valid 10-digit mobile number.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderCustomer(
                  name: n,
                  phone: mobile,
                  shopId: shopId,
                );
                if (!mounted) return;
                dialogClosed = true;
                if (dialogContext.mounted) Navigator.of(dialogContext).pop();
                _selectCustomer(row);
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Customer added.')),
                );
              } on DioException catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(_apiError(e))),
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
                        decoration: const InputDecoration(
                          labelText: 'Name *',
                          border: OutlineInputBorder(),
                        ),
                        textCapitalization: TextCapitalization.words,
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: phone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                          LengthLimitingTextInputFormatter(10),
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Mobile *',
                          border: OutlineInputBorder(),
                        ),
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
    _disposeTextControllersNextFrame([name, phone]);
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
    final phone = TextEditingController();
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
              final mobile = phone.text.trim();
              if (n.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Name is required.')),
                );
                return;
              }
              if (mobile.length < 10) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Enter a valid 10-digit mobile number.')),
                );
                return;
              }
              setDlg(() => saving = true);
              try {
                final row = await _api.createShopOrderPartner(
                  shopId: shopId,
                  name: n,
                  phone: mobile,
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
                    SnackBar(content: Text(_apiError(e))),
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
                      TextField(
                        controller: name,
                        enabled: !saving,
                        decoration: const InputDecoration(
                          labelText: 'Name *',
                          border: OutlineInputBorder(),
                        ),
                        textCapitalization: TextCapitalization.words,
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: phone,
                        enabled: !saving,
                        keyboardType: TextInputType.phone,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                          LengthLimitingTextInputFormatter(10),
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Mobile *',
                          border: OutlineInputBorder(),
                        ),
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
    _disposeTextControllersNextFrame([name, phone]);
  }

  Future<void> _pickImage() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final picker = ImagePicker();
    final x = await picker.pickImage(source: source, imageQuality: 85, maxWidth: 2048);
    if (x == null) return;
    final bytes = await x.readAsBytes();
    if (!mounted) return;
    var filename = x.name.isNotEmpty ? x.name : 'bill.jpg';
    final lower = filename.toLowerCase();
    if (!lower.endsWith('.jpg') &&
        !lower.endsWith('.jpeg') &&
        !lower.endsWith('.png') &&
        !lower.endsWith('.webp')) {
      filename = '$filename.jpg';
    }
    setState(() {
      _imageBytes = bytes;
      _imageFilename = filename;
    });
  }

  Future<void> _save() async {
    if (_saving) return;
    if (_shopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a shop.')),
      );
      return;
    }
    if (_customerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select or add a customer.')),
      );
      return;
    }
    final isDebit = _type == 'debit';
    if (isDebit && _imageBytes == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Upload the offline bill copy.')),
      );
      return;
    }
    if (!isDebit && (_paymentMode == null || _paymentMode!.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a payment mode.')),
      );
      return;
    }
    final amount = double.tryParse(_amountController.text.trim());
    if (amount == null || amount < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a valid amount.')),
      );
      return;
    }
    final points = isDebit ? (int.tryParse(_pointsController.text.trim()) ?? 0) : 0;
    if (isDebit && points < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter reward points.')),
      );
      return;
    }
    if (isDebit && _partnerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a partner to grant reward points.')),
      );
      return;
    }

    setState(() => _saving = true);
    try {
      await _api.createOfflineBill(
        shopId: _shopId!,
        type: _type,
        customerId: _customerId!,
        partnerId: _partnerId,
        rewardPoints: points,
        amount: amount,
        remarks: _remarksController.text.trim(),
        paymentMode: isDebit ? null : _paymentMode,
        imageBytes: isDebit ? _imageBytes : null,
        imageFilename: isDebit ? _imageFilename : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Offline bill saved.')),
      );
      context.pop(true);
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_apiError(e))));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Add offline bill')),
      body: _loadingShops
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _loadShops, child: const Text('Retry')),
                    ],
                  ),
                )
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_shops.length > 1) ...[
                        DropdownButtonFormField<int>(
                          // ignore: deprecated_member_use
                          value: _shopId,
                          decoration: const InputDecoration(
                            labelText: 'Shop *',
                            border: OutlineInputBorder(),
                          ),
                          items: _shops
                              .map((s) => DropdownMenuItem(value: s.id, child: Text(s.name)))
                              .toList(),
                          onChanged: (v) {
                            setState(() => _shopId = v);
                            _loadPartners(v);
                          },
                        ),
                        const SizedBox(height: 16),
                      ],
                      DropdownButtonFormField<String>(
                        // ignore: deprecated_member_use
                        value: _type,
                        decoration: const InputDecoration(
                          labelText: 'Type *',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'debit', child: Text('Debit')),
                          DropdownMenuItem(value: 'credit', child: Text('Credit')),
                        ],
                        onChanged: (v) {
                          if (v == null) return;
                          setState(() {
                            _type = v;
                            if (_type == 'credit') {
                              _imageBytes = null;
                              _imageFilename = null;
                              _pointsController.clear();
                            } else {
                              _paymentMode = null;
                            }
                          });
                        },
                      ),
                      if (_type == 'credit') ...[
                        const SizedBox(height: 16),
                        DropdownButtonFormField<String>(
                          // ignore: deprecated_member_use
                          value: _paymentMode,
                          decoration: const InputDecoration(
                            labelText: 'Mode *',
                            border: OutlineInputBorder(),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'cash', child: Text('Cash')),
                            DropdownMenuItem(value: 'upi', child: Text('UPI')),
                            DropdownMenuItem(value: 'online', child: Text('Online')),
                            DropdownMenuItem(value: 'cheque', child: Text('Cheque')),
                          ],
                          onChanged: (v) => setState(() => _paymentMode = v),
                        ),
                      ],
                      if (_type == 'debit') ...[
                        const SizedBox(height: 16),
                        Text('Bill copy image *', style: Theme.of(context).textTheme.titleSmall),
                        const SizedBox(height: 8),
                        InkWell(
                          onTap: _pickImage,
                          borderRadius: BorderRadius.circular(12),
                          child: Ink(
                            height: 180,
                            decoration: BoxDecoration(
                              border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: _imageBytes != null
                                ? ClipRRect(
                                    borderRadius: BorderRadius.circular(12),
                                    child: Image.memory(_imageBytes!, fit: BoxFit.cover, width: double.infinity),
                                  )
                                : const Center(
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(Icons.add_photo_alternate_outlined, size: 36),
                                        SizedBox(height: 8),
                                        Text('Upload offline bill copy'),
                                      ],
                                    ),
                                  ),
                          ),
                        ),
                        if (_imageBytes != null)
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: () => setState(() {
                                _imageBytes = null;
                                _imageFilename = null;
                              }),
                              child: const Text('Remove image'),
                            ),
                          ),
                      ],
                      const SizedBox(height: 16),
                      Row(
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
                        children: [
                          Expanded(
                            child: Text(
                              _type == 'debit' ? 'Partner *' : 'Partner',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                          TextButton.icon(
                            onPressed: _loadingPartners ? null : _showAddPartnerDialog,
                            icon: const Icon(Icons.person_add_alt_1_outlined, size: 20),
                            label: const Text('Add new'),
                          ),
                        ],
                      ),
                      if (_loadingPartners)
                        const LinearProgressIndicator()
                      else ...[
                        TextField(
                          controller: _partnerSearchController,
                          decoration: const InputDecoration(
                            hintText: 'Search partner name or phone…',
                            border: OutlineInputBorder(),
                          ),
                          onChanged: _onPartnerQueryChanged,
                        ),
                        if (_partnerId != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    'Selected: $_partnerLabel',
                                    style: TextStyle(color: Theme.of(context).colorScheme.primary),
                                  ),
                                ),
                                TextButton(
                                  onPressed: () => setState(() {
                                    _partnerId = null;
                                    _partnerLabel = null;
                                    _partnerSearchController.clear();
                                    _partnerResults = [];
                                  }),
                                  child: const Text('Clear'),
                                ),
                              ],
                            ),
                          ),
                        if (_partnerResults.isNotEmpty)
                          Card(
                            margin: const EdgeInsets.only(top: 8),
                            child: Column(
                              children: _partnerResults.map((row) {
                                return ListTile(
                                  title: Text(row['label'] as String? ?? row['name'] as String? ?? ''),
                                  onTap: () => _selectPartner(row),
                                );
                              }).toList(),
                            ),
                          ),
                      ],
                      if (_type == 'debit') ...[
                        const SizedBox(height: 16),
                        TextField(
                          controller: _pointsController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                          decoration: const InputDecoration(
                            labelText: 'Reward points *',
                            border: OutlineInputBorder(),
                            helperText: 'Granted to the selected partner',
                          ),
                        ),
                      ],
                      const SizedBox(height: 16),
                      TextField(
                        controller: _amountController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(
                          labelText: 'Amount *',
                          prefixText: '₹ ',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 16),
                      TextField(
                        controller: _remarksController,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'Remarks',
                          border: OutlineInputBorder(),
                          alignLabelWithHint: true,
                        ),
                      ),
                      const SizedBox(height: 24),
                      FilledButton(
                        onPressed: _saving ? null : _save,
                        child: _saving
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Text('Save bill'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
