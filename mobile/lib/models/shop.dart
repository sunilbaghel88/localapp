class Shop {
  final int id;
  final String name;
  final String slug;
  final String? description;

  Shop({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
  });

  factory Shop.fromJson(Map<String, dynamic> json) {
    return Shop(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      description: json['description'] as String?,
    );
  }
}
