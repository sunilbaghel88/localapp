import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'app_brand_logo.dart';
import 'app_drawer.dart';

class MainScaffold extends StatelessWidget {
  const MainScaffold({
    super.key,
    required this.body,
    this.title,
    this.actions = const [],
    this.showHomeAction = true,
    this.showNotificationAction = true,
    this.floatingActionButton,
    this.backgroundColor = Colors.white,
  });

  final Widget body;
  final Widget? title;
  final List<Widget> actions;
  final bool showHomeAction;
  final bool showNotificationAction;
  final Widget? floatingActionButton;
  final Color? backgroundColor;

  static const _logoBarHeight = 76.0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: title == null ? _logoAppBar(context) : _titledAppBar(context),
      drawer: const AppDrawer(),
      floatingActionButton: floatingActionButton,
      body: body,
    );
  }

  AppBar _titledAppBar(BuildContext context) {
    return AppBar(
      backgroundColor: Colors.white,
      foregroundColor: Colors.black,
      surfaceTintColor: Colors.white,
      elevation: 0.4,
      scrolledUnderElevation: 0.4,
      centerTitle: true,
      automaticallyImplyLeading: false,
      leading: _menuButton(context),
      title: title,
      actions: _actionButtons(context),
    );
  }

  PreferredSizeWidget _logoAppBar(BuildContext context) {
    return PreferredSize(
      preferredSize: const Size.fromHeight(_logoBarHeight),
      child: Material(
        color: Colors.white,
        elevation: 0.4,
        child: SafeArea(
          bottom: false,
          child: SizedBox(
            height: _logoBarHeight,
            child: Row(
              children: [
                _menuButton(context),
                const Expanded(
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                    child: AppBrandLogo(height: 60),
                  ),
                ),
                ..._actionButtons(context),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _menuButton(BuildContext context) {
    return Builder(
      builder: (context) => IconButton(
        icon: const Icon(Icons.menu),
        color: Colors.black,
        tooltip: 'Menu',
        onPressed: () => Scaffold.of(context).openDrawer(),
      ),
    );
  }

  List<Widget> _actionButtons(BuildContext context) {
    return [
      if (showHomeAction)
        IconButton(
          icon: const Icon(Icons.home_outlined),
          color: Colors.black,
          tooltip: 'Home',
          onPressed: () {
            if (GoRouterState.of(context).uri.path != '/home') {
              context.go('/home');
            }
          },
        ),
      if (showNotificationAction)
        IconButton(
          icon: const Icon(Icons.notifications_none_outlined),
          color: Colors.black,
          tooltip: 'Notifications',
          onPressed: () {},
        ),
      ...actions,
    ];
  }
}
