import 'dart:typed_data';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../core/api_client.dart';
import '../models/brand.dart';
import '../models/category.dart';
import '../models/product_image.dart';
import '../models/product_variant.dart';
import '../models/shop.dart';
import '../services/api_service.dart';

class ShopOwnerProductFormScreen extends StatefulWidget {
  final int? productId;

  const ShopOwnerProductFormScreen({super.key, required this.productId});

  @override
  State<ShopOwnerProductFormScreen> createState() => _ShopOwnerProductFormScreenState();
}

class _ShopOwnerProductFormScreenState extends State<ShopOwnerProductFormScreen> {
  final ApiService _api = ApiService();

  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _brandNameController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String? _error;

  List<Shop> _shops = [];
  List<Category> _categories = [];
  List<Brand> _brands = [];

  int? _selectedShopId;
  int? _selectedParentCategoryId;
  int? _selectedCategoryId;
  int? _selectedBrandId;
  String _status = 'draft';

  List<Category> get _subcategories {
    final parentId = _selectedParentCategoryId;
    if (parentId == null) return const [];
    final parent = _categories.where((c) => c.id == parentId).firstOrNull;
    return parent?.children ?? const [];
  }

  final List<_VariantRow> _variants = [];
  final List<_ImageRow> _images = [];

  @override
  void initState() {
    super.initState();
    _init();
  }

  void _resetVariants() {
    for (final v in _variants) {
      v.dispose();
    }
    _variants.clear();
  }

  void _resetImages() {
    for (final img in _images) {
      img.dispose();
    }
    _images.clear();
  }

  Future<void> _init() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      // Start with empty (will be replaced by load below).
      _resetVariants();
      _resetImages();

      final shops = await _api.getMyShops();
      final categories = await _api.getShopCategories();
      final brands = await _api.getShopBrands();

      if (!mounted) return;
      setState(() {
        _shops = shops;
        _categories = categories;
        _brands = brands;

        _selectedShopId = shops.isNotEmpty ? shops.first.id : null;
        _selectedParentCategoryId = categories.isNotEmpty ? categories.first.id : null;
        _selectedCategoryId = categories.isNotEmpty && categories.first.children.isNotEmpty
            ? categories.first.children.first.id
            : null;
        _loading = false;
      });

