class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? role;
  final List<String>? permissions;
  final bool isElectrician;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.role,
    this.permissions,
    this.isElectrician = false,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final permissionsRaw = json['permissions'];
    final permissions = permissionsRaw is List
        ? permissionsRaw.map((e) => e.toString()).toList()
        : null;

    return User(
      id: json['id'] as int,
      name: _fullNameFromJson(json),
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String?,
      permissions: permissions,
      isElectrician: json['is_electrician'] == true,
    );
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
        'is_electrician': isElectrician,
      };
}
