import '../core/api_client.dart';

class MeetingPhoto {
  final int id;
  final String? path;
  final String? url;

  MeetingPhoto({
    required this.id,
    this.path,
    this.url,
  });

  factory MeetingPhoto.fromJson(Map<String, dynamic> json) {
    return MeetingPhoto(
      id: json['id'] as int,
      path: json['path'] as String?,
      url: json['url'] as String?,
    );
  }

  String? get fullUrl {
    final value = url ?? path;
    if (value == null || value.isEmpty) return null;
    return ApiClient.imageUrl(value);
  }
}

class Meeting {
  final int id;
  final int shopId;
  final String? shopName;
  final String title;
  final String? details;
  final String? location;
  final DateTime? startsAt;
  final List<MeetingPhoto> photos;
  final DateTime? createdAt;

  Meeting({
    required this.id,
    required this.shopId,
    this.shopName,
    required this.title,
    this.details,
    this.location,
    this.startsAt,
    this.photos = const [],
    this.createdAt,
  });

  factory Meeting.fromJson(Map<String, dynamic> json) {
    final shop = json['shop'];
    final photosRaw = json['photos'];
    return Meeting(
      id: json['id'] as int,
      shopId: json['shop_id'] as int,
      shopName: shop is Map ? shop['name'] as String? : null,
      title: json['title'] as String? ?? 'Meeting',
      details: json['details'] as String?,
      location: json['location'] as String?,
      startsAt: json['starts_at'] == null
          ? null
          : DateTime.tryParse(json['starts_at'].toString()),
      photos: photosRaw is List
          ? photosRaw
                .whereType<Map>()
                .map((e) => MeetingPhoto.fromJson(Map<String, dynamic>.from(e)))
                .toList()
          : const [],
      createdAt: json['created_at'] == null
          ? null
          : DateTime.tryParse(json['created_at'].toString()),
    );
  }
}
