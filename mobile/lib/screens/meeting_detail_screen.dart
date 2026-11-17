import 'dart:typed_data';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../models/meeting.dart';
import '../services/api_service.dart';

class MeetingDetailScreen extends StatefulWidget {
  final int meetingId;
  final bool isOwner;

  const MeetingDetailScreen({
    super.key,
    required this.meetingId,
    required this.isOwner,
  });

  @override
  State<MeetingDetailScreen> createState() => _MeetingDetailScreenState();
}

class _MeetingDetailScreenState extends State<MeetingDetailScreen> {
  final ApiService _api = ApiService();
  Meeting? _meeting;
  bool _loading = true;
  bool _uploading = false;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final meeting = await _api.getMeeting(widget.meetingId, isOwner: widget.isOwner);
      if (!mounted) return;
      setState(() {
        _meeting = meeting;
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

  Future<void> _uploadPhotos() async {
    final source = await showModalBottomSheet<ImageSource?>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (source == null || !mounted) return;

    final picker = ImagePicker();
    final files = <XFile>[];
    if (source == ImageSource.gallery) {
      files.addAll(await picker.pickMultiImage(imageQuality: 85, maxWidth: 2048));
    } else {
      final shot = await picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
        maxWidth: 2048,
      );
      if (shot != null) files.add(shot);
    }
    if (files.isEmpty || !mounted) return;

    setState(() => _uploading = true);
    try {
      final photos = <({Uint8List bytes, String filename})>[];
      for (final file in files.take(10)) {
        var filename = file.name.isNotEmpty ? file.name : 'meeting.jpg';
        final lower = filename.toLowerCase();
        if (!lower.endsWith('.jpg') &&
            !lower.endsWith('.jpeg') &&
            !lower.endsWith('.png') &&
            !lower.endsWith('.webp')) {
          filename = '$filename.jpg';
        }
        photos.add((bytes: await file.readAsBytes(), filename: filename));
      }
      final meeting = await _api.uploadMeetingPhotos(
        meetingId: widget.meetingId,
        photos: photos,
      );
      if (!mounted) return;
      setState(() {
        _meeting = meeting;
        _uploading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Photos uploaded.')),
      );
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() => _uploading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_apiError(e))));
    } catch (e) {
      if (!mounted) return;
      setState(() => _uploading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  String _apiError(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] != null) return data['message'].toString();
    return e.message ?? 'Upload failed';
  }

  @override
  Widget build(BuildContext context) {
    final meeting = _meeting;
    final dateFormat = DateFormat('dd MMM yyyy, hh:mm a');

    return Scaffold(
      appBar: AppBar(title: const Text('Meeting')),
      body: _loading && meeting == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && meeting == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : meeting == null
                  ? const Center(child: Text('Not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Text(
                          meeting.title,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 20),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          meeting.startsAt == null
                              ? '—'
                              : dateFormat.format(meeting.startsAt!.toLocal()),
                        ),
                        if ((meeting.shopName ?? '').isNotEmpty) Text('Shop: ${meeting.shopName}'),
                        if ((meeting.location ?? '').trim().isNotEmpty)
                          Text('Location: ${meeting.location}'),
                        const SizedBox(height: 12),
                        Text(
                          (meeting.details ?? '').trim().isEmpty ? '—' : meeting.details!.trim(),
                        ),
                        const SizedBox(height: 20),
                        Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'Photos',
                                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                              ),
                            ),
                            if (widget.isOwner)
                              FilledButton.tonalIcon(
                                onPressed: _uploading ? null : _uploadPhotos,
                                icon: _uploading
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : const Icon(Icons.add_a_photo_outlined),
                                label: const Text('Upload'),
                              ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (meeting.photos.isEmpty)
                          const Text('No photos yet.')
                        else
                          GridView.builder(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: meeting.photos.length,
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              mainAxisSpacing: 8,
                              crossAxisSpacing: 8,
                            ),
                            itemBuilder: (context, i) {
                              final url = meeting.photos[i].fullUrl;
                              if (url == null) {
                                return const ColoredBox(
                                  color: Color(0xFFE0E0E0),
                                  child: Icon(Icons.broken_image_outlined),
                                );
                              }
                              return ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: CachedNetworkImage(
                                  imageUrl: url,
                                  fit: BoxFit.cover,
                                  placeholder: (_, _) => const Center(
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  ),
                                  errorWidget: (_, _, _) => const Icon(Icons.broken_image_outlined),
                                ),
                              );
                            },
                          ),
                      ],
                    ),
    );
  }
}
