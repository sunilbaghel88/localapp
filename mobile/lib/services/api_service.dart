import '../core/api_client.dart';
import '../models/address.dart';
import '../models/order.dart';
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
      String name, String email, String password, String passwordConfirmation) async {
    final r = await _dio.post('/register', data: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'flutter-mobile',
    });
    return r.data as Map<String, dynamic>;
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

  Future<Map<String, dynamic>> placeOrder(int addressId) async {
    final r = await _dio.post('/checkout', data: {'address_id': addressId});
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
}