      if (widget.productId != null) {
        await _loadProduct(widget.productId!);
      } else {
        // Create mode: at least 1 variant row.
        _variants.add(_VariantRow.empty());
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _loadProduct(int id) async {
    final p = await _api.getShopProduct(id);
    if (!mounted) return;

    setState(() {
      _nameController.text = p.name;
      _descriptionController.text = p.description ?? '';
      _status = p.status;
      _selectedShopId = p.shopId ?? _selectedShopId;
      _selectedCategoryId = p.categoryId;
      _selectedBrandId = p.brandId;

      if (p.brandId == null && (p.brand ?? '').trim().isNotEmpty) {
        _brandNameController.text = p.brand!;
      }

      if (_categories.isNotEmpty && p.categoryId != null) {
        final parent = _categories.where((parent) => parent.children.any((c) => c.id == p.categoryId)).firstOrNull;
        _selectedParentCategoryId = parent?.id;
      }

      _resetVariants();
      _resetImages();

      for (final v in p.variants) {
        _variants.add(_VariantRow.fromVariant(v));
      }
      if (_variants.isEmpty) _variants.add(_VariantRow.empty());

      for (final img in p.images) {
        _images.add(_ImageRow.fromImage(img));
      }
      _loading = false;
    });
  }

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    final desc = _descriptionController.text.trim();
    final brandName = _brandNameController.text.trim();

    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Product name is required')));
      return;
    }
    if (_selectedShopId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Shop is required')));
      return;
    }
    if (_selectedCategoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Category is required')));
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      if (_variants.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Add at least one variant')));
        return;
      }

      final variants = _variants.map((v) => v.toPayload()).toList();
      for (final v in _variants) {
        if (v.skuText.isEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Variant SKU is required')));
          return;
        }
      }

      for (final img in _images) {
        if (!img.hasServerUrl) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Upload an image for each image row, or remove empty rows')),
          );
          return;
        }
      }

      final images = _images.map((img) => img.toPayload()).toList();

      final payload = <String, dynamic>{
        'shop_id': _selectedShopId,
        'name': name,
        'status': _status,
        'category_id': _selectedCategoryId,
        'description': desc.isEmpty ? null : desc,
        'variants': variants,
        'images': images,
      };

      if (_selectedBrandId != null) {
        payload['brand_id'] = _selectedBrandId;
      } else if (brandName.isNotEmpty) {
        payload['brand_name'] = brandName;
      }

      final saved = widget.productId == null
          ? await _api.createShopProduct(payload)
          : await _api.updateShopProduct(widget.productId!, payload);

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Product saved successfully')));
      context.go('/owner/products/${saved.id}');
    } catch (e) {
      setState(() => _error = e.toString());
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Save failed: $e')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _brandNameController.dispose();
    for (final v in _variants) {
      v.dispose();
    }
    for (final img in _images) {
      img.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final canSave = !_saving && !_loading;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.productId == null ? 'Create Product' : 'Edit Product'),
        actions: [
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else
            IconButton(
              icon: const Icon(Icons.check),
              onPressed: canSave ? _submit : null,
            )
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null) ...[
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 16),
                ],

                TextField(
                  controller: _nameController,
                  decoration: const InputDecoration(labelText: 'Product name'),
                ),
                const SizedBox(height: 12),

                DropdownMenu<int>(
                  label: const Text('Shop'),
                  initialSelection: _selectedShopId,
                  onSelected: (v) => setState(() => _selectedShopId = v),
                  dropdownMenuEntries: _shops.map((s) => DropdownMenuEntry<int>(value: s.id, label: s.name)).toList(),
                ),
                const SizedBox(height: 12),

                DropdownMenu<String>(
                  label: const Text('Status'),
                  initialSelection: _status,
                  onSelected: (v) => setState(() => _status = v ?? _status),
                  dropdownMenuEntries: const [
                    DropdownMenuEntry(value: 'draft', label: 'Draft'),
                    DropdownMenuEntry(value: 'published', label: 'Published'),
                    DropdownMenuEntry(value: 'archived', label: 'Archived'),
                  ],
                ),

                const SizedBox(height: 12),
                if (_categories.isNotEmpty) ...[
                  DropdownMenu<int>(
                    label: const Text('Parent category'),
                    initialSelection: _selectedParentCategoryId,
                    onSelected: (v) {
                      setState(() {
                        _selectedParentCategoryId = v;
                        _selectedCategoryId = null;
                        if (v != null) {
                          final parent = _categories.where((c) => c.id == v).firstOrNull;
                          if (parent != null && parent.children.isNotEmpty) {
                            _selectedCategoryId = parent.children.first.id;
                          }
                        }
                      });
                    },
                    dropdownMenuEntries: _categories.map((c) => DropdownMenuEntry<int>(value: c.id, label: c.name)).toList(),
                  ),
                  const SizedBox(height: 12),
                  DropdownMenu<int>(
                    label: const Text('Subcategory'),
                    initialSelection: _selectedCategoryId,
                    onSelected: (v) => setState(() => _selectedCategoryId = v),
                    dropdownMenuEntries: _subcategories.map((c) => DropdownMenuEntry<int>(value: c.id, label: c.name)).toList(),
                  ),
                ],

                const SizedBox(height: 12),
                DropdownMenu<int?>(
                  label: const Text('Brand (optional)'),
                  initialSelection: _selectedBrandId,
                  onSelected: (v) {
                    setState(() {
                      _selectedBrandId = v;
                      if (v != null) _brandNameController.text = '';
                    });
                  },
                  dropdownMenuEntries: [
                    const DropdownMenuEntry<int?>(value: null, label: 'None'),
                    ..._brands.map((b) => DropdownMenuEntry<int?>(value: b.id, label: b.name)),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _brandNameController,
                  decoration: const InputDecoration(
                    labelText: 'Brand name (used if not selected)',
                    hintText: 'Leave empty if you chose a brand from dropdown',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descriptionController,
                  decoration: const InputDecoration(labelText: 'Description (optional)'),
                  maxLines: 4,
                ),

                const Divider(height: 32),

                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Variants', style: Theme.of(context).textTheme.titleMedium),
                    FilledButton.tonal(
                      onPressed: () => setState(() => _variants.add(_VariantRow.empty())),
                      child: const Text('Add Variant'),
                    ),
                  ],
                ),

                const SizedBox(height: 12),
                ..._variants.asMap().entries.map((e) {
                  final idx = e.key;
                  final v = e.value;
                  return _VariantCard(
                    index: idx,
                    variant: v,
                    onRemove: () {
                      setState(() {
                        v.dispose();
                        _variants.removeAt(idx);
                        if (_variants.isEmpty) _variants.add(_VariantRow.empty());
                      });
                    },
                  );
                }),

                const Divider(height: 32),

                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Images', style: Theme.of(context).textTheme.titleMedium),
                    FilledButton.tonal(
                      onPressed: () => setState(() => _images.add(_ImageRow.empty())),
                      child: const Text('Add Image'),
                    ),
                  ],
                ),

                const SizedBox(height: 12),
                ..._images.asMap().entries.map((e) {
                  final idx = e.key;
                  final img = e.value;
                  return _ImageCard(
                    index: idx,
                    image: img,
                    api: _api,
                    onChanged: () => setState(() {}),
                    onRemove: () {
                      setState(() {
                        img.dispose();
                        _images.removeAt(idx);
                      });
                    },
                  );
                }),

                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _saving ? null : _submit,
                    child: _saving
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Save'),
                  ),
                ),
              ],
            ),
    );
  }
}

