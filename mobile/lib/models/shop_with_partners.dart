class PartnerOption {
  final int id;
  final String name;
  final String? phone;

  PartnerOption({required this.id, required this.name, this.phone});

  String get displayName => phone != null && phone!.isNotEmpty ? '$name ($phone)' : name;
}

class ShopWithPartners {
  final int id;
  final String name;
  final List<PartnerOption> partners;

  ShopWithPartners({
    required this.id,
    required this.name,
    required this.partners,
  });

  static ShopWithPartners? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;
    final partnersList = (json['partners'] ?? json['electricians']) as List<dynamic>?;
    final list = partnersList
            ?.map((e) {
              final m = e as Map<String, dynamic>?;
              if (m == null) return null;
              return PartnerOption(
                id: m['id'] as int,
                name: (m['name'] as String?) ?? '',
                phone: m['phone'] as String?,
              );
            })
            .whereType<PartnerOption>()
            .toList() ??
        [];
    return ShopWithPartners(
      id: json['id'] as int,
      name: (json['name'] as String?) ?? '',
      partners: list,
    );
  }

  static List<ShopWithPartners> fromJsonList(List<dynamic>? list) {
    if (list == null || list.isEmpty) return [];
    return list
        .map((e) => ShopWithPartners.fromJson(e as Map<String, dynamic>?))
        .whereType<ShopWithPartners>()
        .toList();
  }
}
