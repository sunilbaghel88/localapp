import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:intl/intl.dart';
import '../models/product.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ProductDetailScreen extends StatefulWidget {
  final String slug;

  const ProductDetailScreen({super.key, required this.slug});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final ApiService _api = ApiService();
  Product? _product;
  List<Product> _related = [];
  bool _loading = true;
  String? _error;
  int _selectedVariantIndex = 0;
  int _quantity = 1;
  int _currentImageIndex = 0;

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
      final data = await _api.getProduct(widget.slug);
      setState(() {
        _product = Product.fromJson(data['product'] as Map<String, dynamic>);
        _related = (data['related_products'] as List<dynamic>?)
                ?.map((e) => Product.fromJson(e as Map<String, dynamic>))
                .toList() ??
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

  Future<void> _addToCart() async {
    if (!context.read<AuthProvider>().isAuthenticated) {
      context.push('/login');
      return;
    }
    final variant = _product?.variants.elementAtOrNull(_selectedVariantIndex);
    if (variant == null || variant.stock < 1) return;
    try {
      await _api.addToCart(variant.id, _quantity);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Added to cart')));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(appBar: AppBar(title: const Text('Product')), body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null || _product == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Product')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(_error ?? 'Product not found'),
              FilledButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    final product = _product!;
    final variant = product.variants.isEmpty ? null : product.variants[_selectedVariantIndex.clamp(0, product.variants.length - 1)];
    final currency = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final images = product.images;
    return Scaffold(
      appBar: AppBar(
        title: Text(product.name),
        actions: [
          IconButton(icon: const Icon(Icons.shopping_cart_outlined), onPressed: () => context.push('/cart')),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (images.isNotEmpty && images.length >= 2)
              Column(
                children: [
                  SizedBox(
                    height: 300,
                    child: PageView.builder(
                      itemCount: images.length,
                      onPageChanged: (index) => setState(() => _currentImageIndex = index),
                      itemBuilder: (context, index) {
                        final img = images[index];
                        return CachedNetworkImage(
                          imageUrl: img.fullUrl,
                          fit: BoxFit.cover,
                          placeholder: (_, _) => const Center(child: CircularProgressIndicator()),
                          errorWidget: (_, _, _) => const Center(child: Icon(Icons.image_not_supported, size: 64)),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (images.length > 1)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(images.length, (index) {
                        final isActive = index == _currentImageIndex;
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          width: isActive ? 10 : 6,
                          height: isActive ? 10 : 6,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isActive
                                ? Theme.of(context).colorScheme.primary
                                : Theme.of(context).colorScheme.primary.withValues(alpha: 0.3),
                          ),
                        );
                      }),
                    ),
                ],
              )
            else if (product.imageUrl != null)
              CachedNetworkImage(
                imageUrl: product.imageUrl!,
                height: 300,
                fit: BoxFit.cover,
                placeholder: (_, _) => const SizedBox(height: 300, child: Center(child: CircularProgressIndicator())),
                errorWidget: (_, _, _) => const SizedBox(height: 300, child: Center(child: Icon(Icons.image_not_supported, size: 64))),
              )
            else
              const SizedBox(height: 300, child: Center(child: Icon(Icons.image_not_supported, size: 64))),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(product.name, style: Theme.of(context).textTheme.headlineSmall),
                  if (product.category != null) Text(product.category!.name, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.outline)),
                  const SizedBox(height: 12),
                  if (variant != null) ...[
                    Row(
                      children: [
                        Text(currency.format(variant.price), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        if (variant.hasDiscount) ...[
                          const SizedBox(width: 8),
                          Text(currency.format(variant.compareAtPrice!), style: TextStyle(decoration: TextDecoration.lineThrough, color: Theme.of(context).colorScheme.outline)),
                        ],
                      ],
                    ),
                    const SizedBox(height: 8),
                    if (product.variants.length > 1) ...[
                      Text('Variant', style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 8,
                        children: List.generate(product.variants.length, (int i) {
                          final v = product.variants[i];
                          return ChoiceChip(
                            label: Text(v.name ?? 'Option ${i + 1}'),
                            selected: _selectedVariantIndex == i,
                            onSelected: (selected) => setState(() => _selectedVariantIndex = i),
                          );
                        }),
                      ),
                      const SizedBox(height: 12),
                    ],
                    Text('Stock: ${variant.stock}', style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Text('Quantity', style: Theme.of(context).textTheme.titleSmall),
                        const SizedBox(width: 16),
                        IconButton.filledTonal(icon: const Icon(Icons.remove), onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null),
                        Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Text('$_quantity')),
                        IconButton.filledTonal(
                          icon: const Icon(Icons.add),
                          onPressed: _quantity < variant.stock ? () => setState(() => _quantity++) : null,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: variant.stock > 0 ? _addToCart : null,
                        child: Text(variant.stock > 0 ? 'Add to Cart' : 'Out of Stock'),
                      ),
                    ),
                  ],
                  if (product.description != null && product.description!.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    Text('Description', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text(product.description!),
                  ],
                ],
              ),
            ),
            if (_related.isNotEmpty) ...[
              const Divider(),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text('Related Products', style: Theme.of(context).textTheme.titleMedium),
              ),
              SizedBox(
                height: 200,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: _related.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 12),
                  itemBuilder: (context, i) {
                    final p = _related[i];
                    final v = p.lowestPriceVariant;
                    return SizedBox(
                      width: 140,
                      child: Card(
                        clipBehavior: Clip.antiAlias,
                        child: InkWell(
                          onTap: () => context.pushReplacement('/products/${p.slug}'),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              if (p.imageUrl != null)
                                CachedNetworkImage(imageUrl: p.imageUrl!, height: 120, fit: BoxFit.cover)
                              else
                                const SizedBox(height: 120, child: Center(child: Icon(Icons.image))),
                              Padding(
                                padding: const EdgeInsets.all(8),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                                    if (v != null) Text('₹${v.price.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
