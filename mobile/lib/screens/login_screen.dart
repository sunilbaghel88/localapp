import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends StatelessWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Login')),
      body: const _LoginForm(),
    );
  }
}

class _LoginForm extends StatefulWidget {
  const _LoginForm();

  @override
  State<_LoginForm> createState() => _LoginFormState();
}

class _LoginFormState extends State<_LoginForm> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _otpController = TextEditingController();
  bool _obscurePassword = true;
  bool _otpRequested = false;
  LoginMethod _loginMethod = LoginMethod.password;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _submitPasswordLogin() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.login(_emailController.text.trim(), _passwordController.text);
    if (!mounted) return;
    if (ok) {
      context.go('/home');
    }
  }

  Future<void> _sendOtp() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.requestEmailOtp(_emailController.text.trim());
    if (!mounted) return;
    if (ok) {
      setState(() => _otpRequested = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('OTP sent to your email')),
      );
    }
  }

  Future<void> _submitOtpLogin() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.loginWithOtp(_emailController.text.trim(), _otpController.text.trim());
    if (!mounted) return;
    if (ok) {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 24),
                SegmentedButton<LoginMethod>(
                  segments: const [
                    ButtonSegment(
                      value: LoginMethod.password,
                      label: Text('Password'),
                      icon: Icon(Icons.lock_outline),
                    ),
                    ButtonSegment(
                      value: LoginMethod.otp,
                      label: Text('Email OTP'),
                      icon: Icon(Icons.password_outlined),
                    ),
                  ],
                  selected: {_loginMethod},
                  onSelectionChanged: auth.isLoading
                      ? null
                      : (selection) {
                          setState(() {
                            _loginMethod = selection.first;
                            if (_loginMethod == LoginMethod.password) {
                              _otpRequested = false;
                              _otpController.clear();
                            }
                          });
                          auth.clearError();
                        },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    border: OutlineInputBorder(),
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                  validator: (v) {
                    if (v == null || v.isEmpty) return 'Enter your email';
                    if (!v.contains('@')) return 'Enter a valid email';
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                if (_loginMethod == LoginMethod.password)
                  TextFormField(
                    controller: _passwordController,
                    obscureText: _obscurePassword,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      border: const OutlineInputBorder(),
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility),
                        onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                      ),
                    ),
                    validator: (v) {
                      if (_loginMethod != LoginMethod.password) return null;
                      return (v == null || v.isEmpty) ? 'Enter your password' : null;
                    },
                  )
                else
                  TextFormField(
                    controller: _otpController,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: const InputDecoration(
                      labelText: 'OTP',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.verified_user_outlined),
                      counterText: '',
                    ),
                    validator: (v) {
                      if (_loginMethod != LoginMethod.otp) return null;
                      if (v == null || v.trim().isEmpty) return 'Enter OTP';
                      if (v.trim().length != 6) return 'OTP must be 6 digits';
                      return null;
                    },
                  ),
                if (auth.error != null) ...[
                  const SizedBox(height: 12),
                  Text(auth.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 24),
                if (_loginMethod == LoginMethod.password)
                  FilledButton(
                    onPressed: auth.isLoading ? null : _submitPasswordLogin,
                    child: auth.isLoading
                        ? const SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Login'),
                  )
                else ...[
                  OutlinedButton(
                    onPressed: auth.isLoading ? null : _sendOtp,
                    child: Text(_otpRequested ? 'Resend OTP' : 'Send OTP'),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: auth.isLoading ? null : _submitOtpLogin,
                    child: auth.isLoading
                        ? const SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Login with OTP'),
                  ),
                ],
                const SizedBox(height: 16),
                TextButton(
                  onPressed: () => context.push('/register'),
                  child: const Text("Don't have an account? Register"),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

enum LoginMethod {
  password,
  otp,
}
