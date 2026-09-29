import 'package:flutter/material.dart';

/// Customer-facing e-shop price. Hidden prices show a placeholder.
class EshopPriceText extends StatelessWidget {
  static const placeholder = 'At the time of order';

  const EshopPriceText({
    super.key,
    required this.visible,
    required this.price,
    this.compareAt,
    this.style,
    this.compareStyle,
  });

  final bool visible;
  final String price;
  final String? compareAt;
  final TextStyle? style;
  final TextStyle? compareStyle;

  @override
  Widget build(BuildContext context) {
    final resolvedStyle = style ?? const TextStyle(fontWeight: FontWeight.bold);
    if (!visible) {
      return Text(
        placeholder,
        style: resolvedStyle,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      );
    }
    if (compareAt == null) {
      return Text(price, style: resolvedStyle);
    }
    return Row(
      children: [
        Text(price, style: resolvedStyle),
        const SizedBox(width: 6),
        Text(compareAt!, style: compareStyle),
      ],
    );
  }
}
