import 'package:flutter/material.dart';
import '../utils/nova_ui.dart';
import '../services/api_service.dart';

/// طبقة المكالمة الواردة: شاشة كاملة بتدرج Nova مع أزرار قبول/رفض.
/// [call]: بيانات المكالمة الواردة من GET /calls/incoming.
class IncomingCallOverlay extends StatefulWidget {
  const IncomingCallOverlay({
    super.key,
    required this.call,
    required this.onAnswer,
    required this.onReject,
  });

  final Map<String, dynamic> call;
  final VoidCallback onAnswer;
  final VoidCallback onReject;

  @override
  State<IncomingCallOverlay> createState() => _IncomingCallOverlayState();
}

class _IncomingCallOverlayState extends State<IncomingCallOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ringCtrl;

  @override
  void initState() {
    super.initState();
    _ringCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _ringCtrl.dispose();
    super.dispose();
  }

  String get _callerName {
    final n = widget.call['caller_name'] ??
        widget.call['peer_name'] ??
        widget.call['name'] ??
        '';
    return n.toString();
  }

  String? get _callerAvatar {
    final a = widget.call['caller_avatar'] ?? widget.call['avatar'];
    return (a == null || a.toString().isEmpty)
        ? null
        : ApiService.mediaUrl(a.toString());
  }

  String get _callerType {
    final t = (widget.call['call_type'] ?? 'voice').toString().toLowerCase();
    return t == 'video' ? 'مكالمة فيديو واردة' : 'مكالمة صوتية واردة';
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final letter = _callerName.isNotEmpty ? _callerName[0] : '?';
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [c.accent, c.accent2],
        ),
      ),
      child: SafeArea(
        child: Column(
          children: [
            const SizedBox(height: 90),
            AnimatedBuilder(
              animation: _ringCtrl,
              builder: (_, __) {
                return Transform.scale(
                  scale: 0.96 + _ringCtrl.value * 0.08,
                  child: NovaAvatar(
                    letter: letter,
                    size: 110,
                    radius: 36,
                    imageUrl: _callerAvatar,
                  ),
                );
              },
            ),
            const SizedBox(height: 22),
            Text(_callerName,
                style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: Colors.white)),
            const SizedBox(height: 6),
            Text(_callerType,
                style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    color: Colors.white.withOpacity(0.85))),
            const Spacer(),
            Text('يتصل…',
                style: TextStyle(
                    fontSize: 13, color: Colors.white.withOpacity(0.75))),
            const SizedBox(height: 26),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _ActionCircle(
                  icon: Icons.call_end,
                  color: const Color(0xFFEF4444),
                  label: 'رفض',
                  onTap: widget.onReject,
                ),
                const SizedBox(width: 70),
                _ActionCircle(
                  icon: Icons.call,
                  color: const Color(0xFF22C55E),
                  label: 'قبول',
                  onTap: widget.onAnswer,
                ),
              ],
            ),
            const SizedBox(height: 56),
          ],
        ),
      ),
    );
  }
}

class _ActionCircle extends StatelessWidget {
  const _ActionCircle({
    required this.icon,
    required this.color,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        GestureDetector(
          onTap: onTap,
          behavior: HitTestBehavior.opaque,
          child: Container(
            width: 68,
            height: 68,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                    color: color.withOpacity(0.45),
                    blurRadius: 24,
                    offset: const Offset(0, 8)),
              ],
            ),
            child: Icon(icon, size: 32, color: Colors.white),
          ),
        ),
        const SizedBox(height: 10),
        Text(label,
            style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: Colors.white)),
      ],
    );
  }
}
