import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../models/shop.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ShopOwnerShopsScreen extends StatefulWidget {
  const ShopOwnerShopsScreen({super.key});

  @override
  State<ShopOwnerShopsScreen> createState() => _ShopOwnerShopsScreenState();
}

class _ShopOwnerShopsScreenState extends State<ShopOwnerShopsScreen> {
  final ApiService _api = ApiService();

  List<Shop> _shops = [];
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final shops = await _api.getMyShops();
      if (!mounted) return;
      setState(() {
        _shops = shops;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
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
    final permissions =
        context.watch<AuthProvider>().user?.permissions ?? const [];
    final canCreate = permissions.contains('create_shop');
    final canUpdate = permissions.contains('update_shop');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Manage Shops'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/home'),
        ),
        actions: [
          if (canCreate)
            IconButton(
              tooltip: 'Add shop',
              icon: const Icon(Icons.add),
              onPressed: () async {
                await context.push('/owner/shops/create');
                if (mounted) _load();
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
                      FilledButton(
                        onPressed: _load,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _shops.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            const SizedBox(height: 120),
                            const Icon(
                              Icons.storefront_outlined,
                              size: 56,
                              color: Colors.black38,
                            ),
                            const SizedBox(height: 12),
                            const Text(
                              'No shops yet',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 8),
                            const Text(
                              'Add a shop to start listing products.',
                              textAlign: TextAlign.center,
                            ),
                            if (canCreate) ...[
                              const SizedBox(height: 20),
                              Center(
                                child: FilledButton.icon(
                                  onPressed: () async {
                                    await context.push('/owner/shops/create');
                                    if (mounted) _load();
                                  },
                                  icon: const Icon(Icons.add),
                                  label: const Text('Add shop'),
                                ),
                              ),
                            ],
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _shops.length,
                          itemBuilder: (context, i) {
                            final shop = _shops[i];
                            final photo = shop.shopFrontPhoto;
                            final subtitle = [
                              if (shop.shopTypeName.isNotEmpty)
                                shop.shopTypeName,
                              if ((shop.city ?? '').trim().isNotEmpty)
                                shop.city!.trim(),
                            ].join(' • ');
                            return Card(
                              child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 6,
                                ),
                                leading: photo != null && photo.isNotEmpty
                                    ? ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: CachedNetworkImage(
                                          imageUrl: ApiClient.imageUrl(photo),
                                          width: 48,
                                          height: 48,
                                          fit: BoxFit.cover,
                                          placeholder: (context, url) =>
                                              const SizedBox(
                                            width: 48,
                                            height: 48,
                                            child: Center(
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                              ),
                                            ),
                                          ),
                                          errorWidget: (context, url, error) =>
                                              const Icon(
                                            Icons.storefront_outlined,
                                          ),
                                        ),
                                      )
                                    : const Icon(Icons.storefront_outlined),
                                title: Text(shop.name),
                                subtitle: Text(
                                  [
                                    if (subtitle.isNotEmpty) subtitle,
                                    shop.isOn ? 'On' : 'Off',
                                  ].join(' • '),
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: canUpdate
                                    ? () async {
                                        await context.push(
                                          '/owner/shops/${shop.id}/edit',
                                        );
                                        if (mounted) _load();
                                      }
                                    : null,
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
