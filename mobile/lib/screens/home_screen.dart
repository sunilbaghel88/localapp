import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../models/category.dart';
import '../models/product.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final ApiService _api = ApiService();
  List<Product> _featured = [];
  List<Category> _categories = [];
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
      final data = await _api.getHome();
      setState(() {
        _featured = (data['featured_products'] as List<dynamic>?)
                ?.map((e) => Product.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [];
        _categories = (data['categories'] as List<dynamic>?)
                ?.map((e) => Category.fromJson(e as Map<String, dynamic>))
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

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('LocalApp'),
        actions: [
          IconButton(icon: const Icon(Icons.search), onPressed: () => context.push('/products')),
          IconButton(
            icon: const Icon(Icons.shopping_cart_outlined),
            onPressed: () => context.push('/cart'),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.person_outline),
            onSelected: (value) {
              if (value == 'login') {
                context.push('/login');
              } else if (value == 'register') {
                context.push('/register');
              } else if (value == 'profile') {
                context.push('/profile');
              } else if (value == 'orders') {
                context.push('/orders');
              } else if (value == 'logout') {
                _logout();
              }
            },
            itemBuilder: (context) {
              if (auth.isAuthenticated) {
                return [
                  const PopupMenuItem(value: 'profile', child: Text('Profile')),
                  const PopupMenuItem(value: 'orders', child: Text('My Orders')),
                  const PopupMenuItem(value: 'logout', child: Text('Logout')),
                ];
              }
              return [
                const PopupMenuItem(value: 'login', child: Text('Login')),
                const PopupMenuItem(value: 'register', child: Text('Register')),
              ];
            },
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
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
              : RefreshIndicator(
                  onRefresh: _load,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildHero(),
                        if (_categories.isNotEmpty) _buildCategories(),
                        _buildFeatured(),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildHero() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Theme.of(context).colorScheme.primary,
            Theme.of(context).colorScheme.primary.withValues(alpha: 0.8),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        children: [
          Text(
            'Welcome to LocalApp',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
          ),
          const SizedBox(height: 8),
          Text(
            'Discover amazing products from trusted sellers',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.9)),
          ),
          const SizedBox(height: 20),
          FilledButton.tonal(
            onPressed: () => context.push('/products'),
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Theme.of(context).colorScheme.primary),
            child: const Text('Shop Now'),
          ),
        ],
      ),
    );
  }

  Widget _buildCategories() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Shop by Category', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          SizedBox(
            height: 100,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _categories.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, i) {
                final c = _categories[i];
                return InkWell(
                  onTap: () => context.push('/products?category=${c.slug}'),
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 120,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(c.name, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
                        if (c.productsCount != null) Text('${c.productsCount} products', style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFeatured() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Featured Products', style: Theme.of(context).textTheme.titleLarge),
              TextButton(
                onPressed: () => context.push('/products'),
                child: const Text('View All'),
              ),
            ],
          ),
          if (_featured.isEmpty)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(child: Text('No featured products yet.')),
            )
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.72,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemCount: _featured.length,
              itemBuilder: (context, i) => _ProductCard(product: _featured[i]),
            ),
        ],
      ),
    );
  }

  void _logout() async {
    await context.read<AuthProvider>().logout();
    if (mounted) context.go('/home');
  }
}

class _ProductCard extends StatelessWidget {
  final Product product;

  const _ProductCard({required this.product});

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
                  const SizedBox(height: 4),
                  if (variant != null) ...[
                    Row(
                      children: [
                        Text('₹${variant.price.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                        if (variant.hasDiscount) ...[
                          const SizedBox(width: 6),
                          Text('₹${variant.compareAtPrice!.toStringAsFixed(2)}', style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.outline, decoration: TextDecoration.lineThrough)),
                        ],
                      ],
                    ),
                  ] else
                    Text('Out of stock', style: TextStyle(color: Theme.of(context).colorScheme.outline)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
