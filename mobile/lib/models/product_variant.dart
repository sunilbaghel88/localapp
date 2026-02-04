class ProductVariant {
  final int id;
  final int productId;
  final String? sku;
  final String? name;
  final int stock;
  final double price;
  final double? compareAtPrice;
  final Map<String, dynamic>? attributes;
  final bool isActive;

  ProductVariant({
    required this.id,
    required this.productId,
    this.sku,
    this.name,
    required this.stock,
    required this.price,
    this.compareAtPrice,
    this.attributes,
    this.isActive = true,
  });

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
    // Sometimes backend returns [] for empty JSON columns
    return null;
  }

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      sku: json['sku'] as String?,
      name: json['name'] as String?,
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      price: _toDouble(json['price']),
      compareAtPrice: json['compare_at_price'] == null ? null : _toDouble(json['compare_at_price']),
      attributes: _toMap(json['attributes']),
      isActive: json['is_active'] != false,
    );
  }

  bool get hasDiscount => compareAtPrice != null && compareAtPrice! > price && compareAtPrice! > 0;
}
