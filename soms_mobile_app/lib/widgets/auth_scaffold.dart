// lib/widgets/auth_scaffold.dart
import 'package:flutter/material.dart';

import '../core/app_theme.dart';

/// Mobile version of the web's guest layout (guest-layout.blade.php): a white
/// card centered over a navy backdrop with soft blue glows, the logo tile on
/// top, and the screen's content below it. Used by login, register and
/// change-password so all three look like the web auth pages.
/// Shows a white back arrow automatically when the screen was pushed.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.navy,
      body: Stack(
        children: [
          const Positioned.fill(child: _MeshBackground()),
          SafeArea(
            child: Stack(
              children: [
                Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 24),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 400),
                      child: Container(
                        padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.circular(AppTheme.radiusLg),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF040C28)
                                  .withValues(alpha: .45),
                              blurRadius: 60,
                              offset: const Offset(0, 24),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Center(child: _LogoTile()),
                            const SizedBox(height: 22),
                            child,
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                if (Navigator.of(context).canPop())
                  const Align(
                    alignment: Alignment.topLeft,
                    child: Padding(
                        padding: EdgeInsets.all(8),
                        child: BackButton(color: Colors.white)),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Three soft radial glows at the same positions/colors as the web's .mesh layer.
class _MeshBackground extends StatelessWidget {
  const _MeshBackground();

  @override
  Widget build(BuildContext context) {
    Widget glow(Alignment center, Color color) => Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(
                center: center,
                radius: 0.95,
                colors: [color, color.withValues(alpha: 0)],
                stops: const [0, 1],
              ),
            ),
          ),
        );

    return Stack(
      children: [
        glow(const Alignment(-0.64, -0.56),
            AppColors.primary.withValues(alpha: .55)),
        glow(const Alignment(0.64, -0.64),
            AppColors.primaryLight.withValues(alpha: .40)),
        glow(const Alignment(0, 0.76),
            AppColors.navyLight.withValues(alpha: .90)),
      ],
    );
  }
}

class _LogoTile extends StatelessWidget {
  const _LogoTile();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 64,
      height: 64,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
          color: AppColors.navy, borderRadius: BorderRadius.circular(16)),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.asset('assets/images/logo.png', fit: BoxFit.cover),
      ),
    );
  }
}

/// Title + muted subtitle at the top of an auth form (web: .auth-card h2 + .sub).
class AuthHeader extends StatelessWidget {
  const AuthHeader({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                letterSpacing: -.3,
                color: AppColors.text,
              ),
        ),
        const SizedBox(height: 6),
        Text(subtitle,
            style: const TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const SizedBox(height: 24),
      ],
    );
  }
}

/// Inline error box (web: .alert-error).
class AuthErrorBanner extends StatelessWidget {
  const AuthErrorBanner({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.rose.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: AppColors.rose.withValues(alpha: .30)),
      ),
      child: Text(message,
          style: const TextStyle(color: AppColors.roseText, fontSize: 12.5)),
    );
  }
}

/// Blue gradient primary button with soft glow (web: .btn-primary).
/// Pass `loading: true` while submitting; set `onPressed: null` to disable.
class GradientButton extends StatelessWidget {
  const GradientButton(
      {super.key,
      required this.label,
      required this.onPressed,
      this.loading = false});

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null ? .65 : 1,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.primary, AppColors.primaryLight],
          ),
          borderRadius: BorderRadius.circular(AppTheme.radiusMd),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: .28),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
            onTap: onPressed,
            child: SizedBox(
              height: 46,
              child: Center(
                child: loading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : Text(
                        label,
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w600,
                            fontSize: 14),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
