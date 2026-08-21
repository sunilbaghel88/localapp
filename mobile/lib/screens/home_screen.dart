import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/product.dart';
import '../providers/auth_provider.dart';
import '../providers/branding_provider.dart';
import '../services/api_service.dart';
import '../widgets/app_brand_logo.dart';
import '../widgets/main_scaffold.dart';

const _accentRed = Color(0xFFC62828);
const _waveBlue = Color(0xFF42A5F5);
const _waveBlueDeep = Color(0xFF1E88E5);
const _avatarRing = Color(0xFFE8A838);

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final ApiService _api = ApiService();
  final PageController _pageController = PageController();
  final NumberFormat _pointsFormat = NumberFormat('#,##0.00');

  List<_BannerSlide> _slides = const [];
  int _slideIndex = 0;
  Timer? _autoPlay;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _slides = _defaultSlides();
    _startAutoPlay();
    _loadBanners();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      context.read<AuthProvider>().refreshUser();
    });
  }

  @override
  void dispose() {
    _autoPlay?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  List<_BannerSlide> _defaultSlides() {
    return const [
      _BannerSlide(
        title: 'Earn exclusive rewards on every purchase',
        subtitle: 'LOYALTY PROGRAM',
      ),
      _BannerSlide(
        title: 'Shop from trusted local sellers',
        subtitle: 'E-SHOP',
        route: '/eshop',
      ),
      _BannerSlide(
        title: 'Redeem points with participating shops',
        subtitle: 'REWARDS',
      ),
    ];
  }

  Future<void> _loadBanners() async {
    try {
      final data = await _api.getHome();
      final featured =
          (data['featured_products'] as List<dynamic>?)
              ?.map((e) => Product.fromJson(e as Map<String, dynamic>))
              .where((p) => p.imageUrl != null)
              .take(4)
              .toList() ??
          [];
      if (!mounted) return;
      if (featured.isEmpty) {
        setState(() => _loading = false);
        return;
      }
      setState(() {
        _slides = [
          ..._defaultSlides().take(1),
          ...featured.map(
            (p) => _BannerSlide(
              title: p.name,
              subtitle: (p.brand ?? '').trim().isEmpty
                  ? 'FEATURED'
                  : p.brand!.trim().toUpperCase(),
              imageUrl: p.imageUrl,
              route: '/products/${p.slug}',
            ),
          ),
        ];
        _loading = false;
        _slideIndex = 0;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  void _startAutoPlay() {
    _autoPlay?.cancel();
    _autoPlay = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_pageController.hasClients || _slides.isEmpty) return;
      final next = (_slideIndex + 1) % _slides.length;
      _pageController.animateToPage(
        next,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeInOut,
      );
    });
  }

  void _goSlide(int delta) {
    if (_slides.isEmpty || !_pageController.hasClients) return;
    final next = (_slideIndex + delta + _slides.length) % _slides.length;
    _pageController.animateToPage(
      next,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeInOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final branding = context.watch<BrandingProvider>().branding;
    final name = (user?.name ?? '').trim().isEmpty
        ? 'Guest'
        : user!.name.trim();
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    final points = user?.rewardPoints ?? 0;
    final isPartner = user?.isPartner ?? false;

    return MainScaffold(
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Column(
                children: [
                  _UserSummary(
                    name: name,
                    initial: initial,
                    rewardPoints: _pointsFormat.format(points.toDouble()),
                  ),
                  const SizedBox(height: 18),
                  _PromoCarousel(
                    controller: _pageController,
                    slides: _slides,
                    loading: _loading,
                    onPageChanged: (i) => setState(() => _slideIndex = i),
                    onPrev: () => _goSlide(-1),
                    onNext: () => _goSlide(1),
                  ),
                  const SizedBox(height: 10),
                  _PageDots(count: _slides.length, index: _slideIndex),
                  const SizedBox(height: 22),
                  _ActionGrid(isPartner: isPartner),
                ],
              ),
            ),
          ),
          SliverFillRemaining(
            hasScrollBody: false,
            child: Align(
              alignment: Alignment.bottomCenter,
              child: _HomeFooter(tagline: branding?.tagline),
            ),
          ),
        ],
      ),
    );
  }
}

class _UserSummary extends StatelessWidget {
  const _UserSummary({
    required this.name,
    required this.initial,
    required this.rewardPoints,
  });

