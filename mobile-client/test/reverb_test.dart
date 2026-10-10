import 'dart:async';
import 'dart:convert';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/features/realtime/reverb.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'core_test.dart' show Adapter;

class Socket implements WebSocketChannel {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
  final input = StreamController<dynamic>();
  final output = Sink();
  @override
  Stream<dynamic> get stream => input.stream;
  @override
  WebSocketSink get sink => output;
  @override
  Future<void> get ready async {}
  @override
  int? get closeCode => null;
  @override
  String? get closeReason => null;
  @override
  String? get protocol => null;
  void frame(String event, {String? channel, dynamic data}) => input.add(
    jsonEncode({'event': event, 'channel': channel, 'data': data ?? {}}),
  );
}

class Sink implements WebSocketSink {
  final frames = <Map<String, dynamic>>[];
  bool closed = false;
  final privateSubscribed = Completer<void>();
  @override
  void add(dynamic value) {
    final frame = jsonDecode(value as String) as Map<String, dynamic>;
    frames.add(frame);
    if ((frame['data'] as Map?)?['channel']?.toString().startsWith(
              'private-user.',
            ) ==
            true &&
        !privateSubscribed.isCompleted) {
      privateSubscribed.complete();
    }
  }

  @override
  Future<void> close([int? code, String? reason]) async {
    closed = true;
  }

  @override
  Future<void> get done async {}
  @override
  void addError(Object error, [StackTrace? stackTrace]) {}
  @override
  Future<void> addStream(Stream<dynamic> stream) async {
    await for (final value in stream) {
      add(value);
    }
  }
}

Future<void> tick() async {
  await Future<void>.delayed(Duration.zero);
  await Future<void>.delayed(Duration.zero);
}

void event(
  Socket socket, {
  String id = 'event-1',
  String channel = 'private-user.7',
}) => socket.frame(
  'ReservationConfirmed',
  channel: channel,
  data: jsonEncode({
    'event_id': id,
    'type': 'ReservationConfirmed',
    'data': {'id': '12'},
  }),
);

void main() {
  test(
    'Protocole Reverb : souscription publique, auth Bearer privée brute, ping et déduplication',
    () async {
      final requests = <RequestOptions>[];
      final requested = Completer<void>();
      final dio = Dio(BaseOptions(baseUrl: 'http://example.test/api/v1'))
        ..httpClientAdapter = Adapter((request) async {
          requests.add(request);
          requested.complete();
          return ResponseBody.fromString(
            '{"auth":"public-key:signature"}',
            200,
            headers: {
              'content-type': ['application/json'],
            },
          );
        });
      final api = ApiClient(client: dio)..token = 'demo-test-token';
      final socket = Socket();
      final service = ReverbTransport(
        api,
        const ReverbConfig(key: 'public-key', host: 'localhost'),
        sockets: (uri, headers) {
          expect(uri.queryParameters['protocol'], '7');
          expect(headers['Origin'], 'http://localhost');
          return socket;
        },
      );
      addTearDown(service.dispose);
      final signals = <DomainSignal>[];
      final sub = service.signals.listen(signals.add);
      service.identity('7');
      await tick();
      socket.frame(
        'pusher:connection_established',
        data: jsonEncode({'socket_id': '12.34'}),
      );
      await requested.future.timeout(const Duration(seconds: 3));
      await socket.output.privateSubscribed.future.timeout(
        const Duration(seconds: 3),
      );
      expect(
        requests.single.headers['Authorization'],
        'Bearer demo-test-token',
      );
      expect(requests.single.data, {
        'socket_id': '12.34',
        'channel_name': 'private-user.7',
      });
      expect(socket.output.frames.first['data'], {'channel': 'marketplace'});
      expect(socket.output.frames[1]['data'], {
        'channel': 'private-user.7',
        'auth': 'public-key:signature',
      });
      socket.frame('pusher:ping');
      socket.frame(
        'pusher_internal:subscription_succeeded',
        channel: 'private-user.7',
      );
      event(socket);
      event(socket);
      event(socket, channel: 'private-user.99', id: 'bad-owner');
      await tick();
      expect(signals.map((s) => s.type), [
        'Reconnected',
        'ReservationConfirmed',
      ]);
      expect(socket.output.frames.last['event'], 'pusher:pong');
      api.token = null;
      event(socket, id: 'old-session');
      await tick();
      expect(signals.length, 2);
      service.dispose();
      await sub.cancel();
      await socket.input.close();
      dio.close();
    },
  );
  testWidgets(
    'Cycle de vie : fermeture arrière-plan, nouvelle identité et nettoyage',
    (tester) async {
      final sockets = <Socket>[];
      final api = ApiClient()..token = 'first';
      final service = ReverbTransport(
        api,
        const ReverbConfig(key: 'key', host: 'localhost'),
        sockets: (_, _) {
          final socket = Socket();
          sockets.add(socket);
          return socket;
        },
      );
      service.identity('7');
      await tester.pump();
      service.foreground(false);
      await tester.pump();
      expect(sockets.single.output.closed, isTrue);
      await tester.pump(const Duration(seconds: 40));
      expect(sockets.length, 1);
      service.foreground(true);
      await tester.pump();
      expect(sockets.length, 2);
      api.token = 'second';
      service.identity('8');
      await tester.pump();
      expect(sockets[1].output.closed, isTrue);
      expect(sockets.length, 3);
      service.dispose();
      await tester.pump();
      await tester.pump(const Duration(seconds: 40));
      expect(sockets.length, 3);
      for (final socket in sockets) {
        unawaited(socket.input.close());
      }
      await tester.pump();
      api.dio.close();
    },
  );
  testWidgets(
    'Panne socket : reconnexion bornée et REST toujours indépendant',
    (tester) async {
      final sockets = <Socket>[];
      final api = ApiClient();
      final service = ReverbTransport(
        api,
        const ReverbConfig(key: 'key', host: 'localhost'),
        sockets: (_, _) {
          final socket = Socket();
          sockets.add(socket);
          return socket;
        },
      );
      service.identity(null);
      await tester.pump();
      sockets.first.input.addError(StateError('offline'));
      await tester.pump();
      expect(sockets.first.output.closed, isTrue);
      await tester.pump(const Duration(milliseconds: 999));
      expect(sockets.length, 1);
      await tester.pump(const Duration(milliseconds: 1));
      expect(sockets.length, 2);
      sockets.last.frame(
        'pusher_internal:subscription_succeeded',
        channel: 'marketplace',
      );
      final signal = service.signals.first;
      await tester.pump();
      expect((await signal).type, 'Reconnected');
      service.dispose();
      await tester.pump();
      for (final socket in sockets) {
        unawaited(socket.input.close());
      }
      await tester.pump();
      api.dio.close();
    },
  );
}
