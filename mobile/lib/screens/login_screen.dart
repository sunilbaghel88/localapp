import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../widgets/app_brand_logo.dart';

const _buttonGrey = Color(0xFF8E8E8E);

class LoginScreen extends StatelessWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Colors.white,
      body: _SmsLoginBody(),
    );
  }
}

class _SmsLoginBody extends StatefulWidget {
  const _SmsLoginBody();

  @override
  State<_SmsLoginBody> createState() => _SmsLoginBodyState();
}

class _SmsLoginBodyState extends State<_SmsLoginBody> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();
  bool _otpRequested = false;
  bool _showPasswordLogin = false;

  // Password login (secondary)
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _sendOtp() async {
    final phone = _phoneController.text.trim();
    if (phone.isEmpty || phone.length < 10) {
      _formKey.currentState?.validate();
      return;
    }
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.requestSmsOtp(phone, purpose: 'login');
    if (!mounted) return;
    if (ok) {
      setState(() => _otpRequested = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('OTP sent to your mobile number')),
      );
    }
  }

  Future<void> _verifyOtp() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.loginWithSmsOtp(
      _phoneController.text.trim(),
      _otpController.text.trim(),
    );
    if (!mounted) return;
    if (ok) context.go('/home');
  }

  Future<void> _submitPasswordLogin() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.login(_emailController.text.trim(), _passwordController.text);
    if (!mounted) return;
    if (ok) context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final brandColor = Theme.of(context).colorScheme.primary;
    // Shorter white logo area so the colored panel fully wraps the form fields.
    final topHeight = size.height * 0.28;

    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        return Stack(
          children: [
            Positioned.fill(
              child: Column(
                children: [
                  SizedBox(height: topHeight - 56),
                  Expanded(
                    child: CustomPaint(
                      painter: _WavePainter(color: brandColor),
                      child: const SizedBox.expand(),
                    ),
                  ),
                ],
              ),
            ),
            SafeArea(
              child: Form(
                key: _formKey,
                child: Column(
                  children: [
                    Align(
                      alignment: Alignment.topRight,
                      child: TextButton.icon(
                        onPressed: auth.isLoading
                            ? null
                            : () => setState(() {
                                  _showPasswordLogin = !_showPasswordLogin;
                                  auth.clearError();
                                }),
                        icon: Icon(
                          _showPasswordLogin ? Icons.sms_outlined : Icons.lock_outline,
                          size: 18,
                          color: Colors.black54,
                        ),
                        label: Text(
                          _showPasswordLogin ? 'SMS Login' : 'Password',
                          style: const TextStyle(color: Colors.black54),
                        ),
                      ),
                    ),
                    SizedBox(
                      height: topHeight - 88,
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 32),
                          child: AppBrandLogo(height: 120, fallbackColor: brandColor),
                        ),
                      ),
                    ),
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(28, 48, 28, 24),
                        child: _showPasswordLogin
                            ? _PasswordLoginFields(
                                emailController: _emailController,
                                passwordController: _passwordController,
                                obscurePassword: _obscurePassword,
                                onToggleObscure: () =>
                                    setState(() => _obscurePassword = !_obscurePassword),
                                auth: auth,
                                onSubmit: _submitPasswordLogin,
                              )
                            : _SmsLoginFields(
                                phoneController: _phoneController,
                                otpController: _otpController,
                                otpRequested: _otpRequested,
                                auth: auth,
                                onSendOtp: _sendOtp,
                                onVerifyOtp: _verifyOtp,
                                onRegister: () => context.push('/register'),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _SmsLoginFields extends StatelessWidget {
  const _SmsLoginFields({
    required this.phoneController,
    required this.otpController,
    required this.otpRequested,
    required this.auth,
    required this.onSendOtp,
    required this.onVerifyOtp,
    required this.onRegister,
  });

  final TextEditingController phoneController;
  final TextEditingController otpController;
  final bool otpRequested;
  final AuthProvider auth;
  final VoidCallback onSendOtp;
  final VoidCallback onVerifyOtp;
  final VoidCallback onRegister;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _WhiteField(
          controller: phoneController,
          hintText: 'Enter Mobile Number',
          keyboardType: TextInputType.phone,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
            LengthLimitingTextInputFormatter(10),
          ],
          validator: (v) {
            if (v == null || v.trim().isEmpty) return 'Enter mobile number';
            if (v.trim().length < 10) return 'Enter a valid 10-digit number';
            return null;
          },
        ),
        if (otpRequested) ...[
          const SizedBox(height: 14),
          _WhiteField(
            controller: otpController,
            hintText: 'Enter OTP',
            keyboardType: TextInputType.number,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(6),
            ],
            validator: (v) {
              if (v == null || v.trim().isEmpty) return 'Enter OTP';
              if (v.trim().length != 6) return 'OTP must be 6 digits';
              return null;
            },
          ),
        ],
        if (auth.error != null) ...[
          const SizedBox(height: 12),
          Text(
            auth.error!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ],
        const SizedBox(height: 18),
        _GreyButton(
          label: otpRequested ? 'RESEND OTP' : 'GET OTP',
          onPressed: auth.isLoading ? null : onSendOtp,
        ),
        const SizedBox(height: 12),
        if (otpRequested)
          _GreyButton(
            label: 'VERIFY & LOGIN',
            onPressed: auth.isLoading ? null : onVerifyOtp,
            loading: auth.isLoading,
          )
        else
          _GreyButton(
            label: 'REGISTER YOURSELF',
            onPressed: auth.isLoading ? null : onRegister,
          ),
        const SizedBox(height: 28),
        const Text(
          'Welcome',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.2,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Login with your mobile number',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.white70, fontSize: 13),
        ),
      ],
    );
  }
}