extension _FirstOrNullExt<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}

class _AttributeRow {
  final TextEditingController keyController;
  final TextEditingController valueController;

  _AttributeRow({String key = '', String value = ''})
      : keyController = TextEditingController(text: key),
        valueController = TextEditingController(text: value);

  String get keyText => keyController.text.trim();
  String get valueText => valueController.text.trim();

  void dispose() {
    keyController.dispose();
    valueController.dispose();
  }
}

class _VariantRow {
  final int? id;
  final TextEditingController skuController;
  final TextEditingController nameController;
  final TextEditingController priceController;
  final TextEditingController compareAtPriceController;
  final TextEditingController stockController;
  bool isActive;
  final List<_AttributeRow> attributes;

  _VariantRow({
    this.id,
    required this.skuController,
    required this.nameController,
    required this.priceController,
    required this.compareAtPriceController,
    required this.stockController,
    required this.isActive,
    required this.attributes,
  });

  factory _VariantRow.empty() {
    return _VariantRow(
      id: null,
      skuController: TextEditingController(),
      nameController: TextEditingController(),
      priceController: TextEditingController(text: '0'),
      compareAtPriceController: TextEditingController(),
      stockController: TextEditingController(text: '0'),
      isActive: true,
      attributes: [],
    );
  }

  factory _VariantRow.fromVariant(ProductVariant v) {
    return _VariantRow(
      id: v.id,
      skuController: TextEditingController(text: v.sku ?? ''),
      nameController: TextEditingController(text: v.name ?? ''),
      priceController: TextEditingController(text: v.price.toString()),
      compareAtPriceController: TextEditingController(text: v.compareAtPrice?.toString() ?? ''),
      stockController: TextEditingController(text: v.stock.toString()),
      isActive: v.isActive,
      attributes: (v.attributes ?? {}).entries
          .map((e) => _AttributeRow(key: e.key, value: e.value?.toString() ?? ''))
          .toList(),
    );
  }

  String get skuText => skuController.text.trim();

