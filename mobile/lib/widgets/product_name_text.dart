import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/hindi_name_store.dart';

/// English product name with its Hindi name underneath.
///
/// Hindi is resolved on the device and is not part of the saved product.
class ProductNameText extends StatelessWidget {
  const ProductNameText(
    this.name, {
    super.key,
    this.style,
    this.hindiStyle,
    this.maxLines = 2,
    this.hindiMaxLines = 2,
    this.overflow = TextOverflow.ellipsis,
    this.textAlign,
    this.showEnglish = true,
    this.englishUpperCase = false,
  });

  final String name;
  final TextStyle? style;
  final TextStyle? hindiStyle;
  final int maxLines;
  final int hindiMaxLines;
  final TextOverflow overflow;
  final TextAlign? textAlign;
  final bool showEnglish;
  final bool englishUpperCase;

  @override
  Widget build(BuildContext context) {
    final trimmed = name.trim();
    if (trimmed.isEmpty) {
      return showEnglish
          ? Text('', style: style, textAlign: textAlign)
          : const SizedBox.shrink();
    }

    final store = context.watch<HindiNameStore>();
    final hindi = store.peek(trimmed);
    if (hindi == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        store.ensure(trimmed);
      });
    }

    final hindiText = (hindi != null && hindi.isNotEmpty) ? hindi : null;
    final resolvedHindiStyle = hindiStyle ??
        (style ?? DefaultTextStyle.of(context).style).copyWith(
          fontSize: ((style?.fontSize) ??
                  DefaultTextStyle.of(context).style.fontSize ??
                  14) *
              0.86,
          fontWeight: FontWeight.w500,
          height: 1.25,
          color: style?.color?.withValues(alpha: 0.85) ??
              Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
        );

    final english = englishUpperCase ? trimmed.toUpperCase() : trimmed;
    final align = textAlign == TextAlign.center
        ? CrossAxisAlignment.center
        : textAlign == TextAlign.right || textAlign == TextAlign.end
            ? CrossAxisAlignment.end
            : CrossAxisAlignment.start;

    if (!showEnglish) {
      if (hindiText == null) return const SizedBox.shrink();
      return Text(
        hindiText,
        style: resolvedHindiStyle,
        maxLines: hindiMaxLines,
        overflow: overflow,
        textAlign: textAlign,
      );
    }

    return Column(
      crossAxisAlignment: align,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          english,
          style: style,
          maxLines: maxLines,
          overflow: overflow,
          textAlign: textAlign,
        ),
        if (hindiText != null)
          Text(
            hindiText,
            style: resolvedHindiStyle,
            maxLines: hindiMaxLines,
            overflow: overflow,
            textAlign: textAlign,
          ),
      ],
    );
  }
}

/// Hindi preview for a product name the user is typing. Not saved.
class LiveProductHindiName extends StatefulWidget {
  const LiveProductHindiName({super.key, required this.controller});

  final TextEditingController controller;

  @override
  State<LiveProductHindiName> createState() => _LiveProductHindiNameState();
}

class _LiveProductHindiNameState extends State<LiveProductHindiName> {
  Timer? _debounce;
  String _shown = '';

  @override
  void initState() {
    super.initState();
    _shown = widget.controller.text.trim();
    widget.controller.addListener(_onChange);
  }

  @override
  void didUpdateWidget(covariant LiveProductHindiName oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_onChange);
      widget.controller.addListener(_onChange);
      _shown = widget.controller.text.trim();
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    widget.controller.removeListener(_onChange);
    super.dispose();
  }

  void _onChange() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) return;
      setState(() => _shown = widget.controller.text.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_shown.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: ProductNameText(
        _shown,
        showEnglish: false,
        hindiStyle: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
      ),
    );
  }
}
