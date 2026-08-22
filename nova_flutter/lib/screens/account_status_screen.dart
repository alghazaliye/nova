import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import '../services/api_service.dart';

class AccountStatusScreen extends StatefulWidget {
  const AccountStatusScreen({super.key});

  @override
  State<AccountStatusScreen> createState() => _AccountStatusScreenState();
}

class _AccountStatusScreenState extends State<AccountStatusScreen> {
  final TextEditingController _appealCtrl = TextEditingController();
  bool _submitting = false;
  List<dynamic> _appeals = [];
  bool _loadingAppeals = true;

  @override
  void initState() {
    super.initState();
    _loadAppeals();
  }

  Future<void> _loadAppeals() async {
    setState(() => _loadingAppeals = true);
    try {
      final res = await ApiService.get('/appeals');
      if (mounted && res['success'] == true) {
        setState(() => _appeals = res['data'] ?? []);
      }
    } catch (_) {}
    if (mounted) setState(() => _loadingAppeals = false);
  }

  Future<void> _submitAppeal() async {
    final reason = _appealCtrl.text.trim();
    if (reason.length < 5) {
      showToast(context, 'يرجى كتابة سبب الاعتراض (5 أحرف على الأقل)');
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await ApiService.post('/appeals', body: {'reason': reason});
      if (!mounted) return;
      if (res['success'] == true) {
        showToast(context, 'تم إرسال الاعتراض بنجاح');
        _appealCtrl.clear();
        _loadAppeals();
      } else {
        showToast(context, res['message'] ?? 'فشل إرسال الاعتراض');
      }
    } catch (e) {
      showToast(context, 'خطأ في الاتصال بالخادم');
    }
    if (mounted) setState(() => _submitting = false);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final status = auth.accountStatus;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        title: const Text('حالة الحساب'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _buildStatusCard(status, isDark),
            const SizedBox(height: 24),
            if (_appeals.isEmpty || _appeals.every((a) => a['status'] != 'pending'))
              _buildAppealForm(isDark)
            else
              _buildPendingMessage(isDark),
            const SizedBox(height: 24),
            _buildAppealsList(isDark),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusCard(AccountStatus? status, bool isDark) {
    final isBanned = status?.type == 'BANNED';
    final isSuspended = status?.type == 'SUSPENDED';

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isBanned ? Colors.red.withOpacity(0.1) : Colors.orange.withOpacity(0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isBanned ? Colors.red : Colors.orange),
      ),
      child: Column(
        children: [
          Icon(
            isBanned ? Icons.block : Icons.timer_outlined,
            size: 64,
            color: isBanned ? Colors.red : Colors.orange,
          ),
          const SizedBox(height: 16),
          Text(
            isBanned ? 'الحساب محظور نهائياً' : 'الحساب معلق مؤقتاً',
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          if (isSuspended && status?.suspendUntil != null) ...[
            const SizedBox(height: 8),
            Text(
              'حتى: ${status!.suspendUntil}',
              style: const TextStyle(fontSize: 16),
            ),
          ],
          if (status?.reason != null) ...[
            const SizedBox(height: 12),
            Text(
              'السبب: ${status!.reason}',
              textAlign: TextAlign.center,
              style: TextStyle(color: isDark ? Colors.white70 : Colors.black87),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAppealForm(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'تقديم طلب اعتراض',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _appealCtrl,
          maxLines: 4,
          decoration: InputDecoration(
            hintText: 'اشرح سبب اعتراضك هنا...',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            filled: true,
            fillColor: isDark ? Colors.white10 : Colors.grey[100],
          ),
        ),
        const SizedBox(height: 16),
        ElevatedButton(
          onPressed: _submitting ? null : _submitAppeal,
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          child: _submitting
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('إرسال الاعتراض'),
        ),
      ],
    );
  }

  Widget _buildPendingMessage(bool isDark) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.blue.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        children: [
          Icon(Icons.info_outline, color: Colors.blue),
          SizedBox(width: 12),
          Expanded(
            child: Text('لديك اعتراض قيد المراجعة بالفعل. سنقوم بالرد عليك قريباً.'),
          ),
        ],
      ),
    );
  }

  Widget _buildAppealsList(bool isDark) {
    if (_loadingAppeals) return const Center(child: CircularProgressIndicator());
    if (_appeals.isEmpty) return const SizedBox();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'الاعتراضات السابقة',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: _appeals.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final appeal = _appeals[index];
            final status = appeal['status'];
            final color = status == 'approved' ? Colors.green : (status == 'rejected' ? Colors.red : Colors.blue);
            
            return Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          status == 'approved' ? 'تم القبول' : (status == 'rejected' ? 'تم الرفض' : 'قيد المراجعة'),
                          style: TextStyle(color: color, fontWeight: FontWeight.bold),
                        ),
                        Text(
                          appeal['created_at']?.toString().split(' ')[0] ?? '',
                          style: const TextStyle(fontSize: 12, color: Colors.grey),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(appeal['reason'] ?? ''),
                    if (appeal['admin_note'] != null) ...[
                      const Divider(),
                      Text(
                        'رد الإدارة: ${appeal['admin_note']}',
                        style: const TextStyle(fontStyle: FontStyle.italic),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}
