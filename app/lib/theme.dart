import 'package:flutter/material.dart';

const Color kPink = Color(0xFFD6336C);

ThemeData buildTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: kPink);
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    appBarTheme: const AppBarTheme(centerTitle: false),
    inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
    ),
  );
}
