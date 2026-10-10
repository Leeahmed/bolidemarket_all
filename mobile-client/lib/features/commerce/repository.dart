import 'package:dio/dio.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import 'models.dart';

class CommerceRepository {
  CommerceRepository(this.api);
  final ApiClient api;
  Future<Json> post(String path, Json body, {String? key}) async {
    final response = await api.dio.post<dynamic>(
      path,
      data: body,
      options: Options(headers: {'Idempotency-Key': ?key}),
    );
    final envelope = object(response.data);
    if (envelope['data'] is! Map) {
      throw const ApiFailure(
        'Réponse serveur invalide. Réessayez avec la même tentative.',
      );
    }
    return object(envelope['data']);
  }

  Future<Availability> availability(
    String slug, {
    String? from,
    String? to,
  }) async {
    Json? data;
    var page = 1;
    while (true) {
      final result = await api.get(
        '/vehicles/${Uri.encodeComponent(slug)}/availability',
        {'page': page, 'per_page': 100, 'from': ?from, 'to': ?to},
      );
      final row = object(result['data']);
      data ??= {...row, 'intervals': <dynamic>[]};
      (data['intervals'] as List).addAll(row['intervals'] as List);
      if (page >= (object(result['meta'])['last_page'] as int? ?? 1)) {
        return Availability(data);
      }
      page++;
    }
  }

  Future<Json> quote(String id, DateTime start, DateTime end) =>
      post('/rental-quotes', {
        'vehicle_id': id,
        'start_date': dateInput(start),
        'end_date': dateInput(end),
      });
  Future<CommerceRecord> reserve(Json body, String key) async =>
      CommerceRecord(await post('/reservations', body, key: key));
  Future<CommerceRecord> buy(Json body, String key) async =>
      CommerceRecord(await post('/orders', body, key: key));
  Future<PageData<CommerceRecord>> list(String kind, {int page = 1}) async =>
      PageData.fromJson(
        await api.get('/me/$kind', {'page': page, 'per_page': 20}),
        CommerceRecord.new,
      );
  Future<CommerceRecord> detail(String kind, String identifier) async {
    if (RegExp(r'^\d+$').hasMatch(identifier)) {
      return CommerceRecord(
        object((await api.get('/me/$kind/$identifier'))['data']),
      );
    }
    // The existing private detail API accepts numeric IDs, not references.
    // Resolve internal reference links using only the current user's scoped list.
    var page = 1;
    while (true) {
      final result = await list(kind, page: page++);
      for (final row in result.items) {
        if (row.reference == identifier) return detail(kind, row.id);
      }
      if (!result.hasMore) {
        throw const ApiFailure(
          'Cette transaction n’est plus disponible.',
          status: 404,
        );
      }
    }
  }

  Future<CommerceRecord> cancel(String id) async =>
      CommerceRecord(await post('/me/reservations/$id/cancel', {}));
  Future<List<CommerceRecord>> all(String kind) async {
    final rows = <CommerceRecord>[];
    var page = 1;
    while (true) {
      final result = await list(kind, page: page++);
      rows.addAll(result.items);
      if (!result.hasMore) return rows;
    }
  }
}

class ReceiptRepository {
  ReceiptRepository(this.api);
  final ApiClient api;
  Future<PageData<Receipt>> list({int page = 1}) async => PageData.fromJson(
    await api.get('/me/receipts', {'page': page, 'per_page': 20}),
    Receipt.new,
  );
  Future<Receipt> detail(String reference) async => Receipt(
    object(
      (await api.get('/me/receipts/${Uri.encodeComponent(reference)}'))['data'],
    ),
  );
  Future<List<int>> pdf(String reference) async {
    final response = await api.dio.get<List<int>>(
      '/me/receipts/${Uri.encodeComponent(reference)}/pdf',
      options: Options(
        responseType: ResponseType.bytes,
        followRedirects: false,
        headers: {'Accept': 'application/pdf'},
      ),
    );
    final bytes = response.data ?? [];
    if (response.statusCode != 200 ||
        !text(
          response.headers.value('content-type'),
        ).startsWith('application/pdf') ||
        bytes.length < 5 ||
        bytes.length > 20 * 1024 * 1024 ||
        String.fromCharCodes(bytes.take(5)) != '%PDF-') {
      throw const ApiFailure('Le serveur n’a pas renvoyé un PDF valide.');
    }
    return bytes;
  }
}
