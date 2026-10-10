import 'dart:async';
import 'dart:convert';
import 'package:web_socket_channel/io.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import '../../core/api.dart';
import '../../core/models.dart';

const domainEventTypes = {
  'VehicleCreated',
  'VehicleUpdated',
  'VehicleStatusChanged',
  'VehiclePublished',
  'VehicleUnpublished',
  'VehicleImageUpdated',
  'VehicleAvailabilityChanged',
  'ReservationCreated',
  'ReservationConfirmed',
  'ReservationCancelled',
  'ReservationRejected',
  'ReservationStarted',
  'ReservationCompleted',
  'ReservationExpired',
  'ReservationUpdated',
  'OrderCreated',
  'OrderConfirmed',
  'OrderCancelled',
  'OrderCompleted',
  'OrderUpdated',
  'PriceOfferCreated',
  'PriceOfferAccepted',
  'PriceOfferRejected',
  'PriceOfferConsumed',
};

class DomainSignal {
  const DomainSignal(this.type, this.data, {this.id = '', this.channel = ''});
  final String type, id, channel;
  final Json data;
  static DomainSignal? decode(Json frame, String? userId) {
    final type = text(frame['event']), channel = text(frame['channel']);
    if (!domainEventTypes.contains(type)) return null;
    if (channel != 'marketplace' &&
        (userId == null || channel != 'private-user.$userId')) {
      return null;
    }
    if (channel == 'marketplace' && !type.startsWith('Vehicle')) return null;
    dynamic payload = frame['data'];
    if (payload is String) {
      try {
        payload = jsonDecode(payload);
      } catch (_) {
        return null;
      }
    }
    final data = object(payload);
    if (data['type'] != type ||
        text(data['event_id']).isEmpty ||
        data['data'] is! Map) {
      return null;
    }
    return DomainSignal(
      type,
      object(data['data']),
      id: text(data['event_id']),
      channel: channel,
    );
  }
}

abstract class RealtimeTransport {
  Stream<DomainSignal> get signals;
  void identity(String? userId);
  void foreground(bool active);
  void dispose();
}

typedef SocketFactory =
    WebSocketChannel Function(Uri uri, Map<String, String> headers);

class ReverbConfig {
  const ReverbConfig({
    required this.key,
    required this.host,
    this.port = 8080,
    this.tls = false,
    this.origin = 'http://localhost',
  });
  final String key, host, origin;
  final int port;
  final bool tls;
  factory ReverbConfig.environment() => ReverbConfig(
    key: const String.fromEnvironment('REVERB_APP_KEY'),
    host: const String.fromEnvironment('REVERB_HOST', defaultValue: '') == ''
        ? Uri.parse(AppConfig.baseUrl).host
        : const String.fromEnvironment('REVERB_HOST'),
    port: const int.fromEnvironment('REVERB_PORT', defaultValue: 8080),
    tls: const bool.fromEnvironment('REVERB_TLS', defaultValue: false),
    origin: const String.fromEnvironment(
      'REVERB_ORIGIN',
      defaultValue: 'http://localhost',
    ),
  );
  Uri get uri => Uri(
    scheme: tls ? 'wss' : 'ws',
    host: host,
    port: port,
    path: '/app/$key',
    queryParameters: {
      'protocol': '7',
      'client': 'bolidemarket-flutter',
      'version': '0.8',
      'flash': 'false',
    },
  );
}

class ReverbTransport implements RealtimeTransport {
  ReverbTransport(this.api, this.config, {SocketFactory? sockets})
    : sockets =
          sockets ??
          ((uri, headers) => IOWebSocketChannel.connect(
            uri,
            headers: headers,
            connectTimeout: const Duration(seconds: 10),
            pingInterval: const Duration(seconds: 20),
          ));
  final ApiClient api;
  final ReverbConfig config;
  final SocketFactory sockets;
  final controller = StreamController<DomainSignal>.broadcast();
  final seen = <String>{};
  WebSocketChannel? socket;
  StreamSubscription<dynamic>? listener;
  Timer? retry, heartbeat;
  bool active = true, disposed = false, connecting = false;
  int generation = 0, attempt = 0;
  String? userId, session;
  @override
  Stream<DomainSignal> get signals => controller.stream;
  @override
  void identity(String? value) {
    final token = api.token;
    if (userId == value && session == token && socket != null) return;
    userId = value;
    session = token;
    attempt = 0;
    seen.clear();
    stop();
    unawaited(connect());
  }

