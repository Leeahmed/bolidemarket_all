import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers.dart';
import 'providers.dart';
import 'pdf_files_io.dart' if (dart.library.js_interop) 'pdf_files_web.dart';
export 'pdf_files_io.dart' if (dart.library.js_interop) 'pdf_files_web.dart';

final pdfFilesProvider = Provider(
  (ref) => PdfFiles(
    ref.watch(receiptRepositoryProvider),
    () => ref.read(apiProvider).token,
  ),
);
