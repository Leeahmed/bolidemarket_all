import 'package:phone_numbers_parser/phone_numbers_parser.dart';

String normalizePhone(String input, String country) {
  final iso = IsoCode.values.byName(country.toUpperCase());
  final phone = PhoneNumber.parse(input, callerCountry: iso);
  if (!phone.isValid() || phone.isoCode != iso) {
    throw const FormatException('Numéro invalide pour le pays sélectionné.');
  }
  return phone.international;
}
