import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'app_router.dart';
import 'core/app_keys.dart';
import 'providers/auth_provider.dart';
import 'providers/branding_provider.dart';
import 'services/hindi_name_store.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => BrandingProvider()),
        ChangeNotifierProvider(create: (_) => HindiNameStore()),
      ],
      child: Builder(
        builder: (context) {
          return MaterialApp.router(
            scaffoldMessengerKey: rootScaffoldMessengerKey,
            title: 'LocalApp',
            theme: ThemeData(
              colorScheme: ColorScheme.fromSeed(seedColor: Colors.amber, brightness: Brightness.light),
              useMaterial3: true,
            ),
            routerConfig: createRouter(context),
          );
        },
      ),
    );
  }
}
