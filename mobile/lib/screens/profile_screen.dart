import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../core/app_keys.dart';
import '../providers/auth_provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (!auth.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('Please log in to view your profile.'),
              FilledButton(
                onPressed: () => context.push('/login'),
                child: const Text('Login'),
              ),
            ],
          ),
        ),
      );
    }
    final user = auth.user!;
    final permissions = user.permissions ?? const [];
    final canManageProducts = permissions.contains('view_any_product');
    final canManageOrders = permissions.contains('view_any_order');
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: CircleAvatar(
                child: Text(
                  user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
                ),
              ),
              title: Text(user.name),
              subtitle: Text(user.email),
            ),
          ),
          const SizedBox(height: 16),
          ListTile(
            leading: const Icon(Icons.location_on_outlined),
            title: const Text('Addresses'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/addresses'),
          ),
          ListTile(
            leading: const Icon(Icons.shopping_bag_outlined),
            title: Text(canManageOrders ? 'Manage Orders' : 'My Orders'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () =>
                context.push(canManageOrders ? '/owner/orders' : '/orders'),
          ),
          if (canManageProducts) ...[
            const SizedBox(height: 16),
            ListTile(
              leading: const Icon(Icons.inventory_2_outlined),
              title: const Text('Manage Products'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/owner/products'),
            ),
          ],
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Logout'),
            onTap: () async {
              await auth.logout();
              if (!context.mounted) return;
              rootScaffoldMessengerKey.currentState?.showSnackBar(
                const SnackBar(content: Text('Logged out successfully')),
              );
              context.go('/login');
            },
          ),
        ],
      ),
    );
  }
}
