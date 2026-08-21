import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../models/address.dart';
import '../models/cart.dart';
import '../models/shop_with_partners.dart';
import '../services/api_service.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final ApiService _api = ApiService();
  Cart? _cart;
  List<Address> _addresses = [];
  List<ShopWithPartners> _shopsWithPartnerSupport = [];
  Map<int, int?> _selectedPartners = {};
  double _grandTotal = 0;
  bool _loading = true;
  bool _placing = false;
  String? _error;
  int? _selectedAddressId;

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
      final data = await _api.getCheckout();
      final cartJson = data['cart'] as Map<String, dynamic>?;
      if (cartJson == null || (cartJson['items'] as List?)?.isEmpty == true) {
        setState(() {
          _loading = false;
          _cart = null;
        });
        return;
      }
      _cart = Cart.fromApiResponse(data);
      _addresses = (data['addresses'] as List<dynamic>?)
              ?.map((e) => Address.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [];
      _shopsWithPartnerSupport = ShopWithPartners.fromJsonList(
          (data['shops_with_partner_support'] ?? data['shops_with_electrician_support']) as List<dynamic>?);
      _grandTotal = (data['grand_total'] as num?)?.toDouble() ?? 0;
      final defaultAddr = _addresses.where((a) => a.isDefault).toList();
      _selectedAddressId = (defaultAddr.isNotEmpty ? defaultAddr.first : _addresses.isNotEmpty ? _addresses.first : null)?.id;
      setState(() => _loading = false);
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _placeOrder() async {
    if (_selectedAddressId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select an address')));
      return;
    }
    setState(() => _placing = true);
    try {
      final partnerMap = _selectedPartners.isNotEmpty
          ? _selectedPartners
          : null;
      await _api.placeOrder(_selectedAddressId!,
          electrician: partnerMap);
      if (mounted) {
        context.go('/orders');
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order placed successfully')));
      }
    } catch (e) {
      setState(() => _placing = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _addNewAddress() async {
    final result = await Navigator.of(context).push<Address>(MaterialPageRoute(
      builder: (context) => _AddAddressScreen(),
    ));
    if (result != null) {
      _addresses = await _api.getAddresses();
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(appBar: AppBar(title: const Text('Checkout')), body: const Center(child: CircularProgressIndicator()));
    }
    if (_cart == null || _cart!.items.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('Your cart is empty'),
              FilledButton(onPressed: () => context.go('/cart'), child: const Text('View Cart')),
            ],
          ),
        ),
      );
    }
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Text(_error!), FilledButton(onPressed: _load, child: const Text('Retry'))])),
      );
    }
    final currency = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('Shipping address', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (_addresses.isEmpty)
                  OutlinedButton.icon(
                    icon: const Icon(Icons.add),
                    label: const Text('Add address'),
                    onPressed: _addNewAddress,
                  )
                else
                  RadioGroup<int>(
                    groupValue: _selectedAddressId,
                    onChanged: (v) => setState(() => _selectedAddressId = v),
                    child: Column(
                      children: _addresses
                          .map((a) => RadioListTile<int>(
                                title: Text(a.name),
                                subtitle: Text(a.fullAddress),
                                value: a.id,
                              ))
                          .toList(),
                    ),
                  ),
                if (_addresses.isNotEmpty)
                  TextButton.icon(icon: const Icon(Icons.add), label: const Text('Add new address'), onPressed: _addNewAddress),
                if (_shopsWithPartnerSupport.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text('Partner (optional)', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ..._shopsWithPartnerSupport.map((shop) => _PartnerSelector(
                    shop: shop,
                    selectedId: _selectedPartners[shop.id],
                    onChanged: (id) => setState(() {
                      _selectedPartners = {..._selectedPartners, shop.id: id};
                    }),
                  )),
                ],
                const SizedBox(height: 24),
                Text('Order summary', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ..._cart!.items.map((item) => ListTile(
                      leading: const Icon(Icons.shopping_bag_outlined),
                      title: Text(item.product?.name ?? 'Item'),
                      trailing: Text(currency.format(item.lineTotal)),
                      subtitle: Text('Qty: ${item.quantity}'),
                    )),
                const Divider(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total'),
                    Text(currency.format(_grandTotal), style: const TextStyle(fontWeight: FontWeight.bold)),
                  ],
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _placing || _addresses.isEmpty ? null : _placeOrder,
                  child: _placing ? const SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Place Order'),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PartnerSelector extends StatelessWidget {
  final ShopWithPartners shop;
  final int? selectedId;
  final ValueChanged<int?> onChanged;

  const _PartnerSelector({
    required this.shop,
    required this.selectedId,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int?>(
        initialValue: selectedId,
        decoration: InputDecoration(
          labelText: '${shop.name} — partner (optional)',
          border: const OutlineInputBorder(),
        ),
        items: [
          const DropdownMenuItem<int?>(value: null, child: Text('None')),
          ...shop.partners.map((e) => DropdownMenuItem<int?>(
                value: e.id,
                child: Text(e.displayName),
              )),
        ],
        onChanged: onChanged,
      ),
    );
  }
}

class _AddAddressScreen extends StatefulWidget {
  @override
  State<_AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<_AddAddressScreen> {
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
      await api.createAddress({
        'name': _name.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'address_line1': _line1.text.trim(),
        'address_line2': _line2.text.trim().isEmpty ? null : _line2.text.trim(),
        'city': _city.text.trim(),
        'state': _state.text.trim(),
        'country': _country.text.trim(),
        'postal_code': _postal.text.trim(),
        'is_default': _isDefault,
      });
      if (mounted) Navigator.of(context).pop(Address(id: 0, name: _name.text, addressLine1: _line1.text, city: _city.text, state: _state.text, country: _country.text, postalCode: _postal.text));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('New address')),
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
