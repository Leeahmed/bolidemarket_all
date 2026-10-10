import 'package:flutter/foundation.dart';
import '../../core/api.dart';

/// Compile-time guard: release/profile and non-development builds never bypass auth.
const visualDemoEnabled =
    kDebugMode &&
    bool.fromEnvironment('VISUAL_DEMO', defaultValue: false) &&
    AppConfig.environment == 'development';

bool allowVisualDemo({
  required bool debug,
  required bool requested,
  required String environment,
}) => debug && requested && environment == 'development';
