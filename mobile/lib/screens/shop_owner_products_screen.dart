import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../models/product.dart';
import '../services/api_service.dart';

class ShopOwnerProductsScreen extends StatefulWidget {
  const ShopOwnerProductsScreen({super.key});

  @override
  State<ShopOwnerProductsScreen> createState() => _ShopOwnerProductsScreenState();
}

class _ShopOwnerProductsScreenState extends State<ShopOwnerProductsScreen> {
  final ApiService _api = ApiService();

  List<Product> _products = [];
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
        _products = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getShopProducts(page: _page, perPage: 12);
      final paginator = data['products'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['products']) as List<dynamic>?;
      final newItems = list?.map((e) => Product.fromJson(e as Map<String, dynamic>)).toList() ?? [];

      setState(() {
        _products = append ? [..._products, ...newItems] : newItems;
        _hasMore = (paginator?['current_page'] ?? 1) < (paginator?['last_page'] ?? 1);
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
    return Scaffold(
      appBar: AppBar(
        title: const Text('Manage Products'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/home'),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => context.push('/owner/products/create'),
          ),
        ],
      ),
      body: _loading && _products.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _products.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: () => _load(), child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => _load(),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _products.length + (_hasMore ? 1 : 0),
                    itemBuilder: (context, i) {
                      if (i == _products.length) {
                        // Pagination trigger.
                        WidgetsBinding.instance.addPostFrameCallback((_) {
                          if (mounted) _load(append: true);
                        });
                        return const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24),
                          child: Center(child: CircularProgressIndicator()),
                        );
                      }

                      final p = _products[i];
                      final subtitle = [
                        if ((p.brand ?? '').trim().isNotEmpty) (p.brand ?? '').trim(),
                        if (p.category != null) p.category!.name,
                      ].where((s) => s.isNotEmpty).join(' • ');

                      return Card(
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          leading: p.imageUrl != null
                              ? ClipRRect(
                                  borderRadius: BorderRadius.circular(10),
                                  child: CachedNetworkImage(
                                    imageUrl: p.imageUrl!,
                                    width: 48,
                                    height: 48,
                                    fit: BoxFit.cover,
                                    placeholder: (context, url) => const SizedBox(width: 48, height: 48, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
                                    errorWidget: (context, url, error) => const Icon(Icons.image_not_supported),
                                  ),
                                )
                              : const Icon(Icons.image_not_supported),
                          title: Text(p.name),
                          subtitle: Text('${subtitle.isEmpty ? '' : '$subtitle • '}${p.status}'),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/owner/products/${p.id}'),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}

