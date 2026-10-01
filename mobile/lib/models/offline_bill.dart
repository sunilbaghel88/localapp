import '../core/api_client.dart';

class OfflineBill {
  final int id;
  final int shopId;
  final String? shopName;
  final String type;
  final String? paymentMode;
  final int customerId;
  final String? customerName;
  final String? customerPhone;
  final int? partnerId;
  final String? partnerName;
  final int rewardPoints;
  final double amount;
  final double? closingBalance;
  final String? remarks;
  final String? imagePath;
  final String? imageUrl;
  final DateTime? createdAt;

  OfflineBill({
    required this.id,
    required this.shopId,
    this.shopName,
    this.type = 'debit',
    this.paymentMode,
    required this.customerId,
    this.customerName,
    this.customerPhone,
    this.partnerId,
    this.partnerName,
    this.rewardPoints = 0,
    required this.amount,
    this.closingBalance,
    this.remarks,
    this.imagePath,
    this.imageUrl,
    this.createdAt,
  });

  factory OfflineBill.fromJson(Map<String, dynamic> json) {
    final shop = json['shop'];
    final customer = json['customer'];
    final partner = json['partner'];
    return OfflineBill(
      id: json['id'] as int,
      shopId: json['shop_id'] as int,
      shopName: shop is Map ? shop['name'] as String? : null,
      type: json['type'] as String? ?? 'debit',
      paymentMode: json['payment_mode'] as String?,
      customerId: json['customer_id'] as int,
      customerName: customer is Map ? customer['name'] as String? : null,
      customerPhone: customer is Map ? customer['phone'] as String? : null,
      partnerId: json['partner_id'] as int?,
      partnerName: partner is Map ? partner['name'] as String? : null,
      rewardPoints: (json['reward_points'] as num?)?.toInt() ?? 0,
      amount: _toDouble(json['amount']),
      closingBalance: json['closing_balance'] == null
          ? null
          : _toDouble(json['closing_balance']),
      remarks: json['remarks'] as String?,
      imagePath: json['image_path'] as String?,
      imageUrl: json['image_url'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.tryParse(json['created_at'].toString()),
    );
  }

  String get typeLabel => type == 'credit' ? 'Credit' : 'Debit';

  String? get paymentModeLabel {
    return switch (paymentMode) {
      'cash' => 'Cash',
      'upi' => 'UPI',
      'online' => 'Online',
      'cheque' => 'Cheque',
      _ => paymentMode,
    };
  }

  String? get fullImageUrl {
    final url = imageUrl ?? imagePath;
    if (url == null || url.isEmpty) return null;
    return ApiClient.imageUrl(url);
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class OfflineBillDue {
  final int shopId;
  final String? shopName;
  final int customerId;
  final String? customerName;
  final String? customerPhone;
  final double balance;
  final bool hasMobile;

  OfflineBillDue({
    required this.shopId,
    this.shopName,
    required this.customerId,
    this.customerName,
    this.customerPhone,
    required this.balance,
    this.hasMobile = false,
  });

  factory OfflineBillDue.fromJson(Map<String, dynamic> json) {
    final shop = json['shop'];
    final customer = json['customer'];
    return OfflineBillDue(
      shopId: json['shop_id'] as int,
      shopName: shop is Map ? shop['name'] as String? : null,
      customerId: json['customer_id'] as int,
      customerName: customer is Map ? customer['name'] as String? : null,
      customerPhone: customer is Map ? customer['phone'] as String? : null,
      balance: OfflineBill._toDouble(json['balance']),
      hasMobile: json['has_mobile'] == true,
    );
  }

  String get key => '$shopId:$customerId';
}
