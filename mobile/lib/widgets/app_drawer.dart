import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../core/app_keys.dart';
import '../providers/auth_provider.dart';
import 'app_brand_logo.dart';

class AppDrawer extends StatelessWidget {
  const AppDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final permissions = user?.permissions ?? const [];
    final canManageProducts = permissions.contains('view_any_product');
    final canManageOrders = permissions.contains('view_any_order');
    final canCreateOrder = permissions.contains('create_order');
    final isPartner = user?.isPartner ?? false;
    final canAssignUserTypes = user?.canAssignUserTypes ?? false;
    final currentPath = GoRouterState.of(context).uri.path;

    return Drawer(
      backgroundColor: Colors.white,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Column(
                children: [
                  const AppBrandLogo(height: 56),
                  if (user != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      user.name,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                    if (user.email.isNotEmpty)
                      Text(
                        user.email,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.grey.shade600,
                          fontSize: 12,
                        ),
                      ),
                  ],
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  _item(
                    context,
                    icon: Icons.home_outlined,
                    label: 'Home',
                    route: '/home',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.storefront_outlined,
                    label: 'E-Shop',
                    route: '/eshop',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.workspace_premium_outlined,
                    label: 'Redeem Points',
                    route: isPartner ? '/partner/rewards' : null,
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.menu_book_outlined,
                    label: 'My Ledger',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.shopping_bag_outlined,
                    label: 'My Purchase',
                    route: '/orders',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.upload_file_outlined,
                    label: 'Upload Invoice',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.groups_outlined,
                    label: 'Meetings',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.phone_outlined,
                    label: 'Contact us',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.description_outlined,
                    label: 'T & C',
                    currentPath: currentPath,
                  ),
                  _item(
                    context,
                    icon: Icons.privacy_tip_outlined,
                    label: 'Privacy Policy',
                    currentPath: currentPath,
                  ),
                  if (canManageProducts ||
                      canManageOrders ||
                      canCreateOrder ||
                      isPartner ||
                      canAssignUserTypes) ...[
                    const Padding(
                      padding: EdgeInsets.fromLTRB(16, 12, 16, 4),
                      child: Text(
                        'MANAGEMENT',
                        style: TextStyle(
                          fontSize: 11,
                          letterSpacing: 1.2,
                          fontWeight: FontWeight.w700,
                          color: Colors.black54,
                        ),
                      ),
                    ),
                    if (canManageProducts)
                      _item(
                        context,
                        icon: Icons.inventory_2_outlined,
                        label: 'Manage Products',
                        route: '/owner/products',
                        currentPath: currentPath,
                      ),
                    if (canManageOrders)
                      _item(
                        context,
                        icon: Icons.receipt_long_outlined,
                        label: 'Manage Orders',
                        route: '/owner/orders',
                        currentPath: currentPath,
                      ),
                    if (canManageOrders)
                      _item(
                        context,
                        icon: Icons.card_giftcard_outlined,
                        label: 'Reward Redemptions',
                        route: '/owner/reward-redemptions',
                        currentPath: currentPath,
                      ),
                    if (canCreateOrder)
                      _item(
                        context,
                        icon: Icons.add_shopping_cart_outlined,
                        label: 'Create Order',
                        route: '/owner/orders/create',
                        currentPath: currentPath,
                      ),
                    if (canAssignUserTypes)
                      _item(
                        context,
                        icon: Icons.sell_outlined,
                        label: 'Assign User Types',
                        route: '/owner/user-types',
                        currentPath: currentPath,
                      ),
                    if (isPartner)
                      _item(
                        context,
                        icon: Icons.assignment_outlined,
                        label: canCreateOrder
                            ? 'Create Field Order'
                            : 'Create Order',
                        route: '/partner/orders/create',
                        currentPath: currentPath,
                      ),
                  ],
                  const Divider(),
                  _item(
                    context,
                    icon: Icons.person_outline,
                    label: 'Profile',
                    route: '/profile',
                    currentPath: currentPath,
                  ),
                  ListTile(
                    leading: const Icon(Icons.logout),
                    title: const Text('Logout'),
                    onTap: () => _logout(context),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _item(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String currentPath,
    String? route,
  }) {
    final selected = route != null && currentPath == route;
    return ListTile(
      leading: Icon(
        icon,
        color: selected ? Theme.of(context).colorScheme.primary : null,
      ),
      title: Text(
        label,
        style: TextStyle(
          fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
        ),
      ),
      selected: selected,
      onTap: () => _onItemTap(context, route: route, currentPath: currentPath),
    );
  }

  void _onItemTap(
    BuildContext context, {
    required String? route,
    required String currentPath,
  }) {
    Navigator.of(context).pop();
    if (route == null || currentPath == route) return;
    if (route == '/home' || route == '/eshop') {
      context.go(route);
    } else {
      context.push(route);
    }
  }

  Future<void> _logout(BuildContext context) async {
    final router = GoRouter.of(context);
    final auth = context.read<AuthProvider>();
    Navigator.of(context).pop();
    await auth.logout();
    rootScaffoldMessengerKey.currentState?.showSnackBar(
      const SnackBar(content: Text('Logged out successfully')),
    );
    router.go('/login');
  }
}
