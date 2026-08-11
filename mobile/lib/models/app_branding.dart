class AppBranding {
  final String? appName;
  final String? tagline;
  final String? logoPath;
  final String? logoUrl;

  const AppBranding({
    this.appName,
    this.tagline,
    this.logoPath,
    this.logoUrl,
  });

  factory AppBranding.fromJson(Map<String, dynamic> json) {
    return AppBranding(
      appName: json['app_name'] as String?,
      tagline: json['tagline'] as String?,
      logoPath: json['logo_path'] as String?,
      logoUrl: json['logo_url'] as String?,
    );
  }
}
