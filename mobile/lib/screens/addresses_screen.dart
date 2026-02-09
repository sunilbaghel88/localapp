import 'package:flutter/material.dart';
import '../models/address.dart';
import '../services/api_service.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  final ApiService _api = ApiService();
  List<Address> _addresses = [];
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
      final list = await _api.getAddresses();
      setState(() {
        _addresses = list;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _setDefault(Address a) async {
    try {
      await _api.setDefaultAddress(a.id);
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _delete(Address a) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete address?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await _api.deleteAddress(a.id);
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(appBar: AppBar(title: const Text('Addresses')), body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Addresses')),
        body: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Text(_error!), FilledButton(onPressed: _load, child: const Text('Retry'))])),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: const Text('Addresses'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () async {
              final result = await Navigator.push<Address>(context, MaterialPageRoute(builder: (context) => _AddEditAddressScreen(null)));
              if (result != null) await _load();
            },
          ),
        ],
      ),
      body: _addresses.isEmpty
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('No addresses saved'),
                  FilledButton(
                    onPressed: () async {
                      final result = await Navigator.push<Address>(context, MaterialPageRoute(builder: (context) => _AddEditAddressScreen(null)));
                      if (result != null) await _load();
                    },
                    child: const Text('Add address'),
                  ),
                ],
              ),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _addresses.length,
              itemBuilder: (context, i) {
                final a = _addresses[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    title: Text(a.name),
                    subtitle: Text(a.fullAddress),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (!a.isDefault)
                          TextButton(
                            onPressed: () => _setDefault(a),
                            child: const Text('Set default'),
                          ),
                        IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(a)),
                      ],
                    ),
                    leading: a.isDefault ? const Icon(Icons.star, color: Colors.amber) : null,
                  ),
                );
              },
            ),
    );
  }
}

class _AddEditAddressScreen extends StatefulWidget {
  final Address? address;

  const _AddEditAddressScreen(this.address);

  @override
  State<_AddEditAddressScreen> createState() => _AddEditAddressScreenState();
}

class _AddEditAddressScreenState extends State<_AddEditAddressScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _line1 = TextEditingController();
  final _line2 = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _country = TextEditingController();
  final _postal = TextEditingController();
  bool _isDefault = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final a = widget.address;
    if (a != null) {
      _name.text = a.name;
      _phone.text = a.phone ?? '';
      _line1.text = a.addressLine1;
      _line2.text = a.addressLine2 ?? '';
      _city.text = a.city;
      _state.text = a.state;
      _country.text = a.country;
      _postal.text = a.postalCode;
      _isDefault = a.isDefault;
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _line1.dispose();
    _line2.dispose();
    _city.dispose();
    _state.dispose();
    _country.dispose();
    _postal.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final api = ApiService();
      final data = {
        'name': _name.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'address_line1': _line1.text.trim(),
        'address_line2': _line2.text.trim().isEmpty ? null : _line2.text.trim(),
        'city': _city.text.trim(),
        'state': _state.text.trim(),
        'country': _country.text.trim(),
        'postal_code': _postal.text.trim(),
        'is_default': _isDefault,
      };
      if (widget.address != null) {
        await api.updateAddress(widget.address!.id, data);
      } else {
        await api.createAddress(data);
      }
      if (mounted) Navigator.pop(context, Address(id: 0, name: _name.text, addressLine1: _line1.text, city: _city.text, state: _state.text, country: _country.text, postalCode: _postal.text));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.address == null ? 'New address' : 'Edit address')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(controller: _name, decoration: const InputDecoration(labelText: 'Full name'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            TextFormField(controller: _phone, decoration: const InputDecoration(labelText: 'Phone'), keyboardType: TextInputType.phone),
            TextFormField(controller: _line1, decoration: const InputDecoration(labelText: 'Address line 1'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            TextFormField(controller: _line2, decoration: const InputDecoration(labelText: 'Address line 2')),
            TextFormField(controller: _city, decoration: const InputDecoration(labelText: 'City'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            TextFormField(controller: _state, decoration: const InputDecoration(labelText: 'State'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            TextFormField(controller: _country, decoration: const InputDecoration(labelText: 'Country'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            TextFormField(controller: _postal, decoration: const InputDecoration(labelText: 'Postal code'), validator: (v) => (v == null || v.isEmpty) ? 'Required' : null),
            SwitchListTile(title: const Text('Default address'), value: _isDefault, onChanged: (v) => setState(() => _isDefault = v)),
            const SizedBox(height: 24),
            FilledButton(onPressed: _saving ? null : _save, child: _saving ? const SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save')),
          ],
        ),
      ),
    );
  }
}
