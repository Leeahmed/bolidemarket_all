{{flutter_js}}
{{flutter_build_config}}

// Keep the renderer local as well: visual QA does not require a CDN connection.
_flutter.loader.load({config: {canvasKitBaseUrl: "canvaskit/"}});
