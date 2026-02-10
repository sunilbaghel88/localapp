import 'address.dart';
import 'order_item.dart';
import 'shop.dart';

class Order {
  final int id;
  final int shopId;
  final String status;
  final String paymentStatus;
  final double subtotal;
  final double discountTotal;
  final double shippingTotal;
  final double taxTotal;
  final double grandTotal;
  final Shop? shop;
  final Address? address;
  final List<OrderItem> items;

  Order({
    required this.id,
    required this.shopId,
    required this.status,
    required this.paymentStatus,
    required this.subtotal,
    required this.discountTotal,
    required this.shippingTotal,
    required this.taxTotal,
    required this.grandTotal,
    this.shop,
    this.address,
    this.items = const [],
  });

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      shopId: json['shop_id'] as int,
      status: json['status'] as String? ?? 'pending',
      paymentStatus: json['payment_status'] as String? ?? 'pending',
      subtotal: _toDouble(json['subtotal']),
      discountTotal: _toDouble(json['discount_total']),
      shippingTotal: _toDouble(json['shipping_total']),
      taxTotal: _toDouble(json['tax_total']),
      grandTotal: _toDouble(json['grand_total']),
      shop: json['shop'] != null
          ? Shop.fromJson(json['shop'] as Map<String, dynamic>)
          : null,
      address: json['address'] != null
          ? Address.fromJson(json['address'] as Map<String, dynamic>)
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}
