import 'package:flutter/material.dart';
import 'package:speech_to_text/speech_to_text.dart';

class VoiceOrderPrompt extends StatefulWidget {
  const VoiceOrderPrompt({
    super.key,
    required this.controller,
    this.enabled = true,
    this.minLines = 2,
    this.maxLines = 4,
    this.hintText = 'Type or speak what you want to add',
  });

  final TextEditingController controller;
  final bool enabled;
  final int minLines;
  final int maxLines;
  final String hintText;

  @override
  State<VoiceOrderPrompt> createState() => _VoiceOrderPromptState();
}

class _VoiceOrderPromptState extends State<VoiceOrderPrompt> {
  final SpeechToText _speech = SpeechToText();
  bool _available = false;
  bool _listening = false;
  bool _initializing = false;
  String? _error;

  @override
  void dispose() {
    if (_listening) {
      _speech.stop();
    }
    super.dispose();
  }

  Future<bool> _ensureReady() async {
    if (_available) return true;
    if (_initializing) return false;
    setState(() {
      _initializing = true;
      _error = null;
    });
    try {
      final ok = await _speech.initialize(
        onError: (e) {
          if (!mounted) return;
          setState(() {
            _listening = false;
            _error = e.errorMsg;
          });
        },
        onStatus: (status) {
          if (!mounted) return;
          if (status == 'done' || status == 'notListening') {
            setState(() => _listening = false);
          }
        },
      );
      if (!mounted) return false;
      setState(() {
        _available = ok;
        _initializing = false;
        if (!ok) {
          _error = 'Voice input is not available on this device.';
        }
      });
      return ok;
    } catch (e) {
      if (!mounted) return false;
      setState(() {
        _initializing = false;
        _error = e.toString();
      });
      return false;
    }
  }

  Future<void> _toggleListen() async {
    if (_listening) {
      await _speech.stop();
      if (mounted) setState(() => _listening = false);
      return;
    }

    final ready = await _ensureReady();
    if (!ready || !mounted) {
      if (_error != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_error!)));
      }
      return;
    }

    setState(() {
      _listening = true;
      _error = null;
    });

    final locales = await _speech.locales();
    final localeId = _preferredLocale(locales);

    await _speech.listen(
      onResult: (result) {
        widget.controller.text = result.recognizedWords;
        widget.controller.selection = TextSelection.collapsed(
          offset: widget.controller.text.length,
        );
        if (result.finalResult && mounted) {
          setState(() => _listening = false);
        }
      },
      listenOptions: SpeechListenOptions(
        listenFor: const Duration(seconds: 20),
        pauseFor: const Duration(seconds: 3),
        localeId: localeId,
        partialResults: true,
        cancelOnError: true,
        listenMode: ListenMode.dictation,
      ),
    );
  }

  String? _preferredLocale(List<LocaleName> locales) {
    const preferred = ['en_IN', 'en-IN', 'hi_IN', 'hi-IN', 'en_US', 'en-US'];
    for (final id in preferred) {
      for (final locale in locales) {
        if (locale.localeId == id ||
            locale.localeId.replaceAll('-', '_') == id.replaceAll('-', '_')) {
          return locale.localeId;
        }
      }
    }
    return locales.isNotEmpty ? locales.first.localeId : null;
  }

  @override
  Widget build(BuildContext context) {
    final listening = _listening;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: widget.controller,
          enabled: widget.enabled,
          minLines: widget.minLines,
          maxLines: widget.maxLines,
          decoration: InputDecoration(
            hintText: widget.hintText,
            border: const OutlineInputBorder(),
            suffixIcon: IconButton(
              tooltip: listening ? 'Stop listening' : 'Speak to add products',
              onPressed: widget.enabled && !_initializing ? _toggleListen : null,
              icon: Icon(
                listening ? Icons.mic : Icons.mic_none,
                color: listening ? Theme.of(context).colorScheme.error : null,
              ),
            ),
          ),
        ),
        if (listening)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              'Listening… tap the mic when you are done, then Search & add.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                  ),
            ),
          ),
      ],
    );
  }
}

Future<List<Map<String, dynamic>>> resolveAiSuggestItems(
  BuildContext context,
  List<Map<String, dynamic>> items,
) async {
  final resolved = <Map<String, dynamic>>[];
  for (final item in items) {
    final candidates = (item['candidates'] as List<dynamic>? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    if (item['needs_pick'] == true && candidates.length > 1) {
      if (!context.mounted) break;
      final picked = await showModalBottomSheet<Map<String, dynamic>>(
        context: context,
        showDragHandle: true,
        builder: (ctx) {
          return SafeArea(
            child: ListView(
              shrinkWrap: true,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                  child: Text(
                    'Which one did you mean?',
                    style: Theme.of(ctx).textTheme.titleMedium,
                  ),
                ),
                ...candidates.map((candidate) {
                  final name = (candidate['product_name'] ?? '').toString();
                  final brand = (candidate['brand'] ?? '').toString();
                  final variant = (candidate['variant_label'] ?? '').toString();
                  final qty = candidate['quantity'] ?? 1;
                  return ListTile(
                    title: Text(brand.isEmpty ? name : '$name ($brand)'),
                    subtitle: Text('Qty $qty • $variant'),
                    onTap: () => Navigator.pop(ctx, candidate),
                  );
                }),
                ListTile(
                  title: const Text('Skip this item'),
                  onTap: () => Navigator.pop(ctx),
                ),
              ],
            ),
          );
        },
      );
      if (picked != null) {
        resolved.add(picked);
      }
    } else {
      resolved.add(item);
    }
  }
  return resolved;
}

int? aiInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}
