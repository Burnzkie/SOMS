class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.role,
    required this.studentId,
    required this.mustChangePassword,
  });

  final int id;
  final String name;
  final String role; // admin | officer | student
  final String studentId;
  final bool mustChangePassword;

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        role: json['role'] as String? ?? 'student',
        studentId: json['student_id'] as String? ?? '',
        mustChangePassword: json['must_change_password'] as bool? ?? false,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'role': role,
        'student_id': studentId,
        'must_change_password': mustChangePassword,
      };

  AppUser copyWith({bool? mustChangePassword}) => AppUser(
        id: id,
        name: name,
        role: role,
        studentId: studentId,
        mustChangePassword: mustChangePassword ?? this.mustChangePassword,
      );
}
