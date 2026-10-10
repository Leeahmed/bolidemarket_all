import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import '../../core/api.dart';
import '../../core/providers.dart';
import 'providers.dart';
import 'repository.dart';

final pdfFilesProvider = Provider(
  (ref) => PdfFiles(
    ref.watch(receiptRepositoryProvider),
    () => ref.read(apiProvider).token,
  ),
);

class PdfFiles {
  PdfFiles(this.repo, this.session, {Future<Directory> Function()? directory})
    : directory = directory ?? getApplicationDocumentsDirectory;
  final ReceiptRepository repo;
  final String? Function() session;
  final Future<Directory> Function() directory;
  final sessions = <String, String>{};
  static final safeReference = RegExp(r'^BM-RCP-[A-Za-z0-9-]+$');
  Future<File> download(String reference) async {
    if (!safeReference.hasMatch(reference)) {
      throw const ApiFailure('Référence de reçu invalide.');
    }
    final token = session();
    if (token == null) {
      throw const ApiFailure(
        'Connectez-vous pour télécharger votre reçu.',
        status: 401,
      );
    }
    final bytes = await repo.pdf(reference);
    if (token != session()) {
      throw const ApiFailure('Votre session a changé. Réessayez.', status: 401);
    }
    final root = await directory();
    final folder = Directory('${root.path}/bolidemarket-receipts');
    await folder.create(recursive: true);
    final file = File('${folder.path}/BolideMarket_$reference.pdf');
    await file.writeAsBytes(bytes, flush: true);
    if (token != session()) {
      await file.delete();
      throw const ApiFailure('Votre session a changé.', status: 401);
    }
    sessions[file.path] = token;
    return file;
  }

  Future<void> clear() async {
    sessions.clear();
    // Only files created by this service, in its exact app-owned directory.
    final folder = Directory(
      '${(await directory()).path}/bolidemarket-receipts',
    );
    if (!await folder.exists()) return;
    await for (final entity in folder.list(followLinks: false)) {
      if (entity is File &&
          RegExp(
            r'^BolideMarket_BM-RCP-[A-Za-z0-9-]+\.pdf$',
          ).hasMatch(entity.uri.pathSegments.last)) {
        await entity.delete();
      }
    }
  }

  Future<void> share(File file, Rect origin) async {
    if (session() == null || sessions[file.path] != session()) {
      throw const ApiFailure(
        'Connectez-vous pour partager votre reçu.',
        status: 401,
      );
    }
    await SharePlus.instance.share(
      ShareParams(
        files: [XFile(file.path, mimeType: 'application/pdf')],
        subject: 'Reçu de démonstration BolideMarket',
        sharePositionOrigin: origin,
      ),
    );
  }
}