  final String name;
  final String initial;
  final String rewardPoints;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Reward Points',
                style: TextStyle(
                  color: Colors.black45,
                  fontSize: 12,
                  height: 1.2,
                ),
              ),
              Text(
                rewardPoints,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: Colors.black,
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              Flexible(
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(
                        text: 'Welcome,\n',
                        style: TextStyle(color: Colors.black54, fontSize: 13),
                      ),
                      TextSpan(
                        text: name,
                        style: const TextStyle(
                          color: Colors.black,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  textAlign: TextAlign.right,
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: _avatarRing, width: 2),
                ),
                child: CircleAvatar(
                  radius: 18,
                  backgroundColor: const Color(0xFFE0F2F1),
                  child: Text(
                    initial,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Colors.black87,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _PromoCarousel extends StatelessWidget {
  const _PromoCarousel({
    required this.controller,
    required this.slides,
    required this.loading,
    required this.onPageChanged,
    required this.onPrev,
    required this.onNext,
  });

  final PageController controller;
  final List<_BannerSlide> slides;
  final bool loading;
  final ValueChanged<int> onPageChanged;
  final VoidCallback onPrev;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 148,
      child: Stack(
        alignment: Alignment.center,
        children: [
          PageView.builder(
            controller: controller,
            itemCount: slides.length,
            onPageChanged: onPageChanged,
            itemBuilder: (context, i) {
              final slide = slides[i];
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: _BannerCard(slide: slide),
              );
            },
          ),
          if (loading)
            const IgnorePointer(
              child: Align(
                alignment: Alignment.bottomRight,
                child: Padding(
                  padding: EdgeInsets.all(10),
                  child: SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ),
          Positioned(
            left: 4,
            child: _ChevronButton(icon: Icons.chevron_left, onTap: onPrev),
          ),
          Positioned(
            right: 4,
            child: _ChevronButton(icon: Icons.chevron_right, onTap: onNext),
          ),
        ],
      ),
    );
  }
}

class _BannerCard extends StatelessWidget {
  const _BannerCard({required this.slide});

  final _BannerSlide slide;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: slide.route == null ? null : () => context.push(slide.route!),
        borderRadius: BorderRadius.circular(6),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(6),
          child: Stack(
            fit: StackFit.expand,
            children: [
              const ColoredBox(color: _accentRed),
              if (slide.imageUrl != null)
                CachedNetworkImage(
                  imageUrl: slide.imageUrl!,
                  fit: BoxFit.cover,
                  errorWidget: (_, _, _) => const SizedBox.shrink(),
                ),
              ColoredBox(
                color: _accentRed.withValues(
                  alpha: slide.imageUrl == null ? 0 : 0.55,
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(36, 16, 36, 16),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      slide.subtitle,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: 11,
                        letterSpacing: 1.4,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      slide.title.toUpperCase(),
                      textAlign: TextAlign.center,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        height: 1.25,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChevronButton extends StatelessWidget {
  const _ChevronButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.18),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(4),
          child: Icon(icon, color: Colors.white, size: 28),
        ),
      ),
    );
  }
}

class _PageDots extends StatelessWidget {
  const _PageDots({required this.count, required this.index});

  final int count;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (i) {
        final active = i == index;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          margin: const EdgeInsets.symmetric(horizontal: 3),
          height: 7,
          width: active ? 18 : 7,
          decoration: BoxDecoration(
            color: active ? Colors.grey.shade600 : Colors.grey.shade400,
            borderRadius: BorderRadius.circular(8),
          ),
        );
      }),
    );
  }
}

class _ActionGrid extends StatelessWidget {
  const _ActionGrid({required this.isPartner});

  final bool isPartner;

  @override
  Widget build(BuildContext context) {
    final items = <_HomeAction>[
      const _HomeAction(
        label: 'UPLOAD INVOICE',
        icon: Icons.upload_file_outlined,
      ),
      _HomeAction(
        label: 'MY PURCHASE',
        icon: Icons.shopping_cart_outlined,
        route: '/orders',
      ),
      if (isPartner)
        const _HomeAction(
          label: 'REDEEM POINTS',
          icon: Icons.workspace_premium_outlined,
          route: '/partner/rewards',
        ),
      const _HomeAction(label: 'EVENT', icon: Icons.event_outlined),
      const _HomeAction(label: 'CONTACT US', icon: Icons.phone_outlined),
      const _HomeAction(label: 'GALLERY', icon: Icons.badge_outlined),
    ];

    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 18,
      crossAxisSpacing: 8,
      childAspectRatio: 0.95,
      children: items.map((item) => _ActionButton(action: item)).toList(),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.action});

  final _HomeAction action;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: action.route == null ? null : () => context.push(action.route!),
      borderRadius: BorderRadius.circular(12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: Colors.black87, width: 1),
            ),
            child: Icon(action.icon, color: _accentRed, size: 32),
          ),
          const SizedBox(height: 8),
          Text(
            action.label,
            textAlign: TextAlign.center,
            maxLines: 2,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.3,
              height: 1.15,
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeFooter extends StatelessWidget {
  const _HomeFooter({this.tagline});

  final String? tagline;

  @override
  Widget build(BuildContext context) {
    final line = (tagline == null || tagline!.trim().isEmpty)
        ? 'LOYALTY PROGRAM'
        : tagline!.trim().toUpperCase();

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const SizedBox(height: 12),
        const AppBrandLogo(height: 44),
        const SizedBox(height: 4),
        Text(
          line,
          style: const TextStyle(
            fontSize: 11,
            letterSpacing: 1.6,
            fontWeight: FontWeight.w700,
            color: Colors.black54,
          ),
        ),
        const SizedBox(height: 8),
        const SizedBox(
          height: 72,
          width: double.infinity,
          child: CustomPaint(painter: _WaterWavePainter()),
        ),
      ],
    );
  }
}

class _WaterWavePainter extends CustomPainter {
  const _WaterWavePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final deep = Paint()..color = _waveBlueDeep;
    final light = Paint()..color = _waveBlue;

    final back = Path()
      ..moveTo(0, size.height * 0.42)
      ..quadraticBezierTo(
        size.width * 0.25,
        size.height * 0.08,
        size.width * 0.5,
        size.height * 0.38,
      )
      ..quadraticBezierTo(
        size.width * 0.75,
        size.height * 0.68,
        size.width,
        size.height * 0.28,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(back, deep);

    final front = Path()
      ..moveTo(0, size.height * 0.58)
      ..quadraticBezierTo(
        size.width * 0.2,
        size.height * 0.28,
        size.width * 0.42,
        size.height * 0.52,
      )
      ..quadraticBezierTo(
        size.width * 0.7,
        size.height * 0.82,
        size.width,
        size.height * 0.4,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(front, light);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _BannerSlide {
  const _BannerSlide({
    required this.title,
    required this.subtitle,
    this.imageUrl,
    this.route,
  });

  final String title;
  final String subtitle;
  final String? imageUrl;
  final String? route;
}

class _HomeAction {
  const _HomeAction({required this.label, required this.icon, this.route});

  final String label;
  final IconData icon;
  final String? route;
}
