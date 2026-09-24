class OrderItem {
  final int id;
  final String name;
  final String? nameHi;
  final int quantity;
  final double price;
  final double total;
  final Map<String, dynamic>? attributes;

  OrderItem({
    required this.id,
    required this.name,
    this.nameHi,
    required this.quantity,
    required this.price,
    required this.total,
    this.attributes,
  });

  static String? _nameHi(Map<String, dynamic> json) {
    final direct = json['name_hi'];
    if (direct is String && direct.trim().isNotEmpty) return direct;
    final product = json['product'];
    if (product is Map && product['name_hi'] is String) {
      return product['name_hi'] as String;
    }
    final variant = json['variant'];
    if (variant is Map && variant['product'] is Map) {
      final nested = (variant['product'] as Map)['name_hi'];
      if (nested is String && nested.trim().isNotEmpty) return nested;
    }
    return null;
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  static Map<String, dynamic>? _toMap(dynamic v) {
    if (v == null) return null;
    if (v is Map<String, dynamic>) return v;
    if (v is Map) return Map<String, dynamic>.from(v);
    return null;
  }

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: json['id'] as int,
      name: json['name'] as String,
      nameHi: _nameHi(json),
      quantity: (json['quantity'] as num?)?.toInt() ?? 0,
      price: _toDouble(json['price']),
      total: _toDouble(json['total']),
      attributes: _toMap(json['attributes']),
    );
  }
}
