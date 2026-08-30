// lib/models/user_profile.dart

/// Mirrors the `data` object returned by GET/PUT /api/v1/profile.
/// role and studentId are read-only on the client — the backend never
/// accepts them in the update payload (see ProfileController).
class UserProfile {
  final int id;
  final String studentId;
  final String name;
  final String email;
  final String? department;
  final String? program;
  final String? level;
  final String role;

  const UserProfile({
    required this.id,
    required this.studentId,
    required this.name,
    required this.email,
    required this.department,
    required this.program,
    required this.level,
    required this.role,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      id: json['id'] as int,
      studentId: json['student_id'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      department: json['department'] as String?,
      program: json['program'] as String?,
      level: json['level'] as String?,
      role: json['role'] as String,
    );
  }

  UserProfile copyWith({
    String? name,
    String? email,
    String? department,
    String? program,
    String? level,
  }) {
    return UserProfile(
      id: id,
      studentId: studentId,
      name: name ?? this.name,
      email: email ?? this.email,
      department: department ?? this.department,
      program: program ?? this.program,
      level: level ?? this.level,
      role: role,
    );
  }
}
