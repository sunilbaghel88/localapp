import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/offline_bill.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ShopOwnerOfflineBillsScreen extends StatefulWidget {
  const ShopOwnerOfflineBillsScreen({super.key});

  @override
  State<ShopOwnerOfflineBillsScreen> createState() =>
      _ShopOwnerOfflineBillsScreenState();
}

class _ShopOwnerOfflineBillsScreenState extends State<ShopOwnerOfflineBillsScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  List<OfflineBill> _bills = [];
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
        _bills = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getOfflineBills(
        page: _page,
        perPage: 15,
        q: _searchController.text,
      );
      final paginator = data['bills'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['bills']) as List<dynamic>?;
      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;
      final mapped = (list ?? [])
          .whereType<Map>()
          .map((e) => OfflineBill.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      if (!mounted) return;
      setState(() {
        _bills = append ? [..._bills, ...mapped] : mapped;
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
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String _) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('dd MMM yyyy, hh:mm a');
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    late final Widget body;
    if (_loading && _bills.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _bills.isEmpty) {
      body = Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: () => _load(),
        child: _bills.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 120),
                  Center(
                    child: Text(
                      _searchController.text.trim().isEmpty
                          ? 'No offline bills yet'
                          : 'No customers match your search',
                    ),
                  ),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _bills.length + (_hasMore ? 1 : 0),
                itemBuilder: (context, i) {
                  if (i == _bills.length) {
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      if (mounted) _load(append: true);
                    });
                    return const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }

                  final bill = _bills[i];
                  final remarks = (bill.remarks ?? '').trim();
                  final name = bill.customerName ?? 'Customer #${bill.customerId}';
                  final title = bill.closingBalance == null
                      ? name
                      : '$name (${money.format(bill.closingBalance)})';
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: InkWell(
                      onTap: () => context.push('/owner/offline-bills/${bill.id}'),
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              title,
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                            ),
                            const SizedBox(height: 10),
                            _line('Type', bill.typeLabel),
                            _line('Amount', money.format(bill.amount)),
                            _line('Remarks', remarks.isEmpty ? '—' : remarks),
                            _line(
                              'Date',
                              bill.createdAt == null
                                  ? '—'
                                  : dateFormat.format(bill.createdAt!.toLocal()),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
      );
    }

    final canCreate = context.watch<AuthProvider>().user?.permissions?.contains('create_order') ?? false;

    return Scaffold(
      appBar: AppBar(title: const Text('Offline bills ledger')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              onSubmitted: (_) {
                _searchDebounce?.cancel();
                _load();
              },
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search by customer name or phone',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                isDense: true,
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {});
                          _searchDebounce?.cancel();
                          _load();
                        },
                      ),
              ),
            ),
          ),
          Expanded(child: body),
        ],
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () async {
                final created = await context.push<bool>('/owner/offline-bills/create');
                if (created == true && mounted) _load();
              },
              icon: const Icon(Icons.add),
              label: const Text('Add bill'),
            )
          : null,
    );
  }

  Widget _line(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: TextStyle(color: Theme.of(context).colorScheme.outline),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
