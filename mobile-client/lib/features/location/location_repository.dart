import 'package:geolocator/geolocator.dart';
import '../../core/models.dart';

class LocationRepository {
  Future<Json> locate() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const FormatException(
        'Activez la localisation ou choisissez une ville.',
      );
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const FormatException(
        'Localisation refusée. Vous pouvez choisir une ville.',
      );
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
        timeLimit: Duration(seconds: 15),
      ),
    );
    return {'latitude': position.latitude, 'longitude': position.longitude};
  }
}
