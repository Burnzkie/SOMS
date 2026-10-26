// lib/widgets/tile_grid.dart
import 'package:flutter/material.dart';

/// Lays tiles out [columns] per row. Each tile takes the height of its own
/// content, so nothing is measured or fixed in advance and nothing can overflow.
///
/// Replaces GridView.count + childAspectRatio on the dashboards: a fixed
/// aspect ratio fixes each tile's height, so a larger system font size or
/// display size pushed the content past it ("BOTTOM OVERFLOWED BY n PIXELS").
/// (IntrinsicHeight was tried first, but it measures text slightly differently
/// from how it's drawn, which could still leave 1px of overflow.)
///
/// Tiles in a row end up the same height because the dashboards' tiles all use
/// single-line labels; if a tile ever needs a multi-line label, give it a
/// minimum height instead of relying on row stretching.
class TileGrid extends StatelessWidget {
  const TileGrid(
      {super.key,
      required this.columns,
      required this.children,
      this.spacing = 10});

  final int columns;
  final List<Widget> children;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];

    for (var start = 0; start < children.length; start += columns) {
      final end =
          start + columns < children.length ? start + columns : children.length;
      final slice = children.sublist(start, end);

      rows.add(
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 0; i < columns; i++) ...[
              if (i > 0) SizedBox(width: spacing),
              // An incomplete last row keeps the same column width via an empty slot.
              Expanded(
                  child: i < slice.length ? slice[i] : const SizedBox.shrink()),
            ],
          ],
        ),
      );

      if (end < children.length) rows.add(SizedBox(height: spacing));
    }

    return Column(children: rows);
  }
}
