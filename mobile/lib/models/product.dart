import 'category.dart';
import 'product_image.dart';
import 'product_variant.dart';
import 'shop.dart';

class Product {
  final int id;
  final int? shopId;
  final int? categoryId;
  final int? brandId;
  final String name;
  final String slug;
  final String? description;
  final String? brand;
  final String status;
  final List<ProductImage> images;
  final List<ProductVariant> variants;
  final Category? category;
  final Shop? shop;

  Product({
    required this.id,
    this.shopId,
    this.categoryId,
    this.brandId,
    required this.name,
    required this.slug,
    this.description,
    this.brand,
    this.status = 'published',
    this.images = const [],
    this.variants = const [],
    this.category,
    this.shop,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    String? brandValue;
    final dynamic rawBrand = json['brand'];
    if (rawBrand is String) {
      brandValue = rawBrand;
    } else if (rawBrand is Map<String, dynamic>) {
      brandValue = rawBrand['name'] as String?;
    }

    final brandId = json['brand_id'] as int? ??
        (json['brand'] is Map<String, dynamic> ? (json['brand']['id'] as int?) : null);

    return Product(
      id: json['id'] as int,
      shopId: json['shop_id'] as int?,
      categoryId: json['category_id'] as int?,
      brandId: brandId,
      name: json['name'] as String,
      slug: json['slug'] as String,
      description: json['description'] as String?,
      brand: brandValue,
      status: json['status'] as String? ?? 'published',
      images: (json['images'] as List<dynamic>?) ?.map((e) => ProductImage.fromJson(e as Map<String, dynamic>)).toList() ?? [],
      variants: (json['variants'] as List<dynamic>?) ?.map((e) => ProductVariant.fromJson(e as Map<String, dynamic>)).toList() ?? [],
      category: json['category'] != null ? Category.fromJson(json['category'] as Map<String, dynamic>) : null,
      shop: json['shop'] != null ? Shop.fromJson(json['shop'] as Map<String, dynamic>) : null,
    );
  }

  ProductImage? get primaryImage {
    final primary = images.where((i) => i.isPrimary).toList();
    if (primary.isNotEmpty) return primary.first;
    return images.isNotEmpty ? images.first : null;
  }

  ProductVariant? get lowestPriceVariant => variants.isNotEmpty ? variants.first : null;

  String? get imageUrl => primaryImage?.fullUrl;
}
