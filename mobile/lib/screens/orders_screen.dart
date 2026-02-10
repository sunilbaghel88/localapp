import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../models/order.dart';
import '../services/api_service.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final ApiService _api = ApiService();
  List<Order> _orders = [];
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
      final data = await _api.getOrders(page: 1);
      final paginator = data['orders'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['orders']) as List<dynamic>?;
      setState(() {
        _orders =
            list?.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList() ??
                [];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _orders.isEmpty) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('My Orders'),
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null && _orders.isEmpty) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('My Orders'),
        ),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(_error!),
              FilledButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    final currency = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Orders'),
      ),
      body: _orders.isEmpty
          ? const Center(child: Text('No orders yet'))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _orders.length,
                itemBuilder: (context, i) {
                  final order = _orders[i];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text('Order #${order.id}'),
                      subtitle: Text('${order.shop?.name ?? ''} • ${order.status}'),
                      trailing: Text(
                        currency.format(order.grandTotal),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onTap: () => context.push('/orders/${order.id}'),
                    ),
                  );
                },
              ),
            ),
    );
  }
}

