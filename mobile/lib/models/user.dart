class UserTypeInfo {
  final int id;
  final String name;
  final String slug;

  const UserTypeInfo({
    required this.id,
    required this.name,
    required this.slug,
  });

  factory UserTypeInfo.fromJson(Map<String, dynamic> json) {
    return UserTypeInfo(
      id: json['id'] as int,
      name: (json['name'] ?? '').toString(),
      slug: (json['slug'] ?? '').toString(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'slug': slug,
  };
}

class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? role;
  final List<String>? permissions;
  final List<UserTypeInfo> userTypes;
  final bool isElectrician;
  final bool canAssignUserTypes;
  final int rewardPoints;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.role,
    this.permissions,
    this.userTypes = const [],
    this.isElectrician = false,
    this.canAssignUserTypes = false,
    this.rewardPoints = 0,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final permissionsRaw = json['permissions'];
    final permissions = permissionsRaw is List
        ? permissionsRaw.map((e) => e.toString()).toList()
        : null;

    final typesRaw = json['user_types'];
    final userTypes = typesRaw is List
        ? typesRaw
              .whereType<Map>()
              .map((e) => UserTypeInfo.fromJson(Map<String, dynamic>.from(e)))
              .toList()
        : const <UserTypeInfo>[];

    return User(
      id: json['id'] as int,
      name: _fullNameFromJson(json),
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String?,
      role: json['role'] as String?,
      permissions: permissions,
      userTypes: userTypes,
      isElectrician: json['is_electrician'] == true,
      canAssignUserTypes: json['can_assign_user_types'] == true,
      rewardPoints: _intFromJson(json['reward_points']),
    );
  }

  static int _intFromJson(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _fullNameFromJson(Map<String, dynamic> json) {
    final name = json['name'] as String?;
    if (name != null && name.trim().isNotEmpty) return name.trim();
    return [json['first_name'], json['last_name']]
        .whereType<String>()
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .join(' ');
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'phone': phone,
    'role': role,
    'permissions': permissions,
    'user_types': userTypes.map((t) => t.toJson()).toList(),
    'is_electrician': isElectrician,
    'can_assign_user_types': canAssignUserTypes,
    'reward_points': rewardPoints,
  };
}
