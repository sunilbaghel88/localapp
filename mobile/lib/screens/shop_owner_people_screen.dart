import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../services/api_service.dart';

class ShopOwnerPeopleScreen extends StatefulWidget {
  const ShopOwnerPeopleScreen({super.key});

  @override
  State<ShopOwnerPeopleScreen> createState() => _ShopOwnerPeopleScreenState();
}

class _ShopOwnerPeopleScreenState extends State<ShopOwnerPeopleScreen>
    with SingleTickerProviderStateMixin {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  late final TabController _tabs;

  final Map<String, List<Map<String, dynamic>>> _users = {
    'customer': [],
    'partner': [],
  };
  final Map<String, bool> _loading = {'customer': true, 'partner': true};
  final Map<String, bool> _loadingMore = {'customer': false, 'partner': false};
  final Map<String, bool> _hasMore = {'customer': true, 'partner': true};
  final Map<String, int> _page = {'customer': 1, 'partner': 1};
  final Map<String, String?> _error = {'customer': null, 'partner': null};

  String get _kind => _tabs.index == 1 ? 'partner' : 'customer';

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _tabs.addListener(() {
      if (_tabs.indexIsChanging) return;
      if (_users[_kind]!.isEmpty && _error[_kind] == null) {
        _load();
      }
      setState(() {});
    });
    _load();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false, String? kind}) async {
    final selected = kind ?? _kind;
    if (!mounted) return;
    if (_loadingMore[selected] == true && append) return;
    if (append && _hasMore[selected] != true) return;

    setState(() {
      if (!append) {
        _loading[selected] = true;
        _error[selected] = null;
        _page[selected] = 1;
        _users[selected] = [];
        _hasMore[selected] = true;
      } else {
        _loadingMore[selected] = true;
      }
    });

    try {
      final data = await _api.getShopPeople(
        kind: selected,
        q: _searchController.text,
        page: _page[selected]!,
        perPage: 20,
      );
      final paginator = data['users'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['users']) as List<dynamic>?;
      final mapped = (list ?? []).map((e) => e as Map<String, dynamic>).toList();
      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;

      if (!mounted) return;
      setState(() {
        _users[selected] = append ? [..._users[selected]!, ...mapped] : mapped;
        _hasMore[selected] = currentPage < lastPage;
        _page[selected] = append ? currentPage + 1 : 2;
        _loading[selected] = false;
        _loadingMore[selected] = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error[selected] = e.toString();
        _loading[selected] = false;
        _loadingMore[selected] = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      _load(kind: 'customer');
      _load(kind: 'partner');
    });
  }

  Future<void> _openForm({String? kind, int? id}) async {
    final path = id == null
        ? '/owner/people/create?kind=${kind ?? _kind}'
        : '/owner/people/$id/edit';
    await context.push(path);
    if (mounted) {
      await _load(kind: 'customer');
      await _load(kind: 'partner');
    }
  }

  String _typeLabel(Map<String, dynamic> user) {
    final types = user['user_types'];
    if (types is! List || types.isEmpty) return 'Customer';
    return types
        .whereType<Map>()
        .map((e) => (e['name'] ?? '').toString())
        .where((name) => name.isNotEmpty)
        .join(', ');
  }

  String _shopLabel(Map<String, dynamic> user) {
    final shops = user['shops'];
    if (shops is! List || shops.isEmpty) return '';
    return shops
        .whereType<Map>()
        .map((e) => (e['name'] ?? '').toString())
        .where((name) => name.isNotEmpty)
        .join(', ');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Customers & Partners'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/home'),
        ),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Customers'),
            Tab(text: 'Partners'),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(kind: _kind),
        tooltip: _kind == 'partner' ? 'Add partner' : 'Add customer',
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: const InputDecoration(
                hintText: 'Search name or mobile',
                prefixIcon: Icon(Icons.search),
                border: OutlineInputBorder(),
                isDense: true,
              ),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _list('customer'),
                _list('partner'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _list(String kind) {
    if (_loading[kind] == true && _users[kind]!.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error[kind] != null && _users[kind]!.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error[kind]!),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => _load(kind: kind),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }
    if (_users[kind]!.isEmpty) {
      return Center(
        child: Text(
          kind == 'partner' ? 'No partners yet.' : 'No customers yet.',
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(kind: kind),
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.pixels >=
                  notification.metrics.maxScrollExtent - 80 &&
              _hasMore[kind] == true &&
              _loadingMore[kind] != true) {
            _load(append: true, kind: kind);
          }
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 88),
          itemCount: _users[kind]!.length + (_loadingMore[kind] == true ? 1 : 0),
          separatorBuilder: (_, _) => const Divider(height: 1),
          itemBuilder: (context, index) {
            if (index >= _users[kind]!.length) {
              return const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final user = _users[kind]![index];
            final phone = (user['phone'] ?? '').toString();
            final shops = _shopLabel(user);
            final active = user['is_active'] != false;
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(
                child: Text(
                  ((user['name'] ?? '?') as String).isNotEmpty
                      ? (user['name'] as String)[0].toUpperCase()
                      : '?',
                ),
              ),
              title: Text((user['name'] ?? '').toString()),
              subtitle: Text(
                [
                  if (phone.isNotEmpty) phone,
                  _typeLabel(user),
                  if (shops.isNotEmpty) shops,
                  if (!active) 'Inactive',
                ].join(' • '),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _openForm(id: user['id'] as int),
            );
          },
        ),
      ),
    );
  }
}