class _PasswordLoginFields extends StatelessWidget {
  const _PasswordLoginFields({
    required this.emailController,
    required this.passwordController,
    required this.obscurePassword,
    required this.onToggleObscure,
    required this.auth,
    required this.onSubmit,
  });

  final TextEditingController emailController;
  final TextEditingController passwordController;
  final bool obscurePassword;
  final VoidCallback onToggleObscure;
  final AuthProvider auth;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _WhiteField(
          controller: emailController,
          hintText: 'Email',
          keyboardType: TextInputType.emailAddress,
          validator: (v) {
            if (v == null || v.isEmpty) return 'Enter your email';
            if (!v.contains('@')) return 'Enter a valid email';
            return null;
          },
        ),
        const SizedBox(height: 14),
        _WhiteField(
          controller: passwordController,
          hintText: 'Password',
          obscureText: obscurePassword,
          suffix: IconButton(
            icon: Icon(obscurePassword ? Icons.visibility_off : Icons.visibility, color: Colors.black45),
            onPressed: onToggleObscure,
          ),
          validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
        ),
        if (auth.error != null) ...[
          const SizedBox(height: 12),
          Text(
            auth.error!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ],
        const SizedBox(height: 18),
        _GreyButton(
          label: 'LOGIN',
          onPressed: auth.isLoading ? null : onSubmit,
          loading: auth.isLoading,
        ),
      ],
    );
  }
}

class _WhiteField extends StatelessWidget {
  const _WhiteField({
    required this.controller,
    required this.hintText,
    this.keyboardType,
    this.inputFormatters,
    this.validator,
    this.obscureText = false,
    this.suffix,
  });

  final TextEditingController controller;
  final String hintText;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final String? Function(String?)? validator;
  final bool obscureText;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      obscureText: obscureText,
      style: const TextStyle(color: Colors.black87, fontSize: 16),
      decoration: InputDecoration(
        hintText: hintText,
        hintStyle: const TextStyle(color: Colors.black38),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide.none,
        ),
        suffixIcon: suffix,
        errorStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
      ),
      validator: validator,
    );
  }
}

class _GreyButton extends StatelessWidget {
  const _GreyButton({
    required this.label,
    required this.onPressed,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: _buttonGrey,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            letterSpacing: 0.8,
            fontSize: 15,
          ),
        ),
        child: loading
            ? const SizedBox(
                height: 22,
                width: 22,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : Text(label),
      ),
    );
  }
}

class _WavePainter extends CustomPainter {
  const _WavePainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    final path = Path()
      ..moveTo(0, 36)
      ..quadraticBezierTo(size.width * 0.25, 8, size.width * 0.5, 28)
      ..quadraticBezierTo(size.width * 0.75, 52, size.width, 16)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _WavePainter oldDelegate) => oldDelegate.color != color;
}
