import '../core/api_client.dart';

class ProductImage {
  final int id;
  final String url;
  final bool isPrimary;
  final int sortOrder;

  ProductImage({
    required this.id,
    required this.url,
    this.isPrimary = false,
    this.sortOrder = 0,
  });

  factory ProductImage.fromJson(Map<String, dynamic> json) {
    return ProductImage(
      id: json['id'] as int,
      url: json['url'] as String,
      isPrimary: json['is_primary'] == true,
      sortOrder: (json['sort_order'] as int?) ?? 0,
    );
  }

  String get fullUrl => ApiClient.imageUrl(url);
}
