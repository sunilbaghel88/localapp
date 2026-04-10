import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'screens/splash_screen.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/register_screen.dart';
import 'screens/products_screen.dart';
import 'screens/product_detail_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/orders_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/addresses_screen.dart';
import 'screens/shop_owner_products_screen.dart';
import 'screens/shop_owner_product_detail_screen.dart';
import 'screens/shop_owner_product_form_screen.dart';
import 'screens/shop_owner_orders_screen.dart';
import 'screens/shop_owner_order_detail_screen.dart';
import 'screens/shop_owner_create_order_screen.dart';
import 'screens/electrician_rewards_screen.dart';
import 'screens/electrician_create_order_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

GoRouter createRouter(BuildContext context) {
  final auth = context.read<AuthProvider>();
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/',
    redirect: (context, state) {
      final isAuth = auth.isAuthenticated;
      final isOwnerRoute = state.matchedLocation.startsWith('/owner');
      final isElectricianRoute = state.matchedLocation.startsWith('/electrician');
      final isAuthRoute = state.matchedLocation == '/login' || state.matchedLocation == '/register';
      final isSplash = state.matchedLocation == '/';
      if (isSplash) return null;
      if (auth.isLoading) return null;
      if (!isAuth && (state.matchedLocation.startsWith('/cart') ||
          state.matchedLocation.startsWith('/checkout') ||
          state.matchedLocation.startsWith('/orders') ||
          state.matchedLocation.startsWith('/owner') ||
          state.matchedLocation.startsWith('/electrician') ||
          state.matchedLocation.startsWith('/profile') ||
          state.matchedLocation.startsWith('/addresses'))) {
        return '/login';
      }
      if (isAuth && isAuthRoute) return '/home';

      if (isOwnerRoute && isAuth) {
        final permissions = auth.user?.permissions ?? const [];
        final canAccessOwner = permissions.contains('view_any_product') || permissions.contains('view_any_order');
        if (!canAccessOwner) return '/home';
        if (state.matchedLocation == '/owner/orders/create' && !permissions.contains('create_order')) {
          return '/owner/orders';
        }
      }

      if (isElectricianRoute && isAuth) {
        final isElectrician = auth.user?.isElectrician ?? false;
        if (!isElectrician) return '/home';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) => const SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (_, _) => const LoginScreen(),
      ),
      GoRoute(
        path: '/register',
        builder: (_, _) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/home',
        builder: (_, _) => const HomeScreen(),
      ),
      GoRoute(
        path: '/products',
        builder: (_, _) => const ProductsScreen(),
        routes: [
          GoRoute(
            path: ':slug',
            builder: (context, state) {
              final slug = state.pathParameters['slug']!;
              return ProductDetailScreen(slug: slug);
            },
          ),
        ],
      ),
      GoRoute(
        path: '/cart',
        builder: (_, _) => const CartScreen(),
      ),
      GoRoute(
        path: '/checkout',
        builder: (_, _) => const CheckoutScreen(),
      ),
      GoRoute(
        path: '/orders',
        builder: (_, _) => const OrdersScreen(),
        routes: [
          GoRoute(
            path: ':id',
            builder: (context, state) {
              final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
              return OrderDetailScreen(orderId: id);
            },
          ),
        ],
      ),
      GoRoute(
        path: '/profile',
        builder: (_, _) => const ProfileScreen(),
      ),
      GoRoute(
        path: '/addresses',
        builder: (_, _) => const AddressesScreen(),
      ),
      GoRoute(
        path: '/owner/products',
        builder: (context, state) => const ShopOwnerProductsScreen(),
      ),
      GoRoute(
        path: '/owner/products/create',
        builder: (context, state) => const ShopOwnerProductFormScreen(productId: null),
      ),
      GoRoute(
        path: '/owner/products/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
          return ShopOwnerProductDetailScreen(productId: id);
        },
      ),
      GoRoute(
        path: '/owner/products/:id/edit',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
          return ShopOwnerProductFormScreen(productId: id);
        },
      ),
      GoRoute(
        path: '/owner/orders',
        builder: (context, state) => const ShopOwnerOrdersScreen(),
      ),
      GoRoute(
        path: '/owner/orders/create',
        builder: (context, state) => const ShopOwnerCreateOrderScreen(),
      ),
      GoRoute(
        path: '/owner/orders/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
          return ShopOwnerOrderDetailScreen(orderId: id);
        },
      ),
      GoRoute(
        path: '/electrician/rewards',
        builder: (context, state) => const ElectricianRewardsScreen(),
      ),
      GoRoute(
        path: '/electrician/orders/create',
        builder: (context, state) => const ElectricianCreateOrderScreen(),
      ),
    ],
  );
}
