// Cross-platform stub — selects the web or mobile implementation.
export 'local_nova_db_provider_web.dart'
    if (dart.library.io) 'local_nova_db_provider_mobile.dart';
