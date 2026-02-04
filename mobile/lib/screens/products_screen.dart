import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../models/category.dart';
import '../models/product.dart';
import '../services/api_service.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final ApiService _api = ApiService();
  final _searchController = TextEditingController();
  List<Product> _products = [];
  List<Category> _categories = [];
  String? _selectedCategory;
  String _sort = 'latest';
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;
  String? _error;
  bool _routeQueryApplied = false;

  @override
  void initState() {
    super.initState();
    // Apply initial route query (e.g. ?category=...) after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _routeQueryApplied) return;
      _routeQueryApplied = true;
      final category = GoRouterState.of(context).uri.queryParameters['category'];
      if (category != _selectedCategory) {
        setState(() => _selectedCategory = category);
      }
      _page = 1;
      _products = [];
      _hasMore = true;
      _loadProducts();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadProducts({bool append = false}) async {
    if (_loading) return;
    if (append && !_hasMore) return;
    setState(() {
      _loading = true;
      _error = null;
      if (!append) _page = 1;
    });
    try {
      final data = await _api.getProducts(
        search: _searchController.text.isEmpty ? null : _searchController.text,
        category: _selectedCategory,
        sort: _sort,
        page: append ? _page : 1,
      );
      final paginator = data['products'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['products']) as List<dynamic>?;
      final newItems = list?.map((e) => Product.fromJson(e as Map<String, dynamic>)).toList() ?? [];
      final categories = (data['categories'] as List<dynamic>?)?.map((e) => Category.fromJson(e as Map<String, dynamic>)).toList() ?? [];
      setState(() {
        if (append) {
          _products = [..._products, ...newItems];
        } else {
          _products = newItems;
        }
        if (_categories.isEmpty) _categories = categories;
        _hasMore = (paginator?['current_page'] ?? 1) < (paginator?['last_page'] ?? 1);
        if (append) {
          _page++;
        } else {
          _page = 2;
        }
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
    return Scaffold(
      appBar: AppBar(
        title: const Text('Products'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search products',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                filled: true,
              ),
              onSubmitted: (_) => _loadProducts(),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          if (_categories.isNotEmpty)
            SizedBox(
              height: 44,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: [
                  FilterChip(
                    label: const Text('All'),
                    selected: _selectedCategory == null,
                    onSelected: (_) {
                      setState(() => _selectedCategory = null);
                      _loadProducts();
                    },
                  ),
                  ..._categories.map((c) => Padding(
                        padding: const EdgeInsets.only(left: 8),
                        child: FilterChip(
                          label: Text(c.name),
                          selected: _selectedCategory == c.slug,
                          onSelected: (_) {
                            setState(() => _selectedCategory = c.slug);
                            _loadProducts();
                          },
                        ),
                      )),
                ],
              ),
            ),
          Row(
            children: [
              const SizedBox(width: 12),
              DropdownButton<String>(
                value: _sort,
                items: const [
                  DropdownMenuItem(value: 'latest', child: Text('Latest')),
                  DropdownMenuItem(value: 'price_low', child: Text('Price: Low to High')),
                  DropdownMenuItem(value: 'price_high', child: Text('Price: High to Low')),
                  DropdownMenuItem(value: 'name', child: Text('Name')),
                ],
                onChanged: (v) {
                  if (v != null) {
                    setState(() => _sort = v);
                    _loadProducts();
                  }
                },
              ),
            ],
          ),
          Expanded(
            child: _error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        FilledButton(onPressed: () => _loadProducts(), child: const Text('Retry')),
                      ],
                    ),
                  )
                : _products.isEmpty && !_loading
                    ? const Center(child: Text('No products found'))
                    : RefreshIndicator(
                        onRefresh: () => _loadProducts(),
                        child: GridView.builder(
                          padding: const EdgeInsets.all(12),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            childAspectRatio: 0.72,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                          ),
                          itemCount: _products.length + (_hasMore ? 1 : 0),
                          itemBuilder: (context, i) {
                            if (i == _products.length) {
                              // Trigger pagination AFTER this frame to avoid setState during build.
                              if (!_loading) {
                                WidgetsBinding.instance.addPostFrameCallback((_) {
                                  if (mounted) {
                                    _loadProducts(append: true);
                                  }
                                });
                              }
                              return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()));
                            }
                            return _ProductTile(product: _products[i]);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _ProductTile extends StatelessWidget {
  final Product product;

  const _ProductTile({required this.product});

  @override
  Widget build(BuildContext context) {
    final variant = product.lowestPriceVariant;
    final imageUrl = product.imageUrl;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/products/${product.slug}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: imageUrl != null
                  ? CachedNetworkImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.cover,
                      placeholder: (_, _) => const Center(child: CircularProgressIndicator()),
                      errorWidget: (_, _, _) => const Icon(Icons.image_not_supported, size: 48),
                    )
                  : const Center(child: Icon(Icons.image_not_supported, size: 48)),
            ),
            Padding(
              padding: const EdgeInsets.all(8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                  if (variant != null)
                    Text('₹${variant.price.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
