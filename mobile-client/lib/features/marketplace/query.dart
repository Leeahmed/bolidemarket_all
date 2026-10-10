import '../../core/models.dart';

class SearchQuery {
  const SearchQuery({this.values = const {}});
  final Json values;
  static const sorts = [
    'distance',
    'newest',
    'price_asc',
    'price_desc',
    'year_desc',
    'mileage_asc',
  ];
  SearchQuery copy(Json changes) => SearchQuery(
    values: {...values, ...changes}
      ..removeWhere((key, value) => value == null || value == ''),
  );
  Json parameters({int page = 1}) {
    final params = {...values, 'page': page, 'per_page': 12};
    params.removeWhere((key, value) => value == null || value == '');
    final price =
        params.containsKey('min_price') ||
        params.containsKey('max_price') ||
        ['price_asc', 'price_desc'].contains(params['sort']);
    if (price &&
        (params['listing_type'] == null || params['currency'] == null)) {
      throw const FormatException(
        'Choisissez une offre et une devise pour comparer les prix.',
      );
    }
    if (params['sort'] == 'distance' &&
        (params['latitude'] == null || params['longitude'] == null)) {
      throw const FormatException(
        'Utilisez votre position pour trier par proximité.',
      );
    }
    if (params['sort'] != null && !sorts.contains(params['sort'])) {
      throw const FormatException('Tri non supporté.');
    }
    return params;
  }
}
