// Web stub for the offline database.
// Drift + WasmDatabase requires sqlite3.wasm + drift_worker.js assets which
// cause hangs on some web deployments, and the offline sync layer is mobile-only
// by design (the web app is always connected). On the web we provide a
// lightweight no-op executor so all call sites keep working without WASM.
import 'package:drift/drift.dart';
import 'local_nova_db.dart';

class _NoopTransactionExecutor extends _WebExecutor
    implements TransactionExecutor {
  @override
  bool get supportsNestedTransactions => false;

  @override
  Future<void> send() async {}

  @override
  Future<void> rollback() async {}
}

class _WebExecutor extends QueryExecutor {
  @override
  SqlDialect get dialect => SqlDialect.sqlite;

  @override
  Future<bool> ensureOpen(QueryExecutorUser user) async => true;

  @override
  Future<List<Map<String, Object?>>> runSelect(
      String statement, List<Object?> args) async {
    return const <Map<String, Object?>>[];
  }

  @override
  Future<int> runInsert(String statement, List<Object?> args) async => -1;

  @override
  Future<int> runUpdate(String statement, List<Object?> args) async => 0;

  @override
  Future<int> runDelete(String statement, List<Object?> args) async => 0;

  @override
  Future<void> runCustom(String statement, [List<Object?>? args]) async {}

  @override
  Future<void> runBatched(BatchedStatements statements) async {}

  @override
  TransactionExecutor beginTransaction() => _NoopTransactionExecutor();

  @override
  QueryExecutor beginExclusive() => this;

  @override
  Future<void> close() async {}
}

/// Singleton access to the local offline database on the web.
///
/// Mobile uses SQLite via NativeDatabase (local_nova_db_provider_mobile.dart),
/// while the web uses this stub so the app never depends on WASM assets.
class LocalNovaDbProvider {
  static LocalNovaDb? _instance;

  static Future<LocalNovaDb> get db async {
    if (_instance != null) return _instance!;
    _instance = LocalNovaDb(_WebExecutor());
    return _instance!;
  }

  /// Wipe local data at logout — no-op on web (no persistent store).
  static Future<void> wipeLocalData() async {
    final d = _instance;
    _instance = null;
    try {
      await d?.close();
    } catch (_) {}
  }
}
