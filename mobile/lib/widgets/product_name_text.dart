import 'package:flutter/material.dart';

/// English product name with the saved Hindi name underneath.
class ProductNameText extends StatelessWidget {
  const ProductNameText(
    this.name, {
    super.key,
    this.hindi,
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
  final String? hindi;
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
    final hindiText = _visibleHindi(hindi, trimmed);
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

    if (trimmed.isEmpty && hindiText == null) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: align,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (trimmed.isNotEmpty)
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

  static String? _visibleHindi(String? hindi, String english) {
    final value = hindi?.trim() ?? '';
    if (value.isEmpty || value.toLowerCase() == english.toLowerCase()) {
      return null;
    }
    return value;
  }
}
