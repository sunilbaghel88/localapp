import 'cart_item.dart';

class Cart {
  final int? id;
  final List<CartItem> items;
  final double subtotal;
  final int itemsCount;

  Cart({
    this.id,
    this.items = const [],
    this.subtotal = 0,
    this.itemsCount = 0,
  });

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  factory Cart.fromJson(Map<String, dynamic> json) {
    final itemsList = json['items'] as List<dynamic>?;
    return Cart(
      id: json['id'] as int?,
      items: itemsList
              ?.map((e) => CartItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      subtotal: _toDouble(json['subtotal']),
      itemsCount: (json['items_count'] as int?) ?? 0,
    );
  }

  factory Cart.fromCartJson(Map<String, dynamic> cartJson,
      {double? subtotal, int? itemsCount}) {
    final itemsList = cartJson['items'] as List<dynamic>?;
    return Cart(
      id: cartJson['id'] as int?,
      items: itemsList
              ?.map((e) => CartItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      subtotal: subtotal ?? 0,
      itemsCount: itemsCount ?? 0,
    );
  }

  /// From API response: { cart: { id, items: [] }, subtotal, items_count }
  factory Cart.fromApiResponse(Map<String, dynamic> response) {
    final cartJson = response['cart'] as Map<String, dynamic>?;
    if (cartJson == null) {
      return Cart(
        subtotal: _toDouble(response['subtotal']),
        itemsCount: (response['items_count'] as int?) ?? 0,
      );
    }
    final itemsList = cartJson['items'] as List<dynamic>?;
    return Cart(
      id: cartJson['id'] as int?,
      items: itemsList
              ?.map((e) => CartItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      subtotal: _toDouble(response['subtotal']),
      itemsCount: (response['items_count'] as int?) ?? 0,
    );
  }
}
