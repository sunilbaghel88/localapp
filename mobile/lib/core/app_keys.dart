import 'package:flutter/material.dart';

/// Root messenger so SnackBars (e.g. after logout) survive route changes.
final GlobalKey<ScaffoldMessengerState> rootScaffoldMessengerKey =
    GlobalKey<ScaffoldMessengerState>();
