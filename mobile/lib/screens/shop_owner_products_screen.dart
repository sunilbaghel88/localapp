import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../models/product.dart';
import '../models/product_variant.dart';
import '../services/api_service.dart';
import '../widgets/product_name_text.dart';

class ShopOwnerProductsScreen extends StatefulWidget {
  const ShopOwnerProductsScreen({super.key});

  @override
  State<ShopOwnerProductsScreen> createState() => _ShopOwnerProductsScreenState();
}

class _ShopOwnerProductsScreenState extends State<ShopOwnerProductsScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();

  List<Product> _products = [];
  bool _loading = true;
  String? _error;

  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;
  int _requestId = 0;
  String _appliedSearch = '';

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    if (_loadingMore && append) return;
    if (append && !_hasMore) return;

    final requestId = append ? _requestId : ++_requestId;
    final search = _appliedSearch;

    setState(() {
      if (!append) {
        _loading = true;
        _loadingMore = false;
        _error = null;
        _page = 1;
        _products = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getShopProducts(
        page: _page,
        perPage: 12,
        search: search,
      );
      if (!mounted || requestId != _requestId) return;
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
      if (!mounted || requestId != _requestId) return;
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
    _searchController.dispose();
    super.dispose();
  }

  void _submitSearch() {
    FocusScope.of(context).unfocus();
    _appliedSearch = _searchController.text.trim();
    _load();
  }

  void _clearSearch() {
    _searchController.clear();
    _appliedSearch = '';
    setState(() {});
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final searching = _appliedSearch.isNotEmpty;
    late final Widget body;
    if (_loading && _products.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _products.isEmpty) {
      body = Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: () => _load(), child: const Text('Retry')),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: () => _load(),
        child: _products.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 120),
                  Center(
                    child: Text(
                      searching
                          ? 'No products match your search'
                          : 'No products yet',
                    ),
                  ),
                ],
              )
            : ListView.builder(
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
                        p.status,
                      ].where((s) => s.isNotEmpty).join(' • ');

                      return Card(
                        child: InkWell(
                          onTap: () => context.push('/owner/products/${p.id}'),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                p.imageUrl != null
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
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      ProductNameText(p.name, hindi: p.nameHi),
                                      if (subtitle.isNotEmpty) ...[
                                        const SizedBox(height: 2),
                                        Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
                                      ],
                                      const SizedBox(height: 6),
                                      if (p.variants.isEmpty)
                                        Text('No variants', style: Theme.of(context).textTheme.bodySmall)
                                      else
                                        for (final variant in p.variants) ...[
                                          _VariantPricing(variant: variant, showName: p.variants.length > 1),
                                          if (variant != p.variants.last) const SizedBox(height: 6),
                                        ],
                                    ],
                                  ),
                                ),
                                const Icon(Icons.chevron_right),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Manage Products'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/home'),
        ),
        actions: [
          IconButton(
            tooltip: 'Import invoice',
            icon: const Icon(Icons.picture_as_pdf_outlined),
            onPressed: () => context.push('/owner/products/import'),
          ),
          IconButton(
            tooltip: 'Add product',
            icon: const Icon(Icons.add),
            onPressed: () => context.push('/owner/products/create'),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) => _submitSearch(),
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search name, Hindi, brand, HSN, or SKU',
                border: const OutlineInputBorder(),
                isDense: true,
                suffixIconConstraints: BoxConstraints(
                  minHeight: 48,
                  minWidth: _searchController.text.isEmpty ? 48 : 96,
                ),
                suffixIcon: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_searchController.text.isNotEmpty)
                      IconButton(
                        tooltip: 'Clear search',
                        icon: const Icon(Icons.clear),
                        onPressed: _clearSearch,
                      ),
                    IconButton(
                      tooltip: 'Search',
                      icon: const Icon(Icons.search),
                      onPressed: _submitSearch,
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class _VariantPricing extends StatelessWidget {
  const _VariantPricing({required this.variant, required this.showName});

  final ProductVariant variant;
  final bool showName;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodySmall;
    final name = (variant.name ?? '').trim();
    final label = name.isNotEmpty ? name : (variant.sku ?? '').trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (showName && label.isNotEmpty)
          Text(
            variant.isActive ? label : '$label · Inactive',
            style: style?.copyWith(fontWeight: FontWeight.w600),
          ),
        Wrap(
          spacing: 12,
          runSpacing: 2,
          children: [
            Text('Last purchase ${_money(variant.costPrice)}', style: style),
            Text('Sale ${_money(variant.price)}', style: style),
            Text('Qty ${variant.stock}', style: style),
          ],
        ),
      ],
    );
  }

  static String _money(double? amount) {
    if (amount == null) return '—';
    return '₹${amount.toStringAsFixed(2)}';
  }
}

