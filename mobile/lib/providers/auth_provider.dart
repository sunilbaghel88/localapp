import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants.dart';
import '../models/user.dart';
import '../services/api_service.dart';

class AuthProvider with ChangeNotifier {
  final ApiService _api = ApiService();
  User? _user;
  bool _isLoading = true;
  String? _error;

  User? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get isLoading => _isLoading;
  String? get error => _error;

  AuthProvider() {
    _loadStoredUser();
  }

  Future<void> _loadStoredUser() async {
    _isLoading = true;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(tokenKey);
      if (token != null && token.isNotEmpty) {
        _user = await _api.getCurrentUser();
      } else {
        _user = null;
      }
    } catch (_) {
      _user = null;
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _error = null;
    _isLoading = true;
    notifyListeners();
    try {
      final data = await _api.login(email, password);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(tokenKey, data['token'] as String);
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      _isLoading = false;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? e.response?.data?['errors']?['email']?[0] ?? 'Login failed';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> requestEmailOtp(String email) async {
    _error = null;
    _isLoading = true;
    notifyListeners();
    try {
      await _api.requestLoginOtp(email);
      _isLoading = false;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? e.response?.data?['errors']?['email']?[0] ?? 'Failed to send OTP';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> loginWithOtp(String email, String otp) async {
    _error = null;
    _isLoading = true;
    notifyListeners();
    try {
      final data = await _api.loginWithOtp(email, otp);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(tokenKey, data['token'] as String);
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      _isLoading = false;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ??
          e.response?.data?['errors']?['otp']?[0] ??
          e.response?.data?['errors']?['email']?[0] ??
          'OTP login failed';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register(
    String name,
    String email,
    String password,
    String passwordConfirmation, {
    int? userTypeId,
  }) async {
    _error = null;
    _isLoading = true;
    notifyListeners();
    try {
      final data = await _api.register(
        name,
        email,
        password,
        passwordConfirmation,
        userTypeId: userTypeId,
      );
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(tokenKey, data['token'] as String);
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      _isLoading = false;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      final err = e.response?.data;
      if (err != null && err['errors'] != null) {
        final errors = err['errors'] as Map<String, dynamic>;
        final first = errors.values.first;
        _error = first is List ? (first.isNotEmpty ? first.first.toString() : 'Registration failed') : first.toString();
      } else {
        _error = err?['message'] ?? 'Registration failed';
      }
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } catch (_) {}
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(tokenKey);
    await prefs.remove(userKey);
    _user = null;
    _error = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
