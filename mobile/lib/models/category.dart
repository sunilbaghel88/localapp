class Category {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final bool isActive;
  final int? productsCount;

  Category({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.isActive = true,
    this.productsCount,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      description: json['description'] as String?,
      isActive: json['is_active'] == true,
      productsCount: json['products_count'] as int?,
    );
  }
}
