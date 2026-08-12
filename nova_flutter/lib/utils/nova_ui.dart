import 'dart:ui' show ImageFilter;
import 'package:flutter/material.dart';

/* ═══════════════════════ الألوان (فاتح / داكن) ═══════════════════════ */
class NovaColors {
  const NovaColors({
    required this.bg, required this.surface, required this.surface2,
    required this.text, required this.muted, required this.line,
    required this.accent, required this.accent2, required this.bubble,
    required this.mine, required this.mineText,
  });

  final Color bg, surface, surface2, text, muted, line;
  final Color accent, accent2, bubble, mine, mineText;
  Color get green => const Color(0xFF22C55E);
  Color get red => const Color(0xFFEF4444);

  static const NovaColors light = NovaColors(
    bg: Color(0xFFF5F7FB), surface: Colors.white, surface2: Color(0xFFEEF2F7),
    text: Color(0xFF101828), muted: Color(0xFF667085), line: Color(0xFFE5E7EB),
    accent: Color(0xFF5B5CE2), accent2: Color(0xFF7C3AED),
    bubble: Color(0xFFE9E8FF), mine: Color(0xFF5B5CE2), mineText: Colors.white,
  );
  static const NovaColors dark = NovaColors(
    bg: Color(0xFF080D18), surface: Color(0xFF111827), surface2: Color(0xFF1B2535),
    text: Color(0xFFF3F4F6), muted: Color(0xFF98A2B3), line: Color(0xFF263244),
    accent: Color(0xFF8B7CF7), accent2: Color(0xFFA78BFA),
    bubble: Color(0xFF202A3B), mine: Color(0xFF6758E8), mineText: Colors.white,
  );

  static NovaColors of(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark ? dark : light;
}

ThemeData buildNovaTheme(Brightness b, NovaColors c) {
  final scheme = ColorScheme.fromSeed(seedColor: c.accent, brightness: b).copyWith(
    primary: c.accent, onPrimary: Colors.white,
    secondary: c.accent2, onSecondary: Colors.white,
    surface: c.surface, onSurface: c.text, outline: c.line,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: c.bg,
    fontFamily: 'Cairo',
    splashColor: c.accent.withOpacity(0.06),
  );
}

/* ═══════════════════════ مكوّنات مشتركة ═══════════════════════ */
void pushScreen(BuildContext context, Widget screen) =>
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));

Widget novaTopBar(BuildContext context,
    {Widget? leading, required String title, List<Widget> actions = const []}) {
  final c = NovaColors.of(context);
  return Container(
    color: c.surface,
    child: SafeArea(
      bottom: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 13, 18, 13),
        decoration: BoxDecoration(border: Border(bottom: BorderSide(color: c.line))),
        child: Row(
          children: [
            if (leading != null) ...[leading, const SizedBox(width: 10)],
            Expanded(
              child: Text(title, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: c.text)),
            ),
            ...actions,
          ],
        ),
      ),
    ),
  );
}

class PressScale extends StatefulWidget {
  const PressScale({super.key, required this.child, this.onTap, this.scale = 0.94});
  final Widget child;
  final VoidCallback? onTap;
  final double scale;
  @override
  State<PressScale> createState() => _PressScaleState();
}

class _PressScaleState extends State<PressScale> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) => setState(() => _pressed = false),
      onTapCancel: () => setState(() => _pressed = false),
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _pressed ? widget.scale : 1,
        duration: const Duration(milliseconds: 120),
        child: widget.child,
      ),
    );
  }
}

class NovaAvatar extends StatelessWidget {
  const NovaAvatar({super.key, required this.letter, this.size = 54, this.online = false, this.radius = 18, this.verified = false});
  final String letter;
  final double size, radius;
  final bool online, verified;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(radius),
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFFD8D5FF), Color(0xFF9CA3FF)],
              ),
            ),
            child: Center(
              child: Text(letter.isNotEmpty ? letter : '?',
                  style: TextStyle(
                      fontSize: size * 0.38,
                      fontWeight: FontWeight.w900,
                      color: const Color(0xFF4338CA))),
            ),
          ),
          if (online)
            Positioned(
              bottom: -1,
              left: -1,
              child: Container(
                width: 14,
                height: 14,
                decoration: BoxDecoration(
                  color: c.green,
                  shape: BoxShape.circle,
                  border: Border.all(color: c.bg, width: 3),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class UnreadBadge extends StatelessWidget {
  const UnreadBadge({super.key, required this.count});
  final int count;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Container(
      constraints: const BoxConstraints(minWidth: 21),
      height: 21,
      padding: const EdgeInsets.symmetric(horizontal: 6),
      decoration: BoxDecoration(color: c.accent, borderRadius: BorderRadius.circular(9)),
      child: Center(
        child: Text('$count',
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white)),
      ),
    );
  }
}

class TabChip extends StatelessWidget {
  const TabChip({super.key, required this.label, this.active = false, this.onTap});
  final String label;
  final bool active;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return PressScale(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
        decoration: BoxDecoration(
          color: active ? c.accent : c.surface2,
          borderRadius: BorderRadius.circular(13),
          boxShadow: active
              ? [BoxShadow(color: c.accent.withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 4))]
              : null,
        ),
        child: Text(label,
            style: TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: active ? Colors.white : c.muted)),
      ),
    );
  }
}