  Map<String, dynamic> toPayload() {
    final attrs = <String, dynamic>{};
    for (final a in attributes) {
      final k = a.keyText;
      if (k.isEmpty) continue;
      attrs[k] = a.valueText;
    }

    double parseDouble(TextEditingController c) {
      final t = c.text.trim();
      if (t.isEmpty) return 0;
      return double.tryParse(t) ?? 0;
    }

    int parseInt(TextEditingController c) {
      final t = c.text.trim();
      if (t.isEmpty) return 0;
      return int.tryParse(t) ?? 0;
    }

    final compareAt = compareAtPriceController.text.trim();

    return <String, dynamic>{
      if (id != null) 'id': id,
      'sku': skuText,
      'name': nameController.text.trim().isEmpty ? null : nameController.text.trim(),
      'price': parseDouble(priceController),
      'compare_at_price': compareAt.isEmpty ? null : parseDouble(compareAtPriceController),
      'stock': parseInt(stockController),
      'is_active': isActive,
      'attributes': attrs,
    };
  }

  void dispose() {
    skuController.dispose();
    nameController.dispose();
    priceController.dispose();
    compareAtPriceController.dispose();
    stockController.dispose();
    for (final a in attributes) {
      a.dispose();
    }
  }
}

class _ImageRow {
  final int? id;
  /// Storage path returned by `/shop/product-images/upload` (same as [ProductImage.url]).
  String? serverUrl;
  Uint8List? localPreviewBytes;
  bool uploading;
  final TextEditingController sortOrderController;
  bool isPrimary;

  _ImageRow({
    this.id,
    this.serverUrl,
    this.localPreviewBytes,
    this.uploading = false,
    required this.sortOrderController,
    required this.isPrimary,
  });

  factory _ImageRow.empty() {
    return _ImageRow(
      id: null,
      serverUrl: null,
      localPreviewBytes: null,
      uploading: false,
      sortOrderController: TextEditingController(text: '0'),
      isPrimary: false,
    );
  }

  factory _ImageRow.fromImage(ProductImage img) {
    return _ImageRow(
      id: img.id,
      serverUrl: img.url,
      localPreviewBytes: null,
      uploading: false,
      sortOrderController: TextEditingController(text: img.sortOrder.toString()),
      isPrimary: img.isPrimary,
    );
  }

  bool get hasServerUrl => serverUrl != null && serverUrl!.trim().isNotEmpty;

  Map<String, dynamic> toPayload() {
    final sortText = sortOrderController.text.trim();
    final sort = sortText.isEmpty ? 0 : int.tryParse(sortText) ?? 0;

    return <String, dynamic>{
      if (id != null) 'id': id,
      'url': serverUrl!.trim(),
      'is_primary': isPrimary,
      'sort_order': sort,
    };
  }

  void dispose() {
    sortOrderController.dispose();
  }
}

class _VariantCard extends StatelessWidget {
  final int index;
  final _VariantRow variant;
  final VoidCallback onRemove;

  const _VariantCard({
    required this.index,
    required this.variant,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Variant ${index + 1}', style: Theme.of(context).textTheme.titleSmall),
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),

            const SizedBox(height: 12),

            TextField(
              controller: variant.skuController,
              decoration: const InputDecoration(labelText: 'SKU *'),
            ),
            const SizedBox(height: 12),

            TextField(
              controller: variant.nameController,
              decoration: const InputDecoration(labelText: 'Variant name (optional)'),
            ),

            const SizedBox(height: 12),

            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: variant.priceController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Price *'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: variant.compareAtPriceController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Compare at (optional)'),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: variant.stockController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Stock *'),
                  ),
                ),
                const SizedBox(width: 12),
                Row(
                  children: [
                    Switch(
                      value: variant.isActive,
                      onChanged: (v) {
                        variant.isActive = v;
                        (context as Element).markNeedsBuild();
                      },
                    ),
                    Text(variant.isActive ? 'Active' : 'Inactive'),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 12),

            Text('Attributes', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            if (variant.attributes.isEmpty)
              Text(
                'No attributes. Add one below.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.outline),
              ),

            ...variant.attributes.asMap().entries.map((e) {
              final i = e.key;
              final attr = e.value;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: attr.keyController,
                        decoration: const InputDecoration(labelText: 'Key'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: attr.valueController,
                        decoration: const InputDecoration(labelText: 'Value'),
                      ),
                    ),
                    IconButton(
                      onPressed: () => onRemoveAttribute(context, variant, i),
                      icon: const Icon(Icons.remove_circle_outline),
                    ),
                  ],
                ),
              );
            }),

            const SizedBox(height: 4),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () => onAddAttribute(context, variant),
                icon: const Icon(Icons.add),
                label: const Text('Add attribute'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void onAddAttribute(BuildContext context, _VariantRow variant) {
    // We need setState from parent; use Navigator pop state? Not possible here.
    // This card is rebuilt on parent setState. Since we don't have a callback,
    // we mutate and rely on user-triggered rebuild (Save/other actions) for now.
    // To keep it correct, we pass a rebuild callback via onRemoveAttribute logic below in parent.
    // For now, we just add the row and request a frame.
    variant.attributes.add(_AttributeRow());
    (context as Element).markNeedsBuild();
  }

  void onRemoveAttribute(BuildContext context, _VariantRow variant, int idx) {
    if (idx < 0 || idx >= variant.attributes.length) return;
    final a = variant.attributes[idx];
    a.dispose();
    variant.attributes.removeAt(idx);
    (context as Element).markNeedsBuild();
  }
}

