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
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String?,
      permissions: permissions,
      isElectrician: json['is_electrician'] == true,
    );
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
