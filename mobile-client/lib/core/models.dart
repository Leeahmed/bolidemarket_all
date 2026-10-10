typedef Json = Map<String, dynamic>;
Json object(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
String text(dynamic value) => value?.toString() ?? '';
String place(Json location) => [
  object(location['district'])['name'],
  object(location['city'])['name'],
  object(location['country'])['name'],
].where((v) => v != null && v != '').take(2).join(', ');

class Money {
  Money(this.amount, this.currency, this.minorUnit);
  final BigInt amount;
  final String currency;
  final int minorUnit;
  factory Money.fromJson(Json json) => Money(
    BigInt.parse(text(json['amount_minor'])),
    text(json['currency']),
    json['minor_unit'] as int,
  );
  String format() {
    final divisor = BigInt.from(10).pow(minorUnit);
    final whole = (amount ~/ divisor).toString();
    final separator = ['USD', 'CAD'].contains(currency) ? ',' : ' ';
    final grouped = whole.replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (_) => separator,
    );
    final remainder = amount.remainder(divisor);
    final decimals = remainder == BigInt.zero
        ? ''
        : '${['USD', 'CAD'].contains(currency) ? '.' : ','}${remainder.toString().padLeft(minorUnit, '0')}';
    final number = '$grouped$decimals';
    return switch (currency) {
      'XOF' => '$number FCFA',
      'EUR' => '$number €',
      'USD' => '\$$number',
      'CAD' => 'CA\$$number',
      _ => '$number $currency',
    };
  }
}

class AppUser {
  AppUser(this.json) {
    if (text(json['id']).isEmpty || text(json['email']).isEmpty) {
      throw const FormatException('Identité serveur invalide.');
    }
  }
  final Json json;
  String get id => text(json['id']);
  String get firstName => text(json['first_name']);
  String get name =>
      '${text(json['first_name'])} ${text(json['last_name'])}'.trim();
  String get country => text(json['country_code']);
  String get currency => text(object(json['country'])['currency_code']);
  String get location => [
    object(json['district'])['name'],
    object(json['city'])['name'],
    object(json['country'])['name'],
  ].where((v) => v != null).take(2).join(', ');
}

class Vehicle {
  Vehicle(this.json);
  final Json json;
  String get id => text(json['id']);
  String get slug => text(json['slug']);
  String get title =>
      '${text(object(json['brand'])['name'])} ${text(object(json['model'])['name'])}'
          .trim();
  String get location => place(object(json['location']));
  bool get sold => json['inventory_status'] == 'sold';
  bool get demo => json['is_demo'] == true;
  bool get sale => json['sale_price'] != null;
  bool get rental => json['rental_daily_price'] != null;
  String get shopName => text(object(json['shop'])['name']);
  String get shopSlug => text(object(json['shop'])['slug']);
  Money? price(String offer) {
    final data = json[offer == 'rental' ? 'rental_daily_price' : 'sale_price'];
    return data == null ? null : Money.fromJson(object(data));
  }

  List<Json> get images => (json['images'] as List? ?? []).map(object).toList();
}

class PageData<T> {
  PageData(this.items, this.page, this.lastPage, this.total);
  final List<T> items;
  final int page, lastPage, total;
  bool get hasMore => page < lastPage;
  factory PageData.fromJson(Json json, T Function(Json) decode) {
    final meta = object(json['meta']);
    return PageData(
      (json['data'] as List).map((e) => decode(object(e))).toList(),
      meta['current_page'] as int? ?? 1,
      meta['last_page'] as int? ?? 1,
      meta['total'] as int? ?? 0,
    );
  }
}
