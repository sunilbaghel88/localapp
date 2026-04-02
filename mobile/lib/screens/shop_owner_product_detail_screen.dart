import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../models/product.dart';
import '../models/product_image.dart';
import '../models/product_variant.dart';
import '../services/api_service.dart';

class ShopOwnerProductDetailScreen extends StatefulWidget {
  final int productId;

  const ShopOwnerProductDetailScreen({super.key, required this.productId});

  @override
  State<ShopOwnerProductDetailScreen> createState() => _ShopOwnerProductDetailScreenState();
}

class _ShopOwnerProductDetailScreenState extends State<ShopOwnerProductDetailScreen> {
  final ApiService _api = ApiService();
  Product? _product;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final p = await _api.getShopProduct(widget.productId);
      if (!mounted) return;
      setState(() {
        _product = p;
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
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final product = _product;
    return Scaffold(
      appBar: AppBar(
        title: Text(product?.name ?? 'Product'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/owner/products'),
        ),
        actions: [
          if (product != null)
            IconButton(
              icon: const Icon(Icons.edit_outlined),
              onPressed: () => context.push('/owner/products/${product.id}/edit'),
            ),
        ],
      ),
      body: _loading && product == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && product == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : product == null
                  ? const Center(child: Text('Not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(product.status.toUpperCase(), style: Theme.of(context).textTheme.labelLarge),
                                const SizedBox(height: 12),
                                if (product.shop != null)
                                  Text('Shop: ${product.shop!.name}', style: Theme.of(context).textTheme.bodyMedium),
                                if (product.category != null)
                                  Text('Category: ${product.category!.name}', style: Theme.of(context).textTheme.bodyMedium),
                                if ((product.brand ?? '').trim().isNotEmpty)
                                  Text('Brand: ${(product.brand ?? '').trim()}', style: Theme.of(context).textTheme.bodyMedium),
                                const SizedBox(height: 12),
                                Text('Slug: ${product.slug}', style: Theme.of(context).textTheme.bodySmall),
                              ],
                            ),
                          ),
                        ),
                        if (product.description != null && product.description!.trim().isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text('Description', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(product.description!))),
                        ],
                        const SizedBox(height: 16),
                        Text('Variants', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        if (product.variants.isEmpty)
                          const Text('No variants yet.')
                        else
                          ...product.variants.map((v) => _VariantCard(variant: v)),
                        const SizedBox(height: 16),
                        Text('Images', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        if (product.images.isEmpty)
                          const Text('No images yet.')
                        else
                          _ImagesGrid(images: product.images),
                        const SizedBox(height: 24),
                        FilledButton.icon(
                          icon: const Icon(Icons.edit_outlined),
                          onPressed: () => context.push('/owner/products/${product.id}/edit'),
                          label: const Text('Edit product'),
                        ),
                      ],
                    ),
    );
  }
}

class _VariantCard extends StatelessWidget {
  final ProductVariant variant;

  const _VariantCard({required this.variant});

  @override
  Widget build(BuildContext context) {
    final attrs = variant.attributes ?? const {};
    final attrText = attrs.isEmpty ? '—' : attrs.entries.map((e) => '${e.key}: ${e.value}').join(', ');
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(variant.name?.trim().isNotEmpty == true ? variant.name! : variant.sku ?? 'Variant', style: Theme.of(context).textTheme.titleSmall),
                Text(variant.isActive ? 'Active' : 'Inactive', style: Theme.of(context).textTheme.labelSmall),
              ],
            ),
            const SizedBox(height: 8),
            Text('SKU: ${variant.sku ?? '—'}', style: Theme.of(context).textTheme.bodySmall),
            Text('Price: ₹${variant.price.toStringAsFixed(2)}', style: Theme.of(context).textTheme.bodyMedium),
            if (variant.compareAtPrice != null)
              Text('Compare at: ₹${variant.compareAtPrice!.toStringAsFixed(2)}', style: Theme.of(context).textTheme.bodySmall),
            Text('Stock: ${variant.stock}', style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 8),
            Text('Attributes: $attrText', style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _ImagesGrid extends StatelessWidget {
  final List<ProductImage> images;

  const _ImagesGrid({required this.images});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: images.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
      ),
      itemBuilder: (context, i) {
        final img = images[i];
        return Stack(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: CachedNetworkImage(
                imageUrl: img.fullUrl,
                height: 90,
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(
                  height: 90,
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                ),
                errorWidget: (context, url, error) => Container(
                  height: 90,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Center(child: Icon(Icons.image_not_supported)),
                ),
              ),
            ),
            if (img.isPrimary)
              Positioned(
                top: 6,
                left: 6,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.9),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Primary',
                    style: TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