class IconBtn extends StatelessWidget {
  const IconBtn({super.key, required this.icon, this.onTap, this.size = 21, this.color});
  final IconData icon;
  final VoidCallback? onTap;
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return PressScale(
      onTap: onTap,
      child: SizedBox(
        width: 42,
        height: 42,
        child: Center(child: Icon(icon, size: size, color: color ?? c.text)),
      ),
    );
  }
}

class NovaCard extends StatelessWidget {
  const NovaCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.margin = EdgeInsets.zero,
    this.onTap,
  });
  final Widget child;
  final EdgeInsetsGeometry padding, margin;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final card = Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: c.surface,
        border: Border.all(color: c.line),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 18, offset: const Offset(0, 5))],
      ),
      child: child,
    );
    return onTap == null ? card : PressScale(onTap: onTap, child: card);
  }
}

class RowItem extends StatelessWidget {
  const RowItem({
    super.key,
    required this.title,
    this.subtitle,
    this.leading,
    this.trailing,
    this.onTap,
    this.last = false,
  });
  final String title;
  final String? subtitle;
  final Widget? leading, trailing;
  final VoidCallback? onTap;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return PressScale(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 4),
        decoration: BoxDecoration(border: last ? null : Border(bottom: BorderSide(color: c.line))),
        child: Row(
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: 12)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: c.text)),
                  if (subtitle != null) ...[
                    const SizedBox(height: 3),
                    Text(subtitle!, style: TextStyle(fontSize: 12, color: c.muted)),
                  ],
                ],
              ),
            ),
            if (trailing != null) trailing!,
          ],
        ),
      ),
    );
  }
}

class ListIconBox extends StatelessWidget {
  const ListIconBox({super.key, required this.icon});
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(14)),
      child: Icon(icon, size: 20, color: c.text),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(5, 18, 5, 8),
      child: Text(text, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: c.muted)),
    );
  }
}

class NovaSwitch extends StatelessWidget {
  const NovaSwitch({super.key, required this.value, required this.onChanged});
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return PressScale(
      onTap: () => onChanged(!value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: 46,
        height: 27,
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(color: value ? c.accent : c.line, borderRadius: BorderRadius.circular(20)),
        child: AnimatedAlign(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          alignment: value ? const Alignment(-1, 0) : const Alignment(1, 0),
          child: Container(
            width: 21,
            height: 21,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
          ),
        ),
      ),
    );
  }
}

/// شريط التنقل السفلي الزجاجي (5 تبويبات)
class NovaBottomNav extends StatelessWidget {
  const NovaBottomNav({super.key, required this.index, required this.onTap});
  final int index;
  final ValueChanged<int> onTap;

  static const items = [
    ('المحادثات', Icons.chat_bubble_outline, Icons.chat_bubble),
    ('الحالة', Icons.circle_outlined, Icons.circle),
    ('المكالمات', Icons.call_outlined, Icons.call),
    ('الإعدادات', Icons.settings_outlined, Icons.settings),
  ];

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
        child: Container(
          decoration: BoxDecoration(
            color: c.surface.withOpacity(0.92),
            border: Border(top: BorderSide(color: c.line)),
          ),
          padding: EdgeInsets.fromLTRB(8, 9, 8, MediaQuery.paddingOf(context).bottom + 9),
          child: Row(
            children: [
              for (int i = 0; i < items.length; i++)
                Expanded(
                  child: PressScale(
                    onTap: () => onTap(i),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: const EdgeInsets.symmetric(vertical: 7),
                      decoration: BoxDecoration(
                        color: index == i ? c.surface2 : Colors.transparent,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(index == i ? items[i].$3 : items[i].$2,
                              size: 21, color: index == i ? c.accent : c.muted),
                          const SizedBox(height: 3),
                          Text(items[i].$1,
                              style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: index == i ? c.accent : c.muted)),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
