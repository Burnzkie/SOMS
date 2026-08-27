/// Matches Laravel's Paginator::toJson() shape exactly — every list
/// endpoint in this app returns this, so one generic wrapper covers all
/// of them instead of hand-rolling pagination fields per model.
class Paginated<T> {
  const Paginated({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<T> data;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory Paginated.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) fromJson) {
    final rawData = json['data'] as List<dynamic>? ?? [];
    return Paginated<T>(
      data: rawData.map((e) => fromJson(e as Map<String, dynamic>)).toList(),
      currentPage: json['current_page'] as int? ?? 1,
      lastPage: json['last_page'] as int? ?? 1,
      total: json['total'] as int? ?? rawData.length,
    );
  }

  static const empty = Paginated(data: [], currentPage: 1, lastPage: 1, total: 0);
}