  @override
  void foreground(bool value) {
    if (active == value) return;
    active = value;
    stop();
    if (active) {
      attempt = 0;
      unawaited(connect());
    }
  }

  void stop() {
    generation++;
    connecting = false;
    retry?.cancel();
    retry = null;
    heartbeat?.cancel();
    heartbeat = null;
    unawaited(listener?.cancel());
    listener = null;
    unawaited(socket?.sink.close());
    socket = null;
  }

  Future<void> connect() async {
    if (disposed || !active || connecting || config.key.isEmpty) return;
    if (AppConfig.environment == 'production' && !config.tls) return;
    connecting = true;
    final current = generation;
    try {
      final channel = sockets(config.uri, {'Origin': config.origin});
      socket = channel;
      listener = channel.stream.listen(
        (dynamic raw) {
          if (current != generation || disposed) return;
          unawaited(
            receive(raw, current).catchError((Object _) {
              if (current == generation) restart();
            }),
          );
        },
        onError: (Object _) {
          if (current == generation) restart();
        },
        onDone: () {
          if (current == generation) restart();
        },
      );
      await channel.ready;
      if (current != generation) return;
      connecting = false;
      heartbeat = Timer.periodic(
        const Duration(seconds: 25),
        (_) => send('pusher:ping', {}),
      );
    } catch (_) {
      if (current == generation) restart();
    }
  }

  void restart() {
    stop();
    if (disposed || !active || config.key.isEmpty) return;
    final delay = [1, 2, 4, 8, 16, 30][attempt.clamp(0, 5)];
    attempt++;
    retry = Timer(Duration(seconds: delay), () => unawaited(connect()));
  }

  void send(String event, Json data) {
    try {
      socket?.sink.add(jsonEncode({'event': event, 'data': data}));
    } catch (_) {
      restart();
    }
  }

  Future<void> receive(dynamic raw, int current) async {
    if (raw is! String || raw.length > 100000) return;
    Json frame;
    try {
      frame = object(jsonDecode(raw));
    } catch (_) {
      return;
    }
    final event = text(frame['event']);
    dynamic rawData = frame['data'];
    if (rawData is String) {
      try {
        rawData = jsonDecode(rawData);
      } catch (_) {
        return;
      }
    }
    final data = object(rawData);
    if (event == 'pusher:ping') {
      send('pusher:pong', {});
      return;
    }
    if (event == 'pusher:connection_established') {
      attempt = 0;
      send('pusher:subscribe', {'channel': 'marketplace'});
      final id = userId, token = session;
      if (id != null && token != null) {
        try {
          final result = await api.dio.post<dynamic>(
            '/broadcasting/auth',
            data: {
              'socket_id': data['socket_id'],
              'channel_name': 'private-user.$id',
            },
          );
          if (current != generation || id != userId || token != api.token) {
            return;
          }
          final auth = text(object(result.data)['auth']);
          if (auth.isNotEmpty) {
            send('pusher:subscribe', {
              'channel': 'private-user.$id',
              'auth': auth,
            });
          }
        } catch (_) {
          // Never interrupt REST/auth UI. Reconnect retries private auth when appropriate.
          if (current == generation) restart();
        }
      }
      return;
    }
    if (event == 'pusher_internal:subscription_succeeded') {
      final channel = text(frame['channel']);
      if (channel == 'marketplace' ||
          (userId != null && channel == 'private-user.$userId')) {
        controller.add(DomainSignal('Reconnected', {}, channel: channel));
      }
      return;
    }
    if (event == 'pusher:error') {
      restart();
      return;
    }
    final signal = DomainSignal.decode(frame, userId);
    if (signal == null ||
        current != generation ||
        (signal.channel.startsWith('private-') && api.token != session)) {
      return;
    }
    final key = '${signal.channel}:${signal.id}';
    if (!seen.add(key)) return;
    if (seen.length > 1000) seen.remove(seen.first);
    controller.add(signal);
  }

  @override
  void dispose() {
    disposed = true;
    stop();
    unawaited(controller.close());
  }
}