class _ImageCard extends StatelessWidget {
  final int index;
  final _ImageRow image;
  final ApiService api;
  final VoidCallback onChanged;
  final VoidCallback onRemove;

  const _ImageCard({
    required this.index,
    required this.image,
    required this.api,
    required this.onChanged,
    required this.onRemove,
  });

  Future<void> _pickAndUpload(BuildContext context) async {
    final picker = ImagePicker();
    final x = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 2048,
    );
    if (x == null) return;

    final bytes = await x.readAsBytes();
    image.localPreviewBytes = bytes;
    image.uploading = true;
    onChanged();

    try {
      final filename = x.name.isNotEmpty ? x.name : 'image.jpg';
      final path = await api.uploadShopProductImageBytes(
        bytes,
        filename: filename,
      );
      image.serverUrl = path;
      image.localPreviewBytes = null;
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Upload failed: $e')),
        );
      }
      image.localPreviewBytes = null;
    } finally {
      image.uploading = false;
      onChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Widget preview;
    if (image.uploading) {
      preview = const Center(child: CircularProgressIndicator(strokeWidth: 2));
    } else if (image.localPreviewBytes != null) {
      preview = Image.memory(
        image.localPreviewBytes!,
        fit: BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
      );
    } else if (image.hasServerUrl) {
      preview = CachedNetworkImage(
        imageUrl: ApiClient.imageUrl(image.serverUrl!),
        fit: BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
        placeholder: (context, url) => const Center(child: CircularProgressIndicator(strokeWidth: 2)),
        errorWidget: (context, url, error) => const Center(child: Icon(Icons.broken_image_outlined)),
      );
    } else {
      preview = Center(
        child: Icon(Icons.add_photo_alternate_outlined, size: 40, color: theme.colorScheme.outline),
      );
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Image ${index + 1}', style: theme.textTheme.titleSmall),
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox(
                    width: 100,
                    height: 100,
                    child: ColoredBox(
                      color: theme.colorScheme.surfaceContainerHighest,
                      child: preview,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      OutlinedButton.icon(
                        onPressed: image.uploading ? null : () => _pickAndUpload(context),
                        icon: const Icon(Icons.upload_file, size: 18),
                        label: Text(image.hasServerUrl ? 'Replace image' : 'Choose image'),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        image.hasServerUrl ? 'Image uploaded' : 'Pick a file from your gallery',
                        style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: image.sortOrderController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Sort order'),
                  ),
                ),
                const SizedBox(width: 12),
                Row(
                  children: [
                    Switch(
                      value: image.isPrimary,
                      onChanged: (v) {
                        image.isPrimary = v;
                        onChanged();
                      },
                    ),
                    Text(image.isPrimary ? 'Primary' : 'Not primary'),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

