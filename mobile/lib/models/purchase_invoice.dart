class PurchaseInvoice {
  final int id;
  final int shopId;
  final String? supplierName;
  final String? supplierGstin;
  final String? invoiceNumber;
  final DateTime? invoiceDate;
  final double cgstAmount;
  final double sgstAmount;
  final double igstAmount;
  final String? sourceFilename;
  final String status;
  final int? itemsCount;
  final List<PurchaseInvoiceItem> items;
  final DateTime? createdAt;

  PurchaseInvoice({
    required this.id,
    required this.shopId,
    this.supplierName,
    this.supplierGstin,
    this.invoiceNumber,
    this.invoiceDate,
    this.cgstAmount = 0,
    this.sgstAmount = 0,
    this.igstAmount = 0,
    this.sourceFilename,
    this.status = 'imported',
    this.itemsCount,
    this.items = const [],
    this.createdAt,
  });

  factory PurchaseInvoice.fromJson(Map<String, dynamic> json) {
    return PurchaseInvoice(
      id: json['id'] as int,
      shopId: json['shop_id'] as int,
      supplierName: json['supplier_name'] as String?,
      supplierGstin: json['supplier_gstin'] as String?,
      invoiceNumber: json['invoice_number'] as String?,
      invoiceDate: json['invoice_date'] == null
          ? null
          : DateTime.tryParse(json['invoice_date'].toString()),
      cgstAmount: _toDouble(json['cgst_amount']),
      sgstAmount: _toDouble(json['sgst_amount']),
      igstAmount: _toDouble(json['igst_amount']),
      sourceFilename: json['source_filename'] as String?,
      status: json['status'] as String? ?? 'imported',
      itemsCount: json['items_count'] as int?,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => PurchaseInvoiceItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      createdAt: json['created_at'] == null
          ? null
          : DateTime.tryParse(json['created_at'].toString()),
    );
  }

  String get title {
    if ((invoiceNumber ?? '').trim().isNotEmpty) {
      return 'Invoice ${invoiceNumber!.trim()}';
    }
    if ((supplierName ?? '').trim().isNotEmpty) {
      return supplierName!.trim();
    }
    return 'Purchase #$id';
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class PurchaseInvoiceItem {
  final int id;
  final int? productId;
  final int? productVariantId;
  final String name;
  final String? variantName;
  final String? hsnCode;
  final String? unit;
  final int quantity;
  final double listPrice;
  final double discountPercent;
  final double costPrice;
  final double sellingPrice;
  final double cgstAmount;
  final double sgstAmount;
  final double igstAmount;

  PurchaseInvoiceItem({
    required this.id,
    this.productId,
    this.productVariantId,
    required this.name,
    this.variantName,
    this.hsnCode,
    this.unit,
    required this.quantity,
    required this.listPrice,
    required this.discountPercent,
    required this.costPrice,
    required this.sellingPrice,
    this.cgstAmount = 0,
    this.sgstAmount = 0,
    this.igstAmount = 0,
  });

  factory PurchaseInvoiceItem.fromJson(Map<String, dynamic> json) {
    return PurchaseInvoiceItem(
      id: json['id'] as int,
      productId: json['product_id'] as int?,
      productVariantId: json['product_variant_id'] as int?,
      name: (json['name'] ?? '').toString(),
      variantName: json['variant_name'] as String?,
      hsnCode: json['hsn_code'] as String?,
      unit: json['unit'] as String?,
      quantity: (json['quantity'] as num?)?.toInt() ?? 0,
      listPrice: PurchaseInvoice._toDouble(json['list_price']),
      discountPercent: PurchaseInvoice._toDouble(json['discount_percent']),
      costPrice: PurchaseInvoice._toDouble(json['cost_price']),
      sellingPrice: PurchaseInvoice._toDouble(json['selling_price']),
      cgstAmount: PurchaseInvoice._toDouble(json['cgst_amount']),
      sgstAmount: PurchaseInvoice._toDouble(json['sgst_amount']),
      igstAmount: PurchaseInvoice._toDouble(json['igst_amount']),
    );
  }
}
