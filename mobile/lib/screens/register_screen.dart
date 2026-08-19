import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../widgets/app_brand_logo.dart';

const _buttonGrey = Color(0xFF8E8E8E);

class RegisterScreen extends StatelessWidget {
  const RegisterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Colors.white,
      body: _SmsRegisterBody(),
    );
  }
}

class _SmsRegisterBody extends StatefulWidget {
  const _SmsRegisterBody();

  @override
  State<_SmsRegisterBody> createState() => _SmsRegisterBodyState();
}

class _SmsRegisterBodyState extends State<_SmsRegisterBody> {
  final _formKey = GlobalKey<FormState>();
  final _api = ApiService();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();
  bool _otpRequested = false;
  List<Map<String, dynamic>> _userTypes = [];
  int? _selectedUserTypeId;
  bool _loadingTypes = true;

  @override
  void initState() {
    super.initState();
    _loadUserTypes();
  }

  Future<void> _loadUserTypes() async {
    try {
      final types = await _api.getUserTypes();
      if (mounted) {
        setState(() {
          _userTypes = types;
          _loadingTypes = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingTypes = false);
    }
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _phoneController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _resendOtp() async {
    final phone = _phoneController.text.trim();
    if (phone.length < 10) {
      _formKey.currentState?.validate();
      return;
    }
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.requestSmsOtp(phone, purpose: 'register');
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('OTP sent to your mobile number')),
      );
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final phone = _phoneController.text.trim();

    if (!_otpRequested) {
      final ok = await auth.requestSmsOtp(phone, purpose: 'register');
      if (!mounted) return;
      if (ok) {
        setState(() => _otpRequested = true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('OTP sent to your mobile number')),
        );
      }
      return;
    }

    final ok = await auth.registerWithSmsOtp(
      firstName: _firstNameController.text.trim(),
      lastName: _lastNameController.text.trim(),
      phone: phone,
      otp: _otpController.text.trim(),
      userTypeId: _selectedUserTypeId,
    );
    if (!mounted) return;
    if (ok) context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final brandColor = Theme.of(context).colorScheme.primary;
    final topHeight = size.height * 0.24;

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
                      alignment: Alignment.centerLeft,
                      child: IconButton(
                        onPressed: () => context.pop(),
                        icon: const Icon(Icons.arrow_back, color: Colors.black87),
                      ),
                    ),
                    SizedBox(
                      height: topHeight - 72,
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 32),
                          child: AppBrandLogo(
                            height: 100,
                            fallbackColor: Theme.of(context).colorScheme.primary,
                          ),
                        ),
                      ),
                    ),
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(28, 48, 28, 24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Text(
                              'REGISTER YOURSELF',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 16),
                            _WhiteField(
                              controller: _firstNameController,
                              hintText: 'First name',
                              textCapitalization: TextCapitalization.words,
                              validator: (v) =>
                                  (v == null || v.trim().isEmpty) ? 'Enter first name' : null,
                            ),
                            const SizedBox(height: 12),
                            _WhiteField(
                              controller: _lastNameController,
                              hintText: 'Last name',
                              textCapitalization: TextCapitalization.words,
                              validator: (v) =>
                                  (v == null || v.trim().isEmpty) ? 'Enter last name' : null,
                            ),
                            const SizedBox(height: 12),
                            _WhiteField(
                              controller: _phoneController,
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
                            if (_otpRequested) ...[
                              const SizedBox(height: 12),
                              _WhiteField(
                                controller: _otpController,
                                hintText: 'Enter OTP',
                                keyboardType: TextInputType.number,
                                inputFormatters: [
                                  FilteringTextInputFormatter.digitsOnly,
                                  LengthLimitingTextInputFormatter(6),
                                ],
                                validator: (v) {
                                  if (!_otpRequested) return null;
                                  if (v == null || v.trim().isEmpty) return 'Enter OTP';
                                  if (v.trim().length != 6) return 'OTP must be 6 digits';
                                  return null;
                                },
                              ),
                            ],
                            if (!_loadingTypes && _userTypes.isNotEmpty) ...[
                              const SizedBox(height: 12),
                              DropdownButtonFormField<int?>(
                                initialValue: _selectedUserTypeId,
                                decoration: InputDecoration(
                                  hintText: 'User type (optional)',
                                  filled: true,
                                  fillColor: Colors.white,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(10),
                                    borderSide: BorderSide.none,
                                  ),
                                ),
                                items: _userTypes
                                    .where((t) => t['id'] != null)
                                    .map((t) {
                                      final id = t['id'] as int;
                                      final name = (t['name'] ?? '').toString();
                                      return DropdownMenuItem<int?>(
                                        value: id,
                                        child: Text(name),
                                      );
                                    })
                                    .toList(),
                                onChanged: auth.isLoading
                                    ? null
                                    : (v) => setState(() => _selectedUserTypeId = v),
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
                              label: 'CREATE ACCOUNT',
                              onPressed: auth.isLoading ? null : _submit,
                              loading: auth.isLoading,
                            ),
                            if (_otpRequested) ...[
                              const SizedBox(height: 8),
                              TextButton(
                                onPressed: auth.isLoading ? null : _resendOtp,
                                child: const Text(
                                  'Resend OTP',
                                  style: TextStyle(color: Colors.white),
                                ),
                              ),
                            ],
                            const SizedBox(height: 16),
                            TextButton(
                              onPressed: () => context.go('/login'),
                              child: const Text(
                                'Already registered? Login',
                                style: TextStyle(color: Colors.white),
                              ),
                            ),
                          ],
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

class _WhiteField extends StatelessWidget {
  const _WhiteField({
    required this.controller,
    required this.hintText,
    this.keyboardType,
    this.inputFormatters,
    this.validator,
    this.textCapitalization = TextCapitalization.none,
  });

  final TextEditingController controller;
  final String hintText;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final String? Function(String?)? validator;
  final TextCapitalization textCapitalization;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      textCapitalization: textCapitalization,
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
