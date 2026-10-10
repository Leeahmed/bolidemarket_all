import 'package:dio/dio.dart';
import 'models.dart';

class AppConfig {
  static const environment = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'development',
  );
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
  static void validate() {
    final uri = Uri.tryParse(baseUrl);
    if (uri == null ||
        !uri.hasAuthority ||
        !['http', 'https'].contains(uri.scheme) ||
        (environment == 'production' && uri.scheme != 'https')) {
      throw StateError(
        'API_BASE_URL doit être une URL valide, HTTPS en production.',
      );
    }
  }

  // Rewrite only loopback media returned by the local API for emulator/LAN access.
  static String mediaUrl(String value) {
    final uri = Uri.tryParse(value);
    final api = Uri.parse(baseUrl);
    if (uri != null &&
        ['localhost', '127.0.0.1'].contains(uri.host) &&
        environment == 'development') {
      return uri
          .replace(scheme: api.scheme, host: api.host, port: api.port)
          .toString();
    }
    return value;
  }
}

class ApiFailure implements Exception {
  const ApiFailure(
    this.message, {
    this.status,
    this.fields = const {},
    this.code,
  });
  final String message;
  final int? status;
  final Json fields;
  final String? code;
  factory ApiFailure.from(Object error) {
    if (error is ApiFailure) return error;
    if (error is! DioException) {
      return const ApiFailure('Une erreur est survenue. Réessayez.');
    }
    final status = error.response?.statusCode;
    final body = object(object(error.response?.data)['error']);
    final message = switch (status) {
      401 => 'Votre session a expiré. Connectez-vous à nouveau.',
      403 => 'Cette action n’est pas autorisée pour votre compte.',
      404 => 'Cette annonce ou cette ressource n’est plus disponible.',
      409 => 'Les informations ont changé. Actualisez cette page.',
      422 =>
        text(body['message']).isEmpty
            ? 'Vérifiez les informations saisies.'
            : text(body['message']),
      429 => 'Trop de tentatives. Réessayez dans un instant.',
      null => 'Impossible de se connecter à BolideMarket.',
      _ => 'BolideMarket est momentanément indisponible. Réessayez.',
    };
    return ApiFailure(
      message,
      status: status,
      fields: object(body['fields']),
      code: text(body['code']),
    );
  }
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({Dio? client})
    : dio =
          client ??
          Dio(
            BaseOptions(
              baseUrl: AppConfig.baseUrl,
              connectTimeout: const Duration(seconds: 12),
              receiveTimeout: const Duration(seconds: 20),
              sendTimeout: const Duration(seconds: 20),
              headers: {'Accept': 'application/json'},
            ),
          ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final current = token;
          if (current != null) {
            options.headers['Authorization'] = 'Bearer $current';
            options.extra['sessionToken'] = current;
          }
          handler.next(options);
        },
        onError: (error, handler) {
          final sent = error.requestOptions.extra['sessionToken'];
          // Ignore stale 401s belonging to an older login and credential errors.
          if (error.response?.statusCode == 401 &&
              sent != null &&
              sent == token &&
              !error.requestOptions.path.startsWith('/auth/login')) {
            onInvalidSession?.call();
          }
          handler.next(error);
        },
      ),
    );
  }
  final Dio dio;
  String? token;
  void Function()? onInvalidSession;
  Future<Json> get(String path, [Json query = const {}]) async {
    final data = (await dio.get<dynamic>(path, queryParameters: query)).data;
    if (data is! Map || !data.containsKey('data')) {
      throw const ApiFailure('Réponse serveur invalide. Réessayez.');
    }
    return object(data);
  }
}
