import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/api_client.dart';
import '../providers/branding_provider.dart';

class AppBrandLogo extends StatelessWidget {
  const AppBrandLogo({
    super.key,
    this.height = 120,
    this.fallbackColor,
  });

  final double height;
  final Color? fallbackColor;

  @override
  Widget build(BuildContext context) {
    final branding = context.watch<BrandingProvider>().branding;
    final color = fallbackColor ?? Theme.of(context).colorScheme.primary;
    final rawLogoUrl = branding?.logoUrl;
    final logoUrl = (rawLogoUrl == null || rawLogoUrl.isEmpty)
        ? null
        : ApiClient.imageUrl(rawLogoUrl);

    final fallback = _FallbackLogo(
      color: color,
      appName: branding?.appName,
      tagline: branding?.tagline,
    );

    if (logoUrl == null) {
      return SizedBox(
        height: height,
        width: double.infinity,
        child: Image.asset(
          'assets/images/app_logo.png',
          height: height,
          width: double.infinity,
          fit: BoxFit.contain,
          alignment: Alignment.center,
          errorBuilder: (_, error, stackTrace) => fallback,
        ),
      );
    }

    return SizedBox(
      height: height,
      width: double.infinity,
      child: CachedNetworkImage(
        imageUrl: logoUrl,
        height: height,
        width: double.infinity,
        fit: BoxFit.contain,
        alignment: Alignment.center,
        placeholder: (_, url) => Center(
          child: SizedBox(
            width: 28,
            height: 28,
            child: CircularProgressIndicator(strokeWidth: 2, color: color),
          ),
        ),
        errorWidget: (_, url, error) => Image.asset(
          'assets/images/app_logo.png',
          height: height,
          fit: BoxFit.contain,
          errorBuilder: (_, assetError, stackTrace) => fallback,
        ),
      ),
    );
  }
}

class _FallbackLogo extends StatelessWidget {
  const _FallbackLogo({
    required this.color,
    this.appName,
    this.tagline,
  });

  final Color color;
  final String? appName;
  final String? tagline;

  @override
  Widget build(BuildContext context) {
    final name = (appName == null || appName!.trim().isEmpty) ? 'LOCALAPP' : appName!.trim().toUpperCase();
    final line = (tagline == null || tagline!.trim().isEmpty) ? 'LOYALTY PROGRAM' : tagline!.trim().toUpperCase();

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.workspace_premium, color: color, size: 28),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                name,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: color,
                  fontSize: 28,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1.2,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          line,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.black87,
            fontSize: 12,
            letterSpacing: 3,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
