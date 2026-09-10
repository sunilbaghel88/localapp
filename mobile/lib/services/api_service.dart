import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:http_parser/http_parser.dart';

import '../core/api_client.dart';
import '../models/address.dart';
import '../models/app_branding.dart';
import '../models/brand.dart';
import '../models/category.dart';
import '../models/order.dart';
import '../models/product.dart';
import '../models/shop.dart';
import '../models/user.dart';

class ApiService {
  final _dio = ApiClient.instance.dio;

  Future<AppBranding> getAppBranding() async {
    final r = await _dio.get('/app-branding');
    return AppBranding.fromJson(r.data as Map<String, dynamic>);
  }

  // Auth
  Future<Map<String, dynamic>> login(String email, String password) async {
    final r = await _dio.post(
      '/login',
      data: {
        'email': email,
        'password': password,
        'device_name': 'flutter-mobile',
      },
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> requestLoginOtp(String email) async {
    final r = await _dio.post('/login/otp/request', data: {'email': email});
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> loginWithOtp(String email, String otp) async {
    final r = await _dio.post(
      '/login/otp/verify',
      data: {'email': email, 'otp': otp, 'device_name': 'flutter-mobile'},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> requestSmsOtp(String phone, {String purpose = 'login'}) async {
    final r = await _dio.post(
      '/login/sms/otp/request',
      data: {'phone': phone, 'purpose': purpose},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> loginWithSmsOtp(String phone, String otp) async {
    final r = await _dio.post(
      '/login/sms/otp/verify',
      data: {'phone': phone, 'otp': otp, 'device_name': 'flutter-mobile'},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> registerWithSmsOtp({
    required String firstName,
    required String lastName,
    required String phone,
    required String otp,
    String? email,
  }) async {
    final data = <String, dynamic>{
      'first_name': firstName,
      'last_name': lastName,
      'phone': phone,
      'otp': otp,
      'device_name': 'flutter-mobile',
    };
    if (email != null && email.isNotEmpty) data['email'] = email;
    final r = await _dio.post('/register/sms/otp/verify', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> register(
    String firstName,
    String lastName,
    String email,
    String password,
    String passwordConfirmation,
  ) async {
    final data = <String, dynamic>{
      'first_name': firstName,
      'last_name': lastName,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'flutter-mobile',
    };
    final r = await _dio.post('/register', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> getUserTypes() async {
    final r = await _dio.get('/user-types');
    final list = r.data['user_types'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<Map<String, dynamic>> getAssignableUsers({
    String? q,
    int page = 1,
    int perPage = 20,
  }) async {
    final r = await _dio.get(
      '/shop/assignable-users',
      queryParameters: {
        if (q != null && q.trim().isNotEmpty) 'q': q.trim(),
        'page': page,
        'per_page': perPage,
      },
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateUserTypes({
    required int userId,
    required List<int> userTypeIds,
  }) async {
    final r = await _dio.put(
      '/shop/users/$userId/user-types',
      data: {'user_type_ids': userTypeIds},
    );
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
    final r = await _dio.get(
      '/products',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
        if (category != null && category.isNotEmpty) 'category': category,
        'sort': sort,
        'page': page,
        'per_page': perPage,
      },
    );
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

  Future<Map<String, dynamic>> addToCart(
    int productVariantId,
    int quantity,
  ) async {
    final r = await _dio.post(
      '/cart/add',
      data: {'product_variant_id': productVariantId, 'quantity': quantity},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateCartItem(
    int cartItemId,
    int quantity,
  ) async {
    final r = await _dio.patch(
      '/cart/update/$cartItemId',
      data: {'quantity': quantity},
    );
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
      data['electrician'] = electrician.map(
        (k, v) => MapEntry(k.toString(), v),
      );
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
    return list
        .map((e) => Address.fromJson(e as Map<String, dynamic>))
        .toList();
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
    return list
        .map((e) => Category.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Brand>> getShopBrands() async {
    final r = await _dio.get('/shop/brands');
    final list = r.data['brands'] as List<dynamic>? ?? [];
    return list.map((e) => Brand.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Map<String, dynamic>> getShopProducts({
    int page = 1,
    int perPage = 12,
  }) async {
    final r = await _dio.get(
      '/shop/products',
      queryParameters: {'page': page, 'per_page': perPage},
    );
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

  Future<Product> updateShopProduct(
    int id,
    Map<String, dynamic> payload,
  ) async {
    final r = await _dio.patch('/shop/products/$id', data: payload);
    return Product.fromJson(r.data['product'] as Map<String, dynamic>);
  }

  /// Uploads image bytes to the server; returns the storage path for [ProductImage.url].
  ///
  /// Uses `MultipartFile.fromBytes` so it works on platforms where `dart:io` is unavailable (Flutter Web).
  Future<String> uploadShopProductImageBytes(
    Uint8List bytes, {
    required String filename,
  }) async {
    final formData = FormData.fromMap({
      'image': MultipartFile.fromBytes(bytes, filename: filename),
    });
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/product-images/upload',
      data: formData,
    );
    final data = r.data;
    if (data == null || data['url'] == null) {
      throw StateError('Invalid upload response');
    }
    return data['url'] as String;
  }

  Future<Map<String, dynamic>> extractPurchaseInvoice({
    required int shopId,
    required Uint8List bytes,
    required String filename,
  }) async {
    final formData = FormData.fromMap({
      'shop_id': shopId,
      'file': MultipartFile.fromBytes(
        bytes,
        filename: filename,
        contentType: MediaType('application', 'pdf'),
      ),
    });
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/purchase-invoices/extract',
      data: formData,
      options: Options(
        receiveTimeout: const Duration(seconds: 120),
        sendTimeout: const Duration(seconds: 60),
      ),
    );
    return r.data ?? {};
  }

  Future<Map<String, dynamic>> bulkCreateProductsFromInvoice({
    required int shopId,
    required int categoryId,
    required String status,
    required List<Map<String, dynamic>> items,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/purchase-invoices/bulk-create',
      data: {
        'shop_id': shopId,
        'category_id': categoryId,
        'status': status,
        'items': items,
      },
      options: Options(receiveTimeout: const Duration(seconds: 60)),
    );
    return r.data ?? {};
  }

  Future<Map<String, dynamic>> getShopOrders({
    int page = 1,
    int perPage = 10,
  }) async {
    final r = await _dio.get(
      '/shop/orders',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Order> getShopOrder(int id) async {
    final r = await _dio.get('/shop/orders/$id');
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }

  Future<Order> updateShopOrder(
    int id, {
    required String status,
    required String paymentStatus,
  }) async {
    final r = await _dio.patch(
      '/shop/orders/$id',
      data: {'status': status, 'payment_status': paymentStatus},
    );
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }

  Future<Order> grantShopOrderReward(
    int id, {
    required int points,
    String? notes,
  }) async {
    final r = await _dio.post(
      '/shop/orders/$id/grant-reward',
      data: {
        'points': points,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Order.fromJson(r.data['order'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> getShopRewardRedemptions({
    int page = 1,
    int perPage = 20,
    String? status,
  }) async {
    final r = await _dio.get(
      '/shop/reward-redemptions',
      queryParameters: {
        'page': page,
        'per_page': perPage,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> approveShopRewardRedemption(
    int requestId,
  ) async {
    final r = await _dio.post('/shop/reward-redemptions/$requestId/approve');
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> rejectShopRewardRedemption(
    int requestId, {
    required String reason,
  }) async {
    final r = await _dio.post(
      '/shop/reward-redemptions/$requestId/reject',
      data: {'rejection_reason': reason},
    );
    return r.data as Map<String, dynamic>;
  }

  /// Shop owner: create order on behalf of a customer (matches electrician web flow).
  Future<List<Map<String, dynamic>>> searchShopOrderCustomers(String q) async {
    final r = await _dio.get(
      '/shop/order-create/search-customers',
      queryParameters: {'q': q},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> getShopOrderPartners(
    int shopId,
  ) async {
    final r = await _dio.get(
      '/shop/order-create/partners',
      queryParameters: {'shop_id': shopId},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> getShopOrderDeliveryAgents(
    int shopId,
  ) async {
    final r = await _dio.get(
      '/shop/order-create/delivery-agents',
      queryParameters: {'shop_id': shopId},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> searchShopOrderProducts(
    int shopId,
    String q,
  ) async {
    final r = await _dio.get(
      '/shop/order-create/search-products',
      queryParameters: {'shop_id': shopId, 'q': q},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> getShopOrderCustomerAddresses(
    int customerId,
  ) async {
    final r = await _dio.get(
      '/shop/order-create/customers/$customerId/addresses',
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<Map<String, dynamic>> createShopOrderCustomer({
    required String name,
    required String email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/order-create/customers',
      data: {
        'name': name,
        'email': email.trim().toLowerCase(),
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
    final row = r.data?['data'] as Map<String, dynamic>?;
    if (row == null) throw StateError('Invalid create customer response');
    return row;
  }

  Future<Map<String, dynamic>> createShopOrderPartner({
    required int shopId,
    required String name,
    required String email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/order-create/partners',
      data: {
        'shop_id': shopId,
        'name': name,
        'email': email.trim().toLowerCase(),
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
    final row = r.data?['data'] as Map<String, dynamic>?;
    if (row == null) throw StateError('Invalid create partner response');
    return row;
  }

  Future<Map<String, dynamic>> createShopOrderDeliveryAgent({
    required int shopId,
    required String name,
    required String email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/order-create/delivery-agents',
      data: {
        'shop_id': shopId,
        'name': name,
        'email': email.trim().toLowerCase(),
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
    final row = r.data?['data'] as Map<String, dynamic>?;
    if (row == null) throw StateError('Invalid create delivery agent response');
    return row;
  }

  Future<Map<String, dynamic>> createShopOrderCustomerAddress({
    required int customerId,
    String? label,
    required String name,
    String? phone,
    required String addressLine1,
    String? addressLine2,
    required String city,
    required String state,
    required String country,
    required String postalCode,
    bool isDefault = false,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/order-create/customers/$customerId/addresses',
      data: {
        if (label != null && label.trim().isNotEmpty) 'label': label.trim(),
        'name': name,
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
        'address_line1': addressLine1,
        if (addressLine2 != null && addressLine2.trim().isNotEmpty)
          'address_line2': addressLine2.trim(),
        'city': city,
        'state': state,
        'country': country,
        'postal_code': postalCode,
        if (isDefault) 'is_default': true,
      },
    );
    final row = r.data?['data'] as Map<String, dynamic>?;
    if (row == null) throw StateError('Invalid create address response');
    return row;
  }

  /// Returns `{ 'data': [...], 'missing': [...] }` like the web AI endpoint.
  Future<Map<String, dynamic>> shopOrderAiSuggest(
    int shopId,
    String prompt,
  ) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/order-create/ai-suggest',
      data: {'shop_id': shopId, 'prompt': prompt},
    );
    return r.data ?? {};
  }

  Future<Order> createShopOrderOnBehalf({
    required int shopId,
    required int customerUserId,
    required String deliveryMethod,
    int? addressId,
    int? electricianUserId,
    int? deliveryAgentUserId,
    double? deliveryCharge,
    required List<Map<String, dynamic>> items,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/shop/orders',
      data: {
        'shop_id': shopId,
        'user_id': customerUserId,
        'delivery_method': deliveryMethod,
        'items': items,
        ...?addressId != null ? {'address_id': addressId} : null,
        ...?electricianUserId != null
            ? {'electrician_user_id': electricianUserId}
            : null,
        ...?deliveryAgentUserId != null
            ? {'delivery_agent_user_id': deliveryAgentUserId}
            : null,
        ...?deliveryCharge != null ? {'delivery_charge': deliveryCharge} : null,
      },
    );
    final data = r.data;
    if (data == null || data['order'] == null) {
      throw StateError('Invalid create order response');
    }
    return Order.fromJson(data['order'] as Map<String, dynamic>);
  }

  // ------------------------------------------------------------
  // Partner APIs (auth:sanctum + reward-eligible user types)
  // ------------------------------------------------------------

  Future<List<Shop>> getPartnerShops() async {
    final r = await _dio.get('/partner/shops');
    final list = r.data['shops'] as List<dynamic>? ?? [];
    return list.map((e) => Shop.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Map<String, dynamic>> getPartnerRewardGrants({
    int page = 1,
    int perPage = 50,
  }) async {
    final r = await _dio.get(
      '/partner/reward-grants',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getPartnerRewardRedemptions({
    int page = 1,
    int perPage = 20,
  }) async {
    final r = await _dio.get(
      '/partner/reward-redemptions',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> createPartnerRewardRedemption({
    required int shopId,
    required int requestedPoints,
    required String redemptionType,
    String? note,
  }) async {
    final r = await _dio.post(
      '/partner/reward-redemptions',
      data: {
        'shop_id': shopId,
        'requested_points': requestedPoints,
        'redemption_type': redemptionType,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      },
    );
    return r.data as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> searchPartnerOrderCustomers(
    String q,
  ) async {
    final r = await _dio.get(
      '/partner/order-create/search-customers',
      queryParameters: {'q': q},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> searchPartnerOrderProducts(
    int shopId,
    String q,
  ) async {
    final r = await _dio.get(
      '/partner/order-create/search-products',
      queryParameters: {'shop_id': shopId, 'q': q},
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<List<Map<String, dynamic>>> getPartnerOrderCustomerAddresses(
    int customerId,
  ) async {
    final r = await _dio.get(
      '/partner/order-create/customers/$customerId/addresses',
    );
    final list = r.data['data'] as List<dynamic>? ?? [];
    return list.map((e) => e as Map<String, dynamic>).toList();
  }

  Future<Map<String, dynamic>> partnerOrderAiSuggest(
    int shopId,
    String prompt,
  ) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/partner/order-create/ai-suggest',
      data: {'shop_id': shopId, 'prompt': prompt},
    );
    return r.data ?? {};
  }

  /// Partner creates order for customer; server sets `electrician_user_id` to the logged-in user.
  Future<Order> createPartnerOrderOnBehalf({
    required int shopId,
    required int customerUserId,
    int? addressId,
    required List<Map<String, dynamic>> items,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/partner/orders',
      data: {
        'shop_id': shopId,
        'user_id': customerUserId,
        'items': items,
        'address_id': ?addressId,
      },
    );
    final data = r.data;
    if (data == null || data['order'] == null) {
      throw StateError('Invalid create order response');
    }
    return Order.fromJson(data['order'] as Map<String, dynamic>);
  }
}
