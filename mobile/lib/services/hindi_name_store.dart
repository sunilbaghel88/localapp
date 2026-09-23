import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Display-only English → Hindi names.
///
/// Results stay in memory and a local device cache. They are never written
/// to the product record or sent back to the API.
class HindiNameStore extends ChangeNotifier {
  HindiNameStore() {
    _ready = _load();
  }

  static const _prefsKey = 'product_hindi_name_cache_v1';
  static const _maxCached = 500;
  static const _placeholders = {'product', 'item'};

  final Dio _dio = Dio(
    BaseOptions(
      connectTimeout: const Duration(seconds: 8),
      receiveTimeout: const Duration(seconds: 8),
      headers: const {'User-Agent': 'Mozilla/5.0'},
      responseType: ResponseType.plain,
    ),
  );

  final Map<String, String> _cache = {};
  final List<String> _order = [];
  final List<String> _queue = [];
  final Set<String> _queued = {};

  late final Future<void> _ready;
  Timer? _timer;
  bool _flushing = false;
  bool _flushAgain = false;
  bool _disposed = false;

  /// Hindi for [name], '' when there is nothing extra to show, or null
  /// while it has not been resolved yet.
  String? peek(String name) {
    final key = _normalize(name);
    if (key.isEmpty) return '';
    return _cache[key];
  }

  void ensure(String name) {
    final key = _normalize(name);
    if (key.isEmpty || _cache.containsKey(key) || _queued.contains(key)) {
      return;
    }
    if (_placeholders.contains(key.toLowerCase())) {
      _cache[key] = '';
      return;
    }
    _queued.add(key);
    _queue.add(key);
    _timer?.cancel();
    _timer = Timer(const Duration(milliseconds: 80), _flush);
  }

  Future<void> _load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_prefsKey);
      if (raw == null || raw.isEmpty) return;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return;
      decoded.forEach((key, value) {
        if (key is String && value is String && value.trim().isNotEmpty) {
          _cache[key] = value;
          _order.add(key);
        }
      });
    } catch (_) {}
    if (!_disposed) notifyListeners();
  }

  Future<void> _persist() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final saved = <String, String>{};
      for (final key in _order) {
        final value = _cache[key];
        if (value != null && value.isNotEmpty) saved[key] = value;
      }
      await prefs.setString(_prefsKey, jsonEncode(saved));
    } catch (_) {}
  }

  Future<void> _flush() async {
    if (_flushing) {
      _flushAgain = true;
      return;
    }
    _flushing = true;
    try {
      await _ready;
      while (_queue.isNotEmpty) {
        final batch = <String>[];
        var chars = 0;
        while (_queue.isNotEmpty && batch.length < 10 && chars < 1200) {
          final next = _queue.removeAt(0);
          _queued.remove(next);
          if (_cache.containsKey(next)) continue;
          batch.add(next);
          chars += next.length + 1;
        }
        if (batch.isEmpty) continue;
        await _translateBatch(batch);
      }
    } finally {
      _flushing = false;
      if (_flushAgain && !_disposed) {
        _flushAgain = false;
        await _flush();
      }
    }
  }

  Future<void> _translateBatch(List<String> names) async {
    try {
      final translated = names.length == 1
          ? [await _translateOne(names.first)]
          : await _translateMany(names);
      if (translated == null || translated.length != names.length) {
        for (final name in names) {
          final single = await _translateOne(name);
          _remember(name, single ?? '');
        }
      } else {
        for (var i = 0; i < names.length; i++) {
          _remember(names[i], translated[i] ?? '');
        }
      }
    } catch (_) {
      for (final name in names) {
        _cache[name] = '';
      }
    }
    await _persist();
    if (!_disposed) notifyListeners();
  }

  Future<List<String?>?> _translateMany(List<String> names) async {
    final slotsByIndex = <List<String>>[];
    final protected = <String>[];
    for (final name in names) {
      final slots = <String>[];
      protected.add(_protect(name, slots));
      slotsByIndex.add(slots);
    }
    final parts = await _request(protected.join('\n'));
    if (parts == null || parts.length != names.length) return null;
    return [
      for (var i = 0; i < parts.length; i++)
        _usable(_restore(parts[i], slotsByIndex[i]), names[i]),
    ];
  }

  Future<String?> _translateOne(String name) async {
    final slots = <String>[];
    final protected = _protect(name, slots);
    final parts = await _request(protected);
    if (parts == null || parts.isEmpty) return null;
    return _usable(_restore(parts.first, slots), name);
  }

  Future<List<String>?> _request(String query) async {
    final response = await _dio.get<String>(
      'https://translate.googleapis.com/translate_a/single',
      queryParameters: {
        'client': 'gtx',
        'sl': 'en',
        'tl': 'hi',
        'dt': 't',
        'q': query,
      },
    );
    return _extract(response.data);
  }

  List<String>? _extract(dynamic data) {
    if (data is String) {
      try {
        data = jsonDecode(data);
      } catch (_) {
        return null;
      }
    }
    if (data is! List || data.isEmpty || data.first is! List) return null;
    final parts = <String>[];
    for (final part in data.first as List) {
      if (part is List && part.isNotEmpty && part.first != null) {
        parts.add(part.first.toString().trim());
      }
    }
    return parts;
  }

  void _remember(String key, String value) {
    _cache[key] = value;
    if (value.isEmpty) return;
    _order.remove(key);
    _order.add(key);
    while (_order.length > _maxCached) {
      final oldest = _order.removeAt(0);
      _cache.remove(oldest);
    }
  }

  String? _usable(String hindi, String english) {
    final cleaned = hindi.trim();
    if (cleaned.isEmpty) return '';
    if (!_devanagari.hasMatch(cleaned)) return '';
    if (cleaned.toLowerCase() == english.toLowerCase()) return '';
    return cleaned;
  }

  static String _normalize(String name) =>
      name.trim().replaceAll(RegExp(r'\s+'), ' ');

  static final _token = RegExp(r'\d+(?:\.\d+)?[A-Za-z]{0,4}');
  static final _placeholder = RegExp(r'\[\[\s*([0-9०-९]+)\s*\]\]');
  static final _devanagari = RegExp(r'[\u0900-\u097F]');

  static String _protect(String input, List<String> slots) {
    return input.replaceAllMapped(_token, (match) {
      final id = slots.length;
      slots.add(match.group(0)!);
      return '[[$id]]';
    });
  }

  static String _restore(String translated, List<String> slots) {
    return translated.replaceAllMapped(_placeholder, (match) {
      final raw = match.group(1)!;
      final index = int.tryParse(
        raw.replaceAllMapped(RegExp(r'[०-९]'), (digit) {
          const map = {
            '०': '0',
            '१': '1',
            '२': '2',
            '३': '3',
            '४': '4',
            '५': '5',
            '६': '6',
            '७': '7',
            '८': '8',
            '९': '9',
          };
          return map[digit.group(0)] ?? digit.group(0)!;
        }),
      );
      if (index == null || index < 0 || index >= slots.length) {
        return match.group(0)!;
      }
      return slots[index];
    });
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    _dio.close(force: true);
    super.dispose();
  }
}
