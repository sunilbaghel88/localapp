class ElectricianOption {
  final int id;
  final String name;
  final String? phone;

  ElectricianOption({required this.id, required this.name, this.phone});

  String get displayName => phone != null && phone!.isNotEmpty ? '$name ($phone)' : name;
}

class ShopWithElectricians {
  final int id;
  final String name;
  final List<ElectricianOption> electricians;

  ShopWithElectricians({
    required this.id,
    required this.name,
    required this.electricians,
  });

  static ShopWithElectricians? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;
    final electriciansList = json['electricians'] as List<dynamic>?;
    final list = electriciansList
        ?.map((e) {
          final m = e as Map<String, dynamic>?;
          if (m == null) return null;
          return ElectricianOption(
            id: m['id'] as int,
            name: (m['name'] as String?) ?? '',
            phone: m['phone'] as String?,
          );
        })
        .whereType<ElectricianOption>()
        .toList() ??
        [];
    return ShopWithElectricians(
      id: json['id'] as int,
      name: (json['name'] as String?) ?? '',
      electricians: list,
    );
  }

  static List<ShopWithElectricians> fromJsonList(List<dynamic>? list) {
    if (list == null || list.isEmpty) return [];
    return list
        .map((e) => ShopWithElectricians.fromJson(e as Map<String, dynamic>?))
        .whereType<ShopWithElectricians>()
        .toList();
  }
}
