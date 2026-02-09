class Address {
  final int id;
  final String? label;
  final String name;
  final String? phone;
  final String addressLine1;
  final String? addressLine2;
  final String city;
  final String state;
  final String country;
  final String postalCode;
  final bool isDefault;

  Address({
    required this.id,
    this.label,
    required this.name,
    this.phone,
    required this.addressLine1,
    this.addressLine2,
    required this.city,
    required this.state,
    required this.country,
    required this.postalCode,
    this.isDefault = false,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: json['id'] as int,
      label: json['label'] as String?,
      name: json['name'] as String,
      phone: json['phone'] as String?,
      addressLine1: json['address_line1'] as String,
      addressLine2: json['address_line2'] as String?,
      city: json['city'] as String,
      state: json['state'] as String,
      country: json['country'] as String,
      postalCode: json['postal_code'] as String,
      isDefault: json['is_default'] == true,
    );
  }

  Map<String, dynamic> toJson() => {
        'label': label,
        'name': name,
        'phone': phone,
        'address_line1': addressLine1,
        'address_line2': addressLine2,
        'city': city,
        'state': state,
        'country': country,
        'postal_code': postalCode,
        'is_default': isDefault,
      };

  String get fullAddress =>
      '$addressLine1${addressLine2 != null ? ', $addressLine2' : ''}, $city, $state $postalCode, $country';
}
