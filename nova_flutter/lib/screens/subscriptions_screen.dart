import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';

class SubscriptionsScreen extends StatefulWidget {
  const SubscriptionsScreen({super.key});

  @override
  State<SubscriptionsScreen> createState() => _SubscriptionsScreenState();
}

class _SubscriptionsScreenState extends State<SubscriptionsScreen> {
  bool _loading = true;
  List<dynamic> _plans = [];
  Map<String, dynamic>? _myStatus;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final plansRes = await ApiService.get('/plans');
      final statusRes = await ApiService.get('/subscriptions/my');
      
      if (mounted) {
        setState(() {
          if (plansRes['success'] == true) _plans = plansRes['data'] ?? [];
          if (statusRes['success'] == true) _myStatus = statusRes['data'];
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'خطأ في تحميل البيانات';
          _loading = false;
        });
      }
    }
  }

  Future<void> _requestSubscription(int planId) async {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تأكيد الطلب'),
        content: const Text('هل ترغب في إرسال طلب اشتراك لهذه الباقة؟ سيقوم المشرف بمراجعة طلبك وتفعيل الحساب.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('إلغاء')),
          TextButton(
            onPressed: () async {
              Navigator.pop(c);
              final res = await ApiService.post('/subscriptions/request', body: {'plan_id': planId});
              if (mounted) {
                if (res['success'] == true) {
                  showToast(context, 'تم إرسال الطلب بنجاح');
                  _loadData();
                } else {
                  showToast(context, res['message'] ?? 'فشل إرسال الطلب');
                }
              }
            },
            child: const Text('تأكيد'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الباقات والاشتراكات')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(_error!, style: TextStyle(color: c.text)),
                    TextButton(onPressed: _loadData, child: const Text('إعادة المحاولة')),
                  ],
                ))
              : RefreshIndicator(
                  onRefresh: _loadData,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _buildCurrentSubscription(c),
                      const SizedBox(height: 24),
                      Text('الباقات المتاحة', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: c.text)),
                      const SizedBox(height: 12),
                      ..._plans.map((p) => _buildPlanCard(p, c)),
                    ],
                  ),
                ),
    );
  }

  Widget _buildCurrentSubscription(NovaColors c) {
    final subs = _myStatus?['subscriptions'] as List?;
    final activeSub = subs?.firstWhere((s) => s['status'] == 'active', orElse: () => null);
    final requests = _myStatus?['payment_requests'] as List?;
    final pendingReq = requests?.firstWhere((r) => r['status'] == 'pending', orElse: () => null);

    return NovaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('حالة الاشتراك الحالي', style: TextStyle(fontWeight: FontWeight.bold, color: c.text)),
          const SizedBox(height: 12),
          if (activeSub != null) ...[
            _infoRow('الباقة الحالية', activeSub['plan_name'] ?? 'نشطة'),
            _infoRow('تاريخ الانتهاء', activeSub['expires_at'] ?? 'مدى الحياة'),
            _infoRow('التوثيق', (int.tryParse(activeSub['is_verified']?.toString() ?? '0') ?? 0) == 1 ? 'موثق ✓' : 'غير مفعل'),
          ] else if (pendingReq != null) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.blue.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
              child: const Row(
                children: [
                  Icon(Icons.hourglass_empty, color: Colors.blue, size: 20),
                  SizedBox(width: 8),
                  Expanded(child: Text('لديك طلب اشتراك قيد المراجعة حالياً')),
                ],
              ),
            ),
          ] else
            const Text('لا يوجد اشتراك نشط حالياً', style: TextStyle(color: Colors.grey)),
        ],
      ),
    );
  }

  Widget _buildPlanCard(Map<String, dynamic> plan, NovaColors c) {
    final features = plan['features'] is String ? plan['features'].split('\n') : (plan['features'] as List? ?? []);
    final isFree = (double.tryParse(plan['price']?.toString() ?? '0') ?? 0) == 0;

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(plan['name'] ?? '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                if (plan['enable_verification'] == 1)
                  const Icon(Icons.verified, color: Colors.blue, size: 24),
              ],
            ),
            const SizedBox(height: 8),
            Text(plan['description'] ?? '', style: TextStyle(color: c.muted)),
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(isFree ? 'مجاناً' : '${plan['price']} ${plan['currency']}', 
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Colors.blue)),
                if (!isFree)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4, right: 4),
                    child: Text('/ ${plan['period'] == 'monthly' ? 'شهر' : (plan['period'] == 'yearly' ? 'سنة' : 'دائم')}', 
                        style: TextStyle(color: c.muted, fontSize: 14)),
                  ),
              ],
            ),
            const Divider(height: 32),
            ...features.map((f) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  const Icon(Icons.check_circle, color: Colors.green, size: 18),
                  const SizedBox(width: 8),
                  Text(f.toString()),
                ],
              ),
            )),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: isFree ? null : () => _requestSubscription(plan['id']),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(isFree ? 'باقة افتراضية' : 'طلب الاشتراك'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
