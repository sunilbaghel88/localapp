class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? role;
  final List<String>? permissions;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.role,
    this.permissions,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final permissionsRaw = json['permissions'];
    final permissions = permissionsRaw is List
        ? permissionsRaw.map((e) => e.toString()).toList()
        : null;

    return User(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String?,
      permissions: permissions,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
        'permissions': permissions,
      };
}
