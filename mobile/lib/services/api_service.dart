import '../core/api_client.dart';
import '../models/address.dart';
import '../models/brand.dart';
import '../models/category.dart';
import '../models/order.dart';
import '../models/product.dart';
import '../models/shop.dart';
import '../models/user.dart';

class ApiService {
  final _dio = ApiClient.instance.dio;

  // Auth
  Future<Map<String, dynamic>> login(String email, String password) async {
    final r = await _dio.post('/login', data: {
      'email': email,
      'password': password,
      'device_name': 'flutter-mobile',
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> register(
    String name,
    String email,
    String password,
    String passwordConfirmation, {
    int? userTypeId,
  }) async {
    final data = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'flutter-mobile',
    };
    if (userTypeId != null) data['user_type_id'] = userTypeId;
    final r = await _dio.post('/register', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> getUserTypes() async {
    final r = await _dio.get('/user-types');
    final list = r.data['user_types'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<void> logout() async {
    await _dio.post('/logout');
  }

  Future<User> getCurrentUser() async {
    final r = await _dio.get('/user');
    return User.fromJson(r.data as Map<String, dynamic>);
  }

  // Home
  Future<Map<String, dynamic>> getHome() async {
    final r = await _dio.get('/home');
    return r.data as Map<String, dynamic>;
  }

  // Products
  Future<Map<String, dynamic>> getProducts({
    String? search,
    String? category,
    String sort = 'latest',
    int page = 1,
    int perPage = 12,
  }) async {
    final r = await _dio.get('/products', queryParameters: {
      if (search != null && search.isNotEmpty) 'search': search,
      if (category != null && category.isNotEmpty) 'category': category,
      'sort': sort,
      'page': page,
      'per_page': perPage,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getProduct(String slug) async {
    final r = await _dio.get('/products/$slug');
    return r.data as Map<String, dynamic>;
  }

  // Cart
  Future<Map<String, dynamic>> getCart() async {
    final r = await _dio.get('/cart');
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> addToCart(int productVariantId, int quantity) async {
    final r = await _dio.post('/cart/add', data: {
      'product_variant_id': productVariantId,
      'quantity': quantity,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateCartItem(int cartItemId, int quantity) async {
    final r = await _dio.patch('/cart/update/$cartItemId', data: {
      'quantity': quantity,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> removeCartItem(int cartItemId) async {
    final r = await _dio.delete('/cart/remove/$cartItemId');
    return r.data as Map<String, dynamic>;
  }

  // Checkout
  Future<Map<String, dynamic>> getCheckout() async {
    final r = await _dio.get('/checkout');
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> storeAddress(Map<String, dynamic> data) async {
    final r = await _dio.post('/checkout/address', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> placeOrder(
    int addressId, {
    Map<int, int?>? electrician,
  }) async {
    final data = <String, dynamic>{'address_id': addressId};
    if (electrician != null && electrician.isNotEmpty) {
      data['electrician'] = electrician.map((k, v) => MapEntry(k.toString(), v));
    }
    final r = await _dio.post('/checkout', data: data);
    return r.data as Map<String, dynamic>;
  }

  // Orders
  Future<Map<String, dynamic>> getOrders({int page = 1}) async {
    final r = await _dio.get('/orders', queryParameters: {'page': page});
    return r.data as Map<String, dynamic>;
  }

  Future<Order> getOrder(int id) async {
    final r = await _dio.get('/orders/$id');
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }

  // Addresses
  Future<List<Address>> getAddresses() async {
    final r = await _dio.get('/addresses');
    final list = r.data['addresses'] as List<dynamic>? ?? [];
    return list.map((e) => Address.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Address> createAddress(Map<String, dynamic> data) async {
    final r = await _dio.post('/addresses', data: data);
    return Address.fromJson(r.data['address'] as Map<String, dynamic>);
  }

  Future<Address> updateAddress(int id, Map<String, dynamic> data) async {
    final r = await _dio.patch('/addresses/$id', data: data);
    return Address.fromJson(r.data['address'] as Map<String, dynamic>);
  }

  Future<void> deleteAddress(int id) async {
    await _dio.delete('/addresses/$id');
  }

  Future<Address> setDefaultAddress(int id) async {
    final r = await _dio.post('/addresses/$id/set-default');
    return Address.fromJson(r.data['address'] as Map<String, dynamic>);
  }

  // ------------------------------------------------------------
  // Shop owner management APIs
  // ------------------------------------------------------------

  Future<List<Shop>> getMyShops() async {
    final r = await _dio.get('/shop/shops');
    final list = r.data['shops'] as List<dynamic>? ?? [];
    return list.map((e) => Shop.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<Category>> getShopCategories() async {
    final r = await _dio.get('/shop/categories');
    final list = r.data['categories'] as List<dynamic>? ?? [];
    return list.map((e) => Category.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<Brand>> getShopBrands() async {
    final r = await _dio.get('/shop/brands');
    final list = r.data['brands'] as List<dynamic>? ?? [];
    return list.map((e) => Brand.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Map<String, dynamic>> getShopProducts({int page = 1, int perPage = 12}) async {
    final r = await _dio.get('/shop/products', queryParameters: {
      'page': page,
      'per_page': perPage,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Product> getShopProduct(int id) async {
    final r = await _dio.get('/shop/products/$id');
    return Product.fromJson(r.data['product'] as Map<String, dynamic>);
  }

  Future<Product> createShopProduct(Map<String, dynamic> payload) async {
    final r = await _dio.post('/shop/products', data: payload);
    return Product.fromJson(r.data['product'] as Map<String, dynamic>);
  }

  Future<Product> updateShopProduct(int id, Map<String, dynamic> payload) async {
    final r = await _dio.patch('/shop/products/$id', data: payload);
    return Product.fromJson(r.data['product'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> getShopOrders({int page = 1, int perPage = 10}) async {
    final r = await _dio.get('/shop/orders', queryParameters: {
      'page': page,
      'per_page': perPage,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<Order> getShopOrder(int id) async {
    final r = await _dio.get('/shop/orders/$id');
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }

  Future<Order> updateShopOrder(int id, {required String status, required String paymentStatus}) async {
    final r = await _dio.patch('/shop/orders/$id', data: {
      'status': status,
      'payment_status': paymentStatus,
    });
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }
}
