import 'package:flutter/foundation.dart';
import '../models/app_branding.dart';
import '../services/api_service.dart';

class BrandingProvider with ChangeNotifier {
  final ApiService _api = ApiService();

  AppBranding? _branding;
  bool _isLoading = false;
  bool _loaded = false;

  AppBranding? get branding => _branding;
  bool get isLoading => _isLoading;
  bool get hasLogo => (_branding?.logoUrl ?? '').isNotEmpty;

  BrandingProvider() {
    load();
  }

  Future<void> load({bool force = false}) async {
    if (_isLoading) return;
    if (_loaded && !force) return;

    _isLoading = true;
    notifyListeners();
    try {
      _branding = await _api.getAppBranding();
      _loaded = true;
    } catch (_) {
      // Keep sample/local fallback on the screens.
      if (force) {
        _branding = null;
      }
    }
    _isLoading = false;
    notifyListeners();
  }
}
