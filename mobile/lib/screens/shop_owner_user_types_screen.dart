import 'dart:async';

import 'package:flutter/material.dart';
import '../services/api_service.dart';

class ShopOwnerUserTypesScreen extends StatefulWidget {
  const ShopOwnerUserTypesScreen({super.key});

  @override
  State<ShopOwnerUserTypesScreen> createState() =>
      _ShopOwnerUserTypesScreenState();
}

class _ShopOwnerUserTypesScreenState extends State<ShopOwnerUserTypesScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;

  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _userTypes = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  bool _hasMore = true;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final types = await _api.getUserTypes();
      if (mounted) {
        setState(() => _userTypes = types);
      }
    } catch (_) {
      // Types load is retried when opening the assign sheet.
    }
    await _load();
  }

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    if (_loadingMore && append) return;
    if (append && !_hasMore) return;

    setState(() {
      if (!append) {
        _loading = true;
        _error = null;
        _page = 1;
        _users = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getAssignableUsers(
        q: _searchController.text,
        page: _page,
        perPage: 20,
      );
      final paginator = data['users'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['users']) as List<dynamic>?;
      final mapped = (list ?? []).map((e) => e as Map<String, dynamic>).toList();
      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;

      if (!mounted) return;
      setState(() {
        _users = append ? [..._users, ...mapped] : mapped;
        _hasMore = currentPage < lastPage;
        if (append) {
          _page++;
        } else {
          _page = 2;
        }
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      _load();
    });
  }

  List<int> _idsFrom(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => e['id'])
        .whereType<int>()
        .toList();
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

  Future<void> _openAssignSheet(Map<String, dynamic> user) async {
    if (_userTypes.isEmpty) {
      try {
        final types = await _api.getUserTypes();
        if (mounted) setState(() => _userTypes = types);
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not load user types: $e')),
        );
        return;
      }
    }

    final selected = _idsFrom(user['user_types']).toSet();
    final name = (user['name'] ?? '').toString();

    if (!mounted) return;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                bottom: MediaQuery.viewInsetsOf(context).bottom + 24,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    name.isEmpty ? 'Assign user types' : name,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Leave empty to treat this user as a Customer. Multiple types are allowed.',
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  ..._userTypes.map((type) {
                    final id = type['id'] as int?;
                    if (id == null) return const SizedBox.shrink();
                    final label = (type['name'] ?? '').toString();
                    return CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: selected.contains(id),
                      title: Text(label),
                      onChanged: (checked) {
                        setSheetState(() {
                          if (checked == true) {
                            selected.add(id);
                          } else {
                            selected.remove(id);
                          }
                        });
                      },
                    );
                  }),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: () async {
                      try {
                        await _api.updateUserTypes(
                          userId: user['id'] as int,
                          userTypeIds: selected.toList(),
                        );
                        if (context.mounted) Navigator.of(context).pop(true);
                      } catch (e) {
                        if (!context.mounted) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Could not save types: $e')),
                        );
                      }
                    },
                    child: const Text('Save types'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    if (saved == true) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Assign User Types'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: const InputDecoration(
                hintText: 'Search name, email or phone',
                prefixIcon: Icon(Icons.search),
                border: OutlineInputBorder(),
                isDense: true,
              ),
            ),
          ),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _body() {
    if (_loading && _users.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _users.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }
    if (_users.isEmpty) {
      return const Center(child: Text('No users found.'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.pixels >=
                  notification.metrics.maxScrollExtent - 80 &&
              _hasMore &&
              !_loadingMore) {
            _load(append: true);
          }
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          itemCount: _users.length + (_loadingMore ? 1 : 0),
          separatorBuilder: (_, _) => const Divider(height: 1),
          itemBuilder: (context, index) {
            if (index >= _users.length) {
              return const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final user = _users[index];
            final email = (user['email'] ?? '').toString();
            final phone = (user['phone'] ?? '').toString();
            final subtitle = [
              if (email.isNotEmpty) email,
              if (phone.isNotEmpty) phone,
            ].join(' · ');
            return ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text((user['name'] ?? '').toString()),
              subtitle: Text(
                [
                  if (subtitle.isNotEmpty) subtitle,
                  _typeLabel(user),
                ].join('\n'),
              ),
              isThreeLine: subtitle.isNotEmpty,
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _openAssignSheet(user),
            );
          },
        ),
      ),
    );
  }
}
