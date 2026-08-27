class SomsAnnouncement {
  const SomsAnnouncement({
    required this.id,
    required this.title,
    required this.body,
    required this.isPublished,
    required this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final bool isPublished;
  final String createdAt;

  factory SomsAnnouncement.fromJson(Map<String, dynamic> json) => SomsAnnouncement(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
        isPublished: json['is_published'] as bool? ?? false,
        createdAt: json['created_at'] as String? ?? '',
      );
}
