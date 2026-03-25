<?php
declare(strict_types=1);

namespace StayFlow\Booking;

if (!defined('ABSPATH')) {
    exit;
}

final class CancellationManager
{
    private string $secretKey;

    public function __construct() {
        $this->secretKey = defined('AUTH_KEY') ? AUTH_KEY : 'stayflow_fallback';
    }

    public function generateToken(int $bookingId, string $email): string {
        return hash_hmac('sha256', $bookingId . strtolower(trim($email)), $this->secretKey);
    }

    public function validateToken(string $token, int $bookingId, string $email): bool {
        return hash_equals($this->generateToken($bookingId, $email), $token);
    }

    public function getCancellationStatus(int $bookingId): array
    {
        if (!function_exists('MPHB')) throw new \Exception('MPHB not found.');

        $booking = \MPHB()->getBookingRepository()->findById($bookingId);
        if (!$booking) throw new \Exception('Booking not found.');

        $checkInDate = $booking->getCheckInDate();
        $reservedRooms = $booking->getReservedRooms();
        $roomTypeId = !empty($reservedRooms) ? $reservedRooms[0]->getRoomTypeId() : 0;
        
        // Проверяем политику / Check policy
        $policyType = get_post_meta((int)$roomTypeId, '_sf_cancellation_policy', true);
        $cancellationDays = (int) get_post_meta((int)$roomTypeId, '_sf_cancellation_days', true);

        // Если политика явно Non-refundable или дни не заданы
        if ($policyType === 'non_refundable' || $cancellationDays <= 0) {
            return [
                'is_free'       => false,
                'deadline_date' => '1970-01-01 00:00:00', // Метка для UI
                'penalty'       => 100,
                'check_in'      => $checkInDate->format('Y-m-d')
            ];
        }

        $deadlineDate = clone $checkInDate;
        $deadlineDate->modify("-{$cancellationDays} days");
        $deadlineDate->setTime(23, 59, 59);

        $now = new \DateTime('now', wp_timezone());
        $isFree = $now <= $deadlineDate;

        return [
            'is_free'       => $isFree,
            'deadline_date' => $deadlineDate->format('Y-m-d H:i:s'),
            'penalty'       => $isFree ? 0 : 100,
            'check_in'      => $checkInDate->format('Y-m-d')
        ];
    }
}