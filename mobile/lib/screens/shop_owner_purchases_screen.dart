import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/purchase_invoice.dart';
import '../services/api_service.dart';

class ShopOwnerPurchasesScreen extends StatefulWidget {
  const ShopOwnerPurchasesScreen({super.key});

  @override
  State<ShopOwnerPurchasesScreen> createState() =>
      _ShopOwnerPurchasesScreenState();
}

class _ShopOwnerPurchasesScreenState extends State<ShopOwnerPurchasesScreen> {
  final ApiService _api = ApiService();
  List<PurchaseInvoice> _invoices = [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;

  Future<void> _load({bool append = false}) async {
    if (_loadingMore && append) return;
    if (append && !_hasMore) return;

    setState(() {
      if (!append) {
        _loading = true;
        _error = null;
        _page = 1;
        _invoices = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getPurchaseInvoices(page: _page, perPage: 10);
      final paginator = data['invoices'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['invoices']) as List<dynamic>?;
      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;
      final mapped = (list ?? [])
          .whereType<Map>()
          .map((e) => PurchaseInvoice.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      if (!mounted) return;
      setState(() {
        _invoices = append ? [..._invoices, ...mapped] : mapped;
        _hasMore = currentPage < lastPage;
        _page = append ? _page + 1 : 2;
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('dd MMM yyyy');
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    late final Widget body;
    if (_loading && _invoices.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _invoices.isEmpty) {
      body = Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: () => _load(),
        child: _invoices.isEmpty
            ? const Center(child: Text('No purchase invoices yet'))
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _invoices.length + (_hasMore ? 1 : 0),
                itemBuilder: (context, i) {
                  if (i == _invoices.length) {
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      if (mounted) _load(append: true);
                    });
                    return const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }

                  final invoice = _invoices[i];
                  final tax = invoice.cgstAmount +
                      invoice.sgstAmount +
                      invoice.igstAmount;
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(invoice.title),
                      subtitle: Text(
                        [
                          if ((invoice.supplierName ?? '').isNotEmpty)
                            invoice.supplierName,
                          if (invoice.invoiceDate != null)
                            dateFormat.format(invoice.invoiceDate!),
                          '${invoice.itemsCount ?? invoice.items.length} items',
                        ].join(' • '),
                      ),
                      trailing: Text(
                        money.format(tax),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      onTap: () =>
                          context.push('/owner/purchases/${invoice.id}'),
                    ),
                  );
                },
              ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Purchases'),
        actions: [
          IconButton(
            icon: const Icon(Icons.picture_as_pdf_outlined),
            tooltip: 'Import invoice',
            onPressed: () => context.push('/owner/products/import'),
          ),
        ],
      ),
      body: body,
    );
  }
}
