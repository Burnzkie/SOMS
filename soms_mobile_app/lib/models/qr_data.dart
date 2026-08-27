/// GET /api/v1/student/qr — see StudentApiController::qrCurrent. The
/// backend now issues a live rotating HMAC token (~60s validity) rather
/// than a static stored QR, so this must be re-fetched periodically
/// rather than cached/rendered once. See QrScreen for the polling timer.
class QrData {
  const QrData({
    required this.userId,
    required this.token,
    required this.qrPayload,
    required this.expiresIn,
    required this.serverTime,
  });

  final int userId;
  final String token;
  final String qrPayload; // "<user_id>:<token>" — what's actually encoded
  final int expiresIn; // seconds
  final int serverTime; // Unix timestamp — see QrTokenService::current()

  factory QrData.fromJson(Map<String, dynamic> json) => QrData(
        userId: json['user_id'] as int,
        token: json['token'] as String? ?? '',
        qrPayload: json['qr_payload'] as String? ?? '',
        expiresIn: json['expires_in'] as int? ?? 60,
        serverTime: json['server_time'] as int? ?? 0,
      );
}
