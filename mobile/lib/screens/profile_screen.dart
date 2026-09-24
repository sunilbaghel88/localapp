import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../core/app_keys.dart';
import '../models/user.dart';
import '../providers/auth_provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  String _displayEmail(String email) {
    if (email.endsWith('@phone.localapp')) return '';
    return email;
  }

  String _subtitle(User user) {
    final email = _displayEmail(user.email);
    final phone = (user.phone ?? '').trim();
    if (email.isNotEmpty && phone.isNotEmpty) return '$email\n$phone';
    if (email.isNotEmpty) return email;
    if (phone.isNotEmpty) return phone;
    return '';
  }

  Future<void> _openEditDialog(BuildContext context, User user) async {
    final name = TextEditingController(text: user.name);
    final email = TextEditingController(text: _displayEmail(user.email));
    final phone = TextEditingController(text: user.phone ?? '');
    final formKey = GlobalKey<FormState>();

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              if (!(formKey.currentState?.validate() ?? false)) return;
              setDlg(() => saving = true);
              final auth = context.read<AuthProvider>();
              final ok = await auth.updateProfile(
                name: name.text.trim(),
                email: email.text.trim(),
                phone: phone.text.trim(),
              );
              if (!ctx.mounted) return;
              if (ok) {
                Navigator.of(dialogContext).pop();
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Profile updated')),
                  );
                }
              } else {
                setDlg(() => saving = false);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(auth.error ?? 'Could not update profile')),
                  );
                }
              }
            }

            return AlertDialog(
              title: const Text('Edit profile'),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: SizedBox(
                    width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        TextFormField(
                          controller: name,
                          enabled: !saving,
                          textCapitalization: TextCapitalization.words,
                          decoration: const InputDecoration(
                            labelText: 'Name *',
                            border: OutlineInputBorder(),
                          ),
                          validator: (v) {
                            if (v == null || v.trim().isEmpty) return 'Enter your name';
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: email,
                          enabled: !saving,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(
                            labelText: 'Email',
                            border: OutlineInputBorder(),
                          ),
                          validator: (v) {
                            final value = (v ?? '').trim();
                            if (value.isEmpty) return null;
                            if (!value.contains('@') || !value.contains('.')) {
                              return 'Enter a valid email';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: phone,
                          enabled: !saving,
                          keyboardType: TextInputType.phone,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(10),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Mobile *',
                            border: OutlineInputBorder(),
                          ),
                          validator: (v) {
                            if (v == null || v.trim().length < 10) {
                              return 'Enter a valid 10-digit mobile number';
                            }
                            return null;
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );

    name.dispose();
    email.dispose();
    phone.dispose();
  }

  Future<void> _openResetPasswordDialog(BuildContext context) async {
    final currentPassword = TextEditingController();
    final password = TextEditingController();
    final password2 = TextEditingController();
    final formKey = GlobalKey<FormState>();
    var obscureCurrent = true;
    var obscureNew = true;
    var obscureConfirm = true;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        var saving = false;
        return StatefulBuilder(
          builder: (ctx, setDlg) {
            Future<void> save() async {
              if (saving) return;
              if (!(formKey.currentState?.validate() ?? false)) return;
              setDlg(() => saving = true);
              final auth = context.read<AuthProvider>();
              final ok = await auth.updatePassword(
                currentPassword: currentPassword.text,
                password: password.text,
                passwordConfirmation: password2.text,
              );
              if (!ctx.mounted) return;
              if (ok) {
                Navigator.of(dialogContext).pop();
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Password updated')),
                  );
                }
              } else {
                setDlg(() => saving = false);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(auth.error ?? 'Could not update password')),
                  );
                }
              }
            }

            return AlertDialog(
              title: const Text('Reset password'),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: SizedBox(
                    width: MediaQuery.sizeOf(context).width < 560 ? double.maxFinite : 400,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        TextFormField(
                          controller: currentPassword,
                          enabled: !saving,
                          obscureText: obscureCurrent,
                          decoration: InputDecoration(
                            labelText: 'Current password *',
                            border: const OutlineInputBorder(),
                            suffixIcon: IconButton(
                              icon: Icon(obscureCurrent ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                              onPressed: () => setDlg(() => obscureCurrent = !obscureCurrent),
                            ),
                          ),
                          validator: (v) {
                            if (v == null || v.isEmpty) return 'Enter your current password';
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: password,
                          enabled: !saving,
                          obscureText: obscureNew,
                          decoration: InputDecoration(
                            labelText: 'New password *',
                            border: const OutlineInputBorder(),
                            suffixIcon: IconButton(
                              icon: Icon(obscureNew ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                              onPressed: () => setDlg(() => obscureNew = !obscureNew),
                            ),
                          ),
                          validator: (v) {
                            if (v == null || v.length < 8) return 'Use at least 8 characters';
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: password2,
                          enabled: !saving,
                          obscureText: obscureConfirm,
                          decoration: InputDecoration(
                            labelText: 'Confirm new password *',
                            border: const OutlineInputBorder(),
                            suffixIcon: IconButton(
                              icon: Icon(obscureConfirm ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                              onPressed: () => setDlg(() => obscureConfirm = !obscureConfirm),
                            ),
                          ),
                          validator: (v) {
                            if (v != password.text) return 'Passwords do not match';
                            return null;
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(dialogContext).pop(),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );

    currentPassword.dispose();
    password.dispose();
    password2.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (!auth.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('Please log in to view your profile.'),
              FilledButton(
                onPressed: () => context.push('/login'),
                child: const Text('Login'),
              ),
            ],
          ),
        ),
      );
    }
    final user = auth.user!;
    final permissions = user.permissions ?? const [];
    final canManageProducts = permissions.contains('view_any_product');
    final canManageOrders = permissions.contains('view_any_order');
    final canManageShops = permissions.contains('view_any_shop') ||
        permissions.contains('create_shop');
    final subtitle = _subtitle(user);
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: CircleAvatar(
                child: Text(
                  user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
                ),
              ),
              title: Text(user.name),
              subtitle: subtitle.isEmpty ? null : Text(subtitle),
              isThreeLine: subtitle.contains('\n'),
              trailing: IconButton(
                icon: const Icon(Icons.edit_outlined),
                tooltip: 'Edit profile',
                onPressed: () => _openEditDialog(context, user),
              ),
            ),
          ),
          const SizedBox(height: 16),
          ListTile(
            leading: const Icon(Icons.location_on_outlined),
            title: const Text('Addresses'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/addresses'),
          ),
          ListTile(
            leading: const Icon(Icons.shopping_bag_outlined),
            title: Text(canManageOrders ? 'Manage Orders' : 'My Orders'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () =>
                context.push(canManageOrders ? '/owner/orders' : '/orders'),
          ),
          if (canManageShops)
            ListTile(
              leading: const Icon(Icons.storefront_outlined),
              title: const Text('Manage Shops'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/owner/shops'),
            ),
          if (canManageProducts) ...[
            const SizedBox(height: 16),
            ListTile(
              leading: const Icon(Icons.inventory_2_outlined),
              title: const Text('Manage Products'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/owner/products'),
            ),
          ],
          const Divider(),
          ListTile(
            leading: const Icon(Icons.lock_reset_outlined),
            title: const Text('Reset password'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _openResetPasswordDialog(context),
          ),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Logout'),
            onTap: () async {
              await auth.logout();
              if (!context.mounted) return;
              rootScaffoldMessengerKey.currentState?.showSnackBar(
                const SnackBar(content: Text('Logged out successfully')),
              );
              context.go('/login');
            },
          ),
        ],
      ),
    );
  }
}
