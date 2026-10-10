import 'dart:js_interop';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:web/web.dart' as web;
import '../../core/api.dart';
import 'repository.dart';

class WebReceiptFile {
  const WebReceiptFile(this.bytes, this.name, this.token);
  final Uint8List bytes;
  final String name, token;
}

/// Web exports bytes to the browser; Android/iOS keep their private-file service.
class PdfFiles {
  PdfFiles(this.repo, this.session);
  String get downloadMessage =>
      'PDF exporté vers les téléchargements du navigateur.';
  String? get shareMessage =>
      'Sur le Web, le PDF est téléchargé. Partagez-le ou imprimez-le depuis votre navigateur.';
  final ReceiptRepository repo;
  final String? Function() session;
  final files = <WebReceiptFile>{};
  static final safeReference = RegExp(r'^BM-RCP-[A-Za-z0-9-]+$');
  Future<WebReceiptFile> download(String reference) async {
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
    if (session() != token) {
      throw const ApiFailure('Votre session a changé.', status: 401);
    }
    final file = WebReceiptFile(
      Uint8List.fromList(bytes),
      'BolideMarket_$reference.pdf',
      token,
    );
    files.add(file);
    export(file);
    return file;
  }

  void export(WebReceiptFile file) {
    if (session() != file.token || !files.contains(file)) {
      throw const ApiFailure('Votre session a changé.', status: 401);
    }
    final blob = web.Blob(
      [file.bytes.toJS].toJS,
      web.BlobPropertyBag(type: 'application/pdf'),
    );
    final url = web.URL.createObjectURL(blob);
    final anchor = web.HTMLAnchorElement()
      ..href = url
      ..download = file.name;
    web.document.body?.append(anchor);
    anchor.click();
    anchor.remove();
    Future.delayed(
      const Duration(seconds: 1),
      () => web.URL.revokeObjectURL(url),
    );
  }

  Future<void> clear() async {
    files.clear();
  }

  Future<void> share(WebReceiptFile file, Rect origin) async {
    if (session() != file.token || !files.contains(file)) {
      throw const ApiFailure('Votre session a changé.', status: 401);
    }
    // download already exported it; web fallback avoids a second download.
  }
}
