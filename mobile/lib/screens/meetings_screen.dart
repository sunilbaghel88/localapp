import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/meeting.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class MeetingsScreen extends StatefulWidget {
  final bool isOwner;

  const MeetingsScreen({super.key, required this.isOwner});

  @override
  State<MeetingsScreen> createState() => _MeetingsScreenState();
}

class _MeetingsScreenState extends State<MeetingsScreen> {
  final ApiService _api = ApiService();
  List<Meeting> _meetings = [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;

  Future<void> _load({bool append = false}) async {
    if (_loadingMore && append) return;
    if (append && !_hasMore) return;

    setState(() {
      if (!append) {
        _loading = true;
        _error = null;
        _page = 1;
        _meetings = [];
        _hasMore = true;
      } else {
        _loadingMore = true;
      }
    });

    try {
      final data = await _api.getMeetings(
        isOwner: widget.isOwner,
        page: _page,
        perPage: 15,
      );
      final paginator = data['meetings'] as Map<String, dynamic>?;
      final list = (paginator?['data'] ?? data['meetings']) as List<dynamic>?;
      final currentPage = (paginator?['current_page'] ?? 1) as int;
      final lastPage = (paginator?['last_page'] ?? 1) as int;
      final mapped = (list ?? [])
          .whereType<Map>()
          .map((e) => Meeting.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      if (!mounted) return;
      setState(() {
        _meetings = append ? [..._meetings, ...mapped] : mapped;
        _hasMore = currentPage < lastPage;
        _page = append ? _page + 1 : 2;
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _detailPath(int id) => widget.isOwner
      ? '/owner/meetings/$id'
      : '/partner/meetings/$id';

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('dd MMM yyyy, hh:mm a');
    final canCreate = widget.isOwner &&
        (context.watch<AuthProvider>().user?.permissions?.contains('create_order') ?? false);

    late final Widget body;
    if (_loading && _meetings.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _meetings.isEmpty) {
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
        onRefresh: () => _load(),
        child: _meetings.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('No meetings yet')),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _meetings.length + (_hasMore ? 1 : 0),
                itemBuilder: (context, i) {
                  if (i == _meetings.length) {
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      if (mounted) _load(append: true);
                    });
                    return const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }
                  final meeting = _meetings[i];
                  final details = (meeting.details ?? '').trim();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: InkWell(
                      onTap: () async {
                        await context.push(_detailPath(meeting.id));
                        if (mounted) _load();
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              meeting.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              meeting.startsAt == null
                                  ? '—'
                                  : dateFormat.format(meeting.startsAt!.toLocal()),
                            ),
                            if ((meeting.shopName ?? '').isNotEmpty)
                              Text('Shop: ${meeting.shopName}'),
                            if (details.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                details,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                            const SizedBox(height: 6),
                            Text('${meeting.photos.length} photo${meeting.photos.length == 1 ? '' : 's'}'),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Meetings')),
      body: body,
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () async {
                final created = await context.push<bool>('/owner/meetings/create');
                if (created == true && mounted) _load();
              },
              icon: const Icon(Icons.add),
              label: const Text('Add meeting'),
            )
          : null,
    );
  }
}
