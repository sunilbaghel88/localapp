class ShopTypeOption {
  final int id;
  final String name;
  final String? slug;

  ShopTypeOption({
    required this.id,
    required this.name,
    this.slug,
  });

  factory ShopTypeOption.fromJson(Map<String, dynamic> json) {
    return ShopTypeOption(
      id: _asInt(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
    );
  }
}

class Shop {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final int? shopTypeId;
  final ShopTypeOption? shopType;
  final String? phone;
  final String? alternatePhone;
  final String? email;
  final String? addressLine1;
  final String? addressLine2;
  final String? city;
  final String? state;
  final String? country;
  final String? postalCode;
  final String status;
  final String? shopFrontPhoto;
  final String? ownerPhoto;
  final String? aadharCard;
  final String? shopLicense;
  final String? gstCertificate;
  final String? electricityBill;

  Shop({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.shopTypeId,
    this.shopType,
    this.phone,
    this.alternatePhone,
    this.email,
    this.addressLine1,
    this.addressLine2,
    this.city,
    this.state,
    this.country,
    this.postalCode,
    this.status = 'on',
    this.shopFrontPhoto,
    this.ownerPhoto,
    this.aadharCard,
    this.shopLicense,
    this.gstCertificate,
    this.electricityBill,
  });

  factory Shop.fromJson(Map<String, dynamic> json) {
    final typeJson = json['shop_type'] as Map<String, dynamic>?;
    final shopType = typeJson == null ? null : ShopTypeOption.fromJson(typeJson);
    return Shop(
      id: _asInt(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      description: json['description'] as String?,
      shopTypeId: _asInt(json['shop_type_id']) ?? shopType?.id,
      shopType: shopType,
      phone: json['phone'] as String?,
      alternatePhone: json['alternate_phone'] as String?,
      email: json['email'] as String?,
      addressLine1: json['address_line1'] as String?,
      addressLine2: json['address_line2'] as String?,
      city: json['city'] as String?,
      state: json['state'] as String?,
      country: json['country'] as String?,
      postalCode: json['postal_code'] as String?,
      status: json['status'] as String? ?? 'on',
      shopFrontPhoto: json['shop_front_photo'] as String?,
      ownerPhoto: json['owner_photo'] as String?,
      aadharCard: json['aadhar_card'] as String?,
      shopLicense: json['shop_license'] as String?,
      gstCertificate: json['gst_certificate'] as String?,
      electricityBill: json['electricity_bill'] as String?,
    );
  }

  bool get isOn => status == 'on';

  String get shopTypeName => shopType?.name ?? '';

  String? documentPath(String field) {
    switch (field) {
      case 'shop_front_photo':
        return shopFrontPhoto;
      case 'owner_photo':
        return ownerPhoto;
      case 'aadhar_card':
        return aadharCard;
      case 'shop_license':
        return shopLicense;
      case 'gst_certificate':
        return gstCertificate;
      case 'electricity_bill':
        return electricityBill;
      default:
        return null;
    }
  }
}

int? _asInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  return int.tryParse(value.toString());
}
