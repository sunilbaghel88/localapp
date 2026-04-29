import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../models/order.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ShopOwnerOrdersScreen extends StatefulWidget {
  const ShopOwnerOrdersScreen({super.key});

  @override
  State<ShopOwnerOrdersScreen> createState() => _ShopOwnerOrdersScreenState();
}

class _ShopOwnerOrdersScreenState extends State<ShopOwnerOrdersScreen> {
  final ApiService _api = ApiService();
  List<Order> _orders = [];
  bool _loading = true;
  String? _error;

  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    if (_loadingMore && append) return;
    if (append && !_hasMore) return;

    setState(() {
      if (!append) {
        _loading = true;
        _error = null;
        _page = 1;
        _orders = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });
    try {
      final data = await _api.getShopOrders(page: _page, perPage: 10);
      final paginator = data['orders'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['orders']) as List<dynamic>?;

      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;

      setState(() {
        final mapped = (list ?? []).map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
        _orders = append ? [..._orders, ...mapped] : mapped;
        _hasMore = currentPage < lastPage;
        if (append) {
          _page++;
        } else {
          _page = 2;
        }
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
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
    final perms = context.watch<AuthProvider>().user?.permissions;
    final canCreateOrder = perms?.contains('create_order') ?? false;
    final currency = NumberFormat.currency(locale: 'en_IN', symbol: '₹');

    late final Widget body;
    if (_loading && _orders.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _orders.isEmpty) {
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
        child: _orders.isEmpty
            ? const Center(child: Text('No orders yet'))
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _orders.length + (_hasMore ? 1 : 0),
                itemBuilder: (context, i) {
                  if (i == _orders.length) {
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      if (mounted) _load(append: true);
                    });
                    return const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }

                  final order = _orders[i];
                  final shopName = order.shop?.name.trim() ?? '';
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text('Order #${order.id}'),
                      subtitle: Text('${shopName.isEmpty ? '' : '$shopName • '}${order.status} • ${order.paymentStatus}'),
                      trailing: Text(
                        currency.format(order.grandTotal),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onTap: () => context.push('/owner/orders/${order.id}'),
                    ),
                  );
                },
              ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Manage Orders'),
        actions: [
          if (canCreateOrder)
            IconButton(
              icon: const Icon(Icons.add),
              onPressed: () => context.push('/owner/orders/create'),
            ),
        ],
      ),
      body: body,
    );
  }
}

