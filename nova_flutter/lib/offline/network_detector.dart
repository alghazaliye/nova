// Network connectivity manager.
// Detects: Online / Offline / Server-unreachable (internet OK but backend down).
// Uses connectivity_plus for network, plus a lightweight HTTP health probe.
import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:nova_flutter/services/api_service.dart';

enum NovaNetworkState {
  online, // network + server reachable
  serverDown, // network OK, health check fails
  offline, // no network
}

/// Lightweight provider exposing network state as a ValueNotifier.
class NetworkDetector extends ChangeNotifier {
  NetworkDetector._();
  static final NetworkDetector instance = NetworkDetector._()..start();

  /// Start listening to connectivity and probing server health.
  void start() {
    Connectivity().onConnectivityChanged.listen((_) async {
      await _probe();
    });
    _timer = Timer.periodic(const Duration(seconds: 30), (_) async {
      await _probe();
    });
  }

  late final Timer _timer;
  NovaNetworkState _state = NovaNetworkState.online;
  bool _serverReachable = true;
  bool get serverReachable => _serverReachable;

  NovaNetworkState get state => _state;

  set state(NovaNetworkState s) {
    if (_state != s) {
      _state = s;
      notifyListeners();
    }
  }

  Future<void> _probe() async {
    // Network availability
    final results = await Connectivity().checkConnectivity();
    final hasNetwork = results.any(
      (r) => r != ConnectivityResult.none,
    );
    if (!hasNetwork) {
      _serverReachable = false;
      state = NovaNetworkState.offline;
      return;
    }
    // Server reachability via lightweight health endpoint (10s timeout)
    try {
      final res = await ApiService.get('/health');
      final ok = res['success'] == true || res['data'] != null;
      _serverReachable = ok;
      state = ok ? NovaNetworkState.online : NovaNetworkState.serverDown;
    } catch (_) {
      _serverReachable = false;
      state = NovaNetworkState.serverDown;
    }
  }

  /// Manual probe triggered by refresh.
  Future<void> probeNow() async => _probe();

  bool get isUsable => _state == NovaNetworkState.online;

  String get label {
    switch (_state) {
      case NovaNetworkState.online:
        return 'متصل';
      case NovaNetworkState.serverDown:
        return 'الخادم غير متاح';
      case NovaNetworkState.offline:
        return 'غير متصل';
    }
  }

  void disposeTimer() {
    _timer.cancel();
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }
}
