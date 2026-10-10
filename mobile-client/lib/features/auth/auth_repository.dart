import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api.dart';
import '../../core/models.dart';

abstract class SessionStore {
  Future<String?> readToken();
  Future<void> writeToken(String token);
  Future<void> deleteToken();
  Future<bool> onboardingSeen();
  Future<void> markOnboarding();
}

class SecureSessionStore implements SessionStore {
  const SecureSessionStore();
  static const storage = FlutterSecureStorage();
  @override
  Future<String?> readToken() => storage.read(key: 'bolidemarket.token');
  @override
  Future<void> writeToken(String token) =>
      storage.write(key: 'bolidemarket.token', value: token);
  @override
  Future<void> deleteToken() => storage.delete(key: 'bolidemarket.token');
  @override
  Future<bool> onboardingSeen() async =>
      (await SharedPreferences.getInstance()).getBool('onboarding.seen') ??
      false;
  @override
  Future<void> markOnboarding() async {
    await (await SharedPreferences.getInstance()).setBool(
      'onboarding.seen',
      true,
    );
  }
}

class AuthRepository {
  AuthRepository(this.api, this.store);
  final ApiClient api;
  final SessionStore store;
  Future<AppUser> me() async =>
      AppUser(object((await api.get('/auth/me'))['data']));
  Future<AppUser> login(String email, String password) async {
    final data = object(
      (await api.dio.post<dynamic>(
        '/auth/login',
        data: {
          'email': email.trim(),
          'password': password,
          'device_name': 'BolideMarket mobile',
        },
      )).data,
    )['data'];
    final payload = object(data);
    final token = text(payload['token']);
    if (token.isEmpty) {
      throw const ApiFailure('Le serveur n’a pas fourni de session mobile.');
    }
    final user = AppUser(object(payload['user']));
    await store.writeToken(token);
    api.token = token;
    return user;
  }

  Future<void> register(Json fields) async {
    await api.dio.post<dynamic>('/auth/register', data: fields);
  }

  Future<void> forgot(String email) async {
    await api.dio.post<dynamic>(
      '/auth/forgot-password',
      data: {'email': email.trim()},
    );
  }

  Future<void> logout() async {
    try {
      await api.dio.post<dynamic>('/auth/logout');
    } catch (e) {
      if (ApiFailure.from(e).status != 401) rethrow;
    }
    api.token = null;
    await store.deleteToken();
  }
}
