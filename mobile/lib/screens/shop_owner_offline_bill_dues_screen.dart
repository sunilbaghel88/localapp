import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/offline_bill.dart';
import '../services/api_service.dart';

class ShopOwnerOfflineBillDuesScreen extends StatefulWidget {
  const ShopOwnerOfflineBillDuesScreen({super.key});

  @override
  State<ShopOwnerOfflineBillDuesScreen> createState() =>
      _ShopOwnerOfflineBillDuesScreenState();
}

class _ShopOwnerOfflineBillDuesScreenState
    extends State<ShopOwnerOfflineBillDuesScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  List<OfflineBillDue> _dues = [];
  bool _loading = true;
  bool _sendingAll = false;
  String? _sendingKey;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final dues = await _api.getOfflineBillDues(q: _searchController.text);
      if (!mounted) return;
      setState(() {
        _dues = dues;
        _loading = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _apiError(e);
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String _) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), _load);
  }

  Future<void> _sendOne(OfflineBillDue due) async {
    final name = due.customerName ?? 'this customer';
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Send reminder SMS?'),
        content: Text(
          'Send a dues reminder to $name for ${money.format(due.balance)}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Send SMS'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _sendingKey = due.key);
    try {
      final result = await _api.remindOfflineBillDues(
        shopId: due.shopId,
        customerId: due.customerId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_resultMessage(result, fallback: 'Reminder SMS sent.'))),
      );
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_apiError(e))));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _sendingKey = null);
    }
  }

  Future<void> _sendAll() async {
    final withMobile = _dues.where((d) => d.hasMobile).length;
    if (withMobile == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No customers with a mobile number to remind.')),
      );
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Send all reminder SMS?'),
        content: Text(
          'Send a dues reminder to $withMobile customer${withMobile == 1 ? '' : 's'} with pending dues?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Send all'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _sendingAll = true);
    try {
      final result = await _api.remindOfflineBillDues(q: _searchController.text);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_resultMessage(result, fallback: 'Reminder SMS sent.'))),
      );
    } on DioException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_apiError(e))));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _sendingAll = false);
    }
  }

  String _resultMessage(Map<String, dynamic> result, {required String fallback}) {
    final message = result['message']?.toString().trim();
    if (message != null && message.isNotEmpty) return message;
    final sent = result['sent'];
    if (sent is num) return 'Sent ${sent.toInt()} reminder SMS.';
    return fallback;
  }

  String _apiError(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errs = data['errors'] as Map;
      for (final v in errs.values) {
        if (v is List && v.isNotEmpty) return v.first.toString();
        if (v != null) return v.toString();
      }
      if (data['message'] != null) return data['message'].toString();
    }
    if (data is Map && data['message'] != null) return data['message'].toString();
    return e.message ?? 'Request failed';
  }

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
    final busy = _sendingAll || _sendingKey != null;

    late final Widget body;
    if (_loading && _dues.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _dues.isEmpty) {
      body = Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: _load,
        child: _dues.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 120),
                  Center(
                    child: Text(
                      _searchController.text.trim().isEmpty
                          ? 'No customers with pending dues'
                          : 'No matching customers with pending dues',
                    ),
                  ),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                itemCount: _dues.length,
                itemBuilder: (context, i) {
                  final due = _dues[i];
                  final name = due.customerName ?? 'Customer #${due.customerId}';
                  final sending = _sendingKey == due.key;
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            name,
                            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                          ),
                          const SizedBox(height: 8),
                          Text('Due: ${money.format(due.balance)}'),
                          if ((due.shopName ?? '').isNotEmpty) Text('Shop: ${due.shopName}'),
                          Text(
                            (due.customerPhone ?? '').isNotEmpty
                                ? 'Mobile: ${due.customerPhone}'
                                : 'No mobile number',
                          ),
                          const SizedBox(height: 12),
                          Align(
                            alignment: Alignment.centerRight,
                            child: FilledButton.tonalIcon(
                              onPressed: busy || !due.hasMobile ? null : () => _sendOne(due),
                              icon: sending
                                  ? const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Icon(Icons.sms_outlined),
                              label: Text(due.hasMobile ? 'Send reminder' : 'No mobile'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pending dues'),
        actions: [
          TextButton(
            onPressed: busy || _dues.isEmpty ? null : _sendAll,
            child: _sendingAll
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Send all'),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              onSubmitted: (_) {
                _searchDebounce?.cancel();
                _load();
              },
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search by customer name or phone',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                isDense: true,
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {});
                          _searchDebounce?.cancel();
                          _load();
                        },
                      ),
              ),
            ),
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}
