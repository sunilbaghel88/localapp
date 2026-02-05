import 'product.dart';
import 'product_variant.dart';

class CartItem {
  final int id;
  final int productVariantId;
  final int quantity;
  final double price;
  final ProductVariant? variant;
  final Product? product;

  CartItem({
    required this.id,
    required this.productVariantId,
    required this.quantity,
    required this.price,
    this.variant,
    this.product,
  });

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id'] as int,
      productVariantId: json['product_variant_id'] as int,
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      price: _toDouble(json['price']),
      variant: json['variant'] != null
          ? ProductVariant.fromJson(json['variant'] as Map<String, dynamic>)
          : null,
      product: json['variant'] != null &&
              (json['variant'] as Map<String, dynamic>)['product'] != null
          ? Product.fromJson(
              (json['variant'] as Map<String, dynamic>)['product'] as Map<String, dynamic>)
          : null,
    );
  }

  double get lineTotal => quantity * price;
}
