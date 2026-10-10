// Convert user-entered major units to the API integer contract without doubles.
String budgetMinor(String input, int exponent) {
  final value = input
      .replaceAll(' ', '')
      .replaceAll('\u00a0', '')
      .replaceAll(',', '.');
  if (!RegExp(r'^\d+(\.\d+)?$').hasMatch(value)) {
    throw const FormatException('Saisissez un montant positif.');
  }
  final parts = value.split('.');
  final decimals = parts.length == 2 ? parts[1] : '';
  if (decimals.length > exponent) {
    throw FormatException('Cette devise accepte $exponent décimales.');
  }
  return (BigInt.parse(parts[0]) * BigInt.from(10).pow(exponent) +
          (decimals.isEmpty
              ? BigInt.zero
              : BigInt.parse(decimals.padRight(exponent, '0'))))
      .toString();
}

String budgetMajor(String input, int exponent) {
  final amount = BigInt.parse(input), divisor = BigInt.from(10).pow(exponent);
  final remainder = amount.remainder(divisor);
  return '${amount ~/ divisor}${remainder == BigInt.zero ? '' : '.${remainder.toString().padLeft(exponent, '0')}'}';
}
